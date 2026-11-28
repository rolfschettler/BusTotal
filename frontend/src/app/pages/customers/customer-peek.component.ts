import { DatePipe } from '@angular/common';
import { Component, computed, inject, input, output, signal } from '@angular/core';
import { toObservable, takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, debounceTime, distinctUntilChanged, map, of, switchMap, tap } from 'rxjs';
import { AuthService } from '../../core/auth.service';
import { Customer } from '../../core/customer.service';
import { BusDocument, DocumentService, DocumentTypeValue } from '../../core/document.service';
import { AvatarComponent } from '../../shared/avatar.component';
import { formatAmount } from '../documents/document-calc';

const TYPE_LABELS: Record<DocumentTypeValue, string> = {
  angebot: 'Angebot',
  auftragsbestaetigung: 'Auftragsbestätigung',
  anzahlung: 'Anzahlungsrechnung',
  rechnung: 'Rechnung',
  gutschrift: 'Stornorechnung',
};

/** Vorschau neben der Kundenliste: Kontakt auf einen Blick und die neuesten Belege des Kunden. */
@Component({
  selector: 'app-customer-peek',
  imports: [DatePipe, RouterLink, AvatarComponent],
  template: `
    @if (customer(); as c) {
      <app-avatar [name]="c.display_name" [large]="true" class="mb-2" />
      <h2 class="fs-5 mb-0">{{ c.display_name }}</h2>
      <div class="text-body-secondary">{{ typeLabel() }}</div>

      <ul class="peek-facts">
        @if (c.phone || c.mobile; as phone) { <li><i class="bi bi-telephone" aria-label="Telefon"></i><span>{{ phone }}</span></li> }
        @if (c.email) { <li><i class="bi bi-envelope" aria-label="E-Mail"></i><span>{{ c.email }}</span></li> }
        @if (c.street || c.city) { <li><i class="bi bi-geo-alt" aria-label="Anschrift"></i><span>{{ c.street }}<br />{{ c.postal_code }} {{ c.city }}</span></li> }
      </ul>

      <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary" (click)="opened.emit(c.id)">Kunde öffnen</button>
        @if (canPlan() && !c.deleted_at) { <a class="btn btn-outline-secondary" routerLink="/reisen/neu" [queryParams]="{ kunde: c.id }">Neue Reise</a> }
      </div>

      <h3 class="peek-title">Neueste Belege</h3>
      @if (loading()) {
        <div class="text-body-secondary small">Wird geladen …</div>
      } @else if (failed()) {
        <div class="text-danger small" role="alert">Die Belege konnten nicht geladen werden.</div>
      } @else {
        @for (d of documents(); track d.id) {
          <a class="peek-item" [routerLink]="['/belege', d.id]">
            <span>{{ label(d.type) }} {{ d.number ?? '(Entwurf)' }}<small>{{ d.subject }}@if (d.document_date) { · {{ d.document_date | date: 'dd.MM.yyyy' }} }</small></span>
            <span class="text-nowrap">{{ amount(d.gross_total) }}</span>
          </a>
        } @empty {
          <div class="text-body-secondary small">Noch keine Belege.</div>
        }
        @if (total() > documents().length) {
          <a class="d-inline-block mt-2 small" routerLink="/belege" [queryParams]="{ kunde: c.id }">Alle {{ total() }} Belege anzeigen</a>
        }
      }
    } @else {
      <p class="text-body-secondary mb-0">Wählen Sie einen Kunden, dann stehen hier Kontakt und Belege.</p>
    }
  `,
  styles: `
    :host { display: block; }
    app-avatar { display: grid; }
    .peek-facts { display: grid; gap: 0.6rem; margin: 1.125rem 0; padding: 0; list-style: none; }
    .peek-facts li { display: flex; gap: 0.75rem; overflow-wrap: anywhere; .bi { color: var(--ui-muted); } }
    .peek-title { margin: 1.5rem 0 0.25rem; font-size: 0.875rem; }
    .peek-item {
      display: flex; justify-content: space-between; gap: 0.75rem; padding: 0.6rem 0; border-top: 1px solid var(--ui-line);
      color: var(--ui-text) !important; text-decoration: none;
      small { display: block; color: var(--ui-muted); }
    }
  `,
})
export class CustomerPeekComponent {
  private api = inject(DocumentService);
  private auth = inject(AuthService);

  customer = input<Customer | null>(null);
  typeLabel = input('');
  opened = output<string>();

  documents = signal<BusDocument[]>([]);
  total = signal(0);
  loading = signal(false);
  failed = signal(false);

  canPlan = computed(() => ['admin', 'disponent'].includes(this.auth.user()?.role ?? ''));
  readonly amount = formatAmount;
  label = (type: DocumentTypeValue) => TYPE_LABELS[type] ?? type;

  constructor() {
    // Erst kurz nach dem letzten Wechsel laden: Wer mit den Pfeiltasten durch die Liste läuft, löst nicht je Zeile eine Anfrage aus.
    toObservable(this.customer)
      .pipe(
        map((c) => c?.id ?? null),
        distinctUntilChanged(),
        tap(() => {
          this.documents.set([]);
          this.total.set(0);
          this.failed.set(false);
          this.loading.set(true);
        }),
        debounceTime(250),
        switchMap((id) =>
          id
            ? this.api.list({ q: '', customerId: id, type: '', status: '', page: 1, sort: 'date', dir: 'desc' }).pipe(
                catchError(() => (this.failed.set(true), of(null))),
              )
            : of(null),
        ),
        takeUntilDestroyed(),
      )
      .subscribe((p) => {
        this.documents.set(p?.data.slice(0, 4) ?? []);
        this.total.set(p?.total ?? 0);
        this.loading.set(false);
      });
  }
}
