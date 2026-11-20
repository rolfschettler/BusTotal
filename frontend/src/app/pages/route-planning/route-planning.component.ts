import { HttpErrorResponse } from '@angular/common/http';
import { forkJoin } from 'rxjs';
import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { PricingService } from '../../core/pricing.service';
import { RouteResult, RouteService } from '../../core/route.service';
import { httpErrorText } from '../../core/http-error';
import { VehicleChoice, VehicleService } from '../../core/vehicle.service';
import { HelpLinkComponent } from '../../help/help-link.component';
import { MapComponent, MapMarker } from '../../shared/map.component';
import { PlaceFieldComponent } from './place-field.component';
import {
  Place,
  Waypoint,
  buildStops,
  canCalculate,
  cleanMinutes,
  eventTime,
  eventTitle,
  formatDuration,
  formatKm,
  moveItem,
  toRequest,
} from './route-planning-view';

const DEPOT_KEY = 'bustotal.planung.depot';

/**
 * Streckenplanung: Von – Über – Nach eingeben, Strecke und Fahrzeit mit Pausen und Ruhezeiten berechnen.
 * Der Alltag braucht nur Von, Nach und (freiwillig) Über-Orte; Betriebshof, Aufenthalte und lange Tage stehen unter
 * "Weitere Angaben". Es wird nichts gespeichert (Speichern als Kalkulationsbasis kommt später). Den Betriebshof merkt
 * sich nur dieser Browser. Die Seite ist ein Werkzeug, kein Bearbeitungsformular: Sie nutzt die Formular-Optik
 * (.form-area, .section-title), hat aber keine Tastenleiste.
 */
@Component({
  selector: 'app-route-planning',
  imports: [FormsModule, DatePipe, DecimalPipe, HelpLinkComponent, MapComponent, PlaceFieldComponent],
  templateUrl: './route-planning.component.html',
})
export class RoutePlanningComponent implements OnInit {
  private api = inject(RouteService);
  private vehicleApi = inject(VehicleService);
  private pricing = inject(PricingService);
  private router = inject(Router);
  private nextKey = 1;

  // ---- Eingabe ----
  start = signal<Waypoint>(this.blank());
  end = signal<Waypoint>(this.blank());
  via = signal<Waypoint[]>([]);
  roundTrip = signal(false);
  /** Reihenfolge der Zwischenhalte nach kürzester Fahrzeit ordnen (nur auf Wunsch) */
  optimize = signal(false);

  /** Text im Feld Betriebshof */
  depotText = signal('');
  /** Der dazu gefundene Ort (null: noch nicht gesucht oder Text geändert) */
  depot = signal<Place | null>(null);
  returnToDepot = signal(true);

  vehicles = signal<VehicleChoice[]>([]);
  vehicleId = signal('');
  vehiclesError = signal<string | null>(null);
  startAt = signal('');
  extendedDays = signal(0);

  // ---- Ergebnis ----
  result = signal<RouteResult | null>(null);
  calculating = signal(false);
  error = signal<string | null>(null);
  /** Die Eingabe wurde nach der letzten Berechnung geändert: Das Ergebnis passt nicht mehr dazu. */
  stale = signal(false);

  readonly format = { duration: formatDuration, km: formatKm, title: eventTitle, time: eventTime };

  // ---- Angebot aus der Tour (Preisfindung) ----
  /** Zuschlag in Prozent auf die Summe (Wochenende, Feiertag), von Hand gewählt */
  surcharge = signal<number | null>(null);
  quoting = signal(false);
  quoteError = signal<string | null>(null);

