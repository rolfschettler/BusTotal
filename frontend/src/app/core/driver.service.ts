import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Page } from './customer.service';
import { Deadline, Option } from './vehicle.service';

export interface Absence {
  id?: string;
  type: string;
  /** Kalendertag, YYYY-MM-DD */
  start_date: string;
  /** letzter Tag der Abwesenheit, einschließlich */
  end_date: string;
  notes: string | null;
}

export interface Driver {
  id: string;
  personnel_number: string;
  first_name: string;
  last_name: string;
  /** "Nachname, Vorname" */
  display_name: string;
  birth_date: string | null;
  email: string | null;
  phone: string | null;
  mobile: string | null;
  street: string | null;
  postal_code: string | null;
  city: string | null;
  employment_type: string;
  hired_on: string | null;
  hourly_rate: number | null;
  license_classes: string[] | null;
  license_number: string | null;
  driver_card_number: string | null;
  license_due_date: string | null;
  pbs_due_date: string | null;
  driver_card_due_date: string | null;
  code95_due_date: string | null;
  medical_due_date: string | null;
  notes: string | null;
  deadlines: Deadline[];
  next_deadline: Deadline | null;
  /** Hat heute Urlaub, ist krank oder in Schulung */
  absent_today: boolean;
  absences?: Absence[];
  deleted_at: string | null;
  is_demo: boolean;
}

export type DriverInput = Omit<Driver, 'id' | 'display_name' | 'deadlines' | 'next_deadline' | 'absent_today' | 'absences' | 'is_demo' | 'deleted_at'>;

export interface DriverOptions {
  employment_types: Option[];
  license_classes: Option[];
  absence_types: Option[];
  deadlines: { field: string; label: string }[];
  warn_days: number;
}

@Injectable({ providedIn: 'root' })
export class DriverService {
  private http = inject(HttpClient);
  private base = '/api/drivers';

  list(opts: { q: string; employment: string; deadline: string; archived: boolean; page: number; sort: string; dir: 'asc' | 'desc' }) {
    let params = new HttpParams().set('page', opts.page).set('sort', opts.sort).set('dir', opts.dir);
    if (opts.q) params = params.set('q', opts.q);
    if (opts.employment) params = params.set('employment_type', opts.employment);
    if (opts.deadline) params = params.set('deadline', opts.deadline);
    if (opts.archived) params = params.set('archived', 1);
    return this.http.get<Page<Driver>>(this.base, { params });
  }

  options() {
    return this.http.get<DriverOptions>(`${this.base}/options`);
  }
  get(id: string) {
    return this.http.get<Driver>(`${this.base}/${id}`);
  }
  create(data: DriverInput) {
    return this.http.post<Driver>(this.base, data);
  }
  update(id: string, data: DriverInput) {
    return this.http.put<Driver>(`${this.base}/${id}`, data);
  }
  archive(id: string) {
    return this.http.delete(`${this.base}/${id}`);
  }
  restore(id: string) {
    return this.http.post<Driver>(`${this.base}/${id}/restore`, {});
  }

  saveAbsence(driverId: string, a: Absence) {
    return a.id
      ? this.http.put<Absence>(`${this.base}/${driverId}/absences/${a.id}`, a)
      : this.http.post<Absence>(`${this.base}/${driverId}/absences`, a);
  }
  deleteAbsence(driverId: string, id: string) {
    return this.http.delete(`${this.base}/${driverId}/absences/${id}`);
  }
}
