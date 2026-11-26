import { RouteLeg, RouteResult, ScheduleEvent } from '../../core/route.service';
import { Waypoint, buildStops, canCalculate, cleanMinutes, eventTitle, formatDuration, formatKm, formatOffset, moveItem, PlannedStop, restoredResult, shortLabel, storableRoute, toRequest, tourTitle } from './route-planning-view';

const stop = (key: number, extra: Partial<PlannedStop> = {}): PlannedStop => ({ key, label: `Halt ${key}`, lat: 48 + key, lon: 10 + key, dwell_minutes: 0, driver_free: false, ...extra });

const legs: RouteLeg[] = [
  { from: { label: 'Hof', lat: 1, lon: 1 }, to: { label: 'Ulm', lat: 2, lon: 2 }, distance_m: 1000, duration_s: 600, empty: true },
  { from: { label: 'Ulm', lat: 2, lon: 2 }, to: { label: 'Bonn', lat: 3, lon: 3 }, distance_m: 2000, duration_s: 1200, empty: false },
];

describe('Streckenplanung: Anzeige', () => {
  it('formatDuration', () => {
    expect(formatDuration(0)).toBe('0 Min.');
    expect(formatDuration(45 * 60)).toBe('45 Min.');
    expect(formatDuration(3600)).toBe('1 Std.');
    expect(formatDuration(3660)).toBe('1 Std. 1 Min.');
    expect(formatDuration(26 * 3600 + 15 * 60)).toBe('26 Std. 15 Min.');
    expect(formatDuration(89)).toBe('1 Min.'); // gerundet
    expect(formatDuration(-5)).toBe('0 Min.');
  });

  it('formatKm: deutsche Schreibweise', () => {
    expect(formatKm(0)).toBe('0 km');
    expect(formatKm(1234500)).toBe('1.234,5 km');
    expect(formatKm(999)).toBe('1 km');
    expect(formatKm(12349)).toBe('12,3 km');
  });

  it('formatOffset', () => {
    expect(formatOffset(0)).toBe('+0:00');
    expect(formatOffset(16200)).toBe('+4:30');
    expect(formatOffset(13 * 3600 + 45 * 60 + 59)).toBe('+13:45');
    expect(formatOffset(-10)).toBe('+0:00');
  });

  it('eventTitle', () => {
    const base = { start_s: 0, duration_s: 1, day: 1 };
    expect(eventTitle({ ...base, type: 'drive', leg: 0 }, legs)).toBe('Fahrt: Hof → Ulm (Leerfahrt)');
    expect(eventTitle({ ...base, type: 'drive', leg: 1 }, legs)).toBe('Fahrt: Ulm → Bonn');
    expect(eventTitle({ ...base, type: 'break', leg: null }, legs)).toBe('Pause');
    expect(eventTitle({ ...base, type: 'rest', leg: null }, legs)).toBe('Tagesruhezeit');
    expect(eventTitle({ ...base, type: 'stop', leg: 0 }, legs)).toBe('Halt: Ulm');
    expect(eventTitle({ ...base, type: 'stop', leg: 0, driver_free: true }, legs)).toBe('Halt: Ulm (Fahrer frei)');
    expect(eventTitle({ ...base, type: 'stop', leg: null }, legs)).toBe('Arbeit am Start (Hof)');
  });

  it('eventTitle: unbekannter Abschnitt bricht nicht', () => {
    const e: ScheduleEvent = { type: 'drive', leg: 9, start_s: 0, duration_s: 1, day: 1 };
    expect(eventTitle(e, legs)).toBe('Fahrt');
  });
});

describe('Streckenplanung: Bezeichnungen', () => {
  it('shortLabel kürzt auf die ersten zwei verschiedenen Teile', () => {
    expect(shortLabel('Ulm Hauptbahnhof, Ulm, Baden-Württemberg, Deutschland')).toBe('Ulm Hauptbahnhof, Ulm');
    expect(shortLabel('Ulm, Ulm, Baden-Württemberg, Deutschland')).toBe('Ulm, Baden-Württemberg');
    expect(shortLabel('Ulm')).toBe('Ulm');
    expect(shortLabel('  Ulm ,  Alb-Donau-Kreis ')).toBe('Ulm, Alb-Donau-Kreis');
    expect(shortLabel(',, ,')).toBe(',, ,');
    expect(shortLabel('')).toBe('');
  });
});

