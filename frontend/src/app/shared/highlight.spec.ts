import { hasHit, highlightSegments } from './highlight';

const hits = (text: string | number | null, query: string, mode: 'phrase' | 'words' = 'phrase') =>
  highlightSegments(text, query, mode).filter((s) => s.hit).map((s) => s.text);

describe('highlightSegments', () => {
  it('hebt den Anfang eines Wortes hervor (Beispiel aus der Kundensuche)', () => {
    expect(highlightSegments('Nicole', 'nico')).toEqual([
      { text: 'Nico', hit: true },
      { text: 'le', hit: false },
    ]);
  });

  it('beachtet die Schreibweise des Originals, nicht die der Eingabe', () => {
    expect(highlightSegments('NICOLE Herrmann', 'Nico')).toEqual([
      { text: 'NICO', hit: true },
      { text: 'LE Herrmann', hit: false },
    ]);
  });

  it('ignoriert Umlaute und Akzente wie die Datenbanksuche', () => {
    expect(hits('Müller', 'muller')).toEqual(['Müller']);
    expect(hits('Muller', 'MÜLLER')).toEqual(['Muller']);
    expect(hits('Café Zürich', 'cafe')).toEqual(['Café']);
    expect(hits('Schröder', 'schro')).toEqual(['Schrö']);
  });

  it('findet mehrere Treffer in einem Text', () => {
    expect(hits('Hamburg und Hamburger', 'hamburg')).toEqual(['Hamburg', 'Hamburg']);
  });

  it('findet Begriffe mit Leerzeichen und Bindestrich', () => {
    expect(hits('Albert-Schweitzer-Gymnasium Stuttgart', 'gymnasium st')).toEqual(['Gymnasium St']);
    expect(hits('Albert-Schweitzer-Gymnasium', 'schweitzer-gym')).toEqual(['Schweitzer-Gym']);
  });

  it('markiert Zahlen, auch wenn sie als Zahl ankommen', () => {
    expect(hits(10154, '015')).toEqual(['015']);
    expect(hits('K-00042', '0004')).toEqual(['0004']);
  });

  it('gibt ohne Suchbegriff den ganzen Text ohne Markierung zurück', () => {
    expect(highlightSegments('Nicole', '')).toEqual([{ text: 'Nicole', hit: false }]);
    expect(highlightSegments('Nicole', '   ')).toEqual([{ text: 'Nicole', hit: false }]);
    expect(highlightSegments('Nicole', null)).toEqual([{ text: 'Nicole', hit: false }]);
    expect(highlightSegments('Nicole', undefined)).toEqual([{ text: 'Nicole', hit: false }]);
  });

  it('gibt bei leerem Text eine leere Liste zurück', () => {
    expect(highlightSegments('', 'x')).toEqual([]);
    expect(highlightSegments(null, 'x')).toEqual([]);
    expect(highlightSegments(undefined, 'x')).toEqual([]);
  });

  it('kennzeichnet einen Text, der ganz dem Suchbegriff entspricht', () => {
    expect(highlightSegments('abc', 'abc')).toEqual([{ text: 'abc', hit: true }]);
  });

  it('lässt Text unverändert: Aneinandergereiht ergeben die Stücke immer das Original', () => {
    const texte = ['Nicole Herrmann', 'Müller & Söhne GmbH', 'straße 12a', '<b>fett</b>', 'a  b   c', ' Rand '];
    for (const t of texte) {
      for (const q of ['n', 'mü', 'söhne', 'b', 'a b', 'straße', ' ', 'x']) {
        expect(highlightSegments(t, q).map((s) => s.text).join('')).toBe(t);
      }
    }
  });

  it('behandelt Sonderzeichen des Suchbegriffs als normalen Text, nicht als Muster', () => {
    expect(hits('Preis (netto) 5+5', '(netto)')).toEqual(['(netto)']);
    expect(hits('a.b', '.')).toEqual(['.']);
    expect(hits('abc', '.*')).toEqual([]);
    expect(hits('5+5', '+')).toEqual(['+']);
  });

  it('verändert HTML im Text nicht (die Maskierung übernimmt Angular)', () => {
    expect(highlightSegments('<b>x</b>', 'b')).toEqual([
      { text: '<', hit: false },
      { text: 'b', hit: true },
      { text: '>x</', hit: false },
      { text: 'b', hit: true },
      { text: '>', hit: false },
    ]);
  });

  it('stürzt bei ß und seltenen Zeichen nicht ab', () => {
    expect(hits('Straße', 'stra')).toEqual(['Stra']);
    expect(hits('Gruß 😀 Welt', 'welt')).toEqual(['Welt']);
    // Direkt aufeinanderfolgende Treffer werden zu einer Markierung, und kein Zeichen wird zerrissen.
    expect(hits('😀😀', '😀')).toEqual(['😀😀']);
    expect(hits('aaa', 'a')).toEqual(['aaa']);
  });

  describe('Wortmodus (Hilfe-Suche)', () => {
    it('markiert jedes Wort einzeln', () => {
      expect(hits('Kunden- und Debitorennummer', 'debitor nummer', 'words')).toEqual(['Debitor', 'nummer']);
    });

    it('ignoriert Wörter unter 2 Zeichen', () => {
      expect(hits('a b c Kunde', 'a b kunde', 'words')).toEqual(['Kunde']);
    });

    it('fasst überlappende Treffer zusammen', () => {
      expect(hits('abcd', 'abc bcd', 'words')).toEqual(['abcd']);
    });

    it('findet kein ganzes Wortgruppen-Stück, das nur im Phrasenmodus passt', () => {
      expect(hits('kunde anlegen', 'kunde anlegen', 'words')).toEqual(['kunde', 'anlegen']);
      expect(hits('kunde anlegen', 'kunde anlegen', 'phrase')).toEqual(['kunde anlegen']);
    });
  });
});

describe('hasHit', () => {
  it('sagt, ob es einen Treffer gibt', () => {
    expect(hasHit('Nicole', 'nic')).toBe(true);
    expect(hasHit('Nicole', 'xyz')).toBe(false);
    expect(hasHit(10154, '54')).toBe(true);
    expect(hasHit(null, '54')).toBe(false);
    expect(hasHit('Nicole', '')).toBe(false);
  });
});
