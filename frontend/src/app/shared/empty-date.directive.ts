import { AfterViewChecked, Directive, ElementRef, inject } from '@angular/core';

/**
 * Merkt an Datums- und Zeitfeldern, ob sie leer sind (Klasse `is-empty`). Ein leeres, gesperrtes Feld zeigt dann nichts statt
 * „tt.mm.jjjj“: Das sähe in einer reinen Anzeige wie eine Eingabeaufforderung aus (styles.scss, „Nur Anzeige“).
 */
@Directive({ selector: 'input[type=date], input[type=time], input[type=datetime-local]' })
export class EmptyDateDirective implements AfterViewChecked {
  private el = inject<ElementRef<HTMLInputElement>>(ElementRef);

  ngAfterViewChecked() {
    this.el.nativeElement.classList.toggle('is-empty', !this.el.nativeElement.value);
  }
}