  /**
   * Legt aus dem Ergebnis ein Angebot als Entwurf an und öffnet es. Zum Server gehen nur die Kennzahlen (Kilometer, Lenkzeit,
   * Tage), nie die Route oder die Orte. Den Kunden wählt man im Beleg.
   */
  createQuote() {
    const r = this.result();
    if (!r || this.stale()) return;
    // Betitelt wird mit Von und Nach der Fahrt mit Fahrgästen; Leerfahrten zum Betriebshof gehören nicht in den Betreff.
    const loaded = r.legs.filter((l) => !l.empty);
    // Die Namen des Dienstes sind lang ("München Hauptbahnhof, München"): im Betreff genügt der Teil vor dem ersten Komma.
    const short = (s: string) => s.split(',')[0].trim();
    const label = loaded.length ? `${short(loaded[0].from.label)} – ${short(loaded[loaded.length - 1].to.label)}` : '';

    this.quoting.set(true);
    this.quoteError.set(null);
    this.pricing
      .quoteFromTour({
        loaded_km: Math.round(r.loaded_distance_m / 100) / 10,
        empty_km: Math.round(r.empty_distance_m / 100) / 10,
        drive_s: r.drive_s,
        days: Math.max(1, r.days),
        surcharge_percent: this.surcharge() || 0,
        label,
        start_at: r.start_at,
        vehicle_id: this.vehicleId() || null,
      })
      .subscribe({
        next: (doc) => {
          this.quoting.set(false);
          this.router.navigate(['/belege', doc.id]);
        },
        error: (e: HttpErrorResponse) => {
          this.quoting.set(false);
          this.quoteError.set(httpErrorText(e, 'Das Angebot konnte nicht erstellt werden.'));
        },
      });
  }

  /** Die Halte in Fahrtreihenfolge (leere Felder fehlen, mit Rückfahrt steht der Start am Ende noch einmal). */
  stops = computed(() => buildStops(this.withResolved(this.start()), this.via().map((w) => this.withResolved(w)), this.withResolved(this.end()), this.roundTrip()));
  /** Orte mit Aufenthalt, wie sie unter "Weitere Angaben" einzeln eingestellt werden (ohne die Rückfahrt). */
  placed = computed(() => [this.start(), ...this.via(), this.end()].map((w) => this.withResolved(w)).filter((w) => w.place !== null));

  /** Text je Feld (Schlüssel des Ortes), so wie er eingegeben wurde. */
  texts = signal<Record<number, string>>({});
  /** Beim Berechnen gefundener Ort zu einem Text (Schlüssel des Feldes). Das Feld selbst bleibt unverändert. */
  resolved = signal<Record<number, Place>>({});
  /** Felder mit Text, zu dem noch kein Ort gesucht wurde: Sie werden beim Berechnen aufgelöst. */
  pending = computed(() => [this.start(), this.via(), this.end()].flat().filter((w) => !w.place && !this.resolved()[w.key] && this.textOf(w).trim().length >= 2));
  /** Betriebshof mit Text, aber noch ohne gefundenen Ort */
  depotPending = computed(() => !this.depot() && this.depotText().trim().length >= 2);

  canCalc = computed(() => canCalculate(this.stops().length + this.pending().length, !!this.depot() || this.depotPending(), this.returnToDepot()) && !this.calculating());
  selectedVehicle = computed(() => this.vehicles().find((v) => v.id === this.vehicleId()) ?? null);

  markers = computed<MapMarker[]>(() => {
    const out: MapMarker[] = [];
    const depot = this.depot();
    if (depot) out.push({ lat: depot.lat, lon: depot.lon, pin: 'H', label: `Betriebshof: ${depot.label}`, kind: 'depot' });
    const s = this.withResolved(this.start()).place;
    if (s) out.push({ lat: s.lat, lon: s.lon, pin: 'A', label: `Von: ${s.label}`, kind: 'stop' });
    this.via().map((w) => this.withResolved(w)).forEach((w, i) => w.place && out.push({ lat: w.place.lat, lon: w.place.lon, pin: String(i + 1), label: `Über ${i + 1}: ${w.place.label}`, kind: 'stop' }));
    const e = this.withResolved(this.end()).place;
    if (e) out.push({ lat: e.lat, lon: e.lon, pin: 'Z', label: `Nach: ${e.label}`, kind: 'stop' });
    return out;
  });

