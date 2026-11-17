import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { map } from 'rxjs';
import { HighlightComponent } from '../shared/highlight.component';
import { HelpTarget, HelpViewerComponent } from './help-viewer.component';
import { HelpHit, HelpService } from './help.service';

/** Vollständige Hilfeseite: Themenliste, Suche und der gewählte Text. */
@Component({
  selector: 'app-help-page',
  imports: [FormsModule, RouterLink, HelpViewerComponent, HighlightComponent],
  templateUrl: './help-page.component.html',
})
export class HelpPageComponent implements OnInit {
  help = inject(HelpService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);

  topic = toSignal(this.route.paramMap.pipe(map((p) => p.get('topic') ?? 'start')), { initialValue: 'start' });
  anchor = toSignal(this.route.fragment, { initialValue: null });

  q = signal('');
  hits = signal<HelpHit[]>([]);
  /** Der Suchbegriff, zu dem die angezeigten Treffer gehören (Grundlage der Markierung) */
  hitsQuery = signal('');
  searching = computed(() => this.q().trim().length >= 2);

  private timer?: ReturnType<typeof setTimeout>;
  private requestNo = 0;

  ngOnInit() {
    this.help.loadIndex();
  }

  onSearch(value: string) {
    this.q.set(value);
    clearTimeout(this.timer);
    this.timer = setTimeout(async () => {
      const request = ++this.requestNo;
      const found = this.searching() ? await this.help.search(value) : [];
      if (request !== this.requestNo) return; // inzwischen wurde weiter getippt
      this.hits.set(found);
      this.hitsQuery.set(value);
    }, 250);
  }

  go(t: HelpTarget) {
    this.q.set('');
    this.router.navigate(['/hilfe', t.topic], { fragment: t.anchor ?? undefined });
  }

  clearSearch() {
    this.q.set('');
  }
}
