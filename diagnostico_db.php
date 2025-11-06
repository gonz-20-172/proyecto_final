<?php
/**
 * Script de Diagnóstico - Error de Inserción en visit_events
 * 
 * Este script verifica:
 * 1. Estructura de la tabla visit_events
 * 2. Prueba de inserción manual
 * 3. Logs de errores de MySQL
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "<h2>🔍 Diagnóstico de Base de Datos - visit_events</h2>";

// ============================================
// 1. VERIFICAR ESTRUCTURA DE LA TABLA
// ============================================
echo "<h3>1. Estructura de la tabla visit_events</h3>";

try {
    $tableInfo = dbQuery("DESCRIBE visit_events");
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($tableInfo as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// ============================================
// 2. VERIFICAR VISITAS DISPONIBLES
// ============================================
echo "<h3>2. Visitas disponibles para prueba</h3>";

try {
    $visits = dbQuery("SELECT id, client_id, technician_id, status FROM visits LIMIT 5");
    
    if (empty($visits)) {
        echo "<p style='color: orange;'>⚠️ No hay visitas en la base de datos</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Cliente ID</th><th>Técnico ID</th><th>Estado</th></tr>";
        
        foreach ($visits as $visit) {
            echo "<tr>";
            echo "<td>{$visit['id']}</td>";
            echo "<td>{$visit['client_id']}</td>";
            echo "<td>{$visit['technician_id']}</td>";
            echo "<td>{$visit['status']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// ============================================
// 3. PRUEBA DE INSERCIÓN MANUAL
// ============================================
echo "<h3>3. Prueba de inserción manual</h3>";

try {
    // Obtener una visita de prueba
    $testVisit = dbQueryOne("SELECT id FROM visits LIMIT 1");
    
    if (!$testVisit) {
        echo "<p style='color: orange;'>⚠️ No hay visitas disponibles para probar</p>";
    } else {
        $visitId = $testVisit['id'];
        
        echo "<p>Intentando insertar evento de prueba en visita #{$visitId}...</p>";
        
        // Método 1: Usando dbInsert
        echo "<h4>Método 1: Usando dbInsert()</h4>";
        
        $sql1 = "INSERT INTO visit_events (visit_id, event_type, event_time, lat, lng, notes) 
                 VALUES (?, ?, NOW(), ?, ?, ?)";
        
        $params1 = [
            $visitId,
            'ingreso',
            14.6349,
            -90.5069,
            'Prueba de inserción'
        ];
        
        echo "<p>SQL: <code>" . htmlspecialchars($sql1) . "</code></p>";
        echo "<p>Params: <code>" . htmlspecialchars(json_encode($params1)) . "</code></p>";
        
        $eventId = dbInsert($sql1, $params1);
        
        if ($eventId) {
            echo "<p style='color: green;'>✅ Inserción exitosa! Event ID: {$eventId}</p>";
            
            // Limpiar registro de prueba
            dbExecute("DELETE FROM visit_events WHERE id = ?", [$eventId]);
            echo "<p style='color: gray;'>🧹 Registro de prueba eliminado</p>";
        } else {
            echo "<p style='color: red;'>❌ dbInsert() retornó: " . var_export($eventId, true) . "</p>";
        }
        
        // Método 2: Usando dbExecute y dbLastInsertId
        echo "<h4>Método 2: Usando dbExecute()</h4>";
        
        $result = dbExecute($sql1, $params1);
        
        if ($result) {
            $lastId = $pdo->lastInsertId();
            echo "<p style='color: green;'>✅ dbExecute exitoso! Rows affected: {$result}</p>";
            echo "<p style='color: green;'>✅ Last Insert ID: {$lastId}</p>";
            
            // Limpiar
            dbExecute("DELETE FROM visit_events WHERE id = ?", [$lastId]);
            echo "<p style='color: gray;'>🧹 Registro de prueba eliminado</p>";
        } else {
            echo "<p style='color: red;'>❌ dbExecute() falló</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error en prueba de inserción: " . $e->getMessage() . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// ============================================
// 4. VERIFICAR FUNCIÓN dbInsert
// ============================================
echo "<h3>4. Verificar función dbInsert()</h3>";

if (function_exists('dbInsert')) {
    echo "<p style='color: green;'>✅ La función dbInsert() existe</p>";
    
    // Intentar obtener el código de la función
    $reflection = new ReflectionFunction('dbInsert');
    $filename = $reflection->getFileName();
    $startLine = $reflection->getStartLine();
    $endLine = $reflection->getEndLine();
    
    echo "<p>📁 Ubicación: <code>{$filename}</code> (líneas {$startLine}-{$endLine})</p>";
    
} else {
    echo "<p style='color: red;'>❌ La función dbInsert() NO existe</p>";
    echo "<p>⚠️ Esto explica el error. Debes usar dbExecute() y dbLastInsertId()</p>";
}

// ============================================
// 5. VERIFICAR ÚLTIMOS EVENTOS
// ============================================
echo "<h3>5. Últimos eventos registrados</h3>";

try {
    $lastEvents = dbQuery("
        SELECT ve.*, v.status as visit_status 
        FROM visit_events ve
        LEFT JOIN visits v ON ve.visit_id = v.id
        ORDER BY ve.event_time DESC 
        LIMIT 5
    ");
    
    if (empty($lastEvents)) {
        echo "<p style='color: orange;'>⚠️ No hay eventos registrados</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Visit ID</th><th>Tipo</th><th>Fecha/Hora</th><th>Estado Visita</th></tr>";
        
        foreach ($lastEvents as $event) {
            echo "<tr>";
            echo "<td>{$event['id']}</td>";
            echo "<td>{$event['visit_id']}</td>";
            echo "<td>{$event['event_type']}</td>";
            echo "<td>{$event['event_time']}</td>";
            echo "<td>{$event['visit_status']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// ============================================
// 6. RECOMENDACIONES
// ============================================
echo "<h3>6. 💡 Recomendaciones</h3>";

echo "<div style='background: #f0f0f0; padding: 15px; border-left: 4px solid #0066cc;'>";
echo "<p><strong>Si dbInsert() no existe:</strong></p>";
echo "<p>Reemplaza en events.php la línea:</p>";
echo "<pre>
\$eventId = dbInsert(\$sql, [...]);
</pre>";
echo "<p>Por:</p>";
echo "<pre>
\$result = dbExecute(\$sql, [...]);
if (\$result) {
    \$eventId = dbLastInsertId();
} else {
    \$eventId = false;
}
</pre>";
echo "</div>";

echo "<hr>";
echo "<p>✅ Diagnóstico completado</p>";
?>