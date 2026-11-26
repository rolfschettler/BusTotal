import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Page } from './customer.service';
import { DocumentTypeValue, JourneyTripOrders, OwnFigures, SectionKindValue, TripKindValue } from './document.service';
import { TourPayload } from './pricing.service';
import { RouteResult } from './route.service';

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

/** Zustand einer Strecke gegenüber dem Angebot (Entwurf, sonst das festgeschriebene): neu (noch keine Positionen), geändert (Eingaben anders als bei der Berechnung), aktuell. */
export type SectionState = 'neu' | 'geaendert' | 'aktuell';

/** Stand der Reise und ihres Angebots: Entwurf, festgeschriebenes Angebot, Zustand je Strecke und was die Tasten dürfen. */
export interface QuoteStatus {
  draft_id: string | null;
  issued_id: string | null;
  sections: { id: string; state: SectionState }[];
  can_create: boolean;
  can_sync: boolean;
  /** Zahl der neuen und geänderten Strecken */
  pending: number;
}

/** Eine Strecke auf der Reise-Seite. */
export interface JourneyDetailSection {
  id: string;
  kind: SectionKindValue;
  kind_label: string;
  title: string;
  /** Fahrtdatum (freiwillig) und Zuschlag in Prozent: bestimmen die Preise an Wochenende und Feiertag */
  start_on: string | null;
  surcharge_percent: number;
  /** Eigene Angaben einer Strecke ohne Tour (etwa einer Fahrt vor Ort), sonst null */
  own?: OwnFigures | null;
  vehicles: { type: string | null; label: string | null; count: number }[];
  /** frozen_by: Nummer des festgeschriebenen Belegs, aus dem die Angaben stammen (Stand beim Festschreiben); null = Kopie der Strecke aus dem Angebot */
  trip: { id: string | null; title: string; loaded_km: number; empty_km: number; drive_s: number; days: number; from: string | null; to: string | null; frozen_by: string | null } | null;
}

export interface JourneyDetail {
  id: string;
  title: string;
  date_from: string | null;
  date_to: string | null;
  customer: JourneyCustomer | null;
  sections: JourneyDetailSection[];
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
  trip_orders: JourneyTripOrders;
  quote: QuoteStatus;
}

/**
 * Eine Strecke für die Reise: Art, Quelle (Tour aus dem Archiv ODER in der Streckenplanung berechnete Tour ODER eigene Angaben mit
 * Bezeichnung, Kilometern, Fahrzeit in Minuten und Tagen), Fahrzeugbedarf, freiwillig Fahrtdatum und Zuschlag.
 */
export interface SectionBody {
  kind: SectionKindValue;
  trip_id?: string;
  tour?: TourPayload;
  own?: { title: string; loaded_km: number; drive_minutes: number; days: number };
  start_on?: string | null;
  surcharge_percent?: number;
  vehicles: { type: string; count: number }[];
  /** von Hand geänderte Werte einer berechneten Route */
  figures?: SectionFigures;
}

/** Die Werte einer Strecke, aus denen der Preis entsteht. */
export interface SectionFigures {
  loaded_km: number;
  empty_km: number;
  drive_s: number;
  days: number;
}

/** Ein Ort im gespeicherten Plan einer Strecke. */
export interface PlanPlace {
  label: string;
  lat: number;
  lon: number;
}

/** Eine Strecke zum Bearbeiten in der Streckenplanung. */
export interface SectionEditData {
  journey: { id: string; title: string };
  id: string;
  kind: SectionKindValue;
  kind_label: string;
  title: string;
  start_on: string | null;
  surcharge_percent: number;
  vehicles: { type: string | null; count: number }[];
  /** die gültigen Werte (auch von Hand geänderte) */
  figures: SectionFigures;
  /** Kopie der Route (Halte als Text, Teilstrecken); null: Strecke ohne Route */
  tour: (Omit<TourPayload, 'plan'> & { trip_kind: TripKindValue | null }) | null;
  /** Orte mit Lage, Betriebshof und Ergebnis zum Wiederöffnen; null bei älteren Strecken und ohne Route */
  plan: {
    depot_id: string | null;
    depot_other: PlanPlace | null;
    from_depot?: boolean;
    return_to_depot: boolean;
    start_at: string | null;
    stops: (PlanPlace & { dwell_minutes: number; driver_free: boolean })[];
    route: Omit<RouteResult, 'geometry'> | null;
    geometry: [number, number][] | null;
    calculated: SectionFigures;
  } | null;
  /** Nummer des festgeschriebenen Belegs, in dem die Strecke steht (er behält seinen Stand) */
  frozen_by: string | null;
}

/** Änderung einer Strecke; `tour` nur mit neuer Route. */
export interface SectionUpdate {
  title: string;
  start_on: string | null;
  surcharge_percent: number;
  vehicles: { type: string; count: number }[];
  figures: SectionFigures;
  tour?: TourPayload;
  trip_kind?: TripKindValue;
}

/** Antwort von „Angebot erstellen / aktualisieren“: der Entwurf und die Titel der neu berechneten Strecken. */
export interface QuoteResult {
  id: string;
  created: boolean;
  updated: string[];
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

  /** Neue Reise von Hand planen (Titel, freiwillig Kunde); Strecken kommen danach auf der Reise hinzu. */
  create(title: string, customerId: string | null) {
    return this.http.post<JourneyDetail>(this.base, { title, customer_id: customerId });
  }

  /** Name und Kunde der Reise ändern. */
  update(id: string, data: { title: string; customer_id?: string | null }) {
    return this.http.put<JourneyDetail>(`${this.base}/${id}`, data);
  }

  /** Die Reise umbenennen: Nur der Name ändert sich, er steht auf keinem Beleg. */
  rename(id: string, title: string) {
    return this.update(id, { title });
  }

  /** Eine Reise ohne Belege löschen. */
  remove(id: string) {
    return this.http.delete<{ message: string }>(`${this.base}/${id}`);
  }

  addSection(id: string, body: SectionBody) {
    return this.http.post<JourneyDetail>(`${this.base}/${id}/sections`, body);
  }

  getSection(id: string, sectionId: string) {
    return this.http.get<SectionEditData>(`${this.base}/${id}/sections/${sectionId}`);
  }

  /** Eine Strecke ändern; das Angebot zieht mit „Angebot aktualisieren“ nach. */
  updateSection(id: string, sectionId: string, body: SectionUpdate) {
    return this.http.put<JourneyDetail>(`${this.base}/${id}/sections/${sectionId}`, body);
  }

  removeSection(id: string, sectionId: string) {
    return this.http.delete<JourneyDetail>(`${this.base}/${id}/sections/${sectionId}`);
  }

  /** Angebot erstellen (noch kein Entwurf) oder aktualisieren (Entwurf: nur neue und geänderte Strecken neu berechnen). */
  quote(id: string) {
    return this.http.post<QuoteResult>(`${this.base}/${id}/quote`, {});
  }
}
