import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Page } from './customer.service';
import { DocumentTypeValue, JourneySectionInfo } from './document.service';

/** Der Kunde einer Reise in der Kurzform der Oberfläche */
export interface JourneyCustomer {
  id: string;
  customer_number: string;
  display_name: string;
  company_name?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  postal_code?: string | null;
  city?: string | null;
}

/** Zeile der Reisenliste. Stand und Betrag sind aus den Belegen abgeleitet, nicht gespeichert. */
export interface JourneyRow {
  id: string;
  title: string;
  date_from: string | null;
  date_to: string | null;
  customer: JourneyCustomer | null;
  sections_count: number;
  document_count: number;
  /** weitestgehender gültiger Beleg: Rechnung vor Auftragsbestätigung vor Angebot (nicht storniert); null ohne einen solchen */
  stage: DocumentTypeValue | null;
  stage_document_id: string | null;
  /** Brutto dieses Belegs */
  amount: string | null;
  created_at: string;
}

export interface JourneyDetail {
  id: string;
  title: string;
  date_from: string | null;
  date_to: string | null;
  customer: { id: string; customer_number: string; display_name: string } | null;
  sections: (Omit<JourneySectionInfo, 'trip_id'> & {
    trip: { id: string; title: string; loaded_km: number; empty_km: number; drive_s: number; days: number; from: string | null; to: string | null } | null;
  })[];
  documents: {
    id: string;
    type: DocumentTypeValue;
    number: string | null;
    status: 'entwurf' | 'festgeschrieben';
    cancelled: boolean;
    document_date: string | null;
    gross_total: string;
    subject: string | null;
  }[];
  stage: DocumentTypeValue | null;
  stage_document_id: string | null;
  amount: string | null;
  document_count: number;
}

@Injectable({ providedIn: 'root' })
export class JourneyService {
  private http = inject(HttpClient);
  private base = '/api/journeys';

  list(q: string, page: number) {
    let params = new HttpParams().set('page', page);
    if (q.trim()) params = params.set('q', q.trim());
    return this.http.get<Page<JourneyRow>>(this.base, { params });
  }

  get(id: string) {
    return this.http.get<JourneyDetail>(`${this.base}/${id}`);
  }
}
