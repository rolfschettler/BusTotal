import { fromLocalInput, toLocalInput } from './trip-order-time';

describe('Abfahrtszeit im Fahrtauftrag', () => {
  it('bleibt beim Hin- und Zurückwandeln gleich, in jeder Zeitzone des Browsers', () => {
    for (const local of ['2027-05-10T06:00', '2027-01-01T00:00', '2027-12-31T23:59']) {
      expect(toLocalInput(fromLocalInput(local))).toBe(local);
    }
  });

  it('schickt einen UTC-Zeitpunkt an den Server', () => {
    expect(fromLocalInput('2027-05-10T06:00')).toMatch(/^2027-05-10T\d{2}:00:00\.000Z$|^2027-05-0[89]T\d{2}:00:00\.000Z$/);
  });

  it('leere und ungültige Werte werden zu leer', () => {
    expect(fromLocalInput('')).toBeNull();
    expect(fromLocalInput('kein Datum')).toBeNull();
    expect(toLocalInput(null)).toBe('');
    expect(toLocalInput('kein Datum')).toBe('');
  });
});
