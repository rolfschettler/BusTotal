import { AfterViewInit, Component, ElementRef, OnDestroy, ViewChild, ViewEncapsulation, effect, input } from '@angular/core';
import * as L from 'leaflet';
import { MAP_ATTRIBUTION, MAP_DEFAULT_CENTER, MAP_DEFAULT_ZOOM, MAP_MAX_ZOOM, MAP_TILE_URL } from '../core/map-config';

export interface MapMarker {
  lat: number;
  lon: number;
  /** Beschriftung im Kreis: "1", "2" … oder "B" (Betriebshof) */
  pin: string;
  /** Text beim Darüberfahren (kommt als reiner Text, nie als HTML) */
  label: string;
  kind: 'depot' | 'stop';
}

/**
 * Kartenbaustein (Leaflet) mit nummerierten Markierungen und einem Linienzug.
 *
 *   <app-map [markers]="..." [line]="[[lat, lon], ...]" [dimmed]="veraltet" />
 *
 * Die Kachelquelle steht in core/map-config.ts. Markierungen sind eigene HTML-Symbole (keine Bilddateien: Die
 * Standardsymbole von Leaflet finden ihre Bilder mit dem Anwendungs-Build nicht). Alle Texte werden als Text gesetzt.
 */
@Component({
  selector: 'app-map',
  template: '<div #canvas class="app-map-canvas" role="img" aria-label="Karte der Tour"></div>',
  styleUrl: './map.component.scss',
  encapsulation: ViewEncapsulation.None,
  host: { class: 'app-map', '[class.dimmed]': 'dimmed()' },
})
export class MapComponent implements AfterViewInit, OnDestroy {
  markers = input<MapMarker[]>([]);
  line = input<[number, number][]>([]);
  /** Ergebnis veraltet: Linie blasser zeichnen */
  dimmed = input(false);

  @ViewChild('canvas', { static: true }) private canvas!: ElementRef<HTMLDivElement>;
  private map?: L.Map;
  private layer = L.layerGroup();
  private fitted = '';
  private resizer?: ResizeObserver;

  constructor() {
    // Zeichnet neu, sobald sich Markierungen oder Linie ändern (nachdem die Karte existiert).
    effect(() => {
      this.markers();
      this.line();
      this.draw();
    });
  }

  ngAfterViewInit() {
    this.map = L.map(this.canvas.nativeElement, { zoomControl: true, attributionControl: true }).setView(MAP_DEFAULT_CENTER, MAP_DEFAULT_ZOOM);
    L.tileLayer(MAP_TILE_URL, { attribution: MAP_ATTRIBUTION, maxZoom: MAP_MAX_ZOOM }).addTo(this.map);
    this.layer.addTo(this.map);
    this.draw();

    // Ändert sich die Größe des Kartenfeldes (Fenster, Seitenaufbau), muss Leaflet das erfahren, sonst sitzen die Kacheln falsch.
    this.resizer = new ResizeObserver(() => this.map?.invalidateSize());
    this.resizer.observe(this.canvas.nativeElement);
  }

  ngOnDestroy() {
    this.resizer?.disconnect();
    this.map?.remove();
    this.map = undefined;
  }

  private draw() {
    const map = this.map;
    if (!map) return;
    this.layer.clearLayers();

    const line = this.line();
    if (line.length > 1) {
      L.polyline(line, { color: '#1d4e89', weight: 5, opacity: 0.85 }).addTo(this.layer);
    }

    const bounds: L.LatLngTuple[] = line.length > 1 ? [...line] : [];
    for (const m of this.markers()) {
      const icon = L.divIcon({
        className: 'app-map-pin-wrap',
        html: `<span class="app-map-pin ${m.kind === 'depot' ? 'depot' : ''}">${m.pin.replace(/[^0-9A-Za-z]/g, '')}</span>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
      });
      const marker = L.marker([m.lat, m.lon], { icon, title: m.label }).addTo(this.layer);
      const tip = document.createElement('span');
      tip.textContent = m.label;
      marker.bindTooltip(tip, { direction: 'top', offset: [0, -12] });
      bounds.push([m.lat, m.lon]);
    }

    // Ausschnitt nur anpassen, wenn sich die Punkte geändert haben (nicht bei jedem Neuzeichnen, sonst springt die Karte
    // zurück, wenn der Benutzer gerade zoomt).
    const key = JSON.stringify(bounds);
    if (key === this.fitted) return;
    this.fitted = key;
    if (bounds.length === 0) {
      map.setView(MAP_DEFAULT_CENTER, MAP_DEFAULT_ZOOM);
    } else if (bounds.length === 1) {
      map.setView(bounds[0], 10);
    } else {
      map.fitBounds(L.latLngBounds(bounds), { padding: [32, 32], maxZoom: 13 });
    }
  }
}
