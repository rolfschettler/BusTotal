import { ReportInput } from '../../core/trip-report.service';

type Km = number | null | undefined;

/** Unterschied zweier Kilometerstände; null, solange einer fehlt oder er nicht passt. */
export function kmBetween(from: Km, to: Km): number | null {
  return from !== null && from !== undefined && to !== null && to !== undefined && to >= from ? to - from : null;
}

/** Gefahrene Kilometer: letzte Zeile minus erste Zeile. */
export function drivenKm(times: { km: Km }[]): number | null {
  return times.length > 1 ? kmBetween(times[0].km, times[times.length - 1].km) : null;
}

/** Kurzfassung vor „Fahrt erledigt“: was eingetragen ist und was danach gilt. */
export function completeSummary(report: ReportInput, hasNumber: boolean): string {
  const driven = drivenKm(report.times);
  const count = (n: number, one: string, many: string) => `${n} ${n === 1 ? one : many}`;
  return [
    'Bitte prüfen:',
    `Gefahren: ${driven === null ? 'keine Angabe' : `${driven} km`}`,
    `Personen tatsächlich: ${report.actual_passengers || 'keine Angabe'}`,
    [
      count(report.borders.length, 'Grenzübertritt', 'Grenzübertritte'),
      count(report.extras.length, 'Zusatzleistung', 'Zusatzleistungen'),
      count(report.costs.length, 'Kostenposten', 'Kostenposten'),
    ].join(', '),
    '',
    ...(hasNumber ? [] : ['• Der Auftrag bekommt jetzt seine Nummer.']),
    '• Die Angaben sind dann gesperrt.',
    '• Ein Fehler lässt sich mit „Erledigt zurücknehmen“ korrigieren.',
  ].join('\n');
}
