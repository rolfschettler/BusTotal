import { Component, ElementRef, computed, effect, inject, signal, viewChild } from '@angular/core';
import { PrintPreviewService } from '../core/print-preview.service';

/** Breite, in der die Seiten für Vorschau und Druck gerendert werden: A4 bei ca. 150 dpi. */
const RENDER_WIDTH_PX = 1240;

/**
 * Druckvorschau für PDFs (einmal in `app.component` eingehängt). Zeigt die Seiten des PDFs mit pdf.js als Bild an;
 * „Drucken“ öffnet den Druckdialog des Geräts und druckt genau diese Seiten (Optik: `styles/_print-preview.scss`).
 * pdf.js wird erst beim ersten Öffnen geladen. Geöffnet wird über `PrintPreviewService.open()`.
 */
@Component({
  selector: 'app-print-preview',
  host: { '(document:keydown)': 'onKey($event)' },
  template: `
    @if (preview.state() !== 'closed') {
      @if (hiddenDirect()) {
        <div class="pp-toast" role="status"><span class="spinner-border spinner-border-sm me-2"></span>Druck wird vorbereitet …</div>
      }
      <div class="pp-overlay" [class.pp-direct]="hiddenDirect()" role="dialog" aria-modal="true" [attr.aria-label]="preview.title()">
        <div class="pp-bar">
          <span class="pp-title text-truncate">{{ preview.title() }}</span>
          <button type="button" class="btn btn-sm btn-primary" (click)="print()" [disabled]="!printable()">
            <i class="bi bi-printer"></i><span class="ms-1">Drucken</span>
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" (click)="preview.download()" [disabled]="!preview.blob()">
            <i class="bi bi-download"></i><span class="ms-1 d-none d-sm-inline">Herunterladen</span>
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary" (click)="preview.close()" aria-label="Schließen">
            <i class="bi bi-x-lg"></i><span class="ms-1 d-none d-sm-inline">Schließen</span>
          </button>
        </div>
        <div class="pp-body">
          @if (preview.state() === 'error') {
            <div class="alert alert-danger m-3" role="alert">{{ preview.error() }}</div>
          } @else if (progress()) {
            <div class="pp-status text-body-secondary small"><span class="spinner-border spinner-border-sm me-2"></span>{{ progress() }}</div>
          }
          <div class="pp-pages" #pages></div>
        </div>
      </div>
    }
  `,
})
export class PrintPreviewComponent {
  preview = inject(PrintPreviewService);
  progress = signal<string | null>(null);
  /** Erst drucken, wenn alle Seiten fertig gerendert sind (sonst käme ein halbes PDF aufs Papier). */
  printable = signal(false);

  /** Direktdruck läuft unsichtbar. Bei einem Fehler erscheint doch die Vorschau mit Meldung und „Herunterladen“. */
  hiddenDirect = computed(() => this.preview.direct() && this.preview.state() !== 'error');

  private pages = viewChild<ElementRef<HTMLElement>>('pages');
  private run = 0;

  constructor() {
    effect(() => {
      const state = this.preview.state();
      const blob = this.preview.blob();
      const container = this.pages()?.nativeElement;
      document.body.classList.toggle('pp-open', state !== 'closed');
      this.printable.set(false);
      if (state === 'loading') this.progress.set('PDF wird erstellt …');
      if (state === 'ready' && blob && container) void this.render(blob, container);
      if (state === 'closed') this.run++; // laufende Darstellung abbrechen
    });
  }

  print() {
    window.print();
  }

  /** Direktdruck: Druckvorschau des Browsers öffnen, danach die (unsichtbare) Vorschau schließen. */
  private printDirect() {
    window.addEventListener('afterprint', () => this.preview.close(), { once: true });
    window.print();
  }

  /** Esc schließt die Vorschau. */
  onKey(e: KeyboardEvent) {
    if (e.key === 'Escape' && this.preview.state() !== 'closed') this.preview.close();
  }

  private async render(blob: Blob, container: HTMLElement) {
    const run = ++this.run;
    container.replaceChildren();
    this.progress.set('Seiten werden aufgebaut …');
    try {
      const pdfjs = await import('pdfjs-dist/legacy/build/pdf.mjs');
      pdfjs.GlobalWorkerOptions.workerSrc = new URL('pdfjs/pdf.worker.min.mjs', document.baseURI).href;
      const doc = await pdfjs.getDocument({ data: new Uint8Array(await blob.arrayBuffer()) }).promise;
      try {
        // Seite für Seite, nicht alle gleichzeitig: schont den Speicher auf Tablets.
        for (let n = 1; n <= doc.numPages; n++) {
          if (run !== this.run) return;
          this.progress.set(`Seite ${n} von ${doc.numPages} …`);
          const page = await doc.getPage(n);
          const viewport = page.getViewport({ scale: RENDER_WIDTH_PX / page.getViewport({ scale: 1 }).width });
          const canvas = document.createElement('canvas');
          canvas.width = Math.floor(viewport.width);
          canvas.height = Math.floor(viewport.height);
          const ctx = canvas.getContext('2d');
          if (!ctx) throw new Error('canvas');
          await page.render({ canvasContext: ctx, viewport }).promise;
          if (run !== this.run) return;
          const wrap = document.createElement('div');
          wrap.className = 'pp-page';
          wrap.append(canvas);
          container.append(wrap);
        }
      } finally {
        void doc.destroy();
      }
      if (run !== this.run) return;
      this.progress.set(null);
      this.printable.set(true);
      if (this.preview.direct()) this.printDirect();
    } catch {
      if (run !== this.run) return;
      container.replaceChildren();
      this.progress.set(null);
      this.preview.fail('Die Vorschau konnte auf diesem Gerät nicht aufgebaut werden. Mit „Herunterladen“ erhalten Sie das PDF als Datei.');
    }
  }
}
