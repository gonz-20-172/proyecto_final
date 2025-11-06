// includes/pdf.php
function generarReportePDF($visit_id) {
    // Obtener datos de la visita
    $visit = getVisitData($visit_id);
    $events = getVisitEvents($visit_id);
    
    // Calcular duración
    $ingreso = strtotime($events['ingreso']['timestamp']);
    $egreso = strtotime($events['egreso']['timestamp']);
    $duracion = round(($egreso - $ingreso) / 60); // minutos
    
    // HTML del reporte
    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial; padding: 20px; }
            .header { text-align: center; border-bottom: 2px solid #333; }
            .section { margin: 20px 0; }
            table { width: 100%; border-collapse: collapse; }
            td, th { padding: 8px; border: 1px solid #ddd; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>REPORTE DE VISITA TÉCNICA</h2>
            <p>Fecha: {$visit['scheduled_date']}</p>
        </div>
        
        <div class='section'>
            <h3>Datos del Cliente</h3>
            <table>
                <tr><td><strong>Nombre:</strong></td><td>{$visit['client_name']}</td></tr>
                <tr><td><strong>Dirección:</strong></td><td>{$visit['address']}</td></tr>
                <tr><td><strong>Teléfono:</strong></td><td>{$visit['phone']}</td></tr>
            </table>
        </div>
        
        <div class='section'>
            <h3>Datos de la Visita</h3>
            <table>
                <tr><td><strong>Técnico:</strong></td><td>{$visit['technician_name']}</td></tr>
                <tr><td><strong>Hora de Ingreso:</strong></td><td>{$events['ingreso']['timestamp']}</td></tr>
                <tr><td><strong>Hora de Egreso:</strong></td><td>{$events['egreso']['timestamp']}</td></tr>
                <tr><td><strong>Duración:</strong></td><td>{$duracion} minutos</td></tr>
            </table>
        </div>
        
        <div class='section'>
            <h3>Observaciones</h3>
            <p>{$visit['notes']}</p>
        </div>
        
        <div class='section'>
            <p><strong>Técnico responsable:</strong> {$visit['technician_name']}</p>
        </div>
    </body>
    </html>
    ";
    
    return $html;
}