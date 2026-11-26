import { HttpErrorResponse } from '@angular/common/http';
import { Observable, forkJoin, map, of, switchMap, tap } from 'rxjs';
import { DatePipe, DecimalPipe, NgTemplateOutlet } from '@angular/common';
import { Component, HostListener, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NgbDropdown, NgbDropdownItem, NgbDropdownMenu, NgbDropdownToggle } from '@ng-bootstrap/ng-bootstrap';
import { ActivatedRoute, Router } from '@angular/router';
import { ConfirmService } from '../../core/confirm.service';
import { SectionKindValue, TripKindValue } from '../../core/document.service';
import { JourneyRow, JourneyService, SectionBody, SectionEditData, SectionUpdate } from '../../core/journey.service';
import { PricingService, TourPayload } from '../../core/pricing.service';
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
import { SectionEdit } from './section-edit';
import { SectionEditFieldsComponent } from './section-edit-fields.component';
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
 * Der Alltag braucht nur Von, Nach und (freiwillig) Über-Orte; darüber steht der Betriebshof, Aufenthalte und lange Tage
 * stehen unter "Weitere Angaben".Eine berechnete Tour lässt sich im Tour-Archiv speichern und später wieder laden (samt Zeitplan und Linienzug,
 * ohne neue Berechnung); ohne "Als Vorlage speichern" bleibt nichts zurück. Die Betriebshöfe pflegt der
 * Administrator unter Einstellungen; ihre Koordinaten stehen dort schon fest. Die Seite ist ein Werkzeug, kein Bearbeitungsformular: Sie nutzt die Formular-Optik
 * (.form-area, .section-title), hat aber keine Tastenleiste.
 */
