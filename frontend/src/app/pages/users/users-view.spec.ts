import { filterUsers, sortUsers } from './users-view';

const user = (name: string, over: Record<string, unknown> = {}) =>
  ({
    id: name, name, email: name.toLowerCase().replace(/\s+/g, '.') + '@firma.test', role: 'disponent', active: true,
    must_change_password: false, last_login_at: null, created_at: '2026-01-01T00:00:00Z', ...over,
  }) as never;

const names = (list: { name: string }[]) => list.map((u) => u.name);

describe('sortUsers', () => {
  const alle = [
    user('Zora', { role: 'fahrer', last_login_at: '2026-11-01T10:00:00Z' }),
    user('Anna', { role: 'admin', last_login_at: '2026-11-18T10:00:00Z' }),
    user('Ben', { role: 'buchhaltung', last_login_at: null }),
    user('Äpfel', { role: 'disponent', last_login_at: '2026-10-05T10:00:00Z' }),
  ];

  it('sortiert nach Name, Umlaute bei ihrem Grundbuchstaben', () => {
    expect(names(sortUsers(alle, 'name', 'asc'))).toEqual(['Anna', 'Äpfel', 'Ben', 'Zora']);
    expect(names(sortUsers(alle, 'name', 'desc'))).toEqual(['Zora', 'Ben', 'Äpfel', 'Anna']);
  });

  it('sortiert nach angezeigter Rolle (Administrator, Buchhaltung, Disponent, Fahrer)', () => {
    expect(names(sortUsers(alle, 'role', 'asc'))).toEqual(['Anna', 'Ben', 'Äpfel', 'Zora']);
  });

  it('stellt "noch nie angemeldet" in BEIDEN Richtungen ans Ende', () => {
    expect(names(sortUsers(alle, 'lastLogin', 'asc'))).toEqual(['Äpfel', 'Zora', 'Anna', 'Ben']);
    expect(names(sortUsers(alle, 'lastLogin', 'desc'))).toEqual(['Anna', 'Zora', 'Äpfel', 'Ben']);
  });

  it('sortiert nach Status: aktiv vor gesperrt, vorläufiges Passwort zuerst', () => {
    const liste = [
      user('A gesperrt', { active: false }),
      user('B aktiv'),
      user('C neu', { must_change_password: true }),
    ];
    expect(names(sortUsers(liste, 'status', 'asc'))).toEqual(['C neu', 'B aktiv', 'A gesperrt']);
    expect(names(sortUsers(liste, 'status', 'desc'))).toEqual(['A gesperrt', 'B aktiv', 'C neu']);
  });

  it('entscheidet bei Gleichstand nach dem Namen, unabhängig von der Richtung', () => {
    const gleich = [user('Clara'), user('Berta'), user('Anna')]; // alle Disponenten
    expect(names(sortUsers(gleich, 'role', 'asc'))).toEqual(['Anna', 'Berta', 'Clara']);
    expect(names(sortUsers(gleich, 'role', 'desc'))).toEqual(['Anna', 'Berta', 'Clara']);
  });

  it('verändert die ursprüngliche Liste nicht', () => {
    const kopie = names(alle);
    sortUsers(alle, 'name', 'desc');
    expect(names(alle)).toEqual(kopie);
  });

  it('kommt mit leerer Liste und einem Eintrag zurecht', () => {
    expect(sortUsers([], 'name', 'asc')).toEqual([]);
    expect(names(sortUsers([user('Solo')], 'lastLogin', 'desc'))).toEqual(['Solo']);
  });

  it('wenn alle leer sind, bleibt die Namensreihenfolge', () => {
    const nie = [user('Zack'), user('Abel')];
    expect(names(sortUsers(nie, 'lastLogin', 'asc'))).toEqual(['Abel', 'Zack']);
  });
});

describe('filterUsers', () => {
  const alle = [
    user('Erika Müller', { role: 'disponent' }),
    user('Hans Meier', { role: 'admin' }),
    user('Gerd Gesperrt', { role: 'fahrer', active: false }),
  ];

  it('gibt ohne Filter alle zurück', () => {
    expect(names(filterUsers(alle, '', '', ''))).toEqual(['Erika Müller', 'Hans Meier', 'Gerd Gesperrt']);
    expect(names(filterUsers(alle, '   ', '', ''))).toEqual(['Erika Müller', 'Hans Meier', 'Gerd Gesperrt']);
  });

  it('sucht im Namen, ohne auf Groß-/Kleinschreibung und Umlaute zu achten', () => {
    expect(names(filterUsers(alle, 'MULLER', '', ''))).toEqual(['Erika Müller']);
    expect(names(filterUsers(alle, 'erika', '', ''))).toEqual(['Erika Müller']);
  });

  it('sucht in der E-Mail-Adresse', () => {
    expect(names(filterUsers(alle, 'hans.meier@', '', ''))).toEqual(['Hans Meier']);
  });

  it('sucht in der angezeigten Rolle', () => {
    expect(names(filterUsers(alle, 'administr', '', ''))).toEqual(['Hans Meier']);
    expect(names(filterUsers(alle, 'fahr', '', ''))).toEqual(['Gerd Gesperrt']);
  });

  it('filtert nach Rolle und Status, auch kombiniert mit der Suche', () => {
    expect(names(filterUsers(alle, '', 'admin', ''))).toEqual(['Hans Meier']);
    expect(names(filterUsers(alle, '', '', 'locked'))).toEqual(['Gerd Gesperrt']);
    expect(names(filterUsers(alle, '', '', 'active'))).toEqual(['Erika Müller', 'Hans Meier']);
    expect(names(filterUsers(alle, 'e', '', 'active'))).toEqual(['Erika Müller', 'Hans Meier']);
    expect(names(filterUsers(alle, 'gerd', '', 'active'))).toEqual([]);
  });

  it('findet nichts bei unbekanntem Begriff', () => {
    expect(filterUsers(alle, 'xyz', '', '')).toEqual([]);
  });
});
