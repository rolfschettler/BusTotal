import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { BackNavigation } from './back-navigation.service';

describe('BackNavigation', () => {
  function setup(...urls: string[]) {
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
    const back = TestBed.inject(BackNavigation);
    const go = spyOn(TestBed.inject(Router), 'navigateByUrl').and.returnValue(Promise.resolve(true));
    urls.forEach((u) => back.visit(u));
    return { back, target: () => go.calls.mostRecent().args[0] };
  }

  it('führt von dort zurück, wo man herkam', () => {
    const { back, target } = setup('/reisen/j1', '/belege/b1');
    void back.back('/belege');
    expect(target()).toBe('/reisen/j1');
  });

  it('ohne bekannten Weg gilt die Liste', () => {
    const { back, target } = setup('/belege/b1');
    void back.back('/belege');
    expect(target()).toBe('/belege');
  });

  it('nach dem Zurückgehen geht es weiter rückwärts, nicht hin und her', () => {
    const { back, target } = setup('/reisen', '/reisen/j1', '/belege/b1', '/reisen/j1');
    void back.back('/x');
    expect(target()).toBe('/reisen');
  });

  it('überspringt leere Anlege-Formulare', () => {
    const { back, target } = setup('/kunden?q=a', '/kunden/neu', '/kunden/k1');
    void back.back('/kunden');
    expect(target()).toBe('/kunden?q=a');
  });

  it('geänderte Angaben in der Adresse sind keine neue Seite', () => {
    const { back, target } = setup('/reisen/j1', '/belege/b1', '/belege/b1?tab=2');
    void back.back('/belege');
    expect(target()).toBe('/reisen/j1');
  });
});
