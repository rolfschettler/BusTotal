/** Zustand einer Frist. Dieselben Schwellen wie im Backend (Vehicle::WARN_DAYS). */
export type DeadlineState = 'overdue' | 'soon' | 'ok';

export const WARN_DAYS = 30;

const DAY = 86_400_000;

/** Tage von `today` bis zum Kalendertag `iso` (YYYY-MM-DD). Negativ = abgelaufen. Ohne Zeitzonenfehler. */
export function daysUntil(iso: string, today: Date = new Date()): number {
  const [y, m, d] = iso.split('-').map(Number);
  const due = Date.UTC(y, m - 1, d);
  const now = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
  return Math.round((due - now) / DAY);
}

export function deadlineState(days: number): DeadlineState {
  return days < 0 ? 'overdue' : days <= WARN_DAYS ? 'soon' : 'ok';
}

/** "abgelaufen seit 3 Tagen", "heute fällig", "in 12 Tagen". */
export function deadlineText(days: number): string {
  if (days < 0) return days === -1 ? 'seit 1 Tag abgelaufen' : `seit ${-days} Tagen abgelaufen`;
  if (days === 0) return 'heute fällig';
  if (days === 1) return 'morgen fällig';
  return `in ${days} Tagen`;
}

/** "31.03.2027" aus "2027-03-31". */
export function germanDate(iso: string | null | undefined): string {
  if (!iso) return '';
  const [y, m, d] = iso.split('-');
  return `${d}.${m}.${y}`;
}
