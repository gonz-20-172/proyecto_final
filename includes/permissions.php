<?php

define('PERMISSIONS', [
    'users' => [
        'view' => ['Administrador'],
        'create' => ['Administrador'],
        'edit' => ['Administrador'],
        'delete' => ['Administrador']
    ],
    'clients' => [
        'view' => ['Administrador', 'Supervisor', 'Técnico'],
        'create' => ['Administrador', 'Supervisor'],
        'edit' => ['Administrador', 'Supervisor'],
        'delete' => ['Administrador']
    ],
    'visits' => [
        'view' => ['Administrador', 'Supervisor', 'Técnico'],
        'create' => ['Administrador', 'Supervisor'],
        'edit' => ['Administrador', 'Supervisor'],
        'delete' => ['Administrador']
    ],
    'events' => [
        'create' => ['Técnico', 'Administrador'],
        'view' => ['Administrador', 'Supervisor', 'Técnico']
    ]
]);

function hasPermission($module, $action) {
    $user = getCurrentUser();
    
    if (!$user) {
        return false;
    }
    
    if (!isset(PERMISSIONS[$module][$action])) {
        return false;
    }
    
    return in_array($user->roleName, PERMISSIONS[$module][$action]);
}

function requirePermission($module, $action) {
    requireAuth();
    
    if (!hasPermission($module, $action)) {
        jsonError('No tienes permisos para realizar esta acción', 403);
    }
}

function canViewTechnicianVisits($technicianId) {
    $user = getCurrentUser();
    
    if (!$user) {
        return false;
    }
    
    if ($user->roleName === 'Administrador') {
        return true;
    }
    
    if ($user->roleName === 'Técnico') {
        return $user->userId == $technicianId;
    }
    
    if ($user->roleName === 'Supervisor') {
        $sql = "SELECT COUNT(*) as count FROM users 
                WHERE id = ? AND supervisor_id = ?";
        $result = dbQueryOne($sql, [$technicianId, $user->userId]);
        return $result && $result['count'] > 0;
    }
    
    return false;
}

function getVisitsFilterByRole() {
    $user = getCurrentUser();
    
    if (!$user) {
        return ['1=0', []];
    }
    
    if ($user->roleName === 'Administrador') {
        return ['1=1', []];
    }
    
    if ($user->roleName === 'Supervisor') {
        return ['v.supervisor_id = ?', [$user->userId]];
    }
    
    if ($user->roleName === 'Técnico') {
        return ['v.technician_id = ?', [$user->userId]];
    }
    
    return ['1=0', []];
}

function getAvailableTechnicians() {
    $user = getCurrentUser();
    
    if (!$user) {
        return [];
    }
    
    if ($user->roleName === 'Administrador') {
        $sql = "SELECT u.id, u.name, u.email 
                FROM users u 
                INNER JOIN roles r ON u.role_id = r.id 
                WHERE r.name = 'Técnico' AND u.active = 1 
                ORDER BY u.name";
        return dbQuery($sql);
    }
    
    if ($user->roleName === 'Supervisor') {
        $sql = "SELECT u.id, u.name, u.email 
                FROM users u 
                INNER JOIN roles r ON u.role_id = r.id 
                WHERE r.name = 'Técnico' AND u.supervisor_id = ? AND u.active = 1 
                ORDER BY u.name";
        return dbQuery($sql, [$user->userId]);
    }
    
    return [];
}

function isTechnicianUnderSupervisor($technicianId) {
    $user = getCurrentUser();
    
    if (!$user) {
        return false;
    }
    
    if ($user->roleName === 'Administrador') {
        return true;
    }
    
    if ($user->roleName === 'Supervisor') {
        $sql = "SELECT COUNT(*) as count FROM users 
                WHERE id = ? AND supervisor_id = ? AND active = 1";
        $result = dbQueryOne($sql, [$technicianId, $user->userId]);
        return $result && $result['count'] > 0;
    }
    
    return false;
}