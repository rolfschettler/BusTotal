import { JourneyDetail } from '../../core/journey.service';
import { LineStep } from '../../shared/route-line.component';

type LineData = Pick<JourneyDetail, 'sections' | 'documents' | 'trip_order_list' | 'state'>;

const LABELS = ['Strecken', 'Angebot', 'Auftrag', 'Fahrt', 'Rechnung bezahlt'];

/**
 * Wo die Reise steht: je Haltestelle „erledigt“, „hier“ oder „offen“. Stornierte Belege und abgesagte Fahrten zählen nicht.
 * Ist eine spätere Haltestelle erreicht, gelten die früheren als erledigt. Eine abgesagte Reise hat keine aktuelle Haltestelle.
 */
export function journeyLine(j: LineData): LineStep[] {
  const valid = j.documents.filter((d) => !d.cancelled && d.status === 'festgeschrieben');
  const has = (type: string) => valid.some((d) => d.type === type);
  const trips = j.trip_order_list.filter((t) => !t.cancelled_at);
  const invoices = valid.filter((d) => d.type === 'rechnung');

  const reached = [
    j.sections.length > 0,
    has('angebot'),
    has('auftragsbestaetigung'),
    trips.length > 0 && trips.every((t) => t.status === 'erledigt'),
    invoices.length > 0 && invoices.every((d) => d.payment_state === 'bezahlt'),
  ];
  const done = j.state === 'abgeschlossen' ? LABELS.length - 1 : reached.lastIndexOf(true);

  return LABELS.map((label, i) => ({
    label,
    state: i <= done ? 'done' : i === done + 1 && j.state === 'laufend' ? 'current' : 'open',
  }));
}
