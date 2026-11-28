import { journeyLine } from './journey-line';

type Data = Parameters<typeof journeyLine>[0];

describe('Linienplan der Reise', () => {
  const doc = (type: string, over: object = {}) => ({ type, status: 'festgeschrieben', cancelled: false, ...over });
  const states = (over: object) =>
    journeyLine({ sections: [], documents: [], trip_order_list: [], state: 'laufend', ...over } as unknown as Data).map((s) => s.state);

  it('beginnt bei den Strecken', () => {
    expect(states({})).toEqual(['current', 'open', 'open', 'open', 'open']);
  });

  it('rückt mit festgeschriebenen Belegen weiter, Entwürfe und Stornos zählen nicht', () => {
    const sections = [{}];
    expect(states({ sections, documents: [doc('angebot', { status: 'entwurf' })] })).toEqual(['done', 'current', 'open', 'open', 'open']);
    expect(states({ sections, documents: [doc('angebot'), doc('auftragsbestaetigung', { cancelled: true })] })).toEqual(['done', 'done', 'current', 'open', 'open']);
  });

  it('wartet auf alle Fahrten und lässt abgesagte weg', () => {
    const documents = [doc('auftragsbestaetigung')];
    const open = [{ status: 'erledigt', cancelled_at: null }, { status: 'ausgegeben', cancelled_at: null }];
    expect(states({ documents, trip_order_list: open })[3]).toBe('current');
    const finished = [{ status: 'erledigt', cancelled_at: null }, { status: 'entwurf', cancelled_at: '2026-11-01' }];
    expect(states({ documents, trip_order_list: finished })).toEqual(['done', 'done', 'done', 'done', 'current']);
  });

  it('zeigt bei einer abgesagten Reise keine aktuelle Haltestelle', () => {
    expect(states({ sections: [{}], state: 'abgesagt' })).toEqual(['done', 'open', 'open', 'open', 'open']);
  });
});
