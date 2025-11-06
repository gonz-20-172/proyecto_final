<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/pdf_generator.php';

echo "=== Test de Generación de PDF ===\n\n";

$visitId = 4;

echo "📝 Probando conexión a la base de datos...\n";

try {
    // Test simple
    $test = dbQueryOne("SELECT COUNT(*) as total FROM visits");
    echo "✅ Conexión exitosa. Total de visitas: {$test['total']}\n\n";
} catch (Exception $e) {
    die("❌ Error de conexión: " . $e->getMessage() . "\n");
}

echo "📝 Buscando visita con ID #{$visitId}...\n";

// Primero, verificar si existe la visita
$checkVisit = dbQueryOne("SELECT * FROM visits WHERE id = ?", [$visitId]);

if (!$checkVisit) {
    die("❌ La visita #{$visitId} NO existe en la base de datos\n");
}

echo "✅ Visita #{$visitId} encontrada\n";
echo "   Estado: {$checkVisit['status']}\n";
echo "   Cliente ID: {$checkVisit['client_id']}\n";
echo "   Técnico ID: {$checkVisit['technician_id']}\n";
echo "   Supervisor ID: {$checkVisit['supervisor_id']}\n\n";

// Verificar relaciones
echo "📝 Verificando relaciones...\n";

$client = dbQueryOne("SELECT id, name FROM clients WHERE id = ?", [$checkVisit['client_id']]);
echo ($client ? "✅" : "❌") . " Cliente: " . ($client ? $client['name'] : "NO ENCONTRADO") . "\n";

$tech = dbQueryOne("SELECT id, name FROM users WHERE id = ?", [$checkVisit['technician_id']]);
echo ($tech ? "✅" : "❌") . " Técnico: " . ($tech ? $tech['name'] : "NO ENCONTRADO") . "\n";

$super = dbQueryOne("SELECT id, name FROM users WHERE id = ?", [$checkVisit['supervisor_id']]);
echo ($super ? "✅" : "❌") . " Supervisor: " . ($super ? $super['name'] : "NO ENCONTRADO") . "\n\n";

// Probar query simple primero
echo "📝 Probando query simple sin JOIN...\n";
$simpleVisit = dbQueryOne("SELECT * FROM visits WHERE id = ?", [$visitId]);
if ($simpleVisit) {
    echo "✅ Query simple funciona\n\n";
} else {
    die("❌ Query simple falló\n");
}

// Ahora probar con JOIN paso a paso
echo "📝 Probando JOIN con cliente...\n";
$withClient = dbQueryOne("
    SELECT v.*, c.name as client_name
    FROM visits v
    LEFT JOIN clients c ON v.client_id = c.id
    WHERE v.id = ?
", [$visitId]);

if (!$withClient) {
    echo "❌ JOIN con cliente falló\n";
    echo "Intentando query directa...\n";
    
    // Query directa para debug
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT v.*, c.name as client_name
        FROM visits v
        LEFT JOIN clients c ON v.client_id = c.id
        WHERE v.id = ?
    ");
    $stmt->execute([$visitId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Resultado directo: " . ($result ? "ENCONTRADO" : "NO ENCONTRADO") . "\n";
    if ($result) {
        echo "Columnas: " . implode(', ', array_keys($result)) . "\n";
    }
    
    die("\n❌ Problema con la función dbQueryOne\n");
}

echo "✅ JOIN con cliente funciona: {$withClient['client_name']}\n\n";

// Query completa
echo "📝 Obteniendo datos completos...\n";

$visit = dbQueryOne("
    SELECT 
        v.*,
        c.name as client_name,
        c.email as client_email,
        c.phone as client_phone,
        c.address as client_address,
        t.name as technician_name,
        t.email as technician_email,
        s.name as supervisor_name,
        s.email as supervisor_email
    FROM visits v
    LEFT JOIN clients c ON v.client_id = c.id
    LEFT JOIN users t ON v.technician_id = t.id
    LEFT JOIN users s ON v.supervisor_id = s.id
    WHERE v.id = ?
", [$visitId]);

echo "🧪 Ejecutando query completa directamente...\n";

$pdo = getDatabase();
$stmt = $pdo->prepare("
    SELECT 
        v.*,
        c.name as client_name,
        c.email as client_email,
        c.phone as client_phone,
        c.address as client_address,
        t.name as technician_name,
        t.email as technician_email,
        s.name as supervisor_name,
        s.email as supervisor_email
    FROM visits v
    LEFT JOIN clients c ON v.client_id = c.id
    LEFT JOIN users t ON v.technician_id = t.id
    LEFT JOIN users s ON v.supervisor_id = s.id
    WHERE v.id = ?
");
$stmt->execute([$visitId]);
$direct = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$direct) {
    echo "❌ Query directa también falló.\n";
} else {
    echo "✅ Query directa devuelve resultados.\n";
    echo "Columnas: " . implode(', ', array_keys($direct)) . "\n";
}

if (!$visit) {
    die("❌ Error: Query completa no devolvió resultados\n");
}

echo "✅ Datos completos obtenidos\n";
echo "   Cliente: {$visit['client_name']}\n";
echo "   Técnico: {$visit['technician_name']}\n";
echo "   Supervisor: {$visit['supervisor_name']}\n\n";

// Obtener eventos
echo "📝 Obteniendo eventos...\n";
$events = dbQuery("
    SELECT * FROM visit_events
    WHERE visit_id = ?
    ORDER BY event_time ASC
", [$visitId]);

$visit['events'] = $events;
echo "✅ Eventos encontrados: " . count($events) . "\n";

if (count($events) > 0) {
    foreach ($events as $event) {
        echo "   - {$event['event_type']}: {$event['event_time']}\n";
    }
}
echo "\n";

echo "🔨 Generando PDF...\n";

// Generar PDF
$pdfPath = generateVisitPDF($visit);

if ($pdfPath && file_exists($pdfPath)) {
    echo "✅ PDF generado exitosamente!\n";
    echo "📄 Ubicación: {$pdfPath}\n";
    echo "📦 Tamaño: " . number_format(filesize($pdfPath)) . " bytes\n";
    echo "\n✨ ¡Prueba completada exitosamente!\n";
} else {
    echo "❌ Error al generar PDF\n";
    
    // Mostrar log
    $logFile = __DIR__ . '/logs/php_errors.log';
    if (file_exists($logFile)) {
        echo "\nÚltimas 15 líneas del log:\n";
        echo "==========================================\n";
        $lines = file($logFile);
        echo implode('', array_slice($lines, -15));
        echo "==========================================\n";
    }
}