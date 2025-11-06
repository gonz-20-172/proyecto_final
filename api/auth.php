<?php
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
        
    case 'logout':
        handleLogout();
        break;
        
    default:
        jsonError('Acción no válida', 400);
}

function handleLogin() {
    if (!isMethod('POST')) {
        jsonError('Método no permitido', 405);
    }
    
    $data = getRequestBody();
    
    $missing = checkRequiredFields($data, ['email', 'password']);
    if (!empty($missing)) {
        jsonError('Campos requeridos: ' . implode(', ', $missing), 400);
    }
    
    if (!isValidEmail($data['email'])) {
        jsonError('Email no válido', 400);
    }
    
    $result = performLogin($data['email'], $data['password']);
    
    if (!$result['success']) {
        jsonError($result['error'], 401);
    }
    
    jsonSuccess([
        'token' => $result['token'],
        'user' => $result['user']
    ], 'Login exitoso');
}

function handleLogout() {
    if (!isMethod('POST')) {
        jsonError('Método no permitido', 405);
    }
    
    $result = performLogout();
    jsonSuccess(null, $result['message']);
}