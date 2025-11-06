<?php
// Cargar autoload de Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Cargar conexión a base de datos
require_once __DIR__ . '/../config/database.php';

// Cargar conexión a base de datos
/* require_once __DIR__ . '/../includes/db.php'; */

// Configurar zona horaria
date_default_timezone_set('America/Guatemala');

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Crear directorio de logs si no existe
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Cargar funciones auxiliares (base) - en includes/
require_once __DIR__ . '/functions.php';

// Cargar funciones de reportes PDF - en api/
require_once __DIR__ . '/../api/reports.php';

// Cargar funciones de email - en includes/
require_once __DIR__ . '/mail.php';

