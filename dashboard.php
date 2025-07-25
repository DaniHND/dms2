<?php
// dashboard.php

require_once 'config/session.php';
require_once 'config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Obtener estadísticas del dashboard
function getDashboardStats($userId, $companyId, $role) {
    $stats = [];
    
    // Total de documentos
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM documents WHERE status = 'active'";
        $params = [];
    } else {
        $query = "SELECT COUNT(*) as total FROM documents WHERE company_id = :company_id AND status = 'active'";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['total_documents'] = $result['total'] ?? 0;
    
    // Documentos subidos hoy
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM documents WHERE DATE(created_at) = CURDATE() AND status = 'active'";
        $params = [];
    } else {
        $query = "SELECT COUNT(*) as total FROM documents WHERE company_id = :company_id AND DATE(created_at) = CURDATE() AND status = 'active'";
        $params = ['company_id' => $companyId];
    }
    $result = fetchOne($query, $params);
    $stats['documents_today'] = $result['total'] ?? 0;
    
    // Total de usuarios
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
        $result = fetchOne($query);
        $stats['total_users'] = $result['total'] ?? 0;
    } else {
        $query = "SELECT COUNT(*) as total FROM users WHERE company_id = :company_id AND status = 'active'";
        $result = fetchOne($query, ['company_id' => $companyId]);
        $stats['total_users'] = $result['total'] ?? 0;
    }
    
    // Total de empresas
    if ($role === 'admin') {
        $query = "SELECT COUNT(*) as total FROM companies WHERE status = 'active'";
        $result = fetchOne($query);
        $stats['total_companies'] = $result['total'] ?? 0;
    } else {
        $stats['total_companies'] = 1;
    }
    
    return $stats;
}

// Obtener actividad reciente
function getRecentActivity($userId, $role, $companyId, $limit = 10) {
    if ($role === 'admin') {
        $query = "SELECT al.*, u.first_name, u.last_name, u.username 
                  FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  ORDER BY al.created_at DESC 
                  LIMIT :limit";
        $params = ['limit' => $limit];
    } else {
        $query = "SELECT al.*, u.first_name, u.last_name, u.username 
                  FROM activity_logs al 
                  LEFT JOIN users u ON al.user_id = u.id 
                  WHERE u.company_id = :company_id OR al.user_id = :user_id
                  ORDER BY al.created_at DESC 
                  LIMIT :limit";
        $params = ['company_id' => $companyId, 'user_id' => $userId, 'limit' => $limit];
    }
    
    return fetchAll($query, $params);
}

// Obtener documentos recientes
function getRecentDocuments($userId, $role, $companyId, $limit = 5) {
    if ($role === 'admin') {
        $query = "SELECT d.*, c.name as company_name, u.first_name, u.last_name, dt.name as document_type
                  FROM documents d
                  LEFT JOIN companies c ON d.company_id = c.id
                  LEFT JOIN users u ON d.user_id = u.id
                  LEFT JOIN document_types dt ON d.document_type_id = dt.id
                  WHERE d.status = 'active'
                  ORDER BY d.created_at DESC
                  LIMIT :limit";
        $params = ['limit' => $limit];
    } else {
        $query = "SELECT d.*, c.name as company_name, u.first_name, u.last_name, dt.name as document_type
                  FROM documents d
                  LEFT JOIN companies c ON d.company_id = c.id
                  LEFT JOIN users u ON d.user_id = u.id
                  LEFT JOIN document_types dt ON d.document_type_id = dt.id
                  WHERE d.company_id = :company_id AND d.status = 'active'
                  ORDER BY d.created_at DESC
                  LIMIT :limit";
        $params = ['company_id' => $companyId, 'limit' => $limit];
    }
    
    return fetchAll($query, $params);
}

