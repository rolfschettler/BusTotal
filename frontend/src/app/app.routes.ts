import { Routes } from '@angular/router';
import { authGuard, guestGuard, roleGuard } from './core/auth.guard';

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
        loadComponent: () => import('./pages/dashboard/dashboard.component').then((m) => m.DashboardComponent),
      },
      {
        path: 'kunden',
        canActivate: [roleGuard('admin', 'disponent', 'buchhaltung')],
        children: [
          {
            path: '',
            pathMatch: 'full',
            loadComponent: () => import('./pages/customers/customer-list.component').then((m) => m.CustomerListComponent),
          },
          {
            path: 'neu',
            loadComponent: () => import('./pages/customers/customer-form.component').then((m) => m.CustomerFormComponent),
          },
          {
            path: ':id',
            loadComponent: () => import('./pages/customers/customer-form.component').then((m) => m.CustomerFormComponent),
          },
        ],
      },
      {
        path: 'einstellungen',
        loadComponent: () => import('./pages/settings/settings.component').then((m) => m.SettingsComponent),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
