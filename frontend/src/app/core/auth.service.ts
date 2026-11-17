import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { tap } from 'rxjs';

export type Role = 'admin' | 'disponent' | 'buchhaltung' | 'fahrer';

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
}

interface LoginResponse {
  token: string;
  user: User;
}

const TOKEN_KEY = 'bustotal_token';

/** Kümmert sich um Anmelden, Abmelden und den aktuellen Benutzer. */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  private router = inject(Router);

  private _token = signal<string | null>(localStorage.getItem(TOKEN_KEY));
  private _user = signal<User | null>(null);

  readonly token = this._token.asReadonly();
  readonly user = this._user.asReadonly();
  readonly isLoggedIn = computed(() => this._token() !== null);

  login(email: string, password: string) {
    return this.http.post<LoginResponse>('/api/login', { email, password }).pipe(
      tap((res) => {
        localStorage.setItem(TOKEN_KEY, res.token);
        this._token.set(res.token);
        this._user.set(res.user);
      }),
    );
  }

  /** Beim Seitenstart: Token vorhanden, aber Benutzer noch nicht geladen. */
  loadUser() {
    return this.http.get<User>('/api/me').pipe(tap((u) => this._user.set(u)));
  }

  logout() {
    // Server informieren (Protokoll, Token löschen) – lokal auf jeden Fall abmelden.
    this.http.post('/api/logout', {}).subscribe({ error: () => {} });
    this.clear();
  }

  /** Lokale Sitzung beenden, z. B. nach 401 vom Server. */
  clear() {
    localStorage.removeItem(TOKEN_KEY);
    this._token.set(null);
    this._user.set(null);
    this.router.navigate(['/login']);
  }
}
