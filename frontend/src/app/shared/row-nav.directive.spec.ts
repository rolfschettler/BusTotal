import { Component, signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { RowNavDirective } from './row-nav.directive';

@Component({
  imports: [RowNavDirective],
  template: `
    <div appRowNav #nav="appRowNav" (rowSelect)="current.set($event)" (rowOpen)="opened.push($event)">
      <table>
        @for (id of ids; track id) {
          <tr [attr.data-row-id]="id" [class.row-current]="id === current()"><td>{{ id }} <button type="button">x</button></td></tr>
        }
      </table>
    </div>
  `,
})
class HostComponent {
  ids = ['a', 'b', 'c'];
  current = signal<string | null>(null);
  opened: string[] = [];
}

describe('Zeilen wählen und öffnen', () => {
  function setup() {
    const f = TestBed.createComponent(HostComponent);
    f.detectChanges();
    const el = f.nativeElement as HTMLElement;
    const press = (key: string) => el.querySelector('tr')!.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
    return { host: f.componentInstance, el, press };
  }

  it('zählt auch schnell aufeinanderfolgende Pfeiltasten', () => {
    const { host, press } = setup();
    press('ArrowDown');
    press('ArrowDown');
    press('ArrowDown');
    press('Enter');
    expect(host.current()).toBe('c');
    expect(host.opened).toEqual(['c']);
  });

  it('öffnet beim Klick auf die Zeile, nicht beim Klick auf eine Taste darin', () => {
    const { host, el } = setup();
    el.querySelectorAll('button')[1].click();
    expect(host.current()).toBe('b');
    expect(host.opened).toEqual([]);
    el.querySelectorAll('td')[2].click();
    expect(host.opened).toEqual(['c']);
  });
});
