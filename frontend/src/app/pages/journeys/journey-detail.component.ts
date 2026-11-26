import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, ElementRef, OnInit, computed, effect, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { AuthService } from '../../core/auth.service';
import { ConfirmService } from '../../core/confirm.service';
import { httpErrorText } from '../../core/http-error';
import { JourneyDetail, JourneyDetailSection, JourneyService, SectionState } from '../../core/journey.service';
import { statusBadge } from '../../core/trip-order.service';
import { HelpLinkComponent } from '../../help/help-link.component';
import { CustomerPickerComponent, PickedCustomer } from '../../shared/customer-picker.component';
import { formatDuration } from '../route-planning/route-planning-view';
import { formatAmount } from '../documents/document-calc';
import { JourneyCancelModalComponent } from './journey-cancel-modal.component';
import { STAGE_LABELS } from './journey-list.component';

/** Bezeichnungen der Belegarten für die Kette. */
const TYPE_LABELS: Record<string, string> = {
  angebot: 'Angebot',
  auftragsbestaetigung: 'Auftragsbestätigung',
  anzahlung: 'Anzahlungsrechnung',
  rechnung: 'Rechnung',
  gutschrift: 'Stornorechnung',
};

const STATE_LABELS: Record<SectionState, string> = { neu: 'neu', geaendert: 'geändert', aktuell: 'aktuell' };

/**
 * Die Reise: Hier plant man sie Schritt für Schritt. Name und Kunde ändern, Strecken hinzufügen, ändern und entfernen
 * (Hinfahrt, Fahrten vor Ort, Rückfahrt) und aus ihnen das Angebot erstellen. Ändert man später eine Strecke, gleicht
 * „Angebot aktualisieren“ die Positionen dieser Strecke im Entwurf ab. Dazu die Belege und der nächste Schritt.
 */
@Component({
  selector: 'app-journey-detail',
  imports: [DatePipe, DecimalPipe, FormsModule, RouterLink, HelpLinkComponent, CustomerPickerComponent],
  templateUrl: './journey-detail.component.html',
  host: { class: 'form-page' },
})
export class JourneyDetailComponent implements OnInit {
  private api = inject(JourneyService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);
  private auth = inject(AuthService);
  private confirm = inject(ConfirmService);
  private modals = inject(NgbModal);

  journey = signal<JourneyDetail | null>(null);
  loading = signal(true);
  error = signal<string | null>(null);
  /** Meldung nach einer Aktion (Erfolg oder Fehler) */
  message = signal<{ ok: boolean; text: string } | null>(
    // Rückkehr aus der Streckenplanung nach „Speichern“
    history.state?.sectionSaved ? { ok: true, text: 'Strecke gespeichert.' } : null,
  );

  /** Planen (Strecken, Kunde, Angebot) dürfen Admin und Disponent; Buchhaltung sieht nur. */
  canPlan = computed(() => ['admin', 'disponent'].includes(this.auth.user()?.role ?? ''));

  cancelled = computed(() => this.journey()?.state === 'abgesagt');
  /** Strecken und Angebot ändern: nur mit Berechtigung und solange die Reise nicht abgesagt ist. */
  canChange = computed(() => this.canPlan() && !this.cancelled());

  // ---- Umbenennen ----
  editing = signal(false);
  draftTitle = signal('');
  saving = signal(false);
  renameError = signal<string | null>(null);
  renamed = signal(false);
  private titleInput = viewChild<ElementRef<HTMLInputElement>>('titleInput');

  constructor() {
    // Das Feld entsteht erst nach dem Umschalten: sobald es da ist, bekommt es den Fokus und der Name ist markiert, damit man sofort lostippen kann.
    effect(() => {
      const el = this.titleInput()?.nativeElement;
      if (el && this.editing()) {
        el.focus();
        // ngModel schreibt den Wert erst einen Augenblick später ins Feld; erst danach hält die Markierung.
        setTimeout(() => el.select());
      }
    });
  }

  startRename() {
    this.draftTitle.set(this.journey()?.title ?? '');
    this.renameError.set(null);
    this.renamed.set(false);
    this.editing.set(true);
  }

