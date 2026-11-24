import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { HttpTestingController } from '@angular/common/http/testing';
import { of } from 'rxjs';
import { ConfirmService } from '../../core/confirm.service';
import { RouteResult, RouteService } from '../../core/route.service';
import { Trip } from '../../core/trip.service';
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
