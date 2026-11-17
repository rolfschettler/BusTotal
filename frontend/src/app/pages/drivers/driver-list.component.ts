import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { Driver, DriverService } from '../../core/driver.service';
import { Option } from '../../core/vehicle.service';
import { deadlineText, germanDate } from '../../shared/deadline';
import { hasHit } from '../../shared/highlight';
import { HighlightComponent } from '../../shared/highlight.component';
import { StickyFilterBarDirective } from '../../shared/sticky-filter-bar.directive';

/**
 * Fahrerliste nach dem Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md), Vorbild: Fahrzeugliste.
 * Der SERVER filtert, sortiert und blättert.
 */
@Component({
  selector: 'app-driver-list',
  imports: [FormsModule, RouterLink, HighlightComponent, StickyFilterBarDirective],
  templateUrl: './driver-list.component.html',
  host: { class: 'list-page', style: '--sheet-w: 1074px' },
})
export class DriverListComponent implements OnInit {
  private api = inject(DriverService);
  private confirm = inject(ConfirmService);
  auth = inject(AuthService);

  q = '';
  employment = '';
  deadline = '';
  archived = false;

  sortCol = signal('name');
  sortDir = signal<'asc' | 'desc'>('asc');
  page = signal(1);
  lastPage = signal(1);
  total = signal(0);

  rows = signal<Driver[]>([]);
  employmentTypes = signal<Option[]>([]);
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

  ngOnInit() {
    this.api.options().subscribe((o) => this.employmentTypes.set(o.employment_types));
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

  employmentLabel(value: string) {
    return this.employmentTypes().find((t) => t.value === value)?.label ?? value;
  }

  /** Führerschein-, Fahrerkarten-Nummer und E-Mail sucht man, sieht sie aber nicht in der Liste: Hinweis bei Treffern. */
  hiddenHits(d: Driver): { label: string; value: string }[] {
    const q = this.shownQuery();
    if (!q) return [];
    const out: { label: string; value: string }[] = [];
    if (hasHit(d.email, q)) out.push({ label: 'E-Mail', value: d.email ?? '' });
    if (hasHit(d.license_number, q)) out.push({ label: 'Führerscheinnr.', value: d.license_number ?? '' });
    if (hasHit(d.driver_card_number, q)) out.push({ label: 'Fahrerkartennr.', value: d.driver_card_number ?? '' });
    return out;
  }

  async archive(d: Driver) {
    const ok = await this.confirm.ask({
      title: 'Fahrer archivieren',
      message: `${d.display_name} verschwindet aus der Liste (zum Beispiel bei Austritt), bleibt aber für vergangene Fahrten und Belege erhalten.`,
      confirmLabel: 'Archivieren',
    });
    if (!ok) return;
    this.api.archive(d.id).subscribe({
      next: () => {
        this.message.set(`${d.display_name} wurde archiviert.`);
        this.load();
      },
      error: () => this.error.set('Das Archivieren hat nicht geklappt.'),
    });
  }

  restore(d: Driver) {
    this.api.restore(d.id).subscribe({
      next: () => {
        this.message.set(`${d.display_name} wurde wiederhergestellt.`);
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
      .list({ q: query, employment: this.employment, deadline: this.deadline, archived: this.archived, page: this.page(), sort: this.sortCol(), dir: this.sortDir() })
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
          this.error.set('Fahrer konnten nicht geladen werden.');
          this.loading.set(false);
        },
      });
  }
}
