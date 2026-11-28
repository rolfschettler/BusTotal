import { Component, HostListener, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter, map } from 'rxjs';
import { NgbDropdownModule } from '@ng-bootstrap/ng-bootstrap';
import { BrandService } from '../core/brand.service';
import { HelpPanelService } from '../help/help-panel.component';
import { SuperadminAuthService } from './superadmin-auth.service';

/** Rahmen der Betreiber-Oberfläche: gleicher Aufbau wie die Firmen-Anwendung (Seitenleiste, Arbeitsfläche). */
@Component({
  selector: 'app-superadmin-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, NgbDropdownModule],
  templateUrl: './superadmin-layout.component.html',
})
export class SuperadminLayoutComponent {
  auth = inject(SuperadminAuthService);
  brand = inject(BrandService);
  helpPanel = inject(HelpPanelService);

  private router = inject(Router);

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

  /** Solange das vorläufige Passwort gilt, gibt es kein Menü. */
  forced = computed(() => this.auth.user()?.must_change_password === true);

  @HostListener('document:keydown.f1', ['$event'])
  onF1(event: Event) {
    event.preventDefault();
    this.helpPanel.toggle();
  }
}
