import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { httpErrorText } from '../../core/http-error';
import { JourneyRow, JourneyService } from '../../core/journey.service';
import { ListMemory } from '../../core/list-memory.service';
import { CustomerNameComponent } from '../../shared/customer-name.component';
import { HighlightComponent } from '../../shared/highlight.component';
import { RevealCurrentDirective } from '../../shared/reveal-current.directive';
import { StickyFilterBarDirective } from '../../shared/sticky-filter-bar.directive';
import { formatAmount } from '../documents/document-calc';

const KEY = 'reisen';
type JourneyListParams = { q: string; page: number };

/** Der Stand einer Reise in Worten: der weitestgehende gültige Beleg. */
export const STAGE_LABELS: Record<string, string> = {
  angebot: 'Angebot',
  auftragsbestaetigung: 'Auftrag bestätigt',
  rechnung: 'Rechnung',
};

/**
 * Reisen nach dem Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md). Der SERVER sucht und blättert.
 * Abweichung vom Muster wie bei den Belegen: feste Reihenfolge (neueste zuerst), keine sortierbaren Spaltenköpfe.
 * Eine Reise wird von Hand geplant („Neue Reise planen“, nur Admin und Disponent) oder entsteht mit dem Kurzweg „Angebot erstellen“.
 */
@Component({
  selector: 'app-journey-list',
  imports: [DatePipe, FormsModule, RouterLink, CustomerNameComponent, HighlightComponent, StickyFilterBarDirective, RevealCurrentDirective],
  templateUrl: './journey-list.component.html',
  host: { class: 'list-page', style: '--sheet-w: 986px' },
})
export class JourneyListComponent implements OnInit {
  private api = inject(JourneyService);
  private memory = inject(ListMemory);
  private auth = inject(AuthService);

  /** Reisen planen dürfen Admin und Disponent (der Server prüft es ebenfalls). */
  canPlan = computed(() => ['admin', 'disponent'].includes(this.auth.user()?.role ?? ''));

  q = '';
  page = signal(1);
  lastPage = signal(1);
  total = signal(0);
  rows = signal<JourneyRow[]>([]);
  currentId = signal<string | null>(this.memory.current(KEY));
  loaded = signal(false);
  loading = signal(false);
  error = signal<string | null>(null);
  /** Der Suchbegriff, zu dem die angezeigte Liste gehört (Grundlage der Markierung) */
  shownQuery = signal('');

  readonly amount = formatAmount;
  readonly stageLabels = STAGE_LABELS;

  private requestNo = 0;
  private timer?: ReturnType<typeof setTimeout>;

  ngOnInit() {
    const saved = this.memory.params<JourneyListParams>(KEY);
    this.q = saved?.q ?? '';
    this.page.set(saved?.page ?? 1);
    this.load();
  }

  select(id: string) {
    this.currentId.set(id);
    this.memory.select(KEY, id);
  }

  onSearch() {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => {
      this.page.set(1);
      this.load();
    }, 300);
  }

  refresh() {
    this.load();
  }

  go(p: number) {
    this.page.set(p);
    this.load();
  }

  private load() {
    this.loading.set(true);
    this.error.set(null);
    const query = this.q.trim();
    const request = ++this.requestNo;
    this.api.list(query, this.page()).subscribe({
      next: (p) => {
        if (request !== this.requestNo) return;
        this.page.set(p.current_page);
        this.rows.set(p.data);
        this.shownQuery.set(query);
        this.memory.saveParams(KEY, { q: query, page: this.page() } satisfies JourneyListParams);
        this.lastPage.set(p.last_page);
        this.total.set(p.total);
        this.loaded.set(true);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        if (request !== this.requestNo) return;
        this.error.set(httpErrorText(e, 'Die Reisen konnten nicht geladen werden.'));
        this.loading.set(false);
      },
    });
  }
}
