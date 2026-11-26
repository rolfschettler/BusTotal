import { Component, input, output } from '@angular/core';
import { SectionFigures } from '../../core/journey.service';
import { SectionEdit } from './section-edit';
import { formatDuration } from './route-planning-view';

/**
 * Angaben und Werte einer Strecke der Reise in der Streckenplanung, als zwei Formularflächen nebeneinander. Label oben, je Fläche ein
 * Raster (Formular-Muster): links Drittel, rechts Hälften. Weitere Felder der Seite (Fahrzeug, Fahrtart) kommen als Inhalt nach links.
 * Datum, Zuschlag, Fahrzeuge und Werte lassen sich ändern, ohne die Route neu zu berechnen.
 */
@Component({
  selector: 'app-section-edit-fields',
  template: `
    @let s = edit();
    <div class="row g-3">
    <div class="col-12 col-lg-7">
    <div class="form-area h-100">
    <div class="section-title">Angaben zur Strecke</div>
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label" for="secTitle">Bezeichnung <span class="text-danger">*</span></label>
        <input id="secTitle" type="text" class="form-control" maxlength="150" [class.is-invalid]="s.errorOf('title')" [value]="s.title()" (input)="s.title.set($any($event.target).value)" />
        <div class="invalid-feedback">{{ s.errorOf('title') }}</div>
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label" for="secDate">Datum der Fahrt</label>
        <input id="secDate" type="date" class="form-control" [value]="s.date()" (input)="s.date.set($any($event.target).value)" />
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label" for="secTime">Abfahrtszeit</label>
        <input id="secTime" type="time" class="form-control" [value]="s.time()" (change)="s.time.set($any($event.target).value); timeChanged.emit()" />
      </div>
      <div class="col-12 col-lg-4">
        <label class="form-label" for="secSurcharge">Zuschlag (%)</label>
        <input id="secSurcharge" type="number" min="0" max="100" step="1" class="form-control" [class.is-invalid]="s.errorOf('surcharge')" [value]="s.surcharge() ?? ''"
               (change)="s.surcharge.set(numberOf($any($event.target).value))" />
        <div class="invalid-feedback">{{ s.errorOf('surcharge') }}</div>
      </div>
      <div class="col-12"><div class="form-text mt-0">Das Datum bestimmt Zuschläge für Wochenende und Feiertage. Eine neue Abfahrtszeit (mit Datum) berechnet die Strecke neu.</div></div>

      @for (v of s.vehicles(); track $index; let i = $index) {
        <div class="col-12 col-lg-8">
          <label class="form-label" [for]="'secType' + i">Fahrzeugtyp <span class="text-danger">*</span></label>
          <select [id]="'secType' + i" class="form-select" [class.is-invalid]="s.errorOf('vehicle' + i)" (change)="setVehicle(i, { type: $any($event.target).value })">
            <option value="" [selected]="!v.type">Bitte wählen</option>
            @for (t of vehicleTypes(); track t.value) { <option [value]="t.value" [selected]="t.value === v.type">{{ t.label }}</option> }
          </select>
          <div class="invalid-feedback">{{ s.errorOf('vehicle' + i) }}</div>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" [for]="'secCount' + i">Anzahl</label>
          <div class="input-group">
            <input [id]="'secCount' + i" type="number" min="1" max="99" step="1" class="form-control" [value]="v.count" (change)="setVehicle(i, { count: +$any($event.target).value })" />
            @if (s.vehicles().length > 1) {
              <button type="button" class="btn btn-outline-secondary" aria-label="Fahrzeugtyp entfernen" title="Fahrzeugtyp entfernen" (click)="removeVehicle(i)"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            }
          </div>
        </div>
      }
      <div class="col-12">
        <button type="button" class="btn btn-sm btn-outline-secondary" (click)="addVehicle()"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Weiteren Fahrzeugtyp</button>
      </div>
      <ng-content />
    </div>
    </div>
    </div>

    <div class="col-12 col-lg-5">
    <div class="form-area h-100">
    <div class="section-title">Werte für den Preis</div>
    <div class="row g-3">
      <div class="col-6">
        <label class="form-label" for="valKm">{{ s.hasRoute() ? 'Kilometer mit Gästen' : 'Kilometer' }} <span class="text-danger">*</span></label>
        <input id="valKm" type="number" min="0" step="0.1" class="form-control" [class.border-warning]="changedByHand('loaded_km')" [class.is-invalid]="s.errorOf('loaded_km')"
               [value]="s.figures().loaded_km || ''" (change)="s.setValue('loaded_km', numberOf($any($event.target).value))" />
        <div class="invalid-feedback">{{ s.errorOf('loaded_km') }}</div>
        @if (changedByHand('loaded_km')) { <div class="form-text">Berechnet: {{ km(s.base()!.loaded_km) }}</div> }
      </div>
      @if (s.hasRoute()) {
        <div class="col-6">
          <label class="form-label" for="valEmpty">Leerkilometer</label>
          <input id="valEmpty" type="number" min="0" step="0.1" class="form-control" [class.border-warning]="changedByHand('empty_km')" [class.is-invalid]="s.errorOf('empty_km')"
                 [value]="s.figures().empty_km" (change)="s.setValue('empty_km', numberOf($any($event.target).value))" />
          <div class="invalid-feedback">{{ s.errorOf('empty_km') }}</div>
          @if (changedByHand('empty_km')) { <div class="form-text">Berechnet: {{ km(s.base()!.empty_km) }}</div> }
        </div>
      }
      <div class="w-100 m-0"></div>
      <div class="col-6">
        <label class="form-label" for="valHours">Fahrzeit Std. <span class="text-danger">*</span></label>
        <input id="valHours" type="number" min="0" step="1" class="form-control" [class.border-warning]="changedByHand('drive_s')" [class.is-invalid]="s.errorOf('drive_s')"
               [value]="s.figures().drive_s ? hours() : ''" (change)="setDrive(numberOf($any($event.target).value), minutes())" />
        <div class="invalid-feedback">{{ s.errorOf('drive_s') }}</div>
        @if (changedByHand('drive_s')) { <div class="form-text">Berechnet: {{ duration(s.base()!.drive_s) }}</div> }
      </div>
      <div class="col-6">
        <label class="form-label" for="valMinutes">Minuten</label>
        <input id="valMinutes" type="number" min="0" max="59" step="1" class="form-control" [class.border-warning]="changedByHand('drive_s')"
               [value]="s.figures().drive_s ? minutes() : ''" (change)="setDrive(hours(), numberOf($any($event.target).value))" />
      </div>
      <div class="col-6">
        <label class="form-label" for="valDays">Tage</label>
        <input id="valDays" type="number" min="1" max="60" step="1" class="form-control" [class.border-warning]="changedByHand('days')" [class.is-invalid]="s.errorOf('days')"
               [value]="s.figures().days" (change)="s.setValue('days', numberOf($any($event.target).value))" />
        <div class="invalid-feedback">{{ s.errorOf('days') }}</div>
        @if (changedByHand('days')) { <div class="form-text">Berechnet: {{ s.base()!.days }}</div> }
      </div>
    </div>
    @if (s.hasRoute() && s.hasHandValues()) {
      <div class="alert alert-warning py-2 small d-flex flex-wrap align-items-center gap-2 mt-2 mb-0" role="status">
        <span class="me-auto"><i class="bi bi-pencil-square me-1" aria-hidden="true"></i>Von Hand geändert. Für den Preis gelten Ihre Werte.</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" (click)="s.restoreCalculated()">Berechnete Werte nehmen</button>
      </div>
    } @else {
      <div class="form-text">
        @if (s.hasRoute()) { Die Werte stammen aus der Berechnung. Sie können sie überschreiben. }
        @else { Route berechnen, oder die Werte ohne Route selbst eintragen, zum Beispiel für einen Ausflug vor Ort. }
      </div>
    }
    </div>
    </div>
    </div>
  `,
})
export class SectionEditFieldsComponent {
  edit = input.required<SectionEdit>();
  vehicleTypes = input<{ value: string; label: string }[]>([]);
  /** Die Abfahrtszeit gehört zur Route: Die Seite muss neu berechnen. */
  timeChanged = output<void>();

