<?php
// includes/modals.php
// Modales globales reutilizables para todos los módulos - DMS2
?>

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

<!-- Modal de Confirmación -->
<div id="confirmModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="confirmTitle">Confirmar Acción</h3>
            <button class="close" onclick="hideConfirmModal()">
                <i data-feather="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="confirm-content">
                <div class="confirm-icon">
                    <i data-feather="help-circle"></i>
                </div>
                <p id="confirmMessage">¿Está seguro que desea realizar esta acción?</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="hideConfirmModal()">Cancelar</button>
            <button class="btn btn-primary" id="confirmButton">Confirmar</button>
        </div>
    </div>
</div>

<!-- Modal de Carga -->
<div id="loadingModal" class="modal">
    <div class="modal-content loading-modal">
        <div class="modal-body">
            <div class="loading-content">
                <div class="loading-spinner"></div>
                <p id="loadingMessage">Cargando...</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Notificaciones -->
<div id="notificationsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Notificaciones</h3>
            <button class="close" onclick="hideNotificationsModal()">
                <i data-feather="x"></i>
            </button>
        </div>
        <div class="modal-body">
            <div id="notificationsContent">
                <!-- Contenido cargado dinámicamente -->
            </div>
        </div>
    </div>
</div>

<!-- Toast/Notificaciones flotantes -->
<div id="toastContainer" class="toast-container"></div>

<script>
// Funciones globales para modales

// Modal "Próximamente"
function showComingSoon(feature) {
    const modal = document.getElementById('comingSoonModal');
    const title = document.getElementById('comingSoonTitle');
    const message = document.getElementById('comingSoonMessage');
    
    title.textContent = feature;
    message.textContent = `La funcionalidad "${feature}" estará disponible próximamente.`;
    
    showModal('comingSoonModal');
}

function hideComingSoon() {
    hideModal('comingSoonModal');
}

// Modal de confirmación
function showConfirmModal(title, message, onConfirm, onCancel = null) {
    const modal = document.getElementById('confirmModal');
    const titleEl = document.getElementById('confirmTitle');
    const messageEl = document.getElementById('confirmMessage');
    const confirmBtn = document.getElementById('confirmButton');
    
    titleEl.textContent = title;
    messageEl.textContent = message;
    
    // Limpiar eventos anteriores
    confirmBtn.replaceWith(confirmBtn.cloneNode(true));
    const newConfirmBtn = document.getElementById('confirmButton');
    
    newConfirmBtn.addEventListener('click', function() {
        hideConfirmModal();
        if (onConfirm) onConfirm();
    });
    
    showModal('confirmModal');
}

function hideConfirmModal() {
    hideModal('confirmModal');
}

// Modal de carga
function showLoadingModal(message = 'Cargando...') {
    const messageEl = document.getElementById('loadingMessage');
    messageEl.textContent = message;
    showModal('loadingModal');
}

function hideLoadingModal() {
    hideModal('loadingModal');
}

// Funciones generales de modal
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        
        // Animar entrada
        const content = modal.querySelector('.modal-content');
        if (content) {
            content.style.transform = 'scale(0.8)';
            content.style.opacity = '0';
            
            setTimeout(() => {
                content.style.transform = 'scale(1)';
                content.style.opacity = '1';
                content.style.transition = 'all 0.3s ease-out';
            }, 10);
        }
        
        // Enfocar primer elemento focuseable
        setTimeout(() => {
            const firstFocusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            if (firstFocusable) {
                firstFocusable.focus();
            }
        }, 100);
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        const content = modal.querySelector('.modal-content');
        
        if (content) {
            content.style.transform = 'scale(0.8)';
            content.style.opacity = '0';
            
            setTimeout(() => {
                modal.style.display = 'none';
                content.style.transform = '';
                content.style.opacity = '';
                content.style.transition = '';
            }, 300);
        } else {
            modal.style.display = 'none';
        }
    }
}

// Sistema de toast/notificaciones
function showToast(type, message, duration = 4000) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const icons = {
        'success': 'check-circle',
        'error': 'alert-circle',
        'warning': 'alert-triangle',
        'info': 'info'
    };
    
    toast.innerHTML = `
        <div class="toast-content">
            <i data-feather="${icons[type] || 'info'}"></i>
            <span>${message}</span>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i data-feather="x"></i>
        </button>
    `;
    
    container.appendChild(toast);
    feather.replace();
    
    // Animar entrada
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto-remover
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, duration);
}

// Cerrar modales con Escape y clicks fuera
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal[style*="flex"]');
        if (activeModal) {
            const modalId = activeModal.id;
            if (modalId === 'comingSoonModal') {
                hideComingSoon();
            } else if (modalId === 'confirmModal') {
                hideConfirmModal();
            } else if (modalId === 'loadingModal') {
                // No cerrar modal de carga con Escape
            } else {
                hideModal(modalId);
            }
        }
    }
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        const modalId = e.target.id;
        if (modalId === 'loadingModal') {
            // No cerrar modal de carga haciendo click fuera
            return;
        }
        
        if (modalId === 'comingSoonModal') {
            hideComingSoon();
        } else if (modalId === 'confirmModal') {
            hideConfirmModal();
        } else {
            hideModal(modalId);
        }
    }
});
</script>

<style>
/* Estilos para modales globales */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 12px;
    max-width: 500px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.modal-header {
    padding: 24px 24px 16px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
}

.modal-header .close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px;
    border-radius: 6px;
    transition: background-color 0.2s;
}

.modal-header .close:hover {
    background: #f1f5f9;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px 24px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* Modal de próximamente */
.coming-soon-content {
    text-align: center;
}

.coming-soon-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    color: white;
}

.coming-soon-content p {
    margin-bottom: 16px;
    color: var(--text-primary);
    font-size: 1rem;
    line-height: 1.5;
}

.coming-soon-note {
    font-size: 0.875rem;
    color: var(--text-muted);
    font-style: italic;
}

/* Modal de confirmación */
.confirm-content {
    text-align: center;
}

.confirm-icon {
    width: 60px;
    height: 60px;
    background: #fbbf24;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    color: white;
}

/* Modal de carga */
.loading-modal {
    max-width: 300px;
}

.loading-content {
    text-align: center;
    padding: 20px;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f4f6;
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Sistema de toast */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.toast {
    background: white;
    border-radius: 8px;
    padding: 16px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    border-left: 4px solid;
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-width: 300px;
    transform: translateX(100%);
    transition: transform 0.3s ease-out;
}

.toast.show {
    transform: translateX(0);
}

.toast-success { border-left-color: #10b981; }
.toast-error { border-left-color: #ef4444; }
.toast-warning { border-left-color: #f59e0b; }
.toast-info { border-left-color: #3b82f6; }

.toast-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.toast-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    color: #6b7280;
}

.toast-close:hover {
    background: #f3f4f6;
}

/* Responsive */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 10px;
    }
    
    .toast-container {
        left: 20px;
        right: 20px;
    }
    
    .toast {
        min-width: auto;
    }
}
</style>