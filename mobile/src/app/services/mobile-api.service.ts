import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../environments/environment';
import { CheckinResult, EmployeeLookupResult } from '../models/employee.model';

interface ApiEnvelope<T> {
  status: 'success' | 'error';
  message: string;
  data: T;
  errors?: Record<string, string>;
}

/**
 * Habla con los endpoints publicos /api/v1/mobile/* del backend (sin JWT: el empleado se
 * identifica con numero/CURP/RFC y la camara confirma su identidad, igual que el kiosko
 * del web panel, pero estas checadas quedan marcadas como source_type=mobile_app).
 */
@Injectable({ providedIn: 'root' })
export class MobileApiService {
  private readonly base = environment.apiUrl;

  constructor(private http: HttpClient) {}

  async lookup(query: string): Promise<EmployeeLookupResult> {
    const res = await firstValueFrom(
      this.http.post<ApiEnvelope<EmployeeLookupResult>>(`${this.base}/mobile/lookup`, { query }),
    );
    return res.data;
  }

  async checkin(payload: {
    employee_id: number;
    descriptor: number[];
    latitude: number;
    longitude: number;
    accuracy_meters?: number | null;
    location_label?: string | null;
  }): Promise<CheckinResult> {
    const res = await firstValueFrom(
      this.http.post<ApiEnvelope<CheckinResult>>(`${this.base}/mobile/checkin`, payload),
    );
    return res.data;
  }

  photoUrl(photoPath: string | null): string | null {
    if (!photoPath) return null;
    // El backend guarda photo_path como "uploads/employees/xxx.jpg" (relativo).
    return `${environment.mediaUrl}/${photoPath}`.replace(/([^:]\/)\/+/g, '$1');
  }

  // --- Auto-enrolamiento dentro de la app (primera vez que un empleado la usa) ---
  // Reusa los mismos endpoints publicos que la liga web /enrolar/:token: no hay logica
  // nueva en el backend, solo se pega directo con el token que ya viene en lookup().

  async enrollSave(token: string, descriptor: number[]): Promise<void> {
    await firstValueFrom(
      this.http.post<ApiEnvelope<null>>(`${this.base}/enroll/${token}/save`, { descriptor }),
    );
  }

  async enrollVerify(token: string, descriptor: number[]): Promise<{ face_distance: number }> {
    const res = await firstValueFrom(
      this.http.post<ApiEnvelope<{ face_distance: number }>>(`${this.base}/enroll/${token}/verify`, {
        descriptor,
      }),
    );
    return res.data;
  }

  // --- Home Office: el empleado fija su ubicacion personal (una sola vez, hasta que un
  // admin la desbloquee), con margen de 100m para checar su entrada. ---

  async homeLocation(payload: {
    employee_id: number;
    descriptor: number[];
    latitude: number;
    longitude: number;
  }): Promise<{ home_lat: string; home_lng: string; home_radius_meters: number; home_location_set_at: string }> {
    const res = await firstValueFrom(
      this.http.post<
        ApiEnvelope<{ home_lat: string; home_lng: string; home_radius_meters: number; home_location_set_at: string }>
      >(`${this.base}/mobile/home-location`, payload),
    );
    return res.data;
  }
}
