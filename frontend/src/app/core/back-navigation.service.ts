import { Injectable, inject } from '@angular/core';
import { NavigationEnd, Router } from '@angular/router';

/** Höchstens so viele Seiten merken */
const MAX = 50;

const pathOf = (url: string) => url.split(/[?#]/)[0];

/** Dorthin führt „Zurück“ nie: Anmeldung und leere Anlege-Formulare (nach dem Speichern wäre das eine zweite Neuanlage). */
const skipped = (url: string) => /\/(neu|login)$/.test(pathOf(url));

/**
 * „Zurück“ führt dorthin, wo man hergekommen ist (zum Beispiel von der Reise ins Angebot und zurück zur Reise), nicht immer zur Liste.
 * Der Dienst merkt sich die besuchten Seiten; er entsteht mit der Anwendung (AppComponent). Nach „Seite neu laden“ ist der Weg
 * unbekannt, dann gilt die Liste, die das Formular als Ersatz angibt.
 */
@Injectable({ providedIn: 'root' })
export class BackNavigation {
  private router = inject(Router);
  private pages: string[] = [];

  constructor() {
    this.router.events.subscribe((e) => {
      if (e instanceof NavigationEnd) this.visit(e.urlAfterRedirects);
    });
  }

  /** Eine Seite wurde geöffnet. */
  visit(url: string) {
    const pages = this.pages;
    if (pages.length > 1 && pages[pages.length - 2] === url) {
      pages.pop(); // zurück zur vorigen Seite: Sie steht schon da
    } else if (pages.length && pathOf(pages[pages.length - 1]) === pathOf(url)) {
      pages[pages.length - 1] = url; // dieselbe Seite mit anderen Angaben in der Adresse
    } else {
      pages.push(url);
      if (pages.length > MAX) pages.shift();
    }
  }

  /** Zur vorigen Seite; ist keine bekannt, zu `fallback` (meist die Liste). */
  back(fallback: string) {
    while (this.pages.length > 1 && skipped(this.pages[this.pages.length - 2])) this.pages.splice(this.pages.length - 2, 1);
    const previous = this.pages.length > 1 ? this.pages[this.pages.length - 2] : fallback;
    return this.router.navigateByUrl(previous);
  }
}
