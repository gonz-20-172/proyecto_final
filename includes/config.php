<?php
// Configuración del sistema
define('APP_NAME', 'Sistema de Visitas');
define('APP_VERSION', '1.0.0');

// Modo de almacenamiento: 'file' o 'database'
define('STORAGE_MODE', 'file'); // Cambia a 'database' cuando esté lista la BD

// Configuración de la base de datos (para cuando esté lista)
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'sistema_visitas');
define('DB_USER', 'root');
define('DB_PASS', '');

// Zona horaria
date_default_timezone_set('America/Guatemala');

// Iniciar sesión
session_start();
