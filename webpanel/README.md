# Web Panel - Sistema de Registro Biometrico (Vue 3 + Vite + Tailwind v4)

Panel administrativo: dashboard, empleados, asistencia, reportes y dispositivos.
Consume la API del backend CI4 (`C:\xampp\htdocs\cheil_biometrico`).

## Que trae ya funcionando

- **Login** contra `POST /api/v1/auth/login` (JWT en `localStorage`, interceptor de axios que lo
  manda en cada request y te regresa a `/login` si expira).
- **Layout** con sidebar, topbar, menu de usuario, **tema claro/oscuro** persistido sin parpadeo, y
  notificaciones tipo toast reutilizables (`stores/toast.js`).
- **Dashboard** conectado a `/api/v1/dashboard/summary` y `/api/v1/attendance`: tarjetas de
  metricas, grafica de 7 dias (Chart.js) y actividad reciente.
- **Empleados**: listado con busqueda/filtro/paginacion, alta y edicion (drawer lateral) con foto,
  baja logica con confirmacion inline, y **enrolamiento facial real desde la camara** (face-api.js /
  TensorFlow.js corriendo 100% en el navegador: detecta el rostro en vivo, calcula el descriptor de
  128 numeros y lo manda a `POST /employees/{id}/face`). Los modelos de ML se cargan solo al abrir
  el modal de enrolar (carga perezosa, no engordan el resto de la app).
- **Registro biometrico (kiosko)** en `/registro-biometrico`, accesible desde un boton en el login,
  SIN password: el empleado teclea su numero/CURP/RFC, confirma que es el con foto+nombre, y la
  camara (face-api.js) verifica su rostro contra el enrolado antes de guardar la checada. Pensado
  para correr en una compu/tablet compartida en recepcion en vez de comprar otro checador fisico.
  La pantalla se reinicia sola despues de cada registro para el siguiente empleado.
- **Asistencia**: tabla de todas las checadas con filtros por empleado (buscador con autocompletado,
  `EmployeePicker.vue`), rango de fechas y origen (app/checador/kiosko), con link directo a Google
  Maps por cada registro con coordenadas.
- **Reportes**: reporteador por empleado o de todos, con entrada/salida/total de registros por dia,
  y **exportar a CSV real** (se pide como blob autenticado con JWT, no un link directo, para que el
  export tambien respete la sesion).
- **Detalle de un dia** (`DayDetailDrawer.vue`, compartido entre Asistencia y Reportes): linea de
  tiempo de un empleado en un dia especifico marcando entrada, visitas intermedias y salida, con
  hora, ubicacion, origen y selfie si aplica.
- **Mapa** (`/mapa`, Leaflet + OpenStreetMap, sin costo/API key): pensado para empresas con
  home office — muestra donde se registro cada empleado. Dos modos con un switch arriba:
  - **Hoy**: todos con su ultima ubicacion del dia; si das click en un empleado (ej. "Ana"), traza
    su recorrido completo del dia numerado (1 = entrada en Polanco, 2 = visita en Santa Fe, etc).
    Se refresca solo cada minuto y trae boton de actualizar manual.
  - **Historial**: eliges un empleado (`EmployeePicker`) y un rango de fechas (default ultimos 30
    dias) y ves TODOS los puntos donde se registro en ese rango — util para responder "¿donde ha
    estado Ana este ultimo mes?". Cada punto en el mapa/lista muestra fecha, hora, ubicacion y
    origen (app movil / checador / kiosko). No agrega endpoints nuevos: reusa
    `GET /api/v1/attendance` con `employee_id` + `date_from` + `date_to`.
- **Dispositivos** (`/dispositivos`): CRUD de checadores ZKTeco en tarjetas — nombre, tipo (huella
  entrada / facial salida / otro), numero de serie, ubicacion, IP y ultima vez visto. Los que se
  autoregistran solos al primer handshake ADMS (`DeviceModel::autoRegister`) aparecen aqui
  inactivos, con nombre generico, listos para que el admin los renombre y active.
- **Carga masiva de empleados** (boton "Importar" en Empleados, `EmployeeImportModal.vue`): descarga
  una plantilla `.xlsx`, la subes ya llena y se procesa fila por fila (upsert por numero de empleado).
  Muestra el resultado con contadores de creados/actualizados/con error y el detalle de cada renglon
  que fallo, para poder corregir y volver a subir solo esas filas si hace falta.
- **Zonas geograficas** (`/zonas`): define areas permitidas para checar (nombre, direccion,
  centro y radio en km, marcados con click en un mapa). Si hay al menos una zona activa,
  el backend rechaza checadas (kiosko/app movil) fuera de todas las zonas.
- **Reporte de exportacion** (`/reportes-exportacion`): filtro por empleado (o todos) y
  rango de fechas inicio/fin, vista previa con las columnas exactas del archivo, y
  descarga en `.xlsx` (Employee ID, Employee Name, Work date, Year&Date, Time In, Time Out)
  listo para pegar en el sistema de nomina externo.
