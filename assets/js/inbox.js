// assets/js/inbox.js
// JavaScript para el módulo de Bandeja de Entrada - DMS2

document.addEventListener('DOMContentLoaded', function() {
    initializeInbox();
});

// Inicializar funcionalidades de la bandeja
function initializeInbox() {
    setupCheckboxHandlers();
    setupKeyboardShortcuts();
    setupAutoRefresh();
    setupInfiniteScroll();
    setupDragAndDrop();
    animateItems();
}

// ===================
// GESTIÓN DE CHECKBOXES
// ===================
function setupCheckboxHandlers() {
    const checkboxes = document.querySelectorAll('.inbox-checkbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    
    // Manejar selección individual
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBulkActionsVisibility();
            updateSelectAllState();
        });
    });
    
    // Manejar selección de todos
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', toggleSelectAll);
    }
    
    // Selección con Shift
    let lastChecked = null;
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('click', function(e) {
            if (e.shiftKey && lastChecked) {
                selectRange(lastChecked, this);
            }
            lastChecked = this;
        });
    });
}

// Seleccionar rango de checkboxes
function selectRange(start, end) {
    const checkboxes = Array.from(document.querySelectorAll('.inbox-checkbox'));
    const startIndex = checkboxes.indexOf(start);
    const endIndex = checkboxes.indexOf(end);
    
    const min = Math.min(startIndex, endIndex);
    const max = Math.max(startIndex, endIndex);
    
    for (let i = min; i <= max; i++) {
        checkboxes[i].checked = end.checked;
    }
    
    updateBulkActionsVisibility();
    updateSelectAllState();
}

// Alternar selección de todos
function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.inbox-checkbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
    });
    
    updateBulkActionsVisibility();
    updateSelectAllState();
}

// Actualizar estado del botón "Seleccionar todo"
function updateSelectAllState() {
    const checkboxes = document.querySelectorAll('.inbox-checkbox');
    const selectAllBtn = document.getElementById('selectAllBtn');
    
    if (!selectAllBtn) return;
    
    const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
    const totalCount = checkboxes.length;
    
    if (checkedCount === 0) {
        selectAllBtn.textContent = 'Seleccionar todo';
        selectAllBtn.classList.remove('active');
    } else if (checkedCount === totalCount) {
        selectAllBtn.textContent = 'Deseleccionar todo';
        selectAllBtn.classList.add('active');
    } else {
        selectAllBtn.textContent = `Seleccionar todo (${checkedCount}/${totalCount})`;
        selectAllBtn.classList.add('partial');
    }
}

// Mostrar/ocultar acciones bulk
function updateBulkActionsVisibility() {
    const checkedBoxes = document.querySelectorAll('.inbox-checkbox:checked');
    const bulkActions = document.querySelector('.bulk-actions-selected');
    
    if (bulkActions) {
        if (checkedBoxes.length > 0) {
            bulkActions.style.display = 'flex';
            bulkActions.querySelector('.selected-count').textContent = checkedBoxes.length;
        } else {
            bulkActions.style.display = 'none';
        }
    }
}

// ===================
// ACCIONES DE DOCUMENTOS
// ===================

