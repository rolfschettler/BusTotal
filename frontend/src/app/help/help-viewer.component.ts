import { Component, ElementRef, ViewEncapsulation, effect, inject, input, output, signal, untracked } from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { ANCHOR_PREFIX } from './help-parser';
import { HelpService } from './help.service';

export interface HelpTarget {
  topic: string;
  anchor: string | null;
}

/**
 * Zeigt ein Hilfethema und springt an die gewünschte Sprungmarke.
 * Wird im Seitenfenster und auf der Hilfeseite verwendet.
 */
@Component({
  selector: 'app-help-viewer',
  // Der Hilfetext kommt als fertiges HTML; seine Formatierung muss daher global gelten.
  encapsulation: ViewEncapsulation.None,
  template: `
    @if (error()) {
      <div class="alert alert-warning mb-0" role="alert">Dieses Hilfethema konnte nicht geladen werden.</div>
    } @else {
      <article class="help-content" [innerHTML]="html()" (click)="onClick($event)"></article>
    }
  `,
})
export class HelpViewerComponent {
  private help = inject(HelpService);
  private sanitizer = inject(DomSanitizer);
  private host = inject<ElementRef<HTMLElement>>(ElementRef);

  topic = input.required<string>();
  anchor = input<string | null>(null);
  /** Ändert sich bei jedem Aufruf von außen, damit auch derselbe Anker erneut angesprungen wird. */
  tick = input(0);
  go = output<HelpTarget>();

  html = signal<SafeHtml>('');
  error = signal(false);
  private run = 0;

  constructor() {
    effect(() => {
      const topic = this.topic();
      const anchor = this.anchor();
      this.tick();
      untracked(() => this.render(topic, anchor));
    });
  }

  private async render(topic: string, anchor: string | null) {
    const run = ++this.run;
    try {
      const doc = await this.help.doc(topic);
      if (run !== this.run) return; // inzwischen wurde etwas anderes angefordert
      this.error.set(false);
      // Der HTML-Code wurde vorher durch DOMPurify bereinigt. bypass ist nötig, damit die
      // id-Attribute der Sprungmarken erhalten bleiben (Angulars eigener Filter entfernt sie).
      this.html.set(this.sanitizer.bypassSecurityTrustHtml(doc.html));
      setTimeout(() => this.scrollTo(anchor), 0);
    } catch {
      if (run === this.run) this.error.set(true);
    }
  }

  private scrollTo(anchor: string | null) {
    const root = this.host.nativeElement;
    const target = anchor ? root.querySelector<HTMLElement>(`#${CSS.escape(ANCHOR_PREFIX + anchor)}`) : null;
    (target ?? root.querySelector<HTMLElement>('.help-content > :first-child'))?.scrollIntoView({ block: 'start', behavior: 'smooth' });
    if (target) {
      target.classList.add('help-flash');
      setTimeout(() => target.classList.remove('help-flash'), 2200);
    }
  }

  /** Verweise im Hilfetext (/hilfe/thema#anker oder #anker) bleiben in der Hilfe. */
  onClick(event: MouseEvent) {
    const a = (event.target as HTMLElement).closest('a');
    const href = a?.getAttribute('href');
    if (!href) return;

    // Externe Quellenangaben trägt der Parser mit target="_blank" aus (help-parser.ts): Der Browser öffnet sie selbst.
    if (/^https?:\/\//i.test(href)) return;

    const m = /^(?:\/hilfe\/([a-z0-9-]+))?(?:#([a-z0-9-]+))?$/.exec(href);
    if (!m || (!m[1] && !m[2])) return;
    event.preventDefault();
    this.go.emit({ topic: m[1] ?? this.topic(), anchor: m[2] ?? null });
  }
}
