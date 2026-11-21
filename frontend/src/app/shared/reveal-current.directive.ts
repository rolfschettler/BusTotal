import { Directive, ElementRef, afterNextRender, inject, input } from '@angular/core';

/**
 * Scrollt eine Tabellenzeile in den sichtbaren Bereich, wenn sie als aktuelle Zeile entsteht
 * (Rücksprung aus einem Formular, siehe `ListMemory`). Ein späterer Klick auf eine schon sichtbare Zeile scrollt nicht.
 *
 *   <tr [appRevealCurrent]="d.id === currentId()"> …
 */
@Directive({ selector: '[appRevealCurrent]' })
export class RevealCurrentDirective {
  appRevealCurrent = input(false);
  private el = inject<ElementRef<HTMLElement>>(ElementRef);

  constructor() {
    afterNextRender(() => {
      if (this.appRevealCurrent()) this.el.nativeElement.scrollIntoView({ block: 'center' });
    });
  }
}
