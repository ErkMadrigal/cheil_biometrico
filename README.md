# Sistema de Registro Biometrico

Proyecto con 3 partes:

| Parte | Tecnologia | Estado |
|---|---|---|
| `backend/` (proyecto real: `C:\xampp\htdocs\cheil_biometrico`) | CodeIgniter 4 (API REST + integracion ADMS ZKTeco) | Listo (ver `backend/README.md`) |
| `webpanel/` | Vue 3 + Vite + Tailwind v4 | Login, Dashboard, Empleados, Asistencia, Reportes, Dispositivos, Mapa y kiosko de registro biometrico listos (ver `webpanel/README.md`) |
| `mobile/` | Ionic + Angular + Capacitor | Listo: registro biometrico con GPS obligatorio (ver `mobile/README.md`) |

## Que hace el sistema

- La app movil escanea el rostro del empleado (TensorFlow.js / face-api.js) y registra su
  ubicacion (lat/long) cada vez que checa, tantas veces al dia como haga falta (visitas a
  distintos clientes, no solo entrada/salida de oficina).
- El primer registro del dia = entrada, el ultimo = salida; todo lo intermedio queda como
  historial de ubicaciones/visitas del dia.
- En la oficina hay dos checadores fisicos ZKTeco (huella en la entrada, facial en la salida)
  que desbloquean una chapa imantada y mandan sus registros al backend automaticamente via el
  protocolo ADMS (sin integracion manual).
- El web panel permite dar de alta empleados (con foto, CURP/RFC, numero de empleado), ver a
  todos los empleados y sus registros, un reporteador por empleado o general, y un dashboard.

## Por donde empezar

```bash
cd backend
# sigue backend/README.md paso a paso (composer create-project + copiar app/ + migrar + seed)
```

## Roadmap

1. [Listo] Backend + base de datos (CodeIgniter 4 + MySQL)
2. [Listo] Web panel (Vue): login, dashboard, empleados, asistencia, reportes, dispositivos,
   mapa (hoy + historial) y kiosko biometrico
3. [Listo] App movil (Ionic + Angular + Capacitor): reconocimiento facial on-device,
   geolocalizacion obligatoria, checkin (ver `mobile/README.md` para compilar el APK)
