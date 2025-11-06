-- Base de datos para Sistema de Visitas Técnicas

CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  supervisor_id INT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
  FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_email (email),
  INDEX idx_role (role_id),
  INDEX idx_supervisor (supervisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  address VARCHAR(255),
  phone VARCHAR(50),
  email VARCHAR(255),
  lat DECIMAL(10,7),
  lng DECIMAL(10,7),
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_name (name),
  INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  supervisor_id INT NOT NULL,
  technician_id INT NOT NULL,
  scheduled_date DATE NOT NULL,
  scheduled_time TIME,
  status ENUM('scheduled','in-progress','completed','cancelled') DEFAULT 'scheduled',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (technician_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_client (client_id),
  INDEX idx_technician (technician_id),
  INDEX idx_supervisor (supervisor_id),
  INDEX idx_date (scheduled_date),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visit_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL,
  event_type ENUM('ingreso','egreso','nota') NOT NULL,
  event_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  lat DECIMAL(10,7),
  lng DECIMAL(10,7),
  note TEXT,
  FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
  INDEX idx_visit (visit_id),
  INDEX idx_type (event_type),
  INDEX idx_time (event_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name) VALUES 
  ('Administrador'),
  ('Supervisor'),
  ('Técnico')
ON DUPLICATE KEY UPDATE name=name;

INSERT INTO users (name, email, password_hash, role_id, active) 
VALUES ('Administrador', 'admin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1)
ON DUPLICATE KEY UPDATE name=name;

INSERT INTO users (name, email, password_hash, role_id, active) 
VALUES ('Juan Supervisor', 'supervisor@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1)
ON DUPLICATE KEY UPDATE name=name;

INSERT INTO users (name, email, password_hash, role_id, supervisor_id, active) 
VALUES ('Carlos Técnico', 'tecnico@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 2, 1)
ON DUPLICATE KEY UPDATE name=name;

INSERT INTO clients (name, address, phone, email, lat, lng, notes, created_by) 
VALUES 
  ('Empresa XYZ S.A.', 'Av. Reforma 10-00 Zona 10, Guatemala', '2345-6789', 'contacto@empresaxyz.com', 14.593890, -90.515260, 'Cliente corporativo', 1),
  ('TecnoServicios GT', '12 Calle 3-45 Zona 1, Guatemala', '2234-5678', 'info@tecnoservicios.com', 14.628434, -90.522713, 'Mantenimiento mensual', 2)
ON DUPLICATE KEY UPDATE name=name;

OPTIMIZE TABLE roles, users, clients, visits, visit_events;