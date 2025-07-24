<?php
// config/session.php
// Manejo de sesiones para DMS2

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

class SessionManager {
    
    public static function startSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function login($user) {
        self::startSession();
        
        // Regenerar ID de sesión por seguridad
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['department_id'] = $user['department_id'];
        $_SESSION['group_id'] = $user['group_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['permissions'] = self::getUserPermissions($user['group_id']);
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        
        // Actualizar último login
        require_once 'database.php';
        updateRecord('users', 
            ['last_login' => date('Y-m-d H:i:s')], 
            'id = :id', 
            ['id' => $user['id']]
        );
        
        // Log de actividad
        logActivity($user['id'], 'login', 'users', $user['id'], 'Usuario inició sesión');
    }
    
    public static function logout() {
        self::startSession();
        
        if (isset($_SESSION['user_id'])) {
            // Log de actividad antes de cerrar sesión
            require_once 'database.php';
            logActivity($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id'], 'Usuario cerró sesión');
        }
        
        // Destruir todas las variables de sesión
        $_SESSION = array();
        
        // Destruir la cookie de sesión si existe
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destruir la sesión completamente
        session_destroy();
        
        // Iniciar nueva sesión limpia
        session_start();
        session_regenerate_id(true);
    }
    
    public static function isLoggedIn() {
        self::startSession();
        
        // Verificar que existan las variables de sesión básicas
        return isset($_SESSION['user_id']) && 
               !empty($_SESSION['user_id']) && 
               isset($_SESSION['username']) && 
               isset($_SESSION['login_time']);
    }
    
    public static function checkSession() {
        self::startSession();
        
        if (!self::isLoggedIn()) {
            return false;
        }
        
        // Verificar timeout de sesión (1 hora por defecto)
        $timeout = 3600; // 1 hora
        
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > $timeout) {
            self::logout();
            return false;
        }
        
        // Verificar que el usuario aún existe y esté activo
        if (isset($_SESSION['user_id'])) {
            require_once 'database.php';
            $query = "SELECT id, status FROM users WHERE id = :id AND status = 'active'";
            $user = fetchOne($query, ['id' => $_SESSION['user_id']]);
            
            if (!$user) {
                self::logout();
                return false;
            }
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public static function requireLogin() {
        if (!self::checkSession()) {
            // Redirigir al login con mensaje
            self::setFlashMessage('warning', 'Debe iniciar sesión para acceder al sistema');
            header('Location: ' . self::getBaseUrl() . 'login.php');
            exit();
        }
    }
    
    public static function requireAdmin() {
        self::requireLogin();
        if ($_SESSION['role'] !== 'admin') {
            self::setFlashMessage('error', 'No tiene permisos para acceder a esta sección');
            header('Location: ' . self::getBaseUrl() . 'dashboard.php');
            exit();
        }
    }
    
    public static function getUserPermissions($groupId) {
        if (!$groupId) return [];
        
        require_once 'database.php';
        $query = "SELECT permissions FROM security_groups WHERE id = :id AND status = 'active'";
        $result = fetchOne($query, ['id' => $groupId]);
        
        if ($result && $result['permissions']) {
            return json_decode($result['permissions'], true);
        }
        
        return [];
    }
    
    public static function hasPermission($permission) {
        self::startSession();
        
        if (!isset($_SESSION['permissions'])) {
            return false;
        }
        
        return isset($_SESSION['permissions'][$permission]) && 
               $_SESSION['permissions'][$permission] === true;
    }
    
    public static function canAccessCompany($companyId) {
        self::startSession();
        
        // Admin puede acceder a todas las empresas
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }
        
        // Usuario solo puede acceder a su empresa
        return isset($_SESSION['company_id']) && $_SESSION['company_id'] == $companyId;
    }
    
    public static function canAccessDepartment($departmentId) {
        self::startSession();
        
        // Admin puede acceder a todos los departamentos
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return true;
        }
        
        // Usuario solo puede acceder a su departamento
        return isset($_SESSION['department_id']) && $_SESSION['department_id'] == $departmentId;
    }
    
    public static function getCurrentUser() {
        self::startSession();
        
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'first_name' => $_SESSION['first_name'] ?? null,
            'last_name' => $_SESSION['last_name'] ?? null,
            'company_id' => $_SESSION['company_id'] ?? null,
            'department_id' => $_SESSION['department_id'] ?? null,
            'group_id' => $_SESSION['group_id'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'permissions' => $_SESSION['permissions'] ?? []
        ];
    }
    
    public static function getFullName() {
        self::startSession();
        $firstName = $_SESSION['first_name'] ?? '';
        $lastName = $_SESSION['last_name'] ?? '';
        return trim($firstName . ' ' . $lastName) ?: ($_SESSION['username'] ?? 'Usuario');
    }
    
    public static function setFlashMessage($type, $message) {
        self::startSession();
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    public static function getFlashMessage() {
        self::startSession();
        
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $message;
        }
        
        return null;
    }
    
    public static function getBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $path = dirname($_SERVER['SCRIPT_NAME']);
        
        // Asegurar que termine con /
        if (substr($path, -1) !== '/') {
            $path .= '/';
        }
        
        return $protocol . $host . $path;
    }
    
    public static function clearSession() {
        self::startSession();
        $_SESSION = array();
        session_destroy();
    }
    
    public static function getSessionInfo() {
        self::startSession();
        
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'session_id' => session_id(),
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null,
            'time_remaining' => isset($_SESSION['last_activity']) ? 
                (3600 - (time() - $_SESSION['last_activity'])) : null
        ];
    }
}

// Función auxiliar para verificar permisos
function checkPermission($permission) {
    return SessionManager::hasPermission($permission);
}

// Función auxiliar para obtener el usuario actual
function getCurrentUser() {
    return SessionManager::getCurrentUser();
}

// Función auxiliar para obtener el nombre completo
function getFullName() {
    return SessionManager::getFullName();
}

// Función para debug de sesión (solo en desarrollo)
function debugSession() {
    if (defined('DEBUG') && DEBUG === true) {
        echo "<pre>";
        echo "Session Status: " . session_status() . "\n";
        echo "Session ID: " . session_id() . "\n";
        echo "Session Data: " . print_r($_SESSION, true);
        echo "</pre>";
    }
}
?>