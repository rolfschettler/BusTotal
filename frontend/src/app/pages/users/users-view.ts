import { roleLabel } from '../../core/roles';
import type { ManagedUser } from '../../core/user-admin.service';
import { hasHit } from '../../shared/highlight';

/** Spalten, nach denen die Benutzerliste sortiert werden kann. */
export type UserSortCol = 'name' | 'email' | 'role' | 'status' | 'lastLogin';

export type StatusFilter = '' | 'active' | 'locked';

/**
 * Filtert die Benutzer (clientseitig, die Liste ist klein).
 * Gesucht wird in den Feldern, die die Liste zeigt (Name, E-Mail, Rolle). Dadurch hat jede Zeile, die
 * übrig bleibt, einen sichtbaren und markierbaren Treffer. Der Vergleich ist derselbe wie bei der
 * Markierung (Groß-/Kleinschreibung und Umlaute egal).
 */
export function filterUsers(users: ManagedUser[], query: string, role: string, status: StatusFilter): ManagedUser[] {
  const q = query.trim();
  return users.filter(
    (u) =>
      (!role || u.role === role) &&
      (!status || (status === 'active') === u.active) &&
      (!q || hasHit(u.name, q) || hasHit(u.email, q) || hasHit(roleLabel(u.role), q)),
  );
}

/**
 * Sortiert die Benutzer. Leere Werte ("noch nie angemeldet") stehen in BEIDEN Richtungen am Ende,
 * so wie in der Kundenliste. Bei Gleichstand entscheidet der Name, damit die Reihenfolge stabil ist.
 */
export function sortUsers(users: ManagedUser[], col: UserSortCol, dir: 'asc' | 'desc'): ManagedUser[] {
  const sign = dir === 'asc' ? 1 : -1;

  const value = (u: ManagedUser): string | number | null => {
    switch (col) {
      case 'name':
        return u.name;
      case 'email':
        return u.email;
      case 'role':
        return roleLabel(u.role);
      case 'status':
        // Aktiv vor gesperrt; innerhalb "aktiv" zuerst die mit vorläufigem Passwort (da ist noch etwas zu tun)
        return (u.active ? 0 : 2) + (u.must_change_password ? 0 : 1);
      case 'lastLogin':
        return u.last_login_at ? Date.parse(u.last_login_at) : null;
    }
  };

  return [...users].sort((a, b) => {
    const va = value(a);
    const vb = value(b);
    if (va === null && vb === null) return compareText(a.name, b.name);
    if (va === null) return 1;
    if (vb === null) return -1;

    const c = typeof va === 'number' && typeof vb === 'number' ? va - vb : compareText(String(va), String(vb));
    return c * sign || compareText(a.name, b.name);
  });
}

/** Deutsche Sortierung ohne Rücksicht auf Groß-/Kleinschreibung (Ä steht bei A). */
function compareText(a: string, b: string): number {
  return a.localeCompare(b, 'de', { sensitivity: 'base' });
}
