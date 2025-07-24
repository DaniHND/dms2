<?php
// logout.php
// Cerrar sesión del usuario - DMS2

require_once 'config/session.php';

// Verificar si hay una sesión activa
if (SessionManager::isLoggedIn()) {
    // Cerrar sesión normalmente
    SessionManager::logout();
    SessionManager::setFlashMessage('success', 'Sesión cerrada exitosamente');
} else {
    // Si no hay sesión activa, limpiar cualquier residuo
    SessionManager::clearSession();
    SessionManager::setFlashMessage('info', 'No había una sesión activa');
}

// Redirigir al login
header('Location: login.php');
exit();
?>