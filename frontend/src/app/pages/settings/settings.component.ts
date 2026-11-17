import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormBuilder, FormsModule, ReactiveFormsModule, Validators } from '@angular/forms';
import { AuthService } from '../../core/auth.service';
import { BrandService } from '../../core/brand.service';
import { NumberRange, SettingsService, TaxRate } from '../../core/settings.service';

type Tab = 'firma' | 'steuern' | 'nummern';

const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp'];
const LOGO_MAX_BYTES = 1024 * 1024;

@Component({
  selector: 'app-settings',
  imports: [ReactiveFormsModule, FormsModule],
  templateUrl: './settings.component.html',
  styleUrl: './settings.component.scss',
})
export class SettingsComponent implements OnInit {
  private api = inject(SettingsService);
  private fb = inject(FormBuilder);
  private auth = inject(AuthService);
  brand = inject(BrandService);

  canEdit = computed(() => this.auth.user()?.role === 'admin');
  tab = signal<Tab>('firma');
  message = signal<{ ok: boolean; text: string } | null>(null);
  fieldErrors = signal<Record<string, string>>({});

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

  taxRates = signal<TaxRate[]>([]);
  newTax = { name: '', rate: 0 };
  ranges = signal<NumberRange[]>([]);

  ngOnInit() {
    this.api.getCompany().subscribe((c) => {
      this.company.patchValue(c);
      if (!this.canEdit()) this.company.disable();
    });
    this.loadTaxRates();
    this.loadRanges();
  }

  private loadTaxRates() {
    this.api.getTaxRates().subscribe((t) => this.taxRates.set(t));
  }
  private loadRanges() {
    this.api.getNumberRanges().subscribe((r) => this.ranges.set(r));
  }

  show(tab: Tab) {
    this.tab.set(tab);
    this.message.set(null);
    this.fieldErrors.set({});
  }

  saveCompany() {
    if (this.company.invalid) {
      this.company.markAllAsTouched();
      return;
    }
    // Leere Felder als null senden, damit der Server sie nicht als Text "" speichert.
    const raw = this.company.getRawValue() as Record<string, unknown>;
    const body = Object.fromEntries(Object.entries(raw).map(([k, v]) => [k, v === '' ? null : v]));
    this.api.saveCompany(body as never).subscribe({
      next: (c) => {
        this.company.patchValue(c);
        this.brand.load(); // neue Farbe und Name sofort überall anzeigen
        this.ok('Firmendaten gespeichert.');
      },
      error: (e) => this.fail(e),
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
    this.message.set({ ok: true, text });
  }

  private fail(err: HttpErrorResponse) {
    const errors: Record<string, string[]> = err.error?.errors ?? {};
    this.fieldErrors.set(Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]])));
    this.message.set({
      ok: false,
      text: err.status === 403 ? 'Keine Berechtigung.' : (err.error?.message ?? 'Speichern fehlgeschlagen.'),
    });
  }
}
