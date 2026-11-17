import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { ApplicationConfig, LOCALE_ID, provideZoneChangeDetection } from '@angular/core';
import { registerLocaleData } from '@angular/common';
import localeDe from '@angular/common/locales/de';
import { provideRouter } from '@angular/router';

import { authInterceptor } from './core/auth.interceptor';
import { routes } from './app.routes';
import { isSuperadminHost } from './core/app-info';
import { superadminRoutes } from './superadmin/superadmin.routes';

registerLocaleData(localeDe);

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }),
    // An der Betreiber-Adresse (admin.<basis>) läuft die Betreiber-Oberfläche, überall sonst die Firmen-Anwendung.
    provideRouter(isSuperadminHost() ? superadminRoutes : routes),
    provideHttpClient(withInterceptors([authInterceptor])),
    { provide: LOCALE_ID, useValue: 'de-DE' },
  ],
};
