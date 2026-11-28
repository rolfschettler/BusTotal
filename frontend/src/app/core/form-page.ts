import { DestroyRef, inject } from '@angular/core';
import { NgbModal } from '@ng-bootstrap/ng-bootstrap';
import { AuthService } from './auth.service';
import { ConfirmService } from './confirm.service';

export interface FormPageOptions {
  /** Gibt es Eingaben, die beim Verlassen verloren gingen? */
  unsaved: () => boolean;
  /** Speichert das sichtbare Formular (Strg+S). Prüft selbst, ob gerade gespeichert werden darf. */
  save?: () => void;
}

/**
 * Gemeinsames Verhalten jeder Formularseite (Formular-Muster): Strg+S speichert, beim Neuladen oder Schließen warnt der
 * Browser, und beim Verlassen über Menü, Link oder „Zurück“ fragt die Seite nach.
 *
 *   canLeave = useFormPage({ unsaved: () => this.form.dirty, save: () => this.save() });
 *
 * Dazu gehört in der Route `canDeactivate: [unsavedGuard]`. Wer nach dem Speichern oder Löschen selbst weiterleitet,
 * setzt vorher `form.markAsPristine()`.
 */
export function useFormPage(options: FormPageOptions): () => boolean | Promise<boolean> {
  const auth = inject(AuthService);
  const confirm = inject(ConfirmService);
  const modals = inject(NgbModal);

  const onKey = (event: KeyboardEvent) => {
    if (!options.save || !(event.ctrlKey || event.metaKey) || event.altKey || event.key.toLowerCase() !== 's') return;
    event.preventDefault(); // nicht die Browser-Funktion „Seite speichern“
    if (!event.repeat && !modals.hasOpenModals()) options.save();
  };
  const onUnload = (event: BeforeUnloadEvent) => {
    if (options.unsaved()) {
      event.preventDefault();
      event.returnValue = '';
    }
  };
  document.addEventListener('keydown', onKey);
  window.addEventListener('beforeunload', onUnload);
  inject(DestroyRef).onDestroy(() => {
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('beforeunload', onUnload);
  });

  // Nach dem Abmelden wird nicht mehr gefragt.
  return () => {
    if (!auth.isLoggedIn() || !options.unsaved()) return true;
    return confirm.ask({
      title: 'Änderungen verwerfen?',
      message: 'Ihre Änderungen sind noch nicht gespeichert. Wenn Sie die Seite verlassen, gehen sie verloren.',
      confirmLabel: 'Verwerfen',
      cancelLabel: 'Weiter bearbeiten',
      danger: true,
    });
  };
}
