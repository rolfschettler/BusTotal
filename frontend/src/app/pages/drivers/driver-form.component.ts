import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnInit, computed, inject, signal } from '@angular/core';
import { FormBuilder, FormsModule, NgForm, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { ListMemory } from '../../core/list-memory.service';
import { httpErrorText } from '../../core/http-error';
import { HelpLinkComponent } from '../../help/help-link.component';
import { Absence, Driver, DriverInput, DriverOptions, DriverService } from '../../core/driver.service';
import { HelpService } from '../../help/help.service';
import { daysUntil, deadlineState, deadlineText, germanDate } from '../../shared/deadline';

type Tab = 'stamm' | 'abwesenheiten';

const emptyAbsence = (): Absence => ({ type: 'urlaub', start_date: '', end_date: '', notes: null });

/**
 * Fahrerformular nach dem Formular-Muster (docs/ClaudeCodePatterns/EditFormPattern.md), Vorbild: Kundenformular
 * mit Reitern. Reiter "Abwesenheiten" ist ein Teil-Formular wie die Ansprechpartner beim Kunden.
 */
@Component({
  selector: 'app-driver-form',
  imports: [ReactiveFormsModule, FormsModule, RouterLink, HelpLinkComponent],
  templateUrl: './driver-form.component.html',
  host: { class: 'form-page' },
})
export class DriverFormComponent implements OnInit {
  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private api = inject(DriverService);
  private fb = inject(FormBuilder);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private auth = inject(AuthService);
  private confirm = inject(ConfirmService);
  private memory = inject(ListMemory);
  private help = inject(HelpService);

  isAdmin = computed(() => this.auth.user()?.role === 'admin');

  id: string | null = null;
  driver = signal<Driver | null>(null);
  options = signal<DriverOptions | null>(null);
  tab = signal<Tab>('stamm');
  message = signal<{ ok: boolean; text: string } | null>(null);
  fieldErrors = signal<Record<string, string>>({});
  saving = signal(false);
  loading = signal(false);
  /** Nach dem ersten Speichern-Versuch markiert das Formular fehlerhafte Felder. */
  formSubmitted = signal(false);
  absenceSubmitted = signal(false);
  absenceEdit = signal<Absence | null>(null);
  archived = computed(() => !!this.driver()?.deleted_at);

  readonly date = germanDate;

  /** Die fünf Fristfelder. Kurze Labels (einzeilig), die ganze Bezeichnung steht im Hinweis. */
  readonly deadlineFields = [
    { field: 'license_due_date', label: 'Führerschein bis', hint: 'Ablauf der Fahrerlaubnis', help: 'fristen' },
    { field: 'pbs_due_date', label: 'P-Schein bis', hint: 'Führerschein zur Fahrgastbeförderung', help: 'fristen' },
    { field: 'driver_card_due_date', label: 'Fahrerkarte bis', hint: 'Karte für den digitalen Tacho', help: 'fristen' },
    { field: 'driver_card_read_due_date', label: 'Auslesen bis', hint: 'Fahrerkarte, alle 28 Tage', warn: 7, help: 'karte-auslesen' },
    { field: 'code95_due_date', label: 'Code 95 bis', hint: 'Berufskraftfahrer-Weiterbildung', help: 'fristen' },
    { field: 'medical_due_date', label: 'Untersuchung fällig', hint: 'ärztliche Untersuchung', help: 'fristen' },
  ] as const;

  form = this.fb.nonNullable.group({
    personnel_number: ['', [Validators.required, Validators.maxLength(30)]],
    first_name: ['', Validators.required],
    last_name: ['', Validators.required],
    birth_date: [''],
    email: ['', Validators.email],
    phone: [''],
    mobile: [''],
    street: [''],
    postal_code: [''],
    city: [''],
    employment_type: ['vollzeit', Validators.required],
    hired_on: [''],
    hourly_rate: [null as number | null, [Validators.min(0), Validators.max(999)]],
    license_classes: [[] as string[]],
    license_number: [''],
    driver_card_number: [''],
    license_due_date: [''],
    pbs_due_date: [''],
    driver_card_due_date: [''],
    driver_card_read_due_date: [''],
    code95_due_date: [''],
    medical_due_date: [''],
    notes: [''],
  });

  ngOnInit() {
    this.api.options().subscribe((o) => this.options.set(o));
    this.id = this.route.snapshot.paramMap.get('id');
    if (this.id) {
      this.memory.follow('fahrer', this.id); // der Rücksprung zur Liste landet auf diesem Fahrer
      this.load(this.id);
    }
  }

  invalid(name: keyof typeof this.form.controls): boolean {
    return (this.formSubmitted() && this.form.controls[name].invalid) || !!this.fieldErrors()[name];
  }

  private load(id: string) {
    this.loading.set(true);
    this.api.get(id).subscribe({
      next: (d) => {
        this.loading.set(false);
        this.driver.set(d);
        this.form.patchValue(this.toFormValue(d), { emitEvent: false });
        if (d.deleted_at) this.form.disable({ emitEvent: false });
      },
      error: () => {
        this.loading.set(false);
        this.router.navigate(['/fahrer']);
      },
    });
  }

  private toFormValue(d: Driver) {
    const value = { ...d, license_classes: d.license_classes ?? [] } as Record<string, unknown>;
    for (const k of Object.keys(value)) if (value[k] === null && typeof this.form.controls[k as keyof typeof this.form.controls]?.value === 'string') value[k] = '';
    return value as never;
  }

  show(tab: Tab) {
    this.tab.set(tab);
    // Das Hilfefenster folgt dem Reiter; "stamm" nutzt das Thema der Seite.
    this.help.setAnchor({ stamm: null, abwesenheiten: 'abwesenheiten' }[tab]);
    this.message.set(null);
    this.fieldErrors.set({});
    this.absenceSubmitted.set(false);
  }

  // ---------- Führerscheinklassen ----------

  has(key: string): boolean {
    return this.form.controls.license_classes.value.includes(key);
  }

  toggle(key: string, on: boolean) {
    const list = this.form.controls.license_classes.value.filter((k) => k !== key);
    if (on) list.push(key);
    this.form.controls.license_classes.setValue(list);
    this.form.controls.license_classes.markAsDirty();
  }

  // ---------- Fristen ----------

  /** "in 12 Tagen" oder "seit 3 Tagen abgelaufen" unter dem Datumsfeld. */
  deadlineInfo(field: string): { text: string; cls: string } | null {
    const iso = (this.form.getRawValue() as Record<string, unknown>)[field];
    if (typeof iso !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return null;
    const days = daysUntil(iso);
    const state = deadlineState(days, (this.deadlineFields.find((d) => d.field === field) as { warn?: number } | undefined)?.warn);
    return { text: deadlineText(days), cls: state === 'overdue' ? 'text-danger' : state === 'soon' ? 'text-warning-emphasis' : 'text-body-secondary' };
  }

  // ---------- Speichern ----------

  save() {
    this.formSubmitted.set(true);
    if (this.form.invalid) {
      this.notify(false, 'Bitte die markierten Felder korrigieren.');
      return;
    }
    // Leere Felder als null senden, nicht als Text "". Führerscheinklassen bleiben eine Liste.
    const raw = this.form.getRawValue() as Record<string, unknown>;
    const body = Object.fromEntries(Object.entries(raw).map(([k, v]) => [k, v === '' ? null : v])) as unknown as DriverInput;

    this.saving.set(true);
    const req = this.id ? this.api.update(this.id, body) : this.api.create(body);
    req.subscribe({
      next: (d) => {
        this.saving.set(false);
        if (!this.id) {
          this.router.navigate(['/fahrer', d.id]);
          return;
        }
        this.driver.set(d);
        this.form.patchValue(this.toFormValue(d), { emitEvent: false });
        this.form.markAsPristine();
        this.formSubmitted.set(false);
        this.fieldErrors.set({});
        this.notify(true, 'Gespeichert.');
      },
      error: (e: HttpErrorResponse) => {
        this.saving.set(false);
        this.fail(e);
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
    this.router.navigate(['/fahrer']);
  }

  /** Eingaben verwerfen, Formular auf den gespeicherten Stand zurücksetzen. */
  cancel() {
    const d = this.driver();
    this.form.reset(undefined, { emitEvent: false });
    if (d) this.form.patchValue(this.toFormValue(d), { emitEvent: false });
    this.form.markAsPristine();
    this.formSubmitted.set(false);
    this.fieldErrors.set({});
    this.message.set(null);
  }

  async archive() {
    if (!this.id) return;
    const ok = await this.confirm.ask({
      title: 'Fahrer archivieren',
      message: 'Der Fahrer verschwindet aus der Liste (zum Beispiel bei Austritt), bleibt aber für vergangene Fahrten und Belege erhalten.',
      confirmLabel: 'Archivieren',
    });
    if (ok) this.api.archive(this.id).subscribe(() => this.router.navigate(['/fahrer']));
  }

  /** Trägt „heute ausgelesen“ ein; der Server setzt die nächste Frist (28 Tage). Nur bei gespeichertem Formular, sonst gingen Eingaben verloren. */
  readingBusy = signal(false);
  markRead() {
    if (!this.id || this.form.dirty) return;
    this.readingBusy.set(true);
    this.api.markCardRead(this.id).subscribe({
      next: (d) => {
        this.readingBusy.set(false);
        this.driver.set(d);
        this.form.patchValue(this.toFormValue(d), { emitEvent: false });
        this.form.markAsPristine();
        this.notify(true, `Fahrerkarte als heute ausgelesen eingetragen. Nächste Frist: ${germanDate(d.driver_card_read_due_date)}.`);
      },
      error: (e) => {
        this.readingBusy.set(false);
        this.notify(false, httpErrorText(e, 'Das Eintragen hat nicht geklappt.'));
      },
    });
  }

  restore() {
    if (!this.id) return;
    this.api.restore(this.id).subscribe((d) => {
      this.driver.set(d);
      this.form.enable({ emitEvent: false });
      this.notify(true, 'Fahrer wiederhergestellt.');
    });
  }

  // ---------- Abwesenheiten ----------

  newAbsence() {
    this.absenceSubmitted.set(false);
    this.fieldErrors.set({});
    this.absenceEdit.set(emptyAbsence());
  }

  editAbsence(a: Absence) {
    this.absenceSubmitted.set(false);
    this.fieldErrors.set({});
    this.absenceEdit.set({ ...a });
  }

  /** Das Ende liegt vor dem Beginn: Hinweis schon vor dem Speichern. */
  endBeforeStart(a: Absence): boolean {
    return !!a.start_date && !!a.end_date && a.end_date < a.start_date;
  }

  saveAbsence(f: NgForm) {
    const a = this.absenceEdit();
    if (!a || !this.id) return;
    this.absenceSubmitted.set(true);
    if (f.invalid || this.endBeforeStart(a)) return;
    this.api.saveAbsence(this.id, { ...a, notes: a.notes || null }).subscribe({
      next: () => {
        this.absenceEdit.set(null);
        this.fieldErrors.set({});
        this.reload('Abwesenheit gespeichert.');
      },
      error: (e: HttpErrorResponse) => this.fail(e),
    });
  }

  async deleteAbsence(a: Absence) {
    if (!this.id || !a.id) return;
    const ok = await this.confirm.ask({
      title: 'Abwesenheit löschen',
      message: `${this.typeLabel(a.type)} vom ${germanDate(a.start_date)} bis ${germanDate(a.end_date)} wirklich löschen?`,
      confirmLabel: 'Löschen',
      danger: true,
    });
    if (ok) this.api.deleteAbsence(this.id, a.id).subscribe(() => this.reload('Abwesenheit gelöscht.'));
  }

  typeLabel(value: string) {
    return this.options()?.absence_types.find((t) => t.value === value)?.label ?? value;
  }

  /** Dauer in Kalendertagen, Beginn und Ende zählen mit. */
  days(a: Absence): number {
    return daysUntil(a.end_date, parse(a.start_date)) + 1;
  }

  private reload(text: string) {
    if (!this.id) return;
    this.api.get(this.id).subscribe((d) => {
      this.driver.set(d);
      this.notify(true, text);
    });
  }

  private fail(e: HttpErrorResponse) {
    const errors: Record<string, string[]> = e.error?.errors ?? {};
    this.fieldErrors.set(Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]])));
    this.notify(false, httpErrorText(e, 'Speichern fehlgeschlagen.'));
  }

  /** Meldung oben zeigen UND dorthin scrollen (Speichern sitzt weit unten). Gescrollt wird am eigenen Host. */
  private notify(ok: boolean, text: string) {
    this.message.set({ ok, text });
    this.host.nativeElement.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
}

/** "2027-08-02" als lokales Datum (ohne Zeitzonenverschiebung). */
function parse(iso: string): Date {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d);
}
