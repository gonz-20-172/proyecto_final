<?php
/**
 * Funciones para generación de reportes PDF
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generar PDF de reporte de visita (acepta ID o array de datos)
 * @param int|array $visitData ID de la visita o array con datos completos
 * @return string|false Ruta del archivo PDF generado o false en caso de error
 */
function generateVisitPDF($visitData) {
    try {
        // Si recibimos un ID, obtener los datos
        if (is_numeric($visitData)) {
            $visitId = $visitData;
            
            // Obtener datos de la visita
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
                error_log("Visita #{$visitId} no encontrada");
                return false;
            }
            
            // Obtener eventos
            $events = dbQuery("
                SELECT * FROM visit_events
                WHERE visit_id = ?
                ORDER BY event_time ASC
            ", [$visitId]);
            
            $visit['events'] = $events;
        } else {
            // Ya recibimos el array completo
            $visit = $visitData;
        }
        
        // Obtener HTML del reporte
        $html = generateVisitReportHTML($visit);
        
        if (!$html) {
            error_log('Error: No se pudo generar el HTML del reporte');
            return false;
        }
        
        // Configurar Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Arial');
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Crear directorio temporal si no existe
        $tempDir = __DIR__ . '/../temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        // Guardar PDF
        $filename = 'report_visit_' . ($visit['id'] ?? 'unknown') . '_' . time() . '.pdf';
        $filepath = $tempDir . '/' . $filename;
        
        file_put_contents($filepath, $dompdf->output());
        
        return $filepath;
        
    } catch (Exception $e) {
        error_log('Error generando PDF: ' . $e->getMessage());
        return false;
    }
}

/**
 * Generar HTML del reporte de visita
 * @param array $visit Datos de la visita
 * @return string HTML del reporte
 */
