import { inject } from '@angular/core';
import { CanActivateChildFn, CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';
import { AuthService, Role } from './auth.service';

/** Lässt nur angemeldete Benutzer durch; lädt bei Seitenstart den Benutzer nach. */
export const authGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  const router = inject(Router);

  if (!auth.isLoggedIn()) {
    return router.createUrlTree(['/login']);
  }
  if (auth.user()) {
    return true;
  }
  return auth.loadUser().pipe(
    map(() => true),
    // Der Server nimmt das gespeicherte Token nicht an (abgelaufen, Firma gesperrt, nicht erreichbar ...):
    // Sitzung beenden. Würde das Token bleiben, schickte die Login-Seite (guestGuard) sofort zurück und
    // es entstünde eine Endlosschleife mit leerer Seite.
    catchError(() => {
      auth.clear();
      return of(false);
    }),
  );
};

/**
 * Wer noch das vorläufige Passwort hat, darf nur auf die Seite "Mein Konto" (Passwort ändern).
 * Läuft bei jedem Seitenwechsel im angemeldeten Bereich (canActivateChild).
 */
export const passwordChangeGuard: CanActivateChildFn = (_route, state) => {
  const mustChange = inject(AuthService).user()?.must_change_password;
  return mustChange && !state.url.startsWith('/konto') ? inject(Router).createUrlTree(['/konto']) : true;
};

/** Nur bestimmte Rollen dürfen auf die Seite (der Server prüft zusätzlich!). */
export const roleGuard =
  (...roles: Role[]): CanActivateFn =>
  () => {
    const auth = inject(AuthService);
    return roles.includes(auth.user()?.role as Role) ? true : inject(Router).createUrlTree(['/']);
  };

/** Angemeldete Benutzer brauchen die Login-Seite nicht mehr. */
export const guestGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  return auth.isLoggedIn() ? inject(Router).createUrlTree(['/']) : true;
};
