import { HttpErrorResponse } from '@angular/common/http';
import { Observable, forkJoin, map, of, switchMap, tap } from 'rxjs';
import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { TripKindValue } from '../../core/document.service';
import { PricingService } from '../../core/pricing.service';
import { RouteResult, RouteService } from '../../core/route.service';
import { httpErrorText } from '../../core/http-error';
import { GERMAN_STATES } from '../../core/german-states';
import { Depot, DepotService } from '../../core/depot.service';
import { Trip, TripInput, TripService } from '../../core/trip.service';
import { VehicleChoice, VehicleService } from '../../core/vehicle.service';
import { HelpLinkComponent } from '../../help/help-link.component';
import { HighlightComponent } from '../../shared/highlight.component';
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
  restoredResult,
  storableRoute,
  toRequest,
  tourTitle,
} from './route-planning-view';

/**
 * Streckenplanung: Von – Über – Nach eingeben, Strecke und Fahrzeit mit Pausen und Ruhezeiten berechnen.
 * Der Alltag braucht nur Von, Nach und (freiwillig) Über-Orte; Betriebshof, Aufenthalte und lange Tage stehen unter
 * "Weitere Angaben". Eine berechnete Tour lässt sich im Tour-Archiv speichern und später wieder laden (samt Zeitplan und Linienzug,
 * ohne neue Berechnung); ohne "Tour speichern" bleibt nichts zurück. Die Betriebshöfe pflegt der
 * Administrator unter Einstellungen; ihre Koordinaten stehen dort schon fest. Die Seite ist ein Werkzeug, kein Bearbeitungsformular: Sie nutzt die Formular-Optik
 * (.form-area, .section-title), hat aber keine Tastenleiste.
 */
@Component({
  selector: 'app-route-planning',
  imports: [FormsModule, DatePipe, DecimalPipe, RouterLink, HelpLinkComponent, HighlightComponent, MapComponent, PlaceFieldComponent],
  templateUrl: './route-planning.component.html',
})
export class RoutePlanningComponent implements OnInit {
  private api = inject(RouteService);
  private vehicleApi = inject(VehicleService);
  private depotApi = inject(DepotService);
  private pricing = inject(PricingService);
  private tripApi = inject(TripService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);
  private searchTimer: ReturnType<typeof setTimeout> | undefined;
  private nextKey = 1;

  // ---- Eingabe ----
  start = signal<Waypoint>(this.blank());
  end = signal<Waypoint>(this.blank());
  via = signal<Waypoint[]>([]);
  roundTrip = signal(false);
  /** Reihenfolge der Zwischenhalte nach kürzester Fahrzeit ordnen (nur auf Wunsch) */
  optimize = signal(false);

  /** Betriebshöfe der Firma (Einstellungen), jeder mit gespeichertem Ort */
  depots = signal<Depot[]>([]);
  depotsError = signal<string | null>(null);
  /** Auswahl: leer = kein Betriebshof, "other" = anderer Ort (Text), sonst die Kennung eines Betriebshofs */
  depotChoice = signal('');
  /** Text im Feld "Anderer Ort" */
  depotText = signal('');
  /** Der dazu gefundene Ort (null: noch nicht gesucht oder Text geändert) */
  depot = signal<Place | null>(null);
  returnToDepot = signal(true);

  /** Fahrzeugbedarf der Tour: ein Typ und eine Anzahl. Das konkrete Fahrzeug wählt später der Disponent. */
  vehicleType = signal('');
  vehicleCount = signal(1);
  vehicleTypes = signal<{ value: string; label: string }[]>([]);

  vehicles = signal<VehicleChoice[]>([]);
  /** Freiwillig: ein bestimmtes Fahrzeug, dessen Maße die Route beachtet */
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
  /** Bundesland der Feiertage für diese Tour; leer = Betriebssitz */
  holidayState = signal('');
  readonly states = signal(GERMAN_STATES);
  companyState = signal<string | null>(null);
  defaultsError = signal<string | null>(null);
  companyStateLabel = computed(() => this.states().find((s) => s.value === this.companyState())?.label ?? null);
  quoting = signal(false);
  quoteError = signal<string | null>(null);

  /** Fahrtart des Angebots: bestimmt den Steuersatz. Vorbelegt mit Gelegenheitsverkehr (Charter, Ausflug, Gruppenfahrt). */
  tripKind = signal<TripKindValue>('gelegenheit');
  tripKinds = signal<{ value: TripKindValue; label: string; may_be_reduced: boolean }[]>([]);
  /** Linienverkehr und Schülerfahrten sind nur bis 50 km Beförderungsstrecke ermäßigt; bei längeren Fahrten ein Hinweis (kein Verbot). */
  reducedTooLong = computed(() => {
    const r = this.result();
    const kind = this.tripKinds().find((k) => k.value === this.tripKind());
    return !!r && !!kind?.may_be_reduced && r.loaded_distance_m > 50_000;
  });

