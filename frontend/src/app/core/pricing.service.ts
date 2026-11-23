import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { BusDocument, TripKindValue } from './document.service';

/** Preisparameter der Firma (alle Preise netto in Euro, 0 = Bestandteil aus). */
export interface PricingSettings {
  price_per_km: string;
  empty_km_percent: number;
  hourly_rate: string;
  day_rate: string;
  overnight_rate: string;
  minimum_price: string;
  /** Regelsatz: gilt für Gelegenheitsverkehr (Charter, Ausflug, Gruppe) und Sonstiges */
  tax_rate: string;
  /** Satz für genehmigten Linienverkehr (nur bis 50 km) */
  tax_rate_line: string;
  /** Satz für Schüler- und Schulträgerfahrten (nur bis 50 km) */
  tax_rate_school: string;
  /** Fahrtarten für die Auswahl */
  trip_kinds: { value: TripKindValue; label: string; may_be_reduced: boolean }[];
  /** Zuschläge in Prozent auf den Anteil des Tages (0 = keiner) */
  surcharge_saturday_percent: string;
  surcharge_sunday_percent: string;
  surcharge_holiday_percent: string;
  /** Bundesland des Betriebssitzes (Kürzel), Vorbelegung der Feiertage in der Streckenplanung */
  company_state: string | null;
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
  /** Ende der Tour: bestimmt mit dem Start die Kalendertage für Samstag, Sonntag und Feiertag */
  end_at?: string | null;
  /** Bundesland der Feiertage; leer = Bundesland des Betriebssitzes */
  state?: string | null;
  /** Eingeplantes Fahrzeug: der Server nimmt nur dessen Typ, um die Preise des Typs zu wählen. */
  vehicle_id?: string | null;
  /** Fahrzeugtyp der Tour (geht vor dem Typ des Fahrzeugs) und Anzahl gleicher Fahrzeuge */
  vehicle_type?: string | null;
  vehicle_count?: number;
  /** Die gespeicherte Tour (Tour-Archiv); aus ihr bekommt die Reise ihre Hinfahrt. */
  trip_id?: string | null;
  /** Fahrtart des Angebots (bestimmt den Steuersatz); der Server nimmt ohne Angabe Gelegenheitsverkehr. */
  trip_kind?: TripKindValue | null;
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
