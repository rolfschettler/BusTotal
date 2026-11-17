import { Routes } from '@angular/router';
import { authGuard, guestGuard, roleGuard } from './core/auth.guard';

// data.help = "thema#sprungmarke": Das Hilfethema der Seite. Es steuert das Fragezeichen in der
// Kopfleiste. Die Hilfetexte liegen unter public/help/<thema>.md.
export const routes: Routes = [
  {
    path: 'login',
    canActivate: [guestGuard],
    loadComponent: () => import('./pages/login/login.component').then((m) => m.LoginComponent),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./layout/layout.component').then((m) => m.LayoutComponent),
    children: [
      {
        path: '',
        pathMatch: 'full',
        data: { help: 'start#startseite' },
        loadComponent: () => import('./pages/dashboard/dashboard.component').then((m) => m.DashboardComponent),
      },
      {
        path: 'kunden',
        canActivate: [roleGuard('admin', 'disponent', 'buchhaltung')],
        children: [
          {
            path: '',
            pathMatch: 'full',
            data: { help: 'kunden#kundenliste' },
            loadComponent: () => import('./pages/customers/customer-list.component').then((m) => m.CustomerListComponent),
          },
          {
            path: 'neu',
            data: { help: 'kunden#kunde-anlegen' },
            loadComponent: () => import('./pages/customers/customer-form.component').then((m) => m.CustomerFormComponent),
          },
          {
            path: ':id',
            data: { help: 'kunden#stammdaten' },
            loadComponent: () => import('./pages/customers/customer-form.component').then((m) => m.CustomerFormComponent),
          },
        ],
      },
      {
        path: 'einstellungen',
        canActivate: [roleGuard('admin', 'disponent', 'buchhaltung')],
        data: { help: 'einstellungen#firmendaten' },
        loadComponent: () => import('./pages/settings/settings.component').then((m) => m.SettingsComponent),
      },
      {
        path: 'hilfe',
        children: [
          { path: '', pathMatch: 'full', redirectTo: 'start' },
          {
            path: ':topic',
            loadComponent: () => import('./help/help-page.component').then((m) => m.HelpPageComponent),
          },
        ],
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
