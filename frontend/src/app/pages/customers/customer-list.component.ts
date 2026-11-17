import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { Customer, CustomerService, CustomerType } from '../../core/customer.service';
import { hasHit } from '../../shared/highlight';
import { HighlightComponent } from '../../shared/highlight.component';

@Component({
  selector: 'app-customer-list',
  imports: [FormsModule, RouterLink, HighlightComponent],
  templateUrl: './customer-list.component.html',
})
export class CustomerListComponent implements OnInit {
  private api = inject(CustomerService);
  private route = inject(ActivatedRoute);
  auth = inject(AuthService);

  q = '';
  type = '';
  archived = false;
  page = signal(1);
  lastPage = signal(1);
  total = signal(0);
  rows = signal<Customer[]>([]);
  types = signal<CustomerType[]>([]);
  loading = signal(false);
  error = signal<string | null>(null);
  /** Der Suchbegriff, zu dem die angezeigte Liste gehört (Grundlage der Markierung) */
  shownQuery = signal('');

  private requestNo = 0;
  private timer?: ReturnType<typeof setTimeout>;

  ngOnInit() {
    // Die Suche von der Startseite kommt als ?q=… an.
    this.q = this.route.snapshot.queryParamMap.get('q') ?? '';
    this.api.types().subscribe((t) => this.types.set(t));
    this.load();
  }

  /** Erst 300 ms nach der letzten Eingabe suchen, nicht bei jedem Buchstaben. */
  onSearch() {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.filterChanged(), 300);
  }

  filterChanged() {
    this.page.set(1);
    this.load();
  }

  go(p: number) {
    this.page.set(p);
    this.load();
  }

  typeLabel(value: string) {
    return this.types().find((t) => t.value === value)?.label ?? value;
  }

  /**
   * Felder, in denen die Suche trifft, die in der Liste aber NICHT zu sehen sind. Ohne Hinweis stünde
   * eine Zeile da, in der nichts markiert ist. Die Regel: Treffer sind immer sichtbar hervorgehoben.
   */
  hiddenHits(c: Customer): { label: string; value: string }[] {
    const q = this.shownQuery();
    if (!q) return [];
    const out: { label: string; value: string }[] = [];
    if (hasHit(c.debitor_number, q)) out.push({ label: 'Debitorennr.', value: String(c.debitor_number) });
    // Bei Kunden mit Firmennamen zeigt die Liste nur diesen, nicht Vor- und Nachname.
    if (c.company_name) {
      if (hasHit(c.first_name, q)) out.push({ label: 'Vorname', value: c.first_name ?? '' });
      if (hasHit(c.last_name, q)) out.push({ label: 'Nachname', value: c.last_name ?? '' });
    }
    return out;
  }

  private load() {
    this.loading.set(true);
    this.error.set(null);
    const query = this.q.trim();
    const request = ++this.requestNo;

    this.api.list({ q: query, type: this.type, archived: this.archived, page: this.page() }).subscribe({
      next: (p) => {
        if (request !== this.requestNo) return; // eine neuere Suche läuft schon: diese Antwort ist veraltet
        this.rows.set(p.data);
        // Markiert wird der Begriff, der diese Liste erzeugt hat, nicht der inzwischen weiter getippte.
        this.shownQuery.set(query);
        this.lastPage.set(p.last_page);
        this.total.set(p.total);
        this.loading.set(false);
      },
      error: () => {
        if (request !== this.requestNo) return;
        this.error.set('Kunden konnten nicht geladen werden.');
        this.loading.set(false);
      },
    });
  }
}
