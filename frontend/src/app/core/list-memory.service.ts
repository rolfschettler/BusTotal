import { Injectable } from '@angular/core';

/** Was eine Liste sich merkt: die aktuelle Zeile und ihre Einstellungen (Seite, Filter, Sortierung). */
interface Remembered {
  id: string | null;
  params: Record<string, unknown> | null;
}

/**
 * „Aktueller Datensatz“ je Liste (Kunden, Belege, Fahrzeuge, Fahrer …).
 *
 * Regel: Wer in einer Liste eine Zeile anklickt, macht sie zur aktuellen Zeile. Springt man von dort weg (Formular, Folgebeleg …)
 * und kommt zurück, steht die Liste wieder so da wie vorher (Seite, Suche, Filter, Sortierung) und die Zeile ist markiert
 * und im sichtbaren Bereich (`RevealCurrentDirective`).
 *
 * Ein Formular meldet mit `follow`, welchen Datensatz es gerade zeigt. Ist das ein anderer als der gemerkte (neu angelegt,
 * Folgebeleg, Storno), gelten die gemerkten Einstellungen nicht mehr (Seite 1, kein Filter), damit der neue Datensatz sicher
 * zu sehen ist. Ausnahme `keepParams`: siehe `follow`.
 *
 * Nur im Arbeitsspeicher: Nach „Seite neu laden“ beginnt jede Liste wieder von vorn.
 */
@Injectable({ providedIn: 'root' })
export class ListMemory {
  private lists = new Map<string, Remembered>();

  /** Die aktuelle Zeile der Liste `key`, falls es eine gibt. */
  current(key: string): string | null {
    return this.lists.get(key)?.id ?? null;
  }

  /** Die gemerkten Einstellungen der Liste (oder null, dann gelten die Grundeinstellungen). */
  params<P extends Record<string, unknown>>(key: string): P | null {
    return (this.lists.get(key)?.params as P | null | undefined) ?? null;
  }

  /** Klick auf eine Zeile in der Liste. */
  select(key: string, id: string | null) {
    this.entry(key).id = id;
  }

  /** Die Liste hat Einstellungen geändert (Seite, Filter, Sortierung). */
  saveParams(key: string, params: Record<string, unknown>) {
    this.entry(key).params = params;
  }

  /**
   * Ein Formular zeigt den Datensatz `id`. Ein anderer als der gemerkte setzt die Liste zurück (siehe oben), außer
   * `keepParams`: Dann bleiben Seite, Suche, Filter und Sortierung, und nur die Markierung wandert. Das nutzt eine Liste,
   * die die neue Zeile selbst findet (Belege: der Server liefert mit `locate` die Seite, auf der sie steht).
   */
  follow(key: string, id: string, keepParams = false) {
    const e = this.entry(key);
    if (e.id === id) return;
    if (keepParams) e.id = id;
    else this.lists.set(key, { id, params: null });
  }

  /** Der Datensatz ist weg (gelöscht): nichts mehr markieren. */
  forget(key: string, id: string) {
    const e = this.lists.get(key);
    if (e?.id === id) e.id = null;
  }

  private entry(key: string): Remembered {
    let e = this.lists.get(key);
    if (!e) this.lists.set(key, (e = { id: null, params: null }));
    return e;
  }
}