  readonly duration = formatDuration;
  readonly km = (v: number) => `${v.toLocaleString('de-DE', { maximumFractionDigits: 1 })} km`;

  hours(): number {
    return Math.floor(Math.round(this.edit().figures().drive_s / 60) / 60);
  }
  minutes(): number {
    return Math.round(this.edit().figures().drive_s / 60) % 60;
  }
  setDrive(hours: number | null, minutes: number | null) {
    this.edit().setValue('drive_s', ((hours ?? 0) * 60 + (minutes ?? 0)) * 60);
  }

  changedByHand(key: keyof SectionFigures): boolean {
    return this.edit().hasRoute() && this.edit().manual()[key] !== undefined;
  }

  /** Zahl aus einem Eingabefeld; leer oder unlesbar = null. */
  numberOf(text: string): number | null {
    const t = String(text ?? '').trim();
    const n = Number(t.replace(',', '.'));
    return t === '' || !Number.isFinite(n) ? null : n;
  }

  setVehicle(i: number, patch: Partial<{ type: string; count: number }>) {
    this.edit().vehicles.update((list) => list.map((v, j) => (j === i ? { ...v, ...patch } : v)));
  }
  addVehicle() {
    this.edit().vehicles.update((list) => [...list, { type: '', count: 1 }]);
  }
  removeVehicle(i: number) {
    this.edit().vehicles.update((list) => list.filter((_, j) => j !== i));
  }
}
