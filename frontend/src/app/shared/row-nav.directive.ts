import { AfterViewChecked, Directive, ElementRef, HostListener, afterNextRender, inject, input, output } from '@angular/core';

/** Ab dieser Breite steht neben der Liste die Vorschau (styles/_list-page.scss, .list-peek). */
export const PREVIEW_MEDIA = '(min-width: 1500px)';

/**
 * Zeilen einer Liste wählen und öffnen (Tabellen-Muster). Gehört an den Scroll-Container, die Zeilen tragen
 * `[attr.data-row-id]` und die Klasse `row-current` für die aktuelle Zeile.
 *
 *   <div class="list-body" appRowNav #nav="appRowNav" (rowSelect)="select($event)" (rowOpen)="open($event)">
 *
 * Klick auf die Zeile öffnet sie. Links, Tasten und Felder in der Zeile tun nur ihr Eigenes, Text bleibt markierbar.
 * Pfeiltasten wählen, Enter öffnet, aus der Suche (`appListSearch`) und von den Zeilen aus.
 * Mit `[preview]="true"` wählt der Klick auf breiten Bildschirmen nur (die Vorschau zeigt die Zeile), Doppelklick öffnet.
 */
@Directive({ selector: '[appRowNav]', exportAs: 'appRowNav' })
export class RowNavDirective implements AfterViewChecked {
  rowSelect = output<string>();
  rowOpen = output<string>();
  preview = input(false);

  private host = inject<ElementRef<HTMLElement>>(ElementRef);
  /** Gerade gewählte Zeile, solange die Anzeige noch nicht nachgezogen ist (schnell aufeinanderfolgende Tasten). */
  private chosen: string | null = null;

  private rows(): HTMLElement[] {
    return Array.from(this.host.nativeElement.querySelectorAll<HTMLElement>('tr[data-row-id]'));
  }

  /** Genau eine Zeile ist mit dem Tabulator erreichbar: die aktuelle, sonst die erste. */
  ngAfterViewChecked() {
    this.chosen = null; // ab jetzt gilt wieder die Klasse row-current
    const rows = this.rows();
    const current = rows.find((r) => r.classList.contains('row-current')) ?? rows[0];
    for (const r of rows) r.tabIndex = r === current ? 0 : -1;
  }

  @HostListener('click', ['$event'])
  onClick(event: Event) {
    const id = this.idOf(event);
    if (!id) return;
    this.rowSelect.emit(id);
    if (this.onControl(event) || window.getSelection()?.toString() || this.showsPreview()) return;
    this.rowOpen.emit(id);
  }

  @HostListener('dblclick', ['$event'])
  onDoubleClick(event: Event) {
    const id = this.idOf(event);
    if (id && this.showsPreview() && !this.onControl(event)) this.rowOpen.emit(id);
  }

  private idOf(event: Event): string | undefined {
    return (event.target as HTMLElement).closest<HTMLElement>('tr[data-row-id]')?.dataset['rowId'];
  }

  private onControl(event: Event): boolean {
    return !!(event.target as HTMLElement).closest('a, button, input, select, textarea, label');
  }

  private showsPreview(): boolean {
    return this.preview() && window.matchMedia(PREVIEW_MEDIA).matches;
  }

  @HostListener('keydown', ['$event'])
  onKeydown(event: KeyboardEvent) {
    if ((event.target as HTMLElement).tagName === 'TR') this.key(event);
  }

  /** Pfeiltasten und Enter. */
  key(event: KeyboardEvent) {
    const down = event.key === 'ArrowDown';
    if (!down && event.key !== 'ArrowUp' && event.key !== 'Enter') return;
    event.preventDefault();

    const rows = this.rows();
    if (!rows.length) return;
    const at = rows.findIndex((r) => (this.chosen ? r.dataset['rowId'] === this.chosen : r.classList.contains('row-current')));

    if (event.key === 'Enter') {
      const id = rows[Math.max(0, at)].dataset['rowId']!;
      this.rowSelect.emit(id);
      this.rowOpen.emit(id);
      return;
    }

    const next = rows[at < 0 ? 0 : Math.min(rows.length - 1, Math.max(0, at + (down ? 1 : -1)))];
    this.chosen = next.dataset['rowId']!;
    this.rowSelect.emit(this.chosen);
    next.scrollIntoView({ block: 'nearest' });
    if ((event.target as HTMLElement).tagName === 'TR') next.focus({ preventScroll: true });
  }
}

/**
 * Das Suchfeld einer Liste: bekommt beim Öffnen den Cursor und reicht Pfeiltasten und Enter an die Zeilen weiter.
 *
 *   <input type="search" class="form-control list-search" appListSearch [nav]="nav" [pending]="q.trim() !== shownQuery()"
 *          (searchNow)="searchNow()" …>
 */
@Directive({ selector: 'input[appListSearch]' })
export class ListSearchDirective {
  nav = input.required<RowNavDirective>();
  /** Getippt, aber noch nicht gesucht (oder die Suche läuft): Enter sucht, statt einen alten Treffer zu öffnen. */
  pending = input(false);
  searchNow = output<void>();

  private host = inject<ElementRef<HTMLInputElement>>(ElementRef);

  constructor() {
    afterNextRender(() => this.host.nativeElement.focus({ preventScroll: true }));
  }

  @HostListener('keydown', ['$event'])
  onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && this.pending()) {
      event.preventDefault();
      this.searchNow.emit();
      return;
    }
    this.nav().key(event);
  }
}
