<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

// -----------------------------------------------------------------
// Salud del servicio
// -----------------------------------------------------------------
$routes->get('/', static function () {
    return service('response')->setJSON([
        'status'  => 'success',
        'message' => 'Backend registro biometrico - CodeIgniter 4',
    ]);
});
$routes->get('healthz', static function () {
    return service('response')->setJSON(['status' => 'ok']);
});

// Fotos de empleados / selfies de checadas (guardadas fuera de public/ a proposito)
$routes->get('uploads/(:segment)/(:segment)', 'FilesController::show/$1/$2');

// -----------------------------------------------------------------
// API publica (sin JWT): login
// -----------------------------------------------------------------
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api', 'filter' => 'cors'], static function ($routes) {
    // Preflight CORS: el navegador manda OPTIONS antes de cualquier POST/PUT/DELETE con
    // JSON. Sin esta ruta, CI4 regresa 404 antes de que el filtro 'cors' ponga los headers,
    // y el navegador bloquea la llamada real aunque CORS_ALLOWED_ORIGINS este bien puesto.
    $routes->options('(:any)', static function () {
        return service('response')->setStatusCode(204);
    });

    $routes->post('auth/login', 'AuthController::login');

    // Registro biometrico desde el web panel (kiosko): el empleado se identifica con
    // numero/CURP/RFC (sin password) y la CAMARA confirma su identidad, por eso va sin JWT.
    $routes->post('kiosk/lookup', 'KioskController::lookup');
    $routes->post('kiosk/checkin', 'KioskController::checkin');

    // App movil (Ionic/Capacitor, telefono propio del empleado): mismo esquema que el
    // kiosko (numero/CURP/RFC + match facial obligatorio), pero guarda source_type=mobile_app.
    $routes->post('mobile/lookup', 'MobileController::lookup');
    $routes->post('mobile/checkin', 'MobileController::checkin');
    // El empleado configura su Home Office personal el mismo (verificacion facial + GPS).
    $routes->post('mobile/home-location', 'MobileController::homeLocation');

    // Auto-enrolamiento por liga temporal (72h, un solo uso): el empleado entra sin
    // login, confirma su identidad, captura su rostro y lo vuelve a capturar para
    // verificarlo. Va sin JWT porque el empleado no tiene cuenta en el panel.
    $routes->get('enroll/(:any)', 'EnrollTokenController::lookup/$1');
    $routes->post('enroll/(:any)/save', 'EnrollTokenController::save/$1');
    $routes->post('enroll/(:any)/verify', 'EnrollTokenController::verify/$1');

    // -----------------------------------------------------------------
    // API protegida con JWT (web panel + app movil)
    // -----------------------------------------------------------------
    $routes->group('', ['filter' => 'jwtAuth'], static function ($routes) {
        $routes->get('auth/me', 'AuthController::me');

        // Empleados (panel: admin/supervisor)
        $routes->get('employees', 'EmployeeController::index');
        $routes->get('employees/(:num)', 'EmployeeController::show/$1');
        $routes->post('employees', 'EmployeeController::create');
        $routes->put('employees/(:num)', 'EmployeeController::update/$1');
        $routes->post('employees/(:num)', 'EmployeeController::update/$1'); // soporte multipart desde el panel
        $routes->delete('employees/(:num)', 'EmployeeController::delete/$1');
        $routes->post('employees/(:num)/face', 'EmployeeController::saveFace/$1');
        $routes->post('employees/(:num)/enroll-token', 'EnrollTokenController::generate/$1');
        // Desbloquea la ubicacion Home Office del empleado (se mudo) para que la pueda
        // volver a capturar el mismo desde la app.
        $routes->post('employees/(:num)/home-location/unlock', 'EmployeeController::unlockHomeLocation/$1');

        // Carga masiva de empleados por XLSX (upsert por numero_empleado)
        $routes->get('employees/import/template', 'EmployeeImportController::template');
        $routes->post('employees/import', 'EmployeeImportController::import');

        // Dispositivos ZKTeco (panel: admin)
        $routes->get('devices', 'DeviceController::index');
        $routes->get('devices/(:num)', 'DeviceController::show/$1');
        $routes->post('devices', 'DeviceController::create');
        $routes->put('devices/(:num)', 'DeviceController::update/$1');
        $routes->delete('devices/(:num)', 'DeviceController::delete/$1');

        // Zonas geograficas permitidas para checar (panel: admin)
        $routes->get('geo-zones', 'GeoZoneController::index');
        $routes->get('geo-zones/(:num)', 'GeoZoneController::show/$1');
        $routes->post('geo-zones', 'GeoZoneController::create');
        $routes->put('geo-zones/(:num)', 'GeoZoneController::update/$1');
        $routes->delete('geo-zones/(:num)', 'GeoZoneController::delete/$1');

        // Catalogo de departamentos (panel: admin/RH lo va alimentando)
        $routes->get('departments', 'DepartmentController::index');
        $routes->get('departments/(:num)', 'DepartmentController::show/$1');
        $routes->post('departments', 'DepartmentController::create');
        $routes->put('departments/(:num)', 'DepartmentController::update/$1');
        $routes->delete('departments/(:num)', 'DepartmentController::delete/$1');

        // Catalogo de tipos de incidencia (panel: admin)
        $routes->get('incident-types', 'IncidentTypeController::index');
        $routes->post('incident-types', 'IncidentTypeController::create');
        $routes->put('incident-types/(:num)', 'IncidentTypeController::update/$1');
        $routes->delete('incident-types/(:num)', 'IncidentTypeController::delete/$1');

        // Incidencias (mucho trafico, siniestro en carretera, etc) -- registro
        // independiente, no ligado a una checada especifica
        $routes->get('incidents', 'IncidentController::index');
        $routes->get('incidents/(:num)', 'IncidentController::show/$1');
        $routes->post('incidents', 'IncidentController::create');
        $routes->post('incidents/(:num)', 'IncidentController::update/$1'); // soporte multipart (evidencia)
        $routes->put('incidents/(:num)', 'IncidentController::update/$1');
        $routes->delete('incidents/(:num)', 'IncidentController::delete/$1');

        // Asistencia
        $routes->get('attendance', 'AttendanceController::index');          // panel: listado con filtros
        $routes->post('attendance/checkin', 'AttendanceController::checkin'); // app movil: registrar checada
        $routes->get('attendance/mine', 'AttendanceController::mine');        // app movil: mi historial
        $routes->get('attendance/day-detail/(:num)/(:any)', 'AttendanceController::dayDetail/$1/$2');
        $routes->get('attendance/today-map', 'AttendanceController::todayMap');   // panel: mapa en vivo

        // Reportes y dashboard (panel)
        $routes->get('reports/daily', 'ReportController::daily');
        $routes->get('reports/daily/export', 'ReportController::exportDaily');
        $routes->get('reports/payroll-export', 'ReportController::payrollExport');
        $routes->get('reports/day-detail/(:num)/(:any)', 'ReportController::dayDetail/$1/$2');
        $routes->get('dashboard/summary', 'DashboardController::summary');
    });
});

// -----------------------------------------------------------------
// Protocolo ADMS de los checadores ZKTeco (SIN JWT, el dispositivo no manda tokens).
// Restringir por firewall/VPN a la red del checador en produccion.
// -----------------------------------------------------------------
$routes->group('iclock', ['namespace' => 'App\Controllers\Zkteco'], static function ($routes) {
    $routes->get('cdata', 'AdmsController::cdata');
    $routes->post('cdata', 'AdmsController::cdata');
    $routes->get('getrequest', 'AdmsController::getrequest');
    $routes->post('devicecmd', 'AdmsController::devicecmd');
});