  cancelRename() {
    this.editing.set(false);
    this.renameError.set(null);
  }

  saveRename() {
    const j = this.journey();
    const title = this.draftTitle().trim();
    if (!j || this.saving()) return;
    if (!title) {
      this.renameError.set('Bitte einen Namen für die Reise eingeben.');
      return;
    }
    if (title === j.title) {
      this.cancelRename();
      return;
    }
    this.saving.set(true);
    this.renameError.set(null);
    this.api.rename(j.id, title).subscribe({
      next: (d) => {
        this.journey.set(d);
        this.saving.set(false);
        this.editing.set(false);
        this.renamed.set(true);
      },
      error: (e: HttpErrorResponse) => {
        this.saving.set(false);
        this.renameError.set(httpErrorText(e, 'Der Name konnte nicht gespeichert werden.'));
      },
    });
  }

  // ---- Kunde ----
  customerEditing = signal(false);

  /** Kunde gewählt: sofort an der Reise speichern (er steht auf keinem festgeschriebenen Beleg, der hat seine Kopie). */
  pickCustomer(c: PickedCustomer) {
    const j = this.journey();
    if (!j) return;
    this.api.update(j.id, { title: j.title, customer_id: c.id }).subscribe({
      next: (d) => {
        this.journey.set(d);
        this.customerEditing.set(false);
        this.notify(true, 'Kunde gespeichert. Ein Angebot, das es schon gibt, behält seinen bisherigen Kunden.');
      },
      error: (e: HttpErrorResponse) => this.notify(false, httpErrorText(e, 'Der Kunde konnte nicht gespeichert werden.')),
    });
  }

  // ---- Strecken ----
  busy = signal(false);

  // ---- Absagen ----
  /** Datum, Grund und Folgen stehen in einem eigenen Fenster; es liefert die abgesagte Reise zurück. */
  openCancel() {
    const j = this.journey();
    if (!j || this.busy()) return;
    const ref = this.modals.open(JourneyCancelModalComponent, { centered: true, size: 'lg' });
    (ref.componentInstance as JourneyCancelModalComponent).journey = j;
    ref.result.then(
      (updated: JourneyDetail) => {
        this.journey.set(updated);
        this.notify(true, 'Reise abgesagt.');
      },
      () => undefined,
    );
  }

  async reactivate() {
    const j = this.journey();
    if (!j || this.busy()) return;
    const orders = j.trip_orders.cancelled ?? 0;
    const ok = await this.confirm.ask({
      title: 'Absage zurücknehmen',
      message: `Die Reise „${j.title}“ gilt wieder als laufend.` + (orders
        ? ` ${orders === 1 ? 'Der Fahrtauftrag gilt' : `Die ${orders} Fahrtaufträge gelten`} wieder wie vor der Absage. Bitte informieren Sie die Fahrer erneut.`
        : ''),
      confirmLabel: 'Absage zurücknehmen',
    });
    if (!ok) return;
    this.busy.set(true);
    this.api.reactivate(j.id).subscribe({
      next: (d) => {
        this.busy.set(false);
        this.journey.set(d);
        this.notify(true, 'Absage zurückgenommen.');
      },
      error: (e: HttpErrorResponse) => {
        this.busy.set(false);
        this.notify(false, httpErrorText(e, 'Die Absage konnte nicht zurückgenommen werden.'));
      },
    });
  }

  stateOf(sectionId: string): SectionState | null {
    return this.journey()?.quote.sections.find((s) => s.id === sectionId)?.state ?? null;
  }
  readonly stateLabels = STATE_LABELS;

  /** Löschen geht, solange es nur Entwürfe gibt; festgeschriebene Belege bleiben erhalten. */
  canDelete = computed(() => (this.journey()?.documents ?? []).every((d) => d.status === 'entwurf'));

