import { DocumentJourney, PaymentSummary } from '../../core/document.service';
import { LineStep } from '../../shared/route-line.component';
import { journeyLine } from '../journeys/journey-line';

/**
 * Der Linienplan der Reise, wie ihn der Beleg zeigt. Der Beleg kennt die Fahrtaufträge nur als Zähler und den Zahlstand nur
 * von sich selbst: Eine Rechnung gilt deshalb nur dann als bezahlt, wenn es die angezeigte ist (oder die Reise abgeschlossen ist).
 */
export function documentLine(journey: DocumentJourney, currentId: string, payment: PaymentSummary | null | undefined): LineStep[] {
  const o = journey.trip_orders;
  const trips = (count: number | undefined, status: 'erledigt' | 'ausgegeben') => Array.from({ length: count ?? 0 }, () => ({ status, cancelled_at: null }));
  return journeyLine({
    sections: journey.sections,
    documents: journey.documents.map((d) => ({ ...d, payment_state: d.id === currentId ? payment?.state : null })),
    trip_order_list: [...trips(o?.done, 'erledigt'), ...trips((o?.to_plan ?? 0) + (o?.planned ?? 0) + (o?.issued ?? 0), 'ausgegeben')],
    state: journey.state ?? 'laufend',
  } as unknown as Parameters<typeof journeyLine>[0]);
}
