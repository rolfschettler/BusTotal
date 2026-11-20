import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnInit, computed, inject, signal } from '@angular/core';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { CustomerService } from '../../core/customer.service';
import { BusDocument, DocumentInput, DocumentService, DocumentTypeInfo, DocumentTypeValue } from '../../core/document.service';
import { fieldErrors, httpErrorText } from '../../core/http-error';
import { SettingsService, TaxRate } from '../../core/settings.service';
import { HelpLinkComponent } from '../../help/help-link.component';
import { CalcLine, formatAmount, formatCents, summarize } from './document-calc';

interface PickedCustomer {
  id: string;
  display_name: string;
  customer_number: string;
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
  imports: [DatePipe, ReactiveFormsModule, RouterLink, HelpLinkComponent],
  templateUrl: './document-form.component.html',
  host: { class: 'form-page' },
})
export class DocumentFormComponent implements OnInit {
  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private api = inject(DocumentService);
  private customers = inject(CustomerService);
  private settings = inject(SettingsService);
  private fb = inject(FormBuilder);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private auth = inject(AuthService);
  private confirm = inject(ConfirmService);

  /** Festschreiben und Stornieren: nur Administrator und Buchhaltung (der Server prüft zusätzlich). */
  canFinalize = computed(() => ['admin', 'buchhaltung'].includes(this.auth.user()?.role ?? ''));

  id: string | null = null;
  doc = signal<BusDocument | null>(null);
  types = signal<DocumentTypeInfo[]>([]);
  taxRates = signal<TaxRate[]>([]);
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

  newLine(defaults?: Partial<{ title: string; description: string; quantity: number; unit: string; unit_price: number; tax_rate: number; tax_note: string }>) {
    return this.fb.nonNullable.group({
      title: [defaults?.title ?? '', Validators.required],
      description: [defaults?.description ?? ''],
      quantity: [defaults?.quantity ?? 1, [Validators.required, (c: { value: unknown }) => (Number(c.value) === 0 ? { zero: true } : null)]],
      unit: [defaults?.unit ?? 'Stk'],
      unit_price: [defaults?.unit_price ?? 0, Validators.required],
      tax_rate: [defaults?.tax_rate ?? this.defaultRate(), Validators.required],
      tax_note: [defaults?.tax_note ?? ''],
    });
  }

  private defaultRate(): number {
    const regular = this.taxRates().find((t) => t.active && Number(t.rate) === 19) ?? this.taxRates().find((t) => t.active);
    return regular ? Number(regular.rate) : 19;
  }

  ngOnInit() {
    this.api.types().subscribe((t) => this.types.set(t));
    this.settings.getTaxRates().subscribe((r) => this.taxRates.set(r));

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
          title: l.title, description: l.description ?? '', quantity: Number(l.quantity), unit: l.unit,
          unit_price: Number(l.unit_price), tax_rate: Number(l.tax_rate), tax_note: l.tax_note ?? '',
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
        this.customerHits.set(p.data.slice(0, 8).map((c) => ({ id: c.id, display_name: c.display_name, customer_number: c.customer_number }))),
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
      lines: (v.lines as Record<string, string | number>[]).map((l) => ({
        title: String(l['title']),
        description: l['description'] === '' ? null : String(l['description']),
        quantity: Number(l['quantity']),
        unit: String(l['unit'] || 'Stk'),
        unit_price: Number(l['unit_price']),
        tax_rate: Number(l['tax_rate']),
        tax_note: l['tax_note'] === '' ? null : String(l['tax_note']),
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
    if (ok) this.api.remove(this.id).subscribe({ next: () => this.router.navigate(['/belege']), error: (e) => this.fail(e, 'Löschen fehlgeschlagen.') });
  }

  async cancelDocument() {
    if (!this.id) return;
    const d = this.doc();
    const ok = await this.confirm.ask({
      title: 'Beleg stornieren',
      message:
        `${d?.number} wird durch einen Storno-Beleg (Gutschrift) mit negativen Beträgen ausgeglichen.\n` +
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
