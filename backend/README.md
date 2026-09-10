# Backend - Sistema de Registro Biometrico (CodeIgniter 4)

API REST que da servicio al **web panel (Vue)** y a la **app movil (Ionic + Angular)**, y que ademas
recibe en tiempo real las checadas del **checador ZKTeco** (protocolo ADMS) instalado en la puerta de
la oficina.

Este repo contiene solo la capa de aplicacion (`app/`) + migraciones + seeds. El "esqueleto" de
CodeIgniter 4 (carpeta `public/`, `writable/`, `vendor/`, `spark`, etc.) se genera con Composer, como
se explica abajo.

## 1. Requisitos

- PHP 8.1 o superior (extensiones: `intl`, `mbstring`, `mysqli`, `json`, `curl`)
- Composer 2.x
- MySQL 8.0+ o MariaDB 10.6+
- (Opcional) `php spark serve` para desarrollo, o Apache/Nginx + PHP-FPM para produccion

## 2. Instalacion

Este `backend/` ya trae escritos todos los archivos de `app/` (controladores, modelos, migraciones,
filtros, rutas). Falta generar el esqueleto base de CI4 alrededor de ellos:

```bash
# 1) Genera un esqueleto CI4 limpio en una carpeta aparte
composer create-project codeigniter4/appstarter ci4-esqueleto

# 2) Copia lo que NO tenemos (esto es igual en cualquier proyecto CI4, no lo tocamos):
#    public/, writable/, tests/, spark, preload.php, phpunit.xml.dist,
#    y TODOS los app/Config/*.php EXCEPTO Routes.php y Filters.php (esos ya los traemos nosotros).
cp -r ci4-esqueleto/public ci4-esqueleto/writable ci4-esqueleto/tests ci4-esqueleto/spark \
      ci4-esqueleto/preload.php ci4-esqueleto/phpunit.xml.dist ./backend/ 2>/dev/null

# copia los config de fabrica que nosotros no reescribimos (no borres los tuyos: Routes.php y Filters.php)
cp -n ci4-esqueleto/app/Config/*.php ./backend/app/Config/

# 3) Instala dependencias (framework + firebase/php-jwt) usando NUESTRO composer.json
cd backend
composer install

# 4) Copia el .env y ajustalo (DB, JWT_SECRET, baseURL, etc)
cp .env.example .env
```

> Nota: el paso 2 es un `cp` "no overwrite" (`-n`) para no pisar `Routes.php` ni `Filters.php`, que son
> los dos archivos de configuracion que ya trae este repo. Todo lo demas (App.php, Database.php,
> Security.php, etc.) puede quedarse tal cual lo genera Composer, porque **toda la configuracion de
> este proyecto se hace por variables de entorno en `.env`** (ver seccion 6), no editando esos PHP.

### 2.1 Crear la base de datos y correr migraciones

```bash
# crea la base vacia (o usa database/schema.sql como referencia si prefieres crearla a mano)
mysql -u root -p -e "CREATE DATABASE biometrico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php spark migrate
php spark db:seed DatabaseSeeder
```

Esto crea las tablas y un usuario administrador:

```
email:    admin@cheil.local
password: Admin12345!
```

**Cambia esta contraseña en cuanto entres al panel.**

### 2.2 Levantar el servidor

```bash
php spark serve
# API disponible en http://localhost:8080
```

## 3. Estructura relevante

```
app/
  Config/Routes.php          -> todas las rutas (api/v1/* y iclock/*)
  Config/Filters.php         -> registra jwtAuth y cors
  Controllers/Api/           -> Auth, Employee, Attendance, Device, Dashboard, Report
  Controllers/Zkteco/        -> AdmsController (protocolo ADMS del checador)
  Models/                    -> un modelo por tabla
  Libraries/Jwt.php          -> emitir/validar JWT
  Libraries/AuthContext.php  -> usuario autenticado del request actual
  Filters/JwtAuthFilter.php  -> protege rutas api/v1/* (excepto login)
  Filters/CorsFilter.php     -> habilita CORS para el web panel / app
  Database/Migrations/       -> roles, employees, users, devices, attendance_records, employee_face_embeddings,
                               zkteco_raw_logs, employee_enroll_tokens
  Database/Seeds/            -> RoleSeeder, AdminUserSeeder
database/schema.sql          -> SQL de referencia (documentacion, no se ejecuta directo)
```

## 4. Autenticacion

