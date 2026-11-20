import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Located, RouteService, locateKey } from './route.service';

const CACHE = 'bustotal.planung.orte';

describe('RouteService.locate', () => {
  let api: RouteService;
  let http: HttpTestingController;

  function setup() {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    api = TestBed.inject(RouteService);
    http = TestBed.inject(HttpTestingController);
  }

  beforeEach(() => localStorage.removeItem(CACHE));
  afterEach(() => {
    http?.verify();
    localStorage.removeItem(CACHE);
  });

  it('gleiche Eingaben ergeben denselben Schlüssel', () => {
    expect(locateKey('  Ulm  Hbf, ')).toBe('ulm hbf');
    expect(locateKey('88400 Biberach.')).toBe('88400 biberach');
  });

  it('derselbe Text wird nur einmal gesucht, auch anders geschrieben', () => {
    setup();
    let first: Located | null = null;
    api.locate('88400 Biberach').subscribe((p) => (first = p));
    http.expectOne((r) => r.url === '/api/geocode' && r.params.get('q') === '88400 Biberach' && r.params.get('limit') === '1')
      .flush({ data: [{ label: 'Biberach an der Riß, BW, Deutschland', lat: 48.1, lon: 9.79, postal_code: null, city: null, country: null }] });
    expect(first!.lat).toBe(48.1);

    let again: Located | null = null;
    api.locate(' 88400  biberach ').subscribe((p) => (again = p));
    http.expectNone('/api/geocode');
    expect(again!).toEqual(first!);
  });

  it('der Zwischenspeicher überlebt das Neuladen der Seite', () => {
    localStorage.setItem(CACHE, JSON.stringify([['ulm', { label: 'Ulm', lat: 48.4, lon: 9.99 }]]));
    setup();
    let p: Located | null = null;
    api.locate('Ulm').subscribe((x) => (p = x));
    http.expectNone('/api/geocode');
    expect(p!.label).toBe('Ulm');
  });

  it('"nichts gefunden" wird nicht gemerkt', () => {
    setup();
    api.locate('Xyzzy').subscribe((p) => expect(p).toBeNull());
    http.expectOne(() => true).flush({ data: [] });
    api.locate('Xyzzy').subscribe();
    http.expectOne(() => true).flush({ data: [] });
  });

  it('kaputter Speicherinhalt wird ignoriert', () => {
    localStorage.setItem(CACHE, '{kaputt');
    setup();
    let p: Located | null | undefined;
    api.locate('Ulm').subscribe((x) => (p = x));
    http.expectOne(() => true).flush({ data: [] });
    expect(p).toBeNull();
  });
});
