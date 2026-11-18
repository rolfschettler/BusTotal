import { HttpErrorResponse } from '@angular/common/http';
import { AfterViewInit, Component, ElementRef, inject, signal, viewChild } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { BrandService } from '../../core/brand.service';
import { fieldErrors, httpErrorText, isUnreachable } from '../../core/http-error';

/** Unter diesem Schlüssel merkt sich der Browser die E-Mail-Adresse der letzten erfolgreichen Anmeldung. */
const LAST_EMAIL_KEY = 'bustotal_last_email';

// localStorage kann fehlen oder gesperrt sein (privates Fenster, Richtlinien): dann geht es ohne.
function readLastEmail(): string {
  try {
    return localStorage.getItem(LAST_EMAIL_KEY) ?? '';
  } catch {
    return '';
  }
}
function writeLastEmail(email: string | null) {
  try {
    if (email) localStorage.setItem(LAST_EMAIL_KEY, email);
    else localStorage.removeItem(LAST_EMAIL_KEY);
  } catch {
    /* nicht schlimm */
  }
}

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule],
  templateUrl: './login.component.html',
  styleUrl: './login.component.scss',
})
export class LoginComponent implements AfterViewInit {
  private auth = inject(AuthService);
  private router = inject(Router);
  brand = inject(BrandService);

  private emailInput = viewChild.required<ElementRef<HTMLInputElement>>('emailInput');
  private passwordInput = viewChild.required<ElementRef<HTMLInputElement>>('passwordInput');

  /** E-Mail-Adresse der letzten erfolgreichen Anmeldung, falls vorhanden */
  remembered = signal(readLastEmail());

  form = inject(FormBuilder).nonNullable.group({
    email: [this.remembered(), [Validators.required, Validators.email]],
    password: ['', Validators.required],
  });

  error = signal<string | null>(null);
  busy = signal(false);

  ngAfterViewInit() {
    // Ist die Adresse schon da, wartet der Cursor im Passwortfeld, sonst im E-Mail-Feld.
    (this.remembered() ? this.passwordInput() : this.emailInput()).nativeElement.focus();
  }

  /** Auf gemeinsam genutzten PCs: die gemerkte Adresse vergessen und neu eingeben. */
  otherUser() {
    writeLastEmail(null);
    this.remembered.set('');
    // Erst den Cursor setzen: Das Verlassen des Passwortfelds löst dessen "berührt" aus. Würde das
    // nach dem Leeren passieren, erschiene sofort eine Fehlermeldung.
    this.emailInput().nativeElement.focus();
    // Ausdrücklich leeren: reset() ohne Wert würde den Startwert zurückholen, und der war die gemerkte Adresse.
    this.form.reset({ email: '', password: '' });
    this.error.set(null);
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
      next: () => {
        writeLastEmail(email.trim().toLowerCase()); // erst nach Erfolg, nie das Passwort
        this.router.navigate(['/']);
      },
      error: (err: HttpErrorResponse) => {
        this.busy.set(false);
        this.error.set(
          err.status === 404
            ? 'Firma unbekannt oder gesperrt. Bitte die Adresse prüfen.'
            : (fieldErrors(err)['email'] ?? httpErrorText(err, 'Anmeldung fehlgeschlagen.')),
        );
        // Nur bei falschem Passwort gleich neu tippen lassen, nicht wenn der Server nicht antwortet.
        if (!isUnreachable(err) && err.status < 500) this.passwordInput().nativeElement.select();
      },
    });
  }
}
