import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { BackNavigation } from '../../core/back-navigation.service';
import { CustomerService } from '../../core/customer.service';
import { useFormPage } from '../../core/form-page';
import { httpErrorText } from '../../core/http-error';
import { JourneyService } from '../../core/journey.service';
import { CustomerPickerComponent, PickedCustomer } from '../../shared/customer-picker.component';

/**
 * „Neue Reise planen“: Titel und (freiwillig) Kunde. Danach geht es mit der Hinfahrt in der Streckenplanung weiter („Anlegen und
 * Hinfahrt planen“) oder die Reise öffnet sich („Nur anlegen“); dort kommen die weiteren Strecken hinzu und am Ende das Angebot. Formular nach docs/ClaudeCodePatterns/EditFormPattern.md (Drittelraster, Tastenleiste unten).
 */
@Component({
  selector: 'app-journey-new',
  imports: [FormsModule, CustomerPickerComponent],
  templateUrl: './journey-new.component.html',
  host: { class: 'form-page' },
})
export class JourneyNewComponent implements OnInit {
  private route = inject(ActivatedRoute);
  private customers = inject(CustomerService);
  private api = inject(JourneyService);
  private router = inject(Router);
  private backNav = inject(BackNavigation);

  title = '';
  customer = signal<PickedCustomer | null>(null);
  submitted = signal(false);
  saving = signal(false);
  error = signal<string | null>(null);

  /** Von der Kundenseite aus vorgewählt: Das zählt noch nicht als Eingabe. */
  private preset: string | null = null;

  get dirty(): boolean {
    return this.title.trim() !== '' || (this.customer()?.id ?? null) !== this.preset;
  }

  ngOnInit() {
    const id = this.route.snapshot.queryParamMap.get('kunde');
    if (!id) return;
    // Schlägt das Laden fehl, bleibt das Feld leer und der Kunde wird von Hand gewählt.
    this.customers.get(id).subscribe({
      next: (c) => {
        if (this.customer()) return;
        this.preset = c.id;
        this.customer.set({
          id: c.id, display_name: c.display_name, customer_number: c.customer_number, company_name: c.company_name,
          first_name: c.first_name, last_name: c.last_name, postal_code: c.postal_code, city: c.city,
        });
      },
      error: () => undefined,
    });
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
      next: (j) => {
        this.created = true; // angelegt: beim Weiterleiten nicht nachfragen
        return planHinfahrt
          ? this.router.navigate(['/streckenplanung'], { queryParams: { journey: j.id, kind: 'hinfahrt' } })
          : this.router.navigate(['/reisen', j.id]);
      },
      error: (e: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(httpErrorText(e, 'Die Reise konnte nicht angelegt werden.'));
      },
    });
  }

  private created = false;
  canLeave = useFormPage({
    unsaved: () => !this.created && this.dirty,
    save: () => {
      if (!this.saving()) this.save();
    },
  });

  /** „Zurück“: dorthin, wo man hergekommen ist. */
  back() {
    void this.backNav.back('/reisen');
  }

  cancel() {
    this.title = '';
    this.preset = null;
    this.customer.set(null);
    this.submitted.set(false);
    this.error.set(null);
  }
}
