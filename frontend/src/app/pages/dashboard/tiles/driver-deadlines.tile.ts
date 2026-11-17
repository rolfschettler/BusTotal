import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { DashboardService, DriverDeadlines } from '../../../core/dashboard.service';
import { deadlineText, germanDate } from '../../../shared/deadline';

/**
 * Fällige Fristen beim Personal (Führerschein, P-Schein, Fahrerkarte, Code 95, Untersuchung):
 * abgelaufen oder in 30 Tagen fällig. Die Kachel erscheint nur, wenn es etwas zu tun gibt.
 */
@Component({
  selector: 'app-driver-deadlines-tile',
  imports: [RouterLink],
  styles: `:host { display: contents; }`,
  template: `
    @if (data(); as d) {
      @if (d.total > 0) {
        <div class="card">
          <div class="card-header fw-semibold">Fällige Fristen beim Personal</div>
          <ul class="list-group list-group-flush">
            @for (i of d.items; track i.driver_id + i.kind) {
              <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                <div>
                  <a [routerLink]="['/fahrer', i.driver_id]" class="fw-semibold text-decoration-none">{{ i.driver }}</a>
                  <div class="small text-body-secondary">{{ i.label }} · {{ date(i.date) }}</div>
                </div>
                <span class="badge align-self-center text-nowrap" [class.text-bg-danger]="i.status === 'overdue'" [class.text-bg-warning]="i.status === 'soon'">{{ text(i.days) }}</span>
              </li>
            }
          </ul>
          @if (d.total > d.items.length) {
            <div class="card-footer small">
              <a routerLink="/fahrer" class="text-decoration-none">… und {{ d.total - d.items.length }} weitere, alle Fahrer ansehen</a>
            </div>
          }
        </div>
      }
    }
  `,
})
export class DriverDeadlinesTileComponent implements OnInit {
  private api = inject(DashboardService);
  data = signal<DriverDeadlines | null>(null);
  readonly text = deadlineText;
  readonly date = germanDate;

  ngOnInit() {
    this.api.driverDeadlines().subscribe({ next: (d) => this.data.set(d), error: () => {} });
  }
}
