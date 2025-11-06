-- db/schema.sql
CREATE DATABASE IF NOT EXISTS visitasdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE visitasdb;

CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255)
);

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  supervisor_id INT NULL,
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (supervisor_id) REFERENCES users(id)
);

-- Datos iniciales
INSERT INTO roles (name, description) VALUES 
  ('administrador', 'Acceso total al sistema'),
  ('supervisor', 'Gestiona técnicos y visitas'),
  ('tecnico', 'Ejecuta visitas asignadas');

INSERT INTO users (name, email, password_hash, role_id) VALUES 
  ('Admin', 'admin@sistema.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);
  -- password: "password"

  CREATE TABLE clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  address VARCHAR(255),
  phone VARCHAR(50),
  email VARCHAR(255),
  lat DECIMAL(10,7),
  lng DECIMAL(10,7),
  notes TEXT,
  active TINYINT(1) DEFAULT 1,
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  supervisor_id INT NOT NULL,
  technician_id INT NOT NULL,
  scheduled_date DATE NOT NULL,
  scheduled_start TIME,
  scheduled_end TIME,
  status ENUM('programada','en-curso','completada','cancelada') DEFAULT 'programada',
  notes TEXT,
  created_by INT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id),
  FOREIGN KEY (supervisor_id) REFERENCES users(id),
  FOREIGN KEY (technician_id) REFERENCES users(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE visit_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL,
  event_type ENUM('ingreso','egreso','nota') NOT NULL,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
  lat DECIMAL(10,7),
  lng DECIMAL(10,7),
  note TEXT,
  photo VARCHAR(255), -- Ruta foto (opcional)
  created_by INT,
  FOREIGN KEY (visit_id) REFERENCES visits(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);