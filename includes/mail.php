<?php
/**
 * Funciones para envío de correos electrónicos
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Enviar email con PHPMailer
 * @param string $to Email del destinatario
 * @param string $toName Nombre del destinatario
 * @param string $subject Asunto del correo
 * @param string $body Cuerpo del correo (HTML)
 * @param string|null $attachmentPath Ruta del archivo adjunto
 * @return array Resultado de la operación
 */
function sendEmail($to, $toName, $subject, $body, $attachmentPath = null) {
    try {
        $mail = getMailer();
        
        // Configurar destinatario
        $mail->addAddress($to, $toName);
        
        // Configurar contenido
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        
        // Adjuntar archivo si existe
        if ($attachmentPath && file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath);
        }
        
        // Enviar
        $mail->send();
        
        return [
            'success' => true, 
            'message' => 'Correo enviado exitosamente'
        ];
        
    } catch (Exception $e) {
        error_log('Error enviando correo: ' . $e->getMessage());
        return [
            'success' => false, 
            'error' => 'Error al enviar correo: ' . $mail->ErrorInfo
        ];
    }
}

/**
 * Enviar reporte de visita por correo
 * @param int $visitId ID de la visita
 * @return array Resultado de la operación
 */
function sendVisitReport($visitId) {
    try {
        // Obtener datos de la visita
        $visit = getVisitData($visitId);
        
        if (!$visit) {
            return [
                'success' => false, 
                'error' => 'Visita no encontrada'
            ];
        }
        
        if (empty($visit['client_email'])) {
            return [
                'success' => false, 
                'error' => 'Cliente no tiene email registrado'
            ];
        }
        
        // Generar PDF
        $pdfPath = generateVisitPDF($visit);
        
        if (!$pdfPath) {
            return [
                'success' => false, 
                'error' => 'Error al generar PDF'
            ];
        }
        
        // Generar cuerpo del email
        $emailBody = getEmailBody($visit);
        
        // Enviar email
        $result = sendEmail(
            $visit['client_email'],
            $visit['client_name'],
            'Reporte de Visita Técnica - ' . $visit['client_name'],
            $emailBody,
            $pdfPath
        );
        
        // Eliminar archivo temporal
        if (file_exists($pdfPath)) {
            unlink($pdfPath);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log('Error en sendVisitReport: ' . $e->getMessage());
        return [
            'success' => false, 
            'error' => 'Error al enviar correo: ' . $e->getMessage()
        ];
    }
}

/**
 * Obtener datos completos de la visita
 * @param int $visitId ID de la visita
 * @return array|null Datos de la visita o null si no existe
 */
function getVisitData($visitId) {
    $sql = "SELECT v.*, 
            c.name as client_name, 
            c.email as client_email, 
            c.address as client_address, 
            c.phone as client_phone,
            t.name as technician_name, 
            t.email as technician_email,
            s.name as supervisor_name,
            s.email as supervisor_email,
            (SELECT event_time FROM visit_events WHERE visit_id = v.id AND event_type = 'ingreso' ORDER BY event_time DESC LIMIT 1) as ingreso_time,
            (SELECT event_time FROM visit_events WHERE visit_id = v.id AND event_type = 'egreso' ORDER BY event_time DESC LIMIT 1) as egreso_time,
            (SELECT lat FROM visit_events WHERE visit_id = v.id AND event_type = 'ingreso' ORDER BY event_time DESC LIMIT 1) as ingreso_lat,
            (SELECT lng FROM visit_events WHERE visit_id = v.id AND event_type = 'ingreso' ORDER BY event_time DESC LIMIT 1) as ingreso_lng,
            (SELECT lat FROM visit_events WHERE visit_id = v.id AND event_type = 'egreso' ORDER BY event_time DESC LIMIT 1) as egreso_lat,
            (SELECT lng FROM visit_events WHERE visit_id = v.id AND event_type = 'egreso' ORDER BY event_time DESC LIMIT 1) as egreso_lng
            FROM visits v
            INNER JOIN clients c ON v.client_id = c.id
            INNER JOIN users t ON v.technician_id = t.id
            INNER JOIN users s ON v.supervisor_id = s.id
            WHERE v.id = ?
            LIMIT 1";
    
    $visit = dbQueryOne($sql, [$visitId]);
    
    if ($visit) {
        // Obtener eventos
        $events = dbQuery("
            SELECT * FROM visit_events
            WHERE visit_id = ?
            ORDER BY event_time ASC
        ", [$visitId]);
        
        $visit['events'] = $events;
    }
    
    return $visit;
}

/**
 * Obtener cuerpo del email con template
 * @param array $visit Datos de la visita
 * @return string HTML del email
 */
function getEmailBody($visit) {
    $templatePath = __DIR__ . '/../templates/email-template.html';
    
    // Si existe template personalizado, usarlo
    if (file_exists($templatePath)) {
        $template = file_get_contents($templatePath);
        
        $replacements = [
            '{CLIENT_NAME}' => htmlspecialchars($visit['client_name']),
            '{TECHNICIAN_NAME}' => htmlspecialchars($visit['technician_name']),
            '{VISIT_DATE}' => formatDate($visit['scheduled_date'], 'd/m/Y'),
            '{INGRESO_TIME}' => $visit['ingreso_time'] ? formatDate($visit['ingreso_time'], 'd/m/Y H:i') : 'N/A',
            '{EGRESO_TIME}' => $visit['egreso_time'] ? formatDate($visit['egreso_time'], 'd/m/Y H:i') : 'N/A',
            '{DURATION}' => calculateDuration($visit['ingreso_time'], $visit['egreso_time']),
            '{NOTES}' => htmlspecialchars($visit['notes'] ?: 'Sin observaciones'),
            '{CURRENT_YEAR}' => date('Y')
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    // Si no existe template, usar el por defecto
    return getDefaultEmailBody($visit);
}

/**
 * Obtener cuerpo del email por defecto
 * @param array $visit Datos de la visita
 * @return string HTML del email
 */
function getDefaultEmailBody($visit) {
    $duration = calculateDuration($visit['ingreso_time'], $visit['egreso_time']);
    
    return "
    <html>
    <head>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                padding: 20px; 
            }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 30px; 
                text-align: center; 
                border-radius: 8px 8px 0 0;
            }
            .header h2 {
                margin: 0;
                font-size: 24px;
            }
            .content { 
                padding: 30px; 
                background: #f8f9fa; 
                border-radius: 0 0 8px 8px;
            }
            .info-box { 
                background: white; 
                padding: 20px; 
                margin: 15px 0; 
                border-left: 4px solid #667eea;
                border-radius: 4px;
            }
            .info-box strong {
                color: #667eea;
            }
            .footer { 
                text-align: center; 
                padding: 20px; 
                color: #666; 
                font-size: 12px; 
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Reporte de Visita Técnica</h2>
            </div>
            <div class='content'>
                <p>Estimado/a <strong>" . htmlspecialchars($visit['client_name']) . "</strong>,</p>
                <p>Adjuntamos el reporte de la visita técnica realizada por nuestro equipo.</p>
                
                <div class='info-box'>
                    <strong>Técnico:</strong> " . htmlspecialchars($visit['technician_name']) . "<br>
                    <strong>Fecha:</strong> " . formatDate($visit['scheduled_date'], 'd/m/Y') . "<br>
                    <strong>Hora de ingreso:</strong> " . ($visit['ingreso_time'] ? formatDate($visit['ingreso_time'], 'H:i') : 'N/A') . "<br>
                    <strong>Hora de egreso:</strong> " . ($visit['egreso_time'] ? formatDate($visit['egreso_time'], 'H:i') : 'N/A') . "<br>
                    <strong>Duración:</strong> {$duration}
                </div>
                
                " . (!empty($visit['notes']) ? "
                <div class='info-box'>
                    <strong>Observaciones:</strong><br>
                    " . nl2br(htmlspecialchars($visit['notes'])) . "
                </div>
                " : "") . "
                
                <p>Para más información o consultas, no dude en contactarnos.</p>
                <p>Saludos cordiales,<br><strong>Equipo de Visitas Técnicas</strong></p>
            </div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " Sistema de Visitas Técnicas. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>
    ";
}