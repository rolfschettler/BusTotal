import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, ElementRef, OnInit, computed, effect, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { httpErrorText } from '../../core/http-error';
import { JourneyDetail, JourneyService } from '../../core/journey.service';
import { HelpLinkComponent } from '../../help/help-link.component';
import { formatDuration } from '../route-planning/route-planning-view';
import { formatAmount } from '../documents/document-calc';
import { STAGE_LABELS } from './journey-list.component';

/** Bezeichnungen der Belegarten für die Kette. */
const TYPE_LABELS: Record<string, string> = {
  angebot: 'Angebot',
  auftragsbestaetigung: 'Auftragsbestätigung',
  anzahlung: 'Anzahlungsrechnung',
  rechnung: 'Rechnung',
  gutschrift: 'Stornorechnung',
};

/**
 * Übersicht einer Reise: Strecken mit Fahrzeugen, alle Belege mit Betrag und Stand, und der nächste Schritt. Nur der Name der
 * Reise ist hier änderbar (Stift am Titel). Alles andere bearbeitet man im Beleg (Kunde, Positionen, Strecken im Entwurf des
 * Angebots) und in der Streckenplanung (Touren).
 */
@Component({
  selector: 'app-journey-detail',
  imports: [DatePipe, DecimalPipe, FormsModule, RouterLink, HelpLinkComponent],
  templateUrl: './journey-detail.component.html',
  host: { class: 'form-page' },
})
export class JourneyDetailComponent implements OnInit {
  private api = inject(JourneyService);
  private route = inject(ActivatedRoute);

  journey = signal<JourneyDetail | null>(null);

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
  loading = signal(true);
  error = signal<string | null>(null);

  readonly amount = formatAmount;
  readonly duration = formatDuration;
  readonly stageLabels = STAGE_LABELS;
  typeLabel = (t: string) => TYPE_LABELS[t] ?? t;

  /** Der Beleg, bei dem es weitergeht: der zuletzt angelegte, der nicht storniert ist (sonst der letzte). */
  latest = computed(() => {
    const docs = this.journey()?.documents ?? [];
    return [...docs].reverse().find((d) => !d.cancelled && d.type !== 'gutschrift') ?? docs[docs.length - 1] ?? null;
  });

  /** Was als Nächstes zu tun ist, in einem Satz. */
  nextStep = computed(() => {
    const d = this.latest();
    if (!d) return 'Erstellen Sie in der Streckenplanung ein Angebot für diese Reise.';
    if (d.status === 'entwurf') return `Den ${this.typeLabel(d.type)} prüfen, Kunden wählen und festschreiben.`;
    if (d.cancelled) return 'Dieser Beleg ist storniert. Erstellen Sie bei Bedarf einen neuen.';
    switch (d.type) {
      case 'angebot': return 'Auftragsbestätigung erstellen, sobald der Kunde zusagt.';
      case 'auftragsbestaetigung': return 'Optional eine Anzahlung verlangen, nach der Fahrt die Rechnung erstellen.';
      case 'anzahlung': return 'Zahlungseingang erfassen, nach der Fahrt die Schlussrechnung erstellen.';
      case 'rechnung': return 'Zahlungseingang erfassen.';
      default: return '';
    }
  });

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
