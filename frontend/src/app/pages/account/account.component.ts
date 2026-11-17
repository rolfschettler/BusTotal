import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { roleLabel } from '../../core/roles';
import { HelpLinkComponent } from '../../help/help-link.component';

/** Beide neuen Passwörter müssen gleich sein. */
const sameAsPassword = (c: AbstractControl): ValidationErrors | null =>
  c.get('password')?.value === c.get('password_confirmation')?.value ? null : { mismatch: true };

/** Mein Konto: eigenes Passwort ändern. Auch die Pflichtseite nach einem vorläufigen Passwort. */
@Component({
  selector: 'app-account',
  imports: [ReactiveFormsModule, HelpLinkComponent],
  templateUrl: './account.component.html',
})
export class AccountComponent {
  private auth = inject(AuthService);
  private router = inject(Router);

  user = this.auth.user;
  forced = computed(() => this.auth.user()?.must_change_password === true);
  roleLabel = roleLabel;

  form = inject(FormBuilder).nonNullable.group(
    {
      current_password: ['', Validators.required],
      password: ['', [Validators.required, Validators.minLength(10), Validators.pattern(/[A-Za-z]/), Validators.pattern(/\d/)]],
      password_confirmation: ['', Validators.required],
    },
    { validators: sameAsPassword },
  );

  busy = signal(false);
  done = signal(false);
  errors = signal<Record<string, string>>({});
  error = signal<string | null>(null);

  submit() {
    if (this.form.invalid || this.busy()) {
      this.form.markAllAsTouched();
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
        this.done.set(true);
        // Nach der Pflichtänderung geht es direkt weiter zur Startseite.
        if (wasForced) this.router.navigate(['/']);
      },
      error: (e: HttpErrorResponse) => {
        this.busy.set(false);
        const fields: Record<string, string[]> = e.error?.errors ?? {};
        this.errors.set(Object.fromEntries(Object.entries(fields).map(([k, v]) => [k, v[0]])));
        this.error.set(e.status === 429 ? 'Zu viele Versuche. Bitte eine Minute warten.' : (e.error?.message ?? 'Das Passwort konnte nicht geändert werden.'));
      },
    });
  }
}
