/**
 * Name des Produkts (Arbeitsname). EINZIGE Stelle im Frontend, an der er steht.
 * Beim Umbenennen nur hier ändern (Checkliste: docs/UMBENENNUNG.md). Nicht zu verwechseln mit dem Firmennamen
 * des Mandanten (BrandService), der in der Kopfleiste steht.
 * Im Hilfetext steht dafür der Platzhalter {{app}}.
 */
export const APP_NAME = 'BusTotal';

/**
 * Unterdomäne der Betreiber-Oberfläche (admin.bustotal.de). Muss zu SUPERADMIN_SUBDOMAIN im Backend passen
 * (backend/config/tenancy.php). Unter dieser Adresse startet nicht die Firmen-Anwendung, sondern die
 * Betreiber-Oberfläche (src/app/superadmin).
 */
export const SUPERADMIN_SUBDOMAIN = 'admin';

export function isSuperadminHost(hostname: string = location.hostname): boolean {
  const parts = hostname.toLowerCase().split('.');
  return parts.length > 1 && parts[0] === SUPERADMIN_SUBDOMAIN;
}
