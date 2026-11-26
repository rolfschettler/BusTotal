import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { HttpTestingController } from '@angular/common/http/testing';
import { of } from 'rxjs';
import { ConfirmService } from '../../core/confirm.service';
import { Depot, DepotService } from '../../core/depot.service';
import { RouteResult, RouteService } from '../../core/route.service';
import { Trip } from '../../core/trip.service';
import { JourneyDetail, JourneyService, SectionEditData } from '../../core/journey.service';
import { RoutePlanningComponent } from './route-planning.component';

const trip = {
  id: 't1', title: 'Mietingen – Villach über Salzburg', trip_kind: 'gelegenheit', vehicle_type: 'reisebus', vehicle_count: 2, depot_id: null,
  return_to_depot: false, round_trip: false, loaded_km: 513, empty_km: 0, drive_s: 26700, elapsed_s: 29400, days: 1, updated_at: '',
  stops: [
    { position: 1, label: 'Mietingen, BW', dwell_minutes: 15, driver_free: false, place: { lat: 48.1, lon: 9.9 } },
    { position: 2, label: 'Schladming, ST', dwell_minutes: 30, driver_free: true, place: { lat: 47.4, lon: 13.7 } },
    { position: 3, label: 'Villach, KA', dwell_minutes: 0, driver_free: false, place: { lat: 46.6, lon: 13.8 } },
  ],
} as Trip;

function planner(): RoutePlanningComponent {
  TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])] });
  const c = TestBed.createComponent(RoutePlanningComponent).componentInstance;
  (c as unknown as { applyTrip(t: Trip): void }).applyTrip(trip);
  return c;
}

describe('Streckenplanung: vorgewählter Betriebshof', () => {
  function choiceFor(query: Record<string, string>): string {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(), provideHttpClientTesting(), provideRouter([]),
        { provide: ActivatedRoute, useValue: { snapshot: { queryParamMap: convertToParamMap(query) } } },
      ],
    });
    spyOn(TestBed.inject(DepotService), 'list').and.returnValue(of([{ id: 'd1', name: 'Hof', is_default: true, place: { lat: 48.2, lon: 9.9 } } as Depot]));
    const c = TestBed.createComponent(RoutePlanningComponent).componentInstance;
    c.ngOnInit();
    return c.depotChoice();
  }

  it('neue Planung: der Standard', () => expect(choiceFor({})).toBe('d1'));
  it('Hinfahrt einer Reise: der Standard', () => expect(choiceFor({ journey: 'j1', kind: 'hinfahrt' })).toBe('d1'));
  it('Fahrt vor Ort einer Reise: kein Betriebshof', () => expect(choiceFor({ journey: 'j1', kind: 'vor_ort' })).toBe(''));
});

