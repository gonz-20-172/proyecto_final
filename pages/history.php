<?php
require_once __DIR__ . '/../vendor/autoload.php';
requireAuth();
requirePermission('visits', 'view');

$currentUser = getCurrentUserFromDB();
$userName = $currentUser['name'] ?? 'Usuario';
$userRole = $currentUser['role_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Visitas - Sistema de Visitas Técnicas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
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
                    <a class="nav-link" href="/pages/dashboard.php">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                    <?php if (hasPermission('clients', 'view')): ?>
                    <a class="nav-link" href="/pages/clients.php">
                        <i class="bi bi-people"></i> Clientes
                    </a>
                    <?php endif; ?>
                    <a class="nav-link" href="/pages/visits.php">
                        <i class="bi bi-calendar-check"></i> Visitas
                    </a>
                    <a class="nav-link active" href="/pages/history.php">
                        <i class="bi bi-clock-history"></i> Historial
                    </a>
                    <?php if (hasPermission('users', 'view')): ?>
                    <a class="nav-link" href="/pages/users.php">
                        <i class="bi bi-person-badge"></i> Usuarios
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-10">
                <div class="content">
                    <h2 class="mb-4"><i class="bi bi-clock-history"></i> Historial de Visitas Completadas</h2>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Fecha desde:</label>
                                    <input type="date" class="form-control" id="dateFrom">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Fecha hasta:</label>
                                    <input type="date" class="form-control" id="dateTo">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Técnico:</label>
                                    <select class="form-select" id="technicianFilter">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Cliente:</label>
                                    <select class="form-select" id="clientFilter">
                                        <option value="">Todos</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button class="btn btn-primary w-100" onclick="loadHistory()">
                                        <i class="bi bi-search"></i> Buscar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="alertContainer"></div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Fecha</th>
                                            <th>Cliente</th>
                                            <th>Técnico</th>
                                            <th>Duración</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <tr>
                                            <td colspan="6" class="text-center">
                                                <div class="spinner-border text-primary" role="status"></div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js"></script>

    <script>
document.addEventListener('DOMContentLoaded', async () => {
    console.log("📅 Cargando filtros de historial...");
    await loadFilters();
    setDefaultDates();
    await loadHistory();
});

// ============================================
// CARGAR FILTROS DE TÉCNICOS Y CLIENTES
// ============================================
async function loadFilters() {
    try {
        // 🔹 Cargar técnicos
        const techResponse = await App.request('/api/users.php?role=Técnico');
        const techSelect = document.getElementById('technicianFilter');
        techSelect.innerHTML = '<option value="">Todos</option>';

        if (techResponse.success && Array.isArray(techResponse.data)) {
            techResponse.data.forEach(t => {
                techSelect.innerHTML += `<option value="${t.id}">${t.name}</option>`;
            });
        }

        // 🔹 Cargar clientes
        const clientResponse = await App.request('/api/clients.php');
        const clientSelect = document.getElementById('clientFilter');
        clientSelect.innerHTML = '<option value="">Todos</option>';

        if (clientResponse.success && Array.isArray(clientResponse.data)) {
            clientResponse.data.forEach(c => {
                clientSelect.innerHTML += `<option value="${c.id}">${c.name}</option>`;
            });
        }

        console.log("✅ Filtros cargados correctamente");
    } catch (error) {
        console.error("❌ Error al cargar filtros:", error);
        App.showAlert("Error al cargar filtros de búsqueda.", "error");
    }
}

// ============================================
// FUNCIÓN PRINCIPAL PARA CARGAR HISTORIAL
// ============================================
async function loadHistory() {
    try {
        const from = document.getElementById('dateFrom').value;
        const to = document.getElementById('dateTo').value;
        const technician = document.getElementById('technicianFilter').value;
        const client = document.getElementById('clientFilter').value;

        const params = new URLSearchParams({ from, to, technician, client, status: 'completed' });

        console.log("🔎 Buscando historial con parámetros:", Object.fromEntries(params));

        const response = await App.request(`/api/visits.php?${params.toString()}`, { method: 'GET' });

        if (response.success) {
            console.log("✅ Resultados obtenidos:", response.data);
            renderHistory(response.data);
        } else {
            App.showAlert(response.error || "No se encontraron resultados.", "warning");
        }
    } catch (error) {
        console.error("❌ Error al cargar historial:", error);
        App.showAlert("Error al buscar historial.", "error");
    }
}

// ============================================
// RENDERIZAR TABLA DE HISTORIAL
// ============================================
function renderHistory(visitsList) {
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = '';

    if (!visitsList || visitsList.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted">No se encontraron visitas</td></tr>`;
        return;
    }

    tbody.innerHTML = visitsList.map(visit => `
        <tr>
            <td>#${visit.id}</td>
            <td>${formatDate(visit.scheduled_date)}</td>
            <td><strong>${visit.client_name}</strong></td>
            <td>${visit.technician_name}</td>
            <td>${calculateDuration(visit)}</td>
            <td>
                <a href="../reports/visit-report.php?id=${visit.id}" target="_blank" class="btn btn-sm btn-primary">
                    <i class="bi bi-file-pdf"></i> Ver PDF
                </a>
                <button class="btn btn-sm btn-success" onclick="resendEmail(${visit.id})">
                    <i class="bi bi-envelope"></i> Reenviar
                </button>
            </td>
        </tr>
    `).join('');
}

// ============================================
// UTILIDADES
// ============================================
function setDefaultDates() {
    const today = new Date().toISOString().split('T')[0];
    const monthAgo = new Date();
    monthAgo.setMonth(monthAgo.getMonth() - 1);
    document.getElementById('dateFrom').value = monthAgo.toISOString().split('T')[0];
    document.getElementById('dateTo').value = today;
}

function formatDate(dateString) {
    const date = new Date(dateString + 'T00:00:00');
    return date.toLocaleDateString('es-GT');
}

function calculateDuration(visit) {
    return '--';
}

async function resendEmail(visitId) {
    if (!confirm('¿Desea reenviar el reporte por correo?')) return;

    try {
        const response = await App.request(`/api/reports.php?action=resend&visit_id=${visitId}`, { method: 'POST' });
        if (response.success) {
            App.showAlert('Correo enviado exitosamente', 'success', 'alertContainer');
        } else {
            App.showAlert(response.error || 'Error al enviar correo', 'error', 'alertContainer');
        }
    } catch (error) {
        App.showAlert('Error de conexión', 'error', 'alertContainer');
    }
}

// ============================================
// LOGOUT
// ============================================
document.getElementById('logoutBtn').addEventListener('click', async function(e) {
    e.preventDefault();
    try {
        await App.request('/api/auth.php?action=logout', { method: 'POST' });
        window.location.href = '/pages/login.php';
    } catch {
        window.location.href = '/pages/login.php';
    }
});
</script>
</body>
</html>
