import { daysUntil, deadlineState, deadlineText, germanDate } from './deadline';

describe('Fristen', () => {
  const today = new Date(2027, 2, 31, 15, 30); // 31.03.2027, nachmittags

  it('zählt Kalendertage, unabhängig von der Uhrzeit', () => {
    expect(daysUntil('2027-03-31', today)).toBe(0);
    expect(daysUntil('2027-04-01', today)).toBe(1);
    expect(daysUntil('2027-03-30', today)).toBe(-1);
  });

  it('übersteht die Umstellung auf Sommerzeit', () => {
    expect(daysUntil('2027-04-30', new Date(2027, 2, 20))).toBe(41);
  });

  it('bewertet Fristen mit denselben Schwellen wie der Server', () => {
    expect(deadlineState(-1)).toBe('overdue');
    expect(deadlineState(0)).toBe('soon');
    expect(deadlineState(30)).toBe('soon');
    expect(deadlineState(31)).toBe('ok');
  });

  it('kennt Fristen mit eigener Vorwarnzeit', () => {
    expect(deadlineState(7, 7)).toBe('soon');
    expect(deadlineState(8, 7)).toBe('ok');
    expect(deadlineState(-1, 7)).toBe('overdue');
    expect(deadlineState(14, 14)).toBe('soon');
    expect(deadlineState(15, 14)).toBe('ok');
  });

  it('schreibt Fristen verständlich', () => {
    expect(deadlineText(-1)).toBe('seit 1 Tag abgelaufen');
    expect(deadlineText(-5)).toBe('seit 5 Tagen abgelaufen');
    expect(deadlineText(0)).toBe('heute fällig');
    expect(deadlineText(1)).toBe('morgen fällig');
    expect(deadlineText(12)).toBe('in 12 Tagen');
  });

  it('formatiert das Datum deutsch, leer bleibt leer', () => {
    expect(germanDate('2027-03-31')).toBe('31.03.2027');
    expect(germanDate(null)).toBe('');
  });
});
