import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';

export interface Branding {
  name: string;
  brand_color: string;
  has_logo: boolean;
  logo_version: number | null;
}

const DEFAULT_COLOR = '#1d4e89';

/** Name, Farbe und Logo der Firma; setzt die Markenfarbe für die ganze Oberfläche. */
@Injectable({ providedIn: 'root' })
export class BrandService {
  private http = inject(HttpClient);

  readonly name = signal('BusTotal');
  readonly color = signal(DEFAULT_COLOR);
  readonly logoUrl = signal<string | null>(null);

  load() {
    this.http.get<Branding>('/api/branding').subscribe({
      next: (b) => this.apply(b),
      error: () => this.applyColor(DEFAULT_COLOR), // Server nicht erreichbar: Standardfarbe
    });
  }

  apply(b: Branding) {
    this.name.set(b.name || 'BusTotal');
    this.logoUrl.set(b.has_logo ? `/api/branding/logo?v=${b.logo_version ?? 0}` : null);
    this.applyColor(b.brand_color);
    document.title = this.name();
  }

  private applyColor(hex: string) {
    const m = /^#([0-9a-f]{6})$/i.exec(hex);
    const color = m ? hex.toLowerCase() : DEFAULT_COLOR;
    const n = parseInt(color.slice(1), 16);
    const [r, g, b] = [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    // Dunklere Variante für Hover/Aktiv: 15 % Schwarz beimischen.
    const dark = [r, g, b].map((c) => Math.round(c * 0.85).toString(16).padStart(2, '0')).join('');

    const root = document.documentElement.style;
    root.setProperty('--brand', color);
    root.setProperty('--brand-dark', `#${dark}`);
    root.setProperty('--brand-rgb', `${r}, ${g}, ${b}`);

    // Töne für Tabellenköpfe, Auswahl und Hinweise. Bootstrap rechnet sie beim Bauen fest aus EINER Farbe aus
    // ("subtle" = 80 % Weiß, "border-subtle" = 60 % Weiß, "text-emphasis" = 60 % Schwarz). Da jede Firma ihre
    // eigene Farbe hat, rechnen wir dieselbe Formel zur Laufzeit. styles.scss hängt --bs-primary-*-subtle daran.
    root.setProperty('--brand-subtle', mix([r, g, b], 255, 0.8));
    root.setProperty('--brand-border-subtle', mix([r, g, b], 255, 0.6));
    root.setProperty('--brand-text-emphasis', mix([r, g, b], 0, 0.6));
    this.color.set(color);
  }
}

/** Mischt eine Farbe mit Weiß (255) oder Schwarz (0). `weight` ist der Anteil von Weiß bzw. Schwarz. */
function mix(rgb: number[], target: 0 | 255, weight: number): string {
  return (
    '#' +
    rgb
      .map((c) => Math.round(c * (1 - weight) + target * weight).toString(16).padStart(2, '0'))
      .join('')
  );
}
