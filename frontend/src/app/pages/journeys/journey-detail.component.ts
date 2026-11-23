import { HttpErrorResponse } from '@angular/common/http';
import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
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
 * Übersicht einer Reise: Strecken mit Fahrzeugen, alle Belege mit Betrag und Stand, und der nächste Schritt. Nur lesen:
 * Bearbeitet wird im Beleg (Strecken im Entwurf des Angebots) und in der Streckenplanung (Touren).
 */
@Component({
  selector: 'app-journey-detail',
  imports: [DatePipe, DecimalPipe, RouterLink, HelpLinkComponent],
  templateUrl: './journey-detail.component.html',
  host: { class: 'form-page' },
})
export class JourneyDetailComponent implements OnInit {
  private api = inject(JourneyService);
  private route = inject(ActivatedRoute);

  journey = signal<JourneyDetail | null>(null);
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
