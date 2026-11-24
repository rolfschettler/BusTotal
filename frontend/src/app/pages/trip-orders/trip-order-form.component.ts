import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnInit, computed, inject, signal } from '@angular/core';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { DriverChoice, DriverService } from '../../core/driver.service';
import { httpErrorText } from '../../core/http-error';
import { ListMemory } from '../../core/list-memory.service';
import { PrintPreviewService } from '../../core/print-preview.service';
import { TripOrder, TripOrderBoarding, TripOrderInput, TripOrderService, statusBadge } from '../../core/trip-order.service';
import { VehicleChoice, VehicleService } from '../../core/vehicle.service';
import { HelpLinkComponent } from '../../help/help-link.component';

import { fromLocalInput, toLocalInput } from './trip-order-time';

const KEY = 'fahrtauftraege';

/**
 * Fahrtauftrag nach dem Formular-Muster (docs/ClaudeCodePatterns/EditFormPattern.md), Drittelraster.
 * Hier disponiert der Disponent: Fahrzeug (eigenes oder Fremdunternehmer), Fahrer 1 und 2, Termine, Hinweise. Hinweise des Servers zur
 * Zuweisung (Doppelbelegung, Fristen, Führerschein …) sind Warnungen, nie eine Sperre. Angelegt wird ein Fahrtauftrag hier nie:
 * Er entsteht mit der festgeschriebenen Auftragsbestätigung. Die Buchhaltung darf lesen und drucken, aber nicht ändern.
 */
@Component({
  selector: 'app-trip-order-form',
  imports: [ReactiveFormsModule, RouterLink, HelpLinkComponent],
  templateUrl: './trip-order-form.component.html',
  host: { class: 'form-page' },
})
export class TripOrderFormComponent implements OnInit {
  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private api = inject(TripOrderService);
  private vehicles = inject(VehicleService);
  private drivers = inject(DriverService);
  private fb = inject(FormBuilder);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private auth = inject(AuthService);
  private confirm = inject(ConfirmService);
  private memory = inject(ListMemory);
  private printPreview = inject(PrintPreviewService);

  canEdit = computed(() => ['admin', 'disponent'].includes(this.auth.user()?.role ?? ''));

  id = '';
  order = signal<TripOrder | null>(null);
  vehicleChoices = signal<VehicleChoice[]>([]);
  driverChoices = signal<DriverChoice[]>([]);
  message = signal<{ ok: boolean; text: string } | null>(null);
  fieldErrors = signal<Record<string, string>>({});
  saving = signal(false);
  issuing = signal(false);
  loading = signal(true);
  formSubmitted = signal(false);

  readonly badge = statusBadge;
  /** Erledigt oder abgerechnet: gesperrt. Ohne Berechtigung zum Ändern ebenfalls. */
  readOnly = computed(() => !this.canEdit() || this.order()?.status === 'erledigt' || this.order()?.billing_status === 'abgerechnet');
  issued = computed(() => !!this.order()?.number);

  form = this.fb.nonNullable.group({
    vehicle_id: [''],
    external_company: [''],
    external_plate: [''],
    driver_id: [''],
    driver2_id: [''],
    date_from: [''],
    date_to: [''],
    departure_at: [''],
    staging_time: [''],
    passengers: [''],
    /** Zustiegsstellen: weitere Orte unterwegs mit Uhrzeit und Personenzahl, von Hand erfasst */
    boardings: this.fb.array<FormGroup>([]),
    driver_note: [''],
    notes: [''],
    reason: [''],
  });

  get boardings(): FormArray<FormGroup> {
    return this.form.controls.boardings;
  }

  private newBoarding(row?: TripOrderBoarding): FormGroup {
    return this.fb.nonNullable.group({ kind: [row?.kind ?? 'zustieg'], place: [row?.place ?? ''], time: [row?.time ?? ''], passengers: [row?.passengers ?? ''] });
  }

  addBoarding() {
    this.boardings.push(this.newBoarding());
    this.boardings.markAsDirty();
  }