  async deleteJourney() {
    const j = this.journey();
    if (!j || this.busy() || !this.canDelete()) return;
    const drafts = j.documents.length;
    const parts = [
      `${j.sections.length} ${j.sections.length === 1 ? 'Strecke' : 'Strecken'}`,
      ...(drafts ? [`${drafts === 1 ? 'der Beleg' : `die ${drafts} Belege`} (${j.documents.map((d) => this.typeLabel(d.type)).join(', ')}), noch nicht festgeschrieben`] : []),
    ];
    const ok = await this.confirm.ask({
      title: 'Reise löschen',
      message: `Die Reise „${j.title}“ endgültig löschen? Dabei werden auch ${parts.join(' und ')} gelöscht. Das lässt sich nicht rückgängig machen.`,
      confirmLabel: 'Reise löschen',
      danger: true,
    });
    if (!ok) return;
    this.busy.set(true);
    this.api.remove(j.id).subscribe({
      next: () => void this.router.navigate(['/reisen']),
      error: (e: HttpErrorResponse) => {
        this.busy.set(false);
        this.notify(false, httpErrorText(e, 'Die Reise konnte nicht gelöscht werden.'));
      },
    });
  }

  async removeSection(s: JourneyDetailSection) {
    const j = this.journey();
    if (!j || this.busy()) return;
    const inDraft = this.stateOf(s.id) !== 'neu' && !!j.quote.draft_id;
    const ok = await this.confirm.ask({
      title: 'Strecke entfernen',
      message: inDraft
        ? `Die Strecke „${s.title}“ aus der Reise und aus dem Angebot entfernen?`
        : `Die Strecke „${s.title}“ aus der Reise entfernen?`,
      confirmLabel: 'Entfernen',
      danger: true,
    });
    if (!ok) return;
    this.busy.set(true);
    this.api.removeSection(j.id, s.id).subscribe({
      next: (d) => {
        this.busy.set(false);
        this.journey.set(d);
        this.notify(true, 'Strecke entfernt.');
      },
      error: (e: HttpErrorResponse) => {
        this.busy.set(false);
        this.notify(false, httpErrorText(e, 'Die Strecke konnte nicht entfernt werden.'));
      },
    });
  }

  // ---- Angebot ----
  /** Titel der Strecken, die sich seit der Berechnung geändert haben (ihre Positionen werden beim Abgleich neu berechnet). */
  changedTitles = computed(() => {
    const j = this.journey();
    if (!j) return [];
    return j.sections.filter((s) => this.stateOf(s.id) === 'geaendert').map((s) => s.title);
  });

  /** „Angebot erstellen“ oder „Angebot aktualisieren“; bei geänderten Strecken erst nachfragen (Handänderungen daran gehen verloren). */
  async quote() {
    const j = this.journey();
    if (!j || this.busy()) return;
    const changed = this.changedTitles();
    if (j.quote.draft_id && changed.length) {
      const ok = await this.confirm.ask({
        title: 'Angebot aktualisieren',
        message: `Im Angebot ${changed.length === 1 ? 'wird die Strecke' : 'werden die Strecken'} ${changed.map((t) => `„${t}“`).join(', ')} neu gerechnet. Preise oder Texte, die Sie dort von Hand geändert haben, gehen dabei verloren. Alle anderen Strecken im Angebot bleiben, wie sie sind. Fortfahren?`,
        confirmLabel: 'Aktualisieren',
      });
      if (!ok) return;
    }
    this.busy.set(true);
    this.api.quote(j.id).subscribe({
      next: (r) => {
        this.busy.set(false);
        this.router.navigate(['/belege', r.id]);
      },
      error: (e: HttpErrorResponse) => {
        this.busy.set(false);
        this.notify(false, httpErrorText(e, 'Das Angebot konnte nicht erstellt werden.'));
      },
    });
  }

  readonly amount = formatAmount;
  readonly duration = formatDuration;
  readonly stageLabels = STAGE_LABELS;
  readonly badge = statusBadge;
  typeLabel = (t: string) => TYPE_LABELS[t] ?? t;

  /** Die Fahrtaufträge der Reise im Überblick (entstehen mit der Auftragsbestätigung). */
  orders = computed(() => this.journey()?.trip_orders ?? null);

  /** Der Beleg, bei dem es weitergeht: der zuletzt angelegte, der nicht storniert ist (sonst der letzte). */
  latest = computed(() => {
    const docs = this.journey()?.documents ?? [];
    return [...docs].reverse().find((d) => !d.cancelled && d.type !== 'gutschrift') ?? docs[docs.length - 1] ?? null;
  });

