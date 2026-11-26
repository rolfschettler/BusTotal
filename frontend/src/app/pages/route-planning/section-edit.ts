import { computed, signal } from '@angular/core';
import { SectionEditData, SectionFigures, SectionUpdate } from '../../core/journey.service';

type FigureKey = keyof SectionFigures;
const KEYS: FigureKey[] = ['loaded_km', 'empty_km', 'drive_s', 'days'];

/** Die Werte aus `values`, die von den berechneten abweichen (das sind die von Hand geänderten). */
function differences(values: SectionFigures, base: SectionFigures, keys: FigureKey[] = KEYS): Partial<SectionFigures> {
  return Object.fromEntries(keys.filter((k) => values[k] !== base[k]).map((k) => [k, values[k]]));
}

/** Uhrzeit („06:30“, Ortszeit) aus einem Zeitpunkt, sonst leer. */
function timeOf(at: string | null | undefined): string {
  const d = at ? new Date(at) : null;
  return d && !Number.isNaN(d.getTime()) ? d.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' }) : '';
}

/**
 * Eine Strecke der Reise, die in der Streckenplanung angelegt oder geändert wird: ihre Angaben (Bezeichnung, Datum, Zuschlag, Fahrzeuge) und ihre
 * Werte. Gültig ist je Wert der von Hand eingetragene, sonst der berechnete, sonst (Strecke ohne Route) der gespeicherte.
 */
export class SectionEdit {
  /** Art der Strecke; wählbar nur bei einer neuen */
  readonly kind;
  readonly title;
  readonly date;
  readonly time;
  readonly surcharge;
  readonly vehicles;
  /** berechnete Werte; null: die Strecke hat keine Route */
  readonly base;
  /** von Hand geänderte Werte */
  readonly manual;
  /** Orte, Betriebshof oder Optionen der Route wurden geändert */
  readonly routeChanged = signal(false);
  /** Es gibt eine neue Route (berechnet oder aus einer Vorlage), die mitgespeichert wird. */
  readonly newRoute = signal(false);
  /** Nach dem ersten Speichern-Versuch werden fehlerhafte Felder markiert. */
  readonly submitted = signal(false);
  private readonly initial: string;

  constructor(readonly data: SectionEditData) {
    this.kind = signal(data.kind);
    this.title = signal(data.title);
    this.date = signal(data.start_on ?? '');
    this.time = signal(timeOf(data.plan?.start_at));
    this.surcharge = signal<number | null>(data.surcharge_percent || null);
    const rows = data.vehicles.filter((v) => !!v.type).map((v) => ({ type: v.type!, count: v.count }));
    this.vehicles = signal(rows.length ? rows : [{ type: '', count: 1 }]);
    const base = data.plan?.calculated ?? (data.tour ? data.figures : null);
    this.base = signal<SectionFigures | null>(base);
    this.manual = signal<Partial<SectionFigures>>(base ? differences(data.figures, base) : {});
    this.initial = this.state();
  }

  readonly figures = computed<SectionFigures>(() => ({ ...(this.base() ?? this.data.figures), ...this.manual() }));
  readonly hasRoute = computed(() => this.base() !== null);
  readonly dirty = computed(() => this.routeChanged() || this.newRoute() || this.state() !== this.initial);

  /** Noch nicht auf der Reise gespeichert */
  get isNew(): boolean {
    return !this.data.id;
  }

  /** Gibt es Werte, die eine neue Berechnung ersetzen würde? Bei einer Strecke ohne Route sind alle eingetragenen von Hand. */
  readonly hasHandValues = computed(() =>
    this.hasRoute() ? Object.keys(this.manual()).length > 0 : this.figures().loaded_km > 0 || this.figures().drive_s > 0,
  );

  private state(): string {
    return JSON.stringify([this.title(), this.date(), this.time(), this.surcharge(), this.vehicles(), this.figures()]);
  }

