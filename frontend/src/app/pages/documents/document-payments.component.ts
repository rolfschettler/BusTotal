import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, input, output, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { DocumentService, Payment, PaymentsResponse } from '../../core/document.service';
import { fieldErrors, httpErrorText } from '../../core/http-error';
import { HelpLinkComponent } from '../../help/help-link.component';
import { formatAmount } from './document-calc';

/**
 * Zahlungen zu einer festgeschriebenen Rechnung oder Anzahlungsrechnung: Zahlstand, Buchungsliste und Formular „Zahlung erfassen“.
 *
 * Der Beleg bleibt unverändert (GoBD); der Zahlstand ergibt sich aus den Buchungen. Eine Buchung wird nie gelöscht, nur storniert
 * (mit Grund). Buchen und Stornieren dürfen nur Administrator und Buchhaltung (`canBook`, der Server prüft zusätzlich).
 * Kurzes Inline-Formular nach dem Formular-Muster (docs/ClaudeCodePatterns/EditFormPattern.md): .form-area, Labels oben, Leiste
 * `.form-bar-static`.
 */
@Component({
  selector: 'app-document-payments',
  imports: [DatePipe, ReactiveFormsModule, HelpLinkComponent],
  templateUrl: './document-payments.component.html',
})
export class DocumentPaymentsComponent implements OnInit {
  private api = inject(DocumentService);
  private fb = inject(FormBuilder);

  documentId = input.required<string>();
  canBook = input(false);
  /** Nach jeder Buchung: Der Beleg lädt seinen Zahlstand neu (Liste und Kopf zeigen ihn). */
  changed = output<void>();

  data = signal<PaymentsResponse | null>(null);
  loading = signal(true);
  saving = signal(false);
  message = signal<{ ok: boolean; text: string } | null>(null);
  fieldError = signal<Record<string, string>>({});
  formOpen = signal(false);
  submitted = signal(false);
  /** Buchung, deren Storno-Grund gerade erfragt wird */
  cancelling = signal<string | null>(null);
  cancelReason = signal('');

  readonly amountText = formatAmount;
  summary = computed(() => this.data()?.summary ?? null);

  form = this.fb.nonNullable.group({
    kind: ['eingang' as Payment['kind']],
    paid_on: [this.today(), Validators.required],
    amount: [null as number | null, [Validators.required, Validators.min(0.01)]],
    method: ['ueberweisung'],
    reference: [''],
    note: [''],
  });

  ngOnInit() {
    this.load();
  }

  private today(): string {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  private load() {
    this.loading.set(true);
    this.api.payments(this.documentId()).subscribe({
      next: (r) => {
        this.data.set(r);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.loading.set(false);
        this.message.set({ ok: false, text: httpErrorText(e, 'Die Zahlungen konnten nicht geladen werden.') });
      },
    });
  }

  kindLabel(k: Payment['kind']): string {
    return this.data()?.kinds.find((x) => x.value === k)?.label ?? k;
  }
  methodLabel(m: string): string {
    return this.data()?.methods.find((x) => x.value === m)?.label ?? m;
  }
  /** Rückzahlungen mit Minuszeichen, damit die Richtung sofort auffällt. */
  signed(p: Payment): string {
    return (p.kind === 'rueckzahlung' ? '−' : '') + formatAmount(p.amount);
  }

  // ---------- Formular ----------

  openForm() {
    const open = Number(this.summary()?.open ?? 0);
    this.form.reset({ kind: 'eingang', paid_on: this.today(), amount: open > 0 ? open : null, method: 'ueberweisung', reference: '', note: '' });
    this.fieldError.set({});
    this.submitted.set(false);
    this.message.set(null);
    this.formOpen.set(true);
  }

  closeForm() {
    this.formOpen.set(false);
  }

  /** Bei „Rückzahlung“ ist der Betrag nicht mehr mit dem offenen Betrag vorbelegt, sondern leer. */
  onKindChange() {
    const paid = Number(this.summary()?.paid ?? 0);
    const open = Number(this.summary()?.open ?? 0);
    this.form.controls.amount.setValue(this.form.controls.kind.value === 'eingang' ? (open > 0 ? open : null) : paid > 0 ? paid : null);
  }

  invalid(name: 'paid_on' | 'amount'): boolean {
    return (this.submitted() && this.form.controls[name].invalid) || !!this.fieldError()[name];
  }

  save() {
    this.submitted.set(true);
    this.fieldError.set({});
    if (this.form.invalid) return;
    const v = this.form.getRawValue();
    this.saving.set(true);
    this.api
      .addPayment(this.documentId(), {
        kind: v.kind,
        paid_on: v.paid_on,
        amount: Number(v.amount),
        method: v.method,
        reference: v.reference.trim() || null,
        note: v.note.trim() || null,
      })
      .subscribe({
        next: (r) => {
          this.saving.set(false);
          this.data.set(r);
          this.formOpen.set(false);
          this.message.set({ ok: true, text: v.kind === 'eingang' ? 'Zahlung gebucht.' : 'Rückzahlung gebucht.' });
          this.changed.emit();
        },
        error: (e: HttpErrorResponse) => {
          this.saving.set(false);
          const fe = fieldErrors(e);
          this.fieldError.set(fe);
          // Fehler zu einem Feld stehen am Feld, alles andere (z. B. kein Recht, Server weg) als Meldung
          if (Object.keys(fe).length === 0) this.message.set({ ok: false, text: httpErrorText(e, 'Die Zahlung konnte nicht gebucht werden.') });
        },
      });
  }

  // ---------- Stornieren ----------

  startCancel(p: Payment) {
    this.cancelling.set(p.id);
    this.cancelReason.set('');
    this.message.set(null);
  }

  confirmCancel(p: Payment) {
    const reason = this.cancelReason().trim();
    if (!reason) {
      this.message.set({ ok: false, text: 'Bitte einen Grund für die Stornierung angeben.' });
      return;
    }
    this.api.cancelPayment(this.documentId(), p.id, reason).subscribe({
      next: (r) => {
        this.data.set(r);
        this.cancelling.set(null);
        this.message.set({ ok: true, text: 'Buchung storniert.' });
        this.changed.emit();
      },
      error: (e: HttpErrorResponse) => this.message.set({ ok: false, text: httpErrorText(e, 'Die Buchung konnte nicht storniert werden.') }),
    });
  }
}
