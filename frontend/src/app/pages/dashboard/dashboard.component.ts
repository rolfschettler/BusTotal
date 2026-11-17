import { DatePipe, NgComponentOutlet } from '@angular/common';
import { Component, computed, inject } from '@angular/core';
import { AuthService } from '../../core/auth.service';
import { TILES } from './tiles/tile-registry';

/** Startseite: Begrüßung und die Kacheln, die zur Rolle des Benutzers passen. */
@Component({
  selector: 'app-dashboard',
  imports: [NgComponentOutlet, DatePipe],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.scss',
})
export class DashboardComponent {
  private auth = inject(AuthService);

  today = new Date();

  tiles = computed(() => {
    const role = this.auth.user()?.role;
    return role ? TILES.filter((t) => t.roles.includes(role)) : [];
  });

  greeting = computed(() => {
    const hour = new Date().getHours();
    const hello = hour < 11 ? 'Guten Morgen' : hour < 18 ? 'Guten Tag' : 'Guten Abend';
    const first = this.auth.user()?.name.split(' ')[0];
    return first ? `${hello}, ${first}` : hello;
  });
}
