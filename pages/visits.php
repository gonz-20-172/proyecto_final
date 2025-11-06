<?php
require_once __DIR__ . '/../vendor/autoload.php';
requireAuth();
requirePermission('visits', 'view');

$currentUser = getCurrentUserFromDB();
$canCreate = hasPermission('visits', 'create');
$canEdit = hasPermission('visits', 'edit');
$isTechnician = $currentUser['role_name'] === 'Técnico';
$userName = $currentUser['name'] ?? 'Usuario';
$userRole = $currentUser['role_name'] ?? '';
$googleMapsKey = config('GOOGLE_MAPS_KEY', '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitas - Sistema de Visitas Técnicas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <style>
        #map { height: 400px; width: 100%; border-radius: 8px; }
        .visit-card { transition: all 0.3s; margin-bottom: 15px; }
        .visit-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .status-scheduled { background-color: #0dcaf0; color: white; }
        .status-in-progress { background-color: #ffc107; color: white; }
        .status-completed { background-color: #198754; color: white; }
        .status-cancelled { background-color: #dc3545; color: white; }
        .timer { font-size: 1.5rem; font-weight: bold; color: #667eea; }
    </style>
</head>
<body>
    <!-- ============================================ -->
    <!-- NAVBAR -->
    <!-- ============================================ -->
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

    <!-- ============================================ -->
    <!-- CONTENIDO PRINCIPAL -->
    <!-- ============================================ -->
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
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
                    <a class="nav-link active" href="/pages/visits.php">
                        <i class="bi bi-calendar-check"></i> Visitas
                    </a>
                    <a class="nav-link" href="/pages/history.php">
                        <i class="bi bi-clock-history"></i> Historial
                    </a>
                    <?php if (hasPermission('users', 'view')): ?>
                    <a class="nav-link" href="/pages/users.php">
                        <i class="bi bi-person-badge"></i> Usuarios
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contenido -->
            <div class="col-md-10">
                <div class="content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-calendar-check"></i> 
                            <?php echo $isTechnician ? 'Mis Visitas' : 'Gestión de Visitas'; ?>
                        </h2>
                        <?php if ($canCreate): ?>
                        <button class="btn btn-primary btn-gradient" data-bs-toggle="modal" data-bs-target="#visitModal" onclick="openCreateModal()">
                            <i class="bi bi-plus-circle"></i> Nueva Visita
                        </button>
                        <?php endif; ?>
                    </div>

                    <?php if ($isTechnician): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Mostrando tus visitas programadas. 
                        Registra <strong>Ingreso</strong> al llegar y <strong>Egreso</strong> al terminar.
                    </div>
                    <?php endif; ?>

                    <div id="alertContainer"></div>

                    <?php if ($isTechnician): ?>
                    <!-- Vista Técnico: Cards -->
                    <div id="visitsContainer"></div>
                    <?php else: ?>
                    <!-- Vista Supervisor/Admin: Tabla -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Filtrar por estado:</label>
                                    <select class="form-select" id="statusFilter">
                                        <option value="">Todos</option>
                                        <option value="scheduled">Programada</option>
                                        <option value="in-progress">En Progreso</option>
                                        <option value="completed">Completada</option>
                                        <option value="cancelled">Cancelada</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Hora</th>
                                            <th>Cliente</th>
                                            <th>Técnico</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="visitsTableBody">
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- MODAL CREAR/EDITAR VISITA -->
    <!-- ============================================ -->
    <div class="modal fade" id="visitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header gradient-bg text-white">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="bi bi-calendar-plus"></i> Nueva Visita
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="modalAlert"></div>
                    <form id="visitForm">
                        <input type="hidden" id="visitId">
                        
                        <div class="mb-3">
                            <label for="client_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                            <select class="form-select" id="client_id" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="technician_id" class="form-label">Técnico <span class="text-danger">*</span></label>
                            <select class="form-select" id="technician_id" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="scheduled_date" class="form-label">Fecha <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="scheduled_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="scheduled_time" class="form-label">Hora</label>
                                <input type="time" class="form-control" id="scheduled_time">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea class="form-control" id="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-gradient" onclick="saveVisit()">
                        <i class="bi bi-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- MODAL DETALLES VISITA -->
    <!-- ============================================ -->
    <div class="modal fade" id="visitDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header gradient-bg text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle"></i> Detalles de la Visita
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="visitDetailContent"></div>
                    <div id="map" style="margin-top: 20px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- SCRIPTS -->
    <!-- ============================================ -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js"></script>
    
    <script>
        // ============================================
        // VARIABLES GLOBALES
        // ============================================
        let visits = [];
        let clients = [];
        let technicians = [];
        let map = null;
        let markers = [];
        let mapInitialized = false;
        const isTechnician = <?php echo $isTechnician ? 'true' : 'false'; ?>;
        const canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;

        // ============================================
        // INICIALIZACIÓN
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Página de visitas cargada');
            console.log('👤 Rol:', isTechnician ? 'Técnico' : 'Supervisor/Admin');
            
            loadClients();
            loadTechnicians();
            loadVisits();
            
            if (!isTechnician) {
                document.getElementById('statusFilter').addEventListener('change', function() {
                    loadVisits();
                });
            }

            const today = new Date().toISOString().split('T')[0];
            document.getElementById('scheduled_date').setAttribute('min', today);

            // Listener cuando se muestra el modal de detalles
            document.getElementById('visitDetailModal').addEventListener('shown.bs.modal', function() {
                setTimeout(initializeMap, 300);
            });
        });

        // ============================================
        // FUNCIONES DEL MAPA
        // ============================================
        function initializeMap() {
            if (typeof google === 'undefined' || !google.maps) {
                console.log('⏳ Esperando a que Google Maps cargue...');
                setTimeout(initializeMap, 500);
                return;
            }

            if (map && mapInitialized) {
                console.log('✅ Mapa ya inicializado');
                return;
            }

            try {
                const guatemala = { lat: 14.6349, lng: -90.5069 };
                const mapElement = document.getElementById('map');
                
                if (!mapElement) {
                    console.error('❌ Elemento #map no encontrado');
                    return;
                }

                map = new google.maps.Map(mapElement, {
                    zoom: 12,
                    center: guatemala,
                    mapTypeControl: true,
                    streetViewControl: false,
                    fullscreenControl: true
                });

                mapInitialized = true;
                console.log('✅ Mapa inicializado correctamente');
            } catch (error) {
                console.error('❌ Error al inicializar mapa:', error);
            }
        }

        function showVisitOnMap(visit) {
            if (!map || !mapInitialized) {
                console.log('Inicializando mapa...');
                initializeMap();
                setTimeout(() => showVisitOnMap(visit), 500);
                return;
            }

            try {
                markers.forEach(marker => marker.setMap(null));
                markers = [];

                if (visit.client_lat && visit.client_lng) {
                    const location = {
                        lat: parseFloat(visit.client_lat),
                        lng: parseFloat(visit.client_lng)
                    };

                    const marker = new google.maps.Marker({
                        position: location,
                        map: map,
                        title: visit.client_name,
                        animation: google.maps.Animation.DROP
                    });

                    markers.push(marker);
                    map.setCenter(location);
                    map.setZoom(15);

                    const infoWindow = new google.maps.InfoWindow({
                        content: `
                            <div style="padding: 10px;">
                                <h6><strong>${visit.client_name}</strong></h6>
                                <p class="mb-1">${visit.client_address || 'Sin dirección'}</p>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=${location.lat},${location.lng}" 
                                   target="_blank" class="btn btn-sm btn-primary">
                                    <i class="bi bi-map"></i> Cómo llegar
                                </a>
                            </div>
                        `
                    });

                    marker.addListener('click', function() {
                        infoWindow.open(map, marker);
                    });

                    infoWindow.open(map, marker);
                } else {
                    console.log('❌ Visita sin coordenadas');
                }
            } catch (error) {
                console.error('❌ Error al mostrar visita en mapa:', error);
            }
        }

        // ============================================
        // GESTIÓN DE VISITAS
        // ============================================
        async function loadClients() {
            try {
                const response = await App.request('/api/clients.php');
                if (response.success) {
                    clients = response.data;
                    const select = document.getElementById('client_id');
                    select.innerHTML = '<option value="">Seleccione...</option>' +
                        clients.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
                    console.log('✅ Clientes cargados:', clients.length);
                }
            } catch (error) {
                console.error('❌ Error cargando clientes:', error);
            }
        }

        async function loadTechnicians() {
            try {
                const techs = await getAvailableTechnicians();
                technicians = techs;
                const select = document.getElementById('technician_id');
                select.innerHTML = '<option value="">Seleccione...</option>' +
                    techs.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
                console.log('✅ Técnicos cargados:', techs.length);
            } catch (error) {
                console.error('❌ Error cargando técnicos:', error);
            }
        }

        async function getAvailableTechnicians() {
            const response = await App.request('/api/users.php');
            if (response.success) {
                return response.data.filter(u => u.role_name === 'Técnico');
            }
            return [];
        }

        async function loadVisits() {
            try {
                let url = '/api/visits.php';
                
                if (isTechnician) {
                    url = '/api/visits.php?today=1';
                } else {
                    const status = document.getElementById('statusFilter')?.value;
                    if (status) url += `?status=${status}`;
                }

                const response = await App.request(url);
                if (response.success) {
                    visits = response.data;
                    console.log('✅ Visitas cargadas:', visits.length);
                    
                    if (isTechnician) {
                        renderTechnicianVisits(visits);
                    } else {
                        renderVisitsTable(visits);
                    }
                } else {
                    App.showAlert('Error al cargar visitas', 'error', 'alertContainer');
                }
            } catch (error) {
                console.error('❌ Error cargando visitas:', error);
                App.showAlert('Error de conexión', 'error', 'alertContainer');
            }
        }

        function renderVisitsTable(visitsList) {
            const tbody = document.getElementById('visitsTableBody');
            
            if (visitsList.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay visitas registradas</td></tr>';
                return;
            }

            tbody.innerHTML = visitsList.map(visit => {
                const statusClass = `status-${visit.status}`;
                const statusText = {
                    'scheduled': 'Programada',
                    'in-progress': 'En Progreso',
                    'completed': 'Completada',
                    'cancelled': 'Cancelada'
                }[visit.status];

                return `
                    <tr>
                        <td>${formatDate(visit.scheduled_date)}</td>
                        <td>${visit.scheduled_time || '-'}</td>
                        <td><strong>${visit.client_name}</strong><br><small class="text-muted">${visit.client_address || ''}</small></td>
                        <td>${visit.technician_name}</td>
                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewVisit(${visit.id})" title="Ver detalles">
                                <i class="bi bi-eye"></i>
                            </button>
                            ${canEdit && visit.status !== 'completed' ? `
                                <button class="btn btn-sm btn-warning" onclick="editVisit(${visit.id})" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                    </button>
                                <button class="btn btn-sm btn-danger" onclick="cancelVisit(${visit.id})" title="Cancelar visita">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `;
        }).join('');
        }

        function renderTechnicianVisits(visitsList) {
            const container = document.getElementById('visitsContainer');
            
            if (visitsList.length === 0) {
                container.innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> No tienes visitas programadas para hoy.</div>';
                return;
            }

            container.innerHTML = visitsList.map(visit => {
                const hasIngreso = visit.ingreso_time !== null;
                const hasEgreso = visit.egreso_time !== null;
                const canStart = !hasIngreso;
                const canFinish = hasIngreso && !hasEgreso;

                return `
                    <div class="card visit-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="card-title">
                                        <i class="bi bi-building"></i> ${visit.client_name}
                                    </h5>
                                    <p class="text-muted mb-2">
                                        <i class="bi bi-geo-alt"></i> ${visit.client_address || 'Sin dirección'}
                                    </p>
                                    <p class="mb-2">
                                        <strong>Hora programada:</strong> ${visit.scheduled_time || 'No especificada'}
                                    </p>
                                    ${hasIngreso ? `
                                        <p class="mb-2 text-success">
                                            <i class="bi bi-check-circle"></i> <strong>Ingreso:</strong> 
                                            ${formatDateTime(visit.ingreso_time)}
                                        </p>
                                    ` : ''}
                                    ${hasEgreso ? `
                                        <p class="mb-2 text-success">
                                            <i class="bi bi-check-circle"></i> <strong>Egreso:</strong> 
                                            ${formatDateTime(visit.egreso_time)}
                                        </p>
                                    ` : ''}
                                    ${visit.notes ? `<p class="text-muted small"><strong>Notas:</strong> ${visit.notes}</p>` : ''}
                                </div>
                                <div class="col-md-4 text-end">
                                    ${visit.client_lat && visit.client_lng ? `
                                        <a href="https://www.google.com/maps/dir/?api=1&destination=${visit.client_lat},${visit.client_lng}" 
                                           target="_blank" class="btn btn-sm btn-outline-primary mb-2 w-100">
                                            <i class="bi bi-map"></i> Cómo Llegar
                                        </a>
                                    ` : ''}
                                    ${canStart ? `
                                        <button class="btn btn-success w-100 mb-2" onclick="registerEvent(${visit.id}, 'ingreso')">
                                            <i class="bi bi-play-circle"></i> Iniciar Visita
                                        </button>
                                    ` : ''}
                                    ${canFinish ? `
                                        <button class="btn btn-danger w-100 mb-2" onclick="registerEvent(${visit.id}, 'egreso')">
                                            <i class="bi bi-stop-circle"></i> Finalizar Visita
                                        </button>
                                    ` : ''}
                                    ${hasEgreso ? `
                                        <span class="badge bg-success w-100">✓ Visita Completada</span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function formatDate(dateString) {
            const date = new Date(dateString + 'T00:00:00');
            return date.toLocaleDateString('es-GT');
        }

        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('es-GT');
        }

        // ============================================
        // CREAR/EDITAR VISITAS
        // ============================================
        function openCreateModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-calendar-plus"></i> Nueva Visita';
            document.getElementById('visitForm').reset();
            document.getElementById('visitId').value = '';
            document.getElementById('modalAlert').innerHTML = '';
            console.log('📝 Modal de nueva visita abierto');
        }

        async function editVisit(id) {
            try {
                const visit = visits.find(v => v.id === id);
                
                if (!visit) {
                    App.showAlert('Visita no encontrada', 'error', 'alertContainer');
                    return;
                }
                
                if (visit.status === 'completed') {
                    App.showAlert('No se pueden editar visitas completadas', 'warning', 'alertContainer');
                    return;
                }
                
                document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil"></i> Editar Visita';
                document.getElementById('visitId').value = visit.id;
                document.getElementById('client_id').value = visit.client_id;
                document.getElementById('technician_id').value = visit.technician_id;
                document.getElementById('scheduled_date').value = visit.scheduled_date;
                document.getElementById('scheduled_time').value = visit.scheduled_time || '';
                document.getElementById('notes').value = visit.notes || '';
                document.getElementById('modalAlert').innerHTML = '';
                
                const modal = new bootstrap.Modal(document.getElementById('visitModal'));
                modal.show();
                
                console.log('✏️ Modal de edición abierto para visita #' + id);
                
            } catch (error) {
                console.error('❌ Error al editar visita:', error);
                App.showAlert('Error al cargar datos de la visita', 'error', 'alertContainer');
            }
        }

        // ============================================        
        // CANCELAR VISITA (solo supervisores)
        // ============================================
        async function cancelVisit(visitId) {
            if (!confirm("¿Seguro que deseas cancelar esta visita?")) return;
        
            try {
                const response = await App.request(`/api/visits.php?id=${visitId}`, {
                    method: "PUT",
                    body: JSON.stringify({ status: "cancelled" })
                });
        
                if (response.success) {
                    App.showAlert("Visita cancelada exitosamente.", "success", "alertContainer");
                    loadVisits();
                } else {
                    App.showAlert(response.error || "Error al cancelar visita.", "error", "alertContainer");
                }
            } catch (error) {
                console.error("❌ Error al cancelar visita:", error);
                App.showAlert("Error de conexión.", "error", "alertContainer");
            }
        }



        async function saveVisit() {
            const id = document.getElementById('visitId').value;
            const data = {
                client_id: document.getElementById('client_id').value,
                technician_id: document.getElementById('technician_id').value,
                scheduled_date: document.getElementById('scheduled_date').value,
                scheduled_time: document.getElementById('scheduled_time').value,
                notes: document.getElementById('notes').value
            };

            if (!data.client_id || !data.technician_id || !data.scheduled_date) {
                App.showAlert('Complete los campos requeridos', 'error', 'modalAlert');
                return;
            }

            try {
                const url = id ? `/api/visits.php?id=${id}` : '/api/visits.php';
                const method = id ? 'PUT' : 'POST';

                console.log('💾 Guardando visita...', { id, method, data });

                const response = await App.request(url, {
                    method: method,
                    body: JSON.stringify(data)
                });

                if (response.success) {
                    App.showAlert(response.message, 'success', 'alertContainer');
                    bootstrap.Modal.getInstance(document.getElementById('visitModal')).hide();
                    loadVisits();
                    console.log('✅ Visita guardada exitosamente');
                } else {
                    App.showAlert(response.error, 'error', 'modalAlert');
                }
            } catch (error) {
                console.error('❌ Error guardando visita:', error);
                App.showAlert('Error de conexión', 'error', 'modalAlert');
            }
        }

        // ============================================
        // VER DETALLES
        // ============================================
        async function viewVisit(id) {
            const visit = visits.find(v => v.id === id);
            if (!visit) return;

            const content = `
                <h5>${visit.client_name}</h5>
                <p><strong>Técnico:</strong> ${visit.technician_name}</p>
                <p><strong>Supervisor:</strong> ${visit.supervisor_name || 'N/A'}</p>
                <p><strong>Fecha:</strong> ${formatDate(visit.scheduled_date)}</p>
                <p><strong>Hora:</strong> ${visit.scheduled_time || 'No especificada'}</p>
                <p><strong>Estado:</strong> ${visit.status}</p>
                ${visit.notes ? `<p><strong>Notas:</strong> ${visit.notes}</p>` : ''}
            `;

            document.getElementById('visitDetailContent').innerHTML = content;
            
            const modal = new bootstrap.Modal(document.getElementById('visitDetailModal'));
            modal.show();

            setTimeout(() => {
                initializeMap();
                if (visit.client_lat && visit.client_lng) {
                    showVisitOnMap(visit);
                }
            }, 500);
            
            console.log('👁️ Mostrando detalles de visita #' + id);
        }

        // ============================================
        // REGISTRAR EVENTOS
        // ============================================
async function registerEvent(visitId, eventType) {
    try {
        console.log(`📍 Registrando ${eventType} para visita #${visitId}...`);
        
        // Obtener ubicación GPS
        const position = await App.getLocation();
        
        const data = {
            visit_id: visitId,
            event_type: eventType,
            lat: position.lat,
            lng: position.lng
        };

        // 🔹 Cambiado: se envía con ?action=register
        const response = await App.request('/api/events.php?action=register', {
            method: 'POST',
            body: JSON.stringify(data)
        });

        if (response.success) {
            App.showAlert(response.message, 'success', 'alertContainer');
            // Recargar lista de visitas (si ya tienes una función que lo hace)
            if (typeof loadVisits === 'function') {
                loadVisits();
            }
            console.log(`✅ ${eventType} registrado exitosamente`);
        } else {
            console.error('⚠️ Error en respuesta:', response);
            App.showAlert(response.error || 'Error al registrar evento', 'error', 'alertContainer');
        }
    } catch (error) {
        console.error('❌ Error general:', error);
        App.showAlert('Error al registrar evento: ' + error, 'error', 'alertContainer');
    }
}

        // ============================================
        // LOGOUT
        // ============================================
        document.getElementById('logoutBtn').addEventListener('click', async function(e) {
            e.preventDefault();
            console.log('👋 Cerrando sesión...');
            App.logout();
        });
    </script>

    <!-- Cargar Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo getGoogleMapsApiKey(); ?>&libraries=places" async defer></script>
</body>
</html>