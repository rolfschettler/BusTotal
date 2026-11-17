import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { AuthService } from './auth.service';

/** Hängt den Token an jede /api-Anfrage und meldet bei 401 ab. */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
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
      return throwError(() => err);
    }),
  );
};
