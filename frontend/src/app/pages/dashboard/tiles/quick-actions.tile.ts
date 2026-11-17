import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

/** Schnellzugriffe: neuen Kunden anlegen oder direkt in der Kundenliste suchen. */
@Component({
  selector: 'app-quick-actions-tile',
  imports: [FormsModule, RouterLink],
  styles: `
    :host { display: contents; }
    .full { grid-column: 1 / -1; }
  `,
  template: `
    <div class="card full">
      <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex flex-grow-1 gap-2" (ngSubmit)="search()" role="search">
          <input class="form-control" name="q" [(ngModel)]="q" placeholder="Kunde suchen: Name, Nummer, Ort …" aria-label="Kunde suchen" />
          <button class="btn btn-outline-primary" type="submit">Suchen</button>
        </form>
        <a routerLink="/kunden/neu" class="btn btn-primary">Neuer Kunde</a>
      </div>
    </div>
  `,
})
export class QuickActionsTileComponent {
  private router = inject(Router);
  q = '';

  search() {
    const q = this.q.trim();
    this.router.navigate(['/kunden'], { queryParams: q ? { q } : {} });
  }
}
