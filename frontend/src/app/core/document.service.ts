import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Page } from './customer.service';

export type DocumentTypeValue = 'angebot' | 'auftragsbestaetigung' | 'anzahlung' | 'rechnung' | 'gutschrift';

export interface DocumentTypeInfo {
  value: DocumentTypeValue;
  label: string;
  /** Rechnung im steuerlichen Sinn (strenge Pflichtangaben) */
  invoice: boolean;
  convertible_to: DocumentTypeValue[];
}

export interface DocumentLine {
  id?: string;
  title: string;
  description: string | null;
  quantity: number | string;
  unit: string;
  unit_price: number | string;
  tax_rate: number | string;
  tax_note: string | null;
  /** vom Server berechnet */
  net_amount?: string;
}

/** Absender oder Empfänger, so wie sie beim Festschreiben kopiert wurden. */
export interface DocumentParty {
  name: string;
  street: string | null;
  postal_code: string | null;
  city: string | null;
  country: string | null;
  customer_number?: string;
  debitor_number?: number | null;
  vat_id?: string | null;
  tax_number?: string | null;
}

export interface TaxSummaryRow {
  rate: string;
  net: string;
  tax: string;
}

export interface DocumentDeduction {
  document_id: string;
  number: string;
  document_date: string | null;
  net: string;
  tax: string;
  gross: string;
}

export interface BusDocument {
  id: string;
  type: DocumentTypeValue;
  status: 'entwurf' | 'festgeschrieben';
  number: string | null;
  customer_id: string | null;
  source_id: string | null;
  cancels_id: string | null;
  cancelled_by_id: string | null;
  seller: DocumentParty | null;
  recipient: DocumentParty | null;
  subject: string | null;
  intro_text: string | null;
  footer_text: string | null;
  notes: string | null;
  document_date: string | null;
  service_date_from: string | null;
  service_date_to: string | null;
  payment_term_days: number | null;
  due_date: string | null;
  currency: string;
  net_total: string;
  tax_total: string;
  gross_total: string;
  tax_summary: TaxSummaryRow[] | null;
  /** Schlussrechnung: abgezogene Anzahlungen (getrennt ausgewiesen, nicht in den Positionen) */
  deductions: DocumentDeduction[] | null;
  deduction_net: string;
  deduction_tax: string;
  deduction_gross: string;
  /** Brutto minus Anzahlungen */
  payable_total: string;
  finalized_at: string | null;
  lines?: DocumentLine[];
  customer?: { id: string; customer_number: string; display_name: string } | null;
  source?: { id: string; type: DocumentTypeValue; number: string | null } | null;
  cancelled_by?: { id: string; number: string | null } | null;
}

export interface DocumentInput {
  type?: DocumentTypeValue;
  customer_id: string | null;
  subject: string | null;
  intro_text: string | null;
  footer_text: string | null;
  notes: string | null;
  document_date: string | null;
  service_date_from: string | null;
  service_date_to: string | null;
  payment_term_days: number | null;
  lines: DocumentLine[];
}

@Injectable({ providedIn: 'root' })
export class DocumentService {
  private http = inject(HttpClient);
  private base = '/api/documents';

  list(opts: { q: string; type: string; status: string; page: number }) {
    let params = new HttpParams().set('page', opts.page);
    if (opts.q) params = params.set('q', opts.q);
    if (opts.type) params = params.set('type', opts.type);
    if (opts.status) params = params.set('status', opts.status);
    return this.http.get<Page<BusDocument>>(this.base, { params });
  }

  types() {
    return this.http.get<DocumentTypeInfo[]>(`${this.base}/types`);
  }
  get(id: string) {
    return this.http.get<BusDocument>(`${this.base}/${id}`);
  }
  create(data: DocumentInput) {
    return this.http.post<BusDocument>(this.base, data);
  }
  update(id: string, data: DocumentInput) {
    return this.http.put<BusDocument>(`${this.base}/${id}`, data);
  }
  /** PDF des Belegs (wird bei jedem Abruf neu erzeugt) */
  pdf(id: string) {
    return this.http.get(`${this.base}/${id}/pdf`, { responseType: 'blob' });
  }
  remove(id: string) {
    return this.http.delete(`${this.base}/${id}`);
  }
  finalize(id: string) {
    return this.http.post<BusDocument>(`${this.base}/${id}/finalize`, {});
  }
  cancel(id: string) {
    return this.http.post<BusDocument>(`${this.base}/${id}/cancel`, {});
  }
  /** `percent` gilt nur für die Anzahlungsrechnung (Anteil am Auftrag, Vorbelegung 30). */
  convert(id: string, type: DocumentTypeValue, percent?: number) {
    return this.http.post<BusDocument>(`${this.base}/${id}/convert`, percent ? { type, percent } : { type });
  }
}
