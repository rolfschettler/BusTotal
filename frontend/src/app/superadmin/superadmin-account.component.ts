import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, computed, inject, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { ConfirmService } from '../core/confirm.service';
import { HelpLinkComponent } from '../help/help-link.component';
import { SuperadminAuthService } from './superadmin-auth.service';

const sameAsPassword = (c: AbstractControl): ValidationErrors | null =>
  c.get('password')?.value === c.get('password_confirmation')?.value ? null : { mismatch: true };

/** Mein Konto (Betreiber): eigenes Passwort ändern. Für Betreiber gilt eine höhere Mindestlänge (12 Zeichen). */
@Component({
  selector: 'app-superadmin-account',
  imports: [ReactiveFormsModule, HelpLinkComponent],
  templateUrl: './superadmin-account.component.html',
  host: { class: 'd-block' },
})
export class SuperadminAccountComponent {
  private auth = inject(SuperadminAuthService);
  private router = inject(Router);
  private confirm = inject(ConfirmService);
  private host = inject<ElementRef<HTMLElement>>(ElementRef);

  user = this.auth.user;
  forced = computed(() => this.auth.user()?.must_change_password === true);

  form = inject(FormBuilder).nonNullable.group(
    {
      current_password: ['', Validators.required],
      password: ['', [Validators.required, Validators.minLength(12), Validators.pattern(/[A-Za-z]/), Validators.pattern(/\d/)]],
      password_confirmation: ['', Validators.required],
    },
    { validators: sameAsPassword },
  );

  busy = signal(false);
  formSubmitted = signal(false);
  done = signal(false);
  errors = signal<Record<string, string>>({});
  error = signal<string | null>(null);

  invalid(name: 'current_password' | 'password'): boolean {
    return (this.formSubmitted() && this.form.controls[name].invalid) || !!this.errors()[name];
  }

  confirmInvalid(): boolean {
    return this.formSubmitted() && (this.form.hasError('mismatch') || this.form.controls.password_confirmation.invalid);
  }

  async back() {
    if (this.form.dirty) {
      const ok = await this.confirm.ask({
        title: 'Eingaben verwerfen',
        message: 'Das Passwort wurde noch nicht geändert. Trotzdem zurück?',
        confirmLabel: 'Verwerfen',
        danger: true,
      });
      if (!ok) return;
    }
    this.router.navigate(['/']);
  }

  cancel() {
    this.form.reset();
    this.formSubmitted.set(false);
    this.errors.set({});
    this.error.set(null);
  }

  submit() {
    if (this.busy()) return;
    this.formSubmitted.set(true);
    if (this.form.invalid) {
      this.error.set('Bitte die markierten Felder korrigieren.');
      this.toTop();
      return;
    }
    this.busy.set(true);
    this.errors.set({});
    this.error.set(null);

    const { current_password, password, password_confirmation } = this.form.getRawValue();
    const wasForced = this.forced();

    this.auth.changePassword(current_password, password, password_confirmation).subscribe({
      next: () => {
        this.busy.set(false);
        this.form.reset();
        this.formSubmitted.set(false);
        this.done.set(true);
        this.toTop();
        if (wasForced) this.router.navigate(['/']);
      },
      error: (e: HttpErrorResponse) => {
        this.busy.set(false);
        const fields: Record<string, string[]> = e.error?.errors ?? {};
        this.errors.set(Object.fromEntries(Object.entries(fields).map(([k, v]) => [k, v[0]])));
        this.toTop();
        this.error.set(e.status === 429 ? 'Zu viele Versuche. Bitte eine Minute warten.' : (e.error?.message ?? 'Das Passwort konnte nicht geändert werden.'));
      },
    });
  }

  private toTop() {
    this.host.nativeElement.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
}
