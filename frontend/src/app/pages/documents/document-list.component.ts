import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { httpErrorText } from '../../core/http-error';
import { BusDocument, DocumentService, DocumentTypeInfo } from '../../core/document.service';
import { hasHit } from '../../shared/highlight';
import { PrintPreviewService } from '../../core/print-preview.service';
import { HighlightComponent } from '../../shared/highlight.component';
import { StickyFilterBarDirective } from '../../shared/sticky-filter-bar.directive';
import { RevealCurrentDirective } from '../../shared/reveal-current.directive';
import { ListMemory } from '../../core/list-memory.service';
import { formatAmount } from './document-calc';

const KEY = 'belege';
type DocListParams = {
  q: string; type: string; status: string; page: number; order: 'column' | 'chain'; sortCol: string; sortDir: 'asc' | 'desc';
};

/**
 * Belegliste nach dem Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md). Der SERVER filtert und blättert.
 */
@Component({
  selector: 'app-document-list',
  imports: [DatePipe, FormsModule, RouterLink, HighlightComponent, StickyFilterBarDirective, RevealCurrentDirective],
  templateUrl: './document-list.component.html',
  host: { class: 'list-page', style: '--sheet-w: 964px' },
})
export class DocumentListComponent implements OnInit {
  private api = inject(DocumentService);
  private route = inject(ActivatedRoute);
  private memory = inject(ListMemory);
  private printPreview = inject(PrintPreviewService);

  q = '';
  type = '';
  status = '';
  /** „column“: nach der gewählten Spalte. „chain“: je Kunde untereinander, darin die Belegketten (Entstehungsgeschichte). */
  order: 'column' | 'chain' = 'column';
  sortCol = signal('date');
  sortDir = signal<'asc' | 'desc'>('desc');

  page = signal(1);
  lastPage = signal(1);
  total = signal(0);

  rows = signal<BusDocument[]>([]);
  types = signal<DocumentTypeInfo[]>([]);
  currentId = signal<string | null>(this.memory.current(KEY));
  loaded = signal(false);
  loading = signal(false);
  error = signal<string | null>(null);
  /** Der Suchbegriff, zu dem die angezeigte Liste gehört (Grundlage der Markierung) */
  shownQuery = signal('');

  readonly amount = formatAmount;

  private locate: string | null = null;
  private requestNo = 0;
  private timer?: ReturnType<typeof setTimeout>;

  ngOnInit() {
    // Rücksprung aus einem Beleg: Seite und Filter wie vorher (siehe ListMemory). Eine Suche von außen (?q=) hat Vorrang.
    const fromUrl = this.route.snapshot.queryParamMap.get('q');
    const saved = fromUrl === null ? this.memory.params<DocListParams>(KEY) : null;
    this.q = fromUrl ?? saved?.q ?? '';
    this.type = saved?.type ?? '';
    this.status = saved?.status ?? '';
    this.order = saved?.order ?? 'column';
    this.sortCol.set(saved?.sortCol ?? 'date');
    this.sortDir.set(saved?.sortDir ?? 'desc');
    this.page.set(saved?.page ?? 1);
    this.locate = this.currentId();
    this.api.types().subscribe((t) => this.types.set(t));
    this.load();
  }

  select(id: string) {
    this.currentId.set(id);
    this.memory.select(KEY, id);
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

  /** Druckvorschau (Aktionsspalte). Laden, Fehler und Drucken übernimmt die Vorschau (PrintPreviewService). */
  openPdf(d: BusDocument) {
    this.printPreview.open({
      title: `${this.typeLabel(d.type)} ${d.number ?? '(Entwurf)'}`,
      filename: `${d.number ?? 'Entwurf'}.pdf`,
      load: () => this.api.pdf(d.id),
    });
  }

  /**
   * Klick auf einen Spaltenkopf: neue Spalte zuerst absteigend bei Datum und Betrag (neueste/größte zuerst), sonst aufsteigend;
   * dieselbe Spalte wechselt die Richtung. Verlässt die Kettenansicht.
   */
  sort(col: string) {
    if (this.order === 'column' && this.sortCol() === col) {
      this.sortDir.update((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      this.sortCol.set(col);
      this.sortDir.set(col === 'date' || col === 'gross' ? 'desc' : 'asc');
    }
    this.order = 'column';
    this.filterChanged();
  }

  private sorted(col: string): boolean {
    return this.order === 'column' && this.sortCol() === col;
  }

  sortIcon(col: string): string {
    if (!this.sorted(col)) return 'bi-chevron-expand';
    return this.sortDir() === 'asc' ? 'bi-chevron-up' : 'bi-chevron-down';
  }

  ariaSort(col: string): 'ascending' | 'descending' | 'none' {
    if (!this.sorted(col)) return 'none';
    return this.sortDir() === 'asc' ? 'ascending' : 'descending';
  }

  isSorted(col: string): boolean {
    return this.sorted(col);
  }

  /** Erste Zeile eines neuen Kunden (nur in der Ansicht „je Kunde“): bekommt eine kräftigere Trennlinie. */
  groupStart(i: number): boolean {
    if (this.order !== 'chain' || i === 0) return false;
    return this.rows()[i].customer_id !== this.rows()[i - 1].customer_id;
  }

  typeLabel(value: string) {
    return this.types().find((t) => t.value === value)?.label ?? value;
  }

  /** Beim Festschreiben wird der Empfänger kopiert, davor folgt der Beleg dem Kundenstamm. */
  recipientName(d: BusDocument): string {
    return d.recipient?.name ?? d.customer?.display_name ?? '';
  }

  /** Treffer in Feldern, die die Liste nicht zeigt (hier: die Kundennummer). */
  hiddenHits(d: BusDocument): { label: string; value: string }[] {
    const q = this.shownQuery();
    const nr = d.customer?.customer_number;
    return q && nr && hasHit(nr, q) ? [{ label: 'Kundennr.', value: nr }] : [];
  }

  private load() {
    this.loading.set(true);
    this.error.set(null);
    const query = this.q.trim();
    const request = ++this.requestNo;

    // Beim ersten Laden fragt die Liste nach der Seite, auf der die aktuelle Zeile steht (Rücksprung aus einem Beleg, auch aus
    // einem Folgebeleg, der erst jetzt in die Liste kam). Danach blättert wieder der Nutzer.
    const locate = this.locate;
    this.api.list({ q: query, type: this.type, status: this.status, page: this.page(), order: this.order, sort: this.sortCol(), dir: this.sortDir(), locate }).subscribe({
      next: (p) => {
        if (request !== this.requestNo) return;
        this.locate = null;
        this.page.set(p.current_page);
        this.rows.set(p.data);
        this.shownQuery.set(query);
        this.memory.saveParams(KEY, { q: query, type: this.type, status: this.status, page: this.page(), order: this.order, sortCol: this.sortCol(), sortDir: this.sortDir() } satisfies DocListParams);
        this.lastPage.set(p.last_page);
        this.total.set(p.total);
        this.loaded.set(true);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        if (request !== this.requestNo) return;
        this.error.set(httpErrorText(e, 'Belege konnten nicht geladen werden.'));
        this.loading.set(false);
      },
    });
  }
}
