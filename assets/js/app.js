/**
 * Sistema de Visitas Técnicas
 * Módulo principal de JavaScript
 */

// Definir el objeto App primero, antes de cualquier uso
const App = {
    /**
     * Realizar petición AJAX
     */
    request: async function(url, options = {}) {
        const token = localStorage.getItem('auth_token');
        
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': token ? `Bearer ${token}` : ''
            }
        };
        
        // Combinar correctamente encabezados sin perder Content-Type
        const config = {
            ...defaultOptions,
            ...options,
            headers: { ...defaultOptions.headers, ...(options.headers || {}) }
        };

        // Mostrar en consola para depuración
        console.log('📡 Enviando petición:', url, config);

        try {
            const response = await fetch(url, config);

            // Si no es JSON válido, capturamos el texto para mostrarlo
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                console.error('⚠️ Respuesta no es JSON:', text);
                throw new Error('Respuesta no válida del servidor');
            }

            return data;
        } catch (error) {
            console.error('❌ Error en petición:', error);
            throw error;
        }
    },

    /**
     * Mostrar alerta
     */
    showAlert: function(message, type = 'info', containerId = 'alertContainer') {
        const container = document.getElementById(containerId);
        if (!container) {
            console.error('Contenedor de alertas no encontrado:', containerId);
            return;
        }
        
        const alertTypes = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        };
        
        const alertClass = alertTypes[type] || 'alert-info';
        
        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} alert-dismissible fade show`;
        alert.role = 'alert';
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        container.innerHTML = '';
        container.appendChild(alert);
        
        // Auto-cerrar después de 5 segundos
        setTimeout(() => {
            if (alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 150);
            }
        }, 5000);
    },

    /**
     * Formatear fecha
     */
    formatDate: function(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString('es-GT');
    },

    /**
     * Formatear fecha y hora
     */
    formatDateTime: function(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString('es-GT');
    },

    /**
     * Confirmar acción
     */
    confirm: function(message) {
        return window.confirm(message);
    },

    /**
     * Logout
     */
    logout: async function() {
        try {
            await this.request('/api/auth.php?action=logout', { method: 'POST' });
        } catch (error) {
            console.error('Error en logout:', error);
        } finally {
            localStorage.removeItem('auth_token');
            window.location.href = '/pages/login.php';
        }
    },

    /**
     * Obtener ubicación actual del usuario
     */
    getLocation: async function() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject('Geolocalización no soportada');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                position => {
                    resolve({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    });
                },
                error => {
                    reject('Error obteniendo ubicación: ' + error.message);
                }
            );
        });
    }
};

// Hacer App disponible globalmente
window.App = App;

// Función de inicialización cuando el DOM está listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ App inicializado correctamente');
    
    // Configurar botón de logout si existe
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            App.logout();
        });
    }
});
