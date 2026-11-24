import { HttpErrorResponse } from '@angular/common/http';
import { Observable, forkJoin, map, of, switchMap, tap } from 'rxjs';
import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, HostListener, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NgbDropdown, NgbDropdownItem, NgbDropdownMenu, NgbDropdownToggle } from '@ng-bootstrap/ng-bootstrap';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ConfirmService } from '../../core/confirm.service';
import { TripKindValue } from '../../core/document.service';
import { PricingService } from '../../core/pricing.service';
import { GeoHit, RouteResult, RouteService, locateKey } from '../../core/route.service';
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
  shortLabel,
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
  imports: [FormsModule, NgbDropdown, NgbDropdownToggle, NgbDropdownMenu, NgbDropdownItem, DatePipe, DecimalPipe, RouterLink, HelpLinkComponent, HighlightComponent, MapComponent, PlaceFieldComponent],
  templateUrl: './route-planning.component.html',
  styles: `
    /* Routenleiste: Marker A, 1, 2 … Z auf einer gestrichelten Linie neben den Ortsfeldern (wie die Pins auf der Karte) */
    .route-rail { --rail-col: 2.5rem; --rail-line-top: 3.55rem; }
    .rail-row { position: relative; display: flex; gap: 0.75rem; padding-bottom: 0.75rem; }
    .rail-row::before {
      content: ''; position: absolute; top: 0; bottom: 0; left: calc(var(--rail-col) / 2 - 1px); width: 2px;
      background: repeating-linear-gradient(to bottom, var(--brand, #1f4e8c) 0 6px, transparent 6px 11px); opacity: 0.55;
    }
    .rail-start::before { top: var(--rail-line-top); }
    .rail-end { padding-bottom: 0; }
    .rail-end::before { bottom: auto; height: var(--rail-line-top); }
    .rail-col { position: relative; z-index: 1; flex: 0 0 var(--rail-col); display: flex; justify-content: center; }
    .rail-field { flex: 1 1 auto; min-width: 0; }
    .rail-marker {
      display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 700; line-height: 1;
      background: var(--brand, #1f4e8c); color: var(--brand-on, #fff); border: 2px solid var(--brand, #1f4e8c);
    }
    /* Von und Nach: groß, mit Ring */
    .rail-marker-end {
      width: 2.5rem; height: 2.5rem; font-size: 1.05rem; margin-top: 2.3rem;
      box-shadow: 0 0 0 0.3rem color-mix(in srgb, var(--brand, #1f4e8c) 18%, transparent);
    }
    /* Zwischenhalte: kleiner, hell mit farbigem Rand */
    .rail-marker-via { width: 1.75rem; height: 1.75rem; font-size: 0.85rem; margin-top: 2.3rem; background: #fff; color: var(--brand, #1f4e8c); }
    @media (max-width: 575.98px) { .route-rail { --rail-col: 2rem; } .rail-marker-end { width: 2rem; height: 2rem; font-size: 0.9rem; } }
  `,
})
export class RoutePlanningComponent implements OnInit {
  private api = inject(RouteService);
  private vehicleApi = inject(VehicleService);
  private depotApi = inject(DepotService);
  private pricing = inject(PricingService);
  private tripApi = inject(TripService);
  private confirm = inject(ConfirmService);
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
  returnToDepot = signal(false);
  /**
   * Fährt der Bus am Ende zum Betriebshof zurück? Ja, wenn „Rückfahrt zum Betriebshof“ gewählt ist, und auch immer dann, wenn die Tour
   * mit „Rückfahrt zum Start (Von)“ zum Start zurückkehrt und ein Betriebshof angegeben ist: Dann geht es von dort weiter zum
   * Betriebshof (Nach → Von → Betriebshof). Gespeichert wird nur die eigene Wahl (returnToDepot), das hier ergibt sich daraus.
   */
  returnsToDepot = computed(() => (this.returnToDepot() || this.roundTrip()) && (!!this.depot() || this.depotPending()));

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
   * Tage), nie die Route oder die Orte. Den Kunden wählt man im Beleg. Wurde die Eingabe seit der Berechnung geändert, fragt ein
   * Dialog, ob die Strecke neu berechnet und dann das Angebot erstellt werden soll (der Preis käme sonst von der alten Strecke).
   */
  async createQuote() {
    if (this.quoting() || this.calculating()) return;
    if (this.stale()) {
      if (!this.canCalc()) return;
      const ok = await this.confirm.ask({
        title: 'Strecke neu berechnen?',
        message:
          'Sie haben die Eingabe geändert, das Ergebnis gehört noch zum alten Stand. Damit der Preis zur richtigen Strecke passt, muss sie zuerst neu berechnet werden. Jetzt neu berechnen und danach das Angebot erstellen?',
        confirmLabel: 'Neu berechnen und Angebot erstellen',
      });
      if (!ok) return;
      this.quoteError.set(null);
      this.calculate(() => void this.createQuote());
      return;
    }
    const r = this.result();
    if (!r) return;
    // Betitelt wird mit Von und Nach der Fahrt mit Fahrgästen; Leerfahrten zum Betriebshof gehören nicht in den Betreff.
    const label = this.tripTitle().trim() || tourTitle(r.legs, this.stops());

    this.quoting.set(true);
    this.quoteError.set(null);
    // Die Reise verweist auf die gespeicherte Tour: Ist sie noch nicht im Archiv (oder wurde sie nach dem Laden verändert), legt
    // "Angebot erstellen" sie selbst an. So muss niemand an einen Zwischenschritt denken.
    // Eine Tour, die das Archiv nicht aufnimmt (ein Halt ohne Betriebshof der Firma), geht ohne Archiv-Verweis ins Angebot.
    const current = this.currentTrip();
    const trip$: Observable<string | null> = current && !this.tripDirty() ? of(current.id) : this.canStore() ? this.storeTrip(true).pipe(map((t) => t.id)) : of(null);
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
          this.leaving = true;
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
  /**
   * Der Name passt nicht mehr zur berechneten Strecke (zum Beispiel nach einem geänderten Halt): Vorschlag zum Übernehmen, sonst null.
   * Nur ein Vorschlag mit Knopf: Ein selbst gewählter Name wird nie überschrieben.
   */
  titleSuggestion = computed(() => {
    const r = this.result();
    if (!r || this.stale()) return null;
    const suggestion = tourTitle(r.legs, this.stops());
    const title = this.tripTitle().trim();
    // Nur wenn sich Von oder Nach seit dem Namen geändert hat: Ein eigener Name wie „… über Salzburg“ löst nie einen Vorschlag aus.
    return suggestion && title && title !== suggestion && suggestion !== this.titleBasis() ? suggestion : null;
  });
  /** Der Namensvorschlag („Von – Nach“) zu dem Zeitpunkt, als der Name geladen, gespeichert oder vorgeschlagen wurde. */
  private titleBasis = signal('');

  /** „Als Namen übernehmen“ am Vorschlag. */
  acceptTitleSuggestion(suggestion: string) {
    this.tripTitle.set(suggestion);
    this.titleBasis.set(suggestion);
  }

  /** Merkt, zu welcher Strecke der aktuelle Name gehört (siehe titleSuggestion). */
  private rememberTitleBasis() {
    this.titleBasis.set(tourTitle(this.result()?.legs ?? [], this.stops()));
  }
  /**
   * Nimmt das Archiv diese Tour auf? Es braucht zwei Halte, oder einen Halt und einen Betriebshof der Firma (wie die Berechnung;
   * ein „anderer Ort“ als Betriebshof wird nicht gespeichert).
   */
  canStore = computed(
    () => this.stops().filter((s) => s.key !== -1).length + this.pending().length >= 2 || this.depots().some((d) => d.id === this.depotChoice()),
  );
  /** Die Taste „Tour speichern“ ist nicht nutzbar: es läuft etwas, das Archiv nimmt die Tour nicht, oder die Strecke lässt sich nicht berechnen. */
  saveDisabled = computed(
    () => this.savingTrip() || this.calculating() || !this.canStore() || ((this.stale() || !this.result()) && !this.canCalc()),
  );

  /**
   * Fahrtart, Fahrzeugtyp oder Anzahl geändert: Das Ergebnis bleibt gültig (die Route hängt nicht davon ab), aber die Tour im Archiv
   * hat einen anderen Stand. „Angebot erstellen“ speichert sie deshalb neu.
   */
  markTripChanged() {
    this.tripDirty.set(true);
    this.tripMessage.set(null);
  }
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
    // reset() leert auch Abfahrt, lange Tage und Optimierung: Die gespeicherte Tour hat keinen Termin, ein alter Wert gehörte zur vorigen Planung.
    this.reset();
    const place = (s: Trip['stops'][number]): Place => ({ label: s.label, lat: s.place.lat, lon: s.place.lon });
    const waypoint = (s: Trip['stops'][number]): Waypoint => ({ key: this.nextKey++, place: place(s), dwell_minutes: s.dwell_minutes, driver_free: s.driver_free });

    const list = trip.stops.map(waypoint);
    this.start.set(list[0]);
    // Eine Tour mit nur einem Halt (Betriebshof → Halt → Betriebshof) hat kein eigenes Ziel.
    this.end.set(list.length > 1 ? list[list.length - 1] : this.blank());
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
    this.rememberTitleBasis();
    this.currentTrip.set({ id: trip.id, title: trip.title });
    this.tripDirty.set(false);
    this.tripLoadedMessage.set(`Tour „${trip.title}“ ist geladen. Sie können gleich ein Angebot erstellen oder die Strecke ändern und neu berechnen.`);
  }

  /**
   * Speichert die Tour im Archiv: als Änderung der geladenen Tour oder (asNew) als neue Tour. Wurde die Eingabe seit der Berechnung
   * geändert (zum Beispiel ein Halt), fragt ein Dialog kurz vor dem Speichern, ob die Strecke neu berechnet und dann gespeichert
   * werden soll. Gespeichert wird nie ein Ergebnis, das nicht mehr zur Eingabe passt; neu berechnet wird nie ungefragt.
   */
  async saveTrip(asNew = false) {
    if (this.savingTrip() || this.calculating() || !this.canStore()) return;
    // Eine Kopie unter demselben Namen würde zwei gleiche Touren im Archiv ergeben: Dann bekommt sie den Zusatz „(Kopie)“.
    if (asNew && this.tripTitle().trim() === this.currentTrip()?.title) {
      this.tripTitle.set(`${this.tripTitle().trim().slice(0, 142)} (Kopie)`);
    }
    if (this.stale() || !this.result()) {
      if (!this.canCalc()) return;
      const fresh = !this.result();
      const ok = await this.confirm.ask({
        title: fresh ? 'Strecke berechnen?' : 'Strecke neu berechnen?',
        message: fresh
          ? 'Die Strecke ist noch nicht berechnet. Gespeichert wird die berechnete Strecke mit Zeitplan und Linie. Jetzt berechnen und danach speichern?'
          : 'Sie haben die Angaben zur Strecke geändert, das Ergebnis gehört noch zum alten Stand. Damit die richtige Strecke gespeichert wird, muss sie zuerst neu berechnet werden. Jetzt neu berechnen und danach speichern?',
        confirmLabel: fresh ? 'Berechnen und speichern' : 'Neu berechnen und speichern',
      });
      if (!ok) return;
      this.tripError.set(null);
      this.calculate(() => void this.saveTrip(asNew));
      return;
    }
    this.savingTrip.set(true);
    this.tripError.set(null);
    this.storeTrip(asNew).subscribe({
      next: (trip) => {
        this.savingTrip.set(false);
        this.tripLoadedMessage.set(null);
        this.tripMessage.set(`Tour „${trip.title}“ ist im Tour-Archiv gespeichert. Nächster Schritt: Angebot erstellen. Die Tour finden Sie später über „Aus Archiv laden“.`);
        this.scrollToMessages();
      },
      error: (err: HttpErrorResponse) => {
        this.savingTrip.set(false);
        this.tripError.set(httpErrorText(err, 'Die Tour konnte nicht gespeichert werden.'));
        this.scrollToMessages();
      },
    });
  }

  /** Schickt die berechnete Tour ans Archiv: ändert die geladene Tour oder (asNew) legt eine neue an. */
  private storeTrip(asNew: boolean): Observable<Trip> {
    const r = this.result()!;
    const depotId = this.depots().some((d) => d.id === this.depotChoice()) ? this.depotChoice() : null;
    const body: TripInput = {
      title: this.tripTitle().trim() || tourTitle(r.legs, this.stops()) || 'Tour',
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
        this.rememberTitleBasis();
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
    this.markTripChanged();
  }

  /** Die Halte in Fahrtreihenfolge (leere Felder fehlen, mit Rückfahrt steht der Start am Ende noch einmal). */
  stops = computed(() => buildStops(this.withResolved(this.start()), this.via().map((w) => this.withResolved(w)), this.withResolved(this.end()), this.roundTrip()));
  /** Orte mit Aufenthalt, wie sie unter "Weitere Angaben" einzeln eingestellt werden (ohne die Rückfahrt). */
  placed = computed(() => [this.start(), ...this.via(), this.end()].map((w) => this.withResolved(w)).filter((w) => w.place !== null));

  /** Text je Feld (Schlüssel des Ortes), so wie er eingegeben wurde. */
  texts = signal<Record<number, string>>({});
  /**
   * Beim Berechnen gefundener Ort zu einem Text (Schlüssel des Feldes). Das Feld selbst bleibt unverändert. Der Name des Ortes ist der
   * getippte Text: Was Sie eingeben, steht so im Archiv, im Angebot und im PDF. Vom Ortsdienst kommt nur die Lage.
   */
  resolved = signal<Record<number, Place>>({});
  /** Wie der Ortsdienst den gefundenen Ort selbst nennt (nur als Hinweis unter dem Feld, damit man die Lage prüfen kann). */
  foundAs = signal<Record<number, string>>({});
  /** Felder, zu denen die Suche keinen Ort gefunden hat: Sie werden am Feld selbst rot markiert, bis der Text geändert oder ein Ort gewählt wird. */
  notFound = signal<Record<number, true>>({});

  /** Fehlertext am Feld (leer: alles in Ordnung). */
  problemOf(w: Waypoint): string {
    return this.notFound()[w.key] ? 'Zu diesem Text wurde kein Ort gefunden.' : '';
  }
  /** Felder mit Text, zu dem noch kein Ort gesucht wurde: Sie werden beim Berechnen aufgelöst. */
  pending = computed(() => [this.start(), this.via(), this.end()].flat().filter((w) => !w.place && !this.resolved()[w.key] && this.textOf(w).trim().length >= 2));
  /** Betriebshof mit Text, aber noch ohne gefundenen Ort */
  depotPending = computed(() => this.depotChoice() === 'other' && !this.depot() && this.depotText().trim().length >= 2);

  /**
   * Gibt es etwas zu berechnen? Nein, wenn schon ein Ergebnis da ist und sich seither nichts geändert hat (eine geladene Tour oder eine
   * eben berechnete Strecke): Eine neue Anfrage lieferte dasselbe und kostet Kontingent beim Routing-Dienst.
   */
  needsCalc = computed(() => this.canCalc() && (this.stale() || !this.result()));
  canCalc = computed(() => canCalculate(this.stops().length + this.pending().length, !!this.depot() || this.depotPending(), this.returnsToDepot()) && !this.calculating());
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
    // Ein Halt aus einer geladenen Tour trägt seinen Ort schon: Wird der Text geändert, gilt der alte Ort nicht mehr und der neue Text
    // wird beim Berechnen gesucht (sonst bliebe die Änderung wirkungslos).
    const current = [this.start(), ...this.via(), this.end()].find((w) => w.key === key);
    if (current?.place && locateKey(text) !== locateKey(current.place.label)) this.clearPlace(key);
    this.texts.update((t) => ({ ...t, [key]: text }));
    // Anderer Text: der zuvor gefundene Ort gilt nicht mehr.
    this.resolved.update(({ [key]: _gone, ...rest }) => rest);
    this.foundAs.update(({ [key]: _gone, ...rest }) => rest);
    this.notFound.update(({ [key]: _gone, ...rest }) => rest);
    this.changed();
  }

  /** Lage des Ortes, der für ein Feld gilt (nach dem Berechnen oder bei einer geladenen Tour), sonst null. */
  coordsOf(w: Waypoint): { lat: number; lon: number } | null {
    const place = this.withResolved(w).place;
    return place ? { lat: place.lat, lon: place.lon } : null;
  }

  /**
   * Aus der Trefferliste wurde ein anderer Ort gewählt (der erste Treffer lag falsch, zum Beispiel der Gemeinde-Mittelpunkt im Wald).
   * Der Name bleibt der getippte Text, die Lage kommt vom gewählten Treffer. Die Wahl gilt künftig für denselben Text.
   */
  choosePlace(key: number, hit: GeoHit) {
    const typed = (this.texts()[key] ?? '').replace(/\s+/g, ' ').trim();
    const found = shortLabel(hit.label);
    const place: Place = { label: typed || found, lat: hit.lat, lon: hit.lon };
    const set = (w: Waypoint): Waypoint => (w.key === key ? { ...w, place } : w);
    this.start.update(set);
    this.end.update(set);
    this.via.update((list) => list.map(set));
    this.resolved.update(({ [key]: _gone, ...rest }) => rest);
    this.foundAs.update((r) => ({ ...r, [key]: found }));
    this.notFound.update(({ [key]: _gone, ...rest }) => rest);
    this.api.rememberChoice(typed, { label: found, lat: hit.lat, lon: hit.lon });
    this.changed();
  }

  /** Nimmt einem Feld den bereits bekannten Ort, ohne Aufenthalt und Fahrer-frei zu verlieren. */
  private clearPlace(key: number) {
    const strip = (w: Waypoint): Waypoint => (w.key === key ? { ...w, place: null } : w);
    this.start.update(strip);
    this.end.update(strip);
    this.via.update((list) => list.map(strip));
  }

  textOf(w: Waypoint): string {
    return this.texts()[w.key] ?? '';
  }

  // ---- Über ----
  addVia() {
    this.via.update((list) => [...list, this.blank()]);
  }
  /**
   * Das Ergebnis stammt aus einer Berechnung in dieser Sitzung, nicht aus einer geladenen Tour. Nur dann gibt es den Tipp zur
   * Optimierung: Eine geladene Tour ist schon geplant, der Tipp würde zu unnötigen Anfragen verleiten.
   */
  calculatedHere = signal(false);

  /** Zwischenhalte mit Text (leere Felder zählen nicht). */
  viaCount = computed(() => this.via().filter((w) => this.textOf(w).trim() !== '' || !!w.place).length);

  /** Der Tipp „Optimieren“: Kästchen anhaken und gleich neu berechnen. */
  optimizeNow() {
    this.optimize.set(true);
    this.changed();
    this.calculate();
  }

  removeVia(index: number) {
    this.via.update((list) => list.filter((_, i) => i !== index));
    // Optimieren gibt es erst ab zwei Zwischenhalten: Bleibt nur einer, wird das Kästchen zurückgesetzt
    if (this.via().length < 2) this.optimize.set(false);
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
    this.foundAs.set({});
    this.notFound.set({});
    this.roundTrip.set(false);
    this.startAt.set('');
    this.extendedDays.set(0);
    this.optimize.set(false);
    this.result.set(null);
    this.calculatedHere.set(false);
    this.error.set(null);
    this.stale.set(false);
    this.currentTrip.set(null);
    this.tripTitle.set('');
    this.titleBasis.set('');
    this.tripMessage.set(null);
    this.tripLoadedMessage.set(null);
    this.tripError.set(null);
  }

  /**
   * Gibt es Eingaben, die noch in keiner Tour im Archiv stehen? Bei einer geladenen Tour: Sie wurde seit dem Laden oder Speichern
   * verändert. Sonst: Es steht etwas in den Feldern oder es gibt ein Ergebnis.
   */
  unsavedInput = computed(() =>
    this.currentTrip() ? this.tripDirty() : Object.values(this.texts()).some((t) => t.trim() !== '') || !!this.result(),
  );

  /**
   * Die Seite wird verlassen (anderer Menüpunkt, Zurück-Taste): Gibt es Ungespeichertes, fragt ein Dialog vorher. Nach dem Anlegen eines
   * Angebots wird nicht gefragt, die Strecke steckt dann im Angebot.
   */
  canLeave(): boolean | Promise<boolean> {
    if (this.leaving || !this.unsavedInput()) return true;
    return this.confirm.ask({
      title: 'Seite verlassen?',
      message: 'Ihre Eingabe ist noch nicht im Tour-Archiv gespeichert. Wenn Sie die Seite verlassen, geht sie verloren.',
      confirmLabel: 'Verlassen',
      danger: true,
    });
  }
  private leaving = false;

  /** Neu laden oder Fenster schließen: der Browser warnt, solange etwas nicht gespeichert ist. */
  @HostListener('window:beforeunload', ['$event'])
  warnBeforeUnload(event: BeforeUnloadEvent) {
    if (this.unsavedInput()) {
      event.preventDefault();
      event.returnValue = '';
    }
  }

  /**
   * Zu den Meldungen über der Tastenleiste scrollen: Sie stehen weit unten im Eingabebereich und wurden sonst übersehen. Wird bei JEDER
   * Meldung aufgerufen, auch wenn sie genauso lautet wie die vorige (ein Signal meldet gleiche Werte nicht noch einmal).
   */
  private scrollToMessages() {
    setTimeout(() => document.getElementById('planMessages')?.scrollIntoView({ block: 'center', behavior: 'smooth' }), 120);
  }

  /** „Neu beginnen“: Gibt es Ungespeichertes, fragt ein Dialog vorher. Gespeicherte Touren bleiben immer erhalten. */
  async confirmReset() {
    if (this.unsavedInput()) {
      const trip = this.currentTrip();
      const ok = await this.confirm.ask({
        title: 'Neu beginnen?',
        message: trip
          ? `Ihre Änderungen an der Tour „${trip.title}“ sind noch nicht gespeichert. Wenn Sie neu beginnen, gehen sie verloren. Die gespeicherte Tour bleibt unverändert im Archiv.`
          : 'Ihre Eingabe ist noch in keiner Tour im Archiv gespeichert. Wenn Sie neu beginnen, geht sie verloren. Gespeicherte Touren bleiben erhalten.',
        confirmLabel: 'Neu beginnen',
        danger: true,
      });
      if (!ok) return;
    }
    this.reset();
  }

  /** Jede Änderung an der Eingabe macht ein vorhandenes Ergebnis zum veralteten. */
  changed() {
    if (this.result()) this.stale.set(true);
    this.tripMessage.set(null);
    this.tripLoadedMessage.set(null);
    this.tripDirty.set(true);
  }

  // ---- Berechnung ----
  /** @param after wird nach erfolgreicher Berechnung aufgerufen (zum Beispiel „danach speichern“) */
  calculate(after?: () => void) {
    if (!this.canCalc()) return;
    // Nichts geändert, Ergebnis aktuell: keine Anfrage (weder für Orte noch für die Route)
    if (!this.needsCalc() && this.pending().length === 0 && !this.depotPending()) {
      after?.();
      return;
    }
    this.calculating.set(true);
    this.error.set(null);
    // Nur Felder, deren Text seit der letzten Suche neu ist; schon einmal gesuchte Texte liefert locate() ohne Anfrage.
    const todo = this.pending();
    const texts = [...todo.map((w) => this.textOf(w).trim()), ...(this.depotPending() ? [this.depotText().trim()] : [])];
    if (!texts.length) {
      this.run(after);
      return;
    }
    forkJoin(texts.map((t) => this.api.locate(t))).subscribe({
      next: (places) => {
        // Gefundene Orte werden immer gemerkt und angezeigt, auch wenn ein anderes Feld nicht gefunden wurde.
        const found: Record<number, Place> = {};
        const foundAs: Record<number, string> = {};
        const missing: Record<number, true> = {};
        todo.forEach((w, i) => {
          const hit = places[i];
          if (!hit) {
            missing[w.key] = true;
            return;
          }
          // Name = getippter Text (Leerzeichen bereinigt); die Lage kommt vom Ortsdienst
          found[w.key] = { ...hit, label: texts[i].replace(/\s+/g, ' ') };
          foundAs[w.key] = hit.label;
        });
        this.resolved.update((r) => ({ ...r, ...found }));
        this.foundAs.update((r) => ({ ...r, ...foundAs }));
        const depotHit = this.depotPending() ? places[texts.length - 1] : null;
        if (depotHit) this.depot.set(depotHit);

        const missingTexts = [...todo.filter((w) => missing[w.key]).map((w) => this.textOf(w).trim()), ...(this.depotPending() && !depotHit ? [this.depotText().trim()] : [])];
        if (missingTexts.length) {
          // Das betroffene Feld wird am Feld selbst markiert; die Meldung steht über den Tasten.
          this.notFound.update((r) => ({ ...r, ...missing }));
          this.calculating.set(false);
          this.error.set(
            `Kein Ort zu ${missingTexts.map((m) => `„${m}“`).join(' und zu ')} gefunden. Bitte schreiben Sie ihn genauer (zum Beispiel ohne Ortsteil, mit Postleitzahl oder „Ulm Hbf“) oder wählen Sie beim Feld „Ort aus Trefferliste wählen“.`,
          );
          this.scrollToMessages();
          return;
        }
        this.run(after);
      },
      error: (err: HttpErrorResponse) => {
        this.calculating.set(false);
        this.error.set(httpErrorText(err, 'Die Orte konnten nicht gesucht werden.'));
        this.scrollToMessages();
      },
    });
  }

  /** Die Optimierung hat die Zwischenhalte umgestellt: Die Über-Felder zeigen jetzt die neue Reihenfolge. */
  private applyOrder(keys: number[], order: number[]) {
    const rank = new Map(order.map((idx, pos) => [keys[idx], pos]));
    this.via.update((list) => [...list].sort((a, b) => (rank.get(a.key) ?? 1e9) - (rank.get(b.key) ?? 1e9)));
  }

  private run(after?: () => void) {
    const request = toRequest({
      stops: this.stops(),
      depot: this.depot(),
      returnToDepot: this.returnsToDepot(),
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
        this.calculatedHere.set(true);
        this.calculating.set(false);
        // Name der Tour vorschlagen, solange der Nutzer keinen eigenen eingetragen hat.
        if (!this.tripTitle().trim()) {
          this.tripTitle.set(tourTitle(r.legs, this.stops()));
          this.rememberTitleBasis();
        }
        after?.();
      },
      error: (err: HttpErrorResponse) => {
        this.calculating.set(false);
        this.error.set(httpErrorText(err, 'Die Berechnung ist fehlgeschlagen.'));
        this.scrollToMessages();
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

  /** Hinweis unter dem Feld: wie der Ortsdienst den gefundenen Ort nennt. */
  usedFor(w: Waypoint): string {
    // Immer, wenn ein Ort gesucht oder gewählt wurde: Der Nutzer sieht, welcher Ort für seine Eingabe gilt
    return (w.place ?? this.resolved()[w.key]) ? (this.foundAs()[w.key] ?? '') : '';
  }

  private blank(): Waypoint {
    return { key: this.nextKey++, place: null, dwell_minutes: 0, driver_free: false };
  }
}
