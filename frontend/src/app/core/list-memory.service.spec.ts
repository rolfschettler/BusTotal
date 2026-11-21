import { ListMemory } from './list-memory.service';

describe('ListMemory', () => {
  it('merkt sich Zeile und Einstellungen je Liste', () => {
    const m = new ListMemory();
    m.select('belege', 'a');
    m.saveParams('belege', { page: 3 });
    expect(m.current('belege')).toBe('a');
    expect(m.params('belege')).toEqual({ page: 3 });
    expect(m.current('kunden')).toBeNull();
    expect(m.params('kunden')).toBeNull();
  });

  it('follow auf dieselbe Zeile behält die Einstellungen', () => {
    const m = new ListMemory();
    m.select('belege', 'a');
    m.saveParams('belege', { page: 3 });
    m.follow('belege', 'a');
    expect(m.params('belege')).toEqual({ page: 3 });
  });

  it('follow auf einen anderen Datensatz (Folgebeleg) setzt die Liste zurück und markiert ihn', () => {
    const m = new ListMemory();
    m.select('belege', 'angebot');
    m.saveParams('belege', { page: 3, q: 'x' });
    m.follow('belege', 'auftrag');
    expect(m.current('belege')).toBe('auftrag');
    expect(m.params('belege')).toBeNull();
  });

  it('forget löscht die Markierung nur für den genannten Datensatz', () => {
    const m = new ListMemory();
    m.select('belege', 'a');
    m.forget('belege', 'b');
    expect(m.current('belege')).toBe('a');
    m.forget('belege', 'a');
    expect(m.current('belege')).toBeNull();
  });

  it('follow mit keepParams behält Suche, Ansicht und Seite und verschiebt nur die Markierung', () => {
    const m = new ListMemory();
    m.select('belege', 'auftrag');
    m.saveParams('belege', { q: 'mainz', order: 'chain', page: 2 });
    m.follow('belege', 'rechnung', true);
    expect(m.current('belege')).toBe('rechnung');
    expect(m.params('belege')).toEqual({ q: 'mainz', order: 'chain', page: 2 });
  });
});
