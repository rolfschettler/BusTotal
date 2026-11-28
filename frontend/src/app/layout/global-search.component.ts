import { Component, ElementRef, Injectable, afterNextRender, computed, inject, signal, viewChild } from '@angular/core';
import { Router } from '@angular/router';
import { NgbActiveModal, NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { Observable, catchError, forkJoin, map, of } from 'rxjs';
import { CustomerService } from '../core/customer.service';
import { DocumentService } from '../core/document.service';
import { JourneyService } from '../core/journey.service';
import { TripOrderService } from '../core/trip-order.service';
import { HighlightComponent } from '../shared/highlight.component';

export interface SearchHit {
  group: 'Kunden' | 'Reisen' | 'Belege' | 'Fahrtaufträge';
  title: string;
  detail: string;
  link: string[];
}

/** Treffer je Bereich */
const PER_GROUP = 5;

/** Suchfenster „Alles durchsuchen“: fragt die vorhandenen Listen ab (Suche am Wortanfang, wie dort). */
@Component({
  selector: 'app-global-search',
  imports: [HighlightComponent],
  template: `
    <div class="p-2">
      <input #field type="search" class="form-control form-control-lg" autocomplete="off" aria-label="Alles durchsuchen"
             placeholder="Name, Ort, Reise, Belegnummer …" [value]="query()" (input)="onInput(field.value)" (keydown)="onKey($event)" />
    </div>
    <div class="search-results" role="listbox" aria-label="Treffer">
      @for (hit of hits(); track $index; let i = $index) {
        @if (i === 0 || hits()[i - 1].group !== hit.group) { <div class="search-group">{{ hit.group }}</div> }
        <button type="button" class="search-hit" role="option" [class.active]="i === active()" [attr.aria-selected]="i === active()"
                (mouseenter)="active.set(i)" (click)="open(hit)">
          <span><app-highlight [text]="hit.title" [query]="shown()" mode="start" /></span>
          <small><app-highlight [text]="hit.detail" [query]="shown()" mode="start" /></small>
        </button>
      } @empty {
        <div class="search-empty">{{ hint() }}</div>
      }
      @if (failed()) { <div class="search-empty text-danger" role="alert">Ein Teil der Suche hat nicht geantwortet.</div> }
    </div>
  `,
  styles: `
    .search-results { max-height: 60vh; overflow-y: auto; padding: 0 0.5rem 0.5rem; }
    .search-group { padding: 0.75rem 0.75rem 0.25rem; color: var(--ui-muted); font-size: 0.8125rem; font-weight: 600; }
    .search-hit {
      display: flex; justify-content: space-between; gap: 1rem; width: 100%; padding: 0.5rem 0.75rem;
      border: 0; border-radius: 10px; background: transparent; text-align: left;
      small { color: var(--ui-muted); text-align: right; }
      &.active { background: var(--brand-wash); }
    }
    .search-empty { padding: 0.75rem; color: var(--ui-muted); }
  `,
})
export class GlobalSearchComponent {
  private modal = inject(NgbActiveModal);
  private router = inject(Router);
  private customers = inject(CustomerService);
  private journeys = inject(JourneyService);
  private documents = inject(DocumentService);
  private tripOrders = inject(TripOrderService);
  private field = viewChild.required<ElementRef<HTMLInputElement>>('field');

  query = signal('');
  /** Der Begriff, zu dem die Treffer gehören (Grundlage der Markierung) */
  shown = signal('');
  hits = signal<SearchHit[]>([]);
  active = signal(0);
  loading = signal(false);
  failed = signal(false);
  hint = computed(() => {
    if (this.query().trim().length < 2) return 'Mindestens zwei Zeichen eingeben.';
    return this.loading() ? 'Suche läuft …' : 'Nichts gefunden.';
  });

  private timer?: ReturnType<typeof setTimeout>;
  private requestNo = 0;

  constructor() {
    afterNextRender(() => this.field().nativeElement.focus());
  }

  onInput(value: string) {
    this.query.set(value);
    clearTimeout(this.timer);
    this.requestNo++; // laufende Antworten sind veraltet
    if (value.trim().length < 2) {
      this.hits.set([]);
      this.loading.set(false);
      return;
    }
    this.loading.set(true);
    this.timer = setTimeout(() => this.search(value.trim()), 300);
  }

  onKey(event: KeyboardEvent) {
    const count = this.hits().length;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      if (count) this.active.set((this.active() + (event.key === 'ArrowDown' ? 1 : count - 1)) % count);
    } else if (event.key === 'Enter') {
      event.preventDefault();
      const hit = this.hits()[this.active()];
      if (hit && !this.loading()) this.open(hit);
    }
  }

  open(hit: SearchHit) {
    this.modal.close();
    void this.router.navigate(hit.link);
  }

  private search(q: string) {
    const request = ++this.requestNo;
    this.failed.set(false);
    // Fällt ein Bereich aus, bleiben die anderen Treffer sichtbar.
    const safe = (source: Observable<SearchHit[]>) => source.pipe(catchError(() => (this.failed.set(true), of([] as SearchHit[]))));
    const place = (c: { postal_code?: string | null; city?: string | null } | null | undefined) => [c?.postal_code, c?.city].filter(Boolean).join(' ');

    forkJoin([
      safe(this.customers.list({ q, type: '', archived: false, page: 1, sort: 'name', dir: 'asc' }).pipe(map((p) =>
        p.data.slice(0, PER_GROUP).map((c): SearchHit => ({ group: 'Kunden', title: c.display_name, detail: `${place(c)} ${c.customer_number}`.trim(), link: ['/kunden', c.id] }))))),
      safe(this.journeys.list(q, 1, 'alle').pipe(map((p) =>
        p.data.slice(0, PER_GROUP).map((j): SearchHit => ({ group: 'Reisen', title: j.title, detail: j.customer?.display_name ?? '', link: ['/reisen', j.id] }))))),
      safe(this.documents.list({ q, type: '', status: '', page: 1 }).pipe(map((p) =>
        p.data.slice(0, PER_GROUP).map((d): SearchHit => ({ group: 'Belege', title: `${d.number ?? 'Entwurf'} ${d.subject ?? ''}`.trim(), detail: d.customer?.display_name ?? '', link: ['/belege', d.id] }))))),
      safe(this.tripOrders.list({ q, status: '', journeyId: '', page: 1 }).pipe(map((p) =>
        p.data.slice(0, PER_GROUP).map((o): SearchHit => ({ group: 'Fahrtaufträge', title: o.title, detail: `${o.number ?? ''} ${o.customer?.display_name ?? ''}`.trim(), link: ['/fahrtauftraege', o.id] }))))),
    ]).subscribe((groups) => {
      if (request !== this.requestNo) return;
      this.hits.set(groups.flat());
      this.shown.set(q);
      this.active.set(0);
      this.loading.set(false);
    });
  }
}

/** Öffnet das Suchfenster (nur einmal gleichzeitig). */
@Injectable({ providedIn: 'root' })
export class GlobalSearchService {
  private modals = inject(NgbModal);

  open() {
    if (this.modals.hasOpenModals()) return;
    this.modals.open(GlobalSearchComponent, { size: 'lg', ariaLabelledBy: undefined }).result.catch(() => undefined);
  }
}
