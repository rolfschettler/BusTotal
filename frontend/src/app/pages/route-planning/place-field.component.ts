import { Component, input, output } from '@angular/core';
import { FormsModule } from '@angular/forms';

/**
 * Ein Ortsfeld: einfacher Text, keine Vorschläge beim Tippen. Gesucht wird erst beim Berechnen (eine Anfrage je
 * geändertem Feld); das schont das Tageskontingent der Ortssuche. Der Text bleibt, wie er eingegeben wurde. Welcher Ort
 * dafür gefunden wurde, steht danach unter dem Feld ("Verwendet: …").
 *
 *   <app-place-field label="Von" inputId="von" [text]="textOf(start())" [note]="usedFor(start())" (textChange)="setText(start().key, $event)" (enter)="calculate()" />
 */
@Component({
  selector: 'app-place-field',
  imports: [FormsModule],
  template: `
    <label class="form-label" [for]="inputId()">{{ label() }}</label>
    <input [id]="inputId()" type="text" class="form-control" autocomplete="off" [placeholder]="placeholder()"
           [ngModel]="text()" (ngModelChange)="textChange.emit($event)" (keydown.enter)="enter.emit()" />
    @if (note()) {
      <div class="form-text"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Verwendet: {{ note() }}</div>
    } @else if (hint()) {
      <div class="form-text">{{ hint() }}</div>
    }
  `,
})
export class PlaceFieldComponent {
  label = input.required<string>();
  inputId = input.required<string>();
  hint = input('');
  /** Welcher Ort für den Text verwendet wird (steht nach dem Berechnen unter dem Feld) */
  note = input('');
  placeholder = input('Ort oder Adresse');
  text = input('');

  textChange = output<string>();
  /** Enter im Feld: die Seite berechnet. */
  enter = output<void>();
}