describe('Streckenplanung: Strecke einer Reise ändern', () => {
  const data = {
    journey: { id: 'j1', title: 'Villach' }, id: 's1', kind: 'vor_ort', kind_label: 'Fahrt vor Ort', title: 'Ausflug', start_on: null, surcharge_percent: 0,
    vehicles: [{ type: 'reisebus', count: 1 }], figures: { loaded_km: 78.6, empty_km: 0, drive_s: 5640, days: 1 }, frozen_by: null,
    tour: { title: 'Ausflug', trip_kind: 'gelegenheit', loaded_km: 78.6, empty_km: 0, drive_s: 5640, elapsed_s: 5640, days: 1, round_trip: false, stops: [], legs: [] },
    plan: {
      depot_id: null, depot_other: null, return_to_depot: false, start_at: null, route: { legs: [], schedule: [] }, geometry: [],
      stops: [
        { label: 'Villach', lat: 46.6, lon: 13.8, dwell_minutes: 0, driver_free: false },
        { label: 'Kobarid', lat: 46.2, lon: 13.5, dwell_minutes: 0, driver_free: false },
      ],
      calculated: { loaded_km: 78.6, empty_km: 0, drive_s: 5640, days: 1 },
    },
  } as unknown as SectionEditData;

  function editor(d: SectionEditData = data): RoutePlanningComponent {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])] });
    const c = TestBed.createComponent(RoutePlanningComponent).componentInstance;
    c.sectionId.set(d.id);
    (c as unknown as { applySection(d: SectionEditData): void }).applySection(d);
    return c;
  }

  it('öffnet mit Orten und Ergebnis, ohne etwas zu suchen oder zu berechnen', () => {
    const c = editor();

    expect(c.stops().map((s) => s.label)).toEqual(['Villach', 'Kobarid']);
    expect(c.pending().length).toBe(0);
    expect(c.needsCalc()).toBe(false);
    expect(c.depotChoice()).toBe('');
    expect(c.section()!.dirty()).toBe(false);
    TestBed.inject(HttpTestingController).expectNone(() => true);
  });

  it('Werte von Hand: Speichern schickt sie ohne neue Route', () => {
    const c = editor();
    c.section()!.setValue('loaded_km', 160);
    expect(c.section()!.hasHandValues()).toBe(true);

    void c.saveSection();

    const req = TestBed.inject(HttpTestingController).expectOne('/api/journeys/j1/sections/s1');
    expect(req.request.body.figures.loaded_km).toBe(160);
    expect(req.request.body.tour).toBeUndefined();
  });

  it('Route geändert: Vor dem Speichern wird gefragt, ohne Ja geht nichts hinaus', async () => {
    const c = editor();
    const ask = spyOn(TestBed.inject(ConfirmService), 'ask').and.returnValue(Promise.resolve(false));
    c.setText(c.end().key, 'Bovec');

    await c.saveSection();

    expect(ask).toHaveBeenCalledTimes(1);
    TestBed.inject(HttpTestingController).expectNone(() => true);
  });

  it('neue Berechnung: Handwerte bleiben oder werden ersetzt', () => {
    const edit = editor().section()!;
    edit.setValue('loaded_km', 160);
    const calculated = { loaded_km: 90, empty_km: 0, drive_s: 6000, days: 1 };

    edit.takeCalculation(calculated, true);
    expect(edit.figures().loaded_km).toBe(160);
    expect(edit.figures().drive_s).toBe(6000);

    edit.takeCalculation(calculated, false);
    expect(edit.figures()).toEqual(calculated);
    expect(edit.hasHandValues()).toBe(false);
  });

  it('Strecke ohne Route: die gespeicherten Werte gelten, Orte sind freiwillig', () => {
    const c = editor({ ...data, tour: null, plan: null });

    expect(c.section()!.hasRoute()).toBe(false);
    expect(c.section()!.figures().loaded_km).toBe(78.6);
    expect(c.section()!.problem()).toBeNull();
  });
});

describe('Streckenplanung: neue Strecke einer Reise', () => {
  function adder(kind: string, sections: unknown[] = []): RoutePlanningComponent {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(), provideHttpClientTesting(), provideRouter([]),
        { provide: ActivatedRoute, useValue: { snapshot: { queryParamMap: convertToParamMap(kind ? { journey: 'j1', kind } : { journey: 'j1' }) } } },
      ],
    });
    spyOn(TestBed.inject(DepotService), 'list').and.returnValue(of([{ id: 'd1', name: 'Hof', is_default: true, place: { lat: 48.2, lon: 9.9 } } as Depot]));
    spyOn(TestBed.inject(JourneyService), 'get').and.returnValue(of({ id: 'j1', title: 'Villach', sections } as unknown as JourneyDetail));
    const c = TestBed.createComponent(RoutePlanningComponent).componentInstance;
    c.ngOnInit();
    return c;
  }
  const outward = { kind: 'hinfahrt', vehicles: [{ type: 'reisebus', count: 2 }], trip: { from: 'Laupheim', to: 'Villach' } };

  it('Rückfahrt: Art vorgeschlagen, Orte vertauscht, Fahrzeuge der Hinfahrt, noch nichts zu speichern', () => {
    const c = adder('', [outward]);

    expect(c.section()!.kind()).toBe('rueckfahrt');
    expect(c.textOf(c.start())).toBe('Villach');
    expect(c.textOf(c.end())).toBe('Laupheim');
    expect(c.section()!.vehicles()).toEqual([{ type: 'reisebus', count: 2 }]);
    expect(c.section()!.dirty()).toBe(false);
  });

  it('Art wechseln ändert den Betriebshof, aber nie eine eigene Wahl', () => {
    const c = adder('hinfahrt');
    expect(c.depotChoice()).toBe('d1');

    c.setSectionKind('vor_ort');
    expect(c.depotChoice()).toBe('');

    c.depotTouched = true;
    c.setSectionKind('hinfahrt');
    expect(c.depotChoice()).toBe('');
  });

  it('Rückfahrt: nie Anfahrt vom Betriebshof, am Ende dorthin zurück', () => {
    const c = adder('rueckfahrt', [outward]);

    expect(c.fromDepot()).toBe(false);
    expect(c.returnToDepot()).toBe(true);
    expect(c.depotPath()).toBe('Von → Nach → Betriebshof');

    c.setSectionKind('hinfahrt');
    expect(c.depotPath()).toBe('Betriebshof → Von → Nach');
  });

  it('Speichern ohne Fahrzeugtyp: Das Feld wird markiert, nichts geht hinaus', async () => {
    const c = adder('vor_ort');
    expect(c.section()!.errorOf('vehicle0')).toBe('');   // vor dem ersten Versuch keine Markierung

    await c.saveSection();

    expect(c.section()!.errorOf('vehicle0')).toContain('Fahrzeugtyp');
    TestBed.inject(HttpTestingController).expectNone((r) => r.method === 'POST');
  });

  it('ohne Route: Die eingetragenen Werte werden als neue Strecke gespeichert', async () => {
    const c = adder('vor_ort', [outward]);
    const edit = c.section()!;
    edit.title.set('Ausflug');
    edit.setValue('loaded_km', 60);
    edit.setValue('drive_s', 9000);

    await c.saveSection();

    const req = TestBed.inject(HttpTestingController).expectOne((r) => r.method === 'POST' && r.url === '/api/journeys/j1/sections');
    expect(req.request.body.own).toEqual({ title: 'Ausflug', loaded_km: 60, drive_minutes: 150, days: 1 });
    expect(req.request.body.kind).toBe('vor_ort');
  });
});

