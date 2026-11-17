import { Component, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { BrandService } from './core/brand.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  template: '<router-outlet />',
})
export class AppComponent {
  constructor() {
    // Farbe, Name und Logo der Firma laden, schon für die Login-Seite.
    inject(BrandService).load();
  }
}
