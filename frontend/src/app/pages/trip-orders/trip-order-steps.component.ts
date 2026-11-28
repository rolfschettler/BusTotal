import { Component, Input } from '@angular/core';
import { TripOrder } from '../../core/trip-order.service';
import { LineStep, RouteLineComponent } from '../../shared/route-line.component';
import { TRIP_ORDER_STEPS, nextStepText } from './trip-order-steps';

/** Ablaufleiste oben im Fahrtauftrag: wo er steht und was als Nächstes zu tun ist. */
@Component({
  selector: 'app-trip-order-steps',
  imports: [RouteLineComponent],
  template: `
    <app-route-line [steps]="line">
      <div>
        @if (order.cancelled_at) { <span class="flag flag-danger me-2">Abgesagt</span> }
        <strong>Nächster Schritt:</strong> {{ text }}
      </div>
    </app-route-line>
  `,
})
export class TripOrderStepsComponent {
  @Input({ required: true }) order!: Pick<TripOrder, 'status' | 'number' | 'cancelled_at'>;
  @Input() missing: string[] | null = null;
  /** Erledigt sind alle Haltestellen vor der aktuellen; abgesagt: keine aktuelle. */
  get line(): LineStep[] {
    const current = TRIP_ORDER_STEPS.findIndex((s) => s.value === this.order.status);
    return TRIP_ORDER_STEPS.map((s, i) => ({
      label: s.label,
      state: this.order.cancelled_at ? 'open' : i < current || this.order.status === 'erledigt' ? 'done' : i === current ? 'current' : 'open',
    }));
  }

  get text(): string {
    return nextStepText(this.order, this.missing);
  }
}
