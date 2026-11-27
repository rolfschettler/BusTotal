import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { httpErrorText } from '../../core/http-error';
import { ListMemory } from '../../core/list-memory.service';
import { PrintPreviewService } from '../../core/print-preview.service';
import { TRIP_ORDER_STATUS, TripOrder, TripOrderService, statusBadge } from '../../core/trip-order.service';
import { CustomerNameComponent } from '../../shared/customer-name.component';
import { HighlightComponent } from '../../shared/highlight.component';
import { RevealCurrentDirective } from '../../shared/reveal-current.directive';
import { StickyFilterBarDirective } from '../../shared/sticky-filter-bar.directive';

const KEY = 'fahrtauftraege';
type ListParams = { q: string; status: string; page: number };

/**
 * Fahrtaufträge nach dem Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md). Der SERVER sucht und blättert.
 * Abweichung vom Muster wie bei Belegen und Reisen: feste Reihenfolge (nach Fahrtbeginn), keine sortierbaren Spaltenköpfe.
 * Fahrtaufträge werden nicht von Hand angelegt: Sie entstehen mit der festgeschriebenen Auftragsbestätigung.
 */
@Component({
  selector: 'app-trip-order-list',
  imports: [DatePipe, FormsModule, RouterLink, CustomerNameComponent, HighlightComponent, StickyFilterBarDirective, RevealCurrentDirective],
  templateUrl: './trip-order-list.component.html',
  host: { class: 'list-page', style: '--sheet-w: 1150px' },
})
export class TripOrderListComponent implements OnInit {
  private api = inject(TripOrderService);
  private memory = inject(ListMemory);
  private route = inject(ActivatedRoute);
  private printPreview = inject(PrintPreviewService);

  q = '';
  status = '';
  /** Nur die Fahrtaufträge einer Reise (Link von der Reise); leer = alle */
  journeyId = signal('');
  page = signal(1);
  lastPage = signal(1);
  total = signal(0);
  rows = signal<TripOrder[]>([]);
  currentId = signal<string | null>(this.memory.current(KEY));
  loaded = signal(false);
  loading = signal(false);
  error = signal<string | null>(null);
  /** Der Suchbegriff, zu dem die angezeigte Liste gehört (Grundlage der Markierung) */
  shownQuery = signal('');

  readonly statuses = TRIP_ORDER_STATUS;
  readonly badge = statusBadge;

  private requestNo = 0;
  private timer?: ReturnType<typeof setTimeout>;

  ngOnInit() {
    const journey = this.route.snapshot.queryParamMap.get('journey_id') ?? '';
    const saved = this.memory.params<ListParams>(KEY);
    this.journeyId.set(journey);
    // Ein Link von einer Reise zeigt deren Aufträge von vorn, nicht den gemerkten Stand der Liste.
    this.q = journey ? '' : (saved?.q ?? '');
    this.status = journey ? '' : (saved?.status ?? '');
    this.page.set(journey ? 1 : (saved?.page ?? 1));
    this.load();
  }

  select(id: string) {
    this.currentId.set(id);
    this.memory.select(KEY, id);
  }

  onSearch() {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.reload(), 300);
  }

  reload() {
    this.page.set(1);
    this.load();
  }

  showAll() {
    this.journeyId.set('');
    this.reload();
  }

  refresh() {
    this.load();
  }

  go(p: number) {
    this.page.set(p);
    this.load();
  }

  /** Druckvorschau der Fahrer-Fassung (Aktionsspalte). Laden, Fehler und Drucken übernimmt die Vorschau. */
  openPdf(o: TripOrder) {
    this.printPreview.open({
      title: `Fahrtauftrag ${o.number ?? o.title}`,
      filename: `${o.number ?? 'Fahrtauftrag-Entwurf'}.pdf`,
      load: () => this.api.pdf(o.id),
    });
  }

  private load() {
    this.loading.set(true);
    this.error.set(null);
    const query = this.q.trim();
    const request = ++this.requestNo;
    this.api.list({ q: query, status: this.status, journeyId: this.journeyId(), page: this.page() }).subscribe({
      next: (p) => {
        if (request !== this.requestNo) return;
        this.page.set(p.current_page);
        this.rows.set(p.data);
        this.shownQuery.set(query);
        if (!this.journeyId()) this.memory.saveParams(KEY, { q: query, status: this.status, page: this.page() } satisfies ListParams);
        this.lastPage.set(p.last_page);
        this.total.set(p.total);
        this.loaded.set(true);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        if (request !== this.requestNo) return;
        this.error.set(httpErrorText(e, 'Die Fahrtaufträge konnten nicht geladen werden.'));
        this.loading.set(false);
      },
    });
  }
}