- **Home Office por empleado** (seccion de solo lectura en el drawer de editar empleado,
  `EmployeeFormDrawer.vue`): el empleado fija su propia ubicacion desde la app movil (con
  verificacion facial + GPS, radio de 100m); aqui solo se ve en un mini mapa junto con la
  fecha en que se fijo, y hay un boton "Desbloquear" para cuando el empleado se muda (asi
  la puede volver a capturar el mismo desde la app).
- **Movilidad** (`/movilidad`, `MobilityView.vue`): busca un empleado y activa/desactiva
  que pueda checar su asistencia desde CUALQUIER lugar (sin validar zonas ni Home
  Office) - pensado para quien visita clientes y necesita evidencia de asistencia sin
  estar atado a una ubicacion fija.
- **Liga de auto-enrolamiento remoto** (icono de liga por empleado en Empleados, `EnrollLinkModal.vue`):
  genera una liga de un solo uso, valida 72 horas, para que el empleado enrole su propio rostro SIN
  tener acceso al panel — util cuando molestar a TI por cada alta es tardado. La liga abre
  `/enrolar/:token` (`EnrollTokenView.vue`, pantalla publica): el empleado confirma su identidad,
  captura su rostro y luego se le pide una **segunda foto en vivo** que se compara contra la primera
  antes de dar el enrolamiento por bueno — evita que quede una foto de mala calidad o de otra persona
  sin que nadie se de cuenta.

## Instalacion

```bash
cd webpanel
npm install       # necesario de nuevo si ya lo habias corrido antes: se agrego @vladmandic/face-api
cp .env.example .env
npm run dev
```

Abre `http://localhost:5173`. Entra con el usuario que crea el seeder del backend:

```
email:    admin@cheil.local
password: Admin12345!
```

### Como se conecta al backend

En desarrollo, `vite.config.js` trae un proxy: `/api/*` y `/uploads/*` (fotos de empleados) se
reenvian a `http://localhost/cheil_biometrico/public`. Si tu backend corre en otra URL, cambia el
`target` ahi, o define `VITE_API_URL` en `.env` para pegarle directo a la URL del backend (util para
producción).

### Fotos de empleados / modelos de reconocimiento facial

- `public/models/` trae los pesos de `tiny_face_detector`, `face_landmark_68` y `face_recognition`
  (face-api.js / `@vladmandic/face-api`), ~7MB. Se sirven como archivos estaticos, no necesitan CDN
  ni conexion a internet en produccion.
- Las fotos que subes en el panel se guardan en el backend (`writable/uploads/...`, fuera de
  `public/`) y se sirven a traves de `GET /uploads/{folder}/{archivo}` (`FilesController` en el
  backend) — por eso el proxy de `/uploads` es necesario en desarrollo.

### Build de produccion

```bash
npm run build   # genera dist/
npm run preview # sirve dist/ localmente para probarlo
```

Al desplegar, define `VITE_API_URL` apuntando a la URL publica del backend (y agrega ese dominio a
`CORS_ALLOWED_ORIGINS` en el `.env` del backend).

## Estructura

```
src/
  api/client.js             -> instancia de axios + interceptores (JWT, 401 -> logout)
  api/employees.js           -> llamadas a /employees (list/get/create/update/delete/saveFace)
  stores/auth.js              -> Pinia: login/logout, usuario actual
  stores/theme.js              -> Pinia: tema claro/oscuro
  stores/toast.js               -> Pinia: notificaciones (toast.success / toast.error)
  composables/useFaceModels.js  -> carga perezosa de face-api.js + sus 3 modelos
  router/index.js                -> rutas + guard de autenticacion
  layouts/AppLayout.vue          -> sidebar + topbar + ToastHost (todas las vistas protegidas viven aqui)
  views/LoginView.vue
  views/DashboardView.vue
  views/EmployeesView.vue         -> listado + filtros + paginacion
  views/EnrollTokenView.vue        -> pantalla PUBLICA /enrolar/:token (auto-enrolamiento + verificacion)
  components/Icon.vue               -> set de iconos SVG inline (sin dependencia externa)
  components/ui/Avatar.vue, Badge.vue, ToastHost.vue
  components/employees/EmployeeFormDrawer.vue -> alta/edicion con foto
  components/employees/FaceEnrollModal.vue     -> camara + deteccion en vivo + captura de rostro
  components/employees/EmployeeImportModal.vue -> carga masiva por .xlsx (plantilla + upload + resultado)
  components/employees/EnrollLinkModal.vue      -> genera y muestra la liga de auto-enrolamiento (72h)
  api/employees.js                                -> incluye importXlsx/downloadTemplate/generateEnrollToken
  api/enrollToken.js                               -> lookup/saveFace/verifyFace (cliente axios SIN JWT, es publico)
```

## Limpieza pendiente

El scaffold de Vite dejo algunos archivos de ejemplo sin usar, se pueden borrar sin problema:
`src/components/HelloWorld.vue`, `src/assets/hero.png`, `src/assets/vue.svg`, `public/icons.svg`.

## Siguiente paso

Todo lo planeado del web panel ya esta conectado. Lo que sigue es la app movil (Ionic + Angular)
para checadas fuera de oficina con camara + geolocalizacion.
