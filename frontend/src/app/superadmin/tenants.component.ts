import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnInit, computed, inject, signal, viewChild } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { ConfirmService } from '../core/confirm.service';
import { httpErrorText } from '../core/http-error';
import { HelpLinkComponent } from '../help/help-link.component';
import { HighlightComponent } from '../shared/highlight.component';
import { StickyFilterBarDirective } from '../shared/sticky-filter-bar.directive';
import { ManagedTenant, TenantAdminService, TenantAdminUser } from './tenant-admin.service';
import { TenantSortCol, TenantStatusFilter, filterTenants, sortTenants } from './tenants-view';

interface Draft {
  /** null = neue Firma */
  id: string | null;
  name: string;
  subdomain: string;
  db_host: string;
  db_port: number;
  db_database: string;
  db_username: string;
  db_password: string;
  admin_name: string;
  admin_email: string;
  is_active: boolean;
}

interface AdminDraft {
  tenant: ManagedTenant;
  name: string;
  email: string;
}

/**
 * Firmenverwaltung (nur Betreiber): Liste, Anlegen, Bearbeiten, Sperren, Datenbank aktualisieren, Administrator nachlegen.
 * Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md), clientseitige Variante; die Logik steht in tenants-view.ts.
 * Firmen werden nie gelöscht, nur gesperrt.
 */
@Component({
  selector: 'app-tenants',
  imports: [FormsModule, DatePipe, HelpLinkComponent, HighlightComponent, StickyFilterBarDirective],
  templateUrl: './tenants.component.html',
  host: { class: 'list-page', style: '--sheet-w: 1060px' },
})
export class TenantsComponent implements OnInit {
  private api = inject(TenantAdminService);
  private confirm = inject(ConfirmService);
  private content = viewChild<ElementRef<HTMLElement>>('content');

  tenants = signal<ManagedTenant[]>([]);
  loading = signal(true);

  q = signal('');
  statusFilter = signal<TenantStatusFilter>('');
  sortCol = signal<TenantSortCol>('name');
  sortDir = signal<'asc' | 'desc'>('asc');

  displayRows = computed(() => sortTenants(filterTenants(this.tenants(), this.q(), this.statusFilter()), this.sortCol(), this.sortDir()));
  filtered = computed(() => this.q().trim() !== '' || this.statusFilter() !== '');
  currentId = signal<string | null>(null);

  draft = signal<Draft | null>(null);
  adminDraft = signal<AdminDraft | null>(null);
  adminList = signal<{ tenant: ManagedTenant; admins: TenantAdminUser[]; loading: boolean } | null>(null);
  formSubmitted = signal(false);
  /** Läuft gerade eine längere Aktion (Anlegen baut eine Datenbank auf)? */
  busy = signal(false);
  /** Zugang des ersten Administrators, wird nur einmal angezeigt */
  secret = signal<{ title: string; email: string; password: string; host: string } | null>(null);
  copied = signal(false);
  message = signal<{ ok: boolean; text: string } | null>(null);
  fieldErrors = signal<Record<string, string>>({});

  /** Basis-Adresse der Firmen: dieselbe wie die Betreiber-Adresse ohne das "admin." davor. */
  baseDomain = location.hostname.split('.').slice(1).join('.');

  ngOnInit() {
    this.load();
  }

  refresh() {
    this.message.set(null);
    this.loading.set(true);
    this.load();
  }

  private load() {
    this.api.list().subscribe({
      next: (t) => {
        this.tenants.set(t);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.loading.set(false);
        this.fail(e, 'Die Firmen konnten nicht geladen werden.');
      },
    });
  }

  // ---------- Sortieren ----------

