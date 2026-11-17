import { hasHit } from '../shared/highlight';
import type { ManagedTenant } from './tenant-admin.service';

/** Spalten, nach denen die Firmenliste sortiert werden kann. */
export type TenantSortCol = 'name' | 'subdomain' | 'database' | 'status' | 'migrated';

export type TenantStatusFilter = '' | 'active' | 'locked';

/**
 * Filtert die Firmen (clientseitig, es sind wenige). Gesucht wird nur in Feldern, die die Liste zeigt
 * (Name, Adresse, Datenbank), damit jede übrig bleibende Zeile einen sichtbaren Treffer hat.
 */
export function filterTenants(tenants: ManagedTenant[], query: string, status: TenantStatusFilter): ManagedTenant[] {
  const q = query.trim();
  return tenants.filter(
    (t) =>
      (!status || (status === 'active') === t.is_active) &&
      (!q || hasHit(t.name, q) || hasHit(t.host, q) || hasHit(t.db_database, q)),
  );
}

/**
 * Sortiert die Firmen. Leere Werte ("noch nie aktualisiert") stehen in BEIDEN Richtungen am Ende.
 * Bei Gleichstand entscheidet der Name.
 */
export function sortTenants(tenants: ManagedTenant[], col: TenantSortCol, dir: 'asc' | 'desc'): ManagedTenant[] {
  const sign = dir === 'asc' ? 1 : -1;

  const value = (t: ManagedTenant): string | number | null => {
    switch (col) {
      case 'name':
        return t.name;
      case 'subdomain':
        return t.subdomain;
      case 'database':
        return t.db_database;
      case 'status':
        return t.is_active ? 0 : 1;
      case 'migrated':
        return t.migrated_at ? Date.parse(t.migrated_at) : null;
    }
  };

  return [...tenants].sort((a, b) => {
    const va = value(a);
    const vb = value(b);
    if (va === null && vb === null) return compareText(a.name, b.name);
    if (va === null) return 1;
    if (vb === null) return -1;

    const c = typeof va === 'number' && typeof vb === 'number' ? va - vb : compareText(String(va), String(vb));
    return c * sign || compareText(a.name, b.name);
  });
}

function compareText(a: string, b: string): number {
  return a.localeCompare(b, 'de', { sensitivity: 'base' });
}
