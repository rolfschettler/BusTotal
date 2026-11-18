import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnInit, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { httpErrorText } from '../../core/http-error';
import { Option, Vehicle, VehicleInput, VehicleOptions, VehicleService } from '../../core/vehicle.service';
import { daysUntil, deadlineState, deadlineText } from '../../shared/deadline';

/**
 * Fahrzeugformular nach dem Formular-Muster (docs/ClaudeCodePatterns/EditFormPattern.md), Vorbild: Kundenformular.
 * Die Buchhaltung darf lesen, aber nicht ändern: Für sie ist das Formular gesperrt.
 */
@Component({
  selector: 'app-vehicle-form',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './vehicle-form.component.html',
  host: { class: 'form-page' },
})
export class VehicleFormComponent implements OnInit {
  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private api = inject(VehicleService);
  private fb = inject(FormBuilder);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private auth = inject(AuthService);
  private confirm = inject(ConfirmService);

  isAdmin = computed(() => this.auth.user()?.role === 'admin');
  canEdit = computed(() => ['admin', 'disponent'].includes(this.auth.user()?.role ?? ''));

  id: string | null = null;
  vehicle = signal<Vehicle | null>(null);
  options = signal<VehicleOptions | null>(null);
  message = signal<{ ok: boolean; text: string } | null>(null);
  fieldErrors = signal<Record<string, string>>({});
  saving = signal(false);
  loading = signal(false);
  /** Nach dem ersten Speichern-Versuch markiert das Formular fehlerhafte Felder. */
  formSubmitted = signal(false);
  archived = computed(() => !!this.vehicle()?.deleted_at);
  /** Gesperrt: archiviert oder keine Berechtigung zum Ändern. */
  readOnly = computed(() => this.archived() || !this.canEdit());

  /** Die vier Fristfelder, in der Reihenfolge der Oberfläche. Kurze Labels (einzeilig), die ganze Bezeichnung steht im Hinweis. */
  readonly deadlineFields = [
    { field: 'hu_due_date', label: 'HU fällig', hint: 'Hauptuntersuchung' },
    { field: 'sp_due_date', label: 'SP fällig', hint: 'Sicherheitsprüfung' },
    { field: 'tachograph_due_date', label: 'Tacho fällig', hint: 'Fahrtenschreiber' },
    { field: 'service_due_date', label: 'Wartung fällig', hint: 'nächste Inspektion' },
  ] as const;

  form = this.fb.nonNullable.group({
    fleet_number: ['', [Validators.required, Validators.maxLength(30)]],
    license_plate: ['', [Validators.required, Validators.maxLength(20)]],
    type: ['reisebus', Validators.required],
    manufacturer: [''],
    model: [''],
    year_built: [null as number | null, [Validators.min(1950), Validators.max(new Date().getFullYear() + 1)]],
    vin: ['', Validators.pattern(/^[A-HJ-NPR-Za-hj-npr-z0-9]{17}$/)],
    seats: [null as number | null, [Validators.required, Validators.min(1), Validators.max(200)]],
    cost_per_km: [null as number | null, [Validators.min(0), Validators.max(999)]],
    equipment: [[] as string[]],
    length_m: [null as number | null, [Validators.min(1), Validators.max(30)]],
    width_m: [null as number | null, [Validators.min(1), Validators.max(5)]],
    height_m: [null as number | null, [Validators.min(1), Validators.max(6)]],
    gross_weight_kg: [null as number | null, [Validators.min(500), Validators.max(60000)]],
    hu_due_date: [''],
    sp_due_date: [''],
    tachograph_due_date: [''],
    service_due_date: [''],
    notes: [''],
  });

  ngOnInit() {
    this.api.options().subscribe((o) => this.options.set(o));
    this.id = this.route.snapshot.paramMap.get('id');
    if (this.id) this.load(this.id);
    else if (!this.canEdit()) this.router.navigate(['/fahrzeuge']);
  }

  invalid(name: keyof typeof this.form.controls): boolean {
    return (this.formSubmitted() && this.form.controls[name].invalid) || !!this.fieldErrors()[name];
  }

  private load(id: string) {
    this.loading.set(true);
    this.api.get(id).subscribe({
      next: (v) => {
        this.loading.set(false);
        this.vehicle.set(v);
        this.form.patchValue(this.toFormValue(v), { emitEvent: false });
        if (this.readOnly()) this.form.disable({ emitEvent: false });
      },
      error: () => {
        this.loading.set(false);
        this.router.navigate(['/fahrzeuge']);
      },
    });
  }

