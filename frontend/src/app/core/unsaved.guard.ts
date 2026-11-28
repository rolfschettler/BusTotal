import { CanDeactivateFn } from '@angular/router';

/** Eine Seite mit Eingaben: Sie entscheidet selbst, ob man sie verlassen darf, und fragt dazu bei Bedarf nach. */
export interface LeaveCheck {
  canLeave(): boolean | Promise<boolean>;
}

/** Verlassen über Menü, Link oder „Zurück“: Hat die Seite ungespeicherte Eingaben, fragt sie vorher nach. */
export const unsavedGuard: CanDeactivateFn<LeaveCheck> = (component) => component.canLeave();
