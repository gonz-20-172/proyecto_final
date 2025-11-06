<?php
require_once __DIR__ . '/config.php';

class Storage {
    
    /**
     * Obtener conexión a la base de datos
     */
    private static function getDbConnection() {
        static $conn = null;
        
        if ($conn === null) {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            
            if ($conn->connect_error) {
                throw new Exception("Error de conexión a la base de datos: " . $conn->connect_error);
            }
            
            $conn->set_charset("utf8mb4");
        }
        
        return $conn;
    }
    
    /**
     * Guarda datos en archivo JSON
     */
    private static function saveToFile($file, $data) {
        // Crear directorio si no existe
        $dir = dirname($file);
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($file, $json) !== false;
    }
    
    /**
     * Lee datos desde archivo JSON
     */
    private static function readFromFile($file) {
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        return $data ?: [];
    }
    
    /**
     * Obtener todos los usuarios
     */
    public static function getUsers() {
        if (STORAGE_MODE === 'file') {
            return self::readFromFile(USERS_FILE);
        } else {
            try {
                $conn = self::getDbConnection();
                $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
                $users = [];
                while ($row = $result->fetch_assoc()) {
                    $users[] = $row;
                }
                return $users;
            } catch (Exception $e) {
                error_log("Error al obtener usuarios: " . $e->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Buscar usuario por email
     */
    public static function getUserByEmail($email) {
        if (STORAGE_MODE === 'file') {
            $users = self::getUsers();
            foreach ($users as $user) {
                if ($user['email'] === $email) {
                    return $user;
                }
            }
            return null;
        } else {
            try {
                $conn = self::getDbConnection();
                $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                return $result->fetch_assoc();
            } catch (Exception $e) {
                error_log("Error al buscar usuario: " . $e->getMessage());
                return null;
            }
        }
    }
    
    /**
     * Crear nuevo usuario
     */
    public static function createUser($data) {
        if (STORAGE_MODE === 'file') {
            $users = self::getUsers();
            
            // Generar ID único
            $data['id'] = count($users) > 0 ? max(array_column($users, 'id')) + 1 : 1;
            $data['created_at'] = date('Y-m-d H:i:s');
            
            $users[] = $data;
            
            return self::saveToFile(USERS_FILE, $users);
        } else {
            try {
                $conn = self::getDbConnection();
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $data['name'], $data['email'], $data['password'], $data['role']);
                return $stmt->execute();
            } catch (Exception $e) {
                error_log("Error al crear usuario: " . $e->getMessage());
                return false;
            }
        }
    }
    
    /**
     * Obtener todas las visitas
     */
    public static function getVisits() {
        if (STORAGE_MODE === 'file') {
            return self::readFromFile(VISITS_FILE);
        } else {
            try {
                $conn = self::getDbConnection();
                $result = $conn->query("SELECT * FROM visits ORDER BY entry_time DESC");
                $visits = [];
                while ($row = $result->fetch_assoc()) {
                    $visits[] = $row;
                }
                return $visits;
            } catch (Exception $e) {
                error_log("Error al obtener visitas: " . $e->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Crear nueva visita
     */
    public static function createVisit($data) {
        if (STORAGE_MODE === 'file') {
            $visits = self::getVisits();
            
            // Generar ID único
            $data['id'] = count($visits) > 0 ? max(array_column($visits, 'id')) + 1 : 1;
            $data['created_at'] = date('Y-m-d H:i:s');
            
            $visits[] = $data;
            
            return self::saveToFile(VISITS_FILE, $visits);
        } else {
            try {
                $conn = self::getDbConnection();
                $stmt = $conn->prepare(
                    "INSERT INTO visits (visitor_name, visitor_id, company, person_to_visit, reason, entry_time, status, registered_by) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    "sssssssi", 
                    $data['visitor_name'], 
                    $data['visitor_id'], 
                    $data['company'], 
                    $data['person_to_visit'], 
                    $data['reason'], 
                    $data['entry_time'], 
                    $data['status'], 
                    $data['registered_by']
                );
                return $stmt->execute();
            } catch (Exception $e) {
                error_log("Error al crear visita: " . $e->getMessage());
                return false;
            }
        }
    }
    
    /**
     * Actualizar visita
     */
    public static function updateVisit($id, $data) {
        if (STORAGE_MODE === 'file') {
            $visits = self::getVisits();
            
            foreach ($visits as $key => $visit) {
                if ($visit['id'] == $id) {
                    $visits[$key] = array_merge($visit, $data);
                    $visits[$key]['updated_at'] = date('Y-m-d H:i:s');
                    return self::saveToFile(VISITS_FILE, $visits);
                }
            }
            
            return false;
        } else {
            try {
                $conn = self::getDbConnection();
                
                // Construir la consulta dinámicamente
                $sets = [];
                $types = "";
                $values = [];
                
                foreach ($data as $key => $value) {
                    $sets[] = "$key = ?";
                    $types .= "s";
                    $values[] = $value;
                }
                
                $types .= "i";
                $values[] = $id;
                
                $sql = "UPDATE visits SET " . implode(", ", $sets) . " WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$values);
                
                return $stmt->execute();
            } catch (Exception $e) {
                error_log("Error al actualizar visita: " . $e->getMessage());
                return false;
            }
        }
    }
    
    /**
     * Eliminar visita
     */
    public static function deleteVisit($id) {
        if (STORAGE_MODE === 'file') {
            $visits = self::getVisits();
            
            foreach ($visits as $key => $visit) {
                if ($visit['id'] == $id) {
                    unset($visits[$key]);
                    // Reindexar array
                    $visits = array_values($visits);
                    return self::saveToFile(VISITS_FILE, $visits);
                }
            }
            
            return false;
        } else {
            try {
                $conn = self::getDbConnection();
                $stmt = $conn->prepare("DELETE FROM visits WHERE id = ?");
                $stmt->bind_param("i", $id);
                return $stmt->execute();
            } catch (Exception $e) {
                error_log("Error al eliminar visita: " . $e->getMessage());
                return false;
            }
        }
    }
}
