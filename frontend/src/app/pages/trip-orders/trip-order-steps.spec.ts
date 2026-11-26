import { TripOrder } from '../../core/trip-order.service';
import { issueSummary, nextStepText } from './trip-order-steps';

describe('Ablauf des Fahrtauftrags', () => {
  const order = (over: Partial<TripOrder>) => ({ status: 'disponiert', number: null, cancelled_at: null, ...over }) as TripOrder;

  it('nennt je Stand den nächsten Schritt', () => {
    expect(nextStepText(order({ status: 'entwurf' }))).toContain('speichern');
    expect(nextStepText(order({}))).toContain('erst aus');
    expect(nextStepText(order({ number: 'FA-2026-00001' }))).toContain('zurückgenommen');
    expect(nextStepText(order({ status: 'ausgegeben', number: 'FA-2026-00001' }))).toContain('Drucken');
    expect(nextStepText(order({ cancelled_at: '2026-11-26T10:00:00Z' }))).toContain('findet nicht statt');
  });

  it('zeigt vor dem Ausgeben die Angaben zur Kontrolle', () => {
    const text = issueSummary(order({ date_from: '2026-12-01', date_to: '2026-12-03', external_company: 'Bus Huber' }));
    expect(text).toContain('Termin: 01.12.2026 – 03.12.2026');
    expect(text).toContain('Fremdunternehmer: Bus Huber');
    expect(text).toContain('bekommt eine Nummer');
    expect(issueSummary(order({ number: 'FA-2026-00001' }))).toContain('behält seine Nummer FA-2026-00001');
  });
});
