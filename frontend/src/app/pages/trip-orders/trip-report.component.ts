import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe } from '@angular/common';
import { Component, ElementRef, OnInit, computed, inject, signal } from '@angular/core';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { BackNavigation } from '../../core/back-navigation.service';
import { ConfirmService } from '../../core/confirm.service';
import { httpErrorText } from '../../core/http-error';
import { ReportBorder, ReportCost, ReportExtra, ReportInput, ReportTime, TripReportData, TripReportService } from '../../core/trip-report.service';
import { statusBadge } from '../../core/trip-order.service';
import { HelpLinkComponent } from '../../help/help-link.component';
import { askReason } from '../../shared/reason-modal.component';
import { TripOrderStepsComponent } from './trip-order-steps.component';
import { completeSummary, drivenKm, kmBetween } from './trip-report';

/**
 * Rückerfassung (Formular-Muster): das Erfassungsblatt des Fahrers in derselben Reihenfolge abtippen. Speichern geht beliebig
 * oft; „Fahrt erledigt“ speichert und schließt ab. Danach ist die Seite gesperrt, bis jemand „Erledigt“ zurücknimmt.
 */
@Component({
  selector: 'app-trip-report',
  imports: [DatePipe, ReactiveFormsModule, RouterLink, HelpLinkComponent, TripOrderStepsComponent],
  templateUrl: './trip-report.component.html',
  host: { class: 'form-page' },
})
export class TripReportComponent implements OnInit {
  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private api = inject(TripReportService);
  private fb = inject(FormBuilder);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private backNav = inject(BackNavigation);
  private confirm = inject(ConfirmService);
  private modals = inject(NgbModal);

  id = '';
  data = signal<TripReportData | null>(null);
  loading = signal(true);
  saving = signal(false);
  message = signal<{ ok: boolean; text: string } | null>(null);

  readonly badge = statusBadge;
  readOnly = computed(() => !this.data()?.report.editable);
  done = computed(() => this.data()?.order.status === 'erledigt' && this.data()?.order.billing_status !== 'abgerechnet');

  form = this.fb.group({
    times: this.fb.array<FormGroup>([]),
    borders: this.fb.array<FormGroup>([]),
    extras: this.fb.array<FormGroup>([]),
    costs: this.fb.array<FormGroup>([]),
    actual_passengers: [''],
    report_notes: [''],
  });

  list(name: 'times' | 'borders' | 'extras' | 'costs'): FormArray<FormGroup> {
    return this.form.controls[name];
  }

  ngOnInit() {
    this.id = this.route.snapshot.paramMap.get('id') ?? '';
    this.api.get(this.id).subscribe({
      next: (d) => {
        this.loading.set(false);
        this.apply(d);
      },
      error: () => void this.router.navigate(['/fahrtauftraege']),
    });
  }

  // ---------- Zeilen ----------

  private timeRow(r?: Partial<ReportTime>) {
    return this.fb.group({
      event: [r?.event ?? ''], date: [r?.date ?? ''], place: [r?.place ?? ''], time: [r?.time ?? ''], km: [r?.km ?? null], leg: [r?.leg ?? ''],
    });
  }

  private borderRow(r?: ReportBorder) {
    return this.fb.group({ country: [r?.country ?? ''], km_in: [r?.km_in ?? null], km_out: [r?.km_out ?? null], source: [r?.source ?? 'fahrer'] });
  }

  private extraRow(r?: ReportExtra) {
    return this.fb.group({
      title: [r?.title ?? ''], quantity: [r?.quantity ?? ''], place: [r?.place ?? ''], amount: [r?.amount == null ? null : Number(r.amount)],
      payment: [r?.payment ?? 'rechnung'], tax_rate: [r ? Number(r.tax_rate) : (this.data()?.choices.default_tax_rate ?? 19)],
    });
  }

  private costRow(r?: ReportCost) {
    return this.fb.group({
      category: [r?.category ?? 'maut'], amount: [r?.amount == null ? null : Number(r.amount)], receipt_no: [r?.receipt_no ?? ''],
      travel_service: [r?.travel_service ?? false], charge_customer: [r?.charge_customer ?? false],
    });
  }

  /** Neue Zwischenzeile vor der letzten: Die letzte Zeile bleibt das Ende der Fahrt. */
  addTime() {
    const times = this.list('times');
    times.insert(Math.max(0, times.length - 1), this.timeRow({ leg: 'befoerderung' }));
    times.markAsDirty();
  }

  add(name: 'borders' | 'extras' | 'costs') {
    this.list(name).push(name === 'borders' ? this.borderRow() : name === 'extras' ? this.extraRow() : this.costRow());
    this.list(name).markAsDirty();
  }

  remove(name: 'times' | 'borders' | 'extras' | 'costs', i: number) {
    this.list(name).removeAt(i);
    this.list(name).markAsDirty();
  }

  /** Die Kostenart bestimmt die Vorbelegung „Reisevorleistung“. */
  categoryChanged(i: number) {
    const row = this.list('costs').at(i);
    const category = this.data()?.choices.categories.find((c) => c.value === row.value.category);
    row.patchValue({ travel_service: category?.travel_service ?? false });
  }

  driven(): number | null {
    return drivenKm(this.list('times').getRawValue() as ReportTime[]);
  }

