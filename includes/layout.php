<?php
// includes/layout.php
// Layout base reutilizable para todos los módulos - DMS2

// Verificar que las sesiones estén configuradas
require_once __DIR__ . '/../config/session.php';
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Función para generar el HTML base
function renderLayout($pageTitle, $pageContent, $additionalCSS = [], $additionalJS = [], $pageId = '') {
    global $currentUser;
    
    // Determinar el ID de la página si no se proporciona
    if (empty($pageId)) {
        $pageId = basename($_SERVER['PHP_SELF'], '.php');
    }
    
    ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - DMS2</title>
    
    <!-- CSS Base -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    
    <!-- CSS Adicional -->
    <?php foreach ($additionalCSS as $css): ?>
    <link rel="stylesheet" href="<?php echo $css; ?>">
    <?php endforeach; ?>
    
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <style>
        /* Estilos específicos para badges en navegación */
        .nav-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            font-size: 0.625rem;
            font-weight: 600;
            padding: 2px 5px;
            border-radius: 8px;
            min-width: 16px;
            text-align: center;
            line-height: 1;
            display: none;
        }
        
        .nav-link {
            position: relative;
        }
        
        .nav-link.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
    </style>
</head>
<body class="dashboard-layout" data-page="<?php echo htmlspecialchars($pageId); ?>">
    
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <!-- Contenido principal -->
    <main class="main-content">
        <!-- Header -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            </div>
            
            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(getFullName()); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                
                <div class="header-actions">
                    <button class="btn-icon" onclick="showNotifications()" title="Notificaciones">
                        <i data-feather="bell"></i>
                        <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                    </button>
                    
                    <button class="btn-icon" onclick="showUserMenu()" title="Configuración">
                        <i data-feather="settings"></i>
                    </button>
                    
                    <a href="logout.php" class="btn-icon logout-btn" 
                       onclick="return confirm('¿Está seguro que desea cerrar sesión?')" 
                       title="Cerrar Sesión">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Contenido de la página -->
        <div class="page-content">
            <?php echo $pageContent; ?>
        </div>
    </main>
    
    <!-- Modales globales -->
    <?php include __DIR__ . '/modals.php'; ?>
    
    <!-- JavaScript Base -->
    <script src="assets/js/sidebar.js"></script>
    
    <!-- JavaScript Adicional -->
    <?php foreach ($additionalJS as $js): ?>
    <script src="<?php echo $js; ?>"></script>
    <?php endforeach; ?>
    
    <script>
        // Inicializar Feather icons
        feather.replace();
        
        // Inicializar reloj
        function updateTime() {
            const timeElement = document.getElementById('currentTime');
            if (timeElement) {
                const now = new Date();
                const timeString = now.toLocaleTimeString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                const dateString = now.toLocaleDateString('es-ES', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });
                timeElement.textContent = `${dateString} ${timeString}`;
            }
        }
        
        updateTime();
        setInterval(updateTime, 1000);
        
        // Funciones globales para modales
        function showNotifications() {
            showComingSoon('Sistema de Notificaciones');
        }
        
        function showUserMenu() {
            showComingSoon('Menú de Usuario');
        }
        
        // Log de acceso a la página
        <?php
        if (function_exists('logActivity')) {
            logActivity($currentUser['id'], 'page_access', 'pages', null, 'Usuario accedió a: ' . $pageTitle);
        }
        ?>
    </script>
</body>
</html>
<?php
    return ob_get_clean();
}

// Función auxiliar para renderizar contenido con layout
function renderPage($title, $content, $css = [], $js = [], $pageId = '') {
    echo renderLayout($title, $content, $css, $js, $pageId);
}