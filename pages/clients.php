<?php
require_once __DIR__ . '/../vendor/autoload.php';
requireAuth();
requirePermission('clients', 'view');

$currentUser = getCurrentUserFromDB();
$canCreate = hasPermission('clients', 'create');
$canEdit = hasPermission('clients', 'edit');
$canDelete = hasPermission('clients', 'delete');
$userName = $currentUser['name'] ?? 'Usuario';
$userRole = $currentUser['role_name'] ?? '';
$googleMapsKey = config('GOOGLE_MAPS_KEY', '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - Sistema de Visitas Técnicas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <style>
        #map { height: 300px; width: 100%; border-radius: 8px; margin-top: 10px; }
        .client-card { transition: all 0.3s; cursor: pointer; }
        .client-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
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
                    <a class="nav-link" href="/pages/dashboard.php">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                    <a class="nav-link active" href="/pages/clients.php">
                        <i class="bi bi-people"></i> Clientes
                    </a>
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-people"></i> Gestión de Clientes</h2>
                        <?php if ($canCreate): ?>
                        <button class="btn btn-primary btn-gradient" data-bs-toggle="modal" data-bs-target="#clientModal" onclick="openCreateModal()">
                            <i class="bi bi-plus-circle"></i> Nuevo Cliente
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" id="searchInput" placeholder="Buscar por nombre, email o teléfono...">
                                    </div>
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
                                            <th>Nombre</th>
                                            <th>Dirección</th>
                                            <th>Teléfono</th>
                                            <th>Email</th>
                                            <th>Coordenadas</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="clientsTableBody">
                                        <tr>
                                            <td colspan="6" class="text-center">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Cargando...</span>
                                                </div>
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

    <!-- Modal Cliente -->
    <div class="modal fade" id="clientModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header gradient-bg text-white">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="bi bi-person-plus"></i> Nuevo Cliente
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="modalAlert"></div>
                    <form id="clientForm">
                        <input type="hidden" id="clientId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="phone" placeholder="2345-6789">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="address" class="form-label">Dirección</label>
                                <input type="text" class="form-control" id="address" placeholder="Ingrese dirección">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Ubicación GPS</label>
                            <div class="input-group mb-2">
                                <input type="number" step="any" class="form-control" id="lat" placeholder="Latitud">
                                <input type="number" step="any" class="form-control" id="lng" placeholder="Longitud">
                                <button type="button" class="btn btn-outline-primary" onclick="getCurrentLocation()">
                                    <i class="bi bi-geo-alt"></i> Mi Ubicación
                                </button>
                            </div>
                            <div id="map"></div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas</label>
                            <textarea class="form-control" id="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-gradient" onclick="saveClient()">
                        <i class="bi bi-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js"></script>
    
    <script>
        // ============================================
        // VARIABLES GLOBALES
        // ============================================
        let map = null;
        let marker = null;
        let clients = [];
        let mapInitialized = false;
        const canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
        const canDelete = <?php echo $canDelete ? 'true' : 'false'; ?>;

        // ============================================
        // INICIALIZACIÓN
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            loadClients();
            
            // Listener para búsqueda
            document.getElementById('searchInput').addEventListener('input', function(e) {
                const search = e.target.value.toLowerCase();
                filterClients(search);
            });

            // Listener cuando se cierra el modal
            document.getElementById('clientModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('clientForm').reset();
                document.getElementById('clientId').value = '';
                document.getElementById('modalAlert').innerHTML = '';
            });

            // Listener cuando se muestra el modal
            document.getElementById('clientModal').addEventListener('shown.bs.modal', function() {
                // Inicializar mapa cuando el modal es visible
                setTimeout(initializeMap, 300);
            });
        });

        // ============================================
        // FUNCIONES DEL MAPA
        // ============================================
        function initializeMap() {
            // Verificar que Google Maps esté disponible
            if (typeof google === 'undefined' || !google.maps) {
                console.log('⏳ Esperando a que Google Maps cargue...');
                setTimeout(initializeMap, 500);
                return;
            }

            // Si el mapa ya está inicializado, no reiniciar
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

                // Crear mapa
                map = new google.maps.Map(mapElement, {
                    zoom: 12,
                    center: guatemala,
                    mapTypeControl: true,
                    streetViewControl: false,
                    fullscreenControl: true
                });

                // Listener para clicks en el mapa
                map.addListener('click', function(e) {
                    placeMarker(e.latLng);
                });

                mapInitialized = true;
                console.log('✅ Mapa inicializado correctamente');
            } catch (error) {
                console.error('❌ Error al inicializar mapa:', error);
            }
        }

        function placeMarker(location) {
            // Verificar que el mapa esté inicializado
            if (!map) {
                console.error('❌ Mapa no inicializado');
                initializeMap();
                setTimeout(() => placeMarker(location), 500);
                return;
            }

            try {
                // Si el marcador ya existe, moverlo
                if (marker) {
                    marker.setPosition(location);
                } else {
                    // Crear nuevo marcador
                    marker = new google.maps.Marker({
                        position: location,
                        map: map,
                        draggable: true,
                        animation: google.maps.Animation.DROP
                    });
                    
                    // Listener cuando se arrastra el marcador
                    marker.addListener('dragend', function(e) {
                        updateCoordinates(e.latLng.lat(), e.latLng.lng());
                    });
                }

                // Actualizar coordenadas y centrar mapa
                updateCoordinates(location.lat(), location.lng());
                map.setCenter(location);
            } catch (error) {
                console.error('❌ Error al colocar marcador:', error);
            }
        }

        function updateCoordinates(lat, lng) {
            document.getElementById('lat').value = lat.toFixed(7);
            document.getElementById('lng').value = lng.toFixed(7);
        }

        async function getCurrentLocation() {
            try {
                const position = await App.getLocation();
                document.getElementById('lat').value = position.lat.toFixed(7);
                document.getElementById('lng').value = position.lng.toFixed(7);
                
                const location = new google.maps.LatLng(position.lat, position.lng);
                placeMarker(location);
                
                App.showAlert('Ubicación obtenida correctamente', 'success', 'modalAlert');
            } catch (error) {
                App.showAlert(error, 'error', 'modalAlert');
            }
        }

        // ============================================
        // GESTIÓN DE CLIENTES
        // ============================================
        async function loadClients() {
            try {
                const response = await App.request('/api/clients.php');
                if (response.success) {
                    clients = response.data;
                    renderClients(clients);
                } else {
                    App.showAlert('Error al cargar clientes', 'error', 'alertContainer');
                }
            } catch (error) {
                App.showAlert('Error de conexión', 'error', 'alertContainer');
            }
        }

        function renderClients(clientsList) {
            const tbody = document.getElementById('clientsTableBody');
            
            if (clientsList.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay clientes registrados</td></tr>';
                return;
            }

            tbody.innerHTML = clientsList.map(client => `
                <tr>
                    <td><strong>${client.name}</strong></td>
                    <td>${client.address || '-'}</td>
                    <td>${client.phone || '-'}</td>
                    <td>${client.email || '-'}</td>
                    <td>
                        ${client.lat && client.lng ? `
                            <a href="https://www.google.com/maps/dir/?api=1&destination=${client.lat},${client.lng}" 
                               target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-map"></i> Ver Mapa
                            </a>
                        ` : '-'}
                    </td>
                    <td>
                        ${canEdit ? `
                            <button class="btn btn-sm btn-warning" onclick="editClient(${client.id})">
                                <i class="bi bi-pencil"></i>
                            </button>
                        ` : ''}
                        ${canDelete ? `
                            <button class="btn btn-sm btn-danger" onclick="confirmDelete(${client.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `).join('');
        }

        function filterClients(search) {
            if (!search) {
                renderClients(clients);
                return;
            }

            const filtered = clients.filter(client => 
                client.name.toLowerCase().includes(search) ||
                (client.email && client.email.toLowerCase().includes(search)) ||
                (client.phone && client.phone.includes(search))
            );

            renderClients(filtered);
        }

        // ============================================
        // MODAL - CREAR/EDITAR
        // ============================================
        function openCreateModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-person-plus"></i> Nuevo Cliente';
            document.getElementById('clientForm').reset();
            document.getElementById('clientId').value = '';
            document.getElementById('modalAlert').innerHTML = '';
            
            // Limpiar marcador
            if (marker) {
                marker.setMap(null);
                marker = null;
            }
            
            // El mapa se inicializará cuando el modal sea visible
            // (gracias al listener 'shown.bs.modal')
        }

        async function editClient(id) {
            const client = clients.find(c => c.id === id);
            if (!client) {
                App.showAlert('Cliente no encontrado', 'error', 'alertContainer');
                return;
            }

            // Llenar formulario
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil"></i> Editar Cliente';
            document.getElementById('clientId').value = client.id;
            document.getElementById('name').value = client.name;
            document.getElementById('phone').value = client.phone || '';
            document.getElementById('email').value = client.email || '';
            document.getElementById('address').value = client.address || '';
            document.getElementById('lat').value = client.lat || '';
            document.getElementById('lng').value = client.lng || '';
            document.getElementById('notes').value = client.notes || '';

            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('clientModal'));
            modal.show();

            // Esperar a que el modal sea visible para inicializar mapa
            setTimeout(() => {
                initializeMap();
                
                // Si tiene coordenadas, colocar marcador
                if (client.lat && client.lng && map) {
                    const location = new google.maps.LatLng(parseFloat(client.lat), parseFloat(client.lng));
                    placeMarker(location);
                    map.setZoom(15);
                }
            }, 500);
        }

        async function saveClient() {
            const id = document.getElementById('clientId').value;
            const data = {
                name: document.getElementById('name').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                email: document.getElementById('email').value.trim(),
                address: document.getElementById('address').value.trim(),
                lat: document.getElementById('lat').value ? parseFloat(document.getElementById('lat').value) : null,
                lng: document.getElementById('lng').value ? parseFloat(document.getElementById('lng').value) : null,
                notes: document.getElementById('notes').value.trim()
            };

            if (!data.name) {
                App.showAlert('El nombre es requerido', 'error', 'modalAlert');
                return;
            }

            try {
                const url = id ? `/api/clients.php?id=${id}` : '/api/clients.php';
                const method = id ? 'PUT' : 'POST';

                const response = await App.request(url, {
                    method: method,
                    body: JSON.stringify(data)
                });

                if (response.success) {
                    App.showAlert(response.message, 'success', 'alertContainer');
                    bootstrap.Modal.getInstance(document.getElementById('clientModal')).hide();
                    loadClients();
                } else {
                    App.showAlert(response.error, 'error', 'modalAlert');
                }
            } catch (error) {
                App.showAlert('Error de conexión', 'error', 'modalAlert');
            }
        }

        function confirmDelete(id) {
            if (confirm('¿Está seguro de eliminar este cliente?')) {
                deleteClient(id);
            }
        }

        async function deleteClient(id) {
            try {
                const response = await App.request(`/api/clients.php?id=${id}`, {
                    method: 'DELETE'
                });

                if (response.success) {
                    App.showAlert(response.message, 'success', 'alertContainer');
                    loadClients();
                } else {
                    App.showAlert(response.error, 'error', 'alertContainer');
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
            } catch (error) {
                window.location.href = '/pages/login.php';
            }
        });
    </script>

    <!-- Cargar Google Maps API -->
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo getGoogleMapsApiKey(); ?>&libraries=places" async defer></script>
</body>
</html>