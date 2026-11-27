import { TripOrder } from '../../core/trip-order.service';

/** Der Ablauf eines Fahrtauftrags, wie ihn die Leiste zeigt. */
export const TRIP_ORDER_STEPS = [
  { value: 'entwurf', label: 'Zu disponieren' },
  { value: 'disponiert', label: 'Disponiert' },
  { value: 'ausgegeben', label: 'Ausgegeben' },
  { value: 'erledigt', label: 'Erledigt' },
] as const;

type StepOrder = Pick<TripOrder, 'status' | 'number' | 'cancelled_at'>;

/** Was als Nächstes zu tun ist, in einem Satz. `missing`: auf der Rückerfassung das, was für „erledigt“ noch fehlt. */
export function nextStepText(o: StepOrder, missing: string[] | null = null): string {
  if (o.cancelled_at) return 'Die Fahrt findet nicht statt.';
  if (missing) {
    return missing.length
      ? `Tippen Sie das Blatt ab. Für „erledigt“ fehlt noch: ${missing.join(', ')}.`
      : 'Tippen Sie das Blatt ab. Wenn alles stimmt, klicken Sie auf „Fahrt erledigt“.';
  }
  switch (o.status) {
    case 'entwurf':
      return 'Wählen Sie Bus und Fahrer 1 oder einen Fremdunternehmer und speichern Sie.';
    case 'disponiert':
      // Eine Nummer ohne den Stand „ausgegeben“ heißt: Die Ausgabe wurde zurückgenommen.
      return o.number
        ? 'Die Ausgabe wurde zurückgenommen. Nach der Korrektur erneut ausgeben und den Fahrer informieren.'
        : 'Alles geplant. Geben Sie den Auftrag erst aus, wenn der Fahrer ihn bekommen soll.';
    case 'ausgegeben':
      return 'Drucken Sie den Auftrag für den Fahrer. Nach der Fahrt tragen Sie die Rückerfassung ein.';
    default:
      return 'Die Fahrt ist erledigt.';
  }
}

const day = (iso: string) => iso.split('-').reverse().join('.');

/** Text der Rückfrage vor dem Ausgeben: die Angaben zur Kontrolle und die Folgen. */
export function issueSummary(o: TripOrder): string {
  const period = o.date_from
    ? day(o.date_from) + (o.date_to && o.date_to !== o.date_from ? ` – ${day(o.date_to)}` : '')
    : 'noch kein Datum';
  const departure = o.departure_at
    ? new Date(o.departure_at).toLocaleString('de-DE', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
    : null;
  const drivers = [o.driver?.display_name, o.driver2?.display_name].filter(Boolean).join(' und ');
  const lines = [
    'Bitte prüfen:',
    `Termin: ${period}${departure ? `, Abfahrt ${departure} Uhr` : ''}`,
    o.external_company ? `Fremdunternehmer: ${o.external_company}` : `Bus: ${o.vehicle?.display_name ?? 'noch offen'}`,
    ...(drivers ? [`Fahrer: ${drivers}`] : []),
    '',
    o.number ? `• Der Auftrag behält seine Nummer ${o.number}.` : '• Der Auftrag bekommt eine Nummer.',
    '• Änderungen brauchen dann eine Begründung.',
    '• Es wird nichts verschickt: Bitte drucken und dem Fahrer geben.',
  ];
  return lines.join('\n');
}
