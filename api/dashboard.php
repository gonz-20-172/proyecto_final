<?php
require_once __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

$currentUser = getCurrentUser();
$role = $currentUser->roleName;

$data = [];

switch ($role) {
    case 'Administrador':
        $data = getAdminDashboard();
        break;
        
    case 'Supervisor':
        $data = getSupervisorDashboard($currentUser->userId);
        break;
        
    case 'Técnico':
        $data = getTechnicianDashboard($currentUser->userId);
        break;
        
    default:
        $data = getBasicDashboard();
}

jsonSuccess($data);

function getAdminDashboard() {
    $totalUsers = dbQueryOne("SELECT COUNT(*) as count FROM users WHERE active = 1");
    $totalClients = dbQueryOne("SELECT COUNT(*) as count FROM clients");
    $totalVisits = dbQueryOne("SELECT COUNT(*) as count FROM visits");
    $completedVisits = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE status = 'completed'");
    $pendingVisits = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE status = 'scheduled'");
    $inProgressVisits = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE status = 'in-progress'");
    
    $visitsToday = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE scheduled_date = CURDATE()");
    $visitsThisWeek = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE YEARWEEK(scheduled_date, 1) = YEARWEEK(CURDATE(), 1)");
    $visitsThisMonth = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE YEAR(scheduled_date) = YEAR(CURDATE()) AND MONTH(scheduled_date) = MONTH(CURDATE())");
    
    $recentVisits = dbQuery("
        SELECT v.*, c.name as client_name, t.name as technician_name, s.name as supervisor_name
        FROM visits v
        INNER JOIN clients c ON v.client_id = c.id
        INNER JOIN users t ON v.technician_id = t.id
        INNER JOIN users s ON v.supervisor_id = s.id
        ORDER BY v.created_at DESC
        LIMIT 5
    ");
    
    $visitsByStatus = dbQuery("
        SELECT status, COUNT(*) as count 
        FROM visits 
        GROUP BY status
    ");
    
    $visitsByMonth = dbQuery("
        SELECT 
            DATE_FORMAT(scheduled_date, '%Y-%m') as month,
            COUNT(*) as count
        FROM visits
        WHERE scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    
    $topTechnicians = dbQuery("
        SELECT 
            u.id, u.name,
            COUNT(v.id) as total_visits,
            SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) as completed_visits
        FROM users u
        INNER JOIN visits v ON u.id = v.technician_id
        WHERE u.role_id = (SELECT id FROM roles WHERE name = 'Técnico')
        GROUP BY u.id, u.name
        ORDER BY completed_visits DESC
        LIMIT 5
    ");
    
    return [
        'totals' => [
            'users' => $totalUsers['count'],
            'clients' => $totalClients['count'],
            'visits' => $totalVisits['count'],
            'completed' => $completedVisits['count'],
            'pending' => $pendingVisits['count'],
            'in_progress' => $inProgressVisits['count']
        ],
        'periods' => [
            'today' => $visitsToday['count'],
            'this_week' => $visitsThisWeek['count'],
            'this_month' => $visitsThisMonth['count']
        ],
        'recent_visits' => $recentVisits,
        'visits_by_status' => $visitsByStatus,
        'visits_by_month' => $visitsByMonth,
        'top_technicians' => $topTechnicians
    ];
}

function getSupervisorDashboard($supervisorId) {
    $myTechnicians = dbQuery("
        SELECT id, name, email 
        FROM users 
        WHERE supervisor_id = ? AND active = 1
    ", [$supervisorId]);
    
    $technicianIds = array_column($myTechnicians, 'id');
    $technicianIdsStr = implode(',', $technicianIds ?: [0]);
    
    $totalMyVisits = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE supervisor_id = ?", [$supervisorId]);
    $completedMyVisits = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE supervisor_id = ? AND status = 'completed'", [$supervisorId]);
    $pendingMyVisits = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE supervisor_id = ? AND status = 'scheduled'", [$supervisorId]);
    $inProgressMyVisits = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE supervisor_id = ? AND status = 'in-progress'", [$supervisorId]);
    
    $visitsToday = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE supervisor_id = ? AND scheduled_date = CURDATE()", [$supervisorId]);
    $visitsThisWeek = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE supervisor_id = ? AND YEARWEEK(scheduled_date, 1) = YEARWEEK(CURDATE(), 1)", [$supervisorId]);
    $visitsThisMonth = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE supervisor_id = ? AND YEAR(scheduled_date) = YEAR(CURDATE()) AND MONTH(scheduled_date) = MONTH(CURDATE())", [$supervisorId]);
    
    $upcomingVisits = dbQuery("
        SELECT v.*, c.name as client_name, c.address as client_address, t.name as technician_name
        FROM visits v
        INNER JOIN clients c ON v.client_id = c.id
        INNER JOIN users t ON v.technician_id = t.id
        WHERE v.supervisor_id = ? 
        AND v.scheduled_date >= CURDATE()
        AND v.status IN ('scheduled', 'in-progress')
        ORDER BY v.scheduled_date ASC, v.scheduled_time ASC
        LIMIT 10
    ", [$supervisorId]);
    
    $technicianPerformance = [];
    if (!empty($technicianIds)) {
        $technicianPerformance = dbQuery("
            SELECT 
                u.id, u.name,
                COUNT(v.id) as total_visits,
                SUM(CASE WHEN v.status = 'completed' THEN 1 ELSE 0 END) as completed_visits,
                SUM(CASE WHEN v.status = 'scheduled' THEN 1 ELSE 0 END) as pending_visits
            FROM users u
            LEFT JOIN visits v ON u.id = v.technician_id
            WHERE u.id IN ({$technicianIdsStr})
            GROUP BY u.id, u.name
            ORDER BY completed_visits DESC
        ");
    }
    
    $visitsByStatus = dbQuery("
        SELECT status, COUNT(*) as count 
        FROM visits 
        WHERE supervisor_id = ?
        GROUP BY status
    ", [$supervisorId]);
    
    return [
        'totals' => [
            'my_technicians' => count($myTechnicians),
            'total_visits' => $totalMyVisits['count'],
            'completed' => $completedMyVisits['count'],
            'pending' => $pendingMyVisits['count'],
            'in_progress' => $inProgressMyVisits['count']
        ],
        'periods' => [
            'today' => $visitsToday['count'],
            'this_week' => $visitsThisWeek['count'],
            'this_month' => $visitsThisMonth['count']
        ],
        'my_technicians' => $myTechnicians,
        'upcoming_visits' => $upcomingVisits,
        'technician_performance' => $technicianPerformance,
        'visits_by_status' => $visitsByStatus
    ];
}

function getTechnicianDashboard($technicianId) {
    $totalMyVisits = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE technician_id = ?", [$technicianId]);
    $completedMyVisits = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE technician_id = ? AND status = 'completed'", [$technicianId]);
    $pendingMyVisits = dbQueryOne("SELECT COUNT(*) as count FROM visits WHERE technician_id = ? AND status = 'scheduled'", [$technicianId]);
    
    $visitsToday = dbQuery("
        SELECT v.*, 
               c.name as client_name, c.address as client_address, c.phone as client_phone, c.lat as client_lat, c.lng as client_lng,
               s.name as supervisor_name,
               (SELECT event_time FROM visit_events WHERE visit_id = v.id AND event_type = 'ingreso' ORDER BY event_time DESC LIMIT 1) as ingreso_time,
               (SELECT event_time FROM visit_events WHERE visit_id = v.id AND event_type = 'egreso' ORDER BY event_time DESC LIMIT 1) as egreso_time
        FROM visits v
        INNER JOIN clients c ON v.client_id = c.id
        INNER JOIN users s ON v.supervisor_id = s.id
        WHERE v.technician_id = ? AND v.scheduled_date = CURDATE()
        ORDER BY v.scheduled_time ASC
    ", [$technicianId]);
    
    $upcomingVisits = dbQuery("
        SELECT v.*, c.name as client_name, c.address as client_address, s.name as supervisor_name
        FROM visits v
        INNER JOIN clients c ON v.client_id = c.id
        INNER JOIN users s ON v.supervisor_id = s.id
        WHERE v.technician_id = ? 
        AND v.scheduled_date > CURDATE()
        AND v.status IN ('scheduled', 'in-progress')
        ORDER BY v.scheduled_date ASC, v.scheduled_time ASC
        LIMIT 5
    ", [$technicianId]);
    
    $recentCompleted = dbQuery("
        SELECT v.*, c.name as client_name
        FROM visits v
        INNER JOIN clients c ON v.client_id = c.id
        WHERE v.technician_id = ? AND v.status = 'completed'
        ORDER BY v.updated_at DESC
        LIMIT 5
    ", [$technicianId]);
    
    $visitsByStatus = dbQuery("
        SELECT status, COUNT(*) as count 
        FROM visits 
        WHERE technician_id = ?
        GROUP BY status
    ", [$technicianId]);
    
    $monthlyStats = dbQuery("
        SELECT 
            DATE_FORMAT(scheduled_date, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM visits
        WHERE technician_id = ?
        AND scheduled_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ", [$technicianId]);
    
    return [
        'totals' => [
            'total_visits' => $totalMyVisits['count'],
            'completed' => $completedMyVisits['count'],
            'pending' => $pendingMyVisits['count'],
            'today' => count($visitsToday)
        ],
        'visits_today' => $visitsToday,
        'upcoming_visits' => $upcomingVisits,
        'recent_completed' => $recentCompleted,
        'visits_by_status' => $visitsByStatus,
        'monthly_stats' => $monthlyStats
    ];
}

function getBasicDashboard() {
    return [
        'message' => 'Dashboard básico',
        'totals' => []
    ];
}