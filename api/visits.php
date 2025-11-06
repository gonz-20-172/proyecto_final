<?php
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$method = getRequestMethod();
$id = $_GET['id'] ?? null;
$today = $_GET['today'] ?? null;

switch ($method) {
    case 'GET':
        if ($today) {
            getTodayVisits();
        } elseif ($id) {
            getVisit($id);
        } else {
            getVisits();
        }
        break;
        
    case 'POST':
        createVisit();
        break;
        
    case 'PUT':
        updateVisit($id);
        break;
        
    default:
        jsonError('Método no permitido', 405);
}

function getVisits() {
    requirePermission('visits', 'view');
    
    list($whereClause, $params) = getVisitsFilterByRole();

    // 🔹 Parámetros de filtro
    $status = $_GET['status'] ?? null;
    $client_id = $_GET['client_id'] ?? ($_GET['client'] ?? null);
    $technician_id = $_GET['technician_id'] ?? ($_GET['technician'] ?? null);
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;

    // 🔹 Base de la consulta
    $sql = "SELECT 
                v.*, 
                c.name AS client_name, 
                c.address AS client_address, 
                c.lat AS client_lat, 
                c.lng AS client_lng,
                t.name AS technician_name,
                s.name AS supervisor_name,
                (SELECT COUNT(*) FROM visit_events WHERE visit_id = v.id AND event_type = 'ingreso') AS has_ingreso,
                (SELECT COUNT(*) FROM visit_events WHERE visit_id = v.id AND event_type = 'egreso') AS has_egreso
            FROM visits v
            INNER JOIN clients c ON v.client_id = c.id
            INNER JOIN users t ON v.technician_id = t.id
            INNER JOIN users s ON v.supervisor_id = s.id
            WHERE {$whereClause}";

    // 🔹 Filtro por estado
    if ($status) {
        $sql .= " AND v.status = ?";
        $params[] = $status;
    }

    // 🔹 Filtro por cliente
    if ($client_id) {
        $sql .= " AND v.client_id = ?";
        $params[] = $client_id;
    }

    // 🔹 Filtro por técnico
    if ($technician_id) {
        $sql .= " AND v.technician_id = ?";
        $params[] = $technician_id;
    }

    // 🔹 Filtro por rango de fechas
    if ($from && $to) {
        $sql .= " AND DATE(v.scheduled_date) BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
    } elseif ($from) {
        $sql .= " AND DATE(v.scheduled_date) >= ?";
        $params[] = $from;
    } elseif ($to) {
        $sql .= " AND DATE(v.scheduled_date) <= ?";
        $params[] = $to;
    }

    $sql .= " ORDER BY v.scheduled_date DESC, v.scheduled_time DESC";

    $visits = dbQuery($sql, $params);
    jsonSuccess($visits);
}


function getTodayVisits() {
    requirePermission('visits', 'view');
    
    $currentUser = getCurrentUser();
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    if ($currentUser->roleName === 'Técnico') {
        $sql = "SELECT v.*, 
                c.name as client_name, c.address as client_address, c.lat as client_lat, c.lng as client_lng,
                t.name as technician_name,
                s.name as supervisor_name,
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
                WHERE v.technician_id = ? 
                  AND v.scheduled_date >= ? 
                  AND v.scheduled_date <= ?
                  AND v.status != 'cancelled'
                ORDER BY v.scheduled_date ASC, v.scheduled_time ASC";
        
        $visits = dbQuery($sql, [$currentUser->userId, $today, $tomorrow]);
    } else {
        list($whereClause, $params) = getVisitsFilterByRole();
        $params[] = $today;
        $params[] = $tomorrow;
        
        $sql = "SELECT v.*, 
                c.name as client_name, c.address as client_address,
                t.name as technician_name,
                s.name as supervisor_name
                FROM visits v
                INNER JOIN clients c ON v.client_id = c.id
                INNER JOIN users t ON v.technician_id = t.id
                INNER JOIN users s ON v.supervisor_id = s.id
                WHERE {$whereClause} 
                  AND v.scheduled_date >= ? 
                  AND v.scheduled_date <= ?
                ORDER BY v.scheduled_date ASC, v.scheduled_time ASC";
        
        $visits = dbQuery($sql, $params);
    }
    
    jsonSuccess($visits);
}

