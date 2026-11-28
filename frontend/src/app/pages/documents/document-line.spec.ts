import { DocumentJourney } from '../../core/document.service';
import { documentLine } from './document-line';

describe('Linienplan im Beleg', () => {
  const journey = (over: object) => ({ sections: [{}], documents: [], state: 'laufend', ...over }) as unknown as DocumentJourney;
  const doc = (id: string, type: string) => ({ id, type, status: 'festgeschrieben', cancelled: false });
  const states = (j: DocumentJourney, id = 'x', payment: object | null = null) => documentLine(j, id, payment as never).map((s) => s.state);

  it('zeigt nach der Auftragsbestätigung die Fahrt als aktuell, solange Aufträge offen sind', () => {
    const j = journey({
      documents: [doc('a', 'angebot'), doc('b', 'auftragsbestaetigung')],
      trip_orders: { count: 2, to_plan: 1, planned: 0, issued: 0, done: 1, cancelled: 0 },
    });
    expect(states(j)).toEqual(['done', 'done', 'done', 'current', 'open']);
  });

  it('gilt als bezahlt, wenn die angezeigte Rechnung bezahlt ist', () => {
    const j = journey({ documents: [doc('r', 'rechnung')] });
    expect(states(j, 'r', { state: 'bezahlt' })[4]).toBe('done');
    expect(states(j, 'r', { state: 'offen' })[4]).not.toBe('done');
  });
});
