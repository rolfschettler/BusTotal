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
  id: number;
  customer_number: string;
  display_name: string;
  postal_code: string | null;
  city: string | null;
  updated_at: string;
}

/** Daten der Startseiten-Kacheln. Jede Kachel hat ihre eigene Schnittstelle. */
@Injectable({ providedIn: 'root' })
export class DashboardService {
  private http = inject(HttpClient);

  setup() {
    return this.http.get<SetupStatus>('/api/dashboard/setup');
  }
  recentCustomers() {
    return this.http.get<RecentCustomer[]>('/api/dashboard/recent-customers');
  }
}