function getVisit($id) {
    requirePermission('visits', 'view');
    
    if (!$id) {
        jsonError('ID de visita requerido', 400);
    }
    
    $sql = "SELECT v.*, 
            c.name as client_name, c.address as client_address, c.email as client_email, c.phone as client_phone, c.lat as client_lat, c.lng as client_lng,
            t.name as technician_name, t.email as technician_email,
            s.name as supervisor_name
            FROM visits v
            INNER JOIN clients c ON v.client_id = c.id
            INNER JOIN users t ON v.technician_id = t.id
            INNER JOIN users s ON v.supervisor_id = s.id
            WHERE v.id = ?
            LIMIT 1";
    
    $visit = dbQueryOne($sql, [$id]);
    
    if (!$visit) {
        jsonError('Visita no encontrada', 404);
    }
    
    $events = dbQuery("SELECT * FROM visit_events WHERE visit_id = ? ORDER BY event_time ASC", [$id]);
    $visit['events'] = $events;
    
    jsonSuccess($visit);
}

function createVisit() {
    requirePermission('visits', 'create');
    
    $data = getRequestBody();
    
    $missing = checkRequiredFields($data, ['client_id', 'technician_id', 'scheduled_date']);
    if (!empty($missing)) {
        jsonError('Campos requeridos: ' . implode(', ', $missing), 400);
    }
    
    $currentUser = getCurrentUser();
    
    if ($currentUser->roleName === 'Supervisor') {
        if (!isTechnicianUnderSupervisor($data['technician_id'])) {
            jsonError('Solo puedes asignar visitas a técnicos bajo tu supervisión', 403);
        }
        $supervisor_id = $currentUser->userId;
    } else {
        $supervisor_id = $data['supervisor_id'] ?? $currentUser->userId;
    }
    
    $scheduledDate = $data['scheduled_date'];
    if (strtotime($scheduledDate) < strtotime(date('Y-m-d'))) {
        jsonError('No se pueden crear visitas en fechas pasadas', 400);
    }
    
    $sql = "INSERT INTO visits (client_id, supervisor_id, technician_id, scheduled_date, scheduled_time, notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'scheduled')";
    
    $params = [
        $data['client_id'],
        $supervisor_id,
        $data['technician_id'],
        $scheduledDate,
        $data['scheduled_time'] ?? null,
        sanitizeInput($data['notes'] ?? '')
    ];
    
    $visitId = dbInsert($sql, $params);
    
    if ($visitId) {
        $visit = dbQueryOne("SELECT v.*, c.name as client_name, t.name as technician_name 
                             FROM visits v 
                             INNER JOIN clients c ON v.client_id = c.id 
                             INNER JOIN users t ON v.technician_id = t.id 
                             WHERE v.id = ?", [$visitId]);
        jsonSuccess($visit, 'Visita creada exitosamente');
    } else {
        jsonError('Error al crear visita', 500);
    }
}

function updateVisit($id) {
    requirePermission('visits', 'edit');
    
    if (!$id) {
        jsonError('ID de visita requerido', 400);
    }
    
    $visit = dbQueryOne("SELECT * FROM visits WHERE id = ?", [$id]);
    if (!$visit) {
        jsonError('Visita no encontrada', 404);
    }
    
    if ($visit['status'] === 'completed') {
        jsonError('No se pueden editar visitas completadas', 400);
    }
    
    $data = getRequestBody();
    $currentUser = getCurrentUser();

        // 🔒 Restricción: solo supervisores pueden cancelar visitas
    if (isset($data['status']) && $data['status'] === 'cancelled') {
        if ($currentUser->roleName !== 'Supervisor') {
            jsonError('Solo los supervisores pueden cancelar visitas.', 403);
        }
    }

    // 🔒 No se pueden editar visitas completadas
    if ($visit['status'] === 'completed' && (!isset($data['status']) || $data['status'] !== 'cancelled')) {
        jsonError('No se pueden editar visitas completadas.', 400);
    }
    
    $sql = "UPDATE visits 
            SET client_id = ?, technician_id = ?, scheduled_date = ?, scheduled_time = ?, notes = ?, status = ?
            WHERE id = ?";
    
    $params = [
        $data['client_id'] ?? $visit['client_id'],
        $data['technician_id'] ?? $visit['technician_id'],
        $data['scheduled_date'] ?? $visit['scheduled_date'],
        $data['scheduled_time'] ?? $visit['scheduled_time'],
        sanitizeInput($data['notes'] ?? $visit['notes']),
        $data['status'] ?? $visit['status'],
        $id
    ];
    
    $affected = dbExecute($sql, $params);
    
    if ($affected >= 0) {
        $updatedVisit = dbQueryOne("SELECT v.*, c.name as client_name, t.name as technician_name 
                                     FROM visits v 
                                     INNER JOIN clients c ON v.client_id = c.id 
                                     INNER JOIN users t ON v.technician_id = t.id 
                                     WHERE v.id = ?", [$id]);
        jsonSuccess($updatedVisit, 'Visita actualizada exitosamente');
    } else {
        jsonError('Error al actualizar visita', 400);
    }
}