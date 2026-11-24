import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { httpErrorText } from '../../core/http-error';
import { GeoHit, RouteService } from '../../core/route.service';
import { HelpLinkComponent } from '../../help/help-link.component';

/**
 * Ein Ortsfeld: einfacher Text, keine Vorschläge beim Tippen. Gesucht wird erst beim Berechnen (eine Anfrage je
 * geändertem Feld); das schont das Tageskontingent der Ortssuche. Der Text bleibt, wie er eingegeben wurde. Welcher Ort
 * dafür gefunden wurde, steht danach unter dem Feld ("Verwendet: …").
 *
 * Liegt der gefundene Punkt falsch (die Suche nimmt bei einem Dorf oft den Mittelpunkt der Gemeinde, der im Wald liegen kann),
 * zeigt "Anderen Ort wählen" die Treffer des Ortsdienstes zur Auswahl. Die Anfrage geht erst beim Klick hinaus.
 *
 *   <app-place-field label="Von" inputId="von" [text]="textOf(start())" [note]="usedFor(start())" [current]="coordsOf(start())"
 *                    (textChange)="setText(start().key, $event)" (pick)="choosePlace(start().key, $event)" (enter)="calculate()" />
 */
@Component({
  selector: 'app-place-field',
  imports: [FormsModule, HelpLinkComponent],
  template: `
    <label class="form-label" [class.fw-semibold]="emphasis()" [for]="inputId()">{{ label() }}</label>
    <input [id]="inputId()" type="text" class="form-control" [class.form-control-lg]="emphasis()" autocomplete="off" [placeholder]="placeholder()" [class.is-invalid]="!!problem()"
           [ngModel]="text()" (ngModelChange)="onText($event)" (keydown.enter)="enter.emit()" />
    @if (problem()) {
      <div class="invalid-feedback d-block"><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>{{ problem() }}</div>
    }
    @if (note()) {
      <!-- Der gefundene Ort: gut lesbar, damit man sofort sieht, wohin die Strecke führt. Passt er offensichtlich nicht zur Eingabe,
           steht er in Warnfarbe mit Hinweis (ein „Ratetreffer“ des Dienstes, zum Beispiel ein Gebiet am anderen Ende der Welt). -->
      <div class="d-flex gap-2 align-items-start mt-2 px-2 py-1 rounded border"
           [class.bg-white]="plausible()" [class.border-success]="plausible()"
           [class.bg-warning-subtle]="!plausible()" [class.border-warning]="!plausible()" role="status">
        <i class="bi flex-shrink-0 mt-1" [class.bi-geo-alt-fill]="plausible()" [class.text-success]="plausible()"
           [class.bi-exclamation-triangle-fill]="!plausible()" [class.text-warning-emphasis]="!plausible()" aria-hidden="true"></i>
        <div>
          <span class="fw-semibold">Gefundener Ort:</span> {{ note() }}
          @if (!plausible()) {
            <div class="small">Bitte prüfen: Passt dieser Ort zu Ihrer Eingabe? Wenn nicht, schreiben Sie ihn genauer oder wählen Sie unten „Anderen Ort wählen“.</div>
          }
        </div>
      </div>
    } @else if (hint()) {
      <div class="form-text">{{ hint() }}</div>
    }
    <!-- Immer anbietbar, sobald ein Text da ist: auch vor dem Berechnen und wenn nichts gefunden wurde (dann mit eigener Suche) -->
    @if (text().trim().length >= 2) {
      <div class="form-text">
        <button type="button" class="btn btn-link btn-sm p-0 align-baseline" [attr.aria-expanded]="open()" (click)="toggle()">
          {{ open() ? 'Auswahl schließen' : current() ? 'Anderen Ort wählen' : 'Ort aus Trefferliste wählen' }}
        </button>
        <app-help-link topic="strecken" anchor="ort-waehlen" label="Ort wählen" />
      </div>
    }
    @if (open()) {
      <!-- Andere Suche: Kennt der Dienst die Adresse nicht, hilft oft ein Orientierungspunkt („Sattendorf Bahnhof“, „… Ossiacher See“) -->
      <div class="input-group input-group-sm mt-1">
        <input type="text" class="form-control" autocomplete="off" aria-label="Suchtext für die Auswahl" [value]="query()"
               (input)="query.set($any($event.target).value)" (keydown.enter)="search()" />
        <button type="button" class="btn btn-outline-secondary" (click)="search()">Suchen</button>
      </div>
      <div class="form-text">Anders suchen, zum Beispiel mit einem Bahnhof oder See in der Nähe. Der Name im Feld bleibt Ihr Text.</div>
      <div class="list-group mt-1" role="listbox" [attr.aria-label]="'Treffer für ' + label()">
        @if (loading()) {
          <div class="list-group-item small text-body-secondary"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Treffer werden gesucht …</div>
        } @else if (error()) {
          <div class="list-group-item small text-danger">{{ error() }}</div>
        } @else {
          @for (h of hits(); track $index) {
            <button type="button" class="list-group-item list-group-item-action py-1 small d-flex gap-2 align-items-start" role="option"
                    [class.active]="isCurrent(h)" [attr.aria-selected]="isCurrent(h)" (click)="choose(h)">
              <i class="bi flex-shrink-0" [class.bi-check-lg]="isCurrent(h)" [class.bi-geo-alt]="!isCurrent(h)" aria-hidden="true"></i>
              <span>{{ h.label }}</span>
            </button>
          } @empty {
            <div class="list-group-item small text-body-secondary">Keine Treffer. Schreiben Sie den Ort genauer, zum Beispiel mit Postleitzahl.</div>
          }
        }
      </div>
    }
  `,
})
export class PlaceFieldComponent {
  private api = inject(RouteService);

