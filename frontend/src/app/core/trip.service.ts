import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Page } from './customer.service';
import { TripKindValue } from './document.service';
import { RouteResult } from './route.service';

/** Ein Halt einer gespeicherten Tour; die Koordinaten stehen im gespeicherten Ort. */
export interface TripStop {
  position: number;
  label: string;
  dwell_minutes: number;
  driver_free: boolean;
  place: { lat: number; lon: number };
}

/** Eine Tour des Tour-Archivs. Zeitplan (`route`) und Linienzug (`geometry`) kommen nur im Einzelabruf. */
export interface Trip {
  id: string;
  title: string;
  trip_kind: TripKindValue;
  vehicle_type: string | null;
  vehicle_count: number;
  depot_id: string | null;
  return_to_depot: boolean;
  round_trip: boolean;
  loaded_km: number;
  empty_km: number;
  drive_s: number;
  elapsed_s: number;
  days: number;
  stops: TripStop[];
  updated_at: string;
  /** In wie vielen Reisen die Tour verwendet wird (nur in der Liste); solange > 0 lässt sie sich nicht löschen */
  sections_count?: number;
  route?: Omit<RouteResult, 'geometry'> | null;
  geometry?: [number, number][] | null;
}

export interface TripInput {
  title: string;
  trip_kind: TripKindValue;
  vehicle_type: string | null;
  vehicle_count: number;
  depot_id: string | null;
  return_to_depot: boolean;
  round_trip: boolean;
  loaded_km: number;
  empty_km: number;
  drive_s: number;
  elapsed_s: number;
  days: number;
  route: Omit<RouteResult, 'geometry'>;
  geometry: [number, number][];
  stops: { label: string; lat: number; lon: number; dwell_minutes: number; driver_free: boolean }[];
}

@Injectable({ providedIn: 'root' })
export class TripService {
  private http = inject(HttpClient);
  private base = '/api/trips';

  list(q = '', perPage = 25, page = 1) {
    let params = new HttpParams().set('per_page', perPage).set('page', page);
    if (q.trim()) params = params.set('q', q.trim());
    return this.http.get<Page<Trip>>(this.base, { params });
  }
  get(id: string) {
    return this.http.get<Trip>(`${this.base}/${id}`);
  }
  create(data: TripInput) {
    return this.http.post<Trip>(this.base, data);
  }
  update(id: string, data: TripInput) {
    return this.http.put<Trip>(`${this.base}/${id}`, data);
  }
  remove(id: string) {
    return this.http.delete<void>(`${this.base}/${id}`);
  }
}