  /** Ein Wert von Hand; leer oder gleich dem berechneten heißt: Es gilt wieder der berechnete. */
  setValue(key: FigureKey, value: number | null) {
    const base = this.base();
    this.manual.update((m) => {
      const next = { ...m };
      if (value === null || !Number.isFinite(value) || value === base?.[key]) delete next[key];
      else next[key] = value;
      return next;
    });
  }

  restoreCalculated() {
    this.manual.set({});
  }

  /** Eine neue Berechnung: Die Handwerte bleiben (`keepHandValues`) oder werden durch die berechneten ersetzt. */
  takeCalculation(calculated: SectionFigures, keepHandValues: boolean) {
    // Eine Strecke ohne Route kennt keine Leerfahrt: Behalten werden nur Kilometer, Fahrzeit und Tage.
    const keys: FigureKey[] = this.hasRoute() ? (Object.keys(this.manual()) as FigureKey[]) : ['loaded_km', 'drive_s', 'days'];
    this.manual.set(keepHandValues ? differences(this.figures(), calculated, keys) : {});
    this.base.set(calculated);
    this.newRoute.set(true);
  }

  /** Abfahrt für die Berechnung („2027-05-01T06:30“); ohne Datum und Uhrzeit leer. */
  startAt(): string {
    return this.date() && this.time() ? `${this.date()}T${this.time()}` : '';
  }

  /** Fehlertext je Feld (Schlüssel: title, vehicle0 …, surcharge, loaded_km, empty_km, drive_s, days). */
  readonly errors = computed(() => {
    const e: Record<string, string> = {};
    if (!this.title().trim()) e['title'] = 'Bitte eine Bezeichnung angeben.';
    const list = this.vehicles();
    list.forEach((v, i) => {
      if (!v.type) e[`vehicle${i}`] = 'Bitte einen Fahrzeugtyp wählen.';
      else if (list.findIndex((o) => o.type === v.type) !== i) e[`vehicle${i}`] = 'Diesen Typ gibt es schon. Erhöhen Sie dort die Anzahl.';
    });
    const s = this.surcharge();
    if (s !== null && (s < 0 || s > 100)) e['surcharge'] = 'Der Zuschlag muss zwischen 0 und 100 Prozent liegen.';
    const f = this.figures();
    if (f.loaded_km < 0 || (!this.hasRoute() && f.loaded_km <= 0)) e['loaded_km'] = 'Bitte die Kilometer angeben.';
    if (f.empty_km < 0) e['empty_km'] = 'Bitte die Kilometer angeben.';
    if (f.drive_s < 60) e['drive_s'] = 'Bitte die Fahrzeit angeben.';
    if (f.days < 1 || f.days > 60) e['days'] = 'Bitte 1 bis 60 Tage angeben.';
    return e;
  });

  /** Fehler eines Felds, sichtbar erst nach dem ersten Speichern-Versuch; sonst leer. */
  errorOf(field: string): string {
    return this.submitted() ? (this.errors()[field] ?? '') : '';
  }

  /** Was fehlt zum Speichern? null: nichts. */
  readonly problem = computed(() => Object.values(this.errors())[0] ?? null);

  /** Fehlt etwas, das auch eine Berechnung nicht ergänzt (Fahrzeuge, Zuschlag)? */
  inputProblem(): string | null {
    return Object.entries(this.errors()).find(([k]) => k.startsWith('vehicle') || k === 'surcharge')?.[1] ?? null;
  }

  /** Die Angaben für den Server (ohne Route, die kommt aus der Streckenplanung dazu). */
  body(): SectionUpdate {
    return {
      title: this.title().trim(),
      start_on: this.date() || null,
      surcharge_percent: this.surcharge() ?? 0,
      vehicles: this.vehicles().map((v) => ({ type: v.type, count: Math.min(99, Math.max(1, Math.round(Number(v.count)) || 1)) })),
      figures: this.figures(),
    };
  }
}
