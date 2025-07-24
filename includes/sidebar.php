<?php
// includes/sidebar.php
// Sidebar reutilizable para todos los módulos - DMS2

// Obtener el usuario actual y página activa
$currentUser = SessionManager::getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Definir elementos del menú con permisos
$menuItems = [
    'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'home',
        'url' => 'dashboard.php',
        'permission' => null // Todos pueden acceder
    ],
    'upload' => [
        'title' => 'Subir Documentos',
        'icon' => 'upload',
        'url' => 'upload.php',
        'permission' => 'upload_documents'
    ],
    'inbox' => [
        'title' => 'Bandeja de Entrada',
        'icon' => 'inbox',
        'url' => 'inbox.php',
        'permission' => 'view_documents'
    ],
    'search' => [
        'title' => 'Búsqueda',
        'icon' => 'search',
        'url' => 'search.php',
        'permission' => 'search_documents'
    ],
    'reports' => [
        'title' => 'Reportes',
        'icon' => 'bar-chart-2',
        'url' => 'reports.php',
        'permission' => 'view_reports'
    ]
];

// Elementos administrativos
$adminItems = [
    'users' => [
        'title' => 'Usuarios',
        'icon' => 'users',
        'url' => 'admin/users.php',
        'permission' => 'manage_users'
    ],
    'companies' => [
        'title' => 'Empresas',
        'icon' => 'briefcase',
        'url' => 'admin/companies.php',
        'permission' => 'manage_companies'
    ],
    'departments' => [
        'title' => 'Departamentos',
        'icon' => 'layers',
        'url' => 'admin/departments.php',
        'permission' => 'manage_departments'
    ],
    'groups' => [
        'title' => 'Grupos de Seguridad',
        'icon' => 'shield',
        'url' => 'admin/groups.php',
        'permission' => 'manage_groups'
    ],
    'documents' => [
        'title' => 'Gestión de Documentos',
        'icon' => 'file-text',
        'url' => 'admin/documents.php',
        'permission' => 'manage_all_documents'
    ]
];

// Función para verificar si el usuario puede acceder a una opción
function canAccessMenuItem($permission) {
    global $currentUser;
    
    if (!$permission) return true; // Sin restricción
    
    if ($currentUser['role'] === 'admin') return true; // Admin accede a todo
    
    return checkPermission($permission);
}

// Función para verificar si la página está activa
function isActivePage($url) {
    global $currentPage;
    $pageFromUrl = basename($url, '.php');
    return $currentPage === $pageFromUrl;
}
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="https://perdomoyasociados.com/wp-content/uploads/2023/09/logo_perdomo_2023_dorado-768x150.png" 
                 alt="Perdomo y Asociados" class="logo-image">
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php foreach ($menuItems as $key => $item): ?>
                <?php if (canAccessMenuItem($item['permission'])): ?>
                <li class="nav-item <?php echo isActivePage($item['url']) ? 'active' : ''; ?>">
                    <a href="<?php echo $item['url']; ?>" class="nav-link">
                        <i data-feather="<?php echo $item['icon']; ?>"></i>
                        <span><?php echo $item['title']; ?></span>
                    </a>
                </li>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <li class="nav-divider"></li>
            
            <?php if ($currentUser['role'] === 'admin' || array_filter($adminItems, 'canAccessMenuItem')): ?>
            <li class="nav-section">
                <span>ADMINISTRACIÓN</span>
            </li>
            
            <?php foreach ($adminItems as $key => $item): ?>
                <?php if (canAccessMenuItem($item['permission'])): ?>
                <li class="nav-item <?php echo isActivePage($item['url']) ? 'active' : ''; ?>">
                    <a href="<?php echo $item['url']; ?>" class="nav-link">
                        <i data-feather="<?php echo $item['icon']; ?>"></i>
                        <span><?php echo $item['title']; ?></span>
                    </a>
                </li>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($currentUser['first_name'] ?: $currentUser['username'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars(getFullName()); ?></div>
                <div class="user-role"><?php echo ucfirst($currentUser['role']); ?></div>
            </div>
        </div>
    </div>
</aside>

<!-- Overlay para móvil -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>