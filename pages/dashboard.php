<?php
require_once __DIR__ . '/../vendor/autoload.php';
requireAuth();
$currentUser = getCurrentUserFromDB();
$userName = $currentUser['name'] ?? 'Usuario';
$userRole = $currentUser['role_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Visitas Técnicas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <style>
        .stat-card { border-radius: 10px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: transform 0.3s; margin-bottom: 20px; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { font-size: 3rem; opacity: 0.3; }
        .chart-container { position: relative; height: 300px; }
        .widget-card { border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="/pages/dashboard.php">
                <i class="bi bi-clipboard-check"></i> Sistema de Visitas
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($userName); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header"><?php echo htmlspecialchars($userRole); ?></h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" id="logoutBtn">
                                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 sidebar p-0">
                <div class="nav flex-column">
                    <a class="nav-link active" href="/pages/dashboard.php">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                    <?php if (hasPermission('clients', 'view')): ?>
                    <a class="nav-link" href="/pages/clients.php">
                        <i class="bi bi-people"></i> Clientes
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission('visits', 'view')): ?>
                    <a class="nav-link" href="/pages/visits.php">
                        <i class="bi bi-calendar-check"></i> Visitas
                    </a>
                    <a class="nav-link" href="/pages/history.php">
                        <i class="bi bi-clock-history"></i> Historial
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission('users', 'view')): ?>
                    <a class="nav-link" href="/pages/users.php">
                        <i class="bi bi-person-badge"></i> Usuarios
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-10">
                <div class="content">
                    <h2 class="mb-4">
                        <i class="bi bi-speedometer2"></i> Dashboard 
                        <small class="text-muted">(<?php echo htmlspecialchars($userRole); ?>)</small>
                    </h2>

                    <div id="dashboardContent">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <script>
        const userRole = '<?php echo $userRole; ?>';
        let dashboardData = {};

        document.addEventListener('DOMContentLoaded', function() {
            loadDashboard();
        });

        async function loadDashboard() {
            try {
                const response = await App.request('/api/dashboard.php');
                if (response.success) {
                    dashboardData = response.data;
                    renderDashboard();
                } else {
                    document.getElementById('dashboardContent').innerHTML = 
                        '<div class="alert alert-danger">Error al cargar dashboard</div>';
                }
            } catch (error) {
                document.getElementById('dashboardContent').innerHTML = 
                    '<div class="alert alert-danger">Error de conexión</div>';
            }
        }

        function renderDashboard() {
            switch (userRole) {
                case 'Administrador':
                    renderAdminDashboard();
                    break;
                case 'Supervisor':
                    renderSupervisorDashboard();
                    break;
                case 'Técnico':
                    renderTechnicianDashboard();
                    break;
                default:
                    document.getElementById('dashboardContent').innerHTML = 
                        '<div class="alert alert-info">Dashboard no disponible para este rol</div>';
            }
        }

        function renderAdminDashboard() {
            const html = `
                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Usuarios</h6>
                                        <h2 class="mb-0">${dashboardData.totals.users}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-people text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Clientes</h6>
                                        <h2 class="mb-0">${dashboardData.totals.clients}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-building text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Visitas</h6>
                                        <h2 class="mb-0">${dashboardData.totals.visits}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-calendar-check text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Completadas</h6>
                                        <h2 class="mb-0">${dashboardData.totals.completed}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-check-circle text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Period Stats -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card widget-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Hoy</h5>
                                <h2 class="text-primary">${dashboardData.periods.today}</h2>
                                <p class="text-muted">visitas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card widget-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Esta Semana</h5>
                                <h2 class="text-success">${dashboardData.periods.this_week}</h2>
                                <p class="text-muted">visitas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card widget-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Este Mes</h5>
                                <h2 class="text-info">${dashboardData.periods.this_month}</h2>
                                <p class="text-muted">visitas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card widget-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Visitas por Estado</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card widget-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Visitas por Mes</h5>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tables -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card widget-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Top Técnicos</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Técnico</th>
                                                <th class="text-center">Total</th>
                                                <th class="text-center">Completadas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${dashboardData.top_technicians.map(t => `
                                                <tr>
                                                    <td>${t.name}</td>
                                                    <td class="text-center">${t.total_visits}</td>
                                                    <td class="text-center"><span class="badge bg-success">${t.completed_visits}</span></td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card widget-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Visitas Recientes</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>Técnico</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${dashboardData.recent_visits.map(v => `
                                                <tr>
                                                    <td>${v.client_name}</td>
                                                    <td>${v.technician_name}</td>
                                                    <td><span class="badge bg-${getStatusColor(v.status)}">${getStatusText(v.status)}</span></td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('dashboardContent').innerHTML = html;
            
            renderStatusChart();
            renderMonthChart();
        }

        function renderSupervisorDashboard() {
            const html = `
                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Mis Técnicos</h6>
                                        <h2 class="mb-0">${dashboardData.totals.my_technicians}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-people text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Visitas</h6>
                                        <h2 class="mb-0">${dashboardData.totals.total_visits}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-calendar-check text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Completadas</h6>
                                        <h2 class="mb-0">${dashboardData.totals.completed}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-check-circle text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Pendientes</h6>
                                        <h2 class="mb-0">${dashboardData.totals.pending}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-clock text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Period Stats -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card widget-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Hoy</h5>
                                <h2 class="text-primary">${dashboardData.periods.today}</h2>
                                <p class="text-muted">visitas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card widget-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Esta Semana</h5>
                                <h2 class="text-success">${dashboardData.periods.this_week}</h2>
                                <p class="text-muted">visitas</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card widget-card">
                            <div class="card-body text-center">
                                <h5 class="card-title">Este Mes</h5>
                                <h2 class="text-info">${dashboardData.periods.this_month}</h2>
                                <p class="text-muted">visitas</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts & Tables -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card widget-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Rendimiento del Equipo</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Técnico</th>
                                                <th class="text-center">Total</th>
                                                <th class="text-center">Completadas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${dashboardData.technician_performance.map(t => `
                                                <tr>
                                                    <td>${t.name}</td>
                                                    <td class="text-center">${t.total_visits || 0}</td>
                                                    <td class="text-center"><span class="badge bg-success">${t.completed_visits || 0}</span></td>
                                                </tr>
                                            `).join('')}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card widget-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Próximas Visitas</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    ${dashboardData.upcoming_visits.slice(0, 5).map(v => `
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">${v.client_name}</h6>
                                                <small>${formatDate(v.scheduled_date)}</small>
                                            </div>
                                            <p class="mb-1"><small>${v.technician_name}</small></p>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('dashboardContent').innerHTML = html;
        }

        function renderTechnicianDashboard() {
            const html = `
                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Mis Visitas</h6>
                                        <h2 class="mb-0">${dashboardData.totals.total_visits}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-calendar-check text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Completadas</h6>
                                        <h2 class="mb-0">${dashboardData.totals.completed}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-check-circle text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Pendientes</h6>
                                        <h2 class="mb-0">${dashboardData.totals.pending}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-clock text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted mb-2">Hoy</h6>
                                        <h2 class="mb-0">${dashboardData.totals.today}</h2>
                                    </div>
                                    <div class="stat-icon">
                                        <i class="bi bi-calendar-day text-info"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Visits -->
                ${dashboardData.visits_today.length > 0 ? `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Tienes <strong>${dashboardData.visits_today.length}</strong> visita(s) programada(s) para hoy.
                </div>
                <div class="card widget-card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-day"></i> Visitas de Hoy</h5>
                    </div>
                    <div class="card-body">
                        ${dashboardData.visits_today.map(v => {
                            const hasIngreso = v.ingreso_time !== null;
                            const hasEgreso = v.egreso_time !== null;
                            return `
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <h5>${v.client_name}</h5>
                                        <p class="mb-1"><i class="bi bi-geo-alt"></i> ${v.client_address || 'Sin dirección'}</p>
                                        <p class="mb-1"><i class="bi bi-clock"></i> ${v.scheduled_time || 'No especificada'}</p>
                                        ${hasIngreso ? `<p class="text-success mb-1"><i class="bi bi-check"></i> Ingreso: ${formatDateTime(v.ingreso_time)}</p>` : ''}
                                        ${hasEgreso ? `<p class="text-success mb-1"><i class="bi bi-check"></i> Egreso: ${formatDateTime(v.egreso_time)}</p>` : ''}
                                        <div class="mt-2">
                                            ${v.client_lat && v.client_lng ? `
                                                <a href="https://www.google.com/maps/dir/?api=1&destination=${v.client_lat},${v.client_lng}" 
                                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-map"></i> Cómo Llegar
                                                </a>
                                            ` : ''}
                                            ${!hasIngreso ? `
                                                <a href="/pages/visits.php" class="btn btn-sm btn-success">
                                                    <i class="bi bi-play"></i> Iniciar
                                                </a>
                                            ` : ''}
                                            ${hasIngreso && !hasEgreso ? `
                                                <a href="/pages/visits.php" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-stop"></i> Finalizar
                                                </a>
                                            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
                ` : '<div class="alert alert-success"><i class="bi bi-check-circle"></i> No tienes visitas programadas para hoy.</div>'}

                <!-- Upcoming & Recent -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card widget-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Próximas Visitas</h5>
                            </div>
                            <div class="card-body">
                                ${dashboardData.upcoming_visits.length > 0 ? `
                                    <div class="list-group">
                                        ${dashboardData.upcoming_visits.map(v => `
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1">${v.client_name}</h6>
                                                    <small>${formatDate(v.scheduled_date)}</small>
                                                </div>
                                                <p class="mb-0"><small>${v.client_address || 'Sin dirección'}</small></p>
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : '<p class="text-muted">No hay próximas visitas</p>'}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card widget-card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Visitas Completadas Recientes</h5>
                            </div>
                            <div class="card-body">
                                ${dashboardData.recent_completed.length > 0 ? `
                                    <div class="list-group">
                                        ${dashboardData.recent_completed.map(v => `
                                            <div class="list-group-item">
                                                <h6 class="mb-1">${v.client_name}</h6>
                                                <small class="text-muted">${formatDate(v.scheduled_date)}</small>
                                                <span class="badge bg-success float-end">Completada</span>
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : '<p class="text-muted">No hay visitas completadas</p>'}
                            </div>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('dashboardContent').innerHTML = html;
        }

        function renderStatusChart() {
            const ctx = document.getElementById('statusChart');
            if (!ctx) return;

            const statusMap = {
                'scheduled': 'Programadas',
                'in-progress': 'En Progreso',
                'completed': 'Completadas',
                'cancelled': 'Canceladas'
            };

            const labels = dashboardData.visits_by_status.map(s => statusMap[s.status] || s.status);
            const data = dashboardData.visits_by_status.map(s => s.count);

            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: [
                            'rgba(13, 202, 240, 0.8)',
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(25, 135, 84, 0.8)',
                            'rgba(220, 53, 69, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        function renderMonthChart() {
            const ctx = document.getElementById('monthChart');
            if (!ctx) return;

            const labels = dashboardData.visits_by_month.map(m => {
                const [year, month] = m.month.split('-');
                const date = new Date(year, month - 1);
                return date.toLocaleDateString('es', { month: 'short', year: 'numeric' });
            });
            const data = dashboardData.visits_by_month.map(m => m.count);

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Visitas',
                        data: data,
                        borderColor: 'rgba(102, 126, 234, 1)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        function formatDate(dateString) {
            const date = new Date(dateString + 'T00:00:00');
            return date.toLocaleDateString('es-GT');
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('es-GT');
        }

        function getStatusColor(status) {
            const colors = {
                'scheduled': 'info',
                'in-progress': 'warning',
                'completed': 'success',
                'cancelled': 'danger'
            };
            return colors[status] || 'secondary';
        }

        function getStatusText(status) {
            const texts = {
                'scheduled': 'Programada',
                'in-progress': 'En Progreso',
                'completed': 'Completada',
                'cancelled': 'Cancelada'
            };
            return texts[status] || status;
        }

        document.getElementById('logoutBtn').addEventListener('click', async function(e) {
            e.preventDefault();
            try {
                await App.request('/api/auth.php?action=logout', { method: 'POST' });
                window.location.href = '/pages/login.php';
            } catch (error) {
                window.location.href = '/pages/login.php';
            }
        });
    </script>
</body>
</html>