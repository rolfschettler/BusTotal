import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnInit, computed, inject, signal } from '@angular/core';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { ListMemory } from '../../core/list-memory.service';
import { CustomerService } from '../../core/customer.service';
import { BusDocument, DocumentInput, DocumentService, DocumentTypeInfo, DocumentTypeValue, SectionKindValue, TripKindValue } from '../../core/document.service';
import { fieldErrors, httpErrorText } from '../../core/http-error';
import { PricingService, PricingSettings } from '../../core/pricing.service';
import { SettingsService, TaxRate } from '../../core/settings.service';
import { PrintPreviewService } from '../../core/print-preview.service';
import { Trip, TripService } from '../../core/trip.service';
import { CustomerNameComponent } from '../../shared/customer-name.component';
import { HighlightComponent } from '../../shared/highlight.component';
import { HelpLinkComponent } from '../../help/help-link.component';
import { formatDuration, formatKm } from '../route-planning/route-planning-view';
import { CalcLine, formatAmount, formatCents, summarize } from './document-calc';
import { DocumentPaymentsComponent } from './document-payments.component';

interface PickedCustomer {
  id: string;
  display_name: string;
  customer_number: string;
  company_name?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  postal_code?: string | null;
  city?: string | null;
}

/**
 * Beleg anlegen und bearbeiten (Angebot, Auftragsbestätigung, Anzahlungs- und Schlussrechnung).
 * Formular-Muster: docs/ClaudeCodePatterns/EditFormPattern.md.
 *
 * Ein Entwurf ist frei bearbeitbar. „Festschreiben“ vergibt die Nummer und sperrt den Beleg (GoBD): Danach zeigt dieselbe
 * Seite den Beleg nur noch an, mit den Aktionen Folgebeleg und Stornieren.
 */
@Component({
  selector: 'app-document-form',
  imports: [DatePipe, ReactiveFormsModule, RouterLink, CustomerNameComponent, HelpLinkComponent, HighlightComponent, DocumentPaymentsComponent],
  templateUrl: './document-form.component.html',
  host: { class: 'form-page' },
})
export class DocumentFormComponent implements OnInit {
  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private api = inject(DocumentService);
  private customers = inject(CustomerService);
  private settings = inject(SettingsService);
  private pricingApi = inject(PricingService);
  private fb = inject(FormBuilder);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private auth = inject(AuthService);
  private confirm = inject(ConfirmService);
  private memory = inject(ListMemory);
  private printPreview = inject(PrintPreviewService);
  private tripApi = inject(TripService);

  /** Festschreiben und Stornieren: nur Administrator und Buchhaltung (der Server prüft zusätzlich). */
  canFinalize = computed(() => ['admin', 'buchhaltung'].includes(this.auth.user()?.role ?? ''));
  /** Die Streckenplanung sehen nur Administrator und Disponent: Nur ihnen zeigt das neue Angebot den Hinweis darauf. */
  canPlan = computed(() => ['admin', 'disponent'].includes(this.auth.user()?.role ?? ''));

  id: string | null = null;
  doc = signal<BusDocument | null>(null);
  types = signal<DocumentTypeInfo[]>([]);
  taxRates = signal<TaxRate[]>([]);
  /** Preiseinstellungen: liefern die Fahrtarten und den Steuersatz je Fahrtart. Fehlen sie (kein Recht, Fehler), bleibt der Satz Handarbeit. */
  pricing = signal<PricingSettings | null>(null);
  tripKinds = computed(() => this.pricing()?.trip_kinds ?? []);
  message = signal<{ ok: boolean; text: string; list?: string[] } | null>(null);
  loading = signal(false);
  saving = signal(false);
  working = signal(false);
  formSubmitted = signal(false);

  // Kundenauswahl: Suchfeld mit Trefferliste, solange noch kein Kunde gewählt ist.
  customer = signal<PickedCustomer | null>(null);
  customerQuery = signal('');
  customerHits = signal<PickedCustomer[]>([]);
  private customerTimer?: ReturnType<typeof setTimeout>;

  readonly formatCents = formatCents;
  readonly formatDuration = formatDuration;
  /** Kilometer (nicht Meter) in deutscher Schreibweise */
  km = (kilometers: number) => formatKm(kilometers * 1000);
  /** Betrag mit umgekehrtem Vorzeichen: Ein Abzug von 357 € steht als „−357,00 €“, bei einem Storno (negativ) als positiver Betrag. */
  minus = (v: string) => formatCents(-Math.round(Number(v) * 100));
  readonly amount = formatAmount;

