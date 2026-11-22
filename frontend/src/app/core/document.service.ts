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

/** Fahrtart einer Position: bestimmt den Steuersatz der Beförderung (Gelegenheitsverkehr 19 %, Linie und Schule ermäßigt bis 50 km). */
export type TripKindValue = 'gelegenheit' | 'linie' | 'schule' | 'sonstig';

export interface DocumentLine {
  id?: string;
  title: string;
  description: string | null;
  quantity: number | string;
  unit: string;
  /** leer bei Positionen, die keine Fahrt sind, und bei alten Belegen */
  trip_kind?: TripKindValue | null;
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

/** Zahlstand einer Rechnung oder Anzahlungsrechnung (aus den Zahlungsbuchungen, der Beleg selbst bleibt unverändert). */
export interface PaymentSummary {
  /** Zu zahlen (Brutto minus Anzahlungen) */
  payable: string;
  paid: string;
  open: string;
  state: 'offen' | 'teilweise' | 'bezahlt' | 'storniert';
  /** noch etwas offen und die Fälligkeit liegt vor heute */
  overdue: boolean;
}

export interface Payment {
  id: string;
  kind: 'eingang' | 'rueckzahlung';
  paid_on: string;
  amount: string;
  method: string;
  reference: string | null;
  note: string | null;
  cancelled_at: string | null;
  cancel_reason: string | null;
}

export interface PaymentsResponse {
  payments: Payment[];
  summary: PaymentSummary | null;
  kinds: { value: Payment['kind']; label: string }[];
  methods: { value: string; label: string }[];
}

export interface PaymentInput {
  kind?: Payment['kind'];
  paid_on: string;
  amount: number;
  method?: string;
  reference?: string | null;
  note?: string | null;
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
  /** Prüfhinweise zum Steuersatz (nur Warnungen, vom Server bei Anzeige und Speichern berechnet) */
  warnings?: string[];
  /** Zahlstand; nur bei festgeschriebenen Rechnungen und Anzahlungsrechnungen, sonst null */
  payment?: PaymentSummary | null;
  /** E-Rechnung: Format der archivierten XML (`xrechnung`), null solange es keine gibt */
  einvoice_format?: string | null;
  customer?: { id: string; customer_number: string; display_name: string } | null;
  /** Stufe in der Belegkette (0 = Ursprung), nur in der Ansicht „je Kunde“ */
  chain_depth?: number;
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

  list(opts: { q: string; type: string; status: string; page: number; order?: 'column' | 'chain'; sort?: string; dir?: 'asc' | 'desc'; locate?: string | null }) {
    let params = new HttpParams().set('page', opts.page);
    if (opts.q) params = params.set('q', opts.q);
    if (opts.type) params = params.set('type', opts.type);
    if (opts.status) params = params.set('status', opts.status);
    if (opts.locate) params = params.set('locate', opts.locate);
    if (opts.order === 'chain') params = params.set('order', 'chain');
    else if (opts.sort) params = params.set('sort', opts.sort).set('dir', opts.dir ?? 'desc');
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
  /** E-Rechnung (XRechnung-XML) eines festgeschriebenen Belegs; entsteht beim ersten Abruf, falls es noch keine gibt. */
  zugferd(id: string) {
    return this.http.get(`${this.base}/${id}/zugferd`, { responseType: 'blob' });
  }

  einvoice(id: string) {
    return this.http.get(`${this.base}/${id}/einvoice`, { responseType: 'blob' });
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

  // ---------- Zahlungen (Rechnungen und Anzahlungsrechnungen) ----------

  payments(id: string) {
    return this.http.get<PaymentsResponse>(`${this.base}/${id}/payments`);
  }
  addPayment(id: string, data: PaymentInput) {
    return this.http.post<PaymentsResponse>(`${this.base}/${id}/payments`, data);
  }
  /** Buchung stornieren (nie löschen); der Grund ist Pflicht. */
  cancelPayment(id: string, paymentId: string, reason: string) {
    return this.http.post<PaymentsResponse>(`${this.base}/${id}/payments/${paymentId}/cancel`, { reason });
  }
}
