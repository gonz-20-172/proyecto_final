<?php
require_once __DIR__ . '/vendor/autoload.php';

try {
    $pdo = getDatabase();
    $users = dbQuery("SELECT * FROM users");
    
    echo "✅ Conexión exitosa!<br>";
    echo "Usuarios encontrados: " . count($users) . "<br>";
    
    foreach ($users as $user) {
        echo "- " . $user['name'] . " (" . $user['email'] . ")<br>";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}