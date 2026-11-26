import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';
import { httpErrorText } from '../../core/http-error';
import { CancellationPreview, JourneyDetail, JourneyService } from '../../core/journey.service';
import { formatAmount } from '../documents/document-calc';

/** Heute als JJJJ-MM-TT in der Zeit des Browsers (nicht UTC: kurz nach Mitternacht wäre das gestern). */
export function localToday(now = new Date()): string {
  const two = (n: number) => String(n).padStart(2, '0');
  return `${now.getFullYear()}-${two(now.getMonth() + 1)}-${two(now.getDate())}`;
}

/**
 * Fenster „Reise absagen“: zeigt vorher, was die Absage bewirkt (Fahrtaufträge, schon eingegangenes Geld), und erfasst Datum und
 * Grund. Schließt mit der abgesagten Reise. Fehler bleiben im Fenster, die Eingaben gehen nicht verloren.
 */
@Component({
  selector: 'app-journey-cancel-modal',
  imports: [FormsModule],
  template: `
    <form (ngSubmit)="submit()" novalidate>
      <div class="modal-header">
        <h2 class="modal-title fs-5">Reise absagen: {{ journey.title }}</h2>
        <button type="button" class="btn-close" aria-label="Schließen" (click)="modal.dismiss()"></button>
      </div>
      <div class="modal-body">
        <p class="small">Die Reise und alle Belege bleiben erhalten. Sie wird nicht mehr geplant, und Sie können die Absage später zurücknehmen.</p>

        @if (preview(); as p) {
          @if (p.orders.length) {
            <p class="mb-1 fw-semibold">
              {{ p.orders.length === 1 ? 'Dieser Fahrtauftrag wird' : 'Diese ' + p.orders.length + ' Fahrtaufträge werden' }} mit abgesagt:
            </p>
            <ul class="small">
              @for (o of p.orders; track o.id) {
                <li>
                  {{ o.number ?? 'Ohne Nummer' }}: {{ o.title }}, Bus {{ o.bus_no }} ({{ o.status_label }})@if (o.drivers.length) {, {{ o.drivers.join(', ') }}}
                </li>
              }
            </ul>
            <p class="small">Bitte informieren Sie die eingeteilten Fahrer und Fremdunternehmer selbst.</p>
          }
          @if (p.kept) {
            <p class="small">{{ p.kept === 1 ? 'Ein erledigter Fahrtauftrag bleibt' : p.kept + ' erledigte Fahrtaufträge bleiben' }} unverändert.</p>
          }
          @if (p.payments.length) {
            <div class="alert alert-warning py-2 small">
              <strong>Schon eingegangen: {{ amount(p.paid_total) }}</strong>
              <ul class="mb-1">
                @for (z of p.payments; track z.id) { <li>{{ z.type_label }} {{ z.number }}: {{ amount(z.paid) }}</li> }
              </ul>
              Das Geld wird nicht automatisch zurückgezahlt oder verrechnet. Eine Stornogebühr stellen Sie über „Neuer Beleg“ als Rechnung.
            </div>
          } @else {
            <p class="small">Eine Stornogebühr stellen Sie bei Bedarf über „Neuer Beleg“ als Rechnung.</p>
          }
        } @else if (!previewError()) {
          <div class="d-flex justify-content-center py-3"><div class="spinner-border spinner-border-sm text-primary" aria-label="Wird geladen"></div></div>
        }

        <div class="row g-3">
          <div class="col-12 col-lg-3">
            <label class="form-label small mb-1" for="cancelDate">Abgesagt am</label>
            <input type="date" class="form-control" id="cancelDate" name="date" [max]="today" [(ngModel)]="date" [class.is-invalid]="!!errors()['date']" />
            <div class="invalid-feedback">{{ errors()['date'] }}</div>
          </div>
          <div class="col-12 col-lg-9">
            <label class="form-label small mb-1" for="cancelReason">Grund</label>
            <input type="text" class="form-control" id="cancelReason" name="reason" maxlength="255" autocomplete="off" ngbAutofocus
                   [(ngModel)]="reason" [class.is-invalid]="!!errors()['reason']" />
            <div class="invalid-feedback">{{ errors()['reason'] }}</div>
          </div>
        </div>

        @if (previewError() || error()) {
          <div class="alert alert-danger py-2 small mt-3 mb-0" role="alert">{{ error() || previewError() }}</div>
        }
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" (click)="modal.dismiss()">Abbrechen</button>
        <!-- Erst absagen, wenn die Folgen zu sehen waren -->
        <button type="submit" class="btn btn-danger" [disabled]="saving() || !preview()">
          @if (saving()) { <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> }
          Reise absagen
        </button>
      </div>
    </form>
  `,
})
export class JourneyCancelModalComponent implements OnInit {
  modal = inject(NgbActiveModal);
  private api = inject(JourneyService);

  /** Setzt der Aufrufer vor dem ersten Anzeigen. */
  journey!: JourneyDetail;

  readonly today = localToday();
  readonly amount = formatAmount;
  date = this.today;
  reason = '';
  preview = signal<CancellationPreview | null>(null);
  previewError = signal<string | null>(null);
  error = signal<string | null>(null);
  errors = signal<Record<string, string>>({});
  saving = signal(false);

  ngOnInit() {
    this.api.cancellationPreview(this.journey.id).subscribe({
      next: (p) => this.preview.set(p),
      error: (e: HttpErrorResponse) => this.previewError.set(httpErrorText(e, 'Die Folgen der Absage konnten nicht geladen werden.')),
    });
  }

  submit() {
    if (this.saving() || !this.preview()) return;
    const missing: Record<string, string> = {};
    if (!this.date) missing['date'] = 'Bitte angeben, wann die Reise abgesagt wurde.';
    if (!this.reason.trim()) missing['reason'] = 'Bitte einen Grund für die Absage angeben.';
    this.errors.set(missing);
    this.error.set(null);
    if (Object.keys(missing).length) return;

    this.saving.set(true);
    this.api.cancel(this.journey.id, this.date, this.reason.trim()).subscribe({
      next: (j) => this.modal.close(j),
      error: (e: HttpErrorResponse) => {
        this.saving.set(false);
        const server: Record<string, string[]> = e.error?.errors ?? {};
        this.errors.set(Object.fromEntries(Object.entries(server).map(([k, v]) => [k, v[0]])));
        this.error.set(httpErrorText(e, 'Die Reise konnte nicht abgesagt werden.'));
      },
    });
  }
}
