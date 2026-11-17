import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { AuthService } from './auth.service';

/** Hängt den Token an jede /api-Anfrage, meldet bei 401 ab und leitet bei Passwortpflicht um. */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const token = auth.token();

  if (token && req.url.startsWith('/api')) {
    req = req.clone({ setHeaders: { Authorization: `Bearer ${token}` } });
  }

  return next(req).pipe(
    catchError((err: HttpErrorResponse) => {
      // 401 bei Login selbst = falsche Zugangsdaten, kein Sitzungsablauf
      if (err.status === 401 && !req.url.endsWith('/api/login')) {
        auth.clear();
      }
      // Der Server verlangt zuerst ein neues Passwort (zum Beispiel nach einem Zurücksetzen).
      if (err.status === 403 && err.error?.code === 'password_change_required') {
        router.navigate(['/konto']);
      }
      return throwError(() => err);
    }),
  );
};