Login unico para panel web y app movil (`POST /api/v1/auth/login`), diferenciado por rol:

- `admin` / `supervisor`: usuarios del web panel (tabla `users`, sin `employee_id` obligatorio)
- `employee`: cuenta ligada a un empleado (`users.employee_id`), la usa la app movil para poder
  mandar `POST /api/v1/attendance/checkin`

El login regresa un JWT que se manda como `Authorization: Bearer <token>` en cada request protegido.

## 5. Reglas de negocio implementadas

- **El "dia laboral" NO es medianoche-medianoche**: va de las 5:00am a las 4:59:59am
  del dia siguiente (ver `AttendanceRecordModel::workDateSql()`/`isTodaySql()`). Un
  turno que sale pasada la medianoche (ej. entro 23:00, salio 02:00) sigue contando
  como el mismo dia laboral. Esto aplica a TODO: primer/ultimo registro del dia
  (entrada/salida), el mapa de "hoy", el dashboard, y los filtros de fecha de
  Asistencia/Reportes.
- **Zonas geograficas permitidas** (`geo_zones`, `GeoZoneController`): el admin define
  zonas (nombre, coordenadas, radio en metros) desde el panel. Si hay AL MENOS UNA zona
  activa, cualquier checada (kiosko, app movil, o `attendance/checkin`) cuyas
  coordenadas no caigan dentro de NINGUNA zona activa se rechaza con 422. Sin zonas
  activas configuradas, no se restringe nada (comportamiento previo intacto). Ver
  `GeoZoneModel::isWithinAnyActiveZone()` (formula Haversine).
- **Home Office personal + Movilidad sin restriccion** (ambos sobre `employees`, via
  `GeoZoneModel::isCheckinAllowedForEmployee()`): una checada de `mobile/checkin` o
  `attendance/checkin` es valida si pasa CUALQUIERA de estas tres condiciones -
  (1) el empleado tiene `checkin_unrestricted=1` (modulo "Movilidad" del panel, para
  quien visita clientes: checa desde cualquier lado), (2) cae dentro de alguna zona
  geografica general activa, o (3) cae dentro de los `home_radius_meters` (100m por
  default) de su Home Office personal (`home_lat`/`home_lng`). El Home Office lo fija
  EL MISMO empleado desde la app (`POST /api/v1/mobile/home-location`, requiere
  verificacion facial contra su rostro ya enrolado) y queda bloqueado
  (`home_location_locked=1`) hasta que un admin lo desbloquea
  (`POST /api/v1/employees/{id}/home-location/unlock`) porque el empleado se mudo.
  El kiosko (`KioskController`) NO usa esta regla combinada, sigue validando solo
  contra zonas generales (es una pantalla compartida de oficina, no tiene sentido un
  Home Office ahi).
- **Cada checada es un registro independiente** en `attendance_records` (source `mobile_app` o
  `zkteco_device`), con su hora y coordenadas. Un empleado puede tener N registros al dia (visita a
  cliente en Polanco a las 10am, otro cliente en Pedregal a la 1pm, etc).
- **Entrada = primer registro del dia** (`MIN(recorded_at)`), **salida = ultimo registro del dia**
  (`MAX(recorded_at)`). No se guarda un campo fijo "es entrada/salida": se calcula siempre al leer,
  para que quede correcto sin importar el orden en que lleguen los datos (ej. el checador manda
  registros en lote). Ver `AttendanceRecordModel::dailySummary()` y `::dayDetail()`.
- **Checkin de la app movil** (`POST /api/v1/attendance/checkin`) guarda latitud/longitud, foto y,
  opcionalmente, hace una segunda verificacion server-side comparando el descriptor facial recibido
  contra el guardado en `employee_face_embeddings` (distancia euclidiana, umbral `FACE_MATCH_THRESHOLD`).
  El "match" real (cara detectada = cara enrolada) se calcula en la app con face-api.js/TensorFlow.js;
  este paso en el backend es una validacion extra, no la unica.
- **Checador ZKTeco** empuja sus checadas via ADMS a `/iclock/cdata`; el backend las mapea a
  `attendance_records` con `source_type = zkteco_device`, usando `employees.zkteco_pin` para saber a
  que empleado pertenece cada PIN.
