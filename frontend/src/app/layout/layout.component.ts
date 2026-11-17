import { Component, HostListener, computed, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { NgbDropdownModule } from '@ng-bootstrap/ng-bootstrap';
import { AuthService } from '../core/auth.service';
import { BrandService } from '../core/brand.service';
import { roleLabel } from '../core/roles';
import { HelpPanelService } from '../help/help-panel.component';

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
  helpPanel = inject(HelpPanelService);

  /** Solange das vorläufige Passwort gilt, gibt es kein Menü, nur die Passwortänderung. */
  forced = computed(() => this.auth.user()?.must_change_password === true);

  /** F1 öffnet die Hilfe zur aktuellen Seite statt der Browser-Hilfe. */
  @HostListener('document:keydown.f1', ['$event'])
  onF1(event: Event) {
    event.preventDefault();
    this.helpPanel.toggle();
  }

  rolle = roleLabel;
}