  /**
   * Der Auftrag, sobald er verbindlich ist: die gültige festgeschriebene Auftragsbestätigung, solange danach nur Anzahlungen kamen.
   * Dann führt der nächste Schritt zu den Fahrtaufträgen und zur Auftragsbestätigung, dort entsteht auch die Anzahlung.
   */
  confirmation = computed(() => {
    const d = this.latest();
    if (!d || d.status === 'entwurf' || !['auftragsbestaetigung', 'anzahlung'].includes(d.type)) return null;
    return this.journey()?.documents.find((x) => x.type === 'auftragsbestaetigung' && x.status === 'festgeschrieben' && !x.cancelled) ?? null;
  });
  /** Gibt es schon eine gültige Anzahlungsrechnung (auch als Entwurf)? */
  hasAdvance = computed(() => !!this.journey()?.documents.some((x) => x.type === 'anzahlung' && !x.cancelled));

  /** Es wird noch geplant (kein Beleg oder nur ein Entwurf): Dann gehört „Strecke hinzufügen“ zum nächsten Schritt. */
  planning = computed(() => this.canChange() && (this.latest()?.status ?? 'entwurf') === 'entwurf');

  /** Ist „Strecke hinzufügen“ der naheliegende Schritt (Haupttaste)? Ja, solange es keinen Beleg und keine Rückfahrt gibt. */
  addFirst = computed(() => !this.latest() && !this.journey()?.sections.some((s) => s.kind === 'rueckfahrt'));

  /** Was als Nächstes zu tun ist, in einem Satz. */
  nextStep = computed(() => {
    const j = this.journey();
    const d = this.latest();
    if (j?.state === 'abgeschlossen') return 'Nichts mehr zu tun: Die Reise ist vorbei und die Rechnung bezahlt.';
    if (!d) {
      if (!j?.sections.length) return 'Fügen Sie die erste Strecke hinzu: Hinfahrt, Fahrten vor Ort, Rückfahrt.';
      if (this.addFirst()) return 'Fügen Sie die nächste Strecke hinzu, zum Beispiel die Rückfahrt. Oder erstellen Sie gleich das Angebot.';
      return 'Erstellen Sie das Angebot. Fehlt noch eine Strecke, fügen Sie sie vorher hinzu.';
    }
    if (d.status === 'entwurf') {
      const pending = j?.quote.pending ?? 0;
      if (pending > 0) return 'Sie haben Strecken geändert oder neu hinzugefügt. Das Angebot zeigt das noch nicht. Klicken Sie auf „Angebot aktualisieren“.';
      return `${d.type === 'angebot' ? 'Das Angebot' : `Die ${this.typeLabel(d.type)}`} prüfen, Kunden wählen und festschreiben.`;
    }
    if (d.cancelled) return 'Dieser Beleg ist storniert. Erstellen Sie bei Bedarf einen neuen.';
    switch (d.type) {
      case 'angebot': return 'Auftragsbestätigung erstellen, sobald der Kunde zusagt.';
      case 'auftragsbestaetigung': {
        const o = this.orders();
        if (o && o.to_plan > 0) return `Teilen Sie Bus und Fahrer für ${o.to_plan === 1 ? 'den Fahrtauftrag' : `die ${o.to_plan} Fahrtaufträge`} ein.`;
        return 'Die Fahrt ist eingeteilt.';
      }
      case 'anzahlung': return 'Erfassen Sie den Zahlungseingang in der Anzahlungsrechnung.';
      case 'rechnung': return 'Zahlungseingang erfassen.';
      default: return '';
    }
  });

  private notify(ok: boolean, text: string) {
    this.message.set({ ok, text });
  }

  ngOnInit() {
    const id = this.route.snapshot.paramMap.get('id')!;
    this.api.get(id).subscribe({
      next: (j) => {
        this.journey.set(j);
        this.loading.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(httpErrorText(e, 'Die Reise konnte nicht geladen werden.'));
        this.loading.set(false);
      },
    });
  }
}
