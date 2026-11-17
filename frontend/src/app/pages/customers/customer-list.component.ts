import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth.service';
import { Customer, CustomerService, CustomerType } from '../../core/customer.service';

@Component({
  selector: 'app-customer-list',
  imports: [FormsModule, RouterLink],
  templateUrl: './customer-list.component.html',
  styleUrl: './customer-list.component.scss',
})
export class CustomerListComponent implements OnInit {
  private api = inject(CustomerService);
  auth = inject(AuthService);

  q = '';
  type = '';
  archived = false;
  page = signal(1);
  lastPage = signal(1);
  total = signal(0);
  rows = signal<Customer[]>([]);
  types = signal<CustomerType[]>([]);
  loading = signal(false);
  error = signal<string | null>(null);

  private timer?: ReturnType<typeof setTimeout>;

  ngOnInit() {
    this.api.types().subscribe((t) => this.types.set(t));
    this.load();
  }

  /** Erst 300 ms nach der letzten Eingabe suchen, nicht bei jedem Buchstaben. */
  onSearch() {
    clearTimeout(this.timer);
    this.timer = setTimeout(() => this.filterChanged(), 300);
  }

  filterChanged() {
    this.page.set(1);
    this.load();
  }

  go(p: number) {
    this.page.set(p);
    this.load();
  }

  typeLabel(value: string) {
    return this.types().find((t) => t.value === value)?.label ?? value;
  }

  private load() {
    this.loading.set(true);
    this.error.set(null);
    this.api.list({ q: this.q.trim(), type: this.type, archived: this.archived, page: this.page() }).subscribe({
      next: (p) => {
        this.rows.set(p.data);
        this.lastPage.set(p.last_page);
        this.total.set(p.total);
        this.loading.set(false);
      },
      error: () => {
        this.error.set('Kunden konnten nicht geladen werden.');
        this.loading.set(false);
      },
    });
  }
}