  /**
   * Legt aus dem Ergebnis ein Angebot als Entwurf an und öffnet es. Zum Server gehen nur die Kennzahlen (Kilometer, Lenkzeit,
   * Tage), nie die Route oder die Orte. Den Kunden wählt man im Beleg.
   */
  createQuote() {
    const r = this.result();
    if (!r || this.stale()) return;
    // Betitelt wird mit Von und Nach der Fahrt mit Fahrgästen; Leerfahrten zum Betriebshof gehören nicht in den Betreff.
    const label = this.tripTitle().trim() || tourTitle(r.legs);

    this.quoting.set(true);
    this.quoteError.set(null);
    // Die Reise verweist auf die gespeicherte Tour: Ist sie noch nicht im Archiv (oder wurde sie nach dem Laden verändert), legt
    // "Angebot erstellen" sie selbst an. So muss niemand an einen Zwischenschritt denken.
    const current = this.currentTrip();
    const trip$ = current && !this.tripDirty() ? of(current.id) : this.storeTrip(true).pipe(map((t) => t.id));
    trip$
      .pipe(
        switchMap((tripId) =>
          this.pricing.quoteFromTour({
            loaded_km: Math.round(r.loaded_distance_m / 100) / 10,
            empty_km: Math.round(r.empty_distance_m / 100) / 10,
            drive_s: r.drive_s,
            days: Math.max(1, r.days),
            surcharge_percent: this.surcharge() || 0,
            label,
            start_at: r.start_at,
            end_at: r.end_at,
            state: this.holidayState() || null,
            vehicle_id: this.vehicleId() || null,
            vehicle_type: this.vehicleType() || null,
            vehicle_count: this.vehicleCount(),
            trip_kind: this.tripKind(),
            trip_id: tripId,
          }),
        ),
      )
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

  // ---- Tour-Archiv ----
  /** Name der Tour im Archiv; vorgeschlagen wird "Von – Nach" der berechneten Strecke */
  tripTitle = signal('');
  /** Die Tour, die aus dem Archiv geladen oder zuletzt gespeichert wurde: "Speichern" ändert sie, "Als neue Tour" legt eine zweite an. */
  currentTrip = signal<{ id: string; title: string } | null>(null);
  savingTrip = signal(false);
  /** Die Eingabe wurde seit dem Laden oder Speichern der Tour verändert: Das Archiv hat einen anderen Stand. */
  tripDirty = signal(false);
  /** „Tour gespeichert“: erscheint direkt bei der Taste, weil man beim Speichern weit unten auf der Seite steht */
  tripMessage = signal<string | null>(null);
  /** „Tour geladen“: erscheint oben auf der Seite, wo man nach dem Laden steht */
  tripLoadedMessage = signal<string | null>(null);
  tripError = signal<string | null>(null);

  archiveOpen = signal(false);
  archiveQuery = signal('');
  /** Der Suchbegriff, zu dem die angezeigte Liste gehört (für die Markierung der Treffer) */
  archiveShownQuery = signal('');
  archive = signal<Trip[]>([]);
  archiveTotal = signal(0);
  archiveLoading = signal(false);
  archiveError = signal<string | null>(null);
  loadingTrip = signal(false);

  /**
   * Kommt man aus dem Tour-Archiv (?trip=…), wird die Tour sofort geladen. Das geschieht erst, wenn die Betriebshöfe da sind,
   * damit der gespeicherte Betriebshof gewählt werden kann.
   */
  private openLinkedTrip() {
    const id = this.route.snapshot.queryParamMap.get('trip');
    if (!id) return;
    this.loadingTrip.set(true);
    this.tripApi.get(id).subscribe({
      next: (trip) => {
        this.applyTrip(trip);
        this.loadingTrip.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.loadingTrip.set(false);
        this.tripError.set(httpErrorText(err, 'Die Tour konnte nicht geladen werden.'));
      },
    });
  }

  /** Öffnet oder schließt die Liste der gespeicherten Touren. */
  toggleArchive() {
    this.archiveOpen.update((o) => !o);
    if (this.archiveOpen()) this.searchArchive();
  }

  /** Suche mit kurzer Verzögerung, damit nicht jeder Tastendruck eine Anfrage auslöst. */
  onArchiveQuery(text: string) {
    this.archiveQuery.set(text);
    clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => this.searchArchive(), 300);
  }

