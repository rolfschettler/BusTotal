import { Component } from '@angular/core';

/** Platzhalter für Fahrer, bis es Fahrten und Einsätze gibt. */
@Component({
  selector: 'app-driver-tile',
  styles: `:host { display: contents; }`,
  template: `
    <div class="card">
      <div class="card-header fw-semibold">Ihre Einsätze</div>
      <div class="card-body text-body-secondary">
        Sobald Fahrten für Sie geplant sind, erscheinen sie hier.
      </div>
    </div>
  `,
})
export class DriverTileComponent {}
