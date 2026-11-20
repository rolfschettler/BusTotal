import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnInit, computed, inject, signal } from '@angular/core';
import { AbstractControl, FormBuilder, FormControl, FormGroup, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { BrandService, textOn } from '../../core/brand.service';
import { Depot, DepotService } from '../../core/depot.service';
import { GERMAN_STATES } from '../../core/german-states';
import { ConfirmService } from '../../core/confirm.service';
import { httpErrorText } from '../../core/http-error';
import { HelpLinkComponent } from '../../help/help-link.component';
import { HelpService } from '../../help/help.service';
import { PricingService, PricingSettings, VehicleRateField } from '../../core/pricing.service';
import { NumberRange, SettingsService, TaxRate } from '../../core/settings.service';

type Tab = 'firma' | 'steuern' | 'nummern' | 'preise' | 'betriebshoefe';

/** Eine Zeile der Betriebshof-Tabelle: die Eingaben, dazu der zuletzt gespeicherte Stand und der gefundene Ort. */
interface DepotRow extends Depot {
  /** gespeicherter Stand: ohne Änderung gibt es nichts zu speichern */
  savedName: string;
  savedAddress: string;
  busy: boolean;
  error: string | null;
}

const toRow = (d: Depot): DepotRow => ({ ...d, savedName: d.name, savedAddress: d.address, busy: false, error: null });

const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp'];
const LOGO_MAX_BYTES = 1024 * 1024;

@Component({
  selector: 'app-settings',
  imports: [ReactiveFormsModule, FormsModule, HelpLinkComponent],
  templateUrl: './settings.component.html',
  styleUrl: './settings.component.scss',
})
export class SettingsComponent implements OnInit {
  private api = inject(SettingsService);
  private pricingApi = inject(PricingService);
  private depotApi = inject(DepotService);
  private fb = inject(FormBuilder);
  private auth = inject(AuthService);
  brand = inject(BrandService);
  private help = inject(HelpService);
  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private router = inject(Router);
  private confirm = inject(ConfirmService);

  canEdit = computed(() => this.auth.user()?.role === 'admin');
  tab = signal<Tab>('firma');
  message = signal<{ ok: boolean; text: string } | null>(null);
  fieldErrors = signal<Record<string, string>>({});
  saving = signal(false);
  /** Nach dem ersten Speichern-Versuch markiert das Formular fehlerhafte Felder (Formular-Muster). */
  formSubmitted = signal(false);
  /** Zuletzt gespeicherter Stand, auf den "Abbrechen" zurücksetzt. */
  private saved: unknown = null;

  company = this.fb.group({
    name: ['', Validators.required],
    legal_form: [''],
    street: [''],
    postal_code: [''],
    city: [''],
    country: ['DE', [Validators.required, Validators.minLength(2), Validators.maxLength(2)]],
    state: [''],
    phone: [''],
    email: ['', Validators.email],
    website: [''],
    tax_number: [''],
    vat_id: [''],
    commercial_register: [''],
    managing_director: [''],
    bank_name: [''],
    iban: [''],
    bic: [''],
    letterhead_note: [''],
    footer_text: [''],
    payment_term_days: [14, [Validators.required, Validators.min(0)]],
    brand_color: ['#1d4e89', Validators.pattern(/^#[0-9a-fA-F]{6}$/)],
  });

  readonly textOn = textOn;

  /** Preisparameter der Preisfindung (Reiter "Preise"). 0 schaltet einen Bestandteil aus. */
  pricing = this.fb.nonNullable.group({
    price_per_km: [0, [Validators.required, Validators.min(0)]],
    empty_km_percent: [100, [Validators.required, Validators.min(0), Validators.max(100)]],
    hourly_rate: [0, [Validators.required, Validators.min(0)]],
    day_rate: [0, [Validators.required, Validators.min(0)]],
    overnight_rate: [0, [Validators.required, Validators.min(0)]],
    minimum_price: [0, [Validators.required, Validators.min(0)]],
    tax_rate: [19, [Validators.required, Validators.min(0), Validators.max(100)]],
    surcharge_saturday_percent: [0, [Validators.required, Validators.min(0), Validators.max(300)]],
    surcharge_sunday_percent: [0, [Validators.required, Validators.min(0), Validators.max(300)]],
    surcharge_holiday_percent: [0, [Validators.required, Validators.min(0), Validators.max(300)]],
    // Preise je Fahrzeugtyp: je Typ eine Gruppe, entsteht beim Laden aus der Typenliste des Servers. Leer = Standardpreis.
    vehicle_rates: this.fb.group<Record<string, FormGroup>>({}),
  });
  vehicleTypes = signal<{ value: string; label: string }[]>([]);
  /** Bundesländer für die Auswahl in den Firmendaten */
  readonly states = signal(GERMAN_STATES);
  readonly rateFields: { key: VehicleRateField; label: string; step: string }[] = [
    { key: 'price_per_km', label: 'Preis je km (€)', step: '0.01' },
    { key: 'hourly_rate', label: 'Stundensatz (€)', step: '0.01' },
    { key: 'day_rate', label: 'Tagespauschale (€)', step: '0.01' },
    { key: 'overnight_rate', label: 'Übernachtung (€)', step: '0.01' },
    { key: 'minimum_price', label: 'Mindestpreis (€)', step: '0.01' },
  ];
  pricingSubmitted = signal(false);
  private pricingSaved: unknown = null;

  depots = signal<DepotRow[]>([]);
  newDepot = { name: '', address: '' };
  newDepotError = signal<string | null>(null);
  newDepotBusy = signal(false);

  taxRates = signal<TaxRate[]>([]);
  newTax = { name: '', rate: 0 };
  ranges = signal<NumberRange[]>([]);

  ngOnInit() {
    // Eine Server-Fehlermeldung gilt nur für den abgelehnten Wert: wird das Feld geändert, verschwindet sie.
    for (const [name, control] of Object.entries<AbstractControl>(this.company.controls)) {
      control.valueChanges.subscribe(() => {
        if (this.fieldErrors()[name]) {
          const { [name]: _gone, ...rest } = this.fieldErrors();
          this.fieldErrors.set(rest);
          // Die Fehlermeldung oben gehörte zu diesem Feld: mit der Korrektur verschwindet auch sie.
          if (Object.keys(rest).length === 0 && this.message()?.ok === false) this.message.set(null);
        }
      });
    }
    this.api.getCompany().subscribe((c) => {
      this.company.patchValue(c);
      this.saved = this.company.getRawValue();
      this.company.markAsPristine();
      if (!this.canEdit()) this.company.disable();
    });
    this.loadTaxRates();
    this.loadRanges();
    this.loadDepots();
    this.pricingApi.get().subscribe({
      next: (p) => {
        this.applyPricing(p);
        if (!this.canEdit()) this.pricing.disable();
      },
      // Ohne geladene Preise darf der Reiter nicht still leer bleiben: sonst sieht es aus, als gäbe es keine Preise.
      error: (e) => this.notify(false, httpErrorText(e, 'Die Preise konnten nicht geladen werden.')),
    });
  }

  /** Server-Stand ins Formular übernehmen (beim Laden und nach dem Speichern) und als Stand für "Abbrechen" merken. */
  private applyPricing(p: PricingSettings) {
    this.vehicleTypes.set(p.vehicle_types);
    const rates = this.pricing.controls.vehicle_rates;
    for (const t of p.vehicle_types) {
      if (!rates.contains(t.value)) {
        rates.addControl(t.value, this.fb.group(Object.fromEntries(this.rateFields.map((f) => [f.key, new FormControl<number | null>(null, Validators.min(0))]))));
      }
    }
    const own = Object.fromEntries(
      p.vehicle_types.map((t) => [t.value, Object.fromEntries(this.rateFields.map((f) => [f.key, p.vehicle_rates[t.value]?.[f.key] == null ? null : Number(p.vehicle_rates[t.value][f.key])]))]),
    );
    this.pricing.reset({ ...this.pricingToForm(p), vehicle_rates: own } as never);
    this.pricingSaved = this.pricing.getRawValue();
  }

  private pricingToForm(p: PricingSettings) {
    return Object.fromEntries(
      Object.entries(p as unknown as Record<string, string | number>)
        .filter(([k]) => k in this.pricing.controls && k !== 'vehicle_rates')
        .map(([k, v]) => [k, Number(v)]),
    );
  }

  /** Platzhalter eines Typ-Feldes: der Standardpreis, der gilt, solange das Feld leer ist. */
  standardOf(key: VehicleRateField): string {
    const v = this.pricing.controls[key].value;
    return `Standard: ${v}`;
  }

  /** Hat der Typ mindestens einen eigenen Preis? (für die Kennzeichnung der Überschrift) */
  hasOwnRates(type: string): boolean {
    return Object.values(this.pricing.controls.vehicle_rates.get(type)?.getRawValue() ?? {}).some((v) => v !== null && v !== '');
  }

  invalidTypeRate(type: string, key: VehicleRateField): boolean {
    return (this.pricingSubmitted() && !!this.pricing.controls.vehicle_rates.get([type, key])?.invalid) || !!this.fieldErrors()[`vehicle_rates.${type}.${key}`];
  }

  /** Ist im aktuellen Reiter etwas ungespeichert? (Nur Firmendaten und Preise sind Formulare.) */
  get dirty(): boolean {
    return this.tab() === 'preise' ? this.pricing.dirty : this.company.dirty;
  }

  invalidPricing(name: keyof typeof this.pricing.controls): boolean {
    return (this.pricingSubmitted() && this.pricing.controls[name].invalid) || !!this.fieldErrors()[name];
  }

  savePricing() {
    this.pricingSubmitted.set(true);
    if (this.pricing.invalid) {
      this.notify(false, 'Bitte die markierten Felder korrigieren.');
      return;
    }
    this.saving.set(true);
    this.pricingApi.save(this.pricing.getRawValue()).subscribe({
      next: (p) => {
        this.saving.set(false);
        this.applyPricing(p);
        this.pricingSubmitted.set(false);
        this.ok('Preise gespeichert.');
      },
      error: (e) => {
        this.saving.set(false);
        this.fail(e);
      },
    });
  }

  cancelPricing() {
    this.pricing.reset(this.pricingSaved as never);
    this.pricingSubmitted.set(false);
    this.fieldErrors.set({});
    this.message.set(null);
  }

  private loadTaxRates() {
    this.api.getTaxRates().subscribe((t) => this.taxRates.set(t));
  }
  private loadRanges() {
    this.api.getNumberRanges().subscribe((r) => this.ranges.set(r));
  }
  private loadDepots() {
    this.depotApi.list().subscribe({
      next: (list) => this.depots.set(list.map(toRow)),
      error: (e) => this.notify(false, httpErrorText(e, 'Die Betriebshöfe konnten nicht geladen werden.')),
    });
  }

  show(tab: Tab) {
    this.tab.set(tab);
    // Das Hilfefenster folgt dem Reiter.
    this.help.setAnchor({ firma: 'firmendaten', steuern: 'steuersaetze', nummern: 'nummernkreise', preise: 'preise', betriebshoefe: 'betriebshoefe' }[tab]);
    this.message.set(null);
    this.fieldErrors.set({});
  }

  /** Feld rot markieren: Eingabe ungültig (nach Speichern-Versuch) oder vom Server abgelehnt. */
  invalid(name: keyof typeof this.company.controls): boolean {
    return (this.formSubmitted() && this.company.controls[name].invalid) || !!this.fieldErrors()[name];
  }

  /** Tastenleiste "Zurück": zur Startseite, bei ungespeicherten Änderungen erst nachfragen. */
  async back() {
    if (this.dirty) {
      const ok = await this.confirm.ask({
        title: 'Änderungen verwerfen',
        message: 'Es gibt ungespeicherte Änderungen. Trotzdem zurück zur Startseite?',
        confirmLabel: 'Verwerfen',
        danger: true,
      });
      if (!ok) return;
    }
    this.router.navigate(['/']);
  }

  /** Tastenleiste "Abbrechen": Eingaben verwerfen, auf den gespeicherten Stand zurücksetzen. */
  cancel() {
    this.company.reset(this.saved as never);
    this.company.markAsPristine();
    this.formSubmitted.set(false);
    this.fieldErrors.set({});
    this.message.set(null);
  }

  saveCompany() {
    this.formSubmitted.set(true);
    if (this.company.invalid) {
      this.notify(false, 'Bitte die markierten Felder korrigieren.');
      return;
    }
    // Leere Felder als null senden, damit der Server sie nicht als Text "" speichert.
    const raw = this.company.getRawValue() as Record<string, unknown>;
    const body = Object.fromEntries(Object.entries(raw).map(([k, v]) => [k, v === '' ? null : v]));
    this.saving.set(true);
    this.api.saveCompany(body as never).subscribe({
      next: (c) => {
        this.saving.set(false);
        this.company.patchValue(c);
        this.saved = this.company.getRawValue();
        this.company.markAsPristine();
        this.formSubmitted.set(false);
        this.brand.load(); // neue Farbe und Name sofort überall anzeigen
        this.ok('Firmendaten gespeichert.');
      },
      error: (e) => {
        this.saving.set(false);
        this.fail(e);
      },
    });
  }

  // ---------- Logo ----------

  onLogoSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = ''; // dieselbe Datei später erneut wählbar
    if (!file) return;

    // Schnelle Vorprüfung im Browser; maßgeblich ist die Prüfung auf dem Server.
    if (!LOGO_TYPES.includes(file.type)) {
      this.fieldErrors.set({ logo: 'Erlaubt sind PNG, JPG und WebP.' });
      return;
    }
    if (file.size > LOGO_MAX_BYTES) {
      this.fieldErrors.set({ logo: 'Das Logo darf höchstens 1 MB groß sein.' });
      return;
    }

    this.api.uploadLogo(file).subscribe({
      next: () => {
        this.brand.load();
        this.ok('Logo gespeichert.');
      },
      error: (e) => this.fail(e),
    });
  }

  removeLogo() {
    this.api.deleteLogo().subscribe({
      next: () => {
        this.brand.load();
        this.ok('Logo entfernt.');
      },
      error: (e) => this.fail(e),
    });
  }

  // ---------- Steuersätze ----------

  saveTax(t: TaxRate) {
    this.api.saveTaxRate(t).subscribe({
      next: () => this.ok('Steuersatz gespeichert.'),
      error: (e) => this.fail(e),
    });
  }

  addTax() {
    this.api.addTaxRate({ name: this.newTax.name, rate: Number(this.newTax.rate), active: true }).subscribe({
      next: () => {
        this.newTax = { name: '', rate: 0 };
        this.loadTaxRates();
        this.ok('Steuersatz angelegt.');
      },
      error: (e) => this.fail(e),
    });
  }

  // ---------- Betriebshöfe ----------

  depotDirty(d: DepotRow): boolean {
    return d.name !== d.savedName || d.address !== d.savedAddress;
  }

  /** Speichert Name und Adresse. Den Ort dazu sucht der Server, und nur wenn die Adresse neu ist. */
  saveDepot(d: DepotRow) {
    d.busy = true;
    d.error = null;
    this.depotApi.update(d.id, { name: d.name, address: d.address }).subscribe({
      next: () => {
        this.loadDepots();
        this.ok(`Betriebshof „${d.name}“ gespeichert.`);
      },
      error: (e) => {
        d.busy = false;
        d.error = httpErrorText(e, 'Der Betriebshof konnte nicht gespeichert werden.');
      },
    });
  }

  makeDefaultDepot(d: DepotRow) {
    d.busy = true;
    this.depotApi.update(d.id, { name: d.savedName, address: d.savedAddress, is_default: true }).subscribe({
      next: () => {
        this.loadDepots();
        this.ok(`„${d.savedName}“ ist jetzt der Standard.`);
      },
      error: (e) => {
        d.busy = false;
        d.error = httpErrorText(e, 'Der Standard konnte nicht geändert werden.');
      },
    });
  }

  async removeDepot(d: DepotRow) {
    const ok = await this.confirm.ask({
      title: 'Betriebshof löschen',
      message: `„${d.savedName}“ wirklich löschen?`,
      confirmLabel: 'Löschen',
      danger: true,
    });
    if (!ok) return;
    this.depotApi.remove(d.id).subscribe({
      next: () => {
        this.loadDepots();
        this.ok('Betriebshof gelöscht.');
      },
      error: (e) => (d.error = httpErrorText(e, 'Der Betriebshof konnte nicht gelöscht werden.')),
    });
  }

  addDepot() {
    this.newDepotBusy.set(true);
    this.newDepotError.set(null);
    this.depotApi.create({ name: this.newDepot.name.trim(), address: this.newDepot.address.trim() }).subscribe({
      next: (d) => {
        this.newDepotBusy.set(false);
        this.newDepot = { name: '', address: '' };
        this.loadDepots();
        this.ok(`Betriebshof „${d.name}“ angelegt.`);
      },
      error: (e) => {
        this.newDepotBusy.set(false);
        this.newDepotError.set(httpErrorText(e, 'Der Betriebshof konnte nicht angelegt werden.'));
      },
    });
  }

  // ---------- Nummernkreise ----------

  saveRange(r: NumberRange) {
    this.api.saveNumberRange(r).subscribe({
      next: () => {
        this.loadRanges();
        this.ok(`Nummernkreis „${r.label}“ gespeichert.`);
      },
      error: (e) => {
        this.fail(e);
        this.loadRanges();
      },
    });
  }

  private ok(text: string) {
    this.fieldErrors.set({});
    this.notify(true, text);
  }

  private fail(err: HttpErrorResponse) {
    const errors: Record<string, string[]> = err.error?.errors ?? {};
    this.fieldErrors.set(Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]])));
    this.notify(false, httpErrorText(err, 'Speichern fehlgeschlagen.'));
  }

  /** Meldung ganz oben zeigen UND dorthin scrollen: "Speichern" sitzt unten in der Tastenleiste. */
  private notify(ok: boolean, text: string) {
    this.message.set({ ok, text });
    this.host.nativeElement.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
}
