-- =========================================================
-- Esquema de referencia - Sistema de Registro Biometrico
-- Motor: MySQL 8.0+ / MariaDB 10.6+
-- Este archivo es solo de LECTURA/REFERENCIA.
-- La fuente de verdad son las migraciones en app/Database/Migrations
-- (se crean/actualizan con: php spark migrate)
-- =========================================================

CREATE DATABASE IF NOT EXISTS biometrico CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE biometrico;

-- ---------------------------------------------------------
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(150) NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Catalogo de departamentos (Retail Center, Creative, Client Service, etc). El cliente
-- lo va alimentando desde el panel (modulo Departamentos). Reemplaza al viejo
-- employees.department (texto libre) -- ver employees.department_id abajo.
CREATE TABLE departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
CREATE TABLE employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_number VARCHAR(30) NOT NULL UNIQUE,
    zkteco_pin VARCHAR(30) NULL UNIQUE COMMENT 'PIN/ID con el que esta enrolado en el checador',
    first_name VARCHAR(100) NOT NULL,
    paternal_last_name VARCHAR(100) NOT NULL,
    maternal_last_name VARCHAR(100) NULL,
    curp VARCHAR(18) NULL UNIQUE,
    rfc VARCHAR(13) NULL UNIQUE,
    photo_path VARCHAR(255) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(20) NULL,
    position VARCHAR(100) NULL,
    department VARCHAR(100) NULL COMMENT 'DEPRECADO: texto libre viejo, ya no se usa en el panel, se deja por historial',
    department_id INT UNSIGNED NULL COMMENT 'Catalogo departments. Obligatorio en el panel al dar de alta/editar',
    hire_date DATE NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    -- Home Office personal: la fija el empleado desde la app (verificacion facial + GPS),
    -- ver MobileController::homeLocation(). Queda bloqueada hasta que un admin la desbloquee.
    home_lat DECIMAL(10,7) NULL,
    home_lng DECIMAL(10,7) NULL,
    home_radius_meters INT UNSIGNED NOT NULL DEFAULT 100,
    home_location_locked TINYINT(1) NOT NULL DEFAULT 0,
    home_location_set_at DATETIME NULL,
    -- Empleados que visitan clientes: pueden checar desde cualquier lugar (modulo "Movilidad")
    checkin_unrestricted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    KEY idx_status (status),
    KEY idx_department (department_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Catalogo de tipos de incidencia (Mucho trafico, Siniestro en carretera, etc). El
-- cliente lo alimenta desde el modulo de Incidencias en el panel.
CREATE TABLE incident_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Incidencias reportadas (independientes de una checada especifica): mucho trafico,
-- siniestro en carretera, etc. El empleado afectado y la evidencia son opcionales.
CREATE TABLE incidents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NULL,
    incident_type_id INT UNSIGNED NOT NULL,
    incident_date DATE NOT NULL,
    description TEXT NULL,
    evidence_path VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL COMMENT 'users.id de quien la capturo',
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_employee (employee_id),
    KEY idx_type (incident_type_id),
    KEY idx_date (incident_date),
    CONSTRAINT fk_incidents_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL,
    CONSTRAINT fk_incidents_type FOREIGN KEY (incident_type_id) REFERENCES incident_types(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_users_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
CREATE TABLE employee_face_embeddings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    descriptor TEXT NOT NULL COMMENT 'Vector 128 floats (face-api.js) en JSON',
    source ENUM('web_panel','mobile_app','enroll_link') NOT NULL DEFAULT 'web_panel',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    KEY idx_employee (employee_id),
    CONSTRAINT fk_embeddings_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Ligas temporales de auto-enrolamiento (72h, un solo uso): el admin genera una liga
-- para un empleado y se la manda por WhatsApp/correo en vez de darle acceso al panel.
CREATE TABLE employee_enroll_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_by INT UNSIGNED NULL COMMENT 'users.id del admin que genero la liga',
    created_at DATETIME NULL,
    KEY idx_employee (employee_id),
    CONSTRAINT fk_enroll_token_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Zonas geograficas permitidas para checar asistencia (ej. "Oficina Polanco" 50km,
-- "Sucursal Toluca" 500m). Si hay al menos una zona ACTIVA, una checada cuyas
-- coordenadas no caigan dentro de ninguna se rechaza (ver GeoZoneModel::isWithinAnyActiveZone()).
CREATE TABLE geo_zones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    address_label VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    radius_meters INT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_is_active (is_active)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
CREATE TABLE devices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    serial_number VARCHAR(50) NOT NULL UNIQUE COMMENT 'SN del checador ZKTeco',
    type ENUM('fingerprint_entry','face_exit','other') NOT NULL DEFAULT 'other',
    location_label VARCHAR(150) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    ip_address VARCHAR(45) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
CREATE TABLE attendance_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    source_type ENUM('mobile_app','zkteco_device','web_kiosk') NOT NULL,
    device_id INT UNSIGNED NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    accuracy_meters DECIMAL(8,2) NULL,
    location_label VARCHAR(150) NULL,
    photo_path VARCHAR(255) NULL,
    face_match_score DECIMAL(6,4) NULL,
    verify_mode VARCHAR(30) NULL,
    recorded_at DATETIME NOT NULL,
    raw_payload TEXT NULL,
    created_at DATETIME NULL,
    KEY idx_employee_time (employee_id, recorded_at),
    KEY idx_device (device_id),
    KEY idx_source (source_type),
    CONSTRAINT fk_att_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
CREATE TABLE zkteco_raw_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_serial VARCHAR(50) NOT NULL,
    table_name VARCHAR(30) NOT NULL,
    raw_line TEXT NOT NULL,
    line_hash CHAR(40) NOT NULL UNIQUE,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    attendance_record_id BIGINT UNSIGNED NULL,
    error_message VARCHAR(255) NULL,
    created_at DATETIME NULL,
    KEY idx_device_serial (device_serial),
    CONSTRAINT fk_rawlog_attendance FOREIGN KEY (attendance_record_id) REFERENCES attendance_records(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Datos base
INSERT INTO roles (name, description, created_at, updated_at) VALUES
 ('admin', 'Acceso total al panel', NOW(), NOW()),
 ('supervisor', 'Ve reportes y empleados, no administra usuarios', NOW(), NOW()),
 ('employee', 'Cuenta usada por la app movil para checar', NOW(), NOW());

-- Vista util: primer y ultimo registro del dia por empleado (entrada/salida)
CREATE OR REPLACE VIEW v_attendance_daily_summary AS
SELECT
    ar.employee_id,
    DATE(ar.recorded_at) AS work_date,
    MIN(ar.recorded_at) AS entrada,
    MAX(ar.recorded_at) AS salida,
    COUNT(*) AS total_checkpoints
FROM attendance_records ar
GROUP BY ar.employee_id, DATE(ar.recorded_at);
