<?php
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();
requirePermission('visits', 'view');

if (!isMethod('POST')) {
    jsonError('Método no permitido', 405);
}

$data = getRequestBody();
$visitId = $data['visit_id'] ?? null;

if (!$visitId) {
    jsonError('ID de visita requerido', 400);
}

// Esta función está en includes/mail.php
$result = sendVisitReport($visitId);

if ($result['success']) {
    jsonSuccess(null, $result['message']);
} else {
    jsonError($result['error'], 500);
}