// Marcar como leído
async function markAsRead(recordId) {
    try {
        showLoadingIndicator();
        
        const response = await fetch('inbox.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_read&record_id=${recordId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            const item = document.querySelector(`[data-record-id="${recordId}"]`);
            if (item) {
                item.classList.remove('unread');
                updateItemActions(item, 'read');
            }
            showNotification('success', result.message);
            updateStats();
        } else {
            showNotification('error', result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('error', 'Error al marcar como leído');
    } finally {
        hideLoadingIndicator();
    }
}

// Marcar como no leído
async function markAsUnread(recordId) {
    try {
        showLoadingIndicator();
        
        const response = await fetch('inbox.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=mark_unread&record_id=${recordId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            const item = document.querySelector(`[data-record-id="${recordId}"]`);
            if (item) {
                item.classList.add('unread');
                updateItemActions(item, 'unread');
            }
            showNotification('success', result.message);
            updateStats();
        } else {
            showNotification('error', result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('error', 'Error al marcar como no leído');
    } finally {
        hideLoadingIndicator();
    }
}

// Eliminar de la bandeja
async function deleteFromInbox(recordId) {
    if (!confirm('¿Está seguro que desea eliminar este documento de su bandeja de entrada?')) {
        return;
    }
    
    try {
        showLoadingIndicator();
        
        const response = await fetch('inbox.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&record_id=${recordId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            const item = document.querySelector(`[data-record-id="${recordId}"]`);
            if (item) {
                item.style.animation = 'slideOutRight 0.3s ease-in-out';
                setTimeout(() => {
                    item.remove();
                    checkEmptyState();
                }, 300);
            }
            showNotification('success', result.message);
            updateStats();
        } else {
            showNotification('error', result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('error', 'Error al eliminar documento');
    } finally {
        hideLoadingIndicator();
    }
}

// Marcar todos como leídos
async function markAllAsRead() {
    if (!confirm('¿Está seguro que desea marcar todos los documentos como leídos?')) {
        return;
    }
    
    try {
        showLoadingIndicator();
        
        const response = await fetch('inbox.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=mark_all_read'
        });
        
        const result = await response.json();
        
        if (result.success) {
            const unreadItems = document.querySelectorAll('.inbox-item.unread');
            unreadItems.forEach(item => {
                item.classList.remove('unread');
                updateItemActions(item, 'read');
            });
            showNotification('success', result.message);
            updateStats();
        } else {
            showNotification('error', result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('error', 'Error al marcar todos como leídos');
    } finally {
        hideLoadingIndicator();
    }
}

// Actualizar acciones de un item
function updateItemActions(item, status) {
    const actionsContainer = item.querySelector('.item-actions');
    const readBtn = actionsContainer.querySelector('[onclick*="markAsRead"]');
    const unreadBtn = actionsContainer.querySelector('[onclick*="markAsUnread"]');
    
    if (status === 'read') {
        if (readBtn) {
            readBtn.style.display = 'none';
        }
        if (unreadBtn) {
            unreadBtn.style.display = 'flex';
        }
    } else {
        if (readBtn) {
            readBtn.style.display = 'flex';
        }
        if (unreadBtn) {
            unreadBtn.style.display = 'none';
        }
    }
}

// Ver documento
function viewDocument(documentId) {
    showComingSoon('Visor de Documentos');
    // En producción esto abriría el visor de documentos
    // window.open(`document_viewer.php?id=${documentId}`, '_blank');
}

// Descargar documento
function downloadDocument(documentId) {
    showComingSoon('Descarga de Documentos');
    // En producción esto iniciaría la descarga
    // window.location.href = `download.php?id=${documentId}`;
}

// ===================
// FILTROS Y BÚSQUEDA
// ===================

// Limpiar filtros
function clearFilters() {
    const form = document.querySelector('.filters-form');
    const inputs = form.querySelectorAll('input, select');
    
    inputs.forEach(input => {
        if (input.type === 'text') {
            input.value = '';
        } else if (input.tagName === 'SELECT') {
            input.value = 'all';
        }
    });
    
    form.submit();
}

// Búsqueda en tiempo real (debounced)
function setupLiveSearch() {
    const searchInput = document.querySelector('.search-input input');
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length >= 2 || query.length === 0) {
            searchTimeout = setTimeout(() => {
                performLiveSearch(query);
            }, 500);
        }
    });
}

// Realizar búsqueda en vivo
async function performLiveSearch(query) {
    try {
        showLoadingIndicator();
        
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('search', query);
        currentUrl.searchParams.set('page', '1');
        
        const response = await fetch(currentUrl.toString());
        const html = await response.text();
        
        // Extraer solo la lista de documentos
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newList = doc.querySelector('.inbox-list');
        const newStats = doc.querySelector('.inbox-stats');
        
        if (newList) {
            document.querySelector('.inbox-list').innerHTML = newList.innerHTML;
            animateItems();
        }
        
        if (newStats) {
            document.querySelector('.inbox-stats').innerHTML = newStats.innerHTML;
        }
        
        feather.replace();
        
    } catch (error) {
        console.error('Error en búsqueda:', error);
        showNotification('error', 'Error al buscar documentos');
    } finally {
        hideLoadingIndicator();
    }
}

// ===================
// ACTUALIZACIÓN AUTOMÁTICA
// ===================
function setupAutoRefresh() {
    // Actualizar cada 2 minutos
    setInterval(() => {
        updateStats();
        checkForNewDocuments();
    }, 2 * 60 * 1000);
}

// Verificar nuevos documentos
async function checkForNewDocuments() {
    try {
        const response = await fetch('inbox.php?action=check_new', {
            method: 'GET',
        });
        
        const result = await response.json();
        
        if (result.newCount > 0) {
            showNotification('info', `Tienes ${result.newCount} nuevo(s) documento(s)`);
            
            // Mostrar botón para refrescar
            showRefreshPrompt();
        }
        
    } catch (error) {
        console.error('Error al verificar nuevos documentos:', error);
    }
}

// Mostrar prompt para refrescar
function showRefreshPrompt() {
    const existingPrompt = document.getElementById('refreshPrompt');
    if (existingPrompt) return;
    
    const prompt = document.createElement('div');
    prompt.id = 'refreshPrompt';
    prompt.className = 'refresh-prompt';
    prompt.innerHTML = `
        <div class="refresh-content">
            <i data-feather="refresh-cw"></i>
            <span>Hay nuevos documentos disponibles</span>
            <button onclick="refreshInbox()" class="btn btn-primary btn-sm">
                Actualizar
            </button>
            <button onclick="dismissRefreshPrompt()" class="btn-icon">
                <i data-feather="x"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(prompt);
    feather.replace();
    
    // Auto-hide después de 10 segundos
    setTimeout(() => {
        dismissRefreshPrompt();
    }, 10000);
}

// Descartar prompt de actualización
function dismissRefreshPrompt() {
    const prompt = document.getElementById('refreshPrompt');
    if (prompt) {
        prompt.remove();
    }
}

// Refrescar bandeja
function refreshInbox() {
    dismissRefreshPrompt();
    location.reload();
}

// ===================
// ATAJOS DE TECLADO
// ===================
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Solo si no estamos en un input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        switch (e.key) {
            case 'r':
                e.preventDefault();
                refreshInbox();
                break;
                
            case 'a':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    toggleSelectAll();
                }
                break;
                
            case 's':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    document.querySelector('.search-input input')?.focus();
                }
                break;
                
            case 'Escape':
                clearSelection();
                break;
                
            case 'Delete':
                e.preventDefault();
                deleteSelectedItems();
                break;
        }
    });
    
    // Navegación con flechas
    let currentItem = 0;
    const items = document.querySelectorAll('.inbox-item');
    
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                navigateItems(1);
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                navigateItems(-1);
                break;
                
            case 'Enter':
                e.preventDefault();
                const activeItem = document.querySelector('.inbox-item.keyboard-active');
                if (activeItem) {
                    const checkbox = activeItem.querySelector('.inbox-checkbox');
                    checkbox.checked = !checkbox.checked;
                    updateBulkActionsVisibility();
                }
                break;
        }
    });
}

// Navegar entre items con teclado
function navigateItems(direction) {
    const items = document.querySelectorAll('.inbox-item');
    if (items.length === 0) return;
    
    // Remover clase activa actual
    document.querySelectorAll('.inbox-item.keyboard-active').forEach(item => {
        item.classList.remove('keyboard-active');
    });
    
    // Calcular nuevo índice
    let currentIndex = 0;
    const activeItem = document.querySelector('.inbox-item.keyboard-active');
    if (activeItem) {
        currentIndex = Array.from(items).indexOf(activeItem);
    }
    
    currentIndex += direction;
    currentIndex = Math.max(0, Math.min(items.length - 1, currentIndex));
    
    // Activar nuevo item
    const newActiveItem = items[currentIndex];
    newActiveItem.classList.add('keyboard-active');
    
    // Scroll into view
    newActiveItem.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
    });
}

// Limpiar selección
function clearSelection() {
    document.querySelectorAll('.inbox-checkbox:checked').forEach(checkbox => {
        checkbox.checked = false;
    });
    updateBulkActionsVisibility();
    updateSelectAllState();
}

// Eliminar items seleccionados
async function deleteSelectedItems() {
    const selectedIds = Array.from(document.querySelectorAll('.inbox-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selectedIds.length === 0) return;
    
    if (!confirm(`¿Está seguro que desea eliminar ${selectedIds.length} documento(s) de su bandeja?`)) {
        return;
    }
    
    for (const id of selectedIds) {
        await deleteFromInbox(id);
    }
}

// ===================
// SCROLL INFINITO
// ===================
function setupInfiniteScroll() {
    let loading = false;
    let page = 1;
    
    window.addEventListener('scroll', function() {
        if (loading) return;
        
        const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
        
        if (scrollTop + clientHeight >= scrollHeight - 1000) {
            loadMoreItems();
        }
    });
    
    async function loadMoreItems() {
        if (loading) return;
        
        loading = true;
        page++;
        
        try {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('page', page);
            
            const response = await fetch(currentUrl.toString());
            const html = await response.text();
            
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newItems = doc.querySelectorAll('.inbox-item');
            
            if (newItems.length > 0) {
                const inboxList = document.querySelector('.inbox-list');
                newItems.forEach(item => {
                    inboxList.appendChild(item);
                });
                
                animateItems();
                feather.replace();
            } else {
                // No hay más items
                window.removeEventListener('scroll', arguments.callee);
            }
            
        } catch (error) {
            console.error('Error cargando más items:', error);
            page--; // Revertir página en caso de error
        } finally {
            loading = false;
        }
    }
}

// ===================
// DRAG AND DROP
// ===================
function setupDragAndDrop() {
    const inboxItems = document.querySelectorAll('.inbox-item');
    
    inboxItems.forEach(item => {
        item.draggable = true;
        
        item.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', this.dataset.recordId);
            this.classList.add('dragging');
        });
        
        item.addEventListener('dragend', function(e) {
            this.classList.remove('dragging');
        });
    });
    
    // Drop zones (para futuras funcionalidades como carpetas)
    const dropZones = document.querySelectorAll('.drop-zone');
    
    dropZones.forEach(zone => {
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        
        zone.addEventListener('dragleave', function(e) {
            this.classList.remove('drag-over');
        });
        
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            const recordId = e.dataTransfer.getData('text/plain');
            handleDrop(recordId, this.dataset.action);
        });
    });
}

// Manejar drop
function handleDrop(recordId, action) {
    switch (action) {
        case 'delete':
            deleteFromInbox(recordId);
            break;
        case 'mark-read':
            markAsRead(recordId);
            break;
        case 'mark-unread':
            markAsUnread(recordId);
            break;
        default:
            showComingSoon('Organización de documentos');
    }
}

// ===================
// UTILIDADES
// ===================

// Actualizar estadísticas
async function updateStats() {
    try {
        const response = await fetch('inbox.php?action=get_stats');
        const stats = await response.json();
        
        if (stats.success) {
            document.querySelector('.stat-card .stat-number').textContent = stats.total;
            // Actualizar otros stats...
        }
    } catch (error) {
        console.error('Error actualizando stats:', error);
    }
}

// Verificar estado vacío
function checkEmptyState() {
    const items = document.querySelectorAll('.inbox-item');
    const inboxList = document.querySelector('.inbox-list');
    
    if (items.length === 0) {
        inboxList.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">
                    <i data-feather="inbox"></i>
                </div>
                <h3>Bandeja vacía</h3>
                <p>No hay documentos en su bandeja de entrada</p>
            </div>
        `;
        feather.replace();
    }
}

// Animar items al cargar
function animateItems() {
    const items = document.querySelectorAll('.inbox-item');
    items.forEach((item, index) => {
        item.style.animationDelay = `${index * 0.05}s`;
    });
}

// Mostrar indicador de carga
function showLoadingIndicator() {
    const existing = document.getElementById('loadingIndicator');
    if (existing) return;
    
    const indicator = document.createElement('div');
    indicator.id = 'loadingIndicator';
    indicator.className = 'loading-overlay';
    indicator.innerHTML = `
        <div class="loading-spinner"></div>
    `;
    
    document.body.appendChild(indicator);
}

// Ocultar indicador de carga
function hideLoadingIndicator() {
    const indicator = document.getElementById('loadingIndicator');
    if (indicator) {
        indicator.remove();
    }
}

// Mostrar notificación
function showNotification(type, message, duration = 4000) {
    const notification = document.createElement('div');
    notification.className = `notification-toast ${type}`;
    
    const icons = {
        success: 'check-circle',
        error: 'alert-circle',
        warning: 'alert-triangle',
        info: 'info'
    };
    
    notification.innerHTML = `
        <i data-feather="${icons[type] || 'info'}"></i>
        <span>${message}</span>
        <button onclick="this.parentElement.remove()">
            <i data-feather="x"></i>
        </button>
    `;
    
    document.body.appendChild(notification);
    feather.replace();
    
    // Animar entrada
    setTimeout(() => notification.classList.add('visible'), 10);
    
    // Auto-remover
    setTimeout(() => {
        notification.classList.remove('visible');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// Funciones de utilidad para ordenamiento
function sortInboxBy(criteria) {
    const items = Array.from(document.querySelectorAll('.inbox-item'));
    const container = document.querySelector('.inbox-list');
    
    items.sort((a, b) => {
        switch (criteria) {
            case 'date-new':
                return new Date(b.dataset.date) - new Date(a.dataset.date);
            case 'date-old':
                return new Date(a.dataset.date) - new Date(b.dataset.date);
            case 'name':
                return a.querySelector('.document-title').textContent.localeCompare(
                    b.querySelector('.document-title').textContent
                );
            case 'sender':
                return a.querySelector('.sender').textContent.localeCompare(
                    b.querySelector('.sender').textContent
                );
            case 'priority':
                const priorities = { 'high': 3, 'medium': 2, 'low': 1 };
                return priorities[b.dataset.priority] - priorities[a.dataset.priority];
            default:
                return 0;
        }
    });
    
    // Reordenar elementos
    items.forEach(item => container.appendChild(item));
    animateItems();
}

// Exportar funciones para uso global
window.inboxFunctions = {
    markAsRead,
    markAsUnread,
    deleteFromInbox,
    markAllAsRead,
    viewDocument,
    downloadDocument,
    clearFilters,
    refreshInbox,
    sortInboxBy
};

// Estilos adicionales para funcionalidades dinámicas
const additionalStyles = `
    .keyboard-active {
        outline: 2px solid var(--primary-color) !important;
        outline-offset: 2px;
    }
    
    .dragging {
        opacity: 0.5;
        transform: rotate(5deg);
    }
    
    .drag-over {
        background: var(--primary-color) !important;
        color: white !important;
    }
    
    .refresh-prompt {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 8px;
        padding: 16px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        border: 1px solid #e5e7eb;
        z-index: 1000;
        animation: slideInRight 0.3s ease-out;
    }
    
    .refresh-content {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 14px;
        color: #374151;
    }
    
    .notification-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 8px;
        padding: 12px 16px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        border-left: 4px solid var(--primary-color);
        z-index: 1001;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        transform: translateX(100%);
        transition: transform 0.3s ease-out;
        min-width: 300px;
    }
    
    .notification-toast.visible {
        transform: translateX(0);
    }
    
    .notification-toast.success {
        border-left-color: #10b981;
        color: #065f46;
    }
    
    .notification-toast.error {
        border-left-color: #ef4444;
        color: #991b1b;
    }
    
    .notification-toast.warning {
        border-left-color: #f59e0b;
        color: #92400e;
    }
    
    .notification-toast.info {
        border-left-color: #3b82f6;
        color: #1e40af;
    }
    
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;

// Inyectar estilos
const styleElement = document.createElement('style');
styleElement.textContent = additionalStyles;
document.head.appendChild(styleElement);