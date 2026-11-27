import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { NgbActiveModal, NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { Observable } from 'rxjs';
import { httpErrorText } from '../core/http-error';

export interface ReasonOptions<T> {
  title: string;
  /** Erklärt in einfachen Worten, was passiert */
  message: string;
  confirmLabel: string;
  /** Ersatztext, wenn der Server keine verständliche Meldung liefert */
  errorText: string;
  save: (reason: string) => Observable<T>;
}

/**
 * Fenster für eine Aktion, die eine Begründung braucht (etwas zurücknehmen): erklärt die Folgen, erfasst die Begründung und
 * schickt sie ab. Fehler bleiben im Fenster. Öffnen über askReason().
 */
@Component({
  selector: 'app-reason-modal',
  imports: [FormsModule],
  template: `
    <form (ngSubmit)="submit()" novalidate>
      <div class="modal-header">
        <h2 class="modal-title fs-5">{{ options.title }}</h2>
        <button type="button" class="btn-close" aria-label="Schließen" (click)="modal.dismiss()"></button>
      </div>
      <div class="modal-body">
        <p class="small">{{ options.message }}</p>
        <label class="form-label small mb-1" for="reasonField">Begründung</label>
        <input type="text" class="form-control" id="reasonField" name="reason" maxlength="255" autocomplete="off" ngbAutofocus
               [(ngModel)]="reason" [class.is-invalid]="!!error()" />
        @if (error(); as e) { <div class="text-danger small mt-1" role="alert">{{ e }}</div> }
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" (click)="modal.dismiss()">Abbrechen</button>
        <button type="submit" class="btn btn-primary" [disabled]="saving()">
          @if (saving()) { <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> }
          {{ options.confirmLabel }}
        </button>
      </div>
    </form>
  `,
})
export class ReasonModalComponent {
  modal = inject(NgbActiveModal);

  /** Setzt askReason() vor dem ersten Anzeigen. */
  options!: ReasonOptions<unknown>;

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
    this.options.save(this.reason.trim()).subscribe({
      next: (result) => this.modal.close(result),
      error: (e: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(httpErrorText(e, this.options.errorText));
      },
    });
  }
}

/** Öffnet das Fenster; liefert das Ergebnis des Speicherns oder null bei Abbruch. */
export function askReason<T>(modals: NgbModal, options: ReasonOptions<T>): Promise<T | null> {
  const ref = modals.open(ReasonModalComponent, { centered: true });
  (ref.componentInstance as ReasonModalComponent).options = options as ReasonOptions<unknown>;
  return ref.result.then(
    (result: T) => result,
    () => null,
  );
}
