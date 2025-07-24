<?php
// config/database.php
// Configuración de la base de datos para DMS2

class Database {
    private $host = 'localhost';
    private $db_name = 'dms2';
    private $username = 'root';
    private $password = '';
    private $port = '3306'; // Puerto por defecto de MySQL
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            // Intentar conexión con configuración estándar
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
            
            $this->conn = new PDO(
                $dsn,
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                )
            );
            
            // Verificar que la conexión sea exitosa
            $this->conn->query("SELECT 1");
            
        } catch(PDOException $exception) {
            // Mostrar error más detallado para debugging
            $error_message = "Error de conexión a la base de datos:<br>";
            $error_message .= "Host: " . $this->host . "<br>";
            $error_message .= "Puerto: " . $this->port . "<br>";
            $error_message .= "Base de datos: " . $this->db_name . "<br>";
            $error_message .= "Usuario: " . $this->username . "<br>";
            $error_message .= "Error: " . $exception->getMessage() . "<br><br>";
            
            $error_message .= "<strong>Posibles soluciones:</strong><br>";
            $error_message .= "1. Verificar que MySQL esté ejecutándose en XAMPP<br>";
            $error_message .= "2. Verificar que la base de datos 'dms2' exista<br>";
            $error_message .= "3. Verificar las credenciales de conexión<br>";
            $error_message .= "4. Verificar que el puerto 3306 esté disponible<br>";
            
            die($error_message);
        }
        
        return $this->conn;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
    
    // Método para verificar la conexión
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }
}

// Funciones auxiliares para la base de datos
function executeQuery($query, $params = []) {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        die("Error: No se pudo establecer conexión con la base de datos");
    }
    
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            die("Error al preparar la consulta: " . implode(" ", $conn->errorInfo()));
        }
        
        $result = $stmt->execute($params);
        if (!$result) {
            die("Error al ejecutar la consulta: " . implode(" ", $stmt->errorInfo()));
        }
        
        return $stmt;
    } catch(PDOException $e) {
        error_log("Error en query: " . $e->getMessage());
        die("Error en la base de datos: " . $e->getMessage());
    }
}

function fetchOne($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
}

function fetchAll($query, $params = []) {
    $stmt = executeQuery($query, $params);
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
}

function insertRecord($table, $data) {
    $columns = implode(',', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    
    $stmt = executeQuery($query, $data);
    return $stmt ? true : false;
}

function updateRecord($table, $data, $condition, $conditionParams = []) {
    $setParts = [];
    foreach (array_keys($data) as $key) {
        $setParts[] = "$key = :$key";
    }
    $setClause = implode(', ', $setParts);
    $query = "UPDATE $table SET $setClause WHERE $condition";
    
    $params = array_merge($data, $conditionParams);
    $stmt = executeQuery($query, $params);
    return $stmt ? true : false;
}

function deleteRecord($table, $condition, $params = []) {
    $query = "DELETE FROM $table WHERE $condition";
    $stmt = executeQuery($query, $params);
    return $stmt ? true : false;
}

// Función para log de actividades
function logActivity($userId, $action, $tableName = null, $recordId = null, $description = null) {
    // Solo intentar log si hay conexión a la base de datos
    try {
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        return insertRecord('activity_logs', $data);
    } catch (Exception $e) {
        // Si falla el log, no detener la aplicación
        error_log("Error en logActivity: " . $e->getMessage());
        return false;
    }
}

// Función para obtener configuración del sistema
function getSystemConfig($key) {
    try {
        $query = "SELECT config_value FROM system_config WHERE config_key = :key";
        $result = fetchOne($query, ['key' => $key]);
        return $result ? $result['config_value'] : null;
    } catch (Exception $e) {
        error_log("Error en getSystemConfig: " . $e->getMessage());
        return null;
    }
}

// Función para actualizar configuración del sistema
function updateSystemConfig($key, $value) {
    try {
        $existing = getSystemConfig($key);
        if ($existing) {
            return updateRecord('system_config', 
                ['config_value' => $value], 
                'config_key = :key', 
                ['key' => $key]
            );
        } else {
            return insertRecord('system_config', [
                'config_key' => $key,
                'config_value' => $value
            ]);
        }
    } catch (Exception $e) {
        error_log("Error en updateSystemConfig: " . $e->getMessage());
        return false;
    }
}

// Verificar conexión al cargar este archivo
$database = new Database();
if (!$database->testConnection()) {
    // Mostrar página de error de conexión más amigable
    include_once 'connection_error.php';
    exit();
}
?>