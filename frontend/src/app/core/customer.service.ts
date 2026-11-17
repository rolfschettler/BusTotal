import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

export interface Contact {
  id?: string;
  salutation: string | null;
  first_name: string | null;
  last_name: string;
  position: string | null;
  email: string | null;
  phone: string | null;
  mobile: string | null;
  is_primary: boolean;
  notes: string | null;
}

export interface Address {
  id?: string;
  type: 'abholung' | 'lieferung' | 'sonstige';
  label: string | null;
  street: string;
  postal_code: string;
  city: string;
  country: string;
  notes: string | null;
}

export interface Customer {
  id: string;
  customer_number: string;
  debitor_number: number | null;
  display_name: string;
  type: string;
  company_name: string | null;
  salutation: string | null;
  first_name: string | null;
  last_name: string | null;
  email: string | null;
  phone: string | null;
  mobile: string | null;
  website: string | null;
  vat_id: string | null;
  street: string | null;
  postal_code: string | null;
  city: string | null;
  country: string;
  payment_term_days: number | null;
  notes: string | null;
  /** true bei Demo-Datensätzen (Schulung, Messe, Tests) */
  is_demo: boolean;
  deleted_at: string | null;
  contacts?: Contact[];
  addresses?: Address[];
}

export type CustomerInput = Omit<Customer, 'id' | 'customer_number' | 'debitor_number' | 'display_name' | 'is_demo' | 'deleted_at' | 'contacts' | 'addresses'>;

export interface CustomerType {
  value: string;
  label: string;
  organisation: boolean;
}

export interface Page<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

export interface DuplicateHit {
  customer: Customer;
  reason: string;
}

@Injectable({ providedIn: 'root' })
export class CustomerService {
  private http = inject(HttpClient);
  private base = '/api/customers';

  list(opts: { q: string; type: string; archived: boolean; page: number; sort: string; dir: 'asc' | 'desc' }) {
    let params = new HttpParams().set('page', opts.page).set('sort', opts.sort).set('dir', opts.dir);
    if (opts.q) params = params.set('q', opts.q);
    if (opts.type) params = params.set('type', opts.type);
    if (opts.archived) params = params.set('archived', 1);
    return this.http.get<Page<Customer>>(this.base, { params });
  }

  types() {
    return this.http.get<CustomerType[]>(`${this.base}/types`);
  }
  get(id: string) {
    return this.http.get<Customer>(`${this.base}/${id}`);
  }
  create(data: CustomerInput) {
    return this.http.post<Customer>(this.base, data);
  }
  update(id: string, data: CustomerInput) {
    return this.http.put<Customer>(`${this.base}/${id}`, data);
  }
  archive(id: string) {
    return this.http.delete(`${this.base}/${id}`);
  }
  restore(id: string) {
    return this.http.post<Customer>(`${this.base}/${id}/restore`, {});
  }
  anonymize(id: string) {
    return this.http.post(`${this.base}/${id}/anonymize`, {});
  }
  export(id: string) {
    return this.http.get(`${this.base}/${id}/export`, { responseType: 'blob' });
  }

  duplicates(p: Record<string, string | number | null | undefined>) {
    let params = new HttpParams();
    for (const [k, v] of Object.entries(p)) if (v !== null && v !== undefined && v !== '') params = params.set(k, v);
    return this.http.get<DuplicateHit[]>(`${this.base}/duplicates`, { params });
  }

  saveContact(customerId: string, c: Contact) {
    return c.id
      ? this.http.put<Contact>(`${this.base}/${customerId}/contacts/${c.id}`, c)
      : this.http.post<Contact>(`${this.base}/${customerId}/contacts`, c);
  }
  deleteContact(customerId: string, id: string) {
    return this.http.delete(`${this.base}/${customerId}/contacts/${id}`);
  }
  saveAddress(customerId: string, a: Address) {
    return a.id
      ? this.http.put<Address>(`${this.base}/${customerId}/addresses/${a.id}`, a)
      : this.http.post<Address>(`${this.base}/${customerId}/addresses`, a);
  }
  deleteAddress(customerId: string, id: string) {
    return this.http.delete(`${this.base}/${customerId}/addresses/${id}`);
  }
}
