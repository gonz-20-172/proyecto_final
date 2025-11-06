<?php
require_once __DIR__ . '/../vendor/autoload.php';
requireAuth();
requirePermission('users', 'view');

$currentUser = getCurrentUserFromDB();
$canCreate = hasPermission('users', 'create');
$canEdit = hasPermission('users', 'edit');
$canDelete = hasPermission('users', 'delete');
$userName = $currentUser['name'] ?? 'Usuario';
$userRole = $currentUser['role_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Sistema de Visitas Técnicas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <style>
        .user-card { transition: all 0.3s; margin-bottom: 15px; }
        .user-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .badge-active { background-color: #198754; }
        .badge-inactive { background-color: #dc3545; }
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
                    <a class="nav-link active" href="/pages/users.php">
                        <i class="bi bi-person-badge"></i> Usuarios
                    </a>
                </div>
            </div>

            <div class="col-md-10">
                <div class="content">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-person-badge"></i> Gestión de Usuarios</h2>
                        <?php if ($canCreate): ?>
                        <button class="btn btn-primary btn-gradient" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openCreateModal()">
                            <i class="bi bi-plus-circle"></i> Nuevo Usuario
                        </button>
                        <?php endif; ?>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Filtrar por rol:</label>
                                    <select class="form-select" id="roleFilter">
                                        <option value="">Todos</option>
                                        <option value="Administrador">Administrador</option>
                                        <option value="Supervisor">Supervisor</option>
                                        <option value="Técnico">Técnico</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Filtrar por estado:</label>
                                    <select class="form-select" id="statusFilter">
                                        <option value="">Todos</option>
                                        <option value="1">Activos</option>
                                        <option value="0">Inactivos</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Buscar:</label>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Nombre o email...">
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
                                            <th>Email</th>
                                            <th>Rol</th>
                                            <th>Supervisor</th>
                                            <th>Estado</th>
                                            <th>Fecha Registro</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="usersTableBody">
                                        <tr>
                                            <td colspan="7" class="text-center">
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

    <!-- Modal Usuario -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header gradient-bg text-white">
                    <h5 class="modal-title" id="modalTitle">
                        <i class="bi bi-person-plus"></i> Nuevo Usuario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="modalAlert"></div>
                    <form id="userForm">
                        <input type="hidden" id="userId">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">
                                Contraseña <span class="text-danger" id="passwordRequired">*</span>
                                <small class="text-muted" id="passwordHint" style="display: none;">(Dejar vacío para mantener actual)</small>
                            </label>
                            <input type="password" class="form-control" id="password" minlength="6">
                            <small class="form-text text-muted">Mínimo 6 caracteres</small>
                        </div>

                        <div class="mb-3">
                            <label for="role_id" class="form-label">Rol <span class="text-danger">*</span></label>
                            <select class="form-select" id="role_id" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="mb-3" id="supervisorGroup" style="display: none;">
                            <label for="supervisor_id" class="form-label">Supervisor Asignado</label>
                            <select class="form-select" id="supervisor_id">
                                <option value="">Sin supervisor</option>
                            </select>
                            <small class="form-text text-muted">Solo aplica para técnicos</small>
                        </div>

                        <div class="mb-3">
                            <label for="active" class="form-label">Estado</label>
                            <select class="form-select" id="active">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-gradient" onclick="saveUser()">
                        <i class="bi bi-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <script>
        let users = [];
        let roles = [];
        let supervisors = [];
        const canEdit = <?php echo $canEdit ? 'true' : 'false'; ?>;
        const canDelete = <?php echo $canDelete ? 'true' : 'false'; ?>;

        document.addEventListener('DOMContentLoaded', function() {
            loadRoles();
            loadUsers();
            
            document.getElementById('roleFilter').addEventListener('change', filterUsers);
            document.getElementById('statusFilter').addEventListener('change', filterUsers);
            document.getElementById('searchInput').addEventListener('input', filterUsers);
            
            document.getElementById('role_id').addEventListener('change', function() {
                const selectedRole = roles.find(r => r.id == this.value);
                if (selectedRole && selectedRole.name === 'Técnico') {
                    document.getElementById('supervisorGroup').style.display = 'block';
                    loadSupervisors();
                } else {
                    document.getElementById('supervisorGroup').style.display = 'none';
                    document.getElementById('supervisor_id').value = '';
                }
            });

            document.getElementById('userModal').addEventListener('hidden.bs.modal', function() {
                document.getElementById('userForm').reset();
                document.getElementById('userId').value = '';
                document.getElementById('modalAlert').innerHTML = '';
                document.getElementById('supervisorGroup').style.display = 'none';
                document.getElementById('passwordHint').style.display = 'none';
                document.getElementById('passwordRequired').style.display = 'inline';
                document.getElementById('password').required = true;
            });
        });

        async function loadRoles() {
            try {
                const response = await App.request('/api/users.php');
                if (response.success) {
                    const uniqueRoles = [...new Set(response.data.map(u => JSON.stringify({id: u.role_id, name: u.role_name})))].map(r => JSON.parse(r));
                    roles = uniqueRoles;
                    
                    const select = document.getElementById('role_id');
                    select.innerHTML = '<option value="">Seleccione...</option>' +
                        roles.map(r => `<option value="${r.id}">${r.name}</option>`).join('');
                }
            } catch (error) {
                console.error('Error cargando roles:', error);
            }
        }

        async function loadSupervisors() {
            try {
                const response = await App.request('/api/users.php?role=Supervisor');
                if (response.success) {
                    supervisors = response.data.filter(u => u.active == 1);
                    const select = document.getElementById('supervisor_id');
                    select.innerHTML = '<option value="">Sin supervisor</option>' +
                        supervisors.map(s => `<option value="${s.id}">${s.name}</option>`).join('');
                }
            } catch (error) {
                console.error('Error cargando supervisores:', error);
            }
        }

        async function loadUsers() {
            try {
                const response = await App.request('/api/users.php');
                if (response.success) {
                    users = response.data;
                    renderUsers(users);
                } else {
                    App.showAlert('Error al cargar usuarios', 'error', 'alertContainer');
                }
            } catch (error) {
                App.showAlert('Error de conexión', 'error', 'alertContainer');
            }
        }

        function renderUsers(usersList) {
            const tbody = document.getElementById('usersTableBody');
            
            if (usersList.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No hay usuarios registrados</td></tr>';
                return;
            }

            tbody.innerHTML = usersList.map(user => {
                const statusBadge = user.active == 1 
                    ? '<span class="badge badge-active">Activo</span>' 
                    : '<span class="badge badge-inactive">Inactivo</span>';
                
                return `
                    <tr>
                        <td><strong>${user.name}</strong></td>
                        <td>${user.email}</td>
                        <td><span class="badge bg-primary">${user.role_name}</span></td>
                        <td>${user.supervisor_name || '-'}</td>
                        <td>${statusBadge}</td>
                        <td>${formatDate(user.created_at)}</td>
                        <td>
                            ${canEdit ? `
                                <button class="btn btn-sm btn-warning" onclick="editUser(${user.id})" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                            ` : ''}
                            ${user.active == 1 && canEdit ? `
                                <button class="btn btn-sm btn-secondary" onclick="toggleUserStatus(${user.id}, 0)" title="Desactivar">
                                    <i class="bi bi-x-circle"></i>
                                </button>
                            ` : ''}
                            ${user.active == 0 && canEdit ? `
                                <button class="btn btn-sm btn-success" onclick="toggleUserStatus(${user.id}, 1)" title="Activar">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                            ` : ''}
                            ${canDelete ? `
                                <button class="btn btn-sm btn-danger" onclick="confirmDelete(${user.id})" title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function filterUsers() {
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const searchText = document.getElementById('searchInput').value.toLowerCase();

            let filtered = users;

            if (roleFilter) {
                filtered = filtered.filter(u => u.role_name === roleFilter);
            }

            if (statusFilter !== '') {
                filtered = filtered.filter(u => u.active == statusFilter);
            }

            if (searchText) {
                filtered = filtered.filter(u => 
                    u.name.toLowerCase().includes(searchText) || 
                    u.email.toLowerCase().includes(searchText)
                );
            }

            renderUsers(filtered);
        }

        function openCreateModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-person-plus"></i> Nuevo Usuario';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('passwordHint').style.display = 'none';
            document.getElementById('passwordRequired').style.display = 'inline';
            document.getElementById('password').required = true;
            document.getElementById('supervisorGroup').style.display = 'none';
        }

        async function editUser(id) {
            const user = users.find(u => u.id === id);
            if (!user) return;

            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil"></i> Editar Usuario';
            document.getElementById('userId').value = user.id;
            document.getElementById('name').value = user.name;
            document.getElementById('email').value = user.email;
            document.getElementById('password').value = '';
            document.getElementById('role_id').value = user.role_id;
            document.getElementById('active').value = user.active;
            
            document.getElementById('passwordHint').style.display = 'inline';
            document.getElementById('passwordRequired').style.display = 'none';
            document.getElementById('password').required = false;

            if (user.role_name === 'Técnico') {
                await loadSupervisors();
                document.getElementById('supervisorGroup').style.display = 'block';
                document.getElementById('supervisor_id').value = user.supervisor_id || '';
            } else {
                document.getElementById('supervisorGroup').style.display = 'none';
            }

            new bootstrap.Modal(document.getElementById('userModal')).show();
        }

        async function saveUser() {
            const id = document.getElementById('userId').value;
            const password = document.getElementById('password').value;

            if (!id && !password) {
                App.showAlert('La contraseña es requerida para nuevos usuarios', 'error', 'modalAlert');
                return;
            }

            const data = {
                name: document.getElementById('name').value.trim(),
                email: document.getElementById('email').value.trim(),
                role_id: parseInt(document.getElementById('role_id').value),
                active: parseInt(document.getElementById('active').value)
            };

            if (password) {
                data.password = password;
            }

            const selectedRole = roles.find(r => r.id == data.role_id);
            if (selectedRole && selectedRole.name === 'Técnico') {
                const supervisorId = document.getElementById('supervisor_id').value;
                if (supervisorId) {
                    data.supervisor_id = parseInt(supervisorId);
                }
            }

            if (!data.name || !data.email || !data.role_id) {
                App.showAlert('Complete todos los campos requeridos', 'error', 'modalAlert');
                return;
            }

            try {
                const url = id ? `/api/users.php?id=${id}` : '/api/users.php';
                const method = id ? 'PUT' : 'POST';

                const response = await App.request(url, {
                    method: method,
                    body: JSON.stringify(data)
                });

                if (response.success) {
                    App.showAlert(response.message, 'success', 'alertContainer');
                    bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
                    loadUsers();
                } else {
                    App.showAlert(response.error, 'error', 'modalAlert');
                }
            } catch (error) {
                App.showAlert('Error de conexión', 'error', 'modalAlert');
            }
        }

        async function toggleUserStatus(id, newStatus) {
            const action = newStatus == 1 ? 'activar' : 'desactivar';
            if (!confirm(`¿Está seguro de ${action} este usuario?`)) return;

            const user = users.find(u => u.id === id);
            if (!user) return;

            const data = {
                name: user.name,
                email: user.email,
                role_id: user.role_id,
                active: newStatus
            };

            if (user.supervisor_id) {
                data.supervisor_id = user.supervisor_id;
            }

            try {
                const response = await App.request(`/api/users.php?id=${id}`, {
                    method: 'PUT',
                    body: JSON.stringify(data)
                });

                if (response.success) {
                    App.showAlert(`Usuario ${action === 'activar' ? 'activado' : 'desactivado'} exitosamente`, 'success', 'alertContainer');
                    loadUsers();
                } else {
                    App.showAlert(response.error, 'error', 'alertContainer');
                }
            } catch (error) {
                App.showAlert('Error de conexión', 'error', 'alertContainer');
            }
        }

        function confirmDelete(id) {
            if (confirm('¿Está seguro de eliminar este usuario? Esta acción no se puede deshacer.')) {
                deleteUser(id);
            }
        }

        async function deleteUser(id) {
            try {
                const response = await App.request(`/api/users.php?id=${id}`, {
                    method: 'DELETE'
                });

                if (response.success) {
                    App.showAlert(response.message, 'success', 'alertContainer');
                    loadUsers();
                } else {
                    App.showAlert(response.error, 'error', 'alertContainer');
                }
            } catch (error) {
                App.showAlert('Error de conexión', 'error', 'alertContainer');
            }
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-GT');
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