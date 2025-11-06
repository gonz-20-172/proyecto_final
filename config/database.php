<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

function getDatabase() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $host = $_ENV['DB_HOST'];
            $port = $_ENV['DB_PORT'];
            $dbname = $_ENV['DB_NAME'];
            $username = $_ENV['DB_USER'];
            $password = $_ENV['DB_PASS'];
            
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, $username, $password, $options);
            
        } catch (PDOException $e) {
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            die(json_encode([
                'success' => false,
                'error' => 'Error de conexión a la base de datos'
            ]));
        }
    }
    
    return $pdo;
}

function dbQuery($sql, $params = []) {
    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error en consulta: " . $e->getMessage());
        return [];
    }
}

function dbQueryOne($sql, $params = []) {
    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    } catch (PDOException $e) {
        error_log("Error en consulta: " . $e->getMessage());
        return null;
    }
}

function dbExecute($sql, $params = []) {
    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Error en ejecución: " . $e->getMessage());
        return 0;
    }
}

function dbInsert($sql, $params = []) {
    try {
        $pdo = getDatabase();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error en inserción: " . $e->getMessage());
        return 0;
    }
}