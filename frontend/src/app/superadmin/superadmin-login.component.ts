import { HttpErrorResponse } from '@angular/common/http';
import { AfterViewInit, Component, ElementRef, inject, signal, viewChild } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { APP_NAME } from '../core/app-info';
import { SuperadminAuthService } from './superadmin-auth.service';

/**
 * Anmeldung der Betreiber. Wie die Firmen-Anmeldung, aber ohne "E-Mail merken": Betreiber-Zugänge
 * sollen auch auf fremden Rechnern keine Spuren hinterlassen.
 */
@Component({
  selector: 'app-superadmin-login',
  imports: [ReactiveFormsModule],
  templateUrl: './superadmin-login.component.html',
  styles: `
    .login { min-height: 100vh; }
    .card { width: 100%; max-width: 380px; }
  `,
})
export class SuperadminLoginComponent implements AfterViewInit {
  private auth = inject(SuperadminAuthService);
  private router = inject(Router);

  app = APP_NAME;
  private emailInput = viewChild.required<ElementRef<HTMLInputElement>>('emailInput');
  private passwordInput = viewChild.required<ElementRef<HTMLInputElement>>('passwordInput');

  form = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
  });

  error = signal<string | null>(null);
  busy = signal(false);

  ngAfterViewInit() {
    this.emailInput().nativeElement.focus();
  }

  submit() {
    if (this.form.invalid || this.busy()) {
      this.form.markAllAsTouched();
      return;
    }
    this.busy.set(true);
    this.error.set(null);

    const { email, password } = this.form.getRawValue();
    this.auth.login(email, password).subscribe({
      next: () => this.router.navigate(['/']),
      error: (err: HttpErrorResponse) => {
        this.busy.set(false);
        this.error.set(
          err.status === 429
            ? 'Zu viele Versuche. Bitte eine Minute warten.'
            : err.status === 404
              ? 'Diese Adresse ist nicht erreichbar. Bitte den Betreiber-Zugang des Servers prüfen.'
              : (err.error?.errors?.email?.[0] ?? 'Anmeldung fehlgeschlagen.'),
        );
        this.passwordInput().nativeElement.select();
      },
    });
  }
}
