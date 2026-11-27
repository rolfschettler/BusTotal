import { Component, Input } from '@angular/core';
import { TripOrder } from '../../core/trip-order.service';
import { TRIP_ORDER_STEPS, nextStepText } from './trip-order-steps';

/** Ablaufleiste oben im Fahrtauftrag: wo er steht und was als Nächstes zu tun ist. */
@Component({
  selector: 'app-trip-order-steps',
  template: `
    <div class="bg-white border rounded px-3 py-2 mb-3">
      <ol class="list-unstyled d-flex flex-wrap align-items-center gap-2 mb-0 small" aria-label="Ablauf">
        @for (s of steps; track s.value; let i = $index; let last = $last) {
          <li class="d-flex align-items-center gap-2" [attr.aria-current]="!order.cancelled_at && i === current ? 'step' : null">
            @if (!order.cancelled_at && i === current) {
              <span class="badge text-bg-primary">{{ s.label }}</span>
            } @else {
              <span class="text-body-secondary">
                <i class="bi me-1" [class.bi-check-lg]="!order.cancelled_at && i < current" [class.bi-circle]="order.cancelled_at || i > current" aria-hidden="true"></i>{{ s.label }}
              </span>
            }
            @if (!last) { <i class="bi bi-chevron-right text-body-secondary" aria-hidden="true"></i> }
          </li>
        }
        @if (order.cancelled_at) { <li><span class="badge text-bg-danger">Abgesagt</span></li> }
      </ol>
      <div class="small mt-2"><i class="bi bi-arrow-right-circle me-1" aria-hidden="true"></i><strong>Nächster Schritt:</strong> {{ text }}</div>
    </div>
  `,
})
export class TripOrderStepsComponent {
  @Input({ required: true }) order!: Pick<TripOrder, 'status' | 'number' | 'cancelled_at'>;
  @Input() missing: string[] | null = null;
  readonly steps = TRIP_ORDER_STEPS;

  get current(): number {
    return this.steps.findIndex((s) => s.value === this.order.status);
  }

  get text(): string {
    return nextStepText(this.order, this.missing);
  }
}
