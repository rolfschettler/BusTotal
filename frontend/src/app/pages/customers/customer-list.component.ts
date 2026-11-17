import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { Customer, CustomerService, CustomerType } from '../../core/customer.service';
import { hasHit } from '../../shared/highlight';
import { HighlightComponent } from '../../shared/highlight.component';
import { StickyFilterBarDirective } from '../../shared/sticky-filter-bar.directive';

/**
 * Kundenliste. Lebende Referenz des Tabellen-Musters (docs/patterns/TableViewPattern.md):
 * Seitenkopf mit Zähler und Schaltflächen, klebende Filterleiste, klebender Tabellenkopf mit Sortierung,
 * Aktionsspalte, Auswahlzeile. Weil die Menge groß werden kann, filtert, sortiert und blättert der SERVER.
 */
@Component({
  selector: 'app-customer-list',
  imports: [FormsModule, RouterLink, HighlightComponent, StickyFilterBarDirective],
  templateUrl: './customer-list.component.html',
  // list-page: aktiviert das globale Listenlayout. --sheet-w: Breite des Blattes (Summe der Spalten in <colgroup>).
  host: { class: 'list-page', style: '--sheet-w: 1084px' },
})
export class CustomerListComponent implements OnInit {
  private api = inject(CustomerService);
  private route = inject(ActivatedRoute);
  private confirm = inject(ConfirmService);
  auth = inject(AuthService);

  // Filter (mit der Eingabe verbunden)
  q = '';
  type = '';
  archived = false;

  // Sortierung und Seite
  sortCol = signal('name');
  sortDir = signal<'asc' | 'desc'>('asc');
  page = signal(1);
  lastPage = signal(1);
  total = signal(0);

  rows = signal<Customer[]>([]);
  types = signal<CustomerType[]>([]);
  /** Die in der Liste markierte Zeile (nur optisch, wie im Muster) */
  currentId = signal<string | null>(null);
  loaded = signal(false);
  loading = signal(false);
  error = signal<string | null>(null);
  message = signal<string | null>(null);
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

  // ---------- Bedienung ----------

  /** Erst 300 ms nach der letzten Eingabe suchen, nicht bei jedem Buchstaben. */
  onSearch() {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.filterChanged(), 300);
  }

  filterChanged() {
    this.page.set(1);
    this.load();
  }

  refresh() {
    this.message.set(null);
    this.load();
  }

  go(p: number) {
    this.page.set(p);
    this.load();
  }

  /** Klick auf einen Spaltenkopf: neue Spalte aufsteigend, dieselbe Spalte wechselt die Richtung. */
  sort(col: string) {
    if (this.sortCol() === col) {
      this.sortDir.update((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      this.sortCol.set(col);
      this.sortDir.set('asc');
    }
    this.page.set(1);
    this.load();
  }

  sortIcon(col: string): string {
    if (this.sortCol() !== col) return 'bi-chevron-expand';
    return this.sortDir() === 'asc' ? 'bi-chevron-up' : 'bi-chevron-down';
  }

  ariaSort(col: string): 'ascending' | 'descending' | 'none' {
    if (this.sortCol() !== col) return 'none';
    return this.sortDir() === 'asc' ? 'ascending' : 'descending';
  }

  typeLabel(value: string) {
    return this.types().find((t) => t.value === value)?.label ?? value;
  }

  // ---------- Aktionen ----------

  async archive(c: Customer) {
    const ok = await this.confirm.ask({
      title: 'Kunde archivieren',
      message: `${c.display_name} verschwindet aus der Liste, bleibt aber für Belege erhalten.`,
      confirmLabel: 'Archivieren',
    });
    if (!ok) return;
    this.api.archive(c.id).subscribe({
      next: () => {
        this.message.set(`${c.display_name} wurde archiviert.`);
        this.load();
      },
      error: () => this.error.set('Das Archivieren hat nicht geklappt.'),
    });
  }

  restore(c: Customer) {
    this.api.restore(c.id).subscribe({
      next: () => {
        this.message.set(`${c.display_name} wurde wiederhergestellt.`);
        this.load();
      },
      error: () => this.error.set('Das Wiederherstellen hat nicht geklappt.'),
    });
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

  // ---------- Laden ----------

  private load() {
    this.loading.set(true);
    this.error.set(null);
    const query = this.q.trim();
    const request = ++this.requestNo;

    this.api
      .list({ q: query, type: this.type, archived: this.archived, page: this.page(), sort: this.sortCol(), dir: this.sortDir() })
      .subscribe({
        next: (p) => {
          if (request !== this.requestNo) return; // eine neuere Anfrage läuft schon: diese Antwort ist veraltet

          // Die letzte Seite ist nach dem Archivieren weggefallen: eine Seite zurück.
          if (p.data.length === 0 && p.current_page > 1) {
            this.page.set(Math.max(1, p.last_page));
            this.load();
            return;
          }

          this.rows.set(p.data);
          // Markiert wird der Begriff, der diese Liste erzeugt hat, nicht der inzwischen weiter getippte.
          this.shownQuery.set(query);
          this.lastPage.set(p.last_page);
          this.total.set(p.total);
          this.loaded.set(true);
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
