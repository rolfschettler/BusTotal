import { Role } from './auth.service';

/** Anzeigenamen der Rollen. */
export const ROLE_LABELS: Record<Role, string> = {
  admin: 'Administrator',
  disponent: 'Disponent',
  buchhaltung: 'Buchhaltung',
  fahrer: 'Fahrer',
};

export const ROLE_OPTIONS: { value: Role; label: string }[] = (Object.keys(ROLE_LABELS) as Role[]).map((value) => ({
  value,
  label: ROLE_LABELS[value],
}));

export const roleLabel = (r?: string) => (r ? (ROLE_LABELS[r as Role] ?? r) : '');
