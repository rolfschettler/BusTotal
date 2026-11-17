import { Component, inject } from '@angular/core';
import { AuthService } from '../../core/auth.service';

const ROLLEN: Record<string, string> = {
  admin: 'Administrator',
  disponent: 'Disponent',
  buchhaltung: 'Buchhaltung',
  fahrer: 'Fahrer',
};

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss',
})
export class DashboardComponent {
  auth = inject(AuthService);
  rolle = (r?: string) => (r ? (ROLLEN[r] ?? r) : '');
}