describe('Streckenplanung: Von – Über – Nach', () => {
  const wp = (key: number, label: string | null, extra: Partial<Waypoint> = {}): Waypoint => ({
    key,
    place: label ? { label, lat: key, lon: key * 2 } : null,
    dwell_minutes: 0,
    driver_free: false,
    ...extra,
  });

  it('Reihenfolge: Von, Über, Nach', () => {
    const stops = buildStops(wp(1, 'Ansbach'), [wp(2, 'Ulm')], wp(3, 'München'), false);
    expect(stops.map((s) => s.label)).toEqual(['Ansbach', 'Ulm', 'München']);
    expect(stops[1]).toEqual({ key: 2, label: 'Ulm', lat: 2, lon: 4, dwell_minutes: 0, driver_free: false, movable: true });
  });

  it('leere Felder fehlen, auch leere Über-Zeilen', () => {
    expect(buildStops(wp(1, 'A'), [wp(2, null), wp(3, 'B')], wp(4, null), false).map((s) => s.label)).toEqual(['A', 'B']);
    expect(buildStops(wp(1, null), [], wp(2, 'Z'), false).map((s) => s.label)).toEqual(['Z']);
    expect(buildStops(wp(1, null), [], wp(2, null), false)).toEqual([]);
  });

  it('Rückfahrt hängt den Start ans Ende, ohne Aufenthalt', () => {
    const stops = buildStops(wp(1, 'A', { dwell_minutes: 30, driver_free: true }), [], wp(2, 'Z', { dwell_minutes: 60 }), true);
    expect(stops.map((s) => s.label)).toEqual(['A', 'Z', 'A']);
    expect(stops[0].dwell_minutes).toBe(30);
    expect(stops[2]).toEqual({ key: -1, label: 'A', lat: 1, lon: 2, dwell_minutes: 0, driver_free: false });
  });

  it('Rückfahrt nur, wenn es einen weiteren Ort gibt', () => {
    expect(buildStops(wp(1, 'A'), [], wp(2, null), true).map((s) => s.label)).toEqual(['A']);
    expect(buildStops(wp(1, null), [], wp(2, 'Z'), true).map((s) => s.label)).toEqual(['Z']);
  });

  it('Aufenthalt wird bereinigt', () => {
    const stops = buildStops(wp(1, 'A', { dwell_minutes: 99999 }), [], wp(2, 'Z', { dwell_minutes: NaN }), false);
    expect(stops.map((s) => s.dwell_minutes)).toEqual([1440, 0]);
  });

  it('ergibt zusammen mit canCalculate: Von und Nach genügen', () => {
    expect(canCalculate(buildStops(wp(1, 'A'), [], wp(2, null), false).length, false, true)).toBeFalse();
    expect(canCalculate(buildStops(wp(1, 'A'), [], wp(2, 'Z'), false).length, false, true)).toBeTrue();
  });
});

describe('Streckenplanung: Eingabe', () => {
  it('moveItem verschiebt und lässt die Eingabe unverändert', () => {
    const list = ['a', 'b', 'c'];
    expect(moveItem(list, 1, -1)).toEqual(['b', 'a', 'c']);
    expect(moveItem(list, 1, 1)).toEqual(['a', 'c', 'b']);
    expect(list).toEqual(['a', 'b', 'c']);
  });

  it('moveItem: an den Rändern und außerhalb nichts', () => {
    expect(moveItem(['a', 'b'], 0, -1)).toEqual(['a', 'b']);
    expect(moveItem(['a', 'b'], 1, 1)).toEqual(['a', 'b']);
    expect(moveItem(['a', 'b'], 5, -1)).toEqual(['a', 'b']);
    expect(moveItem([], 0, 1)).toEqual([]);
  });

  it('canCalculate wie auf dem Server', () => {
    expect(canCalculate(0, false, true)).toBeFalse();
    expect(canCalculate(1, false, true)).toBeFalse();
    expect(canCalculate(2, false, true)).toBeTrue();
    expect(canCalculate(1, true, true)).toBeTrue(); // Hof → Halt → Hof
    expect(canCalculate(1, true, false)).toBeTrue(); // Hof → Halt
    expect(canCalculate(0, true, true)).toBeTrue(); // Hof → Hof (der Server rechnet das, Sinn hat es nicht)
    expect(canCalculate(0, true, false)).toBeFalse();
  });

  it('cleanMinutes', () => {
    expect(cleanMinutes(30)).toBe(30);
    expect(cleanMinutes('45')).toBe(45);
    expect(cleanMinutes(12.6)).toBe(13);
    expect(cleanMinutes(-5)).toBe(0);
    expect(cleanMinutes(99999)).toBe(1440);
    expect(cleanMinutes('abc')).toBe(0);
    expect(cleanMinutes(null)).toBe(0);
    expect(cleanMinutes(undefined)).toBe(0);
  });
});

