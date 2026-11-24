import { CanDeactivateFn } from '@angular/router';
import { RoutePlanningComponent } from './route-planning.component';

/** Die Streckenplanung verlassen: Hat sie ungespeicherte Eingaben, fragt die Komponente vorher nach. */
export const planningLeaveGuard: CanDeactivateFn<RoutePlanningComponent> = (component) => component.canLeave();
