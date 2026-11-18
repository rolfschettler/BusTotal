import { HttpErrorResponse } from '@angular/common/http';
import { MSG_FORBIDDEN, MSG_SERVER_ERROR, MSG_TOO_MANY, MSG_UNREACHABLE, fieldErrors, httpErrorText, isUnreachable } from './http-error';

const err = (status: number, error: unknown = null) => new HttpErrorResponse({ status, error });

describe('httpErrorText', () => {
  it('keine Verbindung ist kein Eingabefehler', () => {
    expect(httpErrorText(err(0), 'Anmeldung fehlgeschlagen.')).toBe(MSG_UNREACHABLE);
  });

  it('Gateway-Fehler und Serverfehler ohne eigene Meldung gelten als nicht erreichbar (Proxy, Hoster)', () => {
    for (const s of [502, 503, 504]) expect(isUnreachable(err(s, '<html>'))).toBeTrue();
    expect(isUnreachable(err(500, 'Error occurred while trying to proxy'))).toBeTrue();
    expect(isUnreachable(err(500, ''))).toBeTrue();
    expect(httpErrorText(err(500, ''), 'x')).toBe(MSG_UNREACHABLE);
  });

  it('ein Serverfehler mit Meldung des Servers ist ein Serverfehler, nicht „nicht erreichbar“', () => {
    expect(isUnreachable(err(500, { message: 'Serverfehler' }))).toBeFalse();
    expect(httpErrorText(err(500, { message: 'Serverfehler' }), 'x')).toBe(MSG_SERVER_ERROR);
  });

  it('zu viele Versuche', () => {
    expect(httpErrorText(err(429, { message: 'Too Many Attempts.' }), 'x')).toBe(MSG_TOO_MANY);
  });

  it('keine Berechtigung, mit eigener Meldung falls vorhanden', () => {
    expect(httpErrorText(err(403), 'x')).toBe(MSG_FORBIDDEN);
    expect(httpErrorText(err(403, { message: 'Nur für Administratoren.' }), 'x')).toBe('Nur für Administratoren.');
  });

  it('Eingabefehler: Meldung des Servers, sonst erster Feldfehler, sonst Ersatztext', () => {
    expect(httpErrorText(err(422, { message: 'Die Anmeldedaten sind ungültig.' }), 'x')).toBe('Die Anmeldedaten sind ungültig.');
    expect(httpErrorText(err(422, { errors: { email: ['Ungültig.'] } }), 'x')).toBe('Ungültig.');
    expect(httpErrorText(err(422, {}), 'Speichern fehlgeschlagen.')).toBe('Speichern fehlgeschlagen.');
    expect(httpErrorText(null, 'Fallback')).toBe('Fallback');
  });

  it('Feldfehler: je Feld der erste Text, auch bei unerwarteter Antwort', () => {
    expect(fieldErrors(err(422, { errors: { a: ['x', 'y'], b: ['z'] } }))).toEqual({ a: 'x', b: 'z' });
    expect(fieldErrors(err(500, 'text'))).toEqual({});
    expect(fieldErrors(null)).toEqual({});
  });
});
