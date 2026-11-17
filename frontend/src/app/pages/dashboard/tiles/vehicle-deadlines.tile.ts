import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { DashboardService, VehicleDeadlines } from '../../../core/dashboard.service';
import { deadlineText, germanDate } from '../../../shared/deadline';

/**
 * Fällige Fristen des Fuhrparks (HU, SP, Fahrtenschreiber, Wartung): abgelaufen oder in 30 Tagen fällig.
 * Die Kachel erscheint nur, wenn es etwas zu tun gibt.
 */
@Component({
  selector: 'app-vehicle-deadlines-tile',
  imports: [RouterLink],
  styles: `:host { display: contents; }`,
  template: `
    @if (data(); as d) {
      @if (d.total > 0) {
        <div class="card">
          <div class="card-header fw-semibold">Fällige Fristen im Fuhrpark</div>
          <ul class="list-group list-group-flush">
            @for (i of d.items; track i.vehicle_id + i.kind) {
              <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                <div>
                  <a [routerLink]="['/fahrzeuge', i.vehicle_id]" class="fw-semibold text-decoration-none">{{ i.vehicle }}</a>
                  <div class="small text-body-secondary">{{ i.label }} · {{ date(i.date) }}</div>
                </div>
                <span class="badge align-self-center text-nowrap" [class.text-bg-danger]="i.status === 'overdue'" [class.text-bg-warning]="i.status === 'soon'">{{ text(i.days) }}</span>
              </li>
            }
          </ul>
          @if (d.total > d.items.length) {
            <div class="card-footer small">
              <a routerLink="/fahrzeuge" class="text-decoration-none">… und {{ d.total - d.items.length }} weitere, alle Fahrzeuge ansehen</a>
            </div>
          }
        </div>
      }
    }
  `,
})
export class VehicleDeadlinesTileComponent implements OnInit {
  private api = inject(DashboardService);
  data = signal<VehicleDeadlines | null>(null);
  readonly text = deadlineText;
  readonly date = germanDate;

  ngOnInit() {
    this.api.vehicleDeadlines().subscribe({ next: (d) => this.data.set(d), error: () => {} });
  }
}
