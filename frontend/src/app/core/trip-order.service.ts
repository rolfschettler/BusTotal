import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Page } from './customer.service';

export type TripOrderStatus = 'entwurf' | 'disponiert' | 'ausgegeben' | 'erledigt';

/** Hinweis zur Zuweisung (nie eine Sperre): `field` sagt, an welchem Feld er hängt. */
export interface TripOrderWarning {
  code: string;
  field: 'vehicle' | 'driver' | 'driver2';
  text: string;
}

export interface TripOrder {
  id: string;
  number: string | null;
  status: TripOrderStatus;
  status_label: string;
  title: string;
  vehicle_type: string | null;
  vehicle_type_label: string | null;
  bus_no: number;
  journey_id: string | null;
  document_id: string | null;
  customer: { id: string; customer_number: string; display_name: string; company_name?: string | null; first_name?: string | null; last_name?: string | null; postal_code?: string | null; city?: string | null } | null;
  date_from: string | null;
  date_to: string | null;
  /** Zeitpunkt (UTC, ISO); die Oberfläche zeigt und erfasst ihn in der Zeit des Browsers */
  departure_at: string | null;
  passengers: number | null;
  vehicle: { id: string; display_name: string; seats: number | null } | null;
  vehicle_id: string | null;
  external_company: string | null;
  external_plate: string | null;
  driver: { id: string; display_name: string } | null;
  driver_id: string | null;
  driver2: { id: string; display_name: string } | null;
  driver2_id: string | null;
  driver_note: string | null;
  notes: string | null;
  collective_only: boolean;
  billing_status: string;
  /** nur im Einzelabruf */
  warnings?: TripOrderWarning[];
  document_number?: string | null;
}

/** Was das Formular an den Server schickt. Fehlende Felder bleiben unverändert. */
export interface TripOrderInput {
  vehicle_id: string | null;
  external_company: string | null;
  external_plate: string | null;
  driver_id: string | null;
  driver2_id: string | null;
  date_from: string | null;
  date_to: string | null;
  departure_at: string | null;
  passengers: number | null;
  driver_note: string | null;
  notes: string | null;
  /** Begründung, nötig bei Änderungen nach dem Ausgeben */
  reason?: string | null;
}

@Injectable({ providedIn: 'root' })
export class TripOrderService {
  private http = inject(HttpClient);
  private base = '/api/trip-orders';

  list(opts: { q: string; status: string; journeyId: string; page: number }) {
    let params = new HttpParams().set('page', opts.page);
    if (opts.q.trim()) params = params.set('q', opts.q.trim());
    if (opts.status) params = params.set('status', opts.status);
    if (opts.journeyId) params = params.set('journey_id', opts.journeyId);
    return this.http.get<Page<TripOrder>>(this.base, { params });
  }

  get(id: string) {
    return this.http.get<TripOrder>(`${this.base}/${id}`);
  }

  update(id: string, data: Partial<TripOrderInput>) {
    return this.http.put<TripOrder>(`${this.base}/${id}`, data);
  }

  issue(id: string) {
    return this.http.post<TripOrder>(`${this.base}/${id}/issue`, {});
  }

  /** Fahrer-PDF (ohne Preise) für die Druckvorschau */
  pdf(id: string) {
    return this.http.get(`${this.base}/${id}/pdf`, { responseType: 'blob' });
  }
}

export const TRIP_ORDER_STATUS: { value: TripOrderStatus; label: string }[] = [
  { value: 'entwurf', label: 'Zu disponieren' },
  { value: 'disponiert', label: 'Disponiert' },
  { value: 'ausgegeben', label: 'Ausgegeben' },
  { value: 'erledigt', label: 'Erledigt' },
];

/** Farbe des Stand-Abzeichens: offen = gelb, disponiert = blau, ausgegeben = grün, erledigt = grau */
export function statusBadge(status: TripOrderStatus): string {
  return { entwurf: 'text-bg-warning', disponiert: 'text-bg-info', ausgegeben: 'text-bg-success', erledigt: 'text-bg-secondary' }[status];
}
