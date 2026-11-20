import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { BusDocument } from './document.service';

/** Preisparameter der Firma (alle Preise netto in Euro, 0 = Bestandteil aus). */
export interface PricingSettings {
  price_per_km: string;
  empty_km_percent: number;
  hourly_rate: string;
  day_rate: string;
  overnight_rate: string;
  minimum_price: string;
  tax_rate: string;
  /** Fahrzeugtypen für die Oberfläche (Wert und Bezeichnung) */
  vehicle_types: { value: string; label: string }[];
  /** Preise je Fahrzeugtyp, Schlüssel = Typ. Leeres Feld (null) = Standardpreis gilt. */
  vehicle_rates: Record<string, Record<VehicleRateField, string | null>>;
}

export type VehicleRateField = 'price_per_km' | 'hourly_rate' | 'day_rate' | 'overnight_rate' | 'minimum_price';

/** Kennzahlen einer geplanten Tour: nur Zahlen, keine Route und keine Orte. */
export interface TourFigures {
  loaded_km: number;
  empty_km: number;
  drive_s: number;
  days: number;
  surcharge_percent?: number;
  label?: string;
  start_at?: string | null;
  /** Eingeplantes Fahrzeug: der Server nimmt nur dessen Typ, um die Preise des Typs zu wählen. */
  vehicle_id?: string | null;
}

@Injectable({ providedIn: 'root' })
export class PricingService {
  private http = inject(HttpClient);

  get() {
    return this.http.get<PricingSettings>('/api/settings/pricing');
  }
  save(data: object) {
    return this.http.put<PricingSettings>('/api/settings/pricing', data);
  }
  /** Angebot (Entwurf) aus den Kennzahlen einer Tour; der Kunde wird im Beleg gewählt. */
  quoteFromTour(tour: TourFigures) {
    return this.http.post<BusDocument>('/api/quotes/from-tour', tour);
  }
}