- **Registro biometrico del web panel (kiosko)**: `POST /api/v1/kiosk/lookup` busca al empleado por
  numero/CURP/RFC (publico, sin JWT) y regresa solo datos de confirmacion visual (nombre, foto) -
  NUNCA el descriptor facial. `POST /api/v1/kiosk/checkin` recibe el descriptor capturado por la
  camara y aqui SI es obligatorio el match contra `employee_face_embeddings` (a diferencia del
  checkin de la app movil, donde es una verificacion extra): sin match no hay checada, porque no hay
  ninguna otra credencial de por medio. Guarda `source_type = web_kiosk`. Como no hay password,
  considera poner esta pantalla en una red/compu de confianza y, si se expone a internet, agregar
  rate limiting.
- **Carga masiva de empleados (XLSX)**: `POST /api/v1/employees/import` (panel, JWT) recibe un
  `.xlsx`/`.xls` (mismo orden de columnas que `GET /api/v1/employees/import/template`) y hace
  **upsert por `employee_number`**: si ya existe, actualiza sus datos; si no, lo da de alta. Cada fila
  se procesa por separado (un renglon con datos invalidos no tumba el resto de la carga) y se regresa
  el detalle fila por fila (`created`/`updated`/`error`). Usa `phpoffice/phpspreadsheet` (ver
  `EmployeeImportController`). Las fechas se leen del **valor crudo** de la celda (no del texto
  formateado) para no depender de si Excel formatea como `mm-dd-yy`/`dd-mm-yy`/etc. -- evita fechas
  invertidas.
- **Carga masiva de fotos de perfil**: `POST /api/v1/employees/photos/bulk-import` (panel, JWT,
  multipart con varios archivos en el campo `photos[]`) permite arrastrar de un jalon todas las fotos
  ya nombradas `{numero_empleado}.jpg`/`.png`. El backend usa el NOMBRE del archivo (sin extension)
  para encontrar al empleado y actualiza `photo_path`, borrando la foto anterior del disco. Cada
  archivo se procesa por separado y se regresa detalle por archivo. Ver
  `EmployeeController::bulkImportPhotos()`. **Importante:** esto solo pone la foto de perfil que se ve
  en el panel -- NO enrola el rostro para checar (eso sigue siendo `POST /employees/{id}/face`, que
  requiere el descriptor de 128 numeros calculado en vivo por face-api.js desde la camara).
- **Auto-enrolamiento por liga temporal (72h, un solo uso)**: en vez de darle acceso al panel a cada
  empleado (o que tengan que pedirle a TI que se los enrole), un admin genera una liga desde
  Empleados (`POST /api/v1/employees/{id}/enroll-token`, JWT) y se la manda por WhatsApp/correo. El
  empleado la abre en `/enrolar/{token}` **sin necesitar cuenta**, confirma que es el, captura su
  rostro (`POST /api/v1/enroll/{token}/save`) y luego se le pide una **segunda captura en vivo** que
  se compara contra la que se acaba de guardar (`POST /api/v1/enroll/{token}/verify`, distancia
  euclidiana contra `FACE_MATCH_THRESHOLD`). Solo si la verificacion pasa se marca el token como usado
  (`employee_enroll_tokens.used_at`) - esto evita que alguien enrole una foto de mala calidad o de otra
  persona sin darse cuenta. Ver `EnrollTokenController` y `EmployeeEnrollTokenModel`.
- **Auto-enrolamiento DESDE la app movil (sin que un admin genere nada)**: cuando un empleado se
  identifica por primera vez en la app (`POST /api/v1/mobile/lookup`) y no tiene rostro enrolado, el
  backend genera (o reusa, via `EmployeeEnrollTokenModel::findOrCreateForEmployee()`) el mismo tipo de
  token de 72h de arriba y lo regresa en la respuesta como `enroll_token`. La app entonces llama
  directo a los endpoints publicos `/api/v1/enroll/{token}/save` y `/api/v1/enroll/{token}/verify` -
  mismo flujo de captura + segunda verificacion en vivo que la liga web, sin duplicar logica en el
  backend. Si el empleado ya tiene rostro, `enroll_token` viene `null` y la app pasa derecho a la
  checada normal.
