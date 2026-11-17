import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
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
    catchError(() => of(router.createUrlTree(['/login']))),
  );
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
