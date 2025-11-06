<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/reports.php';

$visitId = $_GET['id'] ?? null;

if (!$visitId) {
    die('ID de visita requerido');
}

$visit = getVisitData($visitId);

if (!$visit) {
    die('Visita no encontrada');
}

$duration = calculateDuration($visit['ingreso_time'], $visit['egreso_time']);
$appName = config('APP_NAME', 'Sistema de Visitas Técnicas');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Visita #<?php echo $visitId; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; font-size: 12px; color: #333; line-height: 1.6; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .header h1 { font-size: 24px; margin-bottom: 10px; }
        .header p { font-size: 14px; }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 16px; font-weight: bold; color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 5px; margin-bottom: 15px; }
        .info-grid { display: table; width: 100%; border-collapse: collapse; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; font-weight: bold; padding: 8px; background: #f8f9fa; border: 1px solid #dee2e6; width: 30%; }
        .info-value { display: table-cell; padding: 8px; border: 1px solid #dee2e6; }
        .coordinates { background: #e7f3ff; padding: 10px; border-radius: 5px; margin-top: 10px; }
        .footer { text-align: center; margin-top: 50px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 10px; color: #666; }
        .signature-box { margin-top: 60px; border-top: 2px solid #333; width: 300px; text-align: center; padding-top: 10px; }
        .notes-box { background: #fff8dc; padding: 15px; border-left: 4px solid #ffc107; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($appName); ?></h1>
            <p>Reporte de Visita Técnica</p>
        </div>

        <div class="section">
            <div class="section-title">Información de la Visita</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">ID de Visita:</div>
                    <div class="info-value">#<?php echo $visitId; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Fecha Programada:</div>
                    <div class="info-value"><?php echo formatDate($visit['scheduled_date'], 'd/m/Y'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Hora Programada:</div>
                    <div class="info-value"><?php echo $visit['scheduled_time'] ?: 'No especificada'; ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Estado:</div>
                    <div class="info-value">
                        <?php 
                        $estados = [
                            'scheduled' => 'Programada',
                            'in-progress' => 'En Progreso',
                            'completed' => 'Completada',
                            'cancelled' => 'Cancelada'
                        ];
                        echo $estados[$visit['status']] ?? $visit['status'];
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Información del Cliente</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Nombre:</div>
                    <div class="info-value"><?php echo htmlspecialchars($visit['client_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Dirección:</div>
                    <div class="info-value"><?php echo htmlspecialchars($visit['client_address'] ?: 'No especificada'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Teléfono:</div>
                    <div class="info-value"><?php echo htmlspecialchars($visit['client_phone'] ?: 'No especificado'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?php echo htmlspecialchars($visit['client_email'] ?: 'No especificado'); ?></div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Personal Asignado</div>
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Técnico:</div>
                    <div class="info-value"><?php echo htmlspecialchars($visit['technician_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Supervisor:</div>
                    <div class="info-value"><?php echo htmlspecialchars($visit['supervisor_name']); ?></div>
                </div>
            </div>
        </div>

        <?php if ($visit['ingreso_time'] || $visit['egreso_time']): ?>
        <div class="section">
            <div class="section-title">Registro de Tiempos</div>
            <div class="info-grid">
                <?php if ($visit['ingreso_time']): ?>
                <div class="info-row">
                    <div class="info-label">Hora de Ingreso:</div>
                    <div class="info-value"><?php echo formatDate($visit['ingreso_time'], 'd/m/Y H:i:s'); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if ($visit['egreso_time']): ?>
                <div class="info-row">
                    <div class="info-label">Hora de Egreso:</div>
                    <div class="info-value"><?php echo formatDate($visit['egreso_time'], 'd/m/Y H:i:s'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Duración Total:</div>
                    <div class="info-value"><strong><?php echo $duration; ?></strong></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($visit['ingreso_lat'] && $visit['ingreso_lng']): ?>
            <div class="coordinates">
                <strong>📍 Coordenadas de Ingreso:</strong><br>
                Latitud: <?php echo $visit['ingreso_lat']; ?>, 
                Longitud: <?php echo $visit['ingreso_lng']; ?>
            </div>
            <?php endif; ?>

            <?php if ($visit['egreso_lat'] && $visit['egreso_lng']): ?>
            <div class="coordinates">
                <strong>📍 Coordenadas de Egreso:</strong><br>
                Latitud: <?php echo $visit['egreso_lat']; ?>, 
                Longitud: <?php echo $visit['egreso_lng']; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($visit['notes']): ?>
        <div class="section">
            <div class="section-title">Observaciones</div>
            <div class="notes-box">
                <?php echo nl2br(htmlspecialchars($visit['notes'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="section">
            <div class="signature-box">
                <?php echo htmlspecialchars($visit['technician_name']); ?><br>
                <small>Técnico Responsable</small>
            </div>
        </div>

        <div class="footer">
            <p>Reporte generado automáticamente el <?php echo date('d/m/Y H:i:s'); ?></p>
            <p><?php echo htmlspecialchars($appName); ?> &copy; <?php echo date('Y'); ?></p>
        </div>
    </div>
</body>
</html>