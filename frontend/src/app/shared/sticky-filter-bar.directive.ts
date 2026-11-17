import { AfterViewInit, Directive, ElementRef, OnDestroy, inject } from '@angular/core';

/**
 * Misst die Höhe der klebenden Filterleiste und schreibt sie als --filter-bar-h an die Listenseite.
 * Der klebende Tabellenkopf (siehe _list-page.scss) sitzt damit genau unter der Filterleiste, auch wenn
 * sie sich ändert (zweizeilig bei schmalem Fenster, Hinweise einblenden …).
 *
 *   <div class="filter-bar" appStickyFilterBar> … </div>
 */
@Directive({ selector: '[appStickyFilterBar]' })
export class StickyFilterBarDirective implements AfterViewInit, OnDestroy {
  private el = inject<ElementRef<HTMLElement>>(ElementRef);
  private observer?: ResizeObserver;
  private target?: HTMLElement;

  ngAfterViewInit() {
    this.target = this.el.nativeElement.closest<HTMLElement>('.list-page') ?? undefined;
    if (!this.target) return;

    const apply = () => this.target?.style.setProperty('--filter-bar-h', `${this.el.nativeElement.offsetHeight}px`);
    apply();
    this.observer = new ResizeObserver(apply);
    this.observer.observe(this.el.nativeElement);
  }

  ngOnDestroy() {
    this.observer?.disconnect();
  }
}
