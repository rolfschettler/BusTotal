import { Component, computed, input } from '@angular/core';
import { HighlightComponent } from './highlight.component';

/** Die Felder, aus denen die Anzeige entsteht (passt auf Kunde und auf den Empfänger-Schnappschuss eines Belegs). */
export interface NameParts {
  company_name?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  postal_code?: string | null;
  city?: string | null;
  customer_number?: string | null;
  display_name?: string | null;
  name?: string | null;
}

/**
 * Ein Kunde in einer Trefferliste oder Auswahl, mit sichtbaren Treffern.
 *
 *   Mustermann, Max                      ← Zeile 1 (fett): die Person, „Nachname, Vorname“
 *   Napf GmbH · 20095 Hamburg · K-00012  ← Zeile 2 (klein): Firma, Ort, Nummer
 *
 * Privatkunden haben keine Firma, deshalb steht die Person vorn. Nur ohne Personennamen steht die Firma in Zeile 1.
 * Alle Teile sind mit <app-highlight> im Modus „start“ markiert, genau wie der Server am Wortanfang sucht
 * (App\Support\NameSearch). So ist in jeder Zeile zu sehen, WO der Treffer liegt.
 *
 *   <app-customer-name [customer]="d.customer" [query]="shownQuery()" [showNumber]="true" />
 */
@Component({
  selector: 'app-customer-name',
  imports: [HighlightComponent],
  host: { class: 'd-block' },
  template: `
    <span class="fw-semibold">
      @if (hasPerson()) {
        <app-highlight [text]="c().last_name" [query]="query()" mode="start" />@if (c().last_name && c().first_name) {,{{ ' ' }}}<app-highlight [text]="c().first_name" [query]="query()" mode="start" />
      } @else {
        <app-highlight [text]="firstLine()" [query]="query()" mode="start" />
      }
    </span>
    @if (secondLine().length) {
      <div class="small text-body-secondary text-truncate">
        @for (part of secondLine(); track $index) {
          @if (!$first) { <span aria-hidden="true"> · </span> }<app-highlight [text]="part" [query]="query()" mode="start" />
        }
      </div>
    }
  `,
})
export class CustomerNameComponent {
  customer = input<NameParts | null | undefined>(null);
  query = input<string | null | undefined>('');
  /** Kundennummer in Zeile 2 mitzeigen (Auswahlliste) */
  showNumber = input(false);
  /** Ort in Zeile 2 mitzeigen (aus, wenn die Liste dafür eine eigene Spalte hat) */
  showPlace = input(true);

  protected c = computed<NameParts>(() => this.customer() ?? {});

  protected hasPerson = computed(() => !!(this.c().last_name || this.c().first_name));

  /** Ohne Person: Firma, sonst der fertige Anzeigename bzw. die Nummer */
  protected firstLine = computed(() => this.c().company_name || this.c().display_name || this.c().name || this.c().customer_number || '');

  protected secondLine = computed(() => {
    const c = this.c();
    const parts: string[] = [];
    if (this.hasPerson() && c.company_name) parts.push(c.company_name);
    const place = [c.postal_code, c.city].filter(Boolean).join(' ');
    if (this.showPlace() && place) parts.push(place);
    if (this.showNumber() && c.customer_number) parts.push(c.customer_number);
    return parts;
  });
}
