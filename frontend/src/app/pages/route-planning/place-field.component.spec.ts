import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { PlaceFieldComponent } from './place-field.component';

describe('PlaceFieldComponent: gefundener Ort', () => {
  let fixture: ComponentFixture<PlaceFieldComponent>;

  function show(text: string, note: string): PlaceFieldComponent {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    fixture = TestBed.createComponent(PlaceFieldComponent);
    fixture.componentRef.setInput('label', 'Von');
    fixture.componentRef.setInput('inputId', 'von');
    fixture.componentRef.setInput('text', text);
    fixture.componentRef.setInput('note', note);
    fixture.detectChanges();
    return fixture.componentInstance;
  }

  it('ein Treffer, der zur Eingabe passt, steht ruhig mit „Gefundener Ort“', () => {
    const c = show('Germanenstraße 4, 89250 Senden', 'Germanenstraße 4, Senden');
    expect(c.plausible()).toBe(true);
    const box = (fixture.nativeElement as HTMLElement).querySelector('[role="status"]')!;
    expect(box.textContent).toContain('Gefundener Ort: Germanenstraße 4, Senden');
    expect(box.textContent).not.toContain('Bitte prüfen');
  });

  it('ein Ratetreffer, der nicht zur Eingabe passt, warnt', () => {
    const c = show('Germanenstraße 4, 89250 Senden-Ay', 'Irrawaddy, Myanmar');
    expect(c.plausible()).toBe(false);
    const box = (fixture.nativeElement as HTMLElement).querySelector('[role="status"]')!;
    expect(box.textContent).toContain('Gefundener Ort: Irrawaddy, Myanmar');
    expect(box.textContent).toContain('Bitte prüfen');
    expect(box.className).toContain('bg-warning-subtle');
  });

  it('abweichende Schreibweise desselben Ortes gilt als passend', () => {
    expect(show('Ulm Hbf', 'Ulm Hauptbahnhof, Ulm').plausible()).toBe(true);
  });

  it('Ort mit Zusatz in der Eingabe gilt als passend', () => {
    expect(show('Sprungweg 11,Sattendorf,AT', 'Sattendorf, Bodensdorf').plausible()).toBe(true);
  });

  it('ohne gefundenen Ort gibt es keinen Hinweis', () => {
    show('Ulm', '');
    expect((fixture.nativeElement as HTMLElement).querySelector('[role="status"]')).toBeNull();
  });

  it('Anfragen sparen: Dieselbe Suche in der Trefferliste wird nur einmal angefragt', () => {
    const c = show('Sattendorf', 'Sattendorf, KA');
    const http = TestBed.inject(HttpTestingController);

    c.toggle();   // öffnen: eine Anfrage
    http.expectOne((r) => r.url === '/api/geocode').flush({ data: [] });
    c.toggle();   // schließen
    c.toggle();   // wieder öffnen, derselbe Text
    c.search();   // und „Suchen“ mit demselben Text
    http.expectNone(() => true);

    c.query.set('Sattendorf Bahnhof');
    c.search();   // anderer Suchtext: neue Anfrage
    http.expectOne((r) => r.url === '/api/geocode' && r.params.get('q') === 'Sattendorf Bahnhof').flush({ data: [] });
  });
});
