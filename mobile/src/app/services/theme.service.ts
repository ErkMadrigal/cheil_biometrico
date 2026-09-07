import { Injectable, signal } from '@angular/core';

export type ThemeMode = 'dark' | 'light';

const STORAGE_KEY = 'cheil-checador-theme';

/**
 * Tema oscuro por defecto (look mas "pro"), con boton para invertir a claro. Se
 * aplica como clase en <html> para que toda la hoja de estilos (variables.scss)
 * reaccione via CSS custom properties, sin duplicar logica por componente.
 */
@Injectable({ providedIn: 'root' })
export class ThemeService {
  readonly mode = signal<ThemeMode>('dark');

  constructor() {
    const saved = localStorage.getItem(STORAGE_KEY) as ThemeMode | null;
    this.set(saved === 'light' ? 'light' : 'dark');
  }

  toggle() {
    this.set(this.mode() === 'dark' ? 'light' : 'dark');
  }

  set(mode: ThemeMode) {
    this.mode.set(mode);
    document.documentElement.classList.toggle('theme-light', mode === 'light');
    localStorage.setItem(STORAGE_KEY, mode);
  }
}
