import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { APP_NAME, isSuperadminHost } from './core/app-info';
import { BrandService } from './core/brand.service';
import { PrintPreviewComponent } from './shared/print-preview.component';

/** Erkennungsfarbe der Betreiber-Oberfläche: bewusst dunkel, damit man sie nie mit einer Firma verwechselt. */
const OPERATOR_COLOR = '#2b3440';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, PrintPreviewComponent],
  // Die Druckvorschau für PDFs gehört an die Wurzel: Sie ist von jeder Seite aus erreichbar (PrintPreviewService).
  template: '<router-outlet /><app-print-preview />',
})
export class AppComponent {
  constructor() {
    const brand = inject(BrandService);
    if (isSuperadminHost()) {
      // Die Betreiber-Adresse hat keine Firma, also auch kein Branding vom Server.
      brand.apply({ name: `${APP_NAME} Betreiber`, brand_color: OPERATOR_COLOR, has_logo: false, logo_version: null });
    } else {
      // Farbe, Name und Logo der Firma laden, schon für die Login-Seite.
      brand.load();
    }
  }
}