  form = this.fb.nonNullable.group({
    type: ['angebot' as DocumentTypeValue, Validators.required],
    customer_id: ['' as string, Validators.required],
    subject: [''],
    document_date: [''],
    service_date_from: [''],
    service_date_to: [''],
    payment_term_days: [null as number | null],
    intro_text: [''],
    footer_text: [''],
    notes: [''],
    lines: new FormArray<FormGroup>([]),
  });

  private tick = signal(0);
  /** Summen-Vorschau; maßgeblich ist, was der Server beim Speichern berechnet. */
  totals = computed(() => {
    this.tick();
    return summarize(this.lines.getRawValue() as CalcLine[]);
  });

  /** Abgezogene Anzahlungen (Schlussrechnung), vom Server beim Speichern und Festschreiben bestimmt. */
  deductions = computed(() => this.doc()?.deductions ?? []);
  /** Abzug in Cent. Beim Festgeschriebenen gilt der gespeicherte Stand. */
  private deductionCents(field: 'net' | 'tax' | 'gross'): number {
    return this.deductions().reduce((sum, d) => sum + Math.round(Number(d[field]) * 100), 0);
  }
  deductionNet = computed(() => this.deductionCents('net'));
  deductionTax = computed(() => this.deductionCents('tax'));
  deductionGross = computed(() => this.deductionCents('gross'));
  /** Zu zahlen: Brutto der Vorschau minus Anzahlungen. */
  payable = computed(() => this.totals().gross - this.deductionGross());

  readonly = computed(() => this.doc()?.status === 'festgeschrieben');
  cancelled = computed(() => !!this.doc()?.cancelled_by_id);
  typeInfo = computed(() => this.types().find((t) => t.value === (this.doc()?.type ?? this.form.controls.type.value)));
  /** Beim Anlegen sind alle Arten außer der Gutschrift wählbar (die entsteht nur durch Storno). */
  creatable = computed(() => this.types().filter((t) => t.value !== 'gutschrift'));
  canCancel = computed(() => {
    const d = this.doc();
    return !!d && this.readonly() && !this.cancelled() && !!this.typeInfo()?.invoice && d.type !== 'gutschrift' && this.canFinalize();
  });
  /** Kurzer Hinweis zum nächsten Schritt in der Reise-Leiste (nur bei Belegen einer Reise). */
  nextStep = computed(() => {
    const d = this.doc();
    if (!d?.journey || this.cancelled() || d.type === 'gutschrift') return null;
    if (d.status === 'entwurf') return 'Kunden wählen, Positionen prüfen und den Beleg festschreiben.';
    // Was es aus diesem Beleg schon gibt (Auftragsbestätigung), ist kein nächster Schritt mehr
    const next = (this.typeInfo()?.convertible_to ?? []).filter((t) => !d.existing_followups?.[t]);
    return next.length ? `${next.map((t) => this.typeLabel(t)).join(' oder ')} erstellen (Tasten unten).` : null;
  });
  isInvoiceType = computed(() => !!this.types().find((t) => t.value === this.typeValue())?.invoice);
  typeValue = signal<DocumentTypeValue>('angebot');

  /** Steuersätze der Einstellungen. Ein Satz, der dort nicht (mehr) steht, bleibt an seiner Position wählbar. */
  taxOptions(current: number | string | null): { rate: number; label: string }[] {
    const opts = this.taxRates()
      .filter((t) => t.active)
      .map((t) => ({ rate: Number(t.rate), label: `${t.name} (${Number(t.rate)} %)` }));
    const cur = Number(current);
    if (current !== null && current !== '' && !opts.some((o) => o.rate === cur)) opts.push({ rate: cur, label: `${cur} %` });
    return opts;
  }

  get lines(): FormArray<FormGroup> {
    return this.form.controls.lines;
  }

  newLine(defaults?: Partial<{ title: string; description: string; quantity: number; unit: string; trip_kind: TripKindValue | null; unit_price: number; tax_rate: number; tax_note: string; journey_section_id: string | null; vehicle_type: string | null }>) {
    return this.fb.nonNullable.group({
      title: [defaults?.title ?? '', Validators.required],
      description: [defaults?.description ?? ''],
      quantity: [defaults?.quantity ?? 1, [Validators.required, (c: { value: unknown }) => (Number(c.value) === 0 ? { zero: true } : null)]],
      unit: [defaults?.unit ?? 'Stk'],
      trip_kind: [(defaults?.trip_kind ?? null) as TripKindValue | null],
      unit_price: [defaults?.unit_price ?? 0, Validators.required],
      tax_rate: [defaults?.tax_rate ?? this.defaultRate(), Validators.required],
      tax_note: [defaults?.tax_note ?? ''],
      // Gehören zur Strecke der Reise und werden nur durchgereicht (Server setzt sie)
      journey_section_id: [defaults?.journey_section_id ?? null],
      vehicle_type: [defaults?.vehicle_type ?? null],
    });
  }

