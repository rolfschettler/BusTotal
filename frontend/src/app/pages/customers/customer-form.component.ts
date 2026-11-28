import { TitleCasePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnInit, computed, inject, signal } from '@angular/core';
import { FormBuilder, FormsModule, NgForm, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { debounceTime } from 'rxjs';
import { BackNavigation } from '../../core/back-navigation.service';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { useFormPage } from '../../core/form-page';
import { httpErrorText } from '../../core/http-error';
import { ListMemory } from '../../core/list-memory.service';
import { HelpLinkComponent } from '../../help/help-link.component';
import { HelpService } from '../../help/help.service';
import {
  Address,
  Contact,
  Customer,
  CustomerInput,
  CustomerService,
  CustomerType,
  DuplicateHit,
} from '../../core/customer.service';

type Tab = 'stamm' | 'kontakte' | 'adressen';

const emptyContact = (): Contact => ({
  salutation: null, first_name: null, last_name: '', position: null,
  email: null, phone: null, mobile: null, is_primary: false, notes: null,
});
const emptyAddress = (): Address => ({
  type: 'abholung', label: null, street: '', postal_code: '', city: '', country: 'DE', notes: null,
});

@Component({
  selector: 'app-customer-form',
  imports: [ReactiveFormsModule, FormsModule, RouterLink, TitleCasePipe, HelpLinkComponent],
  templateUrl: './customer-form.component.html',
  // Formular-Muster: Optik kommt global aus styles/_edit-form.scss
  host: { class: 'form-page form-page-record' },
})
export class CustomerFormComponent implements OnInit {
  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  private api = inject(CustomerService);
  private fb = inject(FormBuilder);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private backNav = inject(BackNavigation);
  private auth = inject(AuthService);
  private confirm = inject(ConfirmService);
  private help = inject(HelpService);
  private memory = inject(ListMemory);

  isAdmin = computed(() => this.auth.user()?.role === 'admin');
  /** DSGVO-Löschung: Administrator und Buchhaltung (der Server prüft zusätzlich). */
  canAnonymize = computed(() => ['admin', 'buchhaltung'].includes(this.auth.user()?.role ?? ''));

  id: string | null = null;
  customer = signal<Customer | null>(null);
  types = signal<CustomerType[]>([]);
  tab = signal<Tab>('stamm');
  message = signal<{ ok: boolean; text: string } | null>(null);
  fieldErrors = signal<Record<string, string>>({});
  duplicates = signal<DuplicateHit[]>([]);
  saving = signal(false);
  /** Wahr, solange ein vorhandener Kunde geladen wird. */
  loading = signal(false);
  /** Nach dem ersten Speichern-Versuch markieren die Formulare fehlerhafte Felder. */
  formSubmitted = signal(false);
  contactSubmitted = signal(false);
  addressSubmitted = signal(false);

  contactEdit = signal<Contact | null>(null);
  addressEdit = signal<Address | null>(null);
  /** Stand beim Öffnen von Ansprechpartner bzw. Adresse: Daran ist zu erkennen, ob etwas geändert wurde. */
  private contactBefore = '';
  private addressBefore = '';

  form = this.fb.nonNullable.group({
    type: ['privat', Validators.required],
    company_name: [''],
    salutation: [''],
    first_name: [''],
    last_name: [''],
    email: ['', Validators.email],
    phone: [''],
    mobile: [''],
    website: [''],
    vat_id: [''],
    street: [''],
    postal_code: [''],
    city: [''],
    country: ['DE', [Validators.required, Validators.minLength(2), Validators.maxLength(2)]],
    payment_term_days: [null as number | null],
    einvoice: [false],
    buyer_reference: [''],
    notes: [''],
  });

  isOrganisation = computed(() => this.types().find((t) => t.value === this.typeValue())?.organisation ?? this.typeValue() !== 'privat');
  typeValue = signal('privat');
  archived = computed(() => !!this.customer()?.deleted_at);
  /** Reisen planen dürfen Administrator und Disponent (der Server prüft zusätzlich). */
  canPlan = computed(() => ['admin', 'disponent'].includes(this.auth.user()?.role ?? ''));
  typeLabel = (value: string) => this.types().find((t) => t.value === value)?.label ?? value;

  ngOnInit() {
    this.api.types().subscribe((t) => this.types.set(t));

    const idParam = this.route.snapshot.paramMap.get('id');
    this.id = idParam ?? null;
    if (this.id) {
      this.memory.follow('kunden', this.id); // der Rücksprung zur Liste landet auf diesem Kunden
      this.load(this.id);
    }

    this.form.controls.type.valueChanges.subscribe((v) => {
      this.typeValue.set(v);
      this.applyTypeValidators();
    });
    this.applyTypeValidators();

    // Dublettenprüfung: kurz nach der letzten Eingabe in den relevanten Feldern.
    this.form.valueChanges.pipe(debounceTime(500)).subscribe(() => this.checkDuplicates());
  }

  private applyTypeValidators() {
    const { company_name, last_name } = this.form.controls;
    company_name.setValidators(this.isOrganisation() ? [Validators.required] : []);
    last_name.setValidators(this.isOrganisation() ? [] : [Validators.required]);
    company_name.updateValueAndValidity({ emitEvent: false });
    last_name.updateValueAndValidity({ emitEvent: false });
  }

  /** Feld rot markieren: Eingabe ungültig (nach Speichern-Versuch) oder vom Server abgelehnt. */
  invalid(name: keyof typeof this.form.controls): boolean {
    const c = this.form.controls[name];
    return (this.formSubmitted() && c.invalid) || !!this.fieldErrors()[name];
  }

  private load(id: string) {
    this.loading.set(true);
    this.api.get(id).subscribe({
      next: (c) => {
        this.loading.set(false);
        this.customer.set(c);
        this.typeValue.set(c.type);
        this.form.patchValue(this.toFormValue(c), { emitEvent: false });
        this.applyTypeValidators();
        if (c.deleted_at) this.form.disable({ emitEvent: false });
      },
      error: () => {
        this.loading.set(false);
        this.router.navigate(['/kunden']);
      },
    });
  }

  private toFormValue(c: Customer) {
    const v = { ...c } as Record<string, unknown>;
    for (const k of Object.keys(v)) if (v[k] === null && k !== 'payment_term_days') v[k] = '';
    return v as never;
  }

  private checkDuplicates() {
    if (this.form.disabled) return;
    const v = this.form.getRawValue();
    this.api
      .duplicates({
        email: v.email, company_name: v.company_name, first_name: v.first_name,
        last_name: v.last_name, postal_code: v.postal_code, ignore: this.id,
      })
      .subscribe((hits) => this.duplicates.set(hits));
  }

  show(tab: Tab) {
    this.tab.set(tab);
    // Das Hilfefenster folgt dem Reiter; "stamm" nutzt das Thema der Seite.
    this.help.setAnchor({ stamm: null, kontakte: 'ansprechpartner', adressen: 'adressen' }[tab]);
    this.message.set(null);
    this.fieldErrors.set({});
    this.contactSubmitted.set(false);
    this.addressSubmitted.set(false);
  }

  save() {
    this.formSubmitted.set(true);
    if (this.form.invalid) {
      this.notify(false, 'Bitte die markierten Felder korrigieren.');
      return;
    }
    // Leere Felder als null senden, nicht als Text "".
    const raw = this.form.getRawValue() as Record<string, unknown>;
    const body = Object.fromEntries(Object.entries(raw).map(([k, v]) => [k, v === '' ? null : v])) as unknown as CustomerInput;

    this.saving.set(true);
    const req = this.id ? this.api.update(this.id, body) : this.api.create(body);
    req.subscribe({
      next: (c) => {
        this.saving.set(false);
        if (!this.id) {
          this.form.markAsPristine(); // gespeichert: beim Wechsel zum neuen Kunden nicht nachfragen
          this.router.navigate(['/kunden', c.id]);
          return;
        }
        this.customer.set(c);
        this.form.markAsPristine();
        this.ok('Gespeichert.');
      },
      error: (e) => {
        this.saving.set(false);
        this.fail(e);
      },
    });
  }

  /** „Zurück“ oben: zur vorigen Seite. Nach ungespeicherten Änderungen fragt `canLeave`. */
  back() {
    void this.backNav.back('/kunden');
  }

  // ---------- Ungespeicherte Eingaben und Tastatur ----------

  /** Stammdaten, Ansprechpartner oder Adresse geändert und noch nicht gespeichert? */
  hasUnsaved(): boolean {
    if (this.archived()) return false;
    const contact = this.contactEdit();
    const address = this.addressEdit();
    return (
      this.form.dirty ||
      (!!contact && JSON.stringify(contact) !== this.contactBefore) ||
      (!!address && JSON.stringify(address) !== this.addressBefore)
    );
  }

  /** Strg+S, Warnung beim Neuladen und Rückfrage beim Verlassen (Formular-Muster). */
  canLeave = useFormPage({ unsaved: () => this.hasUnsaved(), save: () => this.saveVisible() });

  /** Speichert das Formular des sichtbaren Reiters. */
  private saveVisible() {
    if (this.archived() || this.loading()) return;
    if (this.tab() === 'stamm') {
      if (!this.saving() && (!this.id || this.form.dirty)) this.save();
    } else {
      const id = this.tab() === 'kontakte' ? 'contactForm' : 'addressForm';
      this.host.nativeElement.querySelector<HTMLFormElement>('#' + id)?.requestSubmit();
    }
  }

  /** „Verwerfen“: Eingaben zurücknehmen, Formular auf den gespeicherten Stand zurücksetzen. */
  cancel() {
    const c = this.customer();
    if (c) {
      this.form.reset(undefined, { emitEvent: false });
      this.form.patchValue(this.toFormValue(c), { emitEvent: false });
      this.typeValue.set(c.type);
    } else {
      this.form.reset(undefined, { emitEvent: false });
      this.typeValue.set(this.form.controls.type.value);
    }
    this.applyTypeValidators();
    this.form.markAsPristine();
    this.formSubmitted.set(false);
    this.fieldErrors.set({});
    this.message.set(null);
  }

  // ---------- Archiv, Export, DSGVO ----------

  async archive() {
    if (!this.id) return;
    const ok = await this.confirm.ask({
      title: 'Kunde archivieren',
      message: 'Der Kunde verschwindet aus der Liste, bleibt aber für Belege erhalten.',
      confirmLabel: 'Archivieren',
    });
    if (ok) this.api.archive(this.id).subscribe(() => this.leaveToList());
  }

  restore() {
    if (!this.id) return;
    this.api.restore(this.id).subscribe((c) => {
      this.customer.set(c);
      this.form.enable({ emitEvent: false });
      this.ok('Kunde wiederhergestellt.');
    });
  }

  export() {
    if (!this.id) return;
    this.api.export(this.id).subscribe((blob) => {
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = `kunde-${this.customer()?.customer_number}.json`;
      a.click();
      URL.revokeObjectURL(a.href);
    });
  }

  async anonymize() {
    if (!this.id) return;
    const ok = await this.confirm.ask({
      title: 'Kunde anonymisieren',
      message:
        'Alle personenbezogenen Daten dieses Kunden werden UNWIDERRUFLICH entfernt.\n' +
        'Kunden- und Debitorennummer bleiben für Rechnungen erhalten.',
      confirmLabel: 'Endgültig anonymisieren',
      danger: true,
    });
    if (ok) this.api.anonymize(this.id).subscribe(() => this.leaveToList());
  }

  /** Nach Archivieren oder Anonymisieren zur Liste; offene Eingaben sind damit hinfällig. */
  private leaveToList() {
    this.form.markAsPristine();
    this.contactEdit.set(null);
    this.addressEdit.set(null);
    this.router.navigate(['/kunden']);
  }

  // ---------- Ansprechpartner ----------

  newContact() {
    this.openContact(emptyContact());
  }
  editContact(c: Contact) {
    this.openContact({ ...c });
  }
  private openContact(c: Contact) {
    this.contactSubmitted.set(false);
    this.contactBefore = JSON.stringify(c);
    this.contactEdit.set(c);
  }
  saveContact(f: NgForm) {
    const c = this.contactEdit();
    if (!c || !this.id) return;
    this.contactSubmitted.set(true);
    if (f.invalid) return;
    this.api.saveContact(this.id, c).subscribe({
      next: () => {
        this.contactEdit.set(null);
        this.reload('Ansprechpartner gespeichert.');
      },
      error: (e) => this.fail(e),
    });
  }
  async deleteContact(c: Contact) {
    if (!this.id || !c.id) return;
    const ok = await this.confirm.ask({
      title: 'Ansprechpartner löschen',
      message: `${c.first_name ?? ''} ${c.last_name} wirklich löschen?`.trim(),
      confirmLabel: 'Löschen',
      danger: true,
    });
    if (ok) this.api.deleteContact(this.id, c.id).subscribe(() => this.reload('Ansprechpartner gelöscht.'));
  }

  // ---------- Adressen ----------

  newAddress() {
    this.openAddress(emptyAddress());
  }
  editAddress(a: Address) {
    this.openAddress({ ...a });
  }
  private openAddress(a: Address) {
    this.addressSubmitted.set(false);
    this.addressBefore = JSON.stringify(a);
    this.addressEdit.set(a);
  }
  saveAddress(f: NgForm) {
    const a = this.addressEdit();
    if (!a || !this.id) return;
    this.addressSubmitted.set(true);
    if (f.invalid) return;
    this.api.saveAddress(this.id, a).subscribe({
      next: () => {
        this.addressEdit.set(null);
        this.reload('Adresse gespeichert.');
      },
      error: (e) => this.fail(e),
    });
  }
  async deleteAddress(a: Address) {
    if (!this.id || !a.id) return;
    const ok = await this.confirm.ask({
      title: 'Adresse löschen',
      message: `${a.street}, ${a.postal_code} ${a.city} wirklich löschen?`,
      confirmLabel: 'Löschen',
      danger: true,
    });
    if (ok) this.api.deleteAddress(this.id, a.id).subscribe(() => this.reload('Adresse gelöscht.'));
  }

  private reload(text: string) {
    if (!this.id) return;
    this.api.get(this.id).subscribe((c) => {
      this.customer.set(c);
      this.ok(text);
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

  /**
   * Meldung ganz oben zeigen UND dorthin scrollen: "Speichern" sitzt bei langen Formularen weit unten,
   * sonst sieht der Nutzer die Meldung nicht. Gescrollt wird am eigenen Host, nicht am Fenster.
   */
  private notify(ok: boolean, text: string) {
    this.message.set({ ok, text });
    this.host.nativeElement.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
}
