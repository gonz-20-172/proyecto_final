<?php
require_once __DIR__ . '/../vendor/autoload.php';
header('Content-Type: application/json; charset=utf-8');

// 🔹 Determinar método y acción
$method = $_SERVER['REQUEST_METHOD'];
$query = $_GET;
$action = $query['action'] ?? null;
$visit_id = $query['visit_id'] ?? null;

// 🔍 Log de depuración (solo si necesitas ver qué llega)
error_log("🔍 events.php recibido: method={$method}, action={$action}, visit_id={$visit_id}");

switch ($method) {
    case 'GET':
        if ($action === 'list' || isset($visit_id)) {
            getEvents($visit_id);
        } else {
            jsonError('Acción no válida o no especificada', 400);
        }
        break;

    case 'POST':
        if ($action === 'register') {
            createEvent();
        } else {
            jsonError('Acción no válida o no especificada', 400);
        }
        break;

    default:
        jsonError('Método no permitido', 405);
}

/**
 * Obtener eventos de una visita
 */
function getEvents($visit_id) {
    requirePermission('events', 'view');
    
    if (!$visit_id) {
        jsonError('ID de visita requerido', 400);
    }
    
    $sql = "SELECT * FROM visit_events WHERE visit_id = ? ORDER BY event_time ASC";
    $events = dbQuery($sql, [$visit_id]);
    
    jsonSuccess($events);
}

/**
 * Crear nuevo evento (ingreso/egreso)
 */
function createEvent() {
    requirePermission('events', 'create');
    
    $data = getRequestBody();

    if (empty($data)) {
        jsonError('No se recibieron datos en la solicitud', 400);
    }

    $missing = checkRequiredFields($data, ['visit_id', 'event_type']);
    if (!empty($missing)) {
        jsonError('Campos requeridos: ' . implode(', ', $missing), 400);
    }

    $visit = dbQueryOne("SELECT * FROM visits WHERE id = ?", [$data['visit_id']]);
    if (!$visit) {
        jsonError('Visita no encontrada', 404);
    }

    $currentUser = getCurrentUser();

    if ($currentUser->roleName === 'Técnico' && $visit['technician_id'] != $currentUser->userId) {
        jsonError('Solo puedes registrar eventos en tus propias visitas', 403);
    }

    $eventType = $data['event_type'];

    if ($eventType === 'ingreso') {
        $existingIngreso = dbQueryOne("SELECT * FROM visit_events WHERE visit_id = ? AND event_type = 'ingreso'", [$data['visit_id']]);
        if ($existingIngreso) {
            jsonError('Ya existe un registro de ingreso para esta visita', 400);
        }
        dbExecute("UPDATE visits SET status = 'in-progress' WHERE id = ?", [$data['visit_id']]);
    }

    if ($eventType === 'egreso') {
        $existingIngreso = dbQueryOne("SELECT * FROM visit_events WHERE visit_id = ? AND event_type = 'ingreso'", [$data['visit_id']]);
        if (!$existingIngreso) {
            jsonError('Debe registrar el ingreso antes del egreso', 400);
        }

        $existingEgreso = dbQueryOne("SELECT * FROM visit_events WHERE visit_id = ? AND event_type = 'egreso'", [$data['visit_id']]);
        if ($existingEgreso) {
            jsonError('Ya existe un registro de egreso para esta visita', 400);
        }

        dbExecute("UPDATE visits SET status = 'completed' WHERE id = ?", [$data['visit_id']]);
    }

    $sql = "INSERT INTO visit_events (visit_id, event_type, lat, lng, note) VALUES (?, ?, ?, ?, ?)";
    $params = [
        $data['visit_id'],
        $eventType,
        $data['lat'] ?? null,
        $data['lng'] ?? null,
        sanitizeInput($data['note'] ?? '')
    ];

    $eventId = dbInsert($sql, $params);

    if (!$eventId) {
        jsonError('Error al registrar evento', 500);
    }

    $event = dbQueryOne("SELECT * FROM visit_events WHERE id = ?", [$eventId]);
    $visit = dbQueryOne("SELECT v.*, c.name as client_name, t.name as technician_name 
                         FROM visits v 
                         INNER JOIN clients c ON v.client_id = c.id 
                         INNER JOIN users t ON v.technician_id = t.id 
                         WHERE v.id = ?", [$data['visit_id']]);

    // 📨 Enviar correo solo en egreso (opcional, no bloqueante)
    if ($eventType === 'egreso') {
        require_once __DIR__ . '/reports.php';
        try {
            $result = sendVisitReport($data['visit_id']);
            if (!$result['success']) {
                error_log("⚠️ Error al enviar correo (visita {$data['visit_id']}): " . $result['error']);
            }
        } catch (Exception $e) {
            error_log("⚠️ Excepción al enviar correo (visita {$data['visit_id']}): " . $e->getMessage());
        }
    }

    jsonSuccess([
        'event' => $event,
        'visit' => $visit
    ], 'Evento registrado exitosamente');
}
