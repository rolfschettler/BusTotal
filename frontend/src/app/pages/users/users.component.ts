import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnInit, computed, inject, signal, viewChild } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { AuthService, Role } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { useFormPage } from '../../core/form-page';
import { httpErrorText } from '../../core/http-error';
import { ROLE_OPTIONS, roleLabel } from '../../core/roles';
import { ManagedUser, UserAdminService } from '../../core/user-admin.service';
import { HelpLinkComponent } from '../../help/help-link.component';
import { AvatarComponent } from '../../shared/avatar.component';
import { HighlightComponent } from '../../shared/highlight.component';
import { ListSearchDirective, RowNavDirective } from '../../shared/row-nav.directive';
import { StatusFilter, UserSortCol, filterUsers, sortUsers } from './users-view';

interface Draft {
  id: string | null;
  name: string;
  email: string;
  role: Role;
}

/**
 * Benutzerverwaltung (nur Administrator): Liste, Anlegen, Bearbeiten, Sperren, Passwort zurücksetzen.
 * Tabellen-Muster (docs/ClaudeCodePatterns/TableViewPattern.md), clientseitige Variante: Die Liste ist klein,
 * deshalb filtern und sortieren wir im Browser (Logik in users-view.ts, mit Testfällen).
 */
@Component({
  selector: 'app-users',
  imports: [FormsModule, DatePipe, HelpLinkComponent, HighlightComponent, RowNavDirective, ListSearchDirective, AvatarComponent],
  templateUrl: './users.component.html',
  host: { class: 'list-page' },
})
export class UsersComponent implements OnInit {
  private api = inject(UserAdminService);
  private confirm = inject(ConfirmService);
  private auth = inject(AuthService);
  private content = viewChild<ElementRef<HTMLElement>>('content');

  users = signal<ManagedUser[]>([]);
  loading = signal(true);

  // Filter und Sortierung
  q = signal('');
  roleFilter = signal('');
  statusFilter = signal<StatusFilter>('');
  sortCol = signal<UserSortCol>('name');
  sortDir = signal<'asc' | 'desc'>('asc');

  displayRows = computed(() =>
    sortUsers(filterUsers(this.users(), this.q(), this.roleFilter(), this.statusFilter()), this.sortCol(), this.sortDir()),
  );
  filtered = computed(() => this.q().trim() !== '' || this.roleFilter() !== '' || this.statusFilter() !== '');

  /** Die in der Liste markierte Zeile (nur optisch) */
  currentId = signal<string | null>(null);

  draft = signal<Draft | null>(null);
  /** Nach dem ersten Speichern-Versuch markiert das Formular fehlerhafte Felder (Formular-Muster). */
  draftSubmitted = signal(false);
  /** Vorläufiges Passwort, das einmalig angezeigt wird */
  secret = signal<{ name: string; password: string } | null>(null);
  copied = signal(false);
  message = signal<{ ok: boolean; text: string } | null>(null);
  fieldErrors = signal<Record<string, string>>({});

