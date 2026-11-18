import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

export interface ManagedTenant {
  id: string;
  name: string;
  subdomain: string;
  /** Vollständiger Hostname, z. B. muster.bustotal.de */
  host: string;
  db_host: string;
  db_port: number;
  db_database: string;
  db_username: string;
  is_active: boolean;
  migrated_at: string | null;
  created_at: string | null;
}

export interface TenantAdminUser {
  id: string;
  name: string;
  email: string;
  active: boolean;
  /** Hat sich noch nicht mit eigenem Passwort angemeldet */
  must_change_password: boolean;
  /** Das vorläufige Passwort ist noch einsehbar */
  has_temporary: boolean;
}

export interface TenantCreateInput {
  name: string;
  subdomain: string;
  db_host: string;
  db_port: number;
  db_database: string;
  db_username: string;
  db_password: string;
  admin_email: string;
  admin_name: string;
}

export interface TenantUpdateInput {
  name: string;
  is_active: boolean;
  db_host: string;
  db_port: number;
  db_username: string;
  /** Leer lassen = Passwort bleibt */
  db_password: string;
}

@Injectable({ providedIn: 'root' })
export class TenantAdminService {
  private http = inject(HttpClient);

  list() {
    return this.http.get<ManagedTenant[]>('/api/superadmin/tenants');
  }
  create(data: TenantCreateInput) {
    return this.http.post<ManagedTenant & { temporary_password: string | null; admin_error: string | null }>('/api/superadmin/tenants', data);
  }
  update(id: string, data: TenantUpdateInput) {
    return this.http.put<ManagedTenant>(`/api/superadmin/tenants/${id}`, data);
  }
  migrate(id: string) {
    return this.http.post<ManagedTenant>(`/api/superadmin/tenants/${id}/migrate`, {});
  }
  createAdmin(id: string, email: string, name: string) {
    return this.http.post<{ email: string; temporary_password: string }>(`/api/superadmin/tenants/${id}/admin`, { email, name });
  }
  admins(id: string) {
    return this.http.get<TenantAdminUser[]>(`/api/superadmin/tenants/${id}/admins`);
  }
  /** Aufbewahrtes vorläufiges Passwort anzeigen (wird protokolliert). */
  showAdminPassword(id: string, userId: string) {
    return this.http.post<{ email: string; temporary_password: string }>(`/api/superadmin/tenants/${id}/admins/${userId}/password`, {});
  }
  resetAdminPassword(id: string, userId: string) {
    return this.http.post<{ email: string; temporary_password: string }>(`/api/superadmin/tenants/${id}/admins/${userId}/password/reset`, {});
  }
}
