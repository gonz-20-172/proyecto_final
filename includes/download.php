<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/functions.php';
 // Include the file with the function definition

requireAuth();
requirePermission('visits', 'view');

$visitId = $_GET['id'] ?? null;

if (!$visitId) {
    die('ID de visita requerido');
}

generateVisitReport($visitId, false);