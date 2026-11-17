import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Role } from './auth.service';

export interface ManagedUser {
  id: string;
  name: string;
  email: string;
  role: Role;
  active: boolean;
  must_change_password: boolean;
  last_login_at: string | null;
  created_at: string;
  /** Nur direkt nach Anlegen oder Zurücksetzen vorhanden, wird nirgends gespeichert. */
  temporary_password?: string;
}

export interface UserInput {
  name: string;
  email: string;
  role: Role;
}

@Injectable({ providedIn: 'root' })
export class UserAdminService {
  private http = inject(HttpClient);

  list() {
    return this.http.get<ManagedUser[]>('/api/users');
  }
  create(data: UserInput) {
    return this.http.post<ManagedUser>('/api/users', data);
  }
  update(id: string, data: UserInput & { active: boolean }) {
    return this.http.put<ManagedUser>(`/api/users/${id}`, data);
  }
  resetPassword(id: string) {
    return this.http.post<ManagedUser>(`/api/users/${id}/reset-password`, {});
  }
}
