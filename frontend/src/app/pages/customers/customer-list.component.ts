import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { httpErrorText } from '../../core/http-error';
import { Customer, CustomerService, CustomerType } from '../../core/customer.service';
import { hasHit } from '../../shared/highlight';
import { CustomerNameComponent } from '../../shared/customer-name.component';
import { AvatarComponent } from '../../shared/avatar.component';
import { HighlightComponent } from '../../shared/highlight.component';
import { RevealCurrentDirective } from '../../shared/reveal-current.directive';
import { ListSearchDirective, RowNavDirective } from '../../shared/row-nav.directive';
import { ListMemory } from '../../core/list-memory.service';
import { CustomerPeekComponent } from './customer-peek.component';

const KEY = 'kunden';
type ListParams = { q: string; type: string; archived: boolean; sortCol: string; sortDir: 'asc' | 'desc'; page: number };

/**
 * Kundenliste. Lebende Referenz des Tabellen-Musters (docs/ClaudeCodePatterns/TableViewPattern.md).
 * Weil die Menge groß werden kann, filtert, sortiert und blättert der SERVER.
 */
@Component({
  selector: 'app-customer-list',
  imports: [FormsModule, RouterLink, CustomerNameComponent, HighlightComponent, RevealCurrentDirective, RowNavDirective, ListSearchDirective, AvatarComponent, CustomerPeekComponent],
  templateUrl: './customer-list.component.html',
  host: { class: 'list-page' },
})
export class CustomerListComponent implements OnInit {
  private api = inject(CustomerService);
  private route = inject(ActivatedRoute);
  private confirm = inject(ConfirmService);
  private memory = inject(ListMemory);
  private router = inject(Router);
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
  /** Die markierte Zeile; bleibt über Formular und Rücksprung erhalten (ListMemory). */
  currentId = signal<string | null>(this.memory.current(KEY));
  /** Die Zeile, die die Vorschau zeigt: die aktuelle, sonst die erste der Seite. */
  current = computed(() => this.rows().find((c) => c.id === this.currentId()) ?? this.rows()[0] ?? null);
  loaded = signal(false);
  loading = signal(false);
  error = signal<string | null>(null);
  message = signal<string | null>(null);
  /** Der Suchbegriff, zu dem die angezeigte Liste gehört (Grundlage der Markierung) */
  shownQuery = signal('');

  private requestNo = 0;
  private timer?: ReturnType<typeof setTimeout>;

  ngOnInit() {
    // Die Suche von der Startseite kommt als ?q=… an und hat Vorrang. Sonst gilt der Stand vor dem Absprung (ListMemory).
    const fromUrl = this.route.snapshot.queryParamMap.get('q');
    const saved = fromUrl === null ? this.memory.params<ListParams>(KEY) : null;
    this.q = fromUrl ?? saved?.q ?? '';
    this.type = saved?.type ?? '';
    this.archived = saved?.archived ?? false;
    this.sortCol.set(saved?.sortCol ?? 'name');
    this.sortDir.set(saved?.sortDir ?? 'asc');
    this.page.set(saved?.page ?? 1);
    this.api.types().subscribe((t) => this.types.set(t));
    this.load();
  }

  select(id: string) {
    this.currentId.set(id);
    this.memory.select(KEY, id);
  }

  showType(type: string) {
    this.type = type;
    this.filterChanged();
  }

  open(id: string) {
    void this.router.navigate(['/kunden', id]);
  }

  /** Enter in der Suche, bevor die Verzögerung abgelaufen ist: sofort suchen. */
  searchNow() {
    clearTimeout(this.timer);
    if (this.q.trim() !== this.shownQuery()) this.filterChanged();
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
      error: (e: HttpErrorResponse) => this.error.set(httpErrorText(e, 'Das Archivieren hat nicht geklappt.')),
    });
  }

  restore(c: Customer) {
    this.api.restore(c.id).subscribe({
      next: () => {
        this.message.set(`${c.display_name} wurde wiederhergestellt.`);
        this.load();
      },
      error: (e: HttpErrorResponse) => this.error.set(httpErrorText(e, 'Das Wiederherstellen hat nicht geklappt.')),
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
    if (hasHit(c.debitor_number, q, 'start')) out.push({ label: 'Debitorennr.', value: String(c.debitor_number) });
    // Vor- und Nachname zeigt die Liste immer (app-customer-name), hier fehlt nur noch die Debitorennummer.
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
          this.memory.saveParams(KEY, {
            q: query, type: this.type, archived: this.archived, sortCol: this.sortCol(), sortDir: this.sortDir(), page: this.page(),
          } satisfies ListParams);
          this.lastPage.set(p.last_page);
          this.total.set(p.total);
          this.loaded.set(true);
          this.loading.set(false);
        },
        error: (e: HttpErrorResponse) => {
          if (request !== this.requestNo) return;
          this.error.set(httpErrorText(e, 'Kunden konnten nicht geladen werden.'));
          this.loading.set(false);
        },
      });
  }
}