- **Departamento obligatorio (catalogo `departments`)**: dar de alta/editar un empleado ahora
  exige `department_id` (FK a `departments`, ver `EmployeeModel`). El cliente alimenta el
  catalogo el mismo desde el panel (`GET/POST/PUT/DELETE /api/v1/departments`,
  `DepartmentController`), viene sembrado con los 14 departamentos iniciales (Retail Center,
  Creative, Client Service, Account Service, Finance, Project Manager, Digital, FFM Center,
  Human Resources, Mexico, Planning, General Services, New Business, Corporate Service). El
  viejo campo de texto libre `employees.department` se deja (deprecado, solo historial); los
  empleados que ya existian quedan con `department_id` en `NULL` hasta que RH se los asigne uno
  por uno (no hay auto-match automatico contra el texto viejo). La baja de un departamento es
  logica (`is_active=0`), nunca se borra, para no romper referencias historicas. La carga masiva
  por XLSX (`EmployeeImportController`) resuelve la columna "departamento" (texto) contra este
  catalogo por nombre; si no matchea ninguno, esa fila falla con un mensaje claro en vez de
  crear departamentos nuevos por typos.
- **Retardo**: la entrada (primer registro del dia) se espera a las 9:00am con 15 minutos de
  tolerancia (constantes `EXPECTED_ENTRADA_TIME`/`LATE_TOLERANCE_MINUTES` en
  `AttendanceRecordModel`) - una entrada despues de las 9:15:00am se marca `is_late=true`. Esto
  es independiente de la ventana del "dia laboral" (5am-4:59am): solo importa la hora de reloj
  de la entrada, no en que dia laboral cae. El flag `is_late` viene expuesto en
  `AttendanceRecordModel::search()`/`dailySummary()`/`dayDetail()` y por lo tanto en
  `/api/v1/attendance`, `/api/v1/reports/daily` (+ su export CSV) y `/api/v1/reports/payroll-export`
  (columna extra `Late`, sin romper el formato de las primeras 6 columnas que espera nomina).
- **Incidencias** (`incidents`/`incident_types`, `IncidentController`/`IncidentTypeController`):
  registro **independiente**, NO ligado a una checada/attendance_record especifica (mucho
  trafico, siniestro en carretera, clima, falla de transporte publico, etc). El empleado
  afectado y la evidencia (foto/documento, se guarda en `writable/uploads/incidents/`, servida
  via `FilesController`) son **opcionales**; el tipo (catalogo `incident_types`, alimentable
  desde el mismo modulo de Incidencias en el panel) y la fecha son obligatorios. Soporta
  filtros por empleado, departamento (via el empleado), tipo y rango de fechas
  (`GET /api/v1/incidents`).
- **Filtro/agrupacion por departamento en reportes y asistencia**: `GET /api/v1/attendance`,
  `GET /api/v1/reports/daily` (+ export CSV) y `GET /api/v1/reports/payroll-export` aceptan
  `department_id` ademas de `employee_id`, para ver/exportar la asistencia de un departamento
  completo en vez de un empleado a la vez.

## 6. Variables de entorno (`.env`)

| Variable | Para que sirve |
|---|---|
| `database.default.*` | Conexion a MySQL |
| `app.baseURL` | URL publica del backend |
| `JWT_SECRET` | Firma de los tokens, ponla larga y aleatoria en produccion |
| `JWT_TTL_SECONDS` | Vigencia del token (default 12h) |
| `FACE_MATCH_THRESHOLD` | Que tan estricta es la doble verificacion facial server-side |
| `CORS_ALLOWED_ORIGINS` | Dominios permitidos a llamar la API (el dominio del web panel Vue) |

## 7. Endpoints principales

