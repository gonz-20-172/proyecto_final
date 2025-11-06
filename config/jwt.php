<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function generateToken($userId, $email, $roleId, $roleName) {
    $secret = $_ENV['JWT_SECRET'];
    $issuedAt = time();
    $expirationTime = $issuedAt + (60 * 60);
    
    $payload = [
        'iat' => $issuedAt,
        'exp' => $expirationTime,
        'userId' => $userId,
        'email' => $email,
        'roleId' => $roleId,
        'roleName' => $roleName
    ];
    
    return JWT::encode($payload, $secret, 'HS256');
}

function validateToken($token) {
    try {
        $secret = $_ENV['JWT_SECRET'];
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        error_log("Error validando token: " . $e->getMessage());
        return false;
    }
}

function getTokenFromRequest() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $auth = $headers['Authorization'];
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return $matches[1];
        }
    }
    
    if (isset($_COOKIE['auth_token'])) {
        return $_COOKIE['auth_token'];
    }
    
    return null;
}

function getCurrentUser() {
    $token = getTokenFromRequest();
    
    if (!$token) {
        return null;
    }
    
    return validateToken($token);
}

function isAuthenticated() {
    return getCurrentUser() !== null;
}

function hasRole($roles) {
    $user = getCurrentUser();
    
    if (!$user) {
        return false;
    }
    
    if (is_array($roles)) {
        return in_array($user->roleName, $roles);
    }
    
    return $user->roleName === $roles;
}

function setTokenCookie($token) {
    $expirationTime = time() + (60 * 60);
    setcookie('auth_token', $token, [
        'expires' => $expirationTime,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearTokenCookie() {
    setcookie('auth_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}