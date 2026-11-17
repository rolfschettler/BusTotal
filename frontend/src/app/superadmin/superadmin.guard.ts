import { inject } from '@angular/core';
import { CanActivateChildFn, CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { SuperadminAuthService } from './superadmin-auth.service';

/** Nur angemeldete Betreiber; lädt beim Seitenstart den Betreiber nach. */
export const superadminGuard: CanActivateFn = () => {
  const auth = inject(SuperadminAuthService);
  const router = inject(Router);

  if (!auth.isLoggedIn()) return router.createUrlTree(['/login']);
  if (auth.user()) return true;
  return auth.loadUser().pipe(
    map(() => true),
    // Token wird nicht angenommen: Sitzung beenden, sonst schickt die Login-Seite sofort zurück (Endlosschleife).
    catchError(() => {
      auth.clear();
      return of(false);
    }),
  );
};

/** Wer noch das vorläufige Passwort hat, darf nur auf "Mein Konto". */
export const operatorPasswordGuard: CanActivateChildFn = (_route, state) => {
  const mustChange = inject(SuperadminAuthService).user()?.must_change_password;
  return mustChange && !state.url.startsWith('/konto') ? inject(Router).createUrlTree(['/konto']) : true;
};

export const operatorGuestGuard: CanActivateFn = () =>
  inject(SuperadminAuthService).isLoggedIn() ? inject(Router).createUrlTree(['/']) : true;
