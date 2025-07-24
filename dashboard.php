<?php
// dashboard.php - Actualizado para usar el nuevo layout

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/layout.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Obtener estadísticas del dashboard (misma función anterior)
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

// Obtener actividad reciente (misma función anterior)
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

// Obtener documentos recientes (misma función anterior)
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

$stats = getDashboardStats($currentUser['id'], $currentUser['company_id'], $currentUser['role']);
$recentActivity = getRecentActivity($currentUser['id'], $currentUser['role'], $currentUser['company_id']);
$recentDocuments = getRecentDocuments($currentUser['id'], $currentUser['role'], $currentUser['company_id']);

// Obtener información de la empresa del usuario
$companyInfo = null;
if ($currentUser['company_id']) {
    $query = "SELECT * FROM companies WHERE id = :id";
    $companyInfo = fetchOne($query, ['id' => $currentUser['company_id']]);
}

// Preparar contenido del dashboard
ob_start();
?>

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
                        
                        <button class="quick-action-btn" onclick="showComingSoon('Buscar Archivo')">
                            <i data-feather="search"></i>
                            <span>Buscar Archivo</span>
                        </button>
                        
                        <button class="quick-action-btn" onclick="showComingSoon('Ver Reportes')">
                            <i data-feather="bar-chart"></i>
                            <span>Ver Reportes</span>
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
                        <a href="#" onclick="showComingSoon('Ver Todos los Documentos')" class="view-all-link">
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

<?php
$dashboardContent = ob_get_clean();

// CSS adicional específico para el dashboard
$additionalCSS = [];

// JavaScript adicional específico para el dashboard
$additionalJS = ['assets/js/dashboard.js'];

// Renderizar la página usando el layout
renderPage('Dashboard', $dashboardContent, $additionalCSS, $additionalJS, 'dashboard');
?>