  /** Der Steuersatz, den die Einstellungen für eine Fahrtart vorsehen (Regelsatz, Linie, Schule). Null, wenn sie nicht geladen sind. */
  private rateForKind(kind: TripKindValue | null): number | null {
    const p = this.pricing();
    if (!p) return null;
    return Number(kind === 'linie' ? p.tax_rate_line : kind === 'schule' ? p.tax_rate_school : p.tax_rate);
  }

  /**
   * Die Fahrtart wurde gewählt: Der Steuersatz folgt ihr (Gelegenheitsverkehr Regelsatz, Linie und Schule ihr Satz).
   * „Keine Angabe“ lässt den Satz unverändert. Der Satz bleibt danach von Hand änderbar; abweichende Sätze zeigt der Server als Hinweis.
   */
  onKindChange(g: FormGroup) {
    const kind = g.value.trip_kind as TripKindValue | null;
    const rate = kind === null || kind === 'sonstig' ? null : this.rateForKind(kind);
    if (rate !== null && !Number.isNaN(rate)) g.patchValue({ tax_rate: rate });
  }

  private defaultRate(): number {
    const regular = this.taxRates().find((t) => t.active && Number(t.rate) === 19) ?? this.taxRates().find((t) => t.active);
    return regular ? Number(regular.rate) : 19;
  }

  ngOnInit() {
    this.api.types().subscribe((t) => this.types.set(t));
    this.settings.getTaxRates().subscribe((r) => this.taxRates.set(r));
    // Fahrtarten und Sätze je Fahrtart; ohne Zugriff (oder bei einem Fehler) bleibt die Auswahl leer und der Satz Handarbeit.
    this.pricingApi.get().subscribe({ next: (p) => this.pricing.set(p), error: () => this.pricing.set(null) });

    this.form.valueChanges.subscribe(() => {
      this.tick.update((n) => n + 1);
      this.typeValue.set(this.form.controls.type.value);
    });

    // Aus einem Beleg wird ein anderer (Folgebeleg, Storno): Die Adresse wechselt, die Seite bleibt dieselbe
    // Komponente. Deshalb auf die Adresse hören, nicht nur beim Start laden.
    this.route.paramMap.subscribe((p) => this.open(p.get('id')));
  }

  /** Beleg `id` laden, oder ein leeres Formular für einen neuen Beleg zeigen. */
  private open(id: string | null) {
    this.id = id;
    this.message.set(null);
    this.formSubmitted.set(false);
    this.doc.set(null);
    this.customer.set(null);
    this.customerQuery.set('');
    this.customerHits.set([]);
    this.form.enable({ emitEvent: false });
    this.form.reset(undefined, { emitEvent: false });
    this.lines.clear({ emitEvent: false });

    if (id) {
      // Der Rücksprung zur Liste landet auf diesem Beleg (Folgebeleg und Storno wechseln die Adresse, also folgt die Markierung).
      this.memory.follow('belege', id, true);
      this.load(id);
      return;
    }
    const type = this.route.snapshot.queryParamMap.get('type') as DocumentTypeValue | null;
    if (type) this.form.controls.type.setValue(type, { emitEvent: false });
    this.typeValue.set(this.form.controls.type.value);
    this.addLine();
    this.form.markAsPristine();
    this.tick.update((n) => n + 1);
  }

  // ---------- Laden ----------

  private load(id: string) {
    this.loading.set(true);
    this.api.get(id).subscribe({
      next: (d) => {
        this.loading.set(false);
        this.apply(d);
      },
      error: () => {
        this.loading.set(false);
        this.router.navigate(['/belege']);
      },
    });
  }

