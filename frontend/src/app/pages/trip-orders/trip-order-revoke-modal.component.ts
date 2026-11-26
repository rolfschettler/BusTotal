import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NgbActiveModal } from '@ng-bootstrap/ng-bootstrap';
import { httpErrorText } from '../../core/http-error';
import { TripOrder, TripOrderService } from '../../core/trip-order.service';

/** Fenster „Ausgabe zurücknehmen“: erklärt die Folgen und erfasst die Begründung. Schließt mit dem geänderten Fahrtauftrag. */
@Component({
  selector: 'app-trip-order-revoke-modal',
  imports: [FormsModule],
  template: `
    <form (ngSubmit)="submit()" novalidate>
      <div class="modal-header">
        <h2 class="modal-title fs-5">Ausgabe zurücknehmen: {{ order.number }}</h2>
        <button type="button" class="btn-close" aria-label="Schließen" (click)="modal.dismiss()"></button>
      </div>
      <div class="modal-body">
        <p class="small">Der Auftrag ist wieder frei änderbar und behält seine Nummer. Hat der Fahrer ihn schon, informieren Sie ihn bitte.</p>
        <label class="form-label small mb-1" for="revokeReason">Begründung</label>
        <input type="text" class="form-control" id="revokeReason" name="reason" maxlength="255" autocomplete="off" ngbAutofocus
               [(ngModel)]="reason" [class.is-invalid]="!!error()" />
        @if (error(); as e) { <div class="text-danger small mt-1" role="alert">{{ e }}</div> }
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" (click)="modal.dismiss()">Abbrechen</button>
        <button type="submit" class="btn btn-primary" [disabled]="saving()">
          @if (saving()) { <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> }
          Zurücknehmen
        </button>
      </div>
    </form>
  `,
})
export class TripOrderRevokeModalComponent {
  modal = inject(NgbActiveModal);
  private api = inject(TripOrderService);

  /** Setzt der Aufrufer vor dem ersten Anzeigen. */
  order!: TripOrder;

  reason = '';
  error = signal<string | null>(null);
  saving = signal(false);

  submit() {
    if (this.saving()) return;
    if (!this.reason.trim()) {
      this.error.set('Bitte eine Begründung angeben.');
      return;
    }
    this.saving.set(true);
    this.error.set(null);
    this.api.revoke(this.order.id, this.reason.trim()).subscribe({
      next: (o) => this.modal.close(o),
      error: (e: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(httpErrorText(e, 'Die Ausgabe konnte nicht zurückgenommen werden.'));
      },
    });
  }
}
