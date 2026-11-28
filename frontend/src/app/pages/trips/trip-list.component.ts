import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { httpErrorText } from '../../core/http-error';
import { ListMemory } from '../../core/list-memory.service';
import { Trip, TripService } from '../../core/trip.service';
import { HighlightComponent } from '../../shared/highlight.component';
import { RevealCurrentDirective } from '../../shared/reveal-current.directive';
import { ListSearchDirective, RowNavDirective } from '../../shared/row-nav.directive';
import { formatDuration } from '../route-planning/route-planning-view';

const KEY = 'tour-archiv';
type TripListParams = { q: string; page: number };

const VEHICLE_LABELS: Record<string, string> = {
  reisebus: 'Reisebus', doppeldecker: 'Doppeldeckerbus', midibus: 'Midibus', minibus: 'Minibus / Kleinbus', sonstige: 'Sonstiges',
};

/**
 * Tour-Archiv nach dem Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md): gespeicherte Touren suchen, in der
 * Streckenplanung öffnen und löschen. Der SERVER sucht und blättert. Abweichung vom Muster wie bei den Belegen: feste Reihenfolge
 * (zuletzt geändert zuerst), keine sortierbaren Spaltenköpfe. Touren entstehen in der Streckenplanung (Als Vorlage speichern).
 */
@Component({
  selector: 'app-trip-list',
  imports: [DatePipe, DecimalPipe, FormsModule, RouterLink, HighlightComponent, RowNavDirective, ListSearchDirective, RevealCurrentDirective],
  templateUrl: './trip-list.component.html',
  host: { class: 'list-page' },
})
export class TripListComponent implements OnInit {
  private api = inject(TripService);
  private router = inject(Router);
  private memory = inject(ListMemory);
  private confirm = inject(ConfirmService);
  private auth = inject(AuthService);

  /** Löschen und Planen dürfen Administrator und Disponent (der Server prüft zusätzlich). */
  canEdit = computed(() => ['admin', 'disponent'].includes(this.auth.user()?.role ?? ''));

  q = '';
  page = signal(1);
  lastPage = signal(1);
  total = signal(0);
  rows = signal<Trip[]>([]);
  currentId = signal<string | null>(this.memory.current(KEY));
  loaded = signal(false);
  loading = signal(false);
  error = signal<string | null>(null);
  message = signal<string | null>(null);
  /** Der Suchbegriff, zu dem die angezeigte Liste gehört (Grundlage der Markierung) */
  shownQuery = signal('');

  readonly duration = formatDuration;
  vehicleLabel = (type: string) => VEHICLE_LABELS[type] ?? type;
  kindLabel = (kind: string) => ({ gelegenheit: 'Gelegenheitsverkehr', linie: 'Linienverkehr', schule: 'Schülerfahrt', sonstig: 'Sonstiges' })[kind] ?? kind;

  private requestNo = 0;
  private timer?: ReturnType<typeof setTimeout>;

  ngOnInit() {
    const saved = this.memory.params<TripListParams>(KEY);
    this.q = saved?.q ?? '';
    this.page.set(saved?.page ?? 1);
    this.load();
  }

  select(id: string) {
    this.currentId.set(id);
    this.memory.select(KEY, id);
  }

  open(id: string) {
    if (this.canEdit()) void this.router.navigate(['/streckenplanung'], { queryParams: { trip: id } });
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
    this.load();
  }

  go(p: number) {
    this.page.set(p);
    this.load();
  }

  async remove(t: Trip) {
    const ok = await this.confirm.ask({
      title: 'Tour löschen',
      message: `Die Tour „${t.title}“ aus dem Tour-Archiv löschen? Belege, die sie schon enthalten, bleiben unverändert.`,
      confirmLabel: 'Löschen',
      danger: true,
    });
    if (!ok) return;
    this.message.set(null);
    this.api.remove(t.id).subscribe({
      next: () => {
        this.memory.forget(KEY, t.id);
        this.message.set(`Tour „${t.title}“ gelöscht.`);
        // War es die letzte Zeile der Seite, eine Seite zurück.
        if (this.rows().length === 1 && this.page() > 1) this.page.update((p) => p - 1);
        this.load();
      },
      error: (e: HttpErrorResponse) => this.error.set(httpErrorText(e, 'Die Tour konnte nicht gelöscht werden.')),
    });
  }

  private load() {
    this.loading.set(true);
    this.error.set(null);
    const query = this.q.trim();
    const request = ++this.requestNo;
    this.api.list(query, 20, this.page()).subscribe({
      next: (p) => {
        if (request !== this.requestNo) return;
        this.page.set(p.current_page);
        this.rows.set(p.data);
        this.shownQuery.set(query);
        this.memory.saveParams(KEY, { q: query, page: this.page() } satisfies TripListParams);
        this.lastPage.set(p.last_page);
        this.total.set(p.total);
        this.loaded.set(true);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        if (request !== this.requestNo) return;
        this.error.set(httpErrorText(e, 'Das Tour-Archiv konnte nicht geladen werden.'));
        this.loading.set(false);
      },
    });
  }
}
