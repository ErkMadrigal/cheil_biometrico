// Como mostrar el origen de una checada (attendance_records.source_type + verify_mode)
// de forma consistente en Dashboard, Asistencia y Reportes.
export function sourceBadge(row) {
  if (row.source_type === 'zkteco_device') {
    return row.verify_mode === 'fingerprint'
      ? { label: 'Checador · Huella', colorKey: 'sky', color: 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300' }
      : { label: 'Checador · Facial', colorKey: 'violet', color: 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300' }
  }
  if (row.source_type === 'web_kiosk') {
    return { label: 'Registro biometrico', colorKey: 'amber', color: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' }
  }
  return { label: 'App movil', colorKey: 'emerald', color: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' }
}

export const SOURCE_OPTIONS = [
  { value: '', label: 'Todos los origenes' },
  { value: 'mobile_app', label: 'App movil' },
  { value: 'zkteco_device', label: 'Checador ZKTeco' },
  { value: 'web_kiosk', label: 'Registro biometrico' },
]
