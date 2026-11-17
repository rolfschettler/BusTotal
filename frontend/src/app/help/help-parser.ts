import DOMPurify from 'dompurify';
import { marked } from 'marked';

export interface HelpSection {
  /** Stabile Sprungmarke, z. B. "nummernkreise" */
  anchor: string;
  title: string;
  level: number;
  /** Reiner Text des Abschnitts, für die Suche */
  text: string;
}

export interface ParsedHelp {
  html: string;
  sections: HelpSection[];
}

/** Präfix für die id-Attribute im HTML, damit sie nie mit Elementen der App kollidieren. */
export const ANCHOR_PREFIX = 'help-';

const HEADING = /^(#{1,4})\s+(.*?)\s*(?:\{#([a-z0-9-]+)\})?\s*$/;

/** Ersatz für Umlaute, damit aus Überschriften sinnvolle Anker werden. */
export function slug(text: string): string {
  return text
    .toLowerCase()
    .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

/**
 * Wandelt Markdown in sicheres HTML um.
 *
 * Überschriften können eine feste Sprungmarke tragen: "## Nummernkreise {#nummernkreise}".
 * Diese Marke ändert sich nicht, wenn man die Überschrift umformuliert – so bleiben
 * Verweise aus der Anwendung gültig.
 */
export function parseHelp(markdown: string): ParsedHelp {
  // 1. Feste Sprungmarken aus den Überschriftzeilen herausnehmen (Codeblöcke auslassen).
  const explicit: (string | null)[] = [];
  let inFence = false;
  const cleaned = markdown.split(/\r?\n/).map((line) => {
    if (/^\s*```/.test(line)) inFence = !inFence;
    if (inFence) return line;
    const m = HEADING.exec(line);
    if (!m) return line;
    explicit.push(m[3] ?? null);
    return `${m[1]} ${m[2]}`;
  });

  // 2. Markdown → HTML
  const raw = marked.parse(cleaned.join('\n'), { async: false }) as string;
  const doc = new DOMParser().parseFromString(raw, 'text/html');

  // 3. Überschriften bekommen ihre id; Abschnitte für die Suche einsammeln.
  const used = new Set<string>();
  const sections: HelpSection[] = [];
  const headings = Array.from(doc.body.querySelectorAll('h1, h2, h3, h4'));
  const aligned = headings.length === explicit.length; // Sicherheitsnetz, falls die Zählung abweicht

  let current: HelpSection | null = null;
  const parts: string[] = [];
  const flush = () => {
    if (current) current.text = parts.join(' ').replace(/\s+/g, ' ').trim();
    parts.length = 0;
  };

  for (const node of Array.from(doc.body.children)) {
    const i = headings.indexOf(node as Element);
    if (i === -1) {
      if (current) parts.push(node.textContent ?? '');
      continue;
    }
    flush();
    const title = node.textContent?.trim() ?? '';
    let anchor = (aligned ? explicit[i] : null) ?? slug(title);
    while (used.has(anchor)) anchor += '-2';
    used.add(anchor);
    node.id = ANCHOR_PREFIX + anchor;
    current = { anchor, title, level: Number(node.tagName.substring(1)), text: '' };
    sections.push(current);
  }
  flush();

  // 4. Bereinigen. Auch bei eigenen Texten: Verteidigung in der Tiefe.
  const html = DOMPurify.sanitize(doc.body.innerHTML);
  return { html, sections };
}