describe('Streckenplanung: Halt einer geladenen Tour ändern', () => {
  it('eine geladene Tour hat alle Orte, nichts ist zu suchen', () => {
    const c = planner();

    expect(c.pending().length).toBe(0);
    expect(c.stops().map((s) => s.label)).toEqual(['Mietingen, BW', 'Schladming, ST', 'Villach, KA']);
  });

  it('anderer Text: der alte Ort gilt nicht mehr, der neue Text wird beim Berechnen gesucht', () => {
    const c = planner();
    const via = c.via()[0];

    c.setText(via.key, 'Schladming, AT');

    expect(c.pending().map((w) => w.key)).toEqual([via.key]);
    expect(c.stops().map((s) => s.label)).toEqual(['Mietingen, BW', 'Villach, KA']);
    expect(c.canStore()).toBe(true);   // der gesuchte Halt zählt mit
    expect(c.via()[0].dwell_minutes).toBe(30);   // Aufenthalt und „Fahrer frei“ bleiben erhalten
    expect(c.via()[0].driver_free).toBe(true);
  });

  it('gleicher Text (nur anders geschrieben): der Ort bleibt', () => {
    const c = planner();
    const via = c.via()[0];

    c.setText(via.key, '  schladming, st ');

    expect(c.pending().length).toBe(0);
    expect(c.stops().length).toBe(3);
  });

  it('der getippte Text ist der Name des Halts, vom Ortsdienst kommt nur die Lage', () => {
    const c = planner();
    spyOn(TestBed.inject(RouteService), 'locate').and.callFake((text: string) =>
      of({ label: text.startsWith('Schl') ? 'Schladming, ST' : 'Villach, KA', lat: 47.4, lon: 13.7 }),
    );

    c.setText(c.via()[0].key, 'Schladming, AT');
    c.setText(c.end().key, '  Villach,   AT ');
    c.calculate();

    expect(c.stops().map((s) => s.label)).toEqual(['Mietingen, BW', 'Schladming, AT', 'Villach, AT']);
    expect(c.stops()[1].lat).toBe(47.4);   // die Lage ist die des Ortsdienstes
    expect(c.usedFor(c.via()[0])).toBe('Schladming, ST');   // Hinweis unter dem Feld zur Kontrolle der Lage
  });

  it('anderen Ort aus der Trefferliste wählen: Name bleibt der getippte Text, die Lage kommt vom Treffer', () => {
    const c = planner();
    const via = c.via()[0];
    c.setText(via.key, 'Sattendorf, AT');

    c.choosePlace(via.key, { label: 'Sattendorf, Bodensdorf, KA, Österreich', lat: 46.66285, lon: 13.9161, postal_code: null, city: null, country: 'AUT' });

    const stop = c.stops()[1];
    expect(stop.label).toBe('Sattendorf, AT');
    expect(stop.lat).toBe(46.66285);
    expect(stop.lon).toBe(13.9161);
    expect(c.coordsOf(c.via()[0])).toEqual({ lat: 46.66285, lon: 13.9161 });
    expect(c.usedFor(c.via()[0])).toBe('Sattendorf, Bodensdorf');   // Hinweis, welcher Treffer gewählt wurde
    expect(c.pending().length).toBe(0);   // nichts mehr zu suchen
    expect(c.via()[0].dwell_minutes).toBe(30);   // Aufenthalt bleibt
  });

  it('Seite verlassen: nur bei Ungespeichertem fragt ein Dialog nach, bei „Abbrechen“ bleibt man', async () => {
    const c = planner();
    expect(await c.canLeave()).toBe(true);   // frisch geladen, nichts verändert

    c.setText(c.via()[0].key, 'Radstadt');
    const ask = spyOn(TestBed.inject(ConfirmService), 'ask').and.returnValue(Promise.resolve(false));

    expect(await c.canLeave()).toBe(false);
    expect(ask).toHaveBeenCalledTimes(1);

    ask.and.returnValue(Promise.resolve(true));
    expect(await c.canLeave()).toBe(true);
  });

  it('Optimieren: Fällt die Zahl der Zwischenhalte unter zwei, wird das Kästchen zurückgesetzt', () => {
    const c = planner();
    c.addVia();
    expect(c.via().length).toBe(2);
    c.optimize.set(true);

    c.removeVia(1);

    expect(c.optimize()).toBe(false);
  });

  it('Tipp Optimieren: zählt Zwischenhalte mit Text, ein Klick hakt das Kästchen an und berechnet neu', () => {
    const c = planner();
    expect(c.viaCount()).toBe(1);
    c.addVia();
    expect(c.viaCount()).toBe(1);   // das neue Feld ist noch leer
    c.setText(c.via()[1].key, 'Radstadt');
    expect(c.viaCount()).toBe(2);

    const calculate = spyOn(c, 'calculate');
    c.optimizeNow();

    expect(c.optimize()).toBe(true);
    expect(calculate).toHaveBeenCalled();
  });

  it('Tipp Optimieren: nach dem Laden einer Tour nicht, erst nach einer Berechnung in dieser Sitzung', () => {
    const c = planner();
    expect(c.calculatedHere()).toBe(false);   // geladene Tour

    c.reset();
    expect(c.calculatedHere()).toBe(false);
  });

  it('Anfragen sparen: Ist das Ergebnis aktuell, löst Berechnen keine Anfrage aus', () => {
    const c = planner();
    const http = TestBed.inject(HttpTestingController);
    c.result.set({ legs: [], schedule: [], geometry: [] } as unknown as RouteResult);   // wie nach dem Laden einer Tour

    expect(c.needsCalc()).toBe(false);
    c.calculate();   // zum Beispiel Enter im Feld
    http.expectNone(() => true);
    expect(c.calculating()).toBe(false);

    c.setText(c.via()[0].key, 'Radstadt');   // jetzt hat sich etwas geändert
    expect(c.needsCalc()).toBe(true);
  });

  it('Rückfahrt zum Start mit Betriebshof: Der Bus fährt von dort weiter zum Betriebshof', () => {
    const c = planner();
    c.depot.set({ label: 'Betriebshof', lat: 48.2, lon: 9.9 });

    expect(c.returnsToDepot()).toBe(false);   // Tour endet am letzten Halt, kein Rückweg gewählt

    c.roundTrip.set(true);
    expect(c.returnsToDepot()).toBe(true);    // Nach → Von → Betriebshof
    expect(c.returnToDepot()).toBe(false);    // die eigene Wahl bleibt unberührt

    c.depot.set(null);
    expect(c.returnsToDepot()).toBe(false);   // ohne Betriebshof gibt es keinen Rückweg dorthin
  });

  it('Neu beginnen fragt nur bei Ungespeichertem nach', () => {
    const c = planner();
    expect(c.unsavedInput()).toBe(false);   // gerade geladen, nichts verändert

    c.setText(c.via()[0].key, 'Radstadt');
    expect(c.unsavedInput()).toBe(true);    // geladene Tour verändert

    c.reset();
    expect(c.unsavedInput()).toBe(false);   // leere Planung

    c.setText(c.start().key, 'Ulm');
    expect(c.unsavedInput()).toBe(true);    // neue, noch nicht gespeicherte Eingabe
  });

  it('Von und Nach verhalten sich genauso', () => {
    const c = planner();

    c.setText(c.start().key, 'Biberach');
    c.setText(c.end().key, 'Klagenfurt');

    expect(c.pending().length).toBe(2);
  });
});
