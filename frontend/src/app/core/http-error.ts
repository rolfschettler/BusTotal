import { HttpErrorResponse } from '@angular/common/http';

/**
 * Ein Text für jeden Fehler einer Server-Anfrage. Die Meldung sagt, was wirklich los ist, damit niemand am falschen Ort sucht:
 * Kommt gar keine Antwort vom Server, ist das KEIN Eingabefehler (zum Beispiel „Passwort falsch“).
 *
 * Alle Seiten benutzen diese Funktion, damit dieselbe Ursache überall denselben Text bekommt.
 */

export const MSG_UNREACHABLE =
  'Der Server ist nicht erreichbar. Bitte die Verbindung prüfen und gleich noch einmal versuchen. Besteht das Problem weiter, den Betreiber informieren.';
export const MSG_TOO_MANY = 'Zu viele Versuche. Bitte eine Minute warten.';
export const MSG_FORBIDDEN = 'Keine Berechtigung.';
export const MSG_SERVER_ERROR = 'Auf dem Server ist ein Fehler aufgetreten. Bitte später noch einmal versuchen. Besteht das Problem weiter, den Betreiber informieren.';

/** Antwortet der Server überhaupt? Nein bei: keine Verbindung (0), Gateway-Fehler, oder Serverfehler ohne eigene Meldung (Proxy). */
export function isUnreachable(err: HttpErrorResponse | null | undefined): boolean {
  if (!err) return false;
  if (err.status === 0 || err.status === 502 || err.status === 503 || err.status === 504) return true;
  // Unser Server antwortet immer mit JSON und "message". Ein Serverfehler ohne das stammt von einem Vermittler (Proxy, Hoster).
  return err.status >= 500 && !serverMessage(err);
}

/** Die Meldung, die der Server mitschickt (deutsch, von uns geschrieben), oder null. */
export function serverMessage(err: HttpErrorResponse | null | undefined): string | null {
  const body = err?.error;
  return body && typeof body === 'object' && typeof body.message === 'string' && body.message !== '' ? body.message : null;
}

/** Fehlermeldungen je Feld (Laravel-Format), jeweils die erste. */
export function fieldErrors(err: HttpErrorResponse | null | undefined): Record<string, string> {
  const errors: Record<string, string[]> = err?.error && typeof err.error === 'object' ? (err.error.errors ?? {}) : {};
  return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]));
}

/**
 * @param fallback Text, wenn der Server nichts Genaueres sagt (z. B. „Speichern fehlgeschlagen.“).
 */
export function httpErrorText(err: HttpErrorResponse | null | undefined, fallback: string): string {
  if (!err) return fallback;
  if (isUnreachable(err)) return MSG_UNREACHABLE;
  if (err.status === 429) return MSG_TOO_MANY;
  if (err.status === 403) return serverMessage(err) ?? MSG_FORBIDDEN;
  if (err.status >= 500) return MSG_SERVER_ERROR;
  return serverMessage(err) ?? Object.values(fieldErrors(err))[0] ?? fallback;
}
