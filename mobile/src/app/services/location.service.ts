import { Injectable } from '@angular/core';
import { Geolocation, Position } from '@capacitor/geolocation';

export type LocationPermissionState = 'granted' | 'denied' | 'prompt' | 'unavailable';

/**
 * Todo lo relacionado a permiso/lectura de GPS pasa por aqui, en un solo lugar, porque
 * la regla de negocio es estricta: SIN ubicacion NO se puede registrar una checada. Este
 * servicio nunca regresa una posicion si el permiso no fue concedido explicitamente.
 */
@Injectable({ providedIn: 'root' })
export class LocationService {
  async checkPermission(): Promise<LocationPermissionState> {
    try {
      const status = await Geolocation.checkPermissions();
      return this.mapState(status.location);
    } catch {
      return 'unavailable';
    }
  }

  async requestPermission(): Promise<LocationPermissionState> {
    try {
      const status = await Geolocation.requestPermissions();
      return this.mapState(status.location);
    } catch {
      return 'unavailable';
    }
  }

  /**
   * Regresa la posicion actual, o null si no se pudo (permiso negado, GPS apagado, etc).
   * NUNCA lanza: el llamador decide que hacer con null (bloquear el registro).
   */
  async getCurrentPosition(): Promise<Position | null> {
    try {
      const permission = await this.checkPermission();
      if (permission !== 'granted') return null;

      return await Geolocation.getCurrentPosition({
        enableHighAccuracy: true,
        timeout: 15000,
      });
    } catch {
      return null;
    }
  }

  private mapState(state: string): LocationPermissionState {
    if (state === 'granted') return 'granted';
    if (state === 'denied') return 'denied';
    if (state === 'prompt' || state === 'prompt-with-rationale') return 'prompt';
    return 'unavailable';
  }
}
