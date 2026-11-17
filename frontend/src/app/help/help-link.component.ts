import { Component, inject, input } from '@angular/core';
import { HelpPanelService } from './help-panel.component';

/** Kleines Fragezeichen: öffnet die Hilfe direkt an einer bestimmten Stelle. */
@Component({
  selector: 'app-help-link',
  template: `
    <button type="button" class="help-link" (click)="open()" [attr.aria-label]="'Hilfe zu ' + label()" [title]="'Hilfe: ' + label()">?</button>
  `,
  styles: `
    .help-link {
      display: inline-flex; align-items: center; justify-content: center;
      width: 20px; height: 20px; padding: 0; margin-left: 6px;
      border: 1px solid var(--brand); border-radius: 50%;
      background: #fff; color: var(--brand);
      font-size: 12px; font-weight: 700; line-height: 1; vertical-align: middle; cursor: pointer;
    }
    .help-link:hover, .help-link:focus-visible { background: var(--brand); color: #fff; }
  `,
})
export class HelpLinkComponent {
  private panel = inject(HelpPanelService);

  topic = input.required<string>();
  anchor = input<string | null>(null);
  /** Kurzer Name des Themas, für Screenreader und Tooltip */
  label = input.required<string>();

  open() {
    this.panel.open(this.topic(), this.anchor());
  }
}
