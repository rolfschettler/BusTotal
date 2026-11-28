import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { httpErrorText } from '../../core/http-error';
import { Option, Vehicle, VehicleService } from '../../core/vehicle.service';
import { deadlineText, germanDate } from '../../shared/deadline';
import { hasHit } from '../../shared/highlight';
import { HighlightComponent } from '../../shared/highlight.component';
import { ListSearchDirective, RowNavDirective } from '../../shared/row-nav.directive';
import { RevealCurrentDirective } from '../../shared/reveal-current.directive';
import { ListMemory } from '../../core/list-memory.service';

const KEY = 'fahrzeuge';
type ListParams = { q: string; type: string; deadline: string; archived: boolean; sortCol: string; sortDir: 'asc' | 'desc'; page: number };

/**
 * Fahrzeugliste nach dem Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md), Vorbild: Kundenliste.
 * Der SERVER filtert, sortiert und blättert.
 */
@Component({
  selector: 'app-vehicle-list',
  imports: [FormsModule, RouterLink, HighlightComponent, RowNavDirective, ListSearchDirective, RevealCurrentDirective],
  templateUrl: './vehicle-list.component.html',
  host: { class: 'list-page' },
})
export class VehicleListComponent implements OnInit {
  private api = inject(VehicleService);
  private router = inject(Router);
  private confirm = inject(ConfirmService);
  private memory = inject(ListMemory);
  auth = inject(AuthService);

  q = '';
  type = '';
  deadline = '';
  archived = false;

  sortCol = signal('fleet_number');
  sortDir = signal<'asc' | 'desc'>('asc');
  page = signal(1);
  lastPage = signal(1);
  total = signal(0);

  rows = signal<Vehicle[]>([]);
  types = signal<Option[]>([]);
  currentId = signal<string | null>(this.memory.current(KEY));
  loaded = signal(false);
  loading = signal(false);
  error = signal<string | null>(null);
  message = signal<string | null>(null);
  /** Der Suchbegriff, zu dem die angezeigte Liste gehört (Grundlage der Markierung) */
  shownQuery = signal('');

  readonly text = deadlineText;
  readonly date = germanDate;

  private requestNo = 0;
  private timer?: ReturnType<typeof setTimeout>;

  /** Ändern dürfen Administrator und Disponent, die Buchhaltung liest nur. */
  canEdit() {
    return ['admin', 'disponent'].includes(this.auth.user()?.role ?? '');
  }

  ngOnInit() {
    // Stand vor dem Absprung wiederherstellen (ListMemory)
    const saved = this.memory.params<ListParams>(KEY);
    this.q = saved?.q ?? '';
    this.type = saved?.type ?? '';
    this.deadline = saved?.deadline ?? '';
    this.archived = saved?.archived ?? false;
    this.sortCol.set(saved?.sortCol ?? 'fleet_number');
    this.sortDir.set(saved?.sortDir ?? 'asc');
    this.page.set(saved?.page ?? 1);
    this.api.options().subscribe((o) => this.types.set(o.types));
    this.load();
  }

  select(id: string) {
    this.currentId.set(id);
    this.memory.select(KEY, id);
  }

  open(id: string) {
    void this.router.navigate(['/fahrzeuge', id]);
  }

  /** Enter in der Suche, bevor die Verzögerung abgelaufen ist: sofort suchen. */
  searchNow() {
    clearTimeout(this.timer);
    if (this.q.trim() !== this.shownQuery()) this.filterChanged();
  }

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

  /** Die Fahrgestellnummer sucht man, sieht sie aber nicht in der Liste: Treffer dort bekommen einen Hinweis. */
  hiddenHits(v: Vehicle): { label: string; value: string }[] {
    const q = this.shownQuery();
    return q && hasHit(v.vin, q) ? [{ label: 'Fahrgestellnr.', value: v.vin ?? '' }] : [];
  }

  async archive(v: Vehicle) {
    const ok = await this.confirm.ask({
      title: 'Fahrzeug archivieren',
      message: `${v.display_name} verschwindet aus der Liste, bleibt aber für spätere Aufträge und Belege erhalten.`,
      confirmLabel: 'Archivieren',
    });
    if (!ok) return;
    this.api.archive(v.id).subscribe({
      next: () => {
        this.message.set(`${v.display_name} wurde archiviert.`);
        this.load();
      },
      error: (e: HttpErrorResponse) => this.error.set(httpErrorText(e, 'Das Archivieren hat nicht geklappt.')),
    });
  }

  restore(v: Vehicle) {
    this.api.restore(v.id).subscribe({
      next: () => {
        this.message.set(`${v.display_name} wurde wiederhergestellt.`);
        this.load();
      },
      error: (e: HttpErrorResponse) => this.error.set(httpErrorText(e, 'Das Wiederherstellen hat nicht geklappt.')),
    });
  }

  private load() {
    this.loading.set(true);
    this.error.set(null);
    const query = this.q.trim();
    const request = ++this.requestNo;

    this.api
      .list({ q: query, type: this.type, deadline: this.deadline, archived: this.archived, page: this.page(), sort: this.sortCol(), dir: this.sortDir() })
      .subscribe({
        next: (p) => {
          if (request !== this.requestNo) return; // veraltete Antwort

          if (p.data.length === 0 && p.current_page > 1) {
            this.page.set(Math.max(1, p.last_page));
            this.load();
            return;
          }

          this.rows.set(p.data);
          this.shownQuery.set(query);
          this.memory.saveParams(KEY, {
            q: query, type: this.type, deadline: this.deadline, archived: this.archived,
            sortCol: this.sortCol(), sortDir: this.sortDir(), page: this.page(),
          } satisfies ListParams);
          this.lastPage.set(p.last_page);
          this.total.set(p.total);
          this.loaded.set(true);
          this.loading.set(false);
        },
        error: (e: HttpErrorResponse) => {
          if (request !== this.requestNo) return;
          this.error.set(httpErrorText(e, 'Fahrzeuge konnten nicht geladen werden.'));
          this.loading.set(false);
        },
      });
  }
}