  line = computed(() => this.result()?.geometry ?? []);

  ngOnInit() {
    const saved = this.loadDepot();
    this.depotText.set(saved.text);
    this.depot.set(saved.place);
    this.vehicleApi.choices().subscribe({
      next: (v) => this.vehicles.set(v),
      error: (err) => this.vehiclesError.set(httpErrorText(err, 'Die Fahrzeugliste konnte nicht geladen werden.')),
    });
  }

  setText(key: number, text: string) {
    this.texts.update((t) => ({ ...t, [key]: text }));
    // Anderer Text: der zuvor gefundene Ort gilt nicht mehr.
    this.resolved.update(({ [key]: _gone, ...rest }) => rest);
    this.changed();
  }

  textOf(w: Waypoint): string {
    return this.texts()[w.key] ?? '';
  }

  // ---- Über ----
  addVia() {
    this.via.update((list) => [...list, this.blank()]);
  }
  removeVia(index: number) {
    this.via.update((list) => list.filter((_, i) => i !== index));
    this.changed();
  }
  moveVia(index: number, delta: number) {
    this.via.update((list) => moveItem(list, index, delta));
    this.changed();
  }
  /** Von und Nach vertauschen (Rückfahrt planen). */
  swap() {
    // Die Felder samt Text, gefundenem Ort und Aufenthalt tauschen die Plätze (alles hängt am Schlüssel des Feldes).
    const s = this.start();
    this.start.set(this.end());
    this.end.set(s);
    this.changed();
  }

  // ---- Betriebshof (nur dieser Browser) ----
  setDepotText(text: string) {
    this.depotText.set(text);
    // Anderer Text: der zuvor gefundene Ort gilt nicht mehr, er wird beim Berechnen neu ermittelt.
    this.depot.set(null);
    this.saveDepot(text, null);
    this.changed();
  }

  // ---- Aufenthalte ----
  setDwell(w: Waypoint, value: unknown) {
    // w kann eine Kopie mit gefundenem Ort sein: Der Wert gehört in das echte Feld.
    this.original(w).dwell_minutes = cleanMinutes(value);
    this.changed();
  }
  setFree(w: Waypoint, free: boolean) {
    this.original(w).driver_free = free;
    this.changed();
  }

  reset() {
    this.start.set(this.blank());
    this.end.set(this.blank());
    this.via.set([]);
    this.texts.set({});
    this.resolved.set({});
    this.roundTrip.set(false);
    this.result.set(null);
    this.error.set(null);
    this.stale.set(false);
  }

  /** Jede Änderung an der Eingabe macht ein vorhandenes Ergebnis zum veralteten. */
  changed() {
    if (this.result()) this.stale.set(true);
  }

  // ---- Berechnung ----
  calculate() {
    if (!this.canCalc()) return;
    this.calculating.set(true);
    this.error.set(null);
    // Nur Felder, deren Text seit der letzten Suche neu ist; schon einmal gesuchte Texte liefert locate() ohne Anfrage.
    const todo = this.pending();
    const texts = [...todo.map((w) => this.textOf(w).trim()), ...(this.depotPending() ? [this.depotText().trim()] : [])];
    if (!texts.length) {
      this.run();
      return;
    }
    forkJoin(texts.map((t) => this.api.locate(t))).subscribe({
      next: (places) => {
        const missing = texts.find((_, i) => !places[i]);
        if (missing) {
          this.calculating.set(false);
          this.error.set(`Kein Ort zu „${missing}“ gefunden. Bitte anders schreiben (zum Beispiel mit Postleitzahl oder „Ulm Hbf“).`);
          return;
        }
        const found: Record<number, Place> = {};
        todo.forEach((w, i) => (found[w.key] = places[i]!));
        this.resolved.update((r) => ({ ...r, ...found }));
        if (this.depotPending()) {
          const d = places[texts.length - 1]!;
          this.depot.set(d);
          this.saveDepot(this.depotText(), d);
        }
        this.run();
      },
      error: (err: HttpErrorResponse) => {
        this.calculating.set(false);
        this.error.set(httpErrorText(err, 'Die Orte konnten nicht gesucht werden.'));
      },
    });
  }