  /** Server-Stand ins Formular übernehmen. */
  private apply(d: BusDocument) {
    this.doc.set(d);
    this.customer.set(d.customer ? { id: d.customer.id, display_name: d.customer.display_name, customer_number: d.customer.customer_number } : null);
    this.lines.clear({ emitEvent: false });
    for (const l of d.lines ?? []) {
      this.lines.push(
        this.newLine({
          title: l.title, description: l.description ?? '', quantity: Number(l.quantity), unit: l.unit, trip_kind: l.trip_kind ?? null,
          unit_price: Number(l.unit_price), tax_rate: Number(l.tax_rate), tax_note: l.tax_note ?? '',
          journey_section_id: l.journey_section_id ?? null, vehicle_type: l.vehicle_type ?? null,
        }),
        { emitEvent: false },
      );
    }
    this.form.patchValue(
      {
        type: d.type,
        customer_id: d.customer_id ?? '',
        subject: d.subject ?? '',
        document_date: d.document_date ?? '',
        service_date_from: d.service_date_from ?? '',
        service_date_to: d.service_date_to ?? '',
        payment_term_days: d.payment_term_days,
        intro_text: d.intro_text ?? '',
        footer_text: d.footer_text ?? '',
        notes: d.notes ?? '',
      },
      { emitEvent: false },
    );
    this.typeValue.set(d.type);
    if (d.status === 'festgeschrieben') {
      this.form.disable({ emitEvent: false });
    } else {
      this.form.enable({ emitEvent: false });
      this.form.controls.type.disable({ emitEvent: false }); // die Belegart steht nach dem Anlegen fest
    }
    this.form.markAsPristine();
    this.tick.update((n) => n + 1);
  }

  /** Nach einer Zahlungsbuchung: Zahlstand des Belegs neu holen (nur dieser Teil, das Formular bleibt unberührt). */
  refreshPayment() {
    const id = this.id;
    if (!id) return;
    this.api.get(id).subscribe((d) => this.doc.update((cur) => (cur ? { ...cur, payment: d.payment, einvoice_format: d.einvoice_format } : cur)));
  }

  // ---------- Kunde ----------

  onCustomerSearch(q: string) {
    this.customerQuery.set(q);
    clearTimeout(this.customerTimer);
    if (q.trim().length < 2) {
      this.customerHits.set([]);
      return;
    }
    this.customerTimer = setTimeout(() => {
      this.customers.list({ q: q.trim(), type: '', archived: false, page: 1, sort: 'name', dir: 'asc' }).subscribe((p) =>
        this.customerHits.set(p.data.slice(0, 8).map((c) => ({
          id: c.id, display_name: c.display_name, customer_number: c.customer_number, company_name: c.company_name,
          first_name: c.first_name, last_name: c.last_name, postal_code: c.postal_code, city: c.city,
        }))),
      );
    }, 300);
  }

  pickCustomer(c: PickedCustomer) {
    this.customer.set(c);
    this.form.controls.customer_id.setValue(c.id);
    this.form.controls.customer_id.markAsDirty();
    this.customerQuery.set('');
    this.customerHits.set([]);
  }

  clearCustomer() {
    this.customer.set(null);
    this.form.controls.customer_id.setValue('');
    this.form.controls.customer_id.markAsDirty();
  }

  // ---------- Strecken der Reise ----------

  readonly sectionKinds: { value: SectionKindValue; label: string }[] = [
    { value: 'hinfahrt', label: 'Hinfahrt' },
    { value: 'vor_ort', label: 'Fahrt vor Ort' },
    { value: 'rueckfahrt', label: 'Rückfahrt' },
  ];
  vehicleTypes = computed(() => this.pricing()?.vehicle_types ?? []);

  /** Das Formular „Weitere Strecke hinzufügen“ ist offen. */
  sectionOpen = signal(false);
  sectionKind = signal<SectionKindValue>('rueckfahrt');
  sectionTripId = signal('');
  /** Woher Kilometer und Fahrzeit kommen: eigene Angaben (ohne Tour, Vorgabe bei der Fahrt vor Ort) oder eine Tour aus dem Archiv */
  sectionSource = signal<'own' | 'archive'>('archive');
  ownTitle = signal('');
  ownKm = signal<number | null>(null);
  ownHours = signal<number | null>(null);
  ownDays = signal(1);
  sectionDate = signal('');
  sectionVehicles = signal<{ type: string; count: number }[]>([{ type: '', count: 1 }]);
  sectionQuery = signal('');
  /** Der Suchbegriff, zu dem die angezeigten Touren gehören (Grundlage der Markierung) */
  sectionShownQuery = signal('');
  sectionTrips = signal<Trip[]>([]);
  sectionTripsError = signal<string | null>(null);
  sectionBusy = signal(false);
  private sectionTimer?: ReturnType<typeof setTimeout>;

  /** Strecken lassen sich nur im Entwurf eines Belegs mit Reise ändern. */
  canEditSections = computed(() => !this.readonly() && !!this.doc()?.journey);

