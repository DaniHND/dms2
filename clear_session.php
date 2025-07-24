<?php
// clear_session.php
// Página para limpiar completamente la sesión

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
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

// Redirigir al login con mensaje
session_start();
$_SESSION['flash_message'] = [
    'type' => 'success',
    'message' => 'Sesión limpiada exitosamente. Puede iniciar sesión nuevamente.'
];

header('Location: login.php');
exit();
?>