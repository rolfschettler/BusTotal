/**
 * Kartenkacheln für die Streckenplanung. Eine Stelle für alles: Anbieter wechseln = diese Datei ändern.
 *
 * ACHTUNG vor dem Produktivbetrieb: Die Kacheln von tile.openstreetmap.org sind nur für leichte Nutzung
 * gedacht (Nutzungsrichtlinie der OSM-Foundation: kein Dauerbetrieb für viele Nutzer, Quellenangabe Pflicht).
 * Für den echten Betrieb einen Kachel-Anbieter wählen (z. B. MapTiler, Stadia, Thunderforest oder eigene Kacheln)
 * und hier URL und Quellenangabe eintragen. Die Quellenangabe des Anbieters muss sichtbar bleiben.
 */
export const MAP_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
export const MAP_ATTRIBUTION =
  '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap-Mitwirkende</a> | Routing &copy; <a href="https://openrouteservice.org" target="_blank" rel="noopener">openrouteservice</a> by HeiGIT';
export const MAP_MAX_ZOOM = 18;

/** Ausgangsansicht ohne Punkte: Deutschland. */
export const MAP_DEFAULT_CENTER: [number, number] = [51.2, 10.4];
export const MAP_DEFAULT_ZOOM = 7;