  openSectionForm() {
    this.sectionOpen.set(true);
    this.setSectionKind('rueckfahrt');
    this.sectionTripId.set('');
    this.ownTitle.set('');
    this.ownKm.set(null);
    this.ownHours.set(null);
    this.ownDays.set(1);
    this.sectionDate.set('');
    // Vorbelegung: die Busse der ersten Strecke (meist fahren dieselben weiter)
    const first = (this.doc()?.journey?.sections[0]?.vehicles ?? []).filter((v) => !!v.type);
    this.sectionVehicles.set(first.length ? first.map((v) => ({ type: v.type!, count: v.count })) : [{ type: '', count: 1 }]);
    this.sectionQuery.set('');
    this.loadSectionTrips();
  }

  /** Art gewählt: Bei der Fahrt vor Ort sind eigene Angaben die Vorgabe, bei Hin- und Rückfahrt die Tour aus dem Archiv. */
  setSectionKind(kind: SectionKindValue) {
    this.sectionKind.set(kind);
    this.sectionSource.set(kind === 'vor_ort' ? 'own' : 'archive');
  }

  /** Zahl aus einem Eingabefeld; leer oder unlesbar = null. Komma und Punkt gelten beide als Dezimalzeichen. */
  numberOf(text: string): number | null {
    const t = String(text ?? '').trim();
    const n = Number(t.replace(',', '.'));
    return t === '' || !Number.isFinite(n) ? null : n;
  }

  onSectionQuery(text: string) {
    this.sectionQuery.set(text);
    clearTimeout(this.sectionTimer);
    this.sectionTimer = setTimeout(() => this.loadSectionTrips(), 300);
  }

  private loadSectionTrips() {
    const q = this.sectionQuery();
    this.tripApi.list(q, 50).subscribe({
      next: (page) => {
        if (q !== this.sectionQuery()) return;
        this.sectionTrips.set(page.data);
        this.sectionShownQuery.set(q);
        this.sectionTripsError.set(null);
      },
      error: (err: HttpErrorResponse) => this.sectionTripsError.set(httpErrorText(err, 'Das Tour-Archiv konnte nicht geladen werden.')),
    });
  }

  /** Eine Tour gewählt: Fahrzeugtyp und Anzahl der Tour sind die Vorbelegung. */
  chooseSectionTrip(id: string) {
    this.sectionTripId.set(id);
    const trip = this.sectionTrips().find((t) => t.id === id);
    if (trip?.vehicle_type) this.sectionVehicles.set([{ type: trip.vehicle_type, count: trip.vehicle_count }]);
  }

  setSectionVehicle(i: number, patch: Partial<{ type: string; count: number }>) {
    this.sectionVehicles.update((list) => list.map((v, j) => (j === i ? { ...v, ...patch } : v)));
  }
  addSectionVehicle() {
    this.sectionVehicles.update((list) => [...list, { type: '', count: 1 }]);
  }
  removeSectionVehicle(i: number) {
    this.sectionVehicles.update((list) => list.filter((_, j) => j !== i));
  }

  /** Fehlt noch etwas für „Strecke hinzufügen“? Der Text sagt, was. */
  sectionProblem = computed(() => {
    if (this.sectionSource() === 'archive') {
      if (!this.sectionTripId()) return 'Bitte eine Tour aus dem Archiv wählen.';
    } else {
      if (!this.ownTitle().trim()) return 'Bitte eine Bezeichnung angeben.';
      if (!this.ownKm() || this.ownKm()! <= 0) return 'Bitte die Kilometer angeben.';
      if (!this.ownHours() || this.ownHours()! <= 0) return 'Bitte die Fahrzeit angeben.';
      if (!this.ownDays() || this.ownDays() < 1) return 'Bitte die Anzahl der Tage angeben (mindestens 1).';
    }
    const list = this.sectionVehicles();
    if (!list.length || list.some((v) => !v.type)) return 'Bitte zu jeder Zeile einen Fahrzeugtyp wählen.';
    if (new Set(list.map((v) => v.type)).size !== list.length) return 'Jeder Fahrzeugtyp darf nur einmal vorkommen. Erhöhen Sie stattdessen die Anzahl.';
    return null;
  });

  /** Ungespeicherte Änderungen zuerst speichern: Der Server schreibt die Positionen neu, sonst gingen sie verloren. */
  private afterSave(then: () => void) {
    if (this.form.dirty) this.save(then);
    else then();
  }