  removeBoarding(i: number) {
    this.boardings.removeAt(i);
    this.boardings.markAsDirty();
  }

  ngOnInit() {
    this.id = this.route.snapshot.paramMap.get('id') ?? '';
    this.memory.follow(KEY, this.id);
    if (this.canEdit()) {
      this.vehicles.choices().subscribe((v) => this.vehicleChoices.set(v));
      this.drivers.choices().subscribe((d) => this.driverChoices.set(d));
    }
    this.load();
  }

  invalid(name: keyof typeof this.form.controls): boolean {
    return (this.formSubmitted() && this.form.controls[name].invalid) || !!this.fieldErrors()[name];
  }

  /** Hinweise des Servers, die an einem Feld hängen. */
  warningsFor(field: 'vehicle' | 'driver' | 'driver2') {
    return (this.order()?.warnings ?? []).filter((w) => w.field === field);
  }

  private load() {
    this.loading.set(true);
    this.api.get(this.id).subscribe({
      next: (o) => {
        this.loading.set(false);
        this.apply(o);
      },
      error: () => {
        this.loading.set(false);
        this.router.navigate(['/fahrtauftraege']);
      },
    });
  }

  private apply(o: TripOrder) {
    this.order.set(o);
    this.form.reset(this.toFormValue(o), { emitEvent: false });
    // Die Zustiegsstellen sind eine Liste: nach dem Zurücksetzen neu aufbauen
    this.boardings.clear({ emitEvent: false });
    for (const b of o.boardings ?? []) this.boardings.push(this.newBoarding(b), { emitEvent: false });
    this.form.markAsPristine();
    if (this.readOnly()) this.form.disable({ emitEvent: false });
    else this.form.enable({ emitEvent: false });
  }

  private toFormValue(o: TripOrder) {
    return {
      vehicle_id: o.vehicle_id ?? '',
      external_company: o.external_company ?? '',
      external_plate: o.external_plate ?? '',
      driver_id: o.driver_id ?? '',
      driver2_id: o.driver2_id ?? '',
      date_from: o.date_from ?? '',
      date_to: o.date_to ?? '',
      departure_at: toLocalInput(o.departure_at),
      staging_time: o.staging_time ?? '',
      passengers: o.passengers ?? '',
      driver_note: o.driver_note ?? '',
      notes: o.notes ?? '',
      reason: '',
    };
  }

  private toInput(): TripOrderInput {
    const v = this.form.getRawValue();
    const text = (s: string) => (s.trim() === '' ? null : s.trim());
    return {
      vehicle_id: text(v.vehicle_id),
      external_company: text(v.external_company),
      external_plate: text(v.external_plate),
      driver_id: text(v.driver_id),
      driver2_id: text(v.driver2_id),
      date_from: text(v.date_from),
      date_to: text(v.date_to),
      departure_at: fromLocalInput(v.departure_at),
      staging_time: text(v.staging_time),
      passengers: text(v.passengers),
      boardings: this.boardingValues(),
      driver_note: text(v.driver_note),
      notes: text(v.notes),
      reason: text(v.reason),
    };
  }

  // ---------- Speichern, Ausgeben, Drucken ----------

  save() {
    this.formSubmitted.set(true);
    // Nach dem Ausgeben braucht eine Änderung an Fahrzeug, Fahrern oder Terminen eine Begründung (der Server prüft es ebenfalls).
    if (this.issued() && this.needsReason() && this.form.controls.reason.value.trim() === '') {
      this.fieldErrors.set({ reason: 'Bitte eine Begründung für die Änderung angeben.' });
      this.notify(false, 'Der Fahrtauftrag ist schon ausgegeben. Bitte eine Begründung für die Änderung angeben.');
      return;
    }
    this.saving.set(true);
    this.api.update(this.id, this.toInput()).subscribe({
      next: (o) => {
        this.saving.set(false);
        this.formSubmitted.set(false);
        this.fieldErrors.set({});
        this.apply(o);
        this.notify(true, 'Gespeichert.');
      },
      error: (e: HttpErrorResponse) => this.fail(e, 'Speichern fehlgeschlagen.', () => this.saving.set(false)),
    });
  }

