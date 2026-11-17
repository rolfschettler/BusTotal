import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { DashboardService, SetupItem, SetupStatus } from '../../../core/dashboard.service';
import { HelpLinkComponent } from '../../../help/help-link.component';

/**
 * Einrichtungs-Checkliste für den Administrator. Die Software erkennt selbst, welche Punkte
 * erledigt sind. Sind alle erledigt, verschwindet die Kachel.
 */
@Component({
  selector: 'app-setup-tile',
  imports: [RouterLink, HelpLinkComponent],
  styles: `:host { display: contents; }`,
  template: `
    @if (status(); as s) {
      @if (s.done < s.total) {
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Einrichtung</span>
            <span class="small text-body-secondary">{{ s.done }} von {{ s.total }} erledigt</span>
          </div>
          <div class="card-body pb-2">
            <div class="progress mb-3" role="progressbar" aria-label="Einrichtungsfortschritt"
                 [attr.aria-valuenow]="s.done" aria-valuemin="0" [attr.aria-valuemax]="s.total">
              <div class="progress-bar" [style.width.%]="(100 * s.done) / s.total"></div>
            </div>
            <ul class="list-unstyled mb-0">
              @for (i of s.items; track i.key) {
                <li class="d-flex align-items-center py-1">
                  <span class="me-2" [class.text-success]="i.done" [class.text-body-secondary]="!i.done" aria-hidden="true">{{ i.done ? '✔' : '○' }}</span>
                  <span class="visually-hidden">{{ i.done ? 'Erledigt:' : 'Offen:' }}</span>
                  @if (i.done) {
                    <span class="text-body-secondary text-decoration-line-through">{{ i.label }}</span>
                  } @else {
                    <a [routerLink]="i.route" class="text-decoration-none">{{ i.label }}</a>
                    <app-help-link [topic]="topic(i)" [anchor]="anchor(i)" [label]="i.label" />
                  }
                </li>
              }
            </ul>
          </div>
        </div>
      }
    }
  `,
})
export class SetupTileComponent implements OnInit {
  private api = inject(DashboardService);
  status = signal<SetupStatus | null>(null);

  ngOnInit() {
    // Schlägt das Laden fehl, bleibt die Kachel einfach aus; die Startseite funktioniert weiter.
    this.api.setup().subscribe({ next: (s) => this.status.set(s), error: () => {} });
  }

  topic(i: SetupItem) {
    return i.help.split('#')[0];
  }
  anchor(i: SetupItem) {
    return i.help.split('#')[1] ?? null;
  }
}
