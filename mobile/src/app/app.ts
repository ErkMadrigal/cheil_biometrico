import { Component, inject } from '@angular/core';
import { IonApp, IonRouterOutlet } from '@ionic/angular/standalone';
import { ThemeService } from './services/theme.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [IonApp, IonRouterOutlet],
  template: `
    <ion-app>
      <ion-router-outlet></ion-router-outlet>
    </ion-app>
  `,
})
export class App {
  // Se inyecta aqui (no se usa directo en el template) para que el tema se
  // aplique a <html> desde el arranque de la app, antes de pintar cualquier pagina.
  private theme = inject(ThemeService);
}