  /** Die Optimierung hat die Zwischenhalte umgestellt: Die Über-Felder zeigen jetzt die neue Reihenfolge. */
  private applyOrder(keys: number[], order: number[]) {
    const rank = new Map(order.map((idx, pos) => [keys[idx], pos]));
    this.via.update((list) => [...list].sort((a, b) => (rank.get(a.key) ?? 1e9) - (rank.get(b.key) ?? 1e9)));
  }

  private run() {
    const request = toRequest({
      stops: this.stops(),
      depot: this.depot(),
      returnToDepot: this.returnToDepot(),
      vehicleId: this.vehicleId(),
      startAt: this.startAt(),
      extendedDays: this.extendedDays(),
      optimize: this.optimize(),
    });
    const sent = this.stops();
    this.api.calculate(request).subscribe({
      next: (r) => {
        if (r.optimized) this.applyOrder(sent.map((s) => s.key), r.order);
        this.result.set(r);
        this.stale.set(false);
        this.calculating.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.calculating.set(false);
        this.error.set(httpErrorText(err, 'Die Berechnung ist fehlgeschlagen.'));
      },
    });
  }

  /** Ereignisse, die einen eigenen Tagesabschnitt beginnen, bekommen eine Überschrift im Zeitplan. */
  isNewDay(index: number): boolean {
    const events = this.result()?.schedule ?? [];
    return index === 0 || events[index].day !== events[index - 1].day;
  }

  /** Bezeichnung einer Zeile unter "Aufenthalte": Von, Über 1 …, Nach. */
  roleOf(w: Waypoint): string {
    if (w.key === this.start().key) return 'Von';
    if (w.key === this.end().key) return 'Nach';
    return `Über ${this.via().findIndex((v) => v.key === w.key) + 1}`;
  }

  /** Das echte Feld zu einer Kopie (withResolved liefert Kopien mit dem gefundenen Ort). */
  private original(w: Waypoint): Waypoint {
    return [this.start(), ...this.via(), this.end()].find((o) => o.key === w.key) ?? w;
  }

  private withResolved(w: Waypoint): Waypoint {
    return w.place ? w : { ...w, place: this.resolved()[w.key] ?? null };
  }

  /** Hinweis unter dem Feld: welcher Ort für den getippten Text verwendet wurde. */
  usedFor(w: Waypoint): string {
    return w.place ? '' : (this.resolved()[w.key]?.label ?? '');
  }

  private blank(): Waypoint {
    return { key: this.nextKey++, place: null, dwell_minutes: 0, driver_free: false };
  }

  /** Gemerkter Betriebshof: der eingegebene Text und (falls schon gesucht) der gefundene Ort. */
  private loadDepot(): { text: string; place: Place | null } {
    try {
      const raw = localStorage.getItem(DEPOT_KEY);
      if (!raw) return { text: '', place: null };
      const d = JSON.parse(raw);
      const ok = d && typeof d.label === 'string' && Number.isFinite(d.lat) && Number.isFinite(d.lon) && Math.abs(d.lat) <= 90 && Math.abs(d.lon) <= 180;
      const place = ok ? { label: d.label, lat: d.lat, lon: d.lon } : null;
      // Ältere Einträge haben keinen Text: dann steht der Name des Ortes im Feld.
      const text = typeof d?.text === 'string' ? d.text : (place?.label ?? '');
      return { text, place };
    } catch {
      return { text: '', place: null };
    }
  }

  private saveDepot(text: string, place: Place | null) {
    try {
      if (text.trim()) localStorage.setItem(DEPOT_KEY, JSON.stringify({ text, ...(place ?? {}) }));
      else localStorage.removeItem(DEPOT_KEY);
    } catch {
      // Speicher gesperrt: Der Betriebshof gilt dann nur für diese Sitzung.
    }
  }
}
