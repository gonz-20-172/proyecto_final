<?php
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$method = getRequestMethod();
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            getUser($id);
        } else {
            getUsers();
        }
        break;
        
    case 'POST':
        createUser();
        break;
        
    case 'PUT':
        updateUser($id);
        break;
        
    case 'DELETE':
        deleteUser($id);
        break;
        
    default:
        jsonError('Método no permitido', 405);
}

function getUsers() {
    requireAuth();
    
    $currentUser = getCurrentUser();
    $roleFilter = $_GET['role'] ?? null;
    
    $sql = "SELECT u.id, u.name, u.email, u.active, u.created_at, 
            r.name as role_name, r.id as role_id,
            s.name as supervisor_name, s.id as supervisor_id
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            LEFT JOIN users s ON u.supervisor_id = s.id";
    
    $params = [];
    $where = [];
    
    if ($roleFilter) {
        $where[] = "r.name = ?";
        $params[] = $roleFilter;
    }
    
    if ($currentUser->roleName === 'Supervisor') {
        $where[] = "(u.supervisor_id = ? OR u.id = ?)";
        $params[] = $currentUser->userId;
        $params[] = $currentUser->userId;
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    
    $sql .= " ORDER BY u.name ASC";
    
    $users = dbQuery($sql, $params);
    jsonSuccess($users);
}

function getUser($id) {
    requirePermission('users', 'view');
    
    if (!$id) {
        jsonError('ID de usuario requerido', 400);
    }
    
    $sql = "SELECT u.*, r.name as role_name, s.name as supervisor_name
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            LEFT JOIN users s ON u.supervisor_id = s.id
            WHERE u.id = ?
            LIMIT 1";
    
    $user = dbQueryOne($sql, [$id]);
    
    if (!$user) {
        jsonError('Usuario no encontrado', 404);
    }
    
    unset($user['password_hash']);
    
    jsonSuccess($user);
}

function createUser() {
    requirePermission('users', 'create');
    
    $data = getRequestBody();
    
    $missing = checkRequiredFields($data, ['name', 'email', 'password', 'role_id']);
    if (!empty($missing)) {
        jsonError('Campos requeridos: ' . implode(', ', $missing), 400);
    }
    
    if (!isValidEmail($data['email'])) {
        jsonError('Email no válido', 400);
    }
    
    $existingUser = dbQueryOne("SELECT id FROM users WHERE email = ?", [$data['email']]);
    if ($existingUser) {
        jsonError('El email ya está registrado', 400);
    }
    
    if (strlen($data['password']) < 6) {
        jsonError('La contraseña debe tener al menos 6 caracteres', 400);
    }
    
    $roleId = $data['role_id'];
    $role = dbQueryOne("SELECT name FROM roles WHERE id = ?", [$roleId]);
    if (!$role) {
        jsonError('Rol no válido', 400);
    }
    
    $supervisorId = null;
    if ($role['name'] === 'Técnico' && !empty($data['supervisor_id'])) {
        $supervisor = dbQueryOne("SELECT id FROM users WHERE id = ? AND role_id = (SELECT id FROM roles WHERE name = 'Supervisor')", [$data['supervisor_id']]);
        if ($supervisor) {
            $supervisorId = $data['supervisor_id'];
        }
    }
    
    $passwordHash = hashPassword($data['password']);
    
    $sql = "INSERT INTO users (name, email, password_hash, role_id, supervisor_id, active) 
            VALUES (?, ?, ?, ?, ?, 1)";
    
    $params = [
        sanitizeInput($data['name']),
        sanitizeInput($data['email']),
        $passwordHash,
        $roleId,
        $supervisorId
    ];
    
    $userId = dbInsert($sql, $params);
    
    if ($userId) {
        $user = dbQueryOne("SELECT u.*, r.name as role_name FROM users u INNER JOIN roles r ON u.role_id = r.id WHERE u.id = ?", [$userId]);
        unset($user['password_hash']);
        jsonSuccess($user, 'Usuario creado exitosamente');
    } else {
        jsonError('Error al crear usuario', 500);
    }
}

function updateUser($id) {
    requirePermission('users', 'edit');
    
    if (!$id) {
        jsonError('ID de usuario requerido', 400);
    }
    
    $existingUser = dbQueryOne("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$existingUser) {
        jsonError('Usuario no encontrado', 404);
    }
    
    $data = getRequestBody();
    
    $missing = checkRequiredFields($data, ['name', 'email', 'role_id']);
    if (!empty($missing)) {
        jsonError('Campos requeridos: ' . implode(', ', $missing), 400);
    }
    
    if (!isValidEmail($data['email'])) {
        jsonError('Email no válido', 400);
    }
    
    $emailCheck = dbQueryOne("SELECT id FROM users WHERE email = ? AND id != ?", [$data['email'], $id]);
    if ($emailCheck) {
        jsonError('El email ya está registrado por otro usuario', 400);
    }
    
    $roleId = $data['role_id'];
    $role = dbQueryOne("SELECT name FROM roles WHERE id = ?", [$roleId]);
    if (!$role) {
        jsonError('Rol no válido', 400);
    }
    
    $supervisorId = null;
    if ($role['name'] === 'Técnico' && !empty($data['supervisor_id'])) {
        $supervisor = dbQueryOne("SELECT id FROM users WHERE id = ? AND role_id = (SELECT id FROM roles WHERE name = 'Supervisor')", [$data['supervisor_id']]);
        if ($supervisor) {
            $supervisorId = $data['supervisor_id'];
        }
    }
    
    $sql = "UPDATE users 
            SET name = ?, email = ?, role_id = ?, supervisor_id = ?, active = ?
            WHERE id = ?";
    
    $params = [
        sanitizeInput($data['name']),
        sanitizeInput($data['email']),
        $roleId,
        $supervisorId,
        isset($data['active']) ? (int)$data['active'] : $existingUser['active'],
        $id
    ];
    
    if (!empty($data['password'])) {
        if (strlen($data['password']) < 6) {
            jsonError('La contraseña debe tener al menos 6 caracteres', 400);
        }
        
        $passwordHash = hashPassword($data['password']);
        $sql = "UPDATE users 
                SET name = ?, email = ?, password_hash = ?, role_id = ?, supervisor_id = ?, active = ?
                WHERE id = ?";
        
        $params = [
            sanitizeInput($data['name']),
            sanitizeInput($data['email']),
            $passwordHash,
            $roleId,
            $supervisorId,
            isset($data['active']) ? (int)$data['active'] : $existingUser['active'],
            $id
        ];
    }
    
    $affected = dbExecute($sql, $params);
    
    if ($affected >= 0) {
        $user = dbQueryOne("SELECT u.*, r.name as role_name FROM users u INNER JOIN roles r ON u.role_id = r.id WHERE u.id = ?", [$id]);
        unset($user['password_hash']);
        jsonSuccess($user, 'Usuario actualizado exitosamente');
    } else {
        jsonError('Error al actualizar usuario', 400);
    }
}

function deleteUser($id) {
    requirePermission('users', 'delete');
    
    if (!$id) {
        jsonError('ID de usuario requerido', 400);
    }
    
    $currentUser = getCurrentUser();
    if ($currentUser->userId == $id) {
        jsonError('No puedes eliminar tu propio usuario', 400);
    }
    
    $visitsCount = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE technician_id = ? OR supervisor_id = ?", [$id, $id]);
    if ($visitsCount && $visitsCount['count'] > 0) {
        jsonError('No se puede eliminar el usuario porque tiene visitas asociadas. Puedes desactivarlo en su lugar.', 400);
    }
    
    $sql = "DELETE FROM users WHERE id = ?";
    $affected = dbExecute($sql, [$id]);
    
    if ($affected > 0) {
        jsonSuccess(null, 'Usuario eliminado exitosamente');
    } else {
        jsonError('Error al eliminar usuario', 400);
    }
}