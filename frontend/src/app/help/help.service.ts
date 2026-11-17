import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { NavigationEnd, NavigationStart, Router } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { HelpSection, parseHelp } from './help-parser';

export interface HelpTopic {
  id: string;
  title: string;
  summary: string;
}

export interface HelpDoc {
  id: string;
  title: string;
  html: string;
  sections: HelpSection[];
}

export interface HelpContext {
  topic: string;
  anchor: string | null;
}

export interface HelpHit {
  topic: string;
  topicTitle: string;
  anchor: string;
  title: string;
  snippet: string;
  score: number;
}

/** Lädt die Hilfetexte (Markdown-Dateien unter /help), bestimmt den Kontext der aktuellen Seite und sucht. */
@Injectable({ providedIn: 'root' })
export class HelpService {
  private http = inject(HttpClient);
  private router = inject(Router);

  readonly topics = signal<HelpTopic[]>([]);
  private docs = new Map<string, Promise<HelpDoc>>();
  private indexLoaded?: Promise<void>;

  /** Thema der aktuellen Seite, steht in den Routen unter data.help = "thema#anker". */
  private routeContext = signal<HelpContext | null>(null);
  /** Seiten mit Reitern verfeinern den Anker, z. B. beim Wechsel auf "Nummernkreise". */
  private anchorOverride = signal<string | null>(null);

  readonly context = computed<HelpContext | null>(() => {
    const c = this.routeContext();
    return c ? { topic: c.topic, anchor: this.anchorOverride() ?? c.anchor } : null;
  });

  constructor() {
    this.router.events.subscribe((e) => {
      // Neue Navigation: Verfeinerung der alten Seite verwerfen.
      if (e instanceof NavigationStart) this.anchorOverride.set(null);
      if (e instanceof NavigationEnd) this.routeContext.set(this.readRoute());
    });
    this.routeContext.set(this.readRoute());
  }

  /** Eine Seite meldet, in welchem Abschnitt sie gerade ist (z. B. aktiver Reiter). */
  setAnchor(anchor: string | null) {
    this.anchorOverride.set(anchor);
  }

  private readRoute(): HelpContext | null {
    let r = this.router.routerState.snapshot.root;
    let help: unknown = r.data['help'];
    while (r.firstChild) {
      r = r.firstChild;
      if (r.data['help'] !== undefined) help = r.data['help'];
    }
    if (typeof help !== 'string') return null;
    const [topic, anchor] = help.split('#');
    return { topic, anchor: anchor || null };
  }

  // ---------- Laden ----------

  loadIndex(): Promise<void> {
    this.indexLoaded ??= firstValueFrom(this.http.get<HelpTopic[]>('/help/index.json')).then((t) => this.topics.set(t));
    return this.indexLoaded;
  }

  doc(id: string): Promise<HelpDoc> {
    let p = this.docs.get(id);
    if (!p) {
      p = (async () => {
        await this.loadIndex();
        const md = await firstValueFrom(this.http.get(`/help/${encodeURIComponent(id)}.md`, { responseType: 'text' }));
        const parsed = parseHelp(md);
        const title = this.topics().find((t) => t.id === id)?.title ?? id;
        return { id, title, ...parsed };
      })();
      // Fehlgeschlagene Ladeversuche nicht dauerhaft merken.
      p.catch(() => this.docs.delete(id));
      this.docs.set(id, p);
    }
    return p;
  }

  // ---------- Suche ----------

  async search(query: string): Promise<HelpHit[]> {
    const terms = query.toLowerCase().split(/\s+/).filter((t) => t.length >= 2);
    if (!terms.length) return [];

    await this.loadIndex();
    const docs = await Promise.all(this.topics().map((t) => this.doc(t.id)));
    const hits: HelpHit[] = [];

    for (const d of docs) {
      for (const s of d.sections) {
        const title = s.title.toLowerCase();
        const text = s.text.toLowerCase();
        let score = 0;
        let all = true;
        for (const t of terms) {
          const inTitle = title.includes(t);
          const inText = text.includes(t);
          if (!inTitle && !inText) { all = false; break; }
          score += (inTitle ? 10 : 0) + (inText ? 1 : 0);
        }
        if (!all) continue;
        hits.push({
          topic: d.id, topicTitle: d.title, anchor: s.anchor, title: s.title,
          snippet: this.snippet(s.text, terms[0]), score,
        });
      }
    }
    return hits.sort((a, b) => b.score - a.score).slice(0, 20);
  }

  private snippet(text: string, term: string): string {
    const i = text.toLowerCase().indexOf(term);
    if (i < 0) return text.slice(0, 140);
    const start = Math.max(0, i - 50);
    return (start > 0 ? '…' : '') + text.slice(start, start + 150) + (start + 150 < text.length ? '…' : '');
  }
}
