import { Routes } from '@angular/router';
import { operatorGuestGuard, operatorPasswordGuard, superadminGuard } from './superadmin.guard';

/**
 * Routen der Betreiber-Oberfläche (nur an der Betreiber-Adresse, siehe app.config.ts).
 * data.help = "thema#anker" wie in der Firmen-Anwendung; die Hilfe liegt unter public/help/betreiber.md.
 */
export const superadminRoutes: Routes = [
  {
    path: 'login',
    canActivate: [operatorGuestGuard],
    loadComponent: () => import('./superadmin-login.component').then((m) => m.SuperadminLoginComponent),
  },
  {
    path: '',
    canActivate: [superadminGuard],
    canActivateChild: [operatorPasswordGuard],
    loadComponent: () => import('./superadmin-layout.component').then((m) => m.SuperadminLayoutComponent),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'firmen' },
      {
        path: 'firmen',
        // fullPage: Listenseite nach dem Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md)
        data: { help: 'betreiber#firmenliste', fullPage: true },
        loadComponent: () => import('./tenants.component').then((m) => m.TenantsComponent),
      },
      {
        path: 'konto',
        data: { help: 'betreiber#passwort' },
        loadComponent: () => import('./superadmin-account.component').then((m) => m.SuperadminAccountComponent),
      },
      {
        path: 'hilfe',
        children: [
          { path: '', pathMatch: 'full', redirectTo: 'betreiber' },
          {
            path: ':topic',
            loadComponent: () => import('../help/help-page.component').then((m) => m.HelpPageComponent),
          },
        ],
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
