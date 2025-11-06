# Sistema de Visitas

Sistema de gestión de visitas con soporte para almacenamiento en archivos JSON o base de datos MySQL.

## 🚀 Características

- ✅ Login y registro de usuarios
- ✅ Registro de visitas con entrada y salida
- ✅ Dashboard con estadísticas en tiempo real
- ✅ Historial de visitas
- ✅ **Modo archivo JSON** (sin necesidad de base de datos inicialmente)
- ✅ **Modo base de datos** (para producción)
- ✅ Diseño responsive y moderno

## 📁 Estructura del Proyecto

```
sistema-visitas/
├── index.php               # Redirección al login
├── includes/
│   ├── config.php         # Configuración general
│   └── storage.php        # Clase de almacenamiento adaptable
├── pages/
│   ├── login.php          # Login y registro
│   ├── dashboard.php      # Panel principal
│   └── logout.php         # Cerrar sesión
├── data/                  # Archivos JSON (se crean automáticamente)
│   ├── users.json
│   └── visits.json
└── assets/
    ├── css/
    └── js/
```

## 🔧 Instalación

### Opción 1: Modo Archivos JSON (Sin Base de Datos)

1. Copia todos los archivos a tu carpeta de XAMPP:
   ```
   C:\xampp\htdocs\sistema-visitas\
   ```

2. Abre tu navegador y ve a:
   ```
   http://localhost/sistema-visitas
   ```

3. ¡Listo! El sistema creará automáticamente los archivos JSON necesarios.

### Opción 2: Con Base de Datos MySQL

1. Inicia XAMPP y arranca Apache y MySQL

2. Crea la base de datos en phpMyAdmin:
   ```sql
   CREATE DATABASE sistema_visitas;
   ```

3. Importa el archivo `database.sql` (si lo tienes) o crea las tablas manualmente

4. Edita `includes/config.php` y cambia:
   ```php
   define('STORAGE_MODE', 'database'); // Cambiar de 'file' a 'database'
   ```

5. Configura tus credenciales de base de datos en `config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_PORT', '3306');
   define('DB_NAME', 'sistema_visitas');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

## 👤 Primer Uso

1. Ve a http://localhost/sistema-visitas

2. Haz clic en "Registrarse"

3. Completa el formulario con tus datos

4. **El primer usuario registrado será automáticamente ADMIN**

5. Inicia sesión con tus credenciales

## 🔄 Migración de Archivos JSON a Base de Datos

Cuando estés listo para migrar de archivos JSON a base de datos:

1. Guarda una copia de tus archivos en `data/users.json` y `data/visits.json`

2. Crea la base de datos y las tablas necesarias

3. Ejecuta el script de migración (próximamente) o importa manualmente los datos

4. Cambia el modo en `config.php`:
   ```php
   define('STORAGE_MODE', 'database');
   ```

## 📊 SQL para Crear las Tablas (Cuando estés listo)

```sql
-- Tabla de usuarios
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de visitas
CREATE TABLE visits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(255) NOT NULL,
    visitor_id VARCHAR(50) NOT NULL,
    company VARCHAR(255),
    person_to_visit VARCHAR(255) NOT NULL,
    reason TEXT NOT NULL,
    entry_time DATETIME NOT NULL,
    exit_time DATETIME,
    status ENUM('active', 'completed') DEFAULT 'active',
    registered_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (registered_by) REFERENCES users(id)
);
```

## 🛠️ Personalización

### Cambiar el nombre de la aplicación
Edita `includes/config.php`:
```php
define('APP_NAME', 'Tu Nombre Aquí');
```

### Cambiar colores
Edita los archivos CSS en `pages/login.php` y `pages/dashboard.php`

## ⚠️ Notas Importantes

- Los archivos JSON se crean automáticamente en la carpeta `data/`
- Las contraseñas se guardan encriptadas con `password_hash()`
- El modo archivo es perfecto para desarrollo y pruebas
- Para producción, se recomienda usar el modo base de datos
- Los datos en archivos JSON persisten hasta que los borres manualmente

## 🐛 Solución de Problemas

### No puedo iniciar sesión
- Verifica que hayas registrado un usuario primero
- Revisa que la carpeta `data/` tenga permisos de escritura

### Error "Failed to open stream"
- Verifica que la ruta del proyecto sea correcta
- Asegúrate de que XAMPP esté corriendo

### Los datos no se guardan
- Verifica permisos de la carpeta `data/`
- En Windows, la carpeta debe permitir escritura

## 📝 Próximas Mejoras

- [ ] Exportar reportes a PDF
- [ ] Búsqueda y filtros avanzados
- [ ] Notificaciones por email
- [ ] Captura de foto del visitante
- [ ] Firma digital
- [ ] Gráficas y estadísticas avanzadas

## 📄 Licencia

Proyecto libre para uso educativo y comercial.

## 👨‍💻 Soporte

Si tienes dudas o problemas, revisa primero que:
1. XAMPP esté corriendo (solo Apache si usas modo archivos)
2. La ruta del proyecto sea correcta
3. Los permisos de la carpeta `data/` permitan escritura

---

**¡Disfruta tu Sistema de Visitas!** 🎉