  /** Die Zustiegsstellen wie der Server sie bekommt: getrimmt, ohne ganz leere Zeilen. */
  private boardingValues() {
    const t = (s: string | null | undefined) => (s ?? '').trim();
    return this.boardings.getRawValue()
      .map((b) => ({ kind: (b['kind'] === 'ausstieg' ? 'ausstieg' : 'zustieg') as TripOrderBoarding['kind'], place: t(b['place']), time: t(b['time']) || null, passengers: t(b['passengers']) || null }))
      .filter((b) => b.place !== '' || b.time !== null || b.passengers !== null);
  }

  /** Hat sich etwas geändert, das nach dem Ausgeben eine Begründung braucht (nicht nur Hinweise und Notizen)? */
  needsReason(): boolean {
    const o = this.order();
    if (!o) return false;
    const was = this.toFormValue(o);
    const now = this.form.getRawValue();
    const fields = (['vehicle_id', 'external_company', 'external_plate', 'driver_id', 'driver2_id', 'date_from', 'date_to', 'departure_at', 'staging_time', 'passengers'] as const)
      .some((k) => String(was[k] ?? '') !== String(now[k] ?? ''));
    const savedBoardings = (o.boardings ?? []).map((b) => ({ kind: b.kind ?? 'zustieg', place: b.place, time: b.time || null, passengers: b.passengers || null }));
    return fields || JSON.stringify(savedBoardings) !== JSON.stringify(this.boardingValues());
  }

  async issue() {
    if (this.form.dirty) {
      this.notify(false, 'Bitte zuerst speichern, dann ausgeben.');
      return;
    }
    const ok = await this.confirm.ask({
      title: 'Fahrtauftrag ausgeben',
      message: 'Der Fahrtauftrag bekommt jetzt seine Nummer. Danach lassen sich Fahrzeug, Fahrer und Termine nur noch mit einer Begründung ändern.',
      confirmLabel: 'Ausgeben',
    });
    if (!ok) return;
    this.issuing.set(true);
    this.api.issue(this.id).subscribe({
      next: (o) => {
        this.issuing.set(false);
        this.apply(o);
        this.notify(true, `Ausgegeben als ${o.number}. Jetzt kann die Fahrer-Fassung gedruckt werden.`);
      },
      error: (e: HttpErrorResponse) => this.fail(e, 'Das Ausgeben hat nicht geklappt.', () => this.issuing.set(false)),
    });
  }

  /** Druckvorschau der Fahrer-Fassung (ohne Preise). Zeigt den gespeicherten Stand, deshalb vorher speichern. */
  print() {
    const o = this.order();
    if (!o) return;
    if (this.form.dirty) {
      this.notify(false, 'Bitte zuerst speichern: Das PDF zeigt den gespeicherten Stand.');
      return;
    }
    this.printPreview.open({
      title: `Fahrtauftrag ${o.number ?? o.title}`,
      filename: `${o.number ?? 'Fahrtauftrag-Entwurf'}.pdf`,
      load: () => this.api.pdf(this.id),
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
    this.router.navigate(['/fahrtauftraege']);
  }

  /** Eingaben verwerfen, Formular auf den gespeicherten Stand zurücksetzen. */
  cancel() {
    const o = this.order();
    if (o) this.apply(o);
    this.formSubmitted.set(false);
    this.fieldErrors.set({});
    this.message.set(null);
  }

  private fail(e: HttpErrorResponse, fallback: string, done: () => void) {
    done();
    const errors: Record<string, string[]> = e.error?.errors ?? {};
    this.fieldErrors.set(Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]])));
    this.notify(false, httpErrorText(e, fallback));
  }

  /** Meldung oben zeigen UND dorthin scrollen (die Tasten sitzen weit unten). Gescrollt wird am eigenen Host. */
  private notify(ok: boolean, text: string) {
    this.message.set({ ok, text });
    this.host.nativeElement.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
}
