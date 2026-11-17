import { Type } from '@angular/core';
import { Role } from '../../../core/auth.service';
import { DriverTileComponent } from './driver.tile';
import { QuickActionsTileComponent } from './quick-actions.tile';
import { RecentCustomersTileComponent } from './recent-customers.tile';
import { SetupTileComponent } from './setup.tile';

export interface TileDef {
  id: string;
  /** Rollen, die diese Kachel sehen. Der Server prüft die Rolle zusätzlich je Kachel-Schnittstelle. */
  roles: Role[];
  component: Type<unknown>;
}

/**
 * Verzeichnis aller Startseiten-Kacheln. Ein neues Modul trägt hier seine Kacheln ein, zum Beispiel
 * "Anstehende Fahrten" für Disponenten oder "Fällige Fristen" für den Fuhrpark. Die Reihenfolge
 * hier ist die Reihenfolge auf der Seite. Die Kachel selbst lädt ihre Daten und entscheidet,
 * ob sie etwas anzeigt.
 *
 * Kachel-Komponenten brauchen `:host { display: contents; }`, damit die Karte direkt im Raster liegt.
 * Eine Karte über die volle Breite bekommt `grid-column: 1 / -1`.
 */
export const TILES: TileDef[] = [
  { id: 'quick-actions', roles: ['admin', 'disponent', 'buchhaltung'], component: QuickActionsTileComponent },
  { id: 'setup', roles: ['admin'], component: SetupTileComponent },
  { id: 'recent-customers', roles: ['admin', 'disponent', 'buchhaltung'], component: RecentCustomersTileComponent },
  { id: 'driver-assignments', roles: ['fahrer'], component: DriverTileComponent },
];
