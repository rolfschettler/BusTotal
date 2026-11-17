import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

export interface Company {
  name: string;
  legal_form: string | null;
  street: string | null;
  postal_code: string | null;
  city: string | null;
  country: string;
  phone: string | null;
  email: string | null;
  website: string | null;
  tax_number: string | null;
  vat_id: string | null;
  commercial_register: string | null;
  managing_director: string | null;
  bank_name: string | null;
  iban: string | null;
  bic: string | null;
  letterhead_note: string | null;
  footer_text: string | null;
  payment_term_days: number;
  brand_color: string;
  has_logo: boolean;
}

export interface TaxRate {
  id: number;
  name: string;
  rate: string;
  active: boolean;
}

export interface NumberRange {
  id: number;
  type: string;
  label: string;
  prefix: string;
  include_year: boolean;
  padding: number;
  next_number: number;
  reset_yearly: boolean;
  preview: string;
}

@Injectable({ providedIn: 'root' })
export class SettingsService {
  private http = inject(HttpClient);

  getCompany() {
    return this.http.get<Company>('/api/settings/company');
  }
  saveCompany(data: Company) {
    return this.http.put<Company>('/api/settings/company', data);
  }

  uploadLogo(file: File) {
    const body = new FormData();
    body.append('logo', file);
    return this.http.post<Company>('/api/settings/logo', body);
  }
  deleteLogo() {
    return this.http.delete<Company>('/api/settings/logo');
  }

  getTaxRates() {
    return this.http.get<TaxRate[]>('/api/settings/tax-rates');
  }
  saveTaxRate(t: Pick<TaxRate, 'id' | 'name' | 'rate' | 'active'>) {
    return this.http.put<TaxRate>(`/api/settings/tax-rates/${t.id}`, t);
  }
  addTaxRate(t: { name: string; rate: number; active: boolean }) {
    return this.http.post<TaxRate>('/api/settings/tax-rates', t);
  }

  getNumberRanges() {
    return this.http.get<NumberRange[]>('/api/settings/number-ranges');
  }
  saveNumberRange(r: NumberRange) {
    return this.http.put<NumberRange>(`/api/settings/number-ranges/${r.id}`, {
      prefix: r.prefix,
      include_year: r.include_year,
      padding: r.padding,
      reset_yearly: r.reset_yearly,
      next_number: r.next_number,
    });
  }
}
