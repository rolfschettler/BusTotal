import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { NgbDropdownModule } from '@ng-bootstrap/ng-bootstrap';
import { AuthService } from '../core/auth.service';
import { BrandService } from '../core/brand.service';

export const ROLLEN: Record<string, string> = {
  admin: 'Administrator',
  disponent: 'Disponent',
  buchhaltung: 'Buchhaltung',
  fahrer: 'Fahrer',
};

/** Rahmen für alle angemeldeten Seiten: feste Kopfleiste, Seitenleiste links, scrollender Inhalt. */
@Component({
  selector: 'app-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, NgbDropdownModule],
  templateUrl: './layout.component.html',
  styleUrl: './layout.component.scss',
})
export class LayoutComponent {
  auth = inject(AuthService);
  brand = inject(BrandService);
  rolle = (r?: string) => (r ? (ROLLEN[r] ?? r) : '');
}
