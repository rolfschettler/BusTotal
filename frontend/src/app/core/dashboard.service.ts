import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

export interface SetupItem {
  key: string;
  label: string;
  done: boolean;
  /** Seite, auf der man den Punkt erledigt */
  route: string;
  /** Hilfethema und Sprungmarke, "thema#anker" */
  help: string;
}

export interface SetupStatus {
  items: SetupItem[];
  done: number;
  total: number;
}

export interface RecentCustomer {
  id: string;
  customer_number: string;
  display_name: string;
  postal_code: string | null;
  city: string | null;
  updated_at: string;
}

export interface VehicleDeadlineItem {
  kind: string;
  label: string;
  date: string;
  days: number;
  status: 'overdue' | 'soon' | 'ok';
  vehicle_id: string;
  vehicle: string;
}

export interface VehicleDeadlines {
  /** Anzahl aller fälligen Fristen (die Liste zeigt höchstens die dringendsten acht) */
  total: number;
  items: VehicleDeadlineItem[];
}

export interface DriverDeadlineItem {
  kind: string;
  label: string;
  date: string;
  days: number;
  status: 'overdue' | 'soon' | 'ok';
  driver_id: string;
  /** "Nachname, Vorname" */
  driver: string;
}

export interface DriverDeadlines {
  total: number;
  items: DriverDeadlineItem[];
}

/** Daten der Startseiten-Kacheln. Jede Kachel hat ihre eigene Schnittstelle. */
@Injectable({ providedIn: 'root' })
export class DashboardService {
  private http = inject(HttpClient);

  setup() {
    return this.http.get<SetupStatus>('/api/dashboard/setup');
  }
  vehicleDeadlines() {
    return this.http.get<VehicleDeadlines>('/api/dashboard/vehicle-deadlines');
  }
  driverDeadlines() {
    return this.http.get<DriverDeadlines>('/api/dashboard/driver-deadlines');
  }
  recentCustomers() {
    return this.http.get<RecentCustomer[]>('/api/dashboard/recent-customers');
  }
}
