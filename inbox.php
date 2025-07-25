<?php
// inbox.php - Versión corregida
// Módulo de Bandeja de Entrada - DMS2

require_once 'config/session.php';
require_once 'config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();

// Parámetros de búsqueda y filtrado
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$priority = $_GET['priority'] ?? 'all';
$sender = $_GET['sender'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Obtener documentos de la bandeja de entrada - VERSIÓN CORREGIDA
function getInboxDocuments($userId, $companyId, $role, $search = '', $status = 'all', $priority = 'all', $sender = 'all', $limit = 20, $offset = 0) {
    $whereConditions = [];
    $params = [];
    
    // Base query - documentos compartidos con el usuario
    $baseWhere = "ir.recipient_user_id = :user_id AND ir.status != 'deleted'";
    $params['user_id'] = $userId;
    
    // Si no es admin, filtrar por empresa
    if ($role !== 'admin' && $companyId) {
        $baseWhere .= " AND d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }
    
    $whereConditions[] = $baseWhere;
    
    // Filtro de búsqueda
    if (!empty($search)) {
        $whereConditions[] = "(d.name LIKE :search OR d.description LIKE :search OR ir.message LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    
    // Filtro de estado
    if ($status !== 'all') {
        $whereConditions[] = "ir.read_status = :status";
        $params['status'] = $status;
    }
    
    // Filtro de prioridad
    if ($priority !== 'all') {
        $whereConditions[] = "ir.priority = :priority";
        $params['priority'] = $priority;
    }
    
    // Filtro de remitente
    if ($sender !== 'all') {
        $whereConditions[] = "ir.sender_user_id = :sender";
        $params['sender'] = $sender;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Query corregida sin columnas que no existen
    $query = "SELECT ir.*, 
                     d.name as document_name, 
                     d.file_path, 
                     d.file_size, 
                     d.mime_type,
                     dt.name as document_type, 
                     COALESCE(dt.icon, 'file-text') as type_icon,
                     sender.first_name as sender_first_name, 
                     sender.last_name as sender_last_name, 
                     sender.username as sender_username,
                     c.name as company_name
              FROM inbox_records ir
              INNER JOIN documents d ON ir.document_id = d.id
              LEFT JOIN document_types dt ON d.document_type_id = dt.id
              LEFT JOIN users sender ON ir.sender_user_id = sender.id
              LEFT JOIN companies c ON d.company_id = c.id
              WHERE $whereClause
              ORDER BY ir.created_at DESC
              LIMIT :limit OFFSET :offset";
    
    $params['limit'] = $limit;
    $params['offset'] = $offset;
    
    try {
        return fetchAll($query, $params);
    } catch (Exception $e) {
        error_log("Error in getInboxDocuments: " . $e->getMessage());
        return [];
    }
}

// Obtener conteo total para paginación - VERSIÓN CORREGIDA
function getInboxCount($userId, $companyId, $role, $search = '', $status = 'all', $priority = 'all', $sender = 'all') {
    $whereConditions = [];
    $params = [];
    
    $baseWhere = "ir.recipient_user_id = :user_id AND ir.status != 'deleted'";
    $params['user_id'] = $userId;
    
    if ($role !== 'admin' && $companyId) {
        $baseWhere .= " AND d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }
    
    $whereConditions[] = $baseWhere;
    
    if (!empty($search)) {
        $whereConditions[] = "(d.name LIKE :search OR d.description LIKE :search OR ir.message LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    
    if ($status !== 'all') {
        $whereConditions[] = "ir.read_status = :status";
        $params['status'] = $status;
    }
    
    if ($priority !== 'all') {
        $whereConditions[] = "ir.priority = :priority";
        $params['priority'] = $priority;
    }
    
    if ($sender !== 'all') {
        $whereConditions[] = "ir.sender_user_id = :sender";
        $params['sender'] = $sender;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $query = "SELECT COUNT(*) as total
              FROM inbox_records ir
              INNER JOIN documents d ON ir.document_id = d.id
              WHERE $whereClause";
    
    try {
        $result = fetchOne($query, $params);
        return $result['total'] ?? 0;
    } catch (Exception $e) {
        error_log("Error in getInboxCount: " . $e->getMessage());
        return 0;
    }
}

// Obtener estadísticas de la bandeja - VERSIÓN CORREGIDA
function getInboxStats($userId, $companyId, $role) {
    $params = ['user_id' => $userId];
    $companyFilter = '';
    
    if ($role !== 'admin' && $companyId) {
        $companyFilter = " AND d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }
    
    try {
        // Total de documentos
        $query = "SELECT COUNT(*) as total FROM inbox_records ir 
                  INNER JOIN documents d ON ir.document_id = d.id 
                  WHERE ir.recipient_user_id = :user_id AND ir.status != 'deleted'" . $companyFilter;
        $result = fetchOne($query, $params);
        $total = $result['total'] ?? 0;
        
        // No leídos
        $query = "SELECT COUNT(*) as total FROM inbox_records ir 
                  INNER JOIN documents d ON ir.document_id = d.id 
                  WHERE ir.recipient_user_id = :user_id AND ir.read_status = 'unread' AND ir.status != 'deleted'" . $companyFilter;
        $result = fetchOne($query, $params);
        $unread = $result['total'] ?? 0;
        
        // Importantes
        $query = "SELECT COUNT(*) as total FROM inbox_records ir 
                  INNER JOIN documents d ON ir.document_id = d.id 
                  WHERE ir.recipient_user_id = :user_id AND ir.priority = 'high' AND ir.status != 'deleted'" . $companyFilter;
        $result = fetchOne($query, $params);
        $important = $result['total'] ?? 0;
        
        // Recibidos hoy
        $query = "SELECT COUNT(*) as total FROM inbox_records ir 
                  INNER JOIN documents d ON ir.document_id = d.id 
                  WHERE ir.recipient_user_id = :user_id AND DATE(ir.created_at) = CURDATE() AND ir.status != 'deleted'" . $companyFilter;
        $result = fetchOne($query, $params);
        $today = $result['total'] ?? 0;
        
        return [
            'total' => $total,
            'unread' => $unread,
            'important' => $important,
            'today' => $today
        ];
    } catch (Exception $e) {
        error_log("Error in getInboxStats: " . $e->getMessage());
        return [
            'total' => 0,
            'unread' => 0,
            'important' => 0,
            'today' => 0
        ];
    }
}

// Obtener lista de remitentes para filtro - VERSIÓN CORREGIDA
function getSenders($userId, $companyId, $role) {
    $params = ['user_id' => $userId];
    $companyFilter = '';
    
    if ($role !== 'admin' && $companyId) {
        $companyFilter = " AND d.company_id = :company_id";
        $params['company_id'] = $companyId;
    }
    
    $query = "SELECT DISTINCT sender.id, sender.first_name, sender.last_name, sender.username
              FROM inbox_records ir
              INNER JOIN documents d ON ir.document_id = d.id
              LEFT JOIN users sender ON ir.sender_user_id = sender.id
              WHERE ir.recipient_user_id = :user_id AND ir.status != 'deleted'" . $companyFilter . "
              ORDER BY sender.first_name, sender.last_name";
    
    try {
        return fetchAll($query, $params);
    } catch (Exception $e) {
        error_log("Error in getSenders: " . $e->getMessage());
        return [];
    }
}

// Función auxiliar para formatear tamaño de archivo
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 bytes';
    }
}

// Procesar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => ''];
    
    try {
        switch ($action) {
            case 'mark_read':
                $recordId = (int)($_POST['record_id'] ?? 0);
                if ($recordId) {
                    $updated = updateRecord('inbox_records', 
                        ['read_status' => 'read', 'read_at' => date('Y-m-d H:i:s')], 
                        'id = :id AND recipient_user_id = :user_id', 
                        ['id' => $recordId, 'user_id' => $currentUser['id']]
                    );
                    
                    if ($updated) {
                        $response = ['success' => true, 'message' => 'Marcado como leído'];
                        logActivity($currentUser['id'], 'mark_read', 'inbox_records', $recordId, 'Documento marcado como leído');
                    } else {
                        $response = ['success' => false, 'message' => 'Error al marcar como leído'];
                    }
                }
                break;
                
            case 'mark_unread':
                $recordId = (int)($_POST['record_id'] ?? 0);
                if ($recordId) {
                    $updated = updateRecord('inbox_records', 
                        ['read_status' => 'unread', 'read_at' => null], 
                        'id = :id AND recipient_user_id = :user_id', 
                        ['id' => $recordId, 'user_id' => $currentUser['id']]
                    );
                    
                    if ($updated) {
                        $response = ['success' => true, 'message' => 'Marcado como no leído'];
                        logActivity($currentUser['id'], 'mark_unread', 'inbox_records', $recordId, 'Documento marcado como no leído');
                    } else {
                        $response = ['success' => false, 'message' => 'Error al marcar como no leído'];
                    }
                }
                break;
                
            case 'delete':
                $recordId = (int)($_POST['record_id'] ?? 0);
                if ($recordId) {
                    $updated = updateRecord('inbox_records', 
                        ['status' => 'deleted'], 
                        'id = :id AND recipient_user_id = :user_id', 
                        ['id' => $recordId, 'user_id' => $currentUser['id']]
                    );
                    
                    if ($updated) {
                        $response = ['success' => true, 'message' => 'Documento eliminado de la bandeja'];
                        logActivity($currentUser['id'], 'delete', 'inbox_records', $recordId, 'Documento eliminado de la bandeja de entrada');
                    } else {
                        $response = ['success' => false, 'message' => 'Error al eliminar documento'];
                    }
                }
                break;
                
            case 'mark_all_read':
                $params = ['user_id' => $currentUser['id']];
                $companyFilter = '';
                
                if ($currentUser['role'] !== 'admin' && $currentUser['company_id']) {
                    $companyFilter = " AND d.company_id = :company_id";
                    $params['company_id'] = $currentUser['company_id'];
                }
                
                $query = "UPDATE inbox_records ir 
                          INNER JOIN documents d ON ir.document_id = d.id 
                          SET ir.read_status = 'read', ir.read_at = NOW() 
                          WHERE ir.recipient_user_id = :user_id AND ir.read_status = 'unread' AND ir.status != 'deleted'" . $companyFilter;
                
                $stmt = executeQuery($query, $params);
                if ($stmt) {
                    $response = ['success' => true, 'message' => 'Todos los documentos marcados como leídos'];
                    logActivity($currentUser['id'], 'mark_all_read', 'inbox_records', null, 'Todos los documentos marcados como leídos');
                } else {
                    $response = ['success' => false, 'message' => 'Error al marcar todos como leídos'];
                }
                break;
        }
    } catch (Exception $e) {
        error_log("Error in AJAX action: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Error interno del servidor'];
    }
    
    echo json_encode($response);
    exit();
}

// Obtener datos para la vista con manejo de errores
try {
    $inboxDocuments = getInboxDocuments($currentUser['id'], $currentUser['company_id'], $currentUser['role'], $search, $status, $priority, $sender, $limit, $offset);
    $totalRecords = getInboxCount($currentUser['id'], $currentUser['company_id'], $currentUser['role'], $search, $status, $priority, $sender);
    $totalPages = ceil($totalRecords / $limit);
    $stats = getInboxStats($currentUser['id'], $currentUser['company_id'], $currentUser['role']);
    $senders = getSenders($currentUser['id'], $currentUser['company_id'], $currentUser['role']);
} catch (Exception $e) {
    error_log("Error loading inbox data: " . $e->getMessage());
    $inboxDocuments = [];
    $totalRecords = 0;
    $totalPages = 0;
    $stats = ['total' => 0, 'unread' => 0, 'important' => 0, 'today' => 0];
    $senders = [];
}

// Log de acceso al módulo
logActivity($currentUser['id'], 'inbox_access', 'inbox_records', null, 'Usuario accedió a la bandeja de entrada');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bandeja de Entrada - DMS2</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/inbox.css">
    <script src="https://unpkg.com/feather-icons"></script>
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
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <div class="nav-content">
                            <i data-feather="home"></i>
                            <span>Dashboard</span>
                        </div>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="#" class="nav-link" onclick="showComingSoon('Subir Documentos')">
                        <div class="nav-content">
                            <i data-feather="upload"></i>
                            <span>Subir Documentos</span>
                        </div>
                    </a>
                </li>
                
                <li class="nav-item active">
                    <a href="inbox.php" class="nav-link">
                        <div class="nav-content">
                            <i data-feather="inbox"></i>
                            <span>Bandeja de Entrada</span>
                        </div>
                        <?php if ($stats['unread'] > 0): ?>
                        <span class="nav-badge"><?php echo $stats['unread']; ?></span>
                        <?php endif; ?>
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
                    <a href="#" class="nav-link" onclick="showComingSoon('Gestión de Documentos')">
                        <div class="nav-content">
                            <i data-feather="file-text"></i>
                            <span>Documentos</span>
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
                <h1>Bandeja de Entrada</h1>
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
        
        <!-- Contenido de la bandeja -->
        <div class="inbox-content">
            <!-- Estadísticas rápidas -->
            <div class="inbox-stats">
                <div class="stat-card">
                    <div class="stat-icon total">
                        <i data-feather="inbox"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon unread">
                        <i data-feather="mail"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['unread']); ?></div>
                        <div class="stat-label">No Leídos</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon important">
                        <i data-feather="star"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['important']); ?></div>
                        <div class="stat-label">Importantes</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon today">
                        <i data-feather="calendar"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-number"><?php echo number_format($stats['today']); ?></div>
                        <div class="stat-label">Hoy</div>
                    </div>
                </div>
            </div>
            
            <!-- Mensaje informativo si no hay datos -->
            <?php if (empty($inboxDocuments) && empty($search) && $status === 'all'): ?>
            <div class="inbox-empty-state">
                <div class="empty-state">
                    <div class="empty-icon">
                        <i data-feather="inbox"></i>
                    </div>
                    <h3>¡Bienvenido a tu Bandeja de Entrada!</h3>
                    <p>Aquí verás los documentos que otros usuarios compartan contigo.</p>
                    <p><em>Por ahora no tienes documentos en tu bandeja. Cuando alguien comparta un documento contigo, aparecerá aquí.</em></p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Filtros y búsqueda (solo si hay datos o filtros activos) -->
            <?php if (!empty($inboxDocuments) || !empty($search) || $status !== 'all' || $priority !== 'all' || $sender !== 'all'): ?>
            <div class="inbox-filters">
                <form method="GET" action="inbox.php" class="filters-form">
                    <div class="search-group">
                        <div class="search-input">
                            <i data-feather="search"></i>
                            <input type="text" name="search" placeholder="Buscar documentos..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i data-feather="search"></i>
                            Buscar
                        </button>
                    </div>
                    
                    <div class="filter-controls">
                        <select name="status" class="form-control">
                            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Todos los estados</option>
                            <option value="unread" <?php echo $status === 'unread' ? 'selected' : ''; ?>>No leídos</option>
                            <option value="read" <?php echo $status === 'read' ? 'selected' : ''; ?>>Leídos</option>
                        </select>
                        
                        <select name="priority" class="form-control">
                            <option value="all" <?php echo $priority === 'all' ? 'selected' : ''; ?>>Todas las prioridades</option>
                            <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>Alta</option>
                            <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>Media</option>
                            <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Baja</option>
                        </select>
                        
                        <select name="sender" class="form-control">
                            <option value="all" <?php echo $sender === 'all' ? 'selected' : ''; ?>>Todos los remitentes</option>
                            <?php foreach ($senders as $senderUser): ?>
                            <option value="<?php echo $senderUser['id']; ?>" 
                                    <?php echo $sender == $senderUser['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($senderUser['first_name'] . ' ' . $senderUser['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="button" class="btn btn-outline" onclick="clearFilters()">
                            <i data-feather="x"></i>
                            Limpiar
                        </button>
                    </div>
                </form>
                
                <?php if (!empty($inboxDocuments)): ?>
                <div class="bulk-actions">
                    <button type="button" class="btn btn-outline" onclick="markAllAsRead()">
                        <i data-feather="check-circle"></i>
                        Marcar todos como leídos
                    </button>
                    <button type="button" class="btn btn-outline" onclick="refreshInbox()">
                        <i data-feather="refresh-cw"></i>
                        Actualizar
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Lista de documentos -->
            <div class="inbox-list">
                <?php if (empty($inboxDocuments) && (!empty($search) || $status !== 'all' || $priority !== 'all' || $sender !== 'all')): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i data-feather="search"></i>
                    </div>
                    <h3>No se encontraron documentos</h3>
                    <p>No hay documentos que coincidan con los filtros seleccionados</p>
                    <button class="btn btn-outline" onclick="clearFilters()">
                        <i data-feather="refresh-cw"></i>
                        Limpiar filtros
                    </button>
                </div>
                <?php elseif (!empty($inboxDocuments)): ?>
                <?php foreach ($inboxDocuments as $record): ?>
                <div class="inbox-item <?php echo $record['read_status'] === 'unread' ? 'unread' : ''; ?>" 
                     data-record-id="<?php echo $record['id']; ?>">
                    
                    <div class="item-checkbox">
                        <input type="checkbox" class="inbox-checkbox" value="<?php echo $record['id']; ?>">
                    </div>
                    
                    <div class="item-priority">
                        <?php if ($record['priority'] === 'high'): ?>
                        <i data-feather="alert-circle" class="priority-high" title="Prioridad alta"></i>
                        <?php elseif ($record['priority'] === 'medium'): ?>
                        <i data-feather="minus-circle" class="priority-medium" title="Prioridad media"></i>
                        <?php else: ?>
                        <i data-feather="circle" class="priority-low" title="Prioridad baja"></i>
                        <?php endif; ?>
                    </div>
                    
                    <div class="item-type">
                        <div class="document-type-icon">
                            <i data-feather="<?php echo $record['type_icon'] ?? 'file-text'; ?>"></i>
                        </div>
                    </div>
                    
                    <div class="item-info">
                        <div class="item-header">
                            <h4 class="document-title"><?php echo htmlspecialchars($record['document_name']); ?></h4>
                            <div class="item-meta">
                                <span class="sender">
                                    De: <?php echo htmlspecialchars($record['sender_first_name'] . ' ' . $record['sender_last_name']); ?>
                                </span>
                                <span class="date">
                                    <?php echo date('d/m/Y H:i', strtotime($record['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if (!empty($record['message'])): ?>
                        <div class="item-message">
                            <?php echo htmlspecialchars($record['message']); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="item-footer">
                            <div class="document-info">
                                <span class="document-type"><?php echo htmlspecialchars($record['document_type'] ?? 'Sin tipo'); ?></span>
                                <span class="file-size"><?php echo formatFileSize($record['file_size']); ?></span>
                                <?php if ($record['company_name']): ?>
                                <span class="company"><?php echo htmlspecialchars($record['company_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="item-actions">
                        <button class="btn-icon" onclick="viewDocument(<?php echo $record['document_id']; ?>)" title="Ver documento">
                            <i data-feather="eye"></i>
                        </button>
                        
                        <button class="btn-icon" onclick="downloadDocument(<?php echo $record['document_id']; ?>)" title="Descargar">
                            <i data-feather="download"></i>
                        </button>
                        
                        <?php if ($record['read_status'] === 'unread'): ?>
                        <button class="btn-icon" onclick="markAsRead(<?php echo $record['id']; ?>)" title="Marcar como leído">
                            <i data-feather="check"></i>
                        </button>
                        <?php else: ?>
                        <button class="btn-icon" onclick="markAsUnread(<?php echo $record['id']; ?>)" title="Marcar como no leído">
                            <i data-feather="mail"></i>
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn-icon danger" onclick="deleteFromInbox(<?php echo $record['id']; ?>)" title="Eliminar de bandeja">
                            <i data-feather="trash-2"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <div class="pagination-info">
                    Mostrando <?php echo ($offset + 1); ?> - <?php echo min($offset + $limit, $totalRecords); ?> 
                    de <?php echo number_format($totalRecords); ?> documentos
                </div>
                
                <div class="pagination-controls">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-outline">
                        <i data-feather="chevron-left"></i>
                        Anterior
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="btn <?php echo $i === $page ? 'btn-primary' : 'btn-outline'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-outline">
                        Siguiente
                        <i data-feather="chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
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
    <script src="assets/js/inbox.js"></script>
    <script>
        // Inicializar Feather icons
        feather.replace();
        
        // Inicializar reloj
        updateTime();
        setInterval(updateTime, 1000);
        
        // Función para limpiar filtros
        function clearFilters() {
            window.location.href = 'inbox.php';
        }
        
        // Función para refrescar
        function refreshInbox() {
            location.reload();
        }
        
        // Función para mostrar "próximamente"
        function showComingSoon(feature) {
            const modal = document.getElementById('comingSoonModal');
            const title = document.getElementById('comingSoonTitle');
            const message = document.getElementById('comingSoonMessage');
            
            title.textContent = feature;
            message.textContent = `La funcionalidad "${feature}" estará disponible próximamente.`;
            
            modal.style.display = 'flex';
        }
        
        // Función para ocultar modal
        function hideComingSoon() {
            document.getElementById('comingSoonModal').style.display = 'none';
        }
        
        // Función para alternar sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            }
        }
        
        // Función para actualizar tiempo
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
        
        // Función para mostrar menú de usuario
        function showUserMenu() {
            showComingSoon('Menú de Usuario');
        }
        
        // Event listeners
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('comingSoonModal');
            if (e.target === modal) {
                hideComingSoon();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideComingSoon();
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
        
        // Funciones de la bandeja (versión básica para evitar errores)
        function markAsRead(recordId) {
            if (confirm('¿Marcar como leído?')) {
                fetch('inbox.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=mark_read&record_id=${recordId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud');
                });
            }
        }
        
        function markAsUnread(recordId) {
            if (confirm('¿Marcar como no leído?')) {
                fetch('inbox.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=mark_unread&record_id=${recordId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud');
                });
            }
        }
        
        function deleteFromInbox(recordId) {
            if (confirm('¿Está seguro que desea eliminar este documento de su bandeja?')) {
                fetch('inbox.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=delete&record_id=${recordId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud');
                });
            }
        }
        
        function markAllAsRead() {
            if (confirm('¿Marcar todos los documentos como leídos?')) {
                fetch('inbox.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=mark_all_read'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud');
                });
            }
        }
        
        function viewDocument(documentId) {
            showComingSoon('Visor de Documentos');
        }
        
        function downloadDocument(documentId) {
            showComingSoon('Descarga de Documentos');
        }
    </script>
</body>
</html>