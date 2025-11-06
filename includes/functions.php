<?php
/**
 * Funciones auxiliares del sistema
 */
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

// ============================================
// FUNCIONES DE RESPUESTA JSON
// ============================================

/**
 * Enviar respuesta JSON
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Enviar respuesta de error JSON
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'error' => $message
    ], $statusCode);
}

/**
 * Enviar respuesta de éxito JSON
 */
function jsonSuccess($data = null, $message = '') {
    $response = ['success' => true];
    
    if ($message) {
        $response['message'] = $message;
    }
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    jsonResponse($response);
}

// ============================================
// FUNCIONES DE VALIDACIÓN Y SANITIZACIÓN
// ============================================

/**
 * Sanitizar entrada de texto
 */
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar formato de email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Verificar campos requeridos
 */
function checkRequiredFields($data, $requiredFields) {
    $missing = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    
    return $missing;
}

// ============================================
// FUNCIONES DE REQUEST
// ============================================

/**
 * Obtener método de la petición
 */
function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Verificar si el método de la petición coincide
 */
function isMethod($method) {
    return strtoupper(getRequestMethod()) === strtoupper($method);
}

/**
 * Obtener cuerpo de la petición JSON
 */
function getRequestBody() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?: [];
}

// ============================================
// FUNCIONES DE CONFIGURACIÓN
// ============================================

/**
 * Obtener valor de configuración
 */
function config($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

/**
 * Obtener URL base de la aplicación
 */
function getBaseUrl() {
    return config('APP_URL', 'http://localhost');
}

/**
 * Obtener API Key de Google Maps
 */
function getGoogleMapsApiKey() {
    $configFile = __DIR__ . '/../config/maps.php';
    if (file_exists($configFile)) {
        $config = require $configFile;
        return $config['google_maps_api_key'] ?? 'AIzaSyBjH8L3gp5y6-h36Ns2EoJC-7bqtskFw5w';
    }
    return 'AIzaSyBjH8L3gp5y6-h36Ns2EoJC-7bqtskFw5w';
}

// ============================================
// FUNCIONES DE FORMATO
// ============================================

/**
 * Formatear fecha
 */
function formatDate($date, $format = 'd/m/Y H:i') {
    if (empty($date)) {
        return '';
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return '';
    }
    
    return date($format, $timestamp);
}

// ============================================
// FUNCIONES DE SEGURIDAD
// ============================================

/**
 * Hashear contraseña
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verificar contraseña
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ============================================
// FUNCIONES DE NAVEGACIÓN
// ============================================

/**
 * Redirigir a una URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

// ============================================
// FUNCIONES DE LOG
// ============================================

/**
 * Registrar mensaje en log
 */
function logMessage($message, $level = 'INFO') {
    $logFile = __DIR__ . '/../logs/app.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

// ============================================
// FUNCIONES DE EMAIL
// ============================================

/**
 * Obtener instancia configurada de PHPMailer
 */
function getMailer() {
    $mail = new PHPMailer(true);
    
    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host       = config('MAIL_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = config('MAIL_USER', 'visitastecnicassa@gmail.com');
        $mail->Password   = config('MAIL_PASS', 'wsdmdakgxhbxwbgs');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = config('MAIL_PORT', 587);
        
        // Configuración del remitente
        $mail->setFrom(
            config('MAIL_FROM', 'visitastecnicassa@gmail.com'), 
            config('MAIL_FROM_NAME', 'Sistema de Visitas')
        );
        $mail->CharSet = 'UTF-8';
        
        return $mail;
        
    } catch (Exception $e) {
        error_log("Error configurando PHPMailer: {$mail->ErrorInfo}");
        throw new Exception("Error al configurar el servicio de correo");
    }
}

// ============================================
// FUNCIONES DE REPORTES
// ============================================

/**
 * Generar reporte de visita (wrapper function)
 * Esta función obtiene los datos y genera el PDF
 * 
 * @param int $visitId ID de la visita
 * @param bool $forceRegenerate Forzar regeneración del PDF
 * @return array Resultado de la operación
 */
function generateVisitReport($visitId, $forceRegenerate = false) {
    try {
        // Obtener datos de la visita desde la base de datos
        $visit = dbQueryOne("
            SELECT 
                v.*,
                c.name as client_name,
                c.email as client_email,
                c.phone as client_phone,
                c.address as client_address,
                t.name as technician_name,
                t.email as technician_email,
                s.name as supervisor_name,
                s.email as supervisor_email,
                (SELECT event_time FROM visit_events WHERE visit_id = v.id AND event_type = 'ingreso' ORDER BY event_time DESC LIMIT 1) as ingreso_time,
                (SELECT event_time FROM visit_events WHERE visit_id = v.id AND event_type = 'egreso' ORDER BY event_time DESC LIMIT 1) as egreso_time
            FROM visits v
            INNER JOIN clients c ON v.client_id = c.id
            INNER JOIN users t ON v.technician_id = t.id
            INNER JOIN users s ON v.supervisor_id = s.id
            WHERE v.id = ?
        ", [$visitId]);
        
        if (!$visit) {
            return [
                'success' => false,
                'error' => 'Visita no encontrada'
            ];
        }
        
        if ($visit['status'] !== 'completed') {
            return [
                'success' => false,
                'error' => 'Solo se pueden generar reportes de visitas completadas'
            ];
        }
        
        // Obtener eventos
        $events = dbQuery("
            SELECT * FROM visit_events
            WHERE visit_id = ?
            ORDER BY event_time ASC
        ", [$visitId]);
        
        $visit['events'] = $events;
        
        // Verificar si ya existe el PDF
        $tempDir = __DIR__ . '/../temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $filename = 'report_visit_' . $visitId . '.pdf';
        $filepath = $tempDir . '/' . $filename;
        
        // Si ya existe y no se fuerza regeneración, retornar existente
        if (file_exists($filepath) && !$forceRegenerate) {
            return [
                'success' => true,
                'path' => $filepath
            ];
        }
        
        // Generar PDF usando la función de reports.php
        // La función generateVisitPDF debe estar disponible
        $pdfPath = generateVisitPDF($visit);
        
        if (!$pdfPath) {
            return [
                'success' => false,
                'error' => 'Error al generar PDF'
            ];
        }
        
        return [
            'success' => true,
            'path' => $pdfPath
        ];
        
    } catch (Exception $e) {
        error_log('Error en generateVisitReport: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error al generar reporte: ' . $e->getMessage()
        ];
    }
}
