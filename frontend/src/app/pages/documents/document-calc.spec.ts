import { formatAmount, lineNetCents, summarize } from './document-calc';

describe('lineNetCents', () => {
  it('rundet kaufmännisch (halb weg von null)', () => {
    expect(lineNetCents(0.5, 0.05)).toBe(3); // 0,025 € → 0,03 €
    expect(lineNetCents(3, 33.33)).toBe(9999);
  });

  it('rechnet negative Mengen (Storno) spiegelbildlich', () => {
    expect(lineNetCents(-0.5, 0.05)).toBe(-3);
    expect(lineNetCents(-3, 33.33)).toBe(-9999);
  });

  it('akzeptiert Text mit Komma und leere Werte', () => {
    expect(lineNetCents('2,5', '10,00')).toBe(2500);
    expect(lineNetCents('', null)).toBe(0);
  });
});

describe('summarize', () => {
  it('rechnet die Steuer je Steuersatz aus der Nettosumme', () => {
    const r = summarize([
      { quantity: 1, unit_price: 1000, tax_rate: 19 },
      { quantity: 1, unit_price: 200, tax_rate: 7 },
    ]);
    expect(r.net).toBe(120000);
    expect(r.tax).toBe(20400); // 190,00 + 14,00
    expect(r.gross).toBe(140400);
    expect(r.groups.map((g) => g.rate)).toEqual([19, 7]);
  });

  it('fasst Positionen mit gleichem Satz zusammen (3 × 0,10 € bei 19 % = 0,06 € Steuer)', () => {
    const r = summarize([0.1, 0.1, 0.1].map((p) => ({ quantity: 1, unit_price: p, tax_rate: 19 })));
    expect(r.net).toBe(30);
    expect(r.tax).toBe(6);
    expect(r.groups.length).toBe(1);
  });

  it('liefert für einen leeren Beleg Nullen', () => {
    expect(summarize([])).toEqual({ lineNet: [], groups: [], net: 0, tax: 0, gross: 0 });
  });
});

describe('formatAmount', () => {
  it('zeigt Beträge deutsch mit Euro-Zeichen', () => {
    expect(formatAmount('1234.50').replace(/\s/g, ' ')).toBe('1.234,50 €');
    expect(formatAmount('-1190.00').replace(/\s/g, ' ')).toContain('1.190,00');
  });
});
