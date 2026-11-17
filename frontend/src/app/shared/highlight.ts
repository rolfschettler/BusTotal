/** Ein Stück Text, entweder Treffer (hervorgehoben) oder normal. */
export interface Segment {
  text: string;
  hit: boolean;
}

/**
 * "phrase": Der ganze Suchbegriff muss vorkommen (Kundenliste, entspricht der Datenbanksuche).
 * "words": Jedes Wort für sich, ab 2 Zeichen (Hilfe-Suche).
 */
export type HighlightMode = 'phrase' | 'words';

/**
 * Zeichen vergleichbar machen: Groß-/Kleinschreibung und Akzente spielen keine Rolle
 * (Müller = muller = MÜLLER). Genau so vergleicht die Datenbank bei der Kundensuche, deshalb muss die
 * Markierung es auch tun, sonst erscheint eine Zeile, in der nichts markiert ist.
 * Jedes Zeichen bleibt ein Zeichen, so passen die Positionen im Original und im Vergleichstext zusammen.
 */
function fold(ch: string): string {
  return ch.normalize('NFD')[0].toLowerCase();
}

function termsOf(query: string | null | undefined, mode: HighlightMode): string[] {
  const q = (query ?? '').trim();
  if (!q) return [];
  return mode === 'phrase' ? [q] : q.split(/\s+/).filter((t) => t.length >= 2);
}

/**
 * Teilt einen Text in Stücke auf, die den Suchbegriff treffen oder nicht.
 * Beispiel: highlightSegments('Nicole', 'nico') → [{text:'Nico', hit:true}, {text:'le', hit:false}]
 */
export function highlightSegments(
  text: string | number | null | undefined,
  query: string | null | undefined,
  mode: HighlightMode = 'phrase',
): Segment[] {
  const value = text === null || text === undefined ? '' : String(text);
  if (!value) return [];

  const terms = termsOf(query, mode);
  if (!terms.length) return [{ text: value, hit: false }];

  const chars = Array.from(value); // Array.from: auch Sonderzeichen jenseits der Grundzeichen bleiben ganz
  const folded = chars.map(fold);
  const marked = new Array<boolean>(chars.length).fill(false);

  for (const term of terms) {
    const t = Array.from(term).map(fold);
    for (let i = 0; i + t.length <= folded.length; i++) {
      if (t.every((c, k) => folded[i + k] === c)) {
        for (let k = 0; k < t.length; k++) marked[i + k] = true;
        i += t.length - 1;
      }
    }
  }

  // Aufeinanderfolgende gleiche Stücke zusammenfassen (auch überlappende Treffer verschiedener Wörter).
  const out: Segment[] = [];
  let current = '';
  let currentHit = marked[0];
  chars.forEach((ch, i) => {
    if (marked[i] !== currentHit) {
      out.push({ text: current, hit: currentHit });
      current = '';
      currentHit = marked[i];
    }
    current += ch;
  });
  out.push({ text: current, hit: currentHit });
  return out;
}

/** Gibt es im Text mindestens einen Treffer? */
export function hasHit(text: string | number | null | undefined, query: string | null | undefined, mode: HighlightMode = 'phrase'): boolean {
  return highlightSegments(text, query, mode).some((s) => s.hit);
}
