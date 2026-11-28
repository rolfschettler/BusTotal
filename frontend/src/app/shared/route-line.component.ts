import { Component, input } from '@angular/core';

export interface LineStep {
  label: string;
  state: 'done' | 'current' | 'open';
}

/**
 * Der Linienplan eines Vorgangs: die Haltestellen von links nach rechts, die erledigten verbunden, die aktuelle markiert.
 * Darunter (als Inhalt) der Satz zum nächsten Schritt und seine Tasten.
 *
 *   <app-route-line [steps]="line()"> … nächster Schritt … </app-route-line>
 */
@Component({
  selector: 'app-route-line',
  template: `
    <ol class="route-line" aria-label="Ablauf">
      @for (s of steps(); track s.label) {
        <li [class]="s.state" [attr.aria-current]="s.state === 'current' ? 'step' : null">
          <span class="route-stop" aria-hidden="true"></span>
          <span>{{ s.label }}</span>
          @if (s.state === 'done') { <span class="visually-hidden">(erledigt)</span> }
        </li>
      }
    </ol>
    <ng-content />
  `,
  host: { class: 'route-panel' },
})
export class RouteLineComponent {
  steps = input.required<readonly LineStep[]>();
}
