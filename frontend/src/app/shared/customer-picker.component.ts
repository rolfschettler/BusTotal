import { Component, inject, input, output, signal } from '@angular/core';
import { CustomerService } from '../core/customer.service';
import { CustomerNameComponent, NameParts } from './customer-name.component';

/** Der gewählte Kunde in der Kurzform der Auswahl. */
export interface PickedCustomer extends NameParts {
  id: string;
  display_name: string;
  customer_number: string;
}

/**
 * Kundenauswahl: erst Suchfeld mit aufklappender Trefferliste, nach der Wahl steht der Kunde im Feld, „Ändern“ hebt die Wahl auf.
 * Gleiches Verhalten wie im Belegformular und in der Belegliste (Muster: docs/ClaudeCodePatterns/SearchPattern.md): ab 2 Zeichen,
 * 300 ms Verzögerung, höchstens 8 Treffer, Treffer am Wortanfang markiert, Person „Nachname, Vorname“ vorn.
 *
 *   <app-customer-picker [customer]="kunde()" (picked)="kunde.set($event)" (cleared)="kunde.set(null)" inputId="kunde" />
 */
@Component({
  selector: 'app-customer-picker',
  imports: [CustomerNameComponent],
  template: `
    @if (customer(); as c) {
      <div class="input-group">
        <input class="form-control" [id]="inputId()" [value]="c.display_name + ' (' + c.customer_number + ')'" readonly [disabled]="disabled()" />
        @if (!disabled()) {
          <button type="button" class="btn btn-outline-secondary" (click)="cleared.emit()">Ändern</button>
        }
      </div>
    } @else {
      <input class="form-control" [id]="inputId()" autocomplete="off" [disabled]="disabled()" [value]="query()"
             (input)="onSearch($any($event.target).value)" />
      <div class="form-text">{{ hint() }}</div>
      @if (hits().length) {
        <div class="list-group mt-1">
          @for (h of hits(); track h.id) {
            <button type="button" class="list-group-item list-group-item-action" (click)="pick(h)">
              <app-customer-name [customer]="h" [query]="query()" [showNumber]="true" />
            </button>
          }
        </div>
      }
    }
  `,
})
export class CustomerPickerComponent {
  private customers = inject(CustomerService);

  /** Der gewählte Kunde, null = noch keiner */
  customer = input<PickedCustomer | null>(null);
  inputId = input('customerPicker');
  disabled = input(false);
  hint = input('Nachname, Kundennummer oder Ort eingeben, mindestens zwei Zeichen. Mit Komma nach Nachname und Vorname: Mustermann,Max');
  picked = output<PickedCustomer>();
  cleared = output<void>();

  query = signal('');
  hits = signal<PickedCustomer[]>([]);
  private timer?: ReturnType<typeof setTimeout>;

  onSearch(q: string) {
    this.query.set(q);
    clearTimeout(this.timer);
    if (q.trim().length < 2) {
      this.hits.set([]);
      return;
    }
    this.timer = setTimeout(() => {
      this.customers.list({ q: q.trim(), type: '', archived: false, page: 1, sort: 'name', dir: 'asc' }).subscribe((p) => {
        // Eine ältere Antwort darf eine neuere Eingabe nicht überschreiben.
        if (q !== this.query()) return;
        this.hits.set(
          p.data.slice(0, 8).map((c) => ({
            id: c.id, display_name: c.display_name, customer_number: c.customer_number, company_name: c.company_name,
            first_name: c.first_name, last_name: c.last_name, street: c.street, postal_code: c.postal_code, city: c.city,
          })),
        );
      });
    }, 300);
  }

  pick(c: PickedCustomer) {
    this.query.set('');
    this.hits.set([]);
    this.picked.emit(c);
  }
}
