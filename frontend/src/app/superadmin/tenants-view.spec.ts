import { filterTenants, sortTenants } from './tenants-view';

const tenant = (name: string, over: Record<string, unknown> = {}) =>
  ({
    id: name, name, subdomain: name.toLowerCase(), host: name.toLowerCase() + '.bustotal.de', db_host: '127.0.0.1', db_port: 3306,
    db_database: 'db_' + name.toLowerCase(), db_username: 'u', is_active: true, migrated_at: '2026-11-01T10:00:00Z', created_at: null, ...over,
  }) as never;

const names = (list: { name: string }[]) => list.map((t) => t.name);

describe('sortTenants', () => {
  const alle = [
    tenant('Zeta', { migrated_at: '2026-11-01T10:00:00Z' }),
    tenant('Alpha', { migrated_at: '2026-11-18T10:00:00Z' }),
    tenant('Ärger', { migrated_at: null }),
    tenant('Beta', { migrated_at: '2026-10-05T10:00:00Z', is_active: false }),
  ];

  it('sortiert nach Name, Umlaute bei ihrem Grundbuchstaben', () => {
    expect(names(sortTenants(alle, 'name', 'asc'))).toEqual(['Alpha', 'Ärger', 'Beta', 'Zeta']);
    expect(names(sortTenants(alle, 'name', 'desc'))).toEqual(['Zeta', 'Beta', 'Ärger', 'Alpha']);
  });

  it('stellt "noch nie aktualisiert" in BEIDEN Richtungen ans Ende', () => {
    expect(names(sortTenants(alle, 'migrated', 'asc'))).toEqual(['Beta', 'Zeta', 'Alpha', 'Ärger']);
    expect(names(sortTenants(alle, 'migrated', 'desc'))).toEqual(['Alpha', 'Zeta', 'Beta', 'Ärger']);
  });

  it('sortiert nach Status: aktiv vor gesperrt', () => {
    expect(names(sortTenants(alle, 'status', 'asc'))).toEqual(['Alpha', 'Ärger', 'Zeta', 'Beta']);
    expect(names(sortTenants(alle, 'status', 'desc'))).toEqual(['Beta', 'Alpha', 'Ärger', 'Zeta']);
  });

  it('verändert die ursprüngliche Liste nicht', () => {
    const kopie = [...alle];
    sortTenants(alle, 'name', 'desc');
    expect(alle).toEqual(kopie);
  });
});

describe('filterTenants', () => {
  const alle = [tenant('Muster Reisen'), tenant('Nord Bus', { is_active: false }), tenant('Süd Tours')];

  it('sucht in Name, Adresse und Datenbank, ohne Rücksicht auf Groß-/Kleinschreibung und Umlaute', () => {
    expect(names(filterTenants(alle, 'MUSTER', ''))).toEqual(['Muster Reisen']);
    expect(names(filterTenants(alle, 'sud', ''))).toEqual(['Süd Tours']);
    expect(names(filterTenants(alle, 'nord bus.bustotal', ''))).toEqual(['Nord Bus']);
    expect(names(filterTenants(alle, 'db_muster', ''))).toEqual(['Muster Reisen']);
  });

  it('filtert nach Status', () => {
    expect(names(filterTenants(alle, '', 'locked'))).toEqual(['Nord Bus']);
    expect(names(filterTenants(alle, '', 'active'))).toEqual(['Muster Reisen', 'Süd Tours']);
  });

  it('findet nichts außerhalb der sichtbaren Felder (Zugangsname)', () => {
    expect(filterTenants(alle, 'root', '')).toEqual([]);
  });
});
