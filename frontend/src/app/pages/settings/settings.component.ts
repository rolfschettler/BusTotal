import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnInit, computed, inject, signal } from '@angular/core';
import { AbstractControl, FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { BrandService, textOn } from '../../core/brand.service';
import { ConfirmService } from '../../core/confirm.service';
import { httpErrorText } from '../../core/http-error';
import { HelpLinkComponent } from '../../help/help-link.component';
import { HelpService } from '../../help/help.service';
import { PricingService, PricingSettings } from '../../core/pricing.service';
import { NumberRange, SettingsService, TaxRate } from '../../core/settings.service';

type Tab = 'firma' | 'steuern' | 'nummern' | 'preise';

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
  });
  pricingSubmitted = signal(false);
  private pricingSaved: unknown = null;

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
    this.pricingApi.get().subscribe((p) => {
      this.pricing.reset(this.pricingToForm(p));
      this.pricingSaved = this.pricing.getRawValue();
      if (!this.canEdit()) this.pricing.disable();
    });
  }

  private pricingToForm(p: PricingSettings) {
    return Object.fromEntries(Object.entries(p as unknown as Record<string, string | number>).filter(([k]) => k in this.pricing.controls).map(([k, v]) => [k, Number(v)]));
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
        this.pricing.reset(this.pricingToForm(p));
        this.pricingSaved = this.pricing.getRawValue();
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

  show(tab: Tab) {
    this.tab.set(tab);
    // Das Hilfefenster folgt dem Reiter.
    this.help.setAnchor({ firma: 'firmendaten', steuern: 'steuersaetze', nummern: 'nummernkreise', preise: 'preise' }[tab]);
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