// Obtener count de documentos no leídos para el badge
function getUnreadInboxCount($userId, $companyId, $role) {
    try {
        $unreadQuery = "SELECT COUNT(*) as unread FROM inbox_records ir 
                       INNER JOIN documents d ON ir.document_id = d.id 
                       WHERE ir.recipient_user_id = :user_id AND ir.read_status = 'unread' AND ir.status != 'deleted'";
        $unreadParams = ['user_id' => $userId];
        
        if ($role !== 'admin' && $companyId) {
            $unreadQuery .= " AND d.company_id = :company_id";
            $unreadParams['company_id'] = $companyId;
        }
        
        $unreadResult = fetchOne($unreadQuery, $unreadParams);
        return $unreadResult['unread'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

$stats = getDashboardStats($currentUser['id'], $currentUser['company_id'], $currentUser['role']);
$recentActivity = getRecentActivity($currentUser['id'], $currentUser['role'], $currentUser['company_id']);
$recentDocuments = getRecentDocuments($currentUser['id'], $currentUser['role'], $currentUser['company_id']);
$unreadCount = getUnreadInboxCount($currentUser['id'], $currentUser['company_id'], $currentUser['role']);

// Obtener información de la empresa del usuario
$companyInfo = null;
if ($currentUser['company_id']) {
    $query = "SELECT * FROM companies WHERE id = :id";
    $companyInfo = fetchOne($query, ['id' => $currentUser['company_id']]);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DMS2</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        /* Estilos adicionales para el badge de notificaciones */
        .nav-badge {
            background: #ef4444;
            color: white;
            font-size: 0.625rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
            line-height: 1.2;
            margin-left: auto;
        }

        .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--spacing-3);
            padding: var(--spacing-3) var(--spacing-4);
            color: #cbd5e1;
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.875rem;
            position: relative;
        }

        .nav-content {
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
            flex: 1;
        }
    </style>
</head>
<body class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <img src="https://perdomoyasociados.com/wp-content/uploads/2023/09/logo_perdomo_2023_dorado-768x150.png" alt="Perdomo y Asociados" class="logo-image">
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item active">
                    <a href="dashboard.php" class="nav-link">
                        <div class="nav-content">
                            <i data-feather="home"></i>
                            <span>Dashboard</span>
                        </div>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="upload.php" class="nav-link">
                        <div class="nav-content">
                            <i data-feather="upload"></i>
                            <span>Subir Documentos</span>
                        </div>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="inbox.php" class="nav-link">
                        <div class="nav-content">
                            <i data-feather="inbox"></i>
                            <span>Bandeja de Entrada</span>
                        </div>
                        <?php if ($unreadCount > 0): ?>
                        <span class="nav-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="files.php" class="nav-link">
                        <div class="nav-content">
                            <i data-feather="folder"></i>
                            <span>Archivos</span>
                        </div>
                    </a>
                </li>
                
                <li class="nav-divider"></li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showComingSoon('Reportes')">
                        <div class="nav-content">
                            <i data-feather="bar-chart-2"></i>
                            <span>Reportes</span>
                        </div>
                    </a>
                </li>
                
                <?php if ($currentUser['role'] === 'admin' || checkPermission('admin')): ?>
                <li class="nav-section">
                    <span>ADMINISTRACIÓN</span>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showComingSoon('Gestión de Usuarios')">
                        <div class="nav-content">
                            <i data-feather="users"></i>
                            <span>Usuarios</span>
                        </div>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showComingSoon('Gestión de Empresas')">
                        <div class="nav-content">
                            <i data-feather="briefcase"></i>
                            <span>Empresas</span>
                        </div>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showComingSoon('Gestión de Departamentos')">
                        <div class="nav-content">
                            <i data-feather="layers"></i>
                            <span>Departamentos</span>
                        </div>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showComingSoon('Grupos de Seguridad')">
                        <div class="nav-content">
                            <i data-feather="shield"></i>
                            <span>Grupos</span>
                        </div>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showComingSoon('Configuración del Sistema')">
                        <div class="nav-content">
                            <i data-feather="settings"></i>
                            <span>Configuración</span>
                        </div>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        
    </aside>
    
    <!-- Contenido principal -->
    <main class="main-content">
        <!-- Header -->
        <header class="content-header">
            <div class="header-left">
                <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                    <i data-feather="menu"></i>
                </button>
                <h1>Dashboard</h1>
            </div>
            
            <div class="header-right">
                <div class="header-info">
                    <div class="user-name-header"><?php echo htmlspecialchars(getFullName()); ?></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
                
                <div class="header-actions">
                    <button class="btn-icon" onclick="showUserMenu()">
                        <i data-feather="settings"></i>
                    </button>
                    <a href="logout.php" class="btn-icon logout-btn" onclick="return confirm('¿Está seguro que desea cerrar sesión?')">
                        <i data-feather="log-out"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Contenido del dashboard -->
        <div class="dashboard-content">
            <!-- Tarjetas de estadísticas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="stat-number"><?php echo number_format($stats['total_documents']); ?></div>
                        <div class="stat-label">Total Documentos</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="stat-number"><?php echo number_format($stats['documents_today']); ?></div>
                        <div class="stat-label">Subidos Hoy</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="stat-label">Usuarios Activos</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-content">
                        <div class="stat-number"><?php echo number_format($stats['total_companies']); ?></div>
                        <div class="stat-label"><?php echo $currentUser['role'] === 'admin' ? 'Empresas' : 'Mi Empresa'; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Contenido principal en dos columnas -->
            <div class="dashboard-grid">
                <!-- Columna izquierda -->
                <div class="dashboard-column">
                    <!-- Acciones rápidas -->
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3>Acciones Rápidas</h3>
                            <i data-feather="zap"></i>
                        </div>
                        <div class="widget-content">
                            <div class="quick-actions">
                                <button class="quick-action-btn" onclick="window.location.href='upload.php'">
                                    <i data-feather="upload"></i>
                                    <span>Subir Documento</span>
                                </button>
                                
                                <button class="quick-action-btn" onclick="window.location.href='files.php'">
                                    <i data-feather="folder"></i>
                                    <span>Ver Archivos</span>
                                </button>
                                
                                <button class="quick-action-btn" onclick="window.location.href='inbox.php'">
                                    <i data-feather="inbox"></i>
                                    <span>Ver Bandeja</span>
                                </button>
                                
                                <?php if ($currentUser['role'] === 'admin'): ?>
                                <button class="quick-action-btn" onclick="showComingSoon('Nuevo Usuario')">
                                    <i data-feather="user-plus"></i>
                                    <span>Nuevo Usuario</span>
                                </button>
                                
                                <button class="quick-action-btn" onclick="showComingSoon('Nueva Empresa')">
                                    <i data-feather="briefcase"></i>
                                    <span>Nueva Empresa</span>
                                </button>
                                <?php endif; ?>
                                
                                <button class="quick-action-btn" onclick="showComingSoon('Configuración')">
                                    <i data-feather="settings"></i>
                                    <span>Configuración</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Documentos recientes -->
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3>Documentos Recientes</h3>
                            <i data-feather="clock"></i>
                        </div>
                        <div class="widget-content">
                            <?php if (empty($recentDocuments)): ?>
                            <div class="empty-state">
                                <i data-feather="file"></i>
                                <p>No hay documentos recientes</p>
                                <button class="btn" onclick="window.location.href='upload.php'">
                                    <i data-feather="plus"></i>
                                    Subir primer documento
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="document-list">
                                <?php foreach ($recentDocuments as $doc): ?>
                                <div class="document-item">
                                    <div class="document-icon">
                                        <i data-feather="file-text"></i>
                                    </div>
                                    <div class="document-info">
                                        <div class="document-name"><?php echo htmlspecialchars($doc['name']); ?></div>
                                        <div class="document-meta">
                                            <span class="document-type"><?php echo htmlspecialchars($doc['document_type'] ?? 'Sin tipo'); ?></span>
                                            <span class="document-date"><?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="document-actions">
                                        <button class="btn-icon-sm" onclick="showComingSoon('Ver Documento')">
                                            <i data-feather="eye"></i>
                                        </button>
                                        <button class="btn-icon-sm" onclick="showComingSoon('Descargar')">
                                            <i data-feather="download"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="widget-footer">
                                <a href="files.php" class="view-all-link">
                                    Ver todos los documentos 
                                    <i data-feather="arrow-right"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Columna derecha -->
                <div class="dashboard-column">
                    <!-- Actividad reciente -->
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3>Actividad Reciente</h3>
                            <i data-feather="activity"></i>
                        </div>
                        <div class="widget-content">
                            <?php if (empty($recentActivity)): ?>
                            <div class="empty-state">
                                <i data-feather="activity"></i>
                                <p>No hay actividad reciente</p>
                            </div>
                            <?php else: ?>
                            <div class="activity-timeline">
                                <?php foreach ($recentActivity as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <?php
                                        $iconMap = [
                                            'login' => 'log-in',
                                            'logout' => 'log-out',
                                            'upload' => 'upload',
                                            'download' => 'download',
                                            'create' => 'plus',
                                            'update' => 'edit',
                                            'delete' => 'trash-2',
                                            'view' => 'eye'
                                        ];
                                        $icon = $iconMap[$activity['action']] ?? 'activity';
                                        ?>
                                        <i data-feather="<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-description">
                                            <?php 
                                            $userName = $activity['first_name'] ? 
                                                htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) : 
                                                htmlspecialchars($activity['username'] ?? 'Usuario desconocido');
                                            
                                            echo $userName . ' - ' . htmlspecialchars($activity['description'] ?? ucfirst($activity['action']));
                                            ?>
                                        </div>
                                        <div class="activity-time">
                                            <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="widget-footer">
                                <a href="#" onclick="showComingSoon('Ver Toda la Actividad')" class="view-all-link">
                                    Ver toda la actividad 
                                    <i data-feather="arrow-right"></i>
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Información del sistema -->
                    <div class="dashboard-widget">
                        <div class="widget-header">
                            <h3>Información del Sistema</h3>
                            <i data-feather="info"></i>
                        </div>
                        <div class="widget-content">
                            <div class="system-info">
                                <div class="info-item">
                                    <span class="info-label">Usuario:</span>
                                    <span class="info-value"><?php echo htmlspecialchars(getFullName()); ?></span>
                                </div>
                                
                                <div class="info-item">
                                    <span class="info-label">Rol:</span>
                                    <span class="info-value"><?php echo ucfirst($currentUser['role']); ?></span>
                                </div>
                                
                                <?php if ($companyInfo): ?>
                                <div class="info-item">
                                    <span class="info-label">Empresa:</span>
                                    <span class="info-value"><?php echo htmlspecialchars($companyInfo['name']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-item">
                                    <span class="info-label">Último acceso:</span>
                                    <span class="info-value"><?php echo date('d/m/Y H:i'); ?></span>
                                </div>
                                
                                <?php if ($unreadCount > 0): ?>
                                <div class="info-item">
                                    <span class="info-label">Pendientes:</span>
                                    <span class="info-value">
                                        <a href="inbox.php" style="color: #ef4444; font-weight: 600;">
                                            <?php echo $unreadCount; ?> documento(s) sin leer
                                        </a>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="info-item">
                                    <span class="info-label">Versión:</span>
                                    <span class="info-value">DMS2 v1.0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Overlay para móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
    
    <!-- Modal de "Próximamente" -->
    <div id="comingSoonModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="comingSoonTitle">Próximamente</h3>
                <button class="close" onclick="hideComingSoon()">
                    <i data-feather="x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="coming-soon-content">
                    <div class="coming-soon-icon">
                        <i data-feather="clock"></i>
                    </div>
                    <p id="comingSoonMessage">Esta funcionalidad estará disponible próximamente.</p>
                    <p class="coming-soon-note">Estamos trabajando para implementar todos los módulos del sistema.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
    <script>
        // Inicializar Feather icons
        feather.replace();
        
        // Inicializar reloj
        updateTime();
        setInterval(updateTime, 1000);
        
        // Función para alternar sidebar en móvil
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            }
        }
        
        // Función para actualizar la hora
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
        
        // Función para mostrar modal "próximamente"
        function showComingSoon(feature) {
            const modal = document.getElementById('comingSoonModal');
            const title = document.getElementById('comingSoonTitle');
            const message = document.getElementById('comingSoonMessage');
            
            title.textContent = feature;
            message.textContent = `La funcionalidad "${feature}" estará disponible próximamente.`;
            
            modal.style.display = 'flex';
            
            // Animar entrada
            const content = modal.querySelector('.modal-content');
            content.style.transform = 'scale(0.8)';
            content.style.opacity = '0';
            
            setTimeout(() => {
                content.style.transform = 'scale(1)';
                content.style.opacity = '1';
                content.style.transition = 'all 0.3s ease-out';
            }, 10);
        }
        
        // Función para ocultar modal "próximamente"
        function hideComingSoon() {
            const modal = document.getElementById('comingSoonModal');
            const content = modal.querySelector('.modal-content');
            
            content.style.transform = 'scale(0.8)';
            content.style.opacity = '0';
            
            setTimeout(() => {
                modal.style.display = 'none';
                content.style.transform = '';
                content.style.opacity = '';
                content.style.transition = '';
            }, 300);
        }
        
        // Función para mostrar notificaciones
        function showNotifications() {
            showComingSoon('Sistema de Notificaciones');
        }
        
        // Función para mostrar menú de usuario
        function showUserMenu() {
            showComingSoon('Menú de Usuario');
        }
        
        // Cerrar modal al hacer clic fuera
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('comingSoonModal');
            if (e.target === modal) {
                hideComingSoon();
            }
        });
        
        // Cerrar modal con tecla Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('comingSoonModal');
                if (modal.style.display === 'flex') {
                    hideComingSoon();
                }
            }
        });
        
        // Responsive behavior
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Log de acceso al dashboard
        <?php
        logActivity($currentUser['id'], 'dashboard_access', 'users', $currentUser['id'], 'Usuario accedió al dashboard');
        ?>
    </script>
</body>
</html>