@Component({
  selector: 'app-route-planning',
  imports: [FormsModule, NgbDropdown, NgbDropdownToggle, NgbDropdownMenu, NgbDropdownItem, DatePipe, DecimalPipe, NgTemplateOutlet, HelpLinkComponent, HighlightComponent, MapComponent, PlaceFieldComponent, SectionEditFieldsComponent],
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
  private journeyApi = inject(JourneyService);
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
  /** Anfahrt vom Betriebshof zum Start. Bei der Rückfahrt einer Reise ist sie nicht vorgewählt: Der Bus ist schon am Ziel. */
  fromDepot = signal(true);
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
    // Das Angebot legt NICHTS im Tour-Archiv an: Es bekommt die berechnete Tour als eigene Kopie (tour). Nur eine unveränderte Tour
    // aus dem Archiv wird zusätzlich als Herkunft genannt (trip_id); wurde sie nach dem Laden verändert, gilt die Berechnung.
    // Das Archiv bleibt Vorlage für künftige Angebote und wird nur mit „Als Vorlage speichern“ geändert.
    const current = this.currentTrip();
    this.pricing
      .quoteFromTour({
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
        trip_id: current && !this.tripDirty() ? current.id : null,
        tour: this.buildTourPayload(r),
      })
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

  /**
   * Die berechnete Tour als Nutzlast für Angebot oder Reise: Halte, Kilometer, Zeiten und Teilstrecken. Die Strecke der Reise speichert
   * sie als eigene Kopie, ins Tour-Archiv kommt dabei nichts.
   */
  private buildTourPayload(r: RouteResult): TourPayload {
    const label = this.tripTitle().trim() || tourTitle(r.legs, this.stops());
    return {
      title: label,
      trip_kind: this.tripKind(),
      loaded_km: Math.round(r.loaded_distance_m / 100) / 10,
      empty_km: Math.round(r.empty_distance_m / 100) / 10,
      drive_s: r.drive_s,
      elapsed_s: r.elapsed_s,
      days: Math.max(1, r.days),
      round_trip: this.roundTrip(),
      // Die Rückfahrt zum Start steht als zusätzlicher Halt (Nummer -1) in der Berechnung, das Kennzeichen round_trip trägt sie.
      stops: this.stops()
        .filter((s) => s.key !== -1)
        .map((s) => ({ label: s.label, dwell_minutes: s.dwell_minutes ?? 0, driver_free: !!s.driver_free })),
      legs: r.legs.map((l) => ({ from: l.from.label, to: l.to.label, distance_m: l.distance_m, duration_s: l.duration_s, empty: l.empty })),
      plan: this.buildPlan(r),
    };
  }

  /** Alles, was die Strecke einer Reise braucht, um später wieder so geöffnet zu werden. */
  private buildPlan(r: RouteResult): NonNullable<TourPayload['plan']> {
    const other = this.depotChoice() === 'other' ? this.depot() : null;
    return {
      depot_id: this.depots().some((d) => d.id === this.depotChoice()) ? this.depotChoice() : null,
      depot_other: other ? { label: this.depotText().trim() || other.label, lat: other.lat, lon: other.lon } : null,
      from_depot: this.fromDepot(),
      return_to_depot: this.depotChoice() !== '' && this.returnToDepot(),
      start_at: r.start_at,
      stops: this.stops()
        .filter((s) => s.key !== -1)
        .map((s) => ({ label: s.label, lat: s.lat, lon: s.lon, dwell_minutes: s.dwell_minutes, driver_free: s.driver_free })),
      route: storableRoute(r),
      geometry: r.geometry,
    };
  }

  // ---- Strecke einer Reise anlegen oder ändern ----
  /** Gesetzt bei Aufruf von der Reise: die Strecke (?journey=…&section=…) oder „new“ für eine neue (?journey=…). Sonst freie Planung. */
  sectionId = signal<string | null>(null);
  /** Die geladene Strecke; null, solange sie noch lädt */
  section = signal<SectionEdit | null>(null);
  savingSection = signal(false);
  /** Der Betriebshof wurde von Hand gewählt: Ein Wechsel der Art ändert ihn dann nicht mehr. */
  depotTouched = false;
  /** Anfahrt oder Rückfahrt wurden von Hand gewählt: Ein Wechsel der Art ändert sie dann nicht mehr. */
  depotLegsTouched = false;
  /** Felder für Von und Nach werden als fehlend markiert (nach einem Speichern-Versuch ohne vollständige Route). */
  routeMissing = signal(false);

  /**
   * Vorgaben für eine neue Strecke. Hinfahrt: Betriebshof → Von → Nach. Rückfahrt: Von → Nach → Betriebshof (nie mit Anfahrt).
   * Fahrt vor Ort: ohne Betriebshof, der Bus ist schon dort.
   */
  private applyKindDefaults(kind: SectionKindValue) {
    if (!this.depotLegsTouched) {
      this.fromDepot.set(kind !== 'rueckfahrt');
      this.returnToDepot.set(kind === 'rueckfahrt');
    }
    if (this.depotTouched) return;
    const choice = kind === 'vor_ort' ? '' : (this.depots().find((d) => d.is_default)?.id ?? '');
    if (choice !== this.depotChoice()) this.chooseDepot(choice);
  }

  /** Eine neue Strecke: Art vorschlagen, Fahrzeuge der ersten Strecke übernehmen, bei der Rückfahrt Von und Nach vertauscht belegen. */
  private openNewSection(journeyId: string) {
    this.loadingTrip.set(true);
    this.journeyApi.get(journeyId).subscribe({
      next: (j) => {
        this.loadingTrip.set(false);
        const asked = this.sectionKinds.find((k) => k.value === this.route.snapshot.queryParamMap.get('kind'))?.value;
        const have = new Set(j.sections.map((s) => s.kind));
        const kind: SectionKindValue = asked ?? (!have.has('hinfahrt') ? 'hinfahrt' : !have.has('rueckfahrt') ? 'rueckfahrt' : 'vor_ort');
        const first = j.sections.find((s) => s.kind === 'hinfahrt') ?? j.sections[0];
        const vehicles = (first?.vehicles ?? []).filter((v) => !!v.type).map((v) => ({ type: v.type, count: v.count }));
        this.applyKindDefaults(kind);
        if (vehicles[0] && !this.vehicleType()) {
          this.vehicleType.set(vehicles[0].type!);
          this.vehicleCount.set(vehicles[0].count);
        }
        // Rückfahrt: die vertauschten Orte der Hinfahrt, nur als Text; gesucht wird beim Berechnen.
        if (kind === 'rueckfahrt' && first?.trip?.from && first.trip.to && !Object.keys(this.texts()).length) {
          this.texts.set({ [this.start().key]: first.trip.to, [this.end().key]: first.trip.from });
        }
        this.tripDirty.set(false);
        this.section.set(
          new SectionEdit({
            journey: { id: j.id, title: j.title }, id: '', kind, kind_label: this.kindLabel(kind), title: '', start_on: null, surcharge_percent: 0,
            vehicles, figures: { loaded_km: 0, empty_km: 0, drive_s: 0, days: 1 }, tour: null, plan: null, frozen_by: null,
          }),
        );
      },
      error: (err: HttpErrorResponse) => {
        this.loadingTrip.set(false);
        this.tripError.set(httpErrorText(err, 'Die Reise konnte nicht geladen werden.'));
      },
    });
  }

  kindLabel(kind: SectionKindValue): string {
    return this.sectionKinds.find((k) => k.value === kind)!.label;
  }

  /** Art einer neuen Strecke gewählt: Die Vorgaben zum Betriebshof wechseln mit, eigene Wahlen bleiben. */
  setSectionKind(kind: SectionKindValue) {
    this.section()?.kind.set(kind);
    this.applyKindDefaults(kind);
    this.changed();
  }

  private openSection(journeyId: string, sectionId: string) {
    this.loadingTrip.set(true);
    this.journeyApi.getSection(journeyId, sectionId).subscribe({
      next: (data) => {
        this.applySection(data);
        this.loadingTrip.set(false);
        if (this.route.snapshot.queryParamMap.get('focus') === 'date') setTimeout(() => document.getElementById('secDate')?.focus(), 120);
      },
      error: (err: HttpErrorResponse) => {
        this.loadingTrip.set(false);
        this.tripError.set(httpErrorText(err, 'Die Strecke konnte nicht geladen werden.'));
      },
    });
  }

  /** Eine Strecke in die Planung übernehmen, mit gespeicherten Orten und Ergebnis, ohne jede neue Anfrage. */
  private applySection(data: SectionEditData) {
    this.reset();
    const { tour, plan } = data;
    const stops: { label: string; dwell_minutes: number; driver_free: boolean; lat?: number; lon?: number }[] = plan?.stops ?? tour?.stops ?? [];
    // Ältere Strecken kennen nur die Namen der Halte: Ihre Lage wird erst gesucht, wenn Sie neu berechnen.
    const list: Waypoint[] = stops.map((s) => ({
      key: this.nextKey++,
      place: s.lat != null && s.lon != null ? { label: s.label, lat: s.lat, lon: s.lon } : null,
      dwell_minutes: s.dwell_minutes,
      driver_free: s.driver_free,
    }));
    if (list.length) {
      this.start.set(list[0]);
      this.end.set(list.length > 1 ? list[list.length - 1] : this.blank());
      this.via.set(list.slice(1, -1));
      this.texts.set(Object.fromEntries(list.map((w, i) => [w.key, stops[i].label])));
    }
    this.roundTrip.set(tour?.round_trip ?? false);

    if (plan) {
      const known = this.depots().find((d) => d.id === plan.depot_id);
      if (plan.depot_other) {
        this.chooseDepot('other');
        this.depotText.set(plan.depot_other.label);
        this.depot.set(plan.depot_other);
      } else {
        this.chooseDepot(known?.id ?? '');
      }
      this.fromDepot.set(plan.from_depot ?? true);
      this.returnToDepot.set(plan.return_to_depot);
    } else {
      // Ältere Strecke: Der Betriebshof steht nur als Name am Anfang oder Ende der Leerfahrten.
      const legs = tour?.legs ?? [];
      const first = legs[0]?.empty ? this.depots().find((d) => d.name === legs[0].from) : undefined;
      const lastLeg = legs[legs.length - 1];
      const last = lastLeg?.empty ? this.depots().find((d) => d.name === lastLeg.to) : undefined;
      this.chooseDepot(first?.id ?? last?.id ?? '');
      this.fromDepot.set(!!first || !last);
      this.returnToDepot.set(!!last && !tour?.round_trip);
    }

    this.tripKind.set(tour?.trip_kind ?? 'gelegenheit');
    const bus = data.vehicles.find((v) => !!v.type);
    this.vehicleType.set(bus?.type ?? '');
    this.vehicleCount.set(bus?.count ?? 1);
    this.vehicleId.set('');

    if (plan?.route) this.result.set(restoredResult(plan.route, plan.geometry));
    this.stale.set(false);
    this.tripTitle.set(data.title);
    this.rememberTitleBasis();
    this.tripDirty.set(false);
    this.section.set(new SectionEdit(data));
  }

  /** Nach einer Berechnung (oder aus einer Vorlage): neue Werte für die Strecke; bei Handwerten wird gefragt. */
  private async takeSectionRoute(edit: SectionEdit, r: { loaded_km: number; empty_km: number; drive_s: number; days: number }) {
    // Bezeichnung vorschlagen („Von – Nach“), solange keine eingetragen ist
    if (!edit.title().trim()) edit.title.set(this.tripTitle().trim() || tourTitle(this.result()?.legs ?? [], this.stops()));
    const keep =
      edit.hasHandValues() &&
      !(await this.confirm.ask({
        title: 'Werte übernehmen?',
        message: 'Die Strecke hat Werte, die von Hand eingetragen sind. Sollen die neu berechneten Werte sie ersetzen?',
        confirmLabel: 'Berechnete Werte nehmen',
        cancelLabel: 'Meine Werte behalten',
      }));
    edit.takeCalculation(r, keep);
  }

  private figuresOf(r: RouteResult) {
    return { loaded_km: Math.round(r.loaded_distance_m / 100) / 10, empty_km: Math.round(r.empty_distance_m / 100) / 10, drive_s: r.drive_s, days: Math.max(1, r.days) };
  }

  /** „Speichern“ im Änderungsmodus. Neu berechnet wird nur nach Änderungen an der Route, und nie ungefragt. */
  async saveSection() {
    const edit = this.section();
    if (!edit || this.savingSection() || this.calculating()) return;
    this.quoteError.set(null);
    const hasInput = !!this.result() || Object.values(this.texts()).some((t) => t.trim() !== '');
    // Fahrzeuge und Zuschlag zuerst: Dafür braucht es keine Berechnung.
    if (edit.inputProblem()) {
      this.showFieldErrors(edit);
      return;
    }
    // Eine neue Strecke ohne eingetragene Werte braucht die Berechnung, sobald Orte dastehen.
    const needsRoute = edit.routeChanged() || (edit.isNew && edit.figures().loaded_km <= 0);
    if (needsRoute && hasInput && (this.stale() || !this.result())) {
      if (!this.canCalc()) {
        this.routeMissing.set(true);
        this.quoteError.set('Bitte Von und Nach vollständig eingeben, damit die Route berechnet werden kann.');
        this.focusFirstError();
        return;
      }
      const ok = await this.confirm.ask({
        title: 'Strecke neu berechnen?',
        message: edit.isNew
          ? 'Die Strecke ist noch nicht berechnet. Jetzt berechnen und danach speichern?'
          : 'Sie haben die Route geändert. Vor dem Speichern muss sie neu berechnet werden. Jetzt berechnen und danach speichern?',
        confirmLabel: 'Berechnen und speichern',
      });
      if (ok) this.calculate(() => void this.saveSection());
      return;
    }
    if (edit.problem()) {
      this.showFieldErrors(edit);
      return;
    }

    const r = this.result();
    const body = edit.body();
    if (edit.newRoute() && r) body.tour = { ...this.buildTourPayload(r), title: body.title };
    if (edit.hasRoute()) body.trip_kind = this.tripKind();
    this.savingSection.set(true);
    const journey = edit.data.journey.id;
    const call = edit.isNew ? this.journeyApi.addSection(journey, this.newSectionBody(edit, body)) : this.journeyApi.updateSection(journey, edit.data.id, body);
    call.subscribe({
      next: () => {
        this.savingSection.set(false);
        this.leaving = true;
        this.router.navigate(['/reisen', journey], { state: { sectionSaved: true } });
      },
      error: (e: HttpErrorResponse) => {
        this.savingSection.set(false);
        this.quoteError.set(httpErrorText(e, 'Die Strecke konnte nicht gespeichert werden.'));
        this.scrollToMessages();
      },
    });
  }

  /** Die Angaben für eine neue Strecke: mit Route, oder ohne Route mit den selbst eingetragenen Werten. */
  private newSectionBody(edit: SectionEdit, b: SectionUpdate): SectionBody {
    const common = { kind: edit.kind(), start_on: b.start_on, surcharge_percent: b.surcharge_percent, vehicles: b.vehicles };
    return b.tour
      ? { ...common, tour: { ...b.tour, trip_kind: this.tripKind() }, figures: b.figures }
      : { ...common, own: { title: b.title, loaded_km: b.figures.loaded_km, drive_minutes: Math.max(1, Math.round(b.figures.drive_s / 60)), days: b.figures.days } };
  }

  /** Fehlerhafte Felder der Strecke rot markieren und zum ersten springen; die Meldung steht zusätzlich bei den Tasten. */
  private showFieldErrors(edit: SectionEdit) {
    edit.submitted.set(true);
    this.focusFirstError();
  }

  private focusFirstError() {
    setTimeout(() => {
      const field = document.querySelector<HTMLElement>('.form-area .is-invalid');
      field?.scrollIntoView({ block: 'center', behavior: 'smooth' });
      field?.focus({ preventScroll: true });
    }, 120);
  }

  cancelSection() {
    const id = this.route.snapshot.queryParamMap.get('journey');
    this.router.navigate(['/reisen', id]);
  }

  // ---- Zur Reise hinzufügen (freie Planung) ----
  /** Die Angaben dazu sind eingeblendet (Menü neben „Angebot erstellen“) */
  journeyPanel = signal(false);
  /** „Hinzufügen“ ohne Fahrzeugtyp: Das Feld wird markiert. */
  vehicleTypeMissing = signal(false);
  /** Ziel der Strecke („new“ = neue Reise anlegen, sonst die Nummer einer Reise) und Art der Strecke */
  targetChoice = signal('new');
  targetKind = signal<SectionKindValue>('hinfahrt');
  readonly sectionKinds: { value: SectionKindValue; label: string }[] = [
    { value: 'hinfahrt', label: 'Hinfahrt' },
    { value: 'vor_ort', label: 'Fahrt vor Ort' },
    { value: 'rueckfahrt', label: 'Rückfahrt' },
  ];
  journeys = signal<JourneyRow[]>([]);
  addingToJourney = signal(false);
  /** Das Datum der Fahrt (Tag des Starttermins) für die Zuschläge der Strecke, sonst null. */
  private startDay(r: RouteResult): string | null {
    return r.start_at ? new Date(r.start_at).toLocaleDateString('sv-SE') : null;
  }

  openJourneyPanel() {
    this.journeyPanel.set(true);
    this.quoteError.set(null);
    setTimeout(() => document.getElementById('journeyPanel')?.scrollIntoView({ block: 'center', behavior: 'smooth' }), 120);
  }

  /**
   * „Zur Reise hinzufügen“: Die berechnete Tour wird eine Strecke der gewählten oder einer neuen Reise (eigene Kopie, ohne Eintrag im
   * Tour-Archiv). Danach öffnet sich die Reise. Wie beim Angebot wird eine veraltete Berechnung erst neu gerechnet.
   */
  async addToJourney() {
    if (this.quoting() || this.calculating() || this.addingToJourney()) return;
    if (this.stale()) {
      if (!this.canCalc()) return;
      const ok = await this.confirm.ask({
        title: 'Strecke neu berechnen?',
        message: 'Sie haben die Eingabe geändert, das Ergebnis gehört noch zum alten Stand. Jetzt neu berechnen und danach zur Reise hinzufügen?',
        confirmLabel: 'Neu berechnen und hinzufügen',
      });
      if (!ok) return;
      this.quoteError.set(null);
      this.calculate(() => void this.addToJourney());
      return;
    }
    const r = this.result();
    if (!r) return;
    if (!this.vehicleType()) {
      this.vehicleTypeMissing.set(true);
      this.quoteError.set('Bitte bei „Fahrt“ einen Fahrzeugtyp wählen: Er bestimmt die Preise.');
      this.focusFirstError();
      return;
    }

    const body: SectionBody = {
      kind: this.targetKind(),
      tour: this.buildTourPayload(r),
      start_on: this.startDay(r),
      surcharge_percent: this.surcharge() || 0,
      vehicles: [{ type: this.vehicleType(), count: this.vehicleCount() }],
    };
    this.addingToJourney.set(true);
    this.quoteError.set(null);

    const target$: Observable<string> =
      this.targetChoice() === 'new'
        ? this.journeyApi.create(this.tripTitle().trim() || tourTitle(r.legs, this.stops()) || 'Reise', null).pipe(map((j) => j.id))
        : of(this.targetChoice());
    target$.pipe(switchMap((id) => this.journeyApi.addSection(id, body).pipe(map(() => id)))).subscribe({
      next: (id) => {
        this.addingToJourney.set(false);
        this.leaving = true;
        this.router.navigate(['/reisen', id]);
      },
      error: (e: HttpErrorResponse) => {
        this.addingToJourney.set(false);
        this.quoteError.set(httpErrorText(e, 'Die Strecke konnte nicht zur Reise hinzugefügt werden.'));
        this.scrollToMessages();
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
  canStore = computed(() => {
    const depotLegs = this.depots().some((d) => d.id === this.depotChoice()) ? Number(this.fromDepot()) + Number(this.returnToDepot() || this.roundTrip()) : 0;
    return this.stops().filter((s) => s.key !== -1).length + this.pending().length >= 2 || depotLegs > 0;
  });
  /** „Als Vorlage speichern“ ist nicht nutzbar: es läuft etwas, das Archiv nimmt die Tour nicht, oder die Strecke lässt sich nicht berechnen. */
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
        const edit = this.section();
        if (edit) this.useAsTemplate(edit, trip);
      },
      error: (err: HttpErrorResponse) => {
        this.loadingTrip.set(false);
        this.archiveError.set(httpErrorText(err, 'Die Tour konnte nicht geladen werden.'));
      },
    });
  }

  /** Eine Tour als Vorlage für die Strecke, die gerade geändert wird: Die Tour im Archiv bleibt davon unberührt. */
  private useAsTemplate(edit: SectionEdit, trip: Trip) {
    this.currentTrip.set(null);
    if (!edit.title().trim()) edit.title.set(trip.title);
    this.tripTitle.set(edit.title());
    this.rememberTitleBasis();
    if (trip.vehicle_type && edit.vehicles().every((v) => !v.type)) edit.vehicles.set([{ type: trip.vehicle_type, count: trip.vehicle_count }]);
    edit.routeChanged.set(true);
    this.stale.set(false);
    this.tripLoadedMessage.set(`Die Vorlage „${trip.title}“ ist übernommen. Mit „Speichern“ gilt sie für diese Strecke.`);
    if (this.result()) void this.takeSectionRoute(edit, trip);
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
    this.fromDepot.set(trip.from_depot ?? true);
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
        this.tripMessage.set(`Die Vorlage „${trip.title}“ ist im Tour-Archiv gespeichert. ${this.sectionId() ? 'Für die Reise speichern Sie mit „Speichern“.' : 'Sie finden sie später über „Vorlage laden“.'}`);
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
      from_depot: this.fromDepot(),
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
    if (this.routeMissing() && !w.place && !this.textOf(w).trim()) {
      if (w.key === this.start().key) return 'Bitte „Von“ eingeben.';
      if (w.key === this.end().key) return 'Bitte „Nach“ eingeben.';
    }
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
  canCalc = computed(() => canCalculate(this.stops().length + this.pending().length, !!this.depot() || this.depotPending(), this.returnsToDepot(), this.fromDepot()) && !this.calculating());
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
    // Der Standard-Betriebshof ist vorgewählt, nur nicht bei einer „Fahrt vor Ort“ einer Reise: Der Bus ist dann schon dort.
    // Der Ort eines Betriebshofs steht schon fest, beim Berechnen wird nichts gesucht.
    const query = this.route.snapshot.queryParamMap;
    const onSite = !!query.get('journey') && query.get('kind') === 'vor_ort';
    const journey = query.get('journey');
    const section = journey ? query.get('section') : null;
    this.sectionId.set(journey ? (section ?? 'new') : null);
    const open = () => {
      if (section) this.openSection(journey!, section);
      else if (journey) this.openNewSection(journey);
      else this.openLinkedTrip();
    };
    this.depotApi.list().subscribe({
      next: (list) => {
        this.depots.set(list.filter((d) => d.place));
        const standard = this.depots().find((d) => d.is_default);
        if (standard && !onSite && !section && this.depotChoice() === '') this.chooseDepot(standard.id);
        open();
      },
      error: (err) => {
        this.depotsError.set(httpErrorText(err, 'Die Betriebshöfe konnten nicht geladen werden.'));
        // Ohne Betriebshöfe ginge beim Speichern einer vorhandenen Strecke ihr Betriebshof verloren: dann nicht öffnen.
        if (!section) open();
      },
    });
    // Laufende Reisen für „Zur Reise hinzufügen“ (die neuesten); ohne sie bleibt „Neue Reise“
    this.journeyApi.list('', 1, 'laufende').subscribe({ next: (p) => this.journeys.set(p.data), error: () => this.journeys.set([]) });
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

  /** Der Fahrtweg in Worten, zum Beispiel „Von → Nach → Betriebshof“: So prüft man die Wahl auf einen Blick. */
  depotPath = computed(() => {
    if (this.depotChoice() === '') return 'Von → Nach';
    const parts = [this.fromDepot() ? 'Betriebshof' : '', 'Von', 'Nach', this.roundTrip() ? 'Von' : '', this.returnToDepot() || this.roundTrip() ? 'Betriebshof' : ''];
    return parts.filter(Boolean).join(' → ');
  });

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
    // Anfahrt und Rückfahrt zum Betriebshof wie bei einer neuen Planung; der gewählte Betriebshof bleibt.
    this.fromDepot.set(true);
    this.returnToDepot.set(false);
    this.routeMissing.set(false);
    this.vehicleTypeMissing.set(false);
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
    this.sectionId() ? !!this.section()?.dirty() : this.currentTrip() ? this.tripDirty() : Object.values(this.texts()).some((t) => t.trim() !== '') || !!this.result(),
  );

  /**
   * Die Seite wird verlassen (anderer Menüpunkt, Zurück-Taste): Gibt es Ungespeichertes, fragt ein Dialog vorher. Nach dem Anlegen eines
   * Angebots wird nicht gefragt, die Strecke steckt dann im Angebot.
   */
  canLeave(): boolean | Promise<boolean> {
    if (this.leaving || !this.unsavedInput()) return true;
    return this.confirm.ask({
      title: 'Seite verlassen?',
      message: this.sectionId()
        ? 'Die Strecke ist noch nicht in der Reise gespeichert. Wenn Sie die Seite verlassen, gehen Ihre Eingaben verloren.'
        : 'Ihre Eingabe ist noch nicht im Tour-Archiv gespeichert. Wenn Sie die Seite verlassen, geht sie verloren.',
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
    this.section()?.routeChanged.set(true);
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
      fromDepot: this.fromDepot(),
      vehicleId: this.vehicleId(),
      startAt: this.section()?.startAt() ?? this.startAt(),
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
        const edit = this.section();
        if (edit) void this.takeSectionRoute(edit, this.figuresOf(r)).then(() => after?.());
        else after?.();
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