| Metodo | Ruta | Quien lo usa |
|---|---|---|
| POST | `/api/v1/auth/login` | Panel y App |
| GET | `/api/v1/auth/me` | Panel y App |
| GET/POST/PUT/DELETE | `/api/v1/employees` | Panel (alta/edicion de empleados) |
| POST | `/api/v1/employees/{id}/face` | Panel (enrolar foto/rostro de referencia) |
| POST | `/api/v1/employees/{id}/enroll-token` | Panel (genera liga de 72h de auto-enrolamiento) |
| GET | `/api/v1/employees/import/template` | Panel (descarga plantilla .xlsx) |
| POST | `/api/v1/employees/import` | Panel (carga masiva de empleados, upsert por numero) |
| POST | `/api/v1/employees/photos/bulk-import` | Panel (carga masiva de fotos, match por nombre de archivo) |
| GET | `/api/v1/enroll/{token}` | Publico, sin JWT (valida la liga y regresa datos del empleado) |
| POST | `/api/v1/enroll/{token}/save` | Publico, sin JWT (guarda la primera captura de rostro) |
| POST | `/api/v1/enroll/{token}/verify` | Publico, sin JWT (segunda captura: verifica y marca la liga usada) |
| GET/POST/PUT/DELETE | `/api/v1/devices` | Panel (administrar checadores) |
| GET | `/api/v1/attendance` | Panel (listado con filtros) |
| POST | `/api/v1/attendance/checkin` | App movil (registrar checada) |
| GET | `/api/v1/attendance/mine` | App movil (mi historial) |
| GET | `/api/v1/reports/daily` | Panel (reporteador: por empleado o todos) |
| GET | `/api/v1/reports/daily/export` | Panel (descarga CSV) |
| GET | `/api/v1/reports/payroll-export` | Panel (descarga .xlsx para nomina externa: Employee ID/Name/Work date/Year&Date/Time In/Time Out) |
| GET/POST/PUT/DELETE | `/api/v1/geo-zones` | Panel (administrar zonas geograficas permitidas) |
| POST | `/api/v1/mobile/home-location` | Publico, sin JWT (empleado fija su Home Office: verificacion facial + GPS) |
| POST | `/api/v1/employees/{id}/home-location/unlock` | Panel (desbloquea el Home Office de un empleado que se mudo) |
| GET/POST/PUT/DELETE | `/api/v1/departments` | Panel (catalogo de departamentos, obligatorio en empleados) |
| GET/POST/PUT/DELETE | `/api/v1/incident-types` | Panel (catalogo de tipos de incidencia) |
| GET/POST/PUT/DELETE | `/api/v1/incidents` | Panel (registro de incidencias: mucho trafico, siniestro, etc) |
| GET | `/api/v1/reports/day-detail/{employeeId}/{fecha}` | Panel (entrada/checkpoints/salida de un dia) |
| GET | `/api/v1/attendance/today-map` | Panel (mapa en vivo: donde esta cada quien hoy) |
| GET | `/api/v1/dashboard/summary` | Panel (dashboard) |
| GET | `/uploads/{folder}/{archivo}` | Fotos de empleados / selfies (sin JWT, ver `FilesController`) |
| POST | `/api/v1/kiosk/lookup` | Kiosko del panel (buscar empleado por numero/CURP/RFC, sin JWT) |
| POST | `/api/v1/kiosk/checkin` | Kiosko del panel (checar con match facial obligatorio, sin JWT) |
| GET/POST | `/iclock/cdata` | Checador ZKTeco (ADMS, sin JWT) |
| GET | `/iclock/getrequest` | Checador ZKTeco |
| POST | `/iclock/devicecmd` | Checador ZKTeco |

## 8. Configurar el checador ZKTeco (entrada = huella, salida = facial)

Vas a usar **dos** dispositivos, ambos apuntando a este mismo backend:

- **Entrada**: checador de huella que ellos te dan (desbloquea la puerta imantada).
- **Salida**: el facial TCP/IP tipo "10000 caras" (ZKTeco, serie SpeedFace/iClock/MB360) que
  recomendamos, tambien desbloquea la puerta.

En AMBOS, en el menu del dispositivo (`COMM.` -> `Cloud Server`/`ADMS Server` segun el modelo):

1. **Enable ADMS / Cloud Server**: activado
2. **Server Address**: IP o dominio publico de este backend
3. **Server Port**: el puerto donde corre el backend (usa 443 con HTTPS detras de un proxy en produccion)
4. **Enable Proxy Server**: apagado
5. **Enable Domain Name**: activalo si usas dominio en vez de IP

Con eso el dispositivo empieza a llamar solo a `/iclock/cdata`, `/iclock/getrequest` y
`/iclock/devicecmd` de este backend, sin que tengas que ir a jalar nada manualmente.

**Importante:** cada empleado que vaya a usar los checadores fisicos debe estar enrolado en el
dispositivo con un PIN, y ese mismo PIN debe guardarse en `employees.zkteco_pin` desde el panel (o
directo en la base) para que el backend pueda relacionar la checada con el empleado correcto.

En produccion, restringe el acceso a `/iclock/*` por firewall/VPN a la red donde viven los
checadores (esas rutas no llevan JWT porque el dispositivo no puede mandar tokens).

## 9. Siguientes pasos

- Web panel (Vue): dashboard, CRUD de empleados con enrolamiento facial (face-api.js), reportes.
- App movil (Ionic + Angular): captura de rostro + geolocalizacion, checkin, historial propio.