  label = input.required<string>();
  inputId = input.required<string>();
  hint = input('');
  /** Von und Nach: Beschriftung fett, Feld größer (Start und Ziel der Fahrt sollen auffallen). */
  emphasis = input(false);
  /** Welcher Ort für den Text verwendet wird (steht nach dem Berechnen unter dem Feld) */
  note = input('');
  placeholder = input('Ort oder Adresse');
  text = input('');
  /** Fehlertext, wenn zu dem Text kein Ort gefunden wurde (das Feld wird dann rot markiert). */
  problem = input('');
  /** Lage des Ortes, der gerade für das Feld gilt (null: noch keiner gefunden). Nur dann gibt es „Anderen Ort wählen“. */
  current = input<{ lat: number; lon: number } | null>(null);

  /**
   * Passt der gefundene Ort offensichtlich zur Eingabe? Ja, wenn ein Wort des gefundenen Ortsnamens (vor dem ersten Komma, ab drei
   * Buchstaben) auch in der Eingabe vorkommt. „Irrawaddy, Myanmar“ zu „Senden-Ay“ besteht nicht, „Germanenstraße 4, Senden“ schon.
   */
  plausible = computed(() => {
    const found = this.note();
    if (!found) return true;
    const typed = this.text().toLowerCase();
    const words = found.split(',')[0].toLowerCase().split(/[\s\-/()]+/).filter((w) => w.length >= 3);
    return words.length === 0 || words.some((w) => typed.includes(w));
  });

  textChange = output<string>();
  /** Ein Treffer aus der Auswahl wurde gewählt. */
  pick = output<GeoHit>();
  /** Enter im Feld: die Seite berechnet. */
  enter = output<void>();

  open = signal(false);
  /** Suchtext in der Auswahl; beim Öffnen der Text des Feldes */
  query = signal('');
  loading = signal(false);
  error = signal<string | null>(null);
  hits = signal<GeoHit[]>([]);

  onText(value: string) {
    // Anderer Text: die Treffer gehören zum alten Text
    this.open.set(false);
    this.textChange.emit(value);
  }

  toggle() {
    if (this.open()) {
      this.open.set(false);
      return;
    }
    this.open.set(true);
    this.query.set(this.text().trim());
    this.search();
  }

  /** Die Suche, zu der die angezeigten Treffer gehören: Dieselbe Suche wird nicht noch einmal angefragt (Öffnen, Schließen, Öffnen). */
  private loadedQuery = '';

  search() {
    const q = this.query().trim();
    if (q.length < 2) return;
    if (q.toLowerCase() === this.loadedQuery) {
      this.loading.set(false);
      this.error.set(null);
      return;
    }
    this.loading.set(true);
    this.error.set(null);
    this.api.geocode(q, 6).subscribe({
      next: ({ data }) => {
        this.hits.set(data);
        this.loadedQuery = q.toLowerCase();
        this.loading.set(false);
      },
      error: (err: HttpErrorResponse) => {
        this.loading.set(false);
        this.error.set(httpErrorText(err, 'Die Treffer konnten nicht geladen werden.'));
      },
    });
  }

  isCurrent(h: GeoHit): boolean {
    const c = this.current();
    return !!c && Math.abs(c.lat - h.lat) < 1e-4 && Math.abs(c.lon - h.lon) < 1e-4;
  }

  choose(h: GeoHit) {
    this.open.set(false);
    this.pick.emit(h);
  }
}
