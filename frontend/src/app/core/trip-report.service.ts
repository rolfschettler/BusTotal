import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { TripOrderStatus } from './trip-order.service';

export interface ReportTime {
  event: string;
  date: string | null;
  place: string | null;
  time: string | null;
  km: number | null;
  /** Art der Strecke bis zu dieser Zeile */
  leg: 'betriebshof' | 'befoerderung' | 'leerfahrt' | null;
}

export interface ReportBorder {
  country: string;
  km_in: number | null;
  km_out: number | null;
  source: 'fahrer' | 'tachograph' | 'navigation';
}

export interface ReportExtra {
  title: string;
  quantity: string | null;
  place: string | null;
  amount: number | string | null;
  payment: 'bar' | 'rechnung';
  tax_rate: number | string;
}

export interface ReportCost {
  category: string;
  amount: number | string | null;
  receipt_no: string | null;
  travel_service: boolean;
  charge_customer: boolean;
}

/** Was das Formular speichert. */
export interface ReportInput {
  actual_passengers: string | null;
  report_notes: string | null;
  times: ReportTime[];
  borders: ReportBorder[];
  extras: ReportExtra[];
  costs: ReportCost[];
}

export interface TripReportOrder {
  id: string;
  number: string | null;
  title: string;
  bus_no: number;
  status: TripOrderStatus;
  status_label: string;
  cancelled_at: string | null;
  billing_status: string;
  date_from: string | null;
  date_to: string | null;
  journey_id: string | null;
  customer: string | null;
  vehicle: string | null;
  external_company: string | null;
  drivers: string[];
  passengers: string | null;
}

export interface TripReportData {
  order: TripReportOrder;
  report: ReportInput & {
    saved: boolean;
    planned_km: number | null;
    editable: boolean;
    /** was für „erledigt“ noch fehlt */
    missing: string[];
    warnings: string[];
  };
  choices: {
    categories: { value: string; label: string; travel_service: boolean }[];
    default_tax_rate: number;
    tax_rates: number[];
  };
}

@Injectable({ providedIn: 'root' })
export class TripReportService {
  private http = inject(HttpClient);
  private url = (id: string, suffix = '') => `/api/trip-orders/${id}/report${suffix}`;

  get(id: string) {
    return this.http.get<TripReportData>(this.url(id));
  }

  save(id: string, data: ReportInput) {
    return this.http.put<TripReportData>(this.url(id), data);
  }

  complete(id: string) {
    return this.http.post<TripReportData>(this.url(id, '/complete'), {});
  }

  /** „Erledigt“ zurücknehmen: wieder „ausgegeben“, die Eingaben bleiben. */
  reopen(id: string, reason: string) {
    return this.http.post<TripReportData>(this.url(id, '/reopen'), { reason });
  }
}
