import { RouteLeg, RouteRequest, RouteResult, ScheduleEvent } from '../../core/route.service';

/**
 * Reine Hilfsfunktionen der Streckenplanung (kein Angular): Zeiten und Strecken lesbar machen, Halte umsortieren,
 * die Anfrage an den Server bauen. Getrennt von der Seite, damit sie sich ohne Browser testen lassen (route-planning-view.spec.ts).
 */

/** Ein Halt in der Eingabeliste. */
export interface PlannedStop {
  /** nur zur Wiedererkennung in der Liste (track) */
  key: number;
  label: string;
  lat: number;
  lon: number;
  dwell_minutes: number;
  driver_free: boolean;
  /** Zwischenhalt (Über): darf bei der Optimierung umgestellt werden */
  movable?: boolean;
}

export interface Place {
  label: string;
  lat: number;
  lon: number;
}

/** Ein Ort der Eingabe (Von, Über oder Nach) mit Aufenthalt. `place` ist null, solange noch nichts gewählt ist. */
export interface Waypoint {
  key: number;
  place: Place | null;
  dwell_minutes: number;
  driver_free: boolean;
}

/**
 * Die Halte für die Berechnung in Fahrtreihenfolge: Von, alle gewählten Über-Orte, Nach. Leere Felder fehlen.
 * Mit `roundTrip` kommt am Ende noch einmal der Start dazu (Rückfahrt), aber nur, wenn es mindestens einen weiteren Ort gibt.
 */
export function buildStops(start: Waypoint, via: Waypoint[], end: Waypoint, roundTrip: boolean): PlannedStop[] {
  const stops: PlannedStop[] = [start, ...via, end]
    .filter((w): w is Waypoint & { place: Place } => w.place !== null)
    .map((w) => ({ key: w.key, label: w.place.label, lat: w.place.lat, lon: w.place.lon, dwell_minutes: cleanMinutes(w.dwell_minutes), driver_free: w.driver_free, movable: via.includes(w) }));
  if (roundTrip && start.place && stops.length >= 2) {
    stops.push({ key: -1, label: start.place.label, lat: start.place.lat, lon: start.place.lon, dwell_minutes: 0, driver_free: false });
  }
  return stops;
}

/**
 * Kurze Bezeichnung aus einem Treffer der Ortssuche: die ersten zwei verschiedenen Teile, zum Beispiel
 * "Ulm Hauptbahnhof, Ulm, Baden-Württemberg, Deutschland" → "Ulm Hauptbahnhof, Ulm". Der Zeitplan bleibt so lesbar.
 */
export function shortLabel(label: string): string {
  const parts = label.split(',').map((p) => p.trim()).filter((p) => p !== '');
  const unique = parts.filter((p, i) => parts.indexOf(p) === i);
  return unique.slice(0, 2).join(', ') || label.trim();
}

/** "2 Std. 15 Min.", "45 Min.", "26 Std." – auf ganze Minuten gerundet. */
export function formatDuration(seconds: number): string {
  const total = Math.max(0, Math.round(seconds / 60));
  const h = Math.floor(total / 60);
  const m = total % 60;
  if (h === 0) return `${m} Min.`;
  return m === 0 ? `${h} Std.` : `${h} Std. ${m} Min.`;
}

/** "1.234,5 km" (deutsche Schreibweise, eine Nachkommastelle). */
export function formatKm(meters: number): string {
  return `${(meters / 1000).toLocaleString('de-DE', { minimumFractionDigits: 0, maximumFractionDigits: 1 })} km`;
}

/** Versatz seit Tourbeginn als "+13:45" (Stunden:Minuten), wenn keine Abfahrtszeit gewählt ist. */
export function formatOffset(seconds: number): string {
  const total = Math.floor(Math.max(0, seconds) / 60);
  return `+${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

/** Beschreibung eines Ereignisses im Zeitplan, z. B. "Fahrt: München → Ulm". */
export function eventTitle(e: ScheduleEvent, legs: RouteLeg[]): string {
  const leg = e.leg !== null ? legs[e.leg] : undefined;
  switch (e.type) {
    case 'drive':
      return leg ? `Fahrt: ${leg.from.label} → ${leg.to.label}${leg.empty ? ' (Leerfahrt)' : ''}` : 'Fahrt';
    case 'break':
      return 'Pause';
    case 'rest':
      return 'Tagesruhezeit';
    case 'stop':
      if (!leg) return `Arbeit am Start${legs[0] ? ` (${legs[0].from.label})` : ''}`;
      return `Halt: ${leg.to.label}${e.driver_free ? ' (Fahrer frei)' : ''}`;
  }
}

/** Neue Liste mit dem Eintrag an Position `index` um `delta` Plätze verschoben (nach oben: -1). Außerhalb der Liste: unverändert. */
export function moveItem<T>(list: readonly T[], index: number, delta: number): T[] {
  const target = index + delta;
  if (index < 0 || index >= list.length || target < 0 || target >= list.length) return [...list];
  const copy = [...list];
  const [item] = copy.splice(index, 1);
  copy.splice(target, 0, item);
  return copy;
}

/** Reicht die Eingabe für eine Berechnung? Wie auf dem Server: mindestens zwei Orte, mit Betriebshof und Rückfahrt genügt ein Halt. */
export function canCalculate(stopCount: number, hasDepot: boolean, returnToDepot: boolean): boolean {
  return stopCount + (hasDepot ? (returnToDepot ? 2 : 1) : 0) >= 2;
}

/** Aufenthalt in Minuten, ganzzahlig und 0 bis 1440 (der Server lehnt alles andere ab). */
export function cleanMinutes(value: unknown): number {
  const n = Math.round(Number(value));
  return Number.isFinite(n) ? Math.min(1440, Math.max(0, n)) : 0;
}

export interface PlanInput {
  stops: PlannedStop[];
  depot: Place | null;
  returnToDepot: boolean;
  vehicleId: string;
  /** Wert eines datetime-local-Feldes ("2027-03-01T06:00", Ortszeit des Browsers) oder leer */
  startAt: string;
  extendedDays: number;
  /** Reihenfolge der Zwischenhalte optimieren */
  optimize?: boolean;
}

/** Die Anfrage an den Server. Die Abfahrtszeit geht mit Zeitzone (aus der Ortszeit des Browsers) hinaus. */
export function toRequest(input: PlanInput): RouteRequest {
  const request: RouteRequest = {
    stops: input.stops.map((s) => ({
      lat: s.lat,
      lon: s.lon,
      label: s.label,
      dwell_minutes: cleanMinutes(s.dwell_minutes),
      driver_free: s.driver_free,
      movable: s.movable ?? false,
    })),
    extended_days: input.extendedDays,
  };
  if (input.depot) {
    request.depot = { lat: input.depot.lat, lon: input.depot.lon, label: input.depot.label };
    request.return_to_depot = input.returnToDepot;
  }
  if (input.optimize) request.optimize = true;
  if (input.vehicleId) request.vehicle_id = input.vehicleId;
  if (input.startAt) {
    const d = new Date(input.startAt);
    if (!Number.isNaN(d.getTime())) request.start_at = d.toISOString();
  }
  return request;
}

/** Zeit eines Ereignisses: Datum und Uhrzeit (Ortszeit), wenn eine Abfahrt gewählt wurde, sonst der Versatz. */
export function eventTime(e: ScheduleEvent, result: Pick<RouteResult, 'start_at'>): string {
  if (e.start_at && result.start_at) {
    return new Date(e.start_at).toLocaleString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }
  return formatOffset(e.start_s);
}
