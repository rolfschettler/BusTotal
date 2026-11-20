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
  remove(id: string) {
    return this.http.delete(`${this.base}/${id}`);
  }
  finalize(id: string) {
    return this.http.post<BusDocument>(`${this.base}/${id}/finalize`, {});
  }
  cancel(id: string) {
    return this.http.post<BusDocument>(`${this.base}/${id}/cancel`, {});
  }
  convert(id: string, type: DocumentTypeValue) {
    return this.http.post<BusDocument>(`${this.base}/${id}/convert`, { type });
  }
}
