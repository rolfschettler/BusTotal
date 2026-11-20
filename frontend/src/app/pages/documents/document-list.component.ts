import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe } from '@angular/common';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { httpErrorText } from '../../core/http-error';
import { BusDocument, DocumentService, DocumentTypeInfo } from '../../core/document.service';
import { hasHit } from '../../shared/highlight';
import { openPdfWindow, showPdf } from '../../shared/pdf-open';
import { HighlightComponent } from '../../shared/highlight.component';
import { StickyFilterBarDirective } from '../../shared/sticky-filter-bar.directive';
import { formatAmount } from './document-calc';

/**
 * Belegliste nach dem Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md). Der SERVER filtert und blättert.
 * Abweichung vom Muster: keine sortierbaren Spaltenköpfe, die Liste steht fest nach Datum (neueste zuerst, Entwürfe oben).
 */
@Component({
  selector: 'app-document-list',
  imports: [DatePipe, FormsModule, RouterLink, HighlightComponent, StickyFilterBarDirective],
  templateUrl: './document-list.component.html',
  host: { class: 'list-page', style: '--sheet-w: 964px' },
})
export class DocumentListComponent implements OnInit {
  private api = inject(DocumentService);
  private route = inject(ActivatedRoute);

  q = '';
  type = '';
  status = '';

  page = signal(1);
  lastPage = signal(1);
  total = signal(0);

  rows = signal<BusDocument[]>([]);
  types = signal<DocumentTypeInfo[]>([]);
  currentId = signal<string | null>(null);
  loaded = signal(false);
  loading = signal(false);
  error = signal<string | null>(null);
  /** Der Suchbegriff, zu dem die angezeigte Liste gehört (Grundlage der Markierung) */
  shownQuery = signal('');

  readonly amount = formatAmount;

  private requestNo = 0;
  private timer?: ReturnType<typeof setTimeout>;

  ngOnInit() {
    this.q = this.route.snapshot.queryParamMap.get('q') ?? '';
    this.api.types().subscribe((t) => this.types.set(t));
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
    this.load();
  }

  go(p: number) {
    this.page.set(p);
    this.load();
  }

  /** PDF in einem neuen Tab (Aktionsspalte). */
  pdfBusyId = signal<string | null>(null);
  openPdf(d: BusDocument) {
    const target = openPdfWindow();
    this.pdfBusyId.set(d.id);
    this.api.pdf(d.id).subscribe({
      next: (blob) => {
        this.pdfBusyId.set(null);
        showPdf(blob, target, `${d.number ?? 'Entwurf'}.pdf`);
      },
      error: (e: HttpErrorResponse) => {
        this.pdfBusyId.set(null);
        target?.close();
        this.error.set(httpErrorText(e, 'Das PDF konnte nicht erstellt werden.'));
      },
    });
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

    this.api.list({ q: query, type: this.type, status: this.status, page: this.page() }).subscribe({
      next: (p) => {
        if (request !== this.requestNo) return;
        this.rows.set(p.data);
        this.shownQuery.set(query);
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
