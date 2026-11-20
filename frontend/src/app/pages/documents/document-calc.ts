/**
 * Vorschau der Beleg-Summen im Formular. Rechnet genau wie das Backend (DocumentCalculator): in ganzen Cent,
 * Steuer je Steuersatz aus der Nettosumme, kaufmännisch gerundet. Maßgeblich ist immer das, was der Server speichert.
 */

export interface CalcLine {
  quantity: number | string | null;
  unit_price: number | string | null;
  tax_rate: number | string | null;
}

export interface CalcGroup {
  /** Steuersatz in Prozent */
  rate: number;
  net: number;
  tax: number;
}

export interface CalcResult {
  /** Netto je Position in Cent (gleiche Reihenfolge wie die Eingabe) */
  lineNet: number[];
  groups: CalcGroup[];
  net: number;
  tax: number;
  gross: number;
}

/** Ganzzahl-Division mit Rundung „halb weg von null“ (auch für negative Beträge). */
export function roundDiv(value: number, divisor: number): number {
  const half = Math.floor(divisor / 2);
  return value >= 0 ? Math.floor((value + half) / divisor) : -Math.floor((-value + half) / divisor);
}

/** Wie PHP round(): halb weg von null. */
function roundAway(x: number): number {
  return Math.sign(x) * Math.round(Math.abs(x));
}

const num = (v: number | string | null): number => {
  const n = typeof v === 'string' ? Number(v.replace(',', '.')) : (v ?? 0);
  return Number.isFinite(n) ? n : 0;
};

/** Menge (3 Nachkommastellen) × Einzelpreis (2 Nachkommastellen) in Cent. */
export function lineNetCents(quantity: number | string | null, unitPrice: number | string | null): number {
  const milli = roundAway(num(quantity) * 1000);
  const cents = roundAway(num(unitPrice) * 100);
  return roundDiv(milli * cents, 1000);
}

export function summarize(lines: CalcLine[]): CalcResult {
  const lineNet = lines.map((l) => lineNetCents(l.quantity, l.unit_price));
  const byRate = new Map<number, number>(); // Steuersatz in 1/100 % => Netto in Cent
  lines.forEach((l, i) => {
    const rate = roundAway(num(l.tax_rate) * 100);
    byRate.set(rate, (byRate.get(rate) ?? 0) + lineNet[i]);
  });

  const groups: CalcGroup[] = [...byRate.entries()]
    .sort((a, b) => b[0] - a[0])
    .map(([rate, net]) => ({ rate: rate / 100, net, tax: roundDiv(net * rate, 10000) }));

  const net = groups.reduce((s, g) => s + g.net, 0);
  const tax = groups.reduce((s, g) => s + g.tax, 0);
  return { lineNet, groups, net, tax, gross: net + tax };
}

/** 123456 → "1.234,56 €" */
export function formatCents(cents: number): string {
  return new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(cents / 100);
}

/** Server liefert Beträge als Text ("1234.50"). */
export function formatAmount(value: string | number | null | undefined): string {
  return formatCents(roundAway(num(value ?? 0) * 100));
}
