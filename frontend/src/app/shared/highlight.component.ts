import { Component, computed, input } from '@angular/core';
import { HighlightMode, highlightSegments } from './highlight';

/**
 * Zeigt einen Text und hebt darin die Treffer eines Suchbegriffs hervor.
 *
 * Regel für ALLE Suchen in BusTotal: Treffer in einer Trefferliste sind immer hervorgehoben.
 *
 *   <app-highlight [text]="kunde.display_name" [query]="suchbegriff" />
 *
 * Der Text wird nie als HTML eingefügt, sondern stückweise ausgegeben und damit von Angular sicher
 * maskiert. Ein Suchbegriff oder Kundenname mit "<script>" bleibt harmloser Text.
 */
@Component({
  selector: 'app-highlight',
  // Zwischen den Stücken darf kein Leerraum entstehen ("Nic ole"): Span und Mark stehen direkt hintereinander.
  template: `@for (s of segments(); track $index) {@if (s.hit) {<mark class="px-0">{{ s.text }}</mark>} @else {<span>{{ s.text }}</span>}}`,
})
export class HighlightComponent {
  text = input<string | number | null | undefined>('');
  query = input<string | null | undefined>('');
  mode = input<HighlightMode>('phrase');

  segments = computed(() => highlightSegments(this.text(), this.query(), this.mode()));
}