  private toFormValue(v: Vehicle) {
    const value = { ...v, equipment: v.equipment ?? [] } as Record<string, unknown>;
    for (const k of Object.keys(value)) if (value[k] === null && typeof this.form.controls[k as keyof typeof this.form.controls]?.value === 'string') value[k] = '';
    return value as never;
  }

  // ---------- Ausstattung ----------

  has(key: string): boolean {
    return this.form.controls.equipment.value.includes(key);
  }

  toggle(key: string, on: boolean) {
    const list = this.form.controls.equipment.value.filter((k) => k !== key);
    if (on) list.push(key);
    this.form.controls.equipment.setValue(list);
    this.form.controls.equipment.markAsDirty();
  }

  // ---------- Fristen ----------

  /** "in 12 Tagen" oder "seit 3 Tagen abgelaufen" unter dem Datumsfeld, damit man die Dringlichkeit sofort sieht. */
  deadlineInfo(field: string): { text: string; cls: string } | null {
    const iso = (this.form.getRawValue() as Record<string, unknown>)[field];
    if (typeof iso !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return null;
    const days = daysUntil(iso);
    const state = deadlineState(days);
    return { text: deadlineText(days), cls: state === 'overdue' ? 'text-danger' : state === 'soon' ? 'text-warning-emphasis' : 'text-body-secondary' };
  }

  // ---------- Speichern ----------

  save() {
    this.formSubmitted.set(true);
    if (this.form.invalid) {
      this.notify(false, 'Bitte die markierten Felder korrigieren.');
      return;
    }
    // Leere Felder als null senden, nicht als Text "". Ausstattung bleibt eine Liste.
    const raw = this.form.getRawValue() as Record<string, unknown>;
    const body = Object.fromEntries(Object.entries(raw).map(([k, v]) => [k, v === '' ? null : v])) as unknown as VehicleInput;

    this.saving.set(true);
    const req = this.id ? this.api.update(this.id, body) : this.api.create(body);
    req.subscribe({
      next: (v) => {
        this.saving.set(false);
        if (!this.id) {
          this.router.navigate(['/fahrzeuge', v.id]);
          return;
        }
        this.vehicle.set(v);
        this.form.patchValue(this.toFormValue(v), { emitEvent: false });
        this.form.markAsPristine();
        this.formSubmitted.set(false);
        this.fieldErrors.set({});
        this.notify(true, 'Gespeichert.');
      },
      error: (e: HttpErrorResponse) => {
        this.saving.set(false);
        const errors: Record<string, string[]> = e.error?.errors ?? {};
        this.fieldErrors.set(Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]])));
        this.notify(false, httpErrorText(e, 'Speichern fehlgeschlagen.'));
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
    this.router.navigate(['/fahrzeuge']);
  }

  /** Eingaben verwerfen, Formular auf den gespeicherten Stand zurücksetzen. */
  cancel() {
    const v = this.vehicle();
    this.form.reset(undefined, { emitEvent: false });
    if (v) this.form.patchValue(this.toFormValue(v), { emitEvent: false });
    this.form.markAsPristine();
    this.formSubmitted.set(false);
    this.fieldErrors.set({});
    this.message.set(null);
  }

  async archive() {
    if (!this.id) return;
    const ok = await this.confirm.ask({
      title: 'Fahrzeug archivieren',
      message: 'Das Fahrzeug verschwindet aus der Liste, bleibt aber für spätere Aufträge und Belege erhalten.',
      confirmLabel: 'Archivieren',
    });
    if (ok) this.api.archive(this.id).subscribe(() => this.router.navigate(['/fahrzeuge']));
  }

  restore() {
    if (!this.id) return;
    this.api.restore(this.id).subscribe((v) => {
      this.vehicle.set(v);
      if (this.canEdit()) this.form.enable({ emitEvent: false });
      this.notify(true, 'Fahrzeug wiederhergestellt.');
    });
  }

  typeLabel(options: Option[], value: string) {
    return options.find((o) => o.value === value)?.label ?? value;
  }

  /** Meldung oben zeigen UND dorthin scrollen (Speichern sitzt weit unten). Gescrollt wird am eigenen Host. */
  private notify(ok: boolean, text: string) {
    this.message.set({ ok, text });
    this.host.nativeElement.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
}
