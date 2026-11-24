import { Routes } from '@angular/router';
import { authGuard, guestGuard, passwordChangeGuard, roleGuard } from './core/auth.guard';
import { planningLeaveGuard } from './pages/route-planning/leave.guard';

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
    // Solange das vorläufige Passwort gilt, ist nur "Mein Konto" erreichbar.
    canActivateChild: [passwordChangeGuard],
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
            // fullPage: Listenseite nach dem Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md)
            data: { help: 'kunden#kundenliste', fullPage: true },
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
        path: 'fahrtauftraege',
        // Lesen: Admin, Disponent, Buchhaltung; disponieren und ausgeben Admin und Disponent (das Formular sperrt sich selbst).
        // Fahrtaufträge entstehen mit der festgeschriebenen Auftragsbestätigung, hier wird nichts angelegt.
        canActivate: [roleGuard('admin', 'disponent', 'buchhaltung')],
        children: [
          {
            path: '',
            pathMatch: 'full',
            data: { help: 'fahrten#fahrtauftrag', fullPage: true },
            loadComponent: () => import('./pages/trip-orders/trip-order-list.component').then((m) => m.TripOrderListComponent),
          },
          {
            path: ':id',
            data: { help: 'fahrten#fahrtauftrag' },
            loadComponent: () => import('./pages/trip-orders/trip-order-form.component').then((m) => m.TripOrderFormComponent),
          },
        ],
      },
      {
        path: 'reisen',
        // Nur lesen: Admin, Disponent, Buchhaltung. Reisen entstehen mit dem ersten Angebot aus der Streckenplanung.
        canActivate: [roleGuard('admin', 'disponent', 'buchhaltung')],
        children: [
          {
            path: '',
            pathMatch: 'full',
            data: { help: 'belege#reisen', fullPage: true },
            loadComponent: () => import('./pages/journeys/journey-list.component').then((m) => m.JourneyListComponent),
          },
          {
            path: ':id',
            data: { help: 'belege#reisen' },
            loadComponent: () => import('./pages/journeys/journey-detail.component').then((m) => m.JourneyDetailComponent),
          },
        ],
      },
      {
        path: 'tour-archiv',
        // Lesen: Admin, Disponent, Buchhaltung. Löschen und Planen dürfen nur Admin und Disponent (Liste und Server prüfen das).
        canActivate: [roleGuard('admin', 'disponent', 'buchhaltung')],
        data: { help: 'strecken#tour-archiv-liste', fullPage: true },
        loadComponent: () => import('./pages/trips/trip-list.component').then((m) => m.TripListComponent),
      },
      {
        path: 'belege',
        // Entwürfe: Admin, Disponent, Buchhaltung. Festschreiben und Stornieren nur Admin und Buchhaltung (Formular und Server prüfen das).
        canActivate: [roleGuard('admin', 'disponent', 'buchhaltung')],
        children: [
          {
            path: '',
            pathMatch: 'full',
            data: { help: 'belege#belegliste', fullPage: true },
            loadComponent: () => import('./pages/documents/document-list.component').then((m) => m.DocumentListComponent),
          },
          {
            path: 'neu',
            data: { help: 'belege#beleg-anlegen' },
            loadComponent: () => import('./pages/documents/document-form.component').then((m) => m.DocumentFormComponent),
          },
          {
            path: ':id',
            data: { help: 'belege#entwurf-und-festschreiben' },
            loadComponent: () => import('./pages/documents/document-form.component').then((m) => m.DocumentFormComponent),
          },
        ],
      },
      {
        path: 'fahrzeuge',
        // Lesen: Admin, Disponent, Buchhaltung. Ändern dürfen nur Admin und Disponent (Formular und Server prüfen das).
        canActivate: [roleGuard('admin', 'disponent', 'buchhaltung')],
        children: [
          {
            path: '',
            pathMatch: 'full',
            data: { help: 'fuhrpark#fahrzeugliste', fullPage: true },
            loadComponent: () => import('./pages/vehicles/vehicle-list.component').then((m) => m.VehicleListComponent),
          },
          {
            path: 'neu',
            canActivate: [roleGuard('admin', 'disponent')],
            data: { help: 'fuhrpark#fahrzeug-anlegen' },
            loadComponent: () => import('./pages/vehicles/vehicle-form.component').then((m) => m.VehicleFormComponent),
          },
          {
            path: ':id',
            data: { help: 'fuhrpark#stammdaten' },
            loadComponent: () => import('./pages/vehicles/vehicle-form.component').then((m) => m.VehicleFormComponent),
          },
        ],
      },
      {
        path: 'fahrer',
        // Personendaten: nur Administrator und Disponent (der Server prüft zusätzlich).
        canActivate: [roleGuard('admin', 'disponent')],
        children: [
          {
            path: '',
            pathMatch: 'full',
            data: { help: 'fahrer#fahrerliste', fullPage: true },
            loadComponent: () => import('./pages/drivers/driver-list.component').then((m) => m.DriverListComponent),
          },
          {
            path: 'neu',
            data: { help: 'fahrer#fahrer-anlegen' },
            loadComponent: () => import('./pages/drivers/driver-form.component').then((m) => m.DriverFormComponent),
          },
          {
            path: ':id',
            data: { help: 'fahrer#stammdaten' },
            loadComponent: () => import('./pages/drivers/driver-form.component').then((m) => m.DriverFormComponent),
          },
        ],
      },
      {
        path: 'streckenplanung',
        // Ruft einen externen Routing-Dienst auf (Kontingent): nur Administrator und Disponent (der Server prüft zusätzlich).
        canActivate: [roleGuard('admin', 'disponent')],
        // Ungespeicherte Eingaben gehen beim Verlassen verloren: vorher nachfragen
        canDeactivate: [planningLeaveGuard],
        data: { help: 'strecken#streckenplanung' },
        loadComponent: () => import('./pages/route-planning/route-planning.component').then((m) => m.RoutePlanningComponent),
      },
      {
        path: 'einstellungen',
        canActivate: [roleGuard('admin', 'disponent', 'buchhaltung')],
        data: { help: 'einstellungen#firmendaten' },
        loadComponent: () => import('./pages/settings/settings.component').then((m) => m.SettingsComponent),
      },
      {
        path: 'konto',
        data: { help: 'benutzer#passwort-aendern' },
        loadComponent: () => import('./pages/account/account.component').then((m) => m.AccountComponent),
      },
      {
        path: 'benutzer',
        canActivate: [roleGuard('admin')],
        // fullPage: Listenseite nach dem Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md)
        data: { help: 'benutzer#benutzerliste', fullPage: true },
        loadComponent: () => import('./pages/users/users.component').then((m) => m.UsersComponent),
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
