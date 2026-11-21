import { HttpErrorResponse } from '@angular/common/http';
import { Injectable, signal } from '@angular/core';
import { Observable, Subscription } from 'rxjs';
import { httpErrorText } from './http-error';

export interface PrintPreviewRequest {
  /** Überschrift der Vorschau, z. B. „Rechnung RE-2027-0001“ */
  title: string;
  /** Dateiname für „Herunterladen“ */
  filename: string;
  /** Holt das PDF vom Server (Blob). Wird erst aufgerufen, wenn die Vorschau schon offen ist. */
  load: () => Observable<Blob>;
}

/**
 * Direktdruck am PC: Statt der Vorschau in der App öffnet sich gleich die Druckvorschau des Browsers. Auf Tablets und Handys
 * (Touch, Android, iOS) bleibt die Vorschau in der App. Ausprobieren: auf `false` setzen, dann gilt überall die Vorschau in der App.
 */
export const DIRECT_PRINT_ON_DESKTOP = true;

/** PC mit Maus (Windows, macOS, Linux). Ein iPad mit Tastatur meldet sich als Mac, zählt aber als Tablet. */
export function isDesktopDevice(): boolean {
  if (typeof matchMedia !== 'function') return false;
  const iPadAsMac = /Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1;
  return matchMedia('(pointer: fine) and (hover: hover)').matches && !iPadAsMac;
}

export type PrintPreviewState = 'closed' | 'loading' | 'ready' | 'error';

/**
 * Die EINE Stelle, an der ein PDF angezeigt wird: Druckvorschau in der App (kein neuer Tab, kein Popup-Blocker),
 * funktioniert am PC genauso wie auf Android- und iOS-Tablets. Gedruckt wird über den Druckdialog des Geräts
 * (siehe `PrintPreviewComponent`, `styles/_print-preview.scss`).
 *
 * Regel: PDFs nie per `window.open`, immer hier öffnen:
 *
 *   this.preview.open({ title: 'Rechnung RE-1', filename: 'RE-1.pdf', load: () => this.api.pdf(id) });
 */
@Injectable({ providedIn: 'root' })
export class PrintPreviewService {
  state = signal<PrintPreviewState>('closed');
  title = signal('');
  filename = signal('beleg.pdf');
  blob = signal<Blob | null>(null);
  error = signal<string | null>(null);
  /** Direktdruck: Die Seiten werden unsichtbar aufgebaut und gleich gedruckt, die Vorschau in der App bleibt zu. */
  direct = signal(false);

  private sub?: Subscription;

  open(req: PrintPreviewRequest) {
    this.sub?.unsubscribe();
    this.title.set(req.title);
    this.filename.set(req.filename);
    this.blob.set(null);
    this.error.set(null);
    this.direct.set(DIRECT_PRINT_ON_DESKTOP && isDesktopDevice());
    this.state.set('loading');

    this.sub = req.load().subscribe({
      next: (b) => {
        this.blob.set(new Blob([b], { type: 'application/pdf' }));
        this.state.set('ready');
      },
      error: (e: HttpErrorResponse) => {
        this.error.set(httpErrorText(e, 'Das PDF konnte nicht erstellt werden.'));
        this.state.set('error');
      },
    });
  }

  /** Die Darstellung selbst ist gescheitert (altes Gerät, beschädigte Datei): Hinweis, Herunterladen bleibt möglich. */
  fail(message: string) {
    this.error.set(message);
    this.state.set('error');
  }

  close() {
    this.sub?.unsubscribe();
    this.state.set('closed');
    this.direct.set(false);
    this.blob.set(null);
    this.error.set(null);
  }

  /** PDF als Datei speichern (Notausgang, und auf iOS zum Teilen). */
  download() {
    const blob = this.blob();
    if (!blob) return;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = this.filename();
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 60_000);
  }
}
