import { Component, computed, input } from '@angular/core';

/** Die Anfangsbuchstaben eines Namens: „Gymnasium Potsdam“ → „GP“, „Albrecht, Sebastian“ → „AS“. */
export function initials(name: string | null | undefined): string {
  const words = (name ?? '').split(/[\s,.&/-]+/).filter((w) => /^[\p{L}\p{N}]/u.test(w));
  return words.slice(0, 2).map((w) => [...w][0]).join('').toUpperCase();
}

/** Kürzel-Kreis neben einem Namen. Rein schmückend: Der Name steht immer daneben. */
@Component({
  selector: 'app-avatar',
  template: '{{ text() }}',
  host: { 'aria-hidden': 'true', '[class.avatar-large]': 'large()' },
  styles: `
    :host {
      flex: none; display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: 50%;
      background: var(--brand-wash); color: var(--brand-ink); font-size: 0.8125rem; font-weight: 650;
    }
    :host.avatar-large { width: 3.25rem; height: 3.25rem; font-size: 1.0625rem; }
  `,
})
export class AvatarComponent {
  name = input<string | null | undefined>('');
  large = input(false);
  protected text = computed(() => initials(this.name()));
}
