import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { tap } from 'rxjs';

export interface Operator {
  id: string;
  name: string;
  email: string;
  /** true, solange noch das vorläufige Passwort gilt */
  must_change_password: boolean;
}

interface LoginResponse {
  token: string;
  user: Operator;
}

/** Eigener Schlüssel: Das Betreiber-Token hat mit den Firmen-Token nichts zu tun. */
const TOKEN_KEY = 'bustotal_superadmin_token';

/** Anmelden, Abmelden und der angemeldete Betreiber (nur an der Betreiber-Adresse in Gebrauch). */
@Injectable({ providedIn: 'root' })
export class SuperadminAuthService {
  private http = inject(HttpClient);
  private router = inject(Router);

  private _token = signal<string | null>(localStorage.getItem(TOKEN_KEY));
  private _user = signal<Operator | null>(null);

  readonly token = this._token.asReadonly();
  readonly user = this._user.asReadonly();
  readonly isLoggedIn = computed(() => this._token() !== null);

  login(email: string, password: string) {
    return this.http.post<LoginResponse>('/api/superadmin/login', { email, password }).pipe(
      tap((res) => {
        localStorage.setItem(TOKEN_KEY, res.token);
        this._token.set(res.token);
        this._user.set(res.user);
      }),
    );
  }

  loadUser() {
    return this.http.get<Operator>('/api/superadmin/me').pipe(tap((u) => this._user.set(u)));
  }

  changePassword(current_password: string, password: string, password_confirmation: string) {
    return this.http
      .post<{ message: string }>('/api/superadmin/me/password', { current_password, password, password_confirmation })
      .pipe(tap(() => this._user.update((u) => (u ? { ...u, must_change_password: false } : u))));
  }

  logout() {
    this.http.post('/api/superadmin/logout', {}).subscribe({ error: () => {} });
    this.clear();
  }

  clear() {
    localStorage.removeItem(TOKEN_KEY);
    this._token.set(null);
    this._user.set(null);
    this.router.navigate(['/login']);
  }
}
