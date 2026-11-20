import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

/** Der gespeicherte Ort zu einer Adresse (zentrale Ortstabelle des Servers): Die Koordinaten stehen schon fest, nichts wird neu gesucht. */
export interface StoredPlace {
  label: string;
  lat: number;
  lon: number;
  postal_code: string | null;
  city: string | null;
}

export interface Depot {
  id: string;
  name: string;
  address: string;
  is_default: boolean;
  place: StoredPlace | null;
}

export interface DepotInput {
  name: string;
  address: string;
  is_default?: boolean;
}

@Injectable({ providedIn: 'root' })
export class DepotService {
  private http = inject(HttpClient);
  private base = '/api/depots';

  /** Alle Betriebshöfe, der Standard steht oben. */
  list() {
    return this.http.get<Depot[]>(this.base);
  }
  create(data: DepotInput) {
    return this.http.post<Depot>(this.base, data);
  }
  update(id: string, data: DepotInput) {
    return this.http.put<Depot>(`${this.base}/${id}`, data);
  }
  remove(id: string) {
    return this.http.delete<void>(`${this.base}/${id}`);
  }
}