  sort(col: TenantSortCol) {
    if (this.sortCol() === col) {
      this.sortDir.update((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      this.sortCol.set(col);
      this.sortDir.set('asc');
    }
  }

  sortIcon(col: TenantSortCol): string {
    if (this.sortCol() !== col) return 'bi-chevron-expand';
    return this.sortDir() === 'asc' ? 'bi-chevron-up' : 'bi-chevron-down';
  }

  ariaSort(col: TenantSortCol): 'ascending' | 'descending' | 'none' {
    if (this.sortCol() !== col) return 'none';
    return this.sortDir() === 'asc' ? 'ascending' : 'descending';
  }

  // ---------- Formular ----------

  newTenant() {
    this.reset();
    this.draft.set({
      id: null, name: '', subdomain: '', db_host: '127.0.0.1', db_port: 3306, db_database: '', db_username: '',
      db_password: '', admin_name: '', admin_email: '', is_active: true,
    });
    this.scrollToTop();
  }

  edit(t: ManagedTenant) {
    this.reset();
    this.draft.set({
      id: t.id, name: t.name, subdomain: t.subdomain, db_host: t.db_host, db_port: t.db_port, db_database: t.db_database,
      db_username: t.db_username, db_password: '', admin_name: '', admin_email: '', is_active: t.is_active,
    });
    this.scrollToTop();
  }

  cancel() {
    this.draft.set(null);
    this.formSubmitted.set(false);
    this.fieldErrors.set({});
  }

  save(f: NgForm) {
    const d = this.draft();
    if (!d || this.busy()) return;
    this.formSubmitted.set(true);
    if (f.invalid) return;

    this.busy.set(true);
    this.message.set(null);
    this.fieldErrors.set({});

    if (d.id === null) {
      this.api
        .create({
          name: d.name.trim(), subdomain: d.subdomain.trim().toLowerCase(), db_host: d.db_host.trim(), db_port: d.db_port,
          db_database: d.db_database.trim(), db_username: d.db_username.trim(), db_password: d.db_password,
          admin_email: d.admin_email.trim(), admin_name: d.admin_name.trim(),
        })
        .subscribe({
          next: (t) => {
            this.busy.set(false);
            this.draft.set(null);
            this.formSubmitted.set(false);
            if (t.temporary_password) {
              this.showSecret(`Erster Administrator von ${t.name}`, d.admin_email.trim().toLowerCase(), t.temporary_password, t.host);
            } else {
              this.message.set({ ok: false, text: `Die Firma „${t.name}“ ist angelegt, aber der Administrator nicht: ${t.admin_error ?? 'unbekannter Fehler'}. Legen Sie ihn mit dem Symbol „Administrator anlegen“ nach.` });
              this.scrollToTop();
            }
            this.load();
          },
          error: (e) => this.failBusy(e),
        });
    } else {
      this.api
        .update(d.id, {
          name: d.name.trim(), is_active: d.is_active, db_host: d.db_host.trim(), db_port: d.db_port,
          db_username: d.db_username.trim(), db_password: d.db_password,
        })
        .subscribe({
          next: () => {
            this.busy.set(false);
            this.draft.set(null);
            this.formSubmitted.set(false);
            this.ok('Änderungen gespeichert.');
            this.load();
          },
          error: (e) => this.failBusy(e),
        });
    }
  }

  // ---------- Aktionen ----------

  async toggleActive(t: ManagedTenant) {
    if (t.is_active) {
      const ok = await this.confirm.ask({
        title: 'Firma sperren',
        message: `${t.name} (${t.host}) ist danach nicht mehr erreichbar: Niemand kann sich anmelden, auch nicht mit bereits angemeldeten Geräten.\nDie Daten bleiben unverändert erhalten. Sie können die Firma später wieder entsperren.`,
        confirmLabel: 'Sperren',
        danger: true,
      });
      if (!ok) return;
    }
    this.api
      .update(t.id, {
        name: t.name, is_active: !t.is_active, db_host: t.db_host, db_port: t.db_port, db_username: t.db_username, db_password: '',
      })
      .subscribe({
        next: () => {
          this.ok(t.is_active ? `${t.name} wurde gesperrt.` : `${t.name} wurde entsperrt.`);
          this.load();
        },
        error: (e) => this.fail(e),
      });
  }

  async migrate(t: ManagedTenant) {
    const ok = await this.confirm.ask({
      title: 'Datenbank aktualisieren',
      message: `Die Datenbank von ${t.name} wird auf den neuesten Stand gebracht (neue Tabellen und Felder). Vorhandene Daten bleiben erhalten.\nDas ist nach jedem Update der Software nötig.`,
      confirmLabel: 'Aktualisieren',
    });
    if (!ok) return;
    this.message.set(null);
    this.busy.set(true);
    this.api.migrate(t.id).subscribe({
      next: () => {
        this.busy.set(false);
        this.ok(`Die Datenbank von ${t.name} ist aktuell.`);
        this.load();
      },
      error: (e) => this.failBusy(e),
    });
  }

  newAdmin(t: ManagedTenant) {
    this.reset();
    this.adminDraft.set({ tenant: t, name: '', email: '' });
    this.scrollToTop();
  }

  cancelAdmin() {
    this.adminDraft.set(null);
    this.formSubmitted.set(false);
    this.fieldErrors.set({});
  }

  saveAdmin(f: NgForm) {
    const d = this.adminDraft();
    if (!d || this.busy()) return;
    this.formSubmitted.set(true);
    if (f.invalid) return;

    this.busy.set(true);
    this.api.createAdmin(d.tenant.id, d.email.trim(), d.name.trim()).subscribe({
      next: (r) => {
        this.busy.set(false);
        this.adminDraft.set(null);
        this.formSubmitted.set(false);
        this.showSecret(`Neuer Administrator von ${d.tenant.name}`, r.email, r.temporary_password, d.tenant.host);
      },
      error: (e) => this.failBusy(e),
    });
  }

  // ---------- Administratoren und ihre vorläufigen Passwörter ----------

  /** Öffnet die Administratoren-Liste einer Firma. */
  openAdmins(t: ManagedTenant) {
    this.reset();
    this.adminList.set({ tenant: t, admins: [], loading: true });
    this.scrollToTop();
    this.api.admins(t.id).subscribe({
      next: (admins) => this.adminList.set({ tenant: t, admins, loading: false }),
      error: (e) => {
        this.adminList.set(null);
        this.fail(e, 'Die Administratoren konnten nicht geladen werden.');
      },
    });
  }

  closeAdmins() {
    this.adminList.set(null);
  }

  showPassword(a: TenantAdminUser) {
    const l = this.adminList();
    if (!l || this.busy()) return;
    this.busy.set(true);
    this.api.showAdminPassword(l.tenant.id, a.id).subscribe({
      next: (r) => {
        this.busy.set(false);
        this.showSecret(`Vorläufiges Passwort von ${a.name}`, r.email, r.temporary_password, l.tenant.host);
      },
      error: (e) => this.failBusy(e),
    });
  }

  async resetPassword(a: TenantAdminUser) {
    const l = this.adminList();
    if (!l || this.busy()) return;
    const ok = await this.confirm.ask({
      title: 'Neues vorläufiges Passwort',
      message: `${a.name} (${a.email}) bekommt ein neues vorläufiges Passwort. Das bisherige Passwort und alle bestehenden Anmeldungen werden sofort ungültig, beim nächsten Anmelden muss ${a.name} ein eigenes Passwort vergeben.`,
      confirmLabel: 'Neu vergeben',
      danger: true,
    });
    if (!ok) return;
    this.busy.set(true);
    this.api.resetAdminPassword(l.tenant.id, a.id).subscribe({
      next: (r) => {
        this.busy.set(false);
        this.showSecret(`Neues vorläufiges Passwort für ${a.name}`, r.email, r.temporary_password, l.tenant.host);
        this.api.admins(l.tenant.id).subscribe((admins) => this.adminList.set({ tenant: l.tenant, admins, loading: false }));
      },
      error: (e) => this.failBusy(e),
    });
  }

  // ---------- vorläufiges Passwort ----------

  private showSecret(title: string, email: string, password: string, host: string) {
    this.message.set(null);
    this.copied.set(false);
    this.secret.set({ title, email, password, host });
    this.scrollToTop();
  }

  async copy() {
    const pw = this.secret()?.password;
    if (!pw) return;
    try {
      await navigator.clipboard.writeText(pw);
      this.copied.set(true);
    } catch {
      this.copied.set(false);
    }
  }

  closeSecret() {
    this.secret.set(null);
  }

  // ---------- Meldungen ----------

  private scrollToTop() {
    this.content()?.nativeElement.scrollTo({ top: 0, behavior: 'smooth' });
  }

  private reset() {
    this.formSubmitted.set(false);
    this.message.set(null);
    this.fieldErrors.set({});
    this.secret.set(null);
    this.draft.set(null);
    this.adminDraft.set(null);
    this.adminList.set(null);
  }

  private ok(text: string) {
    this.fieldErrors.set({});
    this.message.set({ ok: true, text });
    this.scrollToTop();
  }

  private failBusy(err: HttpErrorResponse) {
    this.busy.set(false);
    this.fail(err);
  }

  private fail(err: HttpErrorResponse | null, fallback = 'Das hat nicht geklappt.') {
    const errors: Record<string, string[]> = err?.error?.errors ?? {};
    this.fieldErrors.set(Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]])));
    this.message.set({ ok: false, text: httpErrorText(err, fallback) });
    this.scrollToTop();
  }
}