  addSection() {
    if (!this.id || this.sectionProblem() || this.sectionBusy()) return;
    const id = this.id;
    this.afterSave(() => {
      this.sectionBusy.set(true);
      this.api
        .addSection(id, {
          kind: this.sectionKind(),
          ...(this.sectionSource() === 'archive'
            ? { trip_id: this.sectionTripId() }
            : {
                own: {
                  title: this.ownTitle().trim(),
                  loaded_km: this.ownKm()!,
                  drive_minutes: Math.max(1, Math.round(this.ownHours()! * 60)),
                  days: Math.round(this.ownDays()),
                },
              }),
          start_on: this.sectionDate() || null,
          vehicles: this.sectionVehicles().map((v) => ({ type: v.type, count: Number(v.count) || 1 })),
        })
        .subscribe({
          next: (d) => {
            this.sectionBusy.set(false);
            this.apply(d);
            this.sectionOpen.set(false);
            this.notify(true, 'Strecke hinzugefügt. Bitte Positionen und Preise prüfen.');
          },
          error: (e: HttpErrorResponse) => {
            this.sectionBusy.set(false);
            this.fail(e, 'Die Strecke konnte nicht hinzugefügt werden.');
          },
        });
    });
  }

  async removeSection(sectionId: string, title: string) {
    if (!this.id) return;
    const ok = await this.confirm.ask({ title: 'Strecke entfernen', message: `Die Strecke „${title}“ mit allen ihren Positionen aus dem Beleg entfernen?`, confirmLabel: 'Entfernen', danger: true });
    if (!ok) return;
    const id = this.id;
    this.afterSave(() => {
      this.sectionBusy.set(true);
      this.api.removeSection(id, sectionId).subscribe({
        next: (d) => {
          this.sectionBusy.set(false);
          this.apply(d);
          this.notify(true, 'Strecke entfernt.');
        },
        error: (e: HttpErrorResponse) => {
          this.sectionBusy.set(false);
          this.fail(e, 'Die Strecke konnte nicht entfernt werden.');
        },
      });
    });
  }

  /**
   * Überschrift über einer Gruppe von Positionen: Strecke und Fahrzeugbedarf. Nur an der ersten Position der Gruppe, sonst null.
   * Positionen ohne Strecke (von Hand angelegt) stehen unter „Weitere Positionen“, sobald der Beleg Strecken hat.
   */
  groupHeader(i: number): { sectionId: string | null; text: string; removable: boolean } | null {
    const sections = this.doc()?.journey?.sections;
    if (!sections?.length) return null;
    const key = (j: number) => `${this.lines.at(j).value.journey_section_id ?? ''}|${this.lines.at(j).value.vehicle_type ?? ''}`;
    if (i > 0 && key(i) === key(i - 1)) return null;

    const sectionId = (this.lines.at(i).value.journey_section_id as string | null) ?? null;
    const section = sections.find((s) => s.id === sectionId);
    if (!section) return { sectionId: null, text: 'Weitere Positionen', removable: false };

    const type = this.lines.at(i).value.vehicle_type as string | null;
    const vehicle = section.vehicles.find((v) => v.type === type);
    const bus = vehicle?.label ? ` · ${vehicle.count > 1 ? vehicle.count + ' × ' : ''}${vehicle.label}` : '';
    // „Strecke entfernen“ nur an der ersten Gruppe einer Strecke (es nimmt alle ihre Fahrzeugtypen mit)
    const first = i === 0 || (this.lines.at(i - 1).value.journey_section_id ?? null) !== sectionId;
    return { sectionId: section.id, text: `${section.kind_label}: ${section.title}${bus}`, removable: sections.length > 1 && first };
  }

  // ---------- Positionen ----------

  addLine() {
    this.lines.push(this.newLine());
    this.form.markAsDirty();
  }

  async removeLine(i: number) {
    const title = this.lines.at(i).value.title;
    if (title) {
      const ok = await this.confirm.ask({ title: 'Position entfernen', message: `„${title}“ aus dem Beleg entfernen?`, confirmLabel: 'Entfernen' });
      if (!ok) return;
    }
    this.lines.removeAt(i);
    this.form.markAsDirty();
  }

  /** Netto der Position i in Cent (0, solange die Vorschau noch nachrechnet). */
  lineNet(i: number): number {
    return this.totals().lineNet[i] ?? 0;
  }

  isZeroRate(g: FormGroup): boolean {
    return Number(g.value.tax_rate) === 0;
  }

  lineInvalid(i: number, field: string): boolean {
    return this.formSubmitted() && this.lines.at(i).get(field)!.invalid;
  }

  invalid(name: 'customer_id'): boolean {
    return this.formSubmitted() && this.form.controls[name].invalid;
  }

  // ---------- Speichern ----------

