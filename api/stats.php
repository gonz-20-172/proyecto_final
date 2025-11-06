<?php
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

$type = $_GET['type'] ?? null;

switch ($type) {
    case 'general':
        getGeneralStats();
        break;
        
    case 'by-technician':
        getStatsByTechnician();
        break;
        
    case 'by-client':
        getStatsByClient();
        break;
        
    case 'by-period':
        getStatsByPeriod();
        break;
        
    default:
        jsonError('Tipo de estadística no válido', 400);
}

function getGeneralStats() {
    requirePermission('visits', 'view');
    
    $stats = [
        'total_visits' => dbQueryOne("SELECT COUNT(*) as count FROM visits"),
        'completed_visits' => dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE status = 'completed'"),
        'pending_visits' => dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE status = 'scheduled'"),
        'in_progress_visits' => dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE status = 'in-progress'"),
        'total_clients' => dbQueryOne("SELECT COUNT(*) as count FROM clients"),
        'total_technicians' => dbQueryOne("SELECT COUNT(*) as count FROM users u INNER JOIN roles r ON u.role_id = r.id WHERE r.name = 'Técnico' AND u.active = 1"),
        'visits_today' => dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE scheduled_date = CURDATE()"),
        'visits_this_month' => dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE YEAR(scheduled_date) = YEAR(CURDATE()) AND MONTH(scheduled_date) = MONTH(CURDATE())")
    ];
    
    jsonSuccess($stats);
}

function getStatsByTechnician() {
    requirePermission('visits', 'view');
    
    $stats = dbQuery("
        SELECT 
            u.id, u.name,
            COUNT(v.id) as total_visits,
            SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) as completed_visits,
            SUM(CASE WHEN v.status = 'scheduled' THEN 1 ELSE 0 END) as pending_visits,
            SUM(CASE WHEN v.status = 'in-progress' THEN 1 ELSE 0 END) as in_progress_visits
        FROM users u
        INNER JOIN roles r ON u.role_id = r.id
        LEFT JOIN visits v ON u.id = v.technician_id
        WHERE r.name = 'Técnico' AND u.active = 1
        GROUP BY u.id, u.name
        ORDER BY completed_visits DESC
    ");
    
    jsonSuccess($stats);
}

function getStatsByClient() {
    requirePermission('visits', 'view');
    
    $stats = dbQuery("
        SELECT 
            c.id, c.name,
            COUNT(v.id) as total_visits,
            SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) as completed_visits,
            MAX(v.scheduled_date) as last_visit_date
        FROM clients c
        LEFT JOIN visits v ON c.id = v.client_id
        GROUP BY c.id, c.name
        HAVING total_visits > 0
        ORDER BY total_visits DESC
        LIMIT 20
    ");
    
    jsonSuccess($stats);
}

function getStatsByPeriod() {
    requirePermission('visits', 'view');
    
    $period = $_GET['period'] ?? 'month';
    
    switch ($period) {
        case 'week':
            $stats = dbQuery("
                SELECT 
                    DATE_FORMAT(scheduled_date, '%Y-%u') as period,
                    CONCAT('Semana ', WEEK(scheduled_date)) as label,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM visits
                WHERE scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
                GROUP BY period, label
                ORDER BY period ASC
            ");
            break;
            
        case 'year':
            $stats = dbQuery("
                SELECT 
                    YEAR(scheduled_date) as period,
                    YEAR(scheduled_date) as label,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM visits
                GROUP BY period, label
                ORDER BY period ASC
            ");
            break;
            
        default: // month
            $stats = dbQuery("
                SELECT 
                    DATE_FORMAT(scheduled_date, '%Y-%m') as period,
                    DATE_FORMAT(scheduled_date, '%b %Y') as label,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM visits
                WHERE scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY period, label
                ORDER BY period ASC
            ");
    }
    
    jsonSuccess($stats);
}