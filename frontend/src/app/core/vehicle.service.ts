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
}