  private body(): DocumentInput {
    const v = this.form.getRawValue();
    const empty = (s: string) => (s === '' ? null : s);
    return {
      ...(this.id ? {} : { type: v.type }),
      customer_id: empty(v.customer_id),
      subject: empty(v.subject),
      intro_text: empty(v.intro_text),
      footer_text: empty(v.footer_text),
      notes: empty(v.notes),
      document_date: empty(v.document_date),
      service_date_from: empty(v.service_date_from),
      service_date_to: empty(v.service_date_to),
      payment_term_days: v.payment_term_days === null || (v.payment_term_days as unknown) === '' ? null : Number(v.payment_term_days),
      lines: (v.lines as Record<string, string | number | null>[]).map((l) => ({
        title: String(l['title']),
        description: l['description'] === '' ? null : String(l['description']),
        quantity: Number(l['quantity']),
        unit: String(l['unit'] || 'Stk'),
        trip_kind: (l['trip_kind'] || null) as TripKindValue | null,
        unit_price: Number(l['unit_price']),
        tax_rate: Number(l['tax_rate']),
        tax_note: l['tax_note'] === '' ? null : String(l['tax_note']),
        journey_section_id: l['journey_section_id'] ? String(l['journey_section_id']) : null,
        vehicle_type: l['vehicle_type'] ? String(l['vehicle_type']) : null,
      })),
    };
  }

  /** Speichern. Nach Erfolg läuft optional `then` weiter (z. B. Festschreiben). */
  save(then?: () => void) {
    this.formSubmitted.set(true);
    if (this.form.invalid) {
      this.notify(false, 'Bitte die markierten Felder korrigieren.');
      return;
    }
    this.saving.set(true);
    const req = this.id ? this.api.update(this.id, this.body()) : this.api.create(this.body());
    req.subscribe({
      next: (d) => {
        this.saving.set(false);
        if (!this.id) {
          this.router.navigate(['/belege', d.id]);
          return;
        }
        this.apply(d);
        this.formSubmitted.set(false);
        if (then) then();
        else this.notify(true, 'Gespeichert.');
      },
      error: (e: HttpErrorResponse) => {
        this.saving.set(false);
        this.fail(e, 'Speichern fehlgeschlagen.');
      },
    });
  }

  async back() {
    if (this.form.dirty) {
      const ok = await this.confirm.ask({
        title: 'Änderungen verwerfen',
        message: 'Es gibt ungespeicherte Änderungen. Trotzdem zurück zur Liste?',
        confirmLabel: 'Verwerfen',
        danger: true,
      });
      if (!ok) return;
    }
    this.router.navigate(['/belege']);
  }

  /** Eingaben verwerfen, Formular auf den gespeicherten Stand zurücksetzen. */
  cancelEdit() {
    const d = this.doc();
    if (d) this.apply(d);
    else {
      this.form.reset(undefined, { emitEvent: false });
      this.lines.clear({ emitEvent: false });
      this.addLine();
      this.customer.set(null);
      this.form.markAsPristine();
    }
    this.formSubmitted.set(false);
    this.message.set(null);
  }

  // ---------- Aktionen am Beleg ----------

  async finalize() {
    if (!this.id) return;
    const ok = await this.confirm.ask({
      title: `${this.typeInfo()?.label ?? 'Beleg'} festschreiben`,
      message:
        'Der Beleg erhält jetzt seine Nummer und kann danach nicht mehr geändert oder gelöscht werden.\n' +
        'Korrekturen sind nur noch über eine Stornierung möglich.',
      confirmLabel: 'Festschreiben',
    });
    if (!ok) return;

    const run = () => {
      this.working.set(true);
      this.api.finalize(this.id!).subscribe({
        next: (d) => {
          this.working.set(false);
          this.apply(d);
          this.notify(true, `Festgeschrieben als ${d.number}.`);
        },
        error: (e: HttpErrorResponse) => {
          this.working.set(false);
          this.fail(e, 'Festschreiben fehlgeschlagen.');
        },
      });
    };
    // Ungespeicherte Änderungen werden zuerst gespeichert, damit der Beleg so festgeschrieben wird, wie er auf dem Schirm steht.
    if (this.form.dirty) this.save(run);
    else run();
  }

  async deleteDraft() {
    if (!this.id) return;
    const ok = await this.confirm.ask({
      title: 'Entwurf löschen',
      message: 'Der Entwurf wird gelöscht. Es ist noch keine Nummer vergeben, daran entsteht keine Lücke.',
      confirmLabel: 'Löschen',
      danger: true,
    });
    const id = this.id;
    if (ok) {
      this.api.remove(id).subscribe({
        next: () => {
          this.memory.forget('belege', id);
          this.router.navigate(['/belege']);
        },
        error: (e) => this.fail(e, 'Löschen fehlgeschlagen.'),
      });
    }
  }

