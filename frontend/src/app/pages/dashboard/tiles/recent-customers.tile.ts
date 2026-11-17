import { Component, OnInit, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { DashboardService, RecentCustomer } from '../../../core/dashboard.service';

/** Die fünf zuletzt geänderten oder angelegten Kunden. */
@Component({
  selector: 'app-recent-customers-tile',
  imports: [RouterLink],
  styles: `:host { display: contents; }`,
  template: `
    @if (customers(); as list) {
      <div class="card">
        <div class="card-header fw-semibold">Zuletzt bearbeitete Kunden</div>
        @if (list.length) {
          <ul class="list-group list-group-flush">
            @for (c of list; track c.id) {
              <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                <div>
                  <a [routerLink]="['/kunden', c.id]" class="fw-semibold text-decoration-none">{{ c.display_name }}</a>
                  <div class="small text-body-secondary">
                    <code>{{ c.customer_number }}</code>@if (c.city) { · {{ c.postal_code }} {{ c.city }} }
                  </div>
                </div>
                <span class="small text-body-secondary text-nowrap">{{ when(c.updated_at) }}</span>
              </li>
            }
          </ul>
        } @else {
          <div class="card-body text-body-secondary">Noch keine Kunden angelegt.</div>
        }
      </div>
    }
  `,
})
export class RecentCustomersTileComponent implements OnInit {
  private api = inject(DashboardService);
  customers = signal<RecentCustomer[] | null>(null);

  ngOnInit() {
    this.api.recentCustomers().subscribe({ next: (c) => this.customers.set(c), error: () => {} });
  }

  /** "heute", "gestern", "vor 3 Tagen" – danach das Datum. */
  when(iso: string): string {
    const d = new Date(iso);
    const days = Math.floor((startOfDay(new Date()) - startOfDay(d)) / 86_400_000);
    if (days <= 0) return 'heute';
    if (days === 1) return 'gestern';
    if (days < 7) return `vor ${days} Tagen`;
    return d.toLocaleDateString('de-DE');
  }
}

function startOfDay(d: Date): number {
  return new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
}
