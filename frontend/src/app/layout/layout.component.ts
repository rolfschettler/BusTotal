import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../core/auth.service';

export const ROLLEN: Record<string, string> = {
  admin: 'Administrator',
  disponent: 'Disponent',
  buchhaltung: 'Buchhaltung',
  fahrer: 'Fahrer',
};

/** Rahmen für alle angemeldeten Seiten: Kopfzeile mit Navigation + Inhalt. */
@Component({
  selector: 'app-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './layout.component.html',
  styleUrl: './layout.component.scss',
})
export class LayoutComponent {
  auth = inject(AuthService);
  rolle = (r?: string) => (r ? (ROLLEN[r] ?? r) : '');
}
