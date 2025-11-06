<?php

// ============================================
// AUTENTICACIÓN CON JWT
// ============================================

function authenticateUser($email, $password) {
    $sql = "SELECT u.*, r.name as role_name 
            FROM users u 
            INNER JOIN roles r ON u.role_id = r.id 
            WHERE u.email = ? AND u.active = 1 
            LIMIT 1";
    
    $user = dbQueryOne($sql, [$email]);
    
    if (!$user) {
        return false;
    }
    
    if (!verifyPassword($password, $user['password_hash'])) {
        return false;
    }
    
    unset($user['password_hash']);
    
    return $user;
}

function performLogin($email, $password) {
    $user = authenticateUser($email, $password);
    
    if (!$user) {
        return [
            'success' => false,
            'error' => 'Credenciales inválidas'
        ];
    }
    
    // Generar token usando jwt.php
    $token = generateToken(
        $user['id'],
        $user['email'],
        $user['role_id'],
        $user['role_name']
    );
    
    setTokenCookie($token);
    
    return [
        'success' => true,
        'token' => $token,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role_name']
        ]
    ];
}

function performLogout() {
    clearTokenCookie();
    return [
        'success' => true,
        'message' => 'Sesión cerrada correctamente'
    ];
}

// ============================================
// AUTENTICACIÓN Y AUTORIZACIÓN
// ============================================

function requireAuth() {
    if (!isAuthenticated()) {
        // Si es petición API/AJAX, retornar JSON
        if (isApiRequest() || isAjaxRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'No autenticado'
            ]);
            exit;
        } else {
            // Si es página web, redirigir al login
            header('Location: /pages/login.php');
            exit;
        }
    }
}

function requireRole($roles) {
    requireAuth();
    
    if (!hasRole($roles)) {
        if (isApiRequest() || isAjaxRequest()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'No tienes permisos para realizar esta acción'
            ]);
            exit;
        } else {
            header('HTTP/1.1 403 Forbidden');
            echo '403 - Acceso Denegado';
            exit;
        }
    }
}

function getCurrentUserFromDB() {
    $currentUser = getCurrentUser();
    
    if (!$currentUser) {
        return null;
    }
    
    $sql = "SELECT u.*, r.name as role_name 
            FROM users u 
            INNER JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ? AND u.active = 1 
            LIMIT 1";
    
    $user = dbQueryOne($sql, [$currentUser->userId]);
    
    if ($user) {
        unset($user['password_hash']);
    }
    
    return $user;
}

function canEdit($resourceOwnerId) {
    $user = getCurrentUser();
    
    if (!$user) {
        return false;
    }
    
    if ($user->roleName === 'Administrador') {
        return true;
    }
    
    return $user->userId == $resourceOwnerId;
}

function canDelete() {
    return hasRole('Administrador');
}

// ============================================
// HELPERS
// ============================================

function isApiRequest() {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return strpos($uri, '/api/') !== false;
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