  searchArchive() {
    const q = this.archiveQuery();
    this.archiveLoading.set(true);
    this.archiveError.set(null);
    this.tripApi.list(q).subscribe({
      next: (page) => {
        // Eine ältere, langsamere Antwort darf eine neuere Suche nicht überschreiben.
        if (q !== this.archiveQuery()) return;
        this.archive.set(page.data);
        this.archiveTotal.set(page.total);
        this.archiveShownQuery.set(q);
        this.archiveLoading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.archiveLoading.set(false);
        this.archiveError.set(httpErrorText(err, 'Das Tour-Archiv konnte nicht geladen werden.'));
      },
    });
  }

  /** Eine Tour aus dem Archiv in die Planung übernehmen: Halte, Einstellungen und das gespeicherte Ergebnis samt Linie, ohne neue Berechnung. */
  loadTrip(summary: Trip) {
    this.loadingTrip.set(true);
    this.archiveError.set(null);
    this.tripApi.get(summary.id).subscribe({
      next: (trip) => {
        this.applyTrip(trip);
        this.loadingTrip.set(false);
        this.archiveOpen.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.loadingTrip.set(false);
        this.archiveError.set(httpErrorText(err, 'Die Tour konnte nicht geladen werden.'));
      },
    });
  }

  private applyTrip(trip: Trip) {
    this.reset();
    const place = (s: Trip['stops'][number]): Place => ({ label: s.label, lat: s.place.lat, lon: s.place.lon });
    const waypoint = (s: Trip['stops'][number]): Waypoint => ({ key: this.nextKey++, place: place(s), dwell_minutes: s.dwell_minutes, driver_free: s.driver_free });

    const list = trip.stops.map(waypoint);
    this.start.set(list[0]);
    this.end.set(list[list.length - 1]);
    this.via.set(list.slice(1, -1));
    this.texts.set(Object.fromEntries(list.map((w) => [w.key, w.place!.label])));
    this.roundTrip.set(trip.round_trip);

    // Der Betriebshof wird nur übernommen, wenn es ihn noch gibt.
    const depot = this.depots().find((d) => d.id === trip.depot_id);
    this.chooseDepot(depot ? depot.id : '');
    this.returnToDepot.set(!!depot && trip.return_to_depot);

    this.tripKind.set(trip.trip_kind);
    this.vehicleType.set(trip.vehicle_type ?? '');
    this.vehicleCount.set(trip.vehicle_count);
    this.vehicleId.set('');

    if (trip.route) this.result.set(restoredResult(trip.route, trip.geometry));
    this.stale.set(false);
    this.tripTitle.set(trip.title);
    this.currentTrip.set({ id: trip.id, title: trip.title });
    this.tripDirty.set(false);
    this.tripLoadedMessage.set(`Tour „${trip.title}“ ist geladen. Sie können gleich ein Angebot erstellen oder die Strecke ändern und neu berechnen.`);
  }

  /** Speichert die berechnete Tour im Archiv: als Änderung der geladenen Tour oder (asNew) als neue Tour. */
  saveTrip(asNew = false) {
    if (this.stale() || !this.result() || !this.tripTitle().trim()) return;
    this.savingTrip.set(true);
    this.tripError.set(null);
    this.storeTrip(asNew).subscribe({
      next: (trip) => {
        this.savingTrip.set(false);
        this.tripLoadedMessage.set(null);
        this.tripMessage.set(`Tour „${trip.title}“ ist im Tour-Archiv gespeichert. Nächster Schritt: Angebot erstellen. Die Tour finden Sie später über „Aus Archiv laden“.`);
      },
      error: (err: HttpErrorResponse) => {
        this.savingTrip.set(false);
        this.tripError.set(httpErrorText(err, 'Die Tour konnte nicht gespeichert werden.'));
      },
    });
  }

