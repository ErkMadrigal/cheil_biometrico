export interface EmployeeLookupResult {
  id: number;
  employee_number: string;
  first_name: string;
  paternal_last_name: string;
  maternal_last_name: string | null;
  position: string | null;
  photo_path: string | null;
  has_face_enrolled: boolean;
  /** Solo viene lleno cuando has_face_enrolled es false: token de 72h (mismo mecanismo
   * que la liga web /enrolar/:token) para que el empleado se auto-enrole aqui mismo. */
  enroll_token: string | null;
}

export function fullEmployeeName(e: EmployeeLookupResult): string {
  return [e.first_name, e.paternal_last_name, e.maternal_last_name].filter(Boolean).join(' ');
}

export interface CheckinResult {
  record: {
    id: number;
    recorded_at: string;
    latitude: string | null;
    longitude: string | null;
    location_label: string | null;
  };
  employee: { id: number; name: string };
  type: 'entrada' | 'checkpoint';
}
