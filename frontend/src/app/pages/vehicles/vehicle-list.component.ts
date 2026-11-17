import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { Option, Vehicle, VehicleService } from '../../core/vehicle.service';
import { deadlineText, germanDate } from '../../shared/deadline';
import { hasHit } from '../../shared/highlight';
import { HighlightComponent } from '../../shared/highlight.component';
import { StickyFilterBarDirective } from '../../shared/sticky-filter-bar.directive';

/**
 * Fahrzeugliste nach dem Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md), Vorbild: Kundenliste.
 * Der SERVER filtert, sortiert und blättert.
 */
@Component({
  selector: 'app-vehicle-list',
  imports: [FormsModule, RouterLink, HighlightComponent, StickyFilterBarDirective],
  templateUrl: './vehicle-list.component.html',
  host: { class: 'list-page', style: '--sheet-w: 1004px' },
})
export class VehicleListComponent implements OnInit {
  private api = inject(VehicleService);
  private confirm = inject(ConfirmService);
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
  currentId = signal<string | null>(null);
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
    this.api.options().subscribe((o) => this.types.set(o.types));
    this.load();
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
      error: () => this.error.set('Das Archivieren hat nicht geklappt.'),
    });
  }

  restore(v: Vehicle) {
    this.api.restore(v.id).subscribe({
      next: () => {
        this.message.set(`${v.display_name} wurde wiederhergestellt.`);
        this.load();
      },
      error: () => this.error.set('Das Wiederherstellen hat nicht geklappt.'),
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
          this.lastPage.set(p.last_page);
          this.total.set(p.total);
          this.loaded.set(true);
          this.loading.set(false);
        },
        error: () => {
          if (request !== this.requestNo) return;
          this.error.set('Fahrzeuge konnten nicht geladen werden.');
          this.loading.set(false);
        },
      });
  }
}