describe('Streckenplanung: Anfrage', () => {
  const base = { stops: [stop(1, { dwell_minutes: 30, driver_free: true }), stop(2)], depot: null, returnToDepot: true, vehicleId: '', startAt: '', extendedDays: 0 };

  it('einfache Tour ohne Betriebshof, Fahrzeug und Abfahrt', () => {
    const r = toRequest(base);
    expect(r.stops).toEqual([
      { lat: 49, lon: 11, label: 'Halt 1', dwell_minutes: 30, driver_free: true, movable: false },
      { lat: 50, lon: 12, label: 'Halt 2', dwell_minutes: 0, driver_free: false, movable: false },
    ]);
    expect(r.depot).toBeUndefined();
    expect(r.return_to_depot).toBeUndefined();
    expect(r.vehicle_id).toBeUndefined();
    expect(r.start_at).toBeUndefined();
    expect(r.extended_days).toBe(0);
  });

  it('mit Betriebshof, Fahrzeug und Abfahrt', () => {
    const r = toRequest({ ...base, depot: { label: 'Hof', lat: 49.3, lon: 10.5 }, returnToDepot: false, vehicleId: 'abc', startAt: '2027-03-01T06:00', extendedDays: 1 });
    expect(r.depot).toEqual({ lat: 49.3, lon: 10.5, label: 'Hof' });
    expect(r.return_to_depot).toBeFalse();
    expect(r.from_depot).toBeTrue();   // ohne Angabe mit Anfahrt
    expect(r.vehicle_id).toBe('abc');
    expect(r.extended_days).toBe(1);
    // Ortszeit des Browsers wird als eindeutiger Zeitpunkt gesendet
    expect(r.start_at).toBe(new Date('2027-03-01T06:00').toISOString());
  });

  it('ungültige Abfahrt wird weggelassen, Aufenthalt wird bereinigt', () => {
    const r = toRequest({ ...base, stops: [stop(1, { dwell_minutes: 99999 }), stop(2, { dwell_minutes: NaN })], startAt: 'quatsch' });
    expect(r.start_at).toBeUndefined();
    expect(r.stops.map((s) => s.dwell_minutes)).toEqual([1440, 0]);
  });
});

describe('Streckenplanung: Tour-Archiv', () => {
  const result = {
    order: [0, 1], optimized: false, distance_m: 3000, empty_distance_m: 1000, loaded_distance_m: 2000, drive_s: 1800, elapsed_s: 1800, days: 1,
    legs, schedule: [{ type: 'drive', start_s: 0, duration_s: 600, leg: 0, day: 1, start_at: '2027-03-01T05:00:00Z', end_at: '2027-03-01T05:10:00Z' }],
    summary: {}, warnings: [], geometry: [[1, 1], [2, 2]], start_at: '2027-03-01T05:00:00Z', end_at: '2027-03-01T06:00:00Z',
    vehicle: { id: 'v1', display_name: 'Bus 1', cost_per_km: 1 }, vehicle_cost: 12,
  } as unknown as RouteResult;

  it('tourTitle: Von und Nach der Fahrt mit Fahrgästen, ohne Leerfahrten und ohne Ortsteil', () => {
    expect(tourTitle(legs)).toBe('Ulm – Bonn');
    expect(tourTitle([{ ...legs[0], from: { label: 'München Hbf, München', lat: 1, lon: 1 } }])).toBe('');
    expect(tourTitle([{ ...legs[1], from: { label: 'München Hbf, München', lat: 1, lon: 1 } }])).toBe('München Hbf – Bonn');
  });

  it('tourTitle: ohne Fahrt mit Fahrgästen nennen die Halte die Tour (Betriebshof → Halt → Betriebshof)', () => {
    const onlyEmpty = [legs[0], { ...legs[0], from: legs[0].to, to: legs[0].from }];
    expect(tourTitle(onlyEmpty, [{ label: 'Ulm, Baden-Württemberg' }])).toBe('Ulm');
    expect(tourTitle(onlyEmpty, [{ label: 'Ulm' }, { label: 'Ulm' }])).toBe('Ulm');
    expect(tourTitle(onlyEmpty, [])).toBe('');
  });

  it('storableRoute: ohne Linienzug, Termin und Fahrzeug, die Eingabe bleibt unverändert', () => {
    const s = storableRoute(result);
    expect('geometry' in s).toBeFalse();
    expect(s.start_at).toBeNull();
    expect(s.end_at).toBeNull();
    expect(s.vehicle).toBeNull();
    expect(s.vehicle_cost).toBeNull();
    expect('start_at' in s.schedule[0]).toBeFalse();
    expect(s.schedule[0].duration_s).toBe(600);
    expect(result.start_at).toBe('2027-03-01T05:00:00Z');
    expect(result.schedule[0].start_at).toBeDefined();
  });

  it('restoredResult setzt die Linie wieder ein', () => {
    const back = restoredResult(storableRoute(result), [[1, 1]]);
    expect(back.geometry).toEqual([[1, 1]]);
    expect(back.legs.length).toBe(2);
    expect(restoredResult(storableRoute(result), null).geometry).toEqual([]);
  });
});