  async cancelDocument() {
    if (!this.id) return;
    const d = this.doc();
    const ok = await this.confirm.ask({
      title: 'Beleg stornieren',
      message:
        `${d?.number} wird durch eine Stornorechnung mit negativen Beträgen ausgeglichen.\n` +
        'Der Beleg selbst bleibt unverändert erhalten. Das lässt sich nicht rückgängig machen.',
      confirmLabel: 'Stornieren',
      danger: true,
    });
    if (!ok) return;
    this.working.set(true);
    this.api.cancel(this.id).subscribe({
      next: (storno) => {
        this.working.set(false);
        this.router.navigate(['/belege', storno.id]);
      },
      error: (e: HttpErrorResponse) => {
        this.working.set(false);
        this.fail(e, 'Stornieren fehlgeschlagen.');
      },
    });
  }

  convertTo(type: DocumentTypeValue) {
    if (!this.id) return;
    this.working.set(true);
    this.api.convert(this.id, type).subscribe({
      next: (neu) => {
        this.working.set(false);
        this.router.navigate(['/belege', neu.id]);
      },
      error: (e: HttpErrorResponse) => {
        this.working.set(false);
        this.fail(e, 'Der Folgebeleg konnte nicht erstellt werden.');
      },
    });
  }

  /** Druckvorschau. Zeigt den gespeicherten Stand (bei ungespeicherten Änderungen erst speichern). */
  openPdf() {
    const d = this.doc();
    if (!this.id) return;
    this.printPreview.open({
      title: `${this.typeLabel(d?.type)} ${d?.number ?? '(Entwurf)'}`,
      filename: `${d?.number ?? 'Entwurf'}.pdf`,
      load: () => this.api.pdf(this.id!),
    });
  }

  /**
   * E-Rechnung (XRechnung-XML) herunterladen. Hat der Beleg noch keine, entsteht sie jetzt einmal (ältere Rechnung, Kunde erst
   * später auf E-Rechnung umgestellt). Fehlen dafür Angaben, nennt die Meldung sie.
   */
  downloadEInvoice() {
    this.downloadEInvoiceFile('xml');
  }

  /** ZUGFeRD-Fassung: das archivierte PDF mit eingebetteter XRechnung. */
  downloadZugferd() {
    this.downloadEInvoiceFile('zugferd');
  }

  private downloadEInvoiceFile(kind: 'xml' | 'zugferd') {
    const d = this.doc();
    if (!this.id || !d) return;
    this.working.set(true);
    (kind === 'xml' ? this.api.einvoice(this.id) : this.api.zugferd(this.id)).subscribe({
      next: (blob) => {
        this.working.set(false);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${d.number ?? 'e-rechnung'}${kind === 'xml' ? '.xml' : '-zugferd.pdf'}`;
        a.click();
        URL.revokeObjectURL(url);
        this.refreshPayment();   // holt auch das Kennzeichen „E-Rechnung“ des Belegs
      },
      error: async (e: HttpErrorResponse) => {
        this.working.set(false);
        this.fail(await this.readBlobError(e), 'Die E-Rechnung konnte nicht erzeugt werden.');
      },
    });
  }

  /** Eine Fehlerantwort, die als Blob ankam (weil die Anfrage eine Datei erwartete), wieder in ihr JSON verwandeln. */
  private async readBlobError(err: HttpErrorResponse): Promise<HttpErrorResponse> {
    if (err.error instanceof Blob) {
      try {
        return new HttpErrorResponse({ error: JSON.parse(await err.error.text()), status: err.status, statusText: err.statusText });
      } catch {
        return err;
      }
    }
    return err;
  }

  typeLabel(value: string | null | undefined): string {
    return this.types().find((t) => t.value === value)?.label ?? value ?? '';
  }

  // ---------- Meldungen ----------

  private fail(err: HttpErrorResponse, fallback: string) {
    // Festschreiben und Storno melden jede fehlende Angabe einzeln (errors.document).
    const list = (err.error?.errors?.document as string[] | undefined) ?? [];
    const first = Object.values(fieldErrors(err))[0];
    if (list.length > 1) this.notify(false, 'Der Beleg ist noch nicht vollständig:', list);
    else this.notify(false, list[0] ?? (err.status === 422 ? (first ?? fallback) : httpErrorText(err, fallback)));
  }

  /** Meldung ganz oben zeigen UND dorthin scrollen (die Tastenleiste sitzt weit unten). */
  private notify(ok: boolean, text: string, list?: string[]) {
    this.message.set({ ok, text, list });
    this.host.nativeElement.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
}
