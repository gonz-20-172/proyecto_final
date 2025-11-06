<?php
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$method = getRequestMethod();
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            getClient($id);
        } else {
            getClients();
        }
        break;
        
    case 'POST':
        createClient();
        break;
        
    case 'PUT':
        updateClient($id);
        break;
        
    case 'DELETE':
        deleteClient($id);
        break;
        
    default:
        jsonError('Método no permitido', 405);
}

function getClients() {
    requirePermission('clients', 'view');
    
    $search = $_GET['search'] ?? '';
    $sql = "SELECT c.*, u.name as created_by_name 
            FROM clients c 
            LEFT JOIN users u ON c.created_by = u.id";
    
    $params = [];
    
    if ($search) {
        $sql .= " WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?";
        $searchTerm = "%{$search}%";
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY c.name ASC";
    
    $clients = dbQuery($sql, $params);
    jsonSuccess($clients);
}

function getClient($id) {
    requirePermission('clients', 'view');
    
    if (!$id) {
        jsonError('ID de cliente requerido', 400);
    }
    
    $sql = "SELECT c.*, u.name as created_by_name 
            FROM clients c 
            LEFT JOIN users u ON c.created_by = u.id 
            WHERE c.id = ? 
            LIMIT 1";
    
    $client = dbQueryOne($sql, [$id]);
    
    if (!$client) {
        jsonError('Cliente no encontrado', 404);
    }
    
    jsonSuccess($client);
}

function createClient() {
    requirePermission('clients', 'create');
    
    $data = getRequestBody();
    
    $missing = checkRequiredFields($data, ['name']);
    if (!empty($missing)) {
        jsonError('Campos requeridos: ' . implode(', ', $missing), 400);
    }
    
    if (isset($data['email']) && !empty($data['email']) && !isValidEmail($data['email'])) {
        jsonError('Email no válido', 400);
    }
    
    $currentUser = getCurrentUser();
    
    $sql = "INSERT INTO clients (name, address, phone, email, lat, lng, notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $params = [
        sanitizeInput($data['name']),
        sanitizeInput($data['address'] ?? ''),
        sanitizeInput($data['phone'] ?? ''),
        sanitizeInput($data['email'] ?? ''),
        $data['lat'] ?? null,
        $data['lng'] ?? null,
        sanitizeInput($data['notes'] ?? ''),
        $currentUser->userId
    ];
    
    $clientId = dbInsert($sql, $params);
    
    if ($clientId) {
        $client = dbQueryOne("SELECT * FROM clients WHERE id = ?", [$clientId]);
        jsonSuccess($client, 'Cliente creado exitosamente');
    } else {
        jsonError('Error al crear cliente', 500);
    }
}

function updateClient($id) {
    requirePermission('clients', 'edit');
    
    if (!$id) {
        jsonError('ID de cliente requerido', 400);
    }
    
    $data = getRequestBody();
    
    $missing = checkRequiredFields($data, ['name']);
    if (!empty($missing)) {
        jsonError('Campos requeridos: ' . implode(', ', $missing), 400);
    }
    
    if (isset($data['email']) && !empty($data['email']) && !isValidEmail($data['email'])) {
        jsonError('Email no válido', 400);
    }
    
    $sql = "UPDATE clients 
            SET name = ?, address = ?, phone = ?, email = ?, lat = ?, lng = ?, notes = ?
            WHERE id = ?";
    
    $params = [
        sanitizeInput($data['name']),
        sanitizeInput($data['address'] ?? ''),
        sanitizeInput($data['phone'] ?? ''),
        sanitizeInput($data['email'] ?? ''),
        $data['lat'] ?? null,
        $data['lng'] ?? null,
        sanitizeInput($data['notes'] ?? ''),
        $id
    ];
    
    $affected = dbExecute($sql, $params);
    
    if ($affected > 0) {
        $client = dbQueryOne("SELECT * FROM clients WHERE id = ?", [$id]);
        jsonSuccess($client, 'Cliente actualizado exitosamente');
    } else {
        jsonError('Error al actualizar cliente o no hubo cambios', 400);
    }
}

function deleteClient($id) {
    requirePermission('clients', 'delete');
    
    if (!$id) {
        jsonError('ID de cliente requerido', 400);
    }
    
    $visitsCount = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE client_id = ?", [$id]);
    
    if ($visitsCount && $visitsCount['count'] > 0) {
        jsonError('No se puede eliminar el cliente porque tiene visitas asociadas', 400);
    }
    
    $sql = "DELETE FROM clients WHERE id = ?";
    $affected = dbExecute($sql, [$id]);
    
    if ($affected > 0) {
        jsonSuccess(null, 'Cliente eliminado exitosamente');
    } else {
        jsonError('Error al eliminar cliente o cliente no encontrado', 400);
    }
}