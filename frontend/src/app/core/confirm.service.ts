import { Component, Injectable, Input, inject } from '@angular/core';
import { NgbActiveModal, NgbModal } from '@ng-bootstrap/ng-bootstrap';

export interface ConfirmOptions {
  title: string;
  message: string;
  confirmLabel?: string;
  /** Beschriftung der zweiten Taste, wenn „Abbrechen“ nicht passt */
  cancelLabel?: string;
  /** Rot hervorgehoben bei unwiderruflichen Aktionen. */
  danger?: boolean;
}

@Component({
  selector: 'app-confirm-modal',
  template: `
    <div class="modal-header">
      <h2 class="modal-title fs-5">{{ title }}</h2>
      <button type="button" class="btn-close" aria-label="Schließen" (click)="modal.dismiss()"></button>
    </div>
    <div class="modal-body" style="white-space: pre-line">{{ message }}</div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline-secondary" (click)="modal.dismiss()">{{ cancelLabel }}</button>
      <button type="button" class="btn" [class.btn-danger]="danger" [class.btn-primary]="!danger" (click)="modal.close(true)">
        {{ confirmLabel }}
      </button>
    </div>
  `,
})
export class ConfirmModalComponent {
  modal = inject(NgbActiveModal);
  @Input() title = '';
  @Input() message = '';
  @Input() confirmLabel = 'OK';
  @Input() cancelLabel = 'Abbrechen';
  @Input() danger = false;
}

/** Ersetzt die Browser-Abfrage confirm() durch einen Dialog im Stil der Anwendung. */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  private modals = inject(NgbModal);

  ask(o: ConfirmOptions): Promise<boolean> {
    const ref = this.modals.open(ConfirmModalComponent, { centered: true });
    Object.assign(ref.componentInstance, {
      title: o.title,
      message: o.message,
      confirmLabel: o.confirmLabel ?? 'OK',
      cancelLabel: o.cancelLabel ?? 'Abbrechen',
      danger: o.danger ?? false,
    });
    return ref.result.then(
      () => true,
      () => false,
    );
  }
}