  abroad(i: number): number | null {
    const row = this.list('borders').at(i).getRawValue() as ReportBorder;
    return kmBetween(row.km_in, row.km_out);
  }

  // ---------- Laden und Speichern ----------

  private apply(d: TripReportData) {
    this.data.set(d);
    const fill = (name: 'times' | 'borders' | 'extras' | 'costs', rows: FormGroup[]) => {
      this.list(name).clear({ emitEvent: false });
      for (const row of rows) this.list(name).push(row, { emitEvent: false });
    };
    fill('times', d.report.times.map((r) => this.timeRow(r)));
    fill('borders', d.report.borders.map((r) => this.borderRow(r)));
    fill('extras', d.report.extras.map((r) => this.extraRow(r)));
    fill('costs', d.report.costs.map((r) => this.costRow(r)));
    this.form.patchValue({ actual_passengers: d.report.actual_passengers ?? '', report_notes: d.report.report_notes ?? '' }, { emitEvent: false });
    this.form.markAsPristine();
    if (d.report.editable) this.form.enable({ emitEvent: false });
    else this.form.disable({ emitEvent: false });
  }

  /** Die Eingaben wie der Server sie bekommt: leere Texte als null, ganz leere Zusatzzeilen entfallen. */
  private toInput(): ReportInput {
    const v = this.form.getRawValue();
    const clean = <T>(row: object) =>
      Object.fromEntries(Object.entries(row).map(([k, x]) => [k, typeof x === 'string' ? x.trim() || null : x])) as T;
    return {
      actual_passengers: v.actual_passengers?.trim() || null,
      report_notes: v.report_notes?.trim() || null,
      times: v.times.map((r) => clean<ReportTime>(r as object)),
      borders: v.borders.map((r) => clean<ReportBorder>(r as object)).filter((r) => r.country || r.km_in !== null || r.km_out !== null),
      extras: v.extras.map((r) => clean<ReportExtra>(r as object)).filter((r) => r.title || r.amount !== null || r.quantity),
      costs: v.costs.map((r) => clean<ReportCost>(r as object)).filter((r) => r.amount !== null || r.receipt_no),
    };
  }

  save() {
    if (this.saving() || this.readOnly()) return;
    this.saving.set(true);
    this.api.save(this.id, this.toInput()).subscribe({
      next: (d) => {
        this.saving.set(false);
        this.apply(d);
        this.notify(true, 'Gespeichert.');
      },
      error: (e: HttpErrorResponse) => this.fail(e, 'Speichern fehlgeschlagen.'),
    });
  }

  /** Erst speichern, dann nachfragen und abschließen. So prüft der Server genau das, was zu sehen ist. */
  complete() {
    if (this.saving() || this.readOnly()) return;
    this.saving.set(true);
    this.api.save(this.id, this.toInput()).subscribe({
      next: async (d) => {
        this.saving.set(false);
        this.apply(d);
        if (d.report.missing.length) {
          this.notify(false, `Gespeichert. Für „erledigt“ fehlt noch: ${d.report.missing.join(', ')}.`);
          return;
        }
        const warnings = d.report.warnings.length ? `\n\nHinweise:\n${d.report.warnings.map((w) => `• ${w}`).join('\n')}` : '';
        const ok = await this.confirm.ask({
          title: 'Fahrt erledigt',
          message: completeSummary(d.report, !!d.order.number) + warnings,
          confirmLabel: 'Fahrt ist erledigt',
          cancelLabel: 'Noch nicht',
        });
        if (!ok) return;
        this.saving.set(true);
        this.api.complete(this.id).subscribe({
          next: (done) => {
            this.saving.set(false);
            this.apply(done);
            this.notify(true, 'Die Fahrt ist erledigt.');
          },
          error: (e: HttpErrorResponse) => this.fail(e, 'Die Fahrt konnte nicht abgeschlossen werden.'),
        });
      },
      error: (e: HttpErrorResponse) => this.fail(e, 'Speichern fehlgeschlagen.'),
    });
  }

  async reopen() {
    const d = await askReason(this.modals, {
      title: 'Erledigt zurücknehmen',
      message: 'Der Fahrtauftrag steht danach wieder auf „Ausgegeben“. Ihre Eingaben bleiben erhalten und lassen sich ändern.',
      confirmLabel: 'Zurücknehmen',
      errorText: '„Erledigt“ konnte nicht zurückgenommen werden.',
      save: (reason) => this.api.reopen(this.id, reason),
    });
    if (d) {
      this.apply(d);
      this.notify(true, '„Erledigt“ zurückgenommen.');
    }
  }

  async back() {
    if (this.form.dirty) {
      const ok = await this.confirm.ask({
        title: 'Änderungen verwerfen', message: 'Es gibt ungespeicherte Änderungen. Trotzdem zurück?', confirmLabel: 'Verwerfen', danger: true,
      });
      if (!ok) return;
    }
    void this.backNav.back(`/fahrtauftraege/${this.id}`);
  }

  cancel() {
    const d = this.data();
    if (d) this.apply(d);
    this.message.set(null);
  }

  private fail(e: HttpErrorResponse, fallback: string) {
    this.saving.set(false);
    this.notify(false, httpErrorText(e, fallback));
  }

  private notify(ok: boolean, text: string) {
    this.message.set({ ok, text });
    this.host.nativeElement.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
}
