import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Page } from './customer.service';

export type DeadlineStatus = 'overdue' | 'soon' | 'ok';

export interface Deadline {
  /** hu, sp, tachograph, service */
  kind: string;
  label: string;
  /** Kalendertag, YYYY-MM-DD */
  date: string;
  /** Tage bis zur Frist, negativ = abgelaufen */
  days: number;
  /** Vorwarnzeit dieser Frist in Tagen (sonst 30) */
  warn_days?: number;
  status: DeadlineStatus;
}

export interface Vehicle {
  id: string;
  fleet_number: string;
  license_plate: string;
  display_name: string;
  type: string;
  manufacturer: string | null;
  model: string | null;
  year_built: number | null;
  vin: string | null;
  seats: number;
  equipment: string[] | null;
  length_m: number | null;
  width_m: number | null;
  height_m: number | null;
  gross_weight_kg: number | null;
  cost_per_km: number | null;
  hu_due_date: string | null;
  sp_due_date: string | null;
  tachograph_due_date: string | null;
  /** Bis wann die Fahrzeugeinheit (Massenspeicher des Fahrtenschreibers) spätestens ausgelesen sein muss (alle 90 Tage) */
  tachograph_read_due_date: string | null;
  service_due_date: string | null;
  notes: string | null;
  deadlines: Deadline[];
  next_deadline: Deadline | null;
  deleted_at: string | null;
  is_demo: boolean;
}

export type VehicleInput = Omit<Vehicle, 'id' | 'display_name' | 'deadlines' | 'next_deadline' | 'is_demo' | 'deleted_at'>;

export interface Option {
  value: string;
  label: string;
}

export interface VehicleChoice {
  id: string;
  display_name: string;
  model: string;
  seats: number;
  /** Fahrzeugtyp (Reisebus, Doppeldecker …); die Planung übernimmt ihn als Typ der Tour */
  type: string | null;
  /** Maße und Gewicht gepflegt (sonst rechnet die Streckenplanung ohne diese Grenzen) */
  has_dimensions: boolean;
}

export interface VehicleOptions {
  types: Option[];
  equipment: Option[];
  deadlines: { field: string; label: string }[];
  warn_days: number;
}

@Injectable({ providedIn: 'root' })
export class VehicleService {
  private http = inject(HttpClient);
  private base = '/api/vehicles';

  list(opts: { q: string; type: string; deadline: string; archived: boolean; page: number; sort: string; dir: 'asc' | 'desc' }) {
    let params = new HttpParams().set('page', opts.page).set('sort', opts.sort).set('dir', opts.dir);
    if (opts.q) params = params.set('q', opts.q);
    if (opts.type) params = params.set('type', opts.type);
    if (opts.deadline) params = params.set('deadline', opts.deadline);
    if (opts.archived) params = params.set('archived', 1);
    return this.http.get<Page<Vehicle>>(this.base, { params });
  }

  options() {
    return this.http.get<VehicleOptions>(`${this.base}/options`);
  }
  /** Alle aktiven Fahrzeuge als schlanke Auswahlliste (ohne Seiten). */
  choices() {
    return this.http.get<VehicleChoice[]>(`${this.base}/choices`);
  }
  get(id: string) {
    return this.http.get<Vehicle>(`${this.base}/${id}`);
  }
  create(data: VehicleInput) {
    return this.http.post<Vehicle>(this.base, data);
  }
  update(id: string, data: VehicleInput) {
    return this.http.put<Vehicle>(`${this.base}/${id}`, data);
  }
  archive(id: string) {
    return this.http.delete(`${this.base}/${id}`);
  }
  restore(id: string) {
    return this.http.post<Vehicle>(`${this.base}/${id}/restore`, {});
  }
  /** Die Fahrzeugeinheit wurde heute ausgelesen: Der Server setzt die nächste Frist (90 Tage). */
  markTachographRead(id: string) {
    return this.http.post<Vehicle>(`${this.base}/${id}/tachograph-read`, {});
  }
}