  /** Schickt die berechnete Tour ans Archiv: ändert die geladene Tour oder (asNew) legt eine neue an. */
  private storeTrip(asNew: boolean): Observable<Trip> {
    const r = this.result()!;
    const depotId = this.depots().some((d) => d.id === this.depotChoice()) ? this.depotChoice() : null;
    const body: TripInput = {
      title: this.tripTitle().trim() || tourTitle(r.legs) || 'Tour',
      trip_kind: this.tripKind(),
      vehicle_type: this.vehicleType() || null,
      vehicle_count: this.vehicleCount(),
      depot_id: depotId,
      return_to_depot: !!depotId && this.returnToDepot(),
      round_trip: this.roundTrip(),
      loaded_km: Math.round(r.loaded_distance_m / 100) / 10,
      empty_km: Math.round(r.empty_distance_m / 100) / 10,
      drive_s: r.drive_s,
      elapsed_s: r.elapsed_s,
      days: Math.max(1, r.days),
      route: storableRoute(r),
      geometry: r.geometry,
      // Die Rückfahrt zum Start steht in der Berechnung als zusätzlicher Halt (Nummer -1), gespeichert wird sie als Kennzeichen.
      stops: this.stops()
        .filter((s) => s.key !== -1)
        .map((s) => ({ label: s.label, lat: s.lat, lon: s.lon, dwell_minutes: s.dwell_minutes, driver_free: s.driver_free })),
    };

    const current = this.currentTrip();
    const call = current && !asNew ? this.tripApi.update(current.id, body) : this.tripApi.create(body);
    return call.pipe(
      tap((trip) => {
        this.currentTrip.set({ id: trip.id, title: trip.title });
        this.tripTitle.set(trip.title);
        this.tripDirty.set(false);
      }),
    );
  }

  /** Ein anderes Fahrzeug gewählt: Der Typ des Fahrzeugs wird zum Fahrzeugtyp der Tour, solange es einen hat. */
  chooseVehicle(id: string) {
    this.vehicleId.set(id);
    const type = this.vehicles().find((v) => v.id === id)?.type;
    if (type) this.vehicleType.set(type);
    this.changed();
  }

  typeLabel(value: string): string {
    return this.vehicleTypes().find((t) => t.value === value)?.label ?? value;
  }

  setVehicleCount(value: unknown) {
    const n = Math.round(Number(value));
    this.vehicleCount.set(Number.isFinite(n) ? Math.min(99, Math.max(1, n)) : 1);
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
  depotPending = computed(() => this.depotChoice() === 'other' && !this.depot() && this.depotText().trim().length >= 2);

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
    // Der Standard-Betriebshof ist vorgewählt. Sein Ort steht schon fest, beim Berechnen wird nichts gesucht.
    this.depotApi.list().subscribe({
      next: (list) => {
        this.depots.set(list.filter((d) => d.place));
        const standard = this.depots().find((d) => d.is_default);
        if (standard && this.depotChoice() === '') this.chooseDepot(standard.id);
        this.openLinkedTrip();
      },
      error: (err) => {
        this.depotsError.set(httpErrorText(err, 'Die Betriebshöfe konnten nicht geladen werden.'));
        this.openLinkedTrip();
      },
    });
    this.vehicleApi.choices().subscribe({
      next: (v) => this.vehicles.set(v),
      error: (err) => this.vehiclesError.set(httpErrorText(err, 'Die Fahrzeugliste konnte nicht geladen werden.')),
    });
    // Bundesländer und das des Betriebssitzes für die Feiertage. Ohne diese Angaben bleibt das Angebot trotzdem möglich.
    this.pricing.get().subscribe({
      next: (p) => {
        this.companyState.set(p.company_state);
        this.tripKinds.set(p.trip_kinds.filter((k) => k.value !== 'sonstig'));
        this.vehicleTypes.set(p.vehicle_types);
      },
      error: (e) => this.defaultsError.set(httpErrorText(e, 'Das Bundesland des Betriebssitzes konnte nicht geladen werden.')),
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

  // ---- Betriebshof ----
  chooseDepot(choice: string) {
    this.depotChoice.set(choice);
    const d = this.depots().find((x) => x.id === choice);
    // Ein Betriebshof der Firma hat seinen Ort gespeichert: Es wird nichts gesucht. Bei "anderer Ort" wird der Text beim Berechnen gesucht.
    this.depot.set(d?.place ? { label: d.name, lat: d.place.lat, lon: d.place.lon } : null);
    this.changed();
  }

  setDepotText(text: string) {
    this.depotText.set(text);
    // Anderer Text: der zuvor gefundene Ort gilt nicht mehr, er wird beim Berechnen neu ermittelt.
    this.depot.set(null);
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
    this.currentTrip.set(null);
    this.tripTitle.set('');
    this.tripMessage.set(null);
    this.tripLoadedMessage.set(null);
    this.tripError.set(null);
  }

  /** Jede Änderung an der Eingabe macht ein vorhandenes Ergebnis zum veralteten. */
  changed() {
    if (this.result()) this.stale.set(true);
    this.tripMessage.set(null);
    this.tripLoadedMessage.set(null);
    this.tripDirty.set(true);
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
        // Name der Tour vorschlagen, solange der Nutzer keinen eigenen eingetragen hat.
        if (!this.tripTitle().trim()) this.tripTitle.set(tourTitle(r.legs));
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
}
