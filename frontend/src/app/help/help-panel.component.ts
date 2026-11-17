import { Component, Injectable, effect, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NgbActiveOffcanvas, NgbOffcanvas, NgbOffcanvasRef } from '@ng-bootstrap/ng-bootstrap';
import { HelpTarget, HelpViewerComponent } from './help-viewer.component';
import { HelpService } from './help.service';

/** Seitenfenster rechts: zeigt die Hilfe zur aktuellen Seite, die Seite bleibt bedienbar. */
@Component({
  selector: 'app-help-panel',
  imports: [HelpViewerComponent, RouterLink],
  // Das Host-Element sitzt zwischen dem Bootstrap-Rahmen (.offcanvas, eine Flex-Spalte) und dem
  // Inhalt. Es muss die Höhe weiterreichen, sonst scrollt der Textbereich nicht in sich.
  styles: `
    :host { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
    .offcanvas-body { min-height: 0; overflow-y: auto; }
  `,
  template: `
    <div class="offcanvas-header border-bottom">
      <h2 class="offcanvas-title fs-5" id="helpTitle">Hilfe</h2>
      <a class="btn btn-sm btn-outline-secondary ms-auto me-2" [routerLink]="['/hilfe', topic()]"
         [fragment]="anchor() ?? undefined" (click)="active.dismiss()">Im Handbuch öffnen</a>
      <button type="button" class="btn-close" aria-label="Hilfe schließen" (click)="active.dismiss()"></button>
    </div>
    <div class="offcanvas-body">
      <app-help-viewer [topic]="topic()" [anchor]="anchor()" [tick]="tick()" (go)="onGo($event)" />
    </div>
  `,
})
export class HelpPanelComponent {
  private help = inject(HelpService);
  active = inject(NgbActiveOffcanvas);

  topic = signal('start');
  anchor = signal<string | null>(null);
  tick = signal(0);

  private first = true;

  constructor() {
    // Wechselt der Benutzer die Seite (oder den Reiter), folgt das offene Fenster.
    effect(() => {
      const c = this.help.context();
      if (this.first) {
        this.first = false; // beim Öffnen gilt das Ziel, das show() gesetzt hat
        return;
      }
      if (c) this.show(c.topic, c.anchor);
    });
  }

  show(topic: string, anchor: string | null) {
    this.topic.set(topic);
    this.anchor.set(anchor);
    this.tick.update((n) => n + 1);
  }

  onGo(t: HelpTarget) {
    this.show(t.topic, t.anchor);
  }
}

/** Öffnet und schließt das Hilfe-Seitenfenster. */
@Injectable({ providedIn: 'root' })
export class HelpPanelService {
  private offcanvas = inject(NgbOffcanvas);
  private help = inject(HelpService);
  private ref?: NgbOffcanvasRef;

  /** Ohne Angabe: Thema und Abschnitt der aktuellen Seite. */
  open(topic?: string, anchor?: string | null) {
    const target: HelpTarget = topic
      ? { topic, anchor: anchor ?? null }
      : (this.help.context() ?? { topic: 'start', anchor: null });

    if (this.ref) {
      (this.ref.componentInstance as HelpPanelComponent).show(target.topic, target.anchor);
      return;
    }
    const ref = this.offcanvas.open(HelpPanelComponent, {
      position: 'end',
      backdrop: false, // Seite dahinter bleibt sichtbar und bedienbar
      scroll: true,
      panelClass: 'help-panel',
      ariaLabelledBy: 'helpTitle',
    });
    (ref.componentInstance as HelpPanelComponent).show(target.topic, target.anchor);
    const clear = () => (this.ref = undefined);
    ref.result.then(clear, clear);
    this.ref = ref;
  }

  toggle() {
    if (this.ref) this.ref.dismiss();
    else this.open();
  }
}
