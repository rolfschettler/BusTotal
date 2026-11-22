import { Component, HostListener, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter, map } from 'rxjs';
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

  private router = inject(Router);

  /**
   * Listenseiten (Route mit data: { fullPage: true }) nutzen die ganze Inhaltsfläche: kein Außenabstand, kein
   * eigener Scrollbalken des Rahmens. Sie scrollen selbst, damit ihr Tabellenkopf kleben kann.
   */
  fullPage = toSignal(
    this.router.events.pipe(
      filter((e) => e instanceof NavigationEnd),
      map(() => this.readFullPage()),
    ),
    { initialValue: this.readFullPage() },
  );

  private readFullPage(): boolean {
    let r = this.router.routerState.snapshot.root;
    let full = r.data['fullPage'] === true;
    while (r.firstChild) {
      r = r.firstChild;
      if (r.data['fullPage'] !== undefined) full = r.data['fullPage'] === true;
    }
    return full;
  }

  // Wer welche Menüpunkte sieht (die Routen und der Server prüfen die Rolle zusätzlich).
  /** Alle außer Fahrer: Fahrer sehen nur Start und Hilfe. */
  isStaff = computed(() => this.auth.user()?.role !== 'fahrer');
  /** Streckenplanung und Fahrer: Administrator und Disponent. */
  canPlan = computed(() => ['admin', 'disponent'].includes(this.auth.user()?.role ?? ''));
  isAdmin = computed(() => this.auth.user()?.role === 'admin');

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
