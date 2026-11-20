import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, of } from 'rxjs';
import { shortLabel } from '../pages/route-planning/route-planning-view';

/** Treffer der Ortssuche. */
export interface GeoHit {
  label: string;
  lat: number;
  lon: number;
  postal_code: string | null;
  city: string | null;
  country: string | null;
}

export interface PlaceInput {
  lat: number;
  lon: number;
  label?: string | null;
}

export interface StopInput extends PlaceInput {
  /** Aufenthalt am Halt in Minuten */
  dwell_minutes?: number;
  /** Der Fahrer hat in dieser Zeit frei (zählt ab 45 Minuten als Pause, ab 11 Stunden als Ruhezeit). */
  driver_free?: boolean;
  /** Zwischenhalt: Die Optimierung darf seinen Platz in der Reihenfolge bestimmen (Von und Nach bleiben stehen). */
  movable?: boolean;
}

export interface RouteRequest {
  stops: StopInput[];
  depot?: PlaceInput | null;
  return_to_depot?: boolean;
  vehicle_id?: string | null;
  /** ISO 8601 */
  start_at?: string | null;
  extended_days?: number;
  /** Reihenfolge der Zwischenhalte nach kürzester Fahrzeit ordnen (Rundreiseproblem) */
  optimize?: boolean;
}

export interface RoutePlace {
  label: string;
  lat: number;
  lon: number;
}

export interface RouteLeg {
  from: RoutePlace;
  to: RoutePlace;
  distance_m: number;
  duration_s: number;
  /** Leerfahrt: der Abschnitt beginnt oder endet im Betriebshof */
  empty: boolean;
}

export type ScheduleType = 'drive' | 'break' | 'stop' | 'rest';

export interface ScheduleEvent {
  type: ScheduleType;
  /** Sekunden seit Beginn der Tour */
  start_s: number;
  duration_s: number;
  /** Index des Abschnitts (null: Arbeit am Start) */
  leg: number | null;
  /** Tagesabschnitt, ab 1 */
  day: number;
  driver_free?: boolean;
  start_at?: string;
  end_at?: string;
}

export interface RouteWarning {
  code: string;
  message: string;
}

export interface RouteResult {
  /** Nummern der gesendeten Halte in Fahrtreihenfolge (nach der Optimierung) */
  order: number[];
  /** Die Reihenfolge der Zwischenhalte wurde geändert */
  optimized: boolean;
  distance_m: number;
  empty_distance_m: number;
  loaded_distance_m: number;
  /** reine Lenkzeit */
  drive_s: number;
  /** Gesamtdauer inklusive Pausen, Halten und Ruhezeiten */
  elapsed_s: number;
  days: number;
  legs: RouteLeg[];
  schedule: ScheduleEvent[];
  summary: Record<string, number>;
  warnings: RouteWarning[];
  /** Linienzug für die Karte, [lat, lon] */
  geometry: [number, number][];
  start_at: string | null;
  end_at: string | null;
  vehicle: { id: string; display_name: string; cost_per_km: number | null } | null;
  /** Kilometer mal Kostensatz des Fahrzeugs: nur eine Rechengrundlage */
  vehicle_cost: number | null;
}

/** Ein gefundener Ort für einen eingegebenen Text */
export interface Located {
  label: string;
  lat: number;
  lon: number;
}

const LOCATE_CACHE_KEY = 'bustotal.planung.orte';
/** Höchstens so viele Texte im Browser merken (die ältesten fallen heraus) */
const LOCATE_CACHE_MAX = 500;

/** Gleiche Eingaben gleich behandeln: Groß-/Kleinschreibung, mehrfache Leerzeichen und Satzzeichen am Ende zählen nicht. */
export function locateKey(text: string): string {
  return text.trim().replace(/\s+/g, ' ').replace(/[ ,.;:-]+$/, '').toLowerCase();
}

@Injectable({ providedIn: 'root' })
export class RouteService {
  private http = inject(HttpClient);
  /** Text → gefundener Ort. Bleibt im Browser gespeichert: Derselbe Text wird nie zweimal gesucht. */
  private located = new Map<string, Located>(this.loadLocated());

  geocode(q: string, limit = 6) {
    const params = new HttpParams().set('q', q).set('limit', limit);
    return this.http.get<{ data: GeoHit[] }>('/api/geocode', { params });
  }

  /**
   * Der beste Ort zu einem Text (null: nichts gefunden). Schon gesuchte Texte kommen aus dem Speicher des Browsers,
   * ohne Anfrage. "Nichts gefunden" wird nicht gemerkt, damit eine Korrektur der Daten beim Dienst ankommt.
   */
  locate(text: string): Observable<Located | null> {
    const key = locateKey(text);
    const hit = this.located.get(key);
    if (hit) return of(hit);
    return this.geocode(text.trim(), 1).pipe(
      map(({ data }) => {
        const h = data[0];
        if (!h) return null;
        const place: Located = { label: shortLabel(h.label), lat: h.lat, lon: h.lon };
        this.remember(key, place);
        return place;
      }),
    );
  }

  private remember(key: string, place: Located) {
    this.located.delete(key);
    this.located.set(key, place);
    while (this.located.size > LOCATE_CACHE_MAX) this.located.delete(this.located.keys().next().value!);
    try {
      localStorage.setItem(LOCATE_CACHE_KEY, JSON.stringify([...this.located]));
    } catch {
      // Speicher voll oder gesperrt: Dann gilt der Zwischenspeicher nur für diese Sitzung.
    }
  }

  private loadLocated(): [string, Located][] {
    try {
      const raw = JSON.parse(localStorage.getItem(LOCATE_CACHE_KEY) ?? '[]');
      if (!Array.isArray(raw)) return [];
      return raw.filter(
        (e): e is [string, Located] =>
          Array.isArray(e) && typeof e[0] === 'string' && !!e[1] && typeof e[1].label === 'string' && Number.isFinite(e[1].lat) && Number.isFinite(e[1].lon),
      );
    } catch {
      return [];
    }
  }

  calculate(request: RouteRequest) {
    return this.http.post<RouteResult>('/api/routes/calculate', request);
  }
}
