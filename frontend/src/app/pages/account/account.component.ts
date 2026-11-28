import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, computed, inject, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { useFormPage } from '../../core/form-page';
import { httpErrorText } from '../../core/http-error';
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
  host: { class: 'd-block' },
})
export class AccountComponent {
  private auth = inject(AuthService);
  private router = inject(Router);
  private host = inject<ElementRef<HTMLElement>>(ElementRef);

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
  /** Nach dem ersten Speichern-Versuch markiert das Formular fehlerhafte Felder (Formular-Muster). */
  formSubmitted = signal(false);
  done = signal(false);
  errors = signal<Record<string, string>>({});
  error = signal<string | null>(null);

  /** Feld rot markieren: ungültig (nach Speichern-Versuch) oder vom Server abgelehnt. */
  invalid(name: 'current_password' | 'password'): boolean {
    return (this.formSubmitted() && this.form.controls[name].invalid) || !!this.errors()[name];
  }

  confirmInvalid(): boolean {
    return this.formSubmitted() && (this.form.hasError('mismatch') || this.form.controls.password_confirmation.invalid);
  }

  /** Strg+S ändert das Passwort; beim Verlassen mit Eingaben wird nachgefragt. */
  canLeave = useFormPage({
    unsaved: () => this.form.dirty,
    save: () => this.submit(),
  });

  /** „Zurück“ oben: zur Startseite. */
  back() {
    this.router.navigate(['/']);
  }

  /** Tastenleiste "Abbrechen": Eingaben verwerfen. */
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
        // Nach der Pflichtänderung geht es direkt weiter zur Startseite.
        if (wasForced) this.router.navigate(['/']);
      },
      error: (e: HttpErrorResponse) => {
        this.busy.set(false);
        const fields: Record<string, string[]> = e.error?.errors ?? {};
        this.errors.set(Object.fromEntries(Object.entries(fields).map(([k, v]) => [k, v[0]])));
        this.toTop();
        this.error.set(httpErrorText(e, 'Das Passwort konnte nicht geändert werden.'));
      },
    });
  }

  /** Meldungen stehen oben, die Tastenleiste unten: nach oben scrollen, damit man sie sieht. */
  private toTop() {
    this.host.nativeElement.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
}
