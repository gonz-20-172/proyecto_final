-- Base de datos para Sistema de Visitas
-- Ejecutar este archivo cuando quieras usar base de datos en lugar de archivos JSON

CREATE DATABASE IF NOT EXISTS sistema_visitas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE sistema_visitas;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de visitas
CREATE TABLE IF NOT EXISTS visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(255) NOT NULL,
    visitor_id VARCHAR(50) NOT NULL,
    company VARCHAR(255),
    person_to_visit VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    entry_time DATETIME NOT NULL,
    exit_time DATETIME,
    status ENUM('active', 'completed') DEFAULT 'active',
    registered_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (registered_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_entry_time (entry_time),
    INDEX idx_visitor_id (visitor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de configuración del sistema (opcional)
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuraciones iniciales
INSERT INTO system_config (config_key, config_value, description) VALUES
('app_name', 'Sistema de Visitas', 'Nombre de la aplicación'),
('max_visit_hours', '12', 'Máximo de horas para una visita'),
('require_photo', '0', 'Requerir foto del visitante (0=No, 1=Sí)');

-- Vista para reportes (opcional)
CREATE OR REPLACE VIEW visits_report AS
SELECT 
    v.id,
    v.visitor_name,
    v.visitor_id,
    v.company,
    v.person_to_visit,
    v.reason,
    v.entry_time,
    v.exit_time,
    v.status,
    u.name as registered_by_name,
    u.email as registered_by_email,
    TIMESTAMPDIFF(MINUTE, v.entry_time, COALESCE(v.exit_time, NOW())) as duration_minutes,
    DATE(v.entry_time) as visit_date
FROM visits v
LEFT JOIN users u ON v.registered_by = u.id;

-- Ejemplos de consultas útiles (comentadas)

-- Visitas activas
-- SELECT * FROM visits WHERE status = 'active';

-- Visitas de hoy
-- SELECT * FROM visits WHERE DATE(entry_time) = CURDATE();

-- Visitas por rango de fechas
-- SELECT * FROM visits WHERE entry_time BETWEEN '2025-01-01' AND '2025-12-31';

-- Estadísticas por día
-- SELECT 
--     DATE(entry_time) as fecha,
--     COUNT(*) as total_visitas,
--     AVG(TIMESTAMPDIFF(MINUTE, entry_time, exit_time)) as promedio_minutos
-- FROM visits 
-- WHERE status = 'completed'
-- GROUP BY DATE(entry_time)
-- ORDER BY fecha DESC;

-- Top visitantes
-- SELECT 
--     visitor_name,
--     COUNT(*) as num_visitas
-- FROM visits
-- GROUP BY visitor_name
-- ORDER BY num_visitas DESC
-- LIMIT 10;
