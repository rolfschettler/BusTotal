import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { BackNavigation } from '../../core/back-navigation.service';
import { httpErrorText } from '../../core/http-error';
import { JourneyService } from '../../core/journey.service';
import { CustomerPickerComponent, PickedCustomer } from '../../shared/customer-picker.component';

/**
 * „Neue Reise planen“: Titel und (freiwillig) Kunde. Danach geht es mit der Hinfahrt in der Streckenplanung weiter („Anlegen und
 * Hinfahrt planen“) oder die Reise öffnet sich („Nur anlegen“); dort kommen die weiteren Strecken hinzu und am Ende das Angebot. Formular nach docs/ClaudeCodePatterns/EditFormPattern.md (Drittelraster, Tastenleiste unten).
 */
@Component({
  selector: 'app-journey-new',
  imports: [FormsModule, RouterLink, CustomerPickerComponent],
  templateUrl: './journey-new.component.html',
  host: { class: 'form-page' },
})
export class JourneyNewComponent {
  private api = inject(JourneyService);
  private router = inject(Router);
  private backNav = inject(BackNavigation);

  title = '';
  customer = signal<PickedCustomer | null>(null);
  submitted = signal(false);
  saving = signal(false);
  error = signal<string | null>(null);

  get dirty(): boolean {
    return this.title.trim() !== '' || !!this.customer();
  }

  /** `planHinfahrt`: Nach dem Anlegen gleich die Streckenplanung für die Hinfahrt öffnen, sonst die Reise-Seite. */
  save(planHinfahrt = true) {
    this.submitted.set(true);
    this.error.set(null);
    if (!this.title.trim()) {
      this.error.set('Bitte einen Namen für die Reise eingeben.');
      return;
    }
    this.saving.set(true);
    this.api.create(this.title.trim(), this.customer()?.id ?? null).subscribe({
      next: (j) =>
        planHinfahrt
          ? this.router.navigate(['/streckenplanung'], { queryParams: { journey: j.id, kind: 'hinfahrt' } })
          : this.router.navigate(['/reisen', j.id]),
      error: (e: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(httpErrorText(e, 'Die Reise konnte nicht angelegt werden.'));
      },
    });
  }

  /** „Zurück“: dorthin, wo man hergekommen ist. */
  back() {
    void this.backNav.back('/reisen');
  }

  cancel() {
    this.title = '';
    this.customer.set(null);
    this.submitted.set(false);
    this.error.set(null);
  }
}