  roles = ROLE_OPTIONS;
  roleLabel = roleLabel;
  me = this.auth.user;

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
      next: (u) => {
        this.users.set(u);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.loading.set(false);
        this.fail(e, 'Die Benutzer konnten nicht geladen werden.');
      },
    });
  }

  isMe(u: ManagedUser) {
    return u.id === this.me()?.id;
  }

  // ---------- Sortieren ----------

  /** Klick auf einen Spaltenkopf: neue Spalte aufsteigend, dieselbe Spalte wechselt die Richtung. */
  sort(col: UserSortCol) {
    if (this.sortCol() === col) {
      this.sortDir.update((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      this.sortCol.set(col);
      this.sortDir.set('asc');
    }
  }

  sortIcon(col: UserSortCol): string {
    if (this.sortCol() !== col) return 'bi-chevron-expand';
    return this.sortDir() === 'asc' ? 'bi-chevron-up' : 'bi-chevron-down';
  }

  ariaSort(col: UserSortCol): 'ascending' | 'descending' | 'none' {
    if (this.sortCol() !== col) return 'none';
    return this.sortDir() === 'asc' ? 'ascending' : 'descending';
  }

  // ---------- Formular ----------

  newUser() {
    this.reset();
    this.openDraft({ id: null, name: '', email: '', role: 'disponent' });
    this.scrollToTop();
  }

  /** Klick oder Enter auf eine Zeile. Ein offenes Formular wird nicht ungefragt ersetzt. */
  editById(id: string) {
    const user = this.users().find((u) => u.id === id);
    if (user && !this.hasUnsaved()) this.edit(user);
  }

  /** Strg+S speichert das Formular; beim Verlassen wird nach ungespeicherten Eingaben gefragt. */
  canLeave = useFormPage({
    unsaved: () => this.hasUnsaved(),
    save: () => document.querySelector<HTMLFormElement>('#userForm')?.requestSubmit(),
  });

  private hasUnsaved(): boolean {
    const d = this.draft();
    return !!d && JSON.stringify(d) !== this.draftBefore;
  }
  private draftBefore = '';

  edit(u: ManagedUser) {
    this.reset();
    this.openDraft({ id: u.id, name: u.name, email: u.email, role: u.role });
    this.scrollToTop();
  }

  private openDraft(d: Draft) {
    this.draftBefore = JSON.stringify(d);
    this.draft.set(d);
  }

  cancel() {
    this.draft.set(null);
    this.draftSubmitted.set(false);
    this.fieldErrors.set({});
  }

  save(f: NgForm) {
    const d = this.draft();
    if (!d) return;
    this.draftSubmitted.set(true);
    if (f.invalid) return;
    const body = { name: d.name.trim(), email: d.email.trim(), role: d.role };

    if (d.id === null) {
      this.api.create(body).subscribe({
        next: (u) => {
          this.draft.set(null);
          this.showSecret(u);
          this.load();
        },
        error: (e) => this.fail(e),
      });
    } else {
      const current = this.users().find((u) => u.id === d.id);
      this.api.update(d.id, { ...body, active: current?.active ?? true }).subscribe({
        next: () => {
          this.draft.set(null);
          this.ok('Änderungen gespeichert.');
          this.load();
        },
        error: (e) => this.fail(e),
      });
    }
  }

  // ---------- Aktionen ----------

  async toggleActive(u: ManagedUser) {
    if (u.active) {
      const ok = await this.confirm.ask({
        title: 'Benutzer sperren',
        message: `${u.name} wird sofort überall abgemeldet und kann sich nicht mehr anmelden.\nDie Daten und das Änderungsprotokoll bleiben erhalten. Sie können den Benutzer später wieder entsperren.`,
        confirmLabel: 'Sperren',
        danger: true,
      });
      if (!ok) return;
    }
    this.api.update(u.id, { name: u.name, email: u.email, role: u.role, active: !u.active }).subscribe({
      next: () => {
        this.ok(u.active ? `${u.name} wurde gesperrt.` : `${u.name} wurde entsperrt.`);
        this.load();
      },
      error: (e) => this.fail(e),
    });
  }

  async resetPassword(u: ManagedUser) {
    const ok = await this.confirm.ask({
      title: 'Passwort zurücksetzen',
      message: `Für ${u.name} wird ein neues vorläufiges Passwort erzeugt. Das bisherige Passwort wird ungültig, und ${u.name} wird überall abgemeldet.`,
      confirmLabel: 'Zurücksetzen',
      danger: true,
    });
    if (!ok) return;
    this.api.resetPassword(u.id).subscribe({
      next: (r) => {
        this.showSecret(r);
        this.load();
      },
      error: (e) => this.fail(e),
    });
  }

  // ---------- vorläufiges Passwort ----------

  private showSecret(u: ManagedUser) {
    this.message.set(null);
    this.copied.set(false);
    this.secret.set({ name: u.name, password: u.temporary_password ?? '' });
    this.scrollToTop();
  }

  async copy() {
    const pw = this.secret()?.password;
    if (!pw) return;
    try {
      await navigator.clipboard.writeText(pw);
      this.copied.set(true);
    } catch {
      this.copied.set(false); // Zwischenablage nicht erlaubt: das Passwort ist markierbar
    }
  }

  closeSecret() {
    this.secret.set(null);
  }

  // ---------- Meldungen ----------

  /** Formular und Hinweise stehen oben im scrollenden Bereich: Dorthin springen, damit man sie sieht. */
  private scrollToTop() {
    this.content()?.nativeElement.scrollTo({ top: 0, behavior: 'smooth' });
  }

  private reset() {
    this.draftSubmitted.set(false);
    this.message.set(null);
    this.fieldErrors.set({});
    this.secret.set(null);
  }

  private ok(text: string) {
    this.fieldErrors.set({});
    this.message.set({ ok: true, text });
  }

  private fail(err: HttpErrorResponse | null, fallback = 'Das hat nicht geklappt.') {
    const errors: Record<string, string[]> = err?.error?.errors ?? {};
    this.fieldErrors.set(Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]])));
    this.message.set({ ok: false, text: httpErrorText(err, fallback) });
  }
}
