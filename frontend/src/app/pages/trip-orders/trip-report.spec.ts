import { completeSummary, drivenKm, kmBetween } from './trip-report';

describe('Rückerfassung', () => {
  it('rechnet die gefahrenen Kilometer aus erster und letzter Zeile', () => {
    expect(drivenKm([{ km: 1000 }, { km: null }, { km: 1560 }])).toBe(560);
    expect(drivenKm([{ km: 1000 }, { km: null }])).toBeNull();
    expect(drivenKm([{ km: 1000 }])).toBeNull();
    expect(kmBetween(200, 100)).toBeNull();
    expect(kmBetween(0, 0)).toBe(0);
  });

  it('fasst vor „Fahrt erledigt“ die Angaben zusammen', () => {
    const report = {
      actual_passengers: '42', report_notes: null, borders: [], extras: [], costs: [],
      times: [{ km: 10 }, { km: 30 }] as never[],
    };
    expect(completeSummary(report, false)).toContain('Gefahren: 20 km');
    expect(completeSummary(report, false)).toContain('bekommt jetzt seine Nummer');
    expect(completeSummary(report, true)).not.toContain('Nummer');
  });
});