function generateVisitReportHTML($visit) {
    // Validar que $visit sea un array
    if (!is_array($visit)) {
        error_log('Error: generateVisitReportHTML requiere un array, recibió: ' . gettype($visit));
        return false;
    }
    
    // Calcular duración
    $duration = calculateDuration($visit['ingreso_time'] ?? null, $visit['egreso_time'] ?? null);
    
    // Formatear fechas de forma segura
    $scheduledDate = isset($visit['scheduled_date']) ? formatDate($visit['scheduled_date'], 'd/m/Y') : 'N/A';
    $ingresoTime = !empty($visit['ingreso_time']) ? formatDate($visit['ingreso_time'], 'd/m/Y H:i') : 'N/A';
    $egresoTime = !empty($visit['egreso_time']) ? formatDate($visit['egreso_time'], 'd/m/Y H:i') : 'N/A';
    
    // Obtener datos de forma segura
    $visitId = $visit['id'] ?? 'N/A';
    $clientName = htmlspecialchars($visit['client_name'] ?? 'N/A');
    $clientAddress = htmlspecialchars($visit['client_address'] ?? 'N/A');
    $clientPhone = htmlspecialchars($visit['client_phone'] ?? 'N/A');
    $clientContact = htmlspecialchars($visit['client_contact'] ?? 'N/A');
    $technicianName = htmlspecialchars($visit['technician_name'] ?? 'N/A');
    $supervisorName = htmlspecialchars($visit['supervisor_name'] ?? 'N/A');
    $status = $visit['status'] ?? 'pending';
    $notes = $visit['notes'] ?? '';
    
    // Obtener eventos si existen
    $eventsHtml = '';
    if (!empty($visit['events']) && is_array($visit['events'])) {
        $eventsHtml = '<div class="section">
            <h3>Historial de Eventos</h3>
            <table class="events-table">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Fecha y Hora</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($visit['events'] as $event) {
            $eventType = ucfirst($event['event_type'] ?? 'N/A');
            $eventTime = isset($event['event_time']) ? formatDate($event['event_time'], 'd/m/Y H:i') : 'N/A';
            $eventNotes = htmlspecialchars($event['notes'] ?? '');
            
            $eventsHtml .= "<tr>
                <td><strong>{$eventType}</strong></td>
                <td>{$eventTime}</td>
                <td>{$eventNotes}</td>
            </tr>";
        }
        
        $eventsHtml .= '</tbody></table></div>';
    }
    
    $html = "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Reporte de Visita #{$visitId}</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Arial', sans-serif;
                line-height: 1.6;
                color: #333;
                background: #fff;
            }
            
            .container {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
                border-radius: 8px 8px 0 0;
                margin-bottom: 30px;
            }
            
            .header h1 {
                font-size: 28px;
                margin-bottom: 10px;
            }
            
            .header p {
                font-size: 14px;
                opacity: 0.9;
            }
            
            .section {
                background: #f8f9fa;
                padding: 20px;
                margin-bottom: 20px;
                border-radius: 8px;
                border-left: 4px solid #667eea;
            }
            
            .section h3 {
                color: #667eea;
                margin-bottom: 15px;
                font-size: 18px;
            }
            
            .info-row {
                display: flex;
                padding: 10px 0;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .info-row:last-child {
                border-bottom: none;
            }
            
            .info-label {
                font-weight: bold;
                width: 200px;
                color: #555;
            }
            
            .info-value {
                flex: 1;
                color: #333;
            }
            
            .events-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 15px;
            }
            
            .events-table th {
                background: #667eea;
                color: white;
                padding: 12px;
                text-align: left;
                font-weight: bold;
            }
            
            .events-table td {
                padding: 10px 12px;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .events-table tr:last-child td {
                border-bottom: none;
            }
            
            .footer {
                text-align: center;
                padding: 20px;
                color: #666;
                font-size: 12px;
                margin-top: 30px;
                border-top: 2px solid #e0e0e0;
            }
            
            .status {
                display: inline-block;
                padding: 5px 15px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: bold;
            }
            
            .status.completed {
                background: #d4edda;
                color: #155724;
            }
            
            .status.pending {
                background: #fff3cd;
                color: #856404;
            }
            
            .status.in_progress {
                background: #d1ecf1;
                color: #0c5460;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Reporte de Visita Técnica</h1>
                <p>Visita #{$visitId} - Generado el " . date('d/m/Y H:i') . "</p>
            </div>
            
            <div class='section'>
                <h3>Información del Cliente</h3>
                <div class='info-row'>
                    <div class='info-label'>Nombre:</div>
                    <div class='info-value'>{$clientName}</div>
                </div>
                <div class='info-row'>
                    <div class='info-label'>Dirección:</div>
                    <div class='info-value'>{$clientAddress}</div>
                </div>
                <div class='info-row'>
                    <div class='info-label'>Teléfono:</div>
                    <div class='info-value'>{$clientPhone}</div>
                </div>
                <div class='info-row'>
                    <div class='info-label'>Contacto:</div>
                    <div class='info-value'>{$clientContact}</div>
                </div>
            </div>
            
            <div class='section'>
                <h3>Detalles de la Visita</h3>
                <div class='info-row'>
                    <div class='info-label'>Fecha Programada:</div>
                    <div class='info-value'>{$scheduledDate}</div>
                </div>
                <div class='info-row'>
                    <div class='info-label'>Técnico Asignado:</div>
                    <div class='info-value'>{$technicianName}</div>
                </div>
                <div class='info-row'>
                    <div class='info-label'>Supervisor:</div>
                    <div class='info-value'>{$supervisorName}</div>
                </div>
                <div class='info-row'>
                    <div class='info-label'>Estado:</div>
                    <div class='info-value'>
                        <span class='status {$status}'>" . strtoupper($status) . "</span>
                    </div>
                </div>
            </div>
            
            <div class='section'>
                <h3>Registro de Tiempos</h3>
                <div class='info-row'>
                    <div class='info-label'>Hora de Ingreso:</div>
                    <div class='info-value'>{$ingresoTime}</div>
                </div>
                <div class='info-row'>
                    <div class='info-label'>Hora de Egreso:</div>
                    <div class='info-value'>{$egresoTime}</div>
                </div>
                <div class='info-row'>
                    <div class='info-label'>Duración Total:</div>
                    <div class='info-value'><strong>{$duration}</strong></div>
                </div>
            </div>
            
            <div class='section'>
                <h3>Observaciones</h3>
                <p>" . nl2br(htmlspecialchars($notes ?: 'Sin observaciones registradas')) . "</p>
            </div>
            
            {$eventsHtml}
            
            <div class='footer'>
                <p><strong>Sistema de Gestión de Visitas Técnicas</strong></p>
                <p>&copy; " . date('Y') . " - Todos los derechos reservados</p>
                <p>Este documento es confidencial y está destinado únicamente para el uso del destinatario.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}

/**
 * Calcular duración entre dos fechas/horas
 * @param string|null $start Fecha/hora de inicio
 * @param string|null $end Fecha/hora de fin
 * @return string Duración formateada
 */
function calculateDuration($start, $end) {
    if (!$start || !$end) {
        return 'N/A';
    }
    
    $startTime = strtotime($start);
    $endTime = strtotime($end);
    
    if ($startTime === false || $endTime === false || $endTime < $startTime) {
        return 'N/A';
    }
    
    $diff = $endTime - $startTime;
    
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    
    if ($hours > 0) {
        return "{$hours} hora(s) y {$minutes} minuto(s)";
    } else {
        return "{$minutes} minuto(s)";
    }
}
// ======================================================
// Modo de ejecución directa (solo si se accede desde el navegador)
// ======================================================
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    // Si se llama desde navegador con ?id=123 → generar PDF visible
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $visitId = (int)$_GET['id'];
        $pdfPath = generateVisitPDF($visitId);

        if (!$pdfPath || !file_exists($pdfPath)) {
            header('HTTP/1.1 500 Internal Server Error');
            echo "Error al generar PDF para la visita #{$visitId}";
            exit;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="reporte_visita_' . $visitId . '.pdf"');
        readfile($pdfPath);
        exit;
    }

    // Si se llama desde AJAX con ?action=resend → reenviar correo
    if (isset($_GET['action']) && $_GET['action'] === 'resend') {
        header('Content-Type: application/json; charset=utf-8');
        require_once __DIR__ . '/../includes/mail.php';

        $visitId = $_GET['visit_id'] ?? $_POST['visit_id'] ?? null;
        if (!$visitId) {
            echo json_encode(['success' => false, 'error' => 'ID de visita requerido']);
            exit;
        }

        try {
            $result = sendVisitReport($visitId);
            echo json_encode($result);
            exit;
        } catch (Throwable $e) {
            error_log('Error al reenviar correo: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }

    // Si llega aquí sin parámetros válidos
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acción o parámetro inválido']);
    exit;
}
