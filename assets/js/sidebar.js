// assets/js/sidebar.js
// JavaScript para el sidebar reutilizable - DMS2

document.addEventListener('DOMContentLoaded', function() {
    initializeSidebar();
});

// Inicializar sidebar
function initializeSidebar() {
    setupSidebarToggle();
    setupResponsiveBehavior();
    setupNavigationHandlers();
    setupKeyboardNavigation();
    highlightActiveLink();
}

// Alternar sidebar (función global requerida por el HTML)
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const mainContent = document.querySelector('.main-content');
    
    if (window.innerWidth <= 768) {
        // Comportamiento móvil
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        
        // Prevenir scroll del body cuando está abierto
        if (sidebar.classList.contains('active')) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    } else {
        // Comportamiento desktop
        sidebar.classList.toggle('collapsed');
        if (mainContent) {
            mainContent.classList.toggle('sidebar-collapsed');
        }
    }
}

// Configurar toggle del sidebar
function setupSidebarToggle() {
    // Botón de toggle móvil
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', toggleSidebar);
    }
    
    // Cerrar con overlay
    const overlay = document.getElementById('sidebarOverlay');
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Cerrar con tecla Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });
}

// Cerrar sidebar (especialmente útil para móvil)
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Configurar comportamiento responsive
function setupResponsiveBehavior() {
    let resizeTimer;
    
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mainContent = document.querySelector('.main-content');
            
            if (window.innerWidth > 768) {
                // Desktop: limpiar clases móviles
                sidebar.classList.remove('active');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                // Mobile: limpiar clases desktop
                sidebar.classList.remove('collapsed');
                if (mainContent) mainContent.classList.remove('sidebar-collapsed');
            }
        }, 250);
    });
}

// Configurar manejadores de navegación
function setupNavigationHandlers() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            // Si es un enlace externo o ancla, manejar normalmente
            if (href.startsWith('http') || href.startsWith('#')) {
                return;
            }
            
            // Añadir estado de carga
            this.classList.add('loading');
            
            // En móvil, cerrar sidebar después de hacer clic
            if (window.innerWidth <= 768) {
                setTimeout(() => {
                    closeSidebar();
                }, 150);
            }
        });
        
        // Manejar hover para efectos adicionales
        link.addEventListener('mouseenter', function() {
            this.style.transform = 'translateX(2px)';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });
}

// Configurar navegación por teclado
function setupKeyboardNavigation() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach((link, index) => {
        link.addEventListener('keydown', function(e) {
            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    const nextLink = navLinks[index + 1];
                    if (nextLink) nextLink.focus();
                    break;
                    
                case 'ArrowUp':
                    e.preventDefault();
                    const prevLink = navLinks[index - 1];
                    if (prevLink) prevLink.focus();
                    break;
                    
                case 'Home':
                    e.preventDefault();
                    navLinks[0].focus();
                    break;
                    
                case 'End':
                    e.preventDefault();
                    navLinks[navLinks.length - 1].focus();
                    break;
            }
        });
    });
}

// Resaltar enlace activo basado en la URL actual
function highlightActiveLink() {
    const currentPage = window.location.pathname.split('/').pop().replace('.php', '');
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        const link = item.querySelector('.nav-link');
        if (link) {
            const href = link.getAttribute('href');
            const linkPage = href.split('/').pop().replace('.php', '');
            
            if (linkPage === currentPage || 
                (currentPage === 'index' && linkPage === 'dashboard') ||
                (currentPage === '' && linkPage === 'dashboard')) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        }
    });
}

// Funciones de utilidad para módulos específicos
const SidebarUtils = {
    // Mostrar indicador de carga en un enlace específico
    showLinkLoading: function(linkSelector) {
        const link = document.querySelector(linkSelector);
        if (link) {
            link.classList.add('loading');
        }
    },
    
    // Ocultar indicador de carga
    hideLinkLoading: function(linkSelector) {
        const link = document.querySelector(linkSelector);
        if (link) {
            link.classList.remove('loading');
        }
    },
    
    // Actualizar contador en un enlace (ej: notificaciones)
    updateLinkBadge: function(linkSelector, count) {
        const link = document.querySelector(linkSelector);
        if (link) {
            let badge = link.querySelector('.nav-badge');
            
            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'nav-badge';
                    link.appendChild(badge);
                }
                badge.textContent = count > 99 ? '99+' : count.toString();
                badge.style.display = 'inline-block';
            } else if (badge) {
                badge.style.display = 'none';
            }
        }
    },
    
    // Marcar enlace como activo programáticamente
    setActiveLink: function(linkSelector) {
        // Remover activo de todos
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Activar el seleccionado
        const link = document.querySelector(linkSelector);
        if (link) {
            const navItem = link.closest('.nav-item');
            if (navItem) {
                navItem.classList.add('active');
            }
        }
    },
    
    // Verificar si el sidebar está abierto (útil para móvil)
    isOpen: function() {
        const sidebar = document.getElementById('sidebar');
        return sidebar && sidebar.classList.contains('active');
    },
    
    // Abrir sidebar programáticamente
    open: function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar && window.innerWidth <= 768) {
            sidebar.classList.add('active');
            if (overlay) overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },
    
    // Cerrar sidebar programáticamente
    close: function() {
        closeSidebar();
    }
};

// Exponer utilidades globalmente
window.SidebarUtils = SidebarUtils;

// Manejo de estados de conexión
window.addEventListener('online', function() {
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('disabled');
        link.removeAttribute('title');
    });
});

window.addEventListener('offline', function() {
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.add('disabled');
        link.setAttribute('title', 'Sin conexión a internet');
    });
});

// Atajos de teclado para el sidebar
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + B para toggle sidebar
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        toggleSidebar();
    }
    
    // Alt + números para navegación rápida
    if (e.altKey && e.key >= '1' && e.key <= '9') {
        e.preventDefault();
        const index = parseInt(e.key) - 1;
        const navLinks = document.querySelectorAll('.nav-link');
        if (navLinks[index]) {
            navLinks[index].click();
        }
    }
});

// Inicializar tooltips personalizados (opcional)
function initSidebarTooltips() {
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function(e) {
            if (window.innerWidth > 768) return; // Solo en móvil
            
            const text = this.querySelector('span').textContent;
            const tooltip = createTooltip(text);
            
            document.body.appendChild(tooltip);
            positionTooltip(tooltip, this);
            
            this._tooltip = tooltip;
        });
        
        link.addEventListener('mouseleave', function() {
            if (this._tooltip) {
                this._tooltip.remove();
                delete this._tooltip;
            }
        });
    });
}

function createTooltip(text) {
    const tooltip = document.createElement('div');
    tooltip.className = 'sidebar-tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: absolute;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        pointer-events: none;
        z-index: 10000;
        opacity: 0;
        transition: opacity 0.2s;
    `;
    
    setTimeout(() => tooltip.style.opacity = '1', 10);
    return tooltip;
}

function positionTooltip(tooltip, element) {
    const rect = element.getBoundingClientRect();
    tooltip.style.left = rect.right + 10 + 'px';
    tooltip.style.top = rect.top + (rect.height / 2) - (tooltip.offsetHeight / 2) + 'px';
}

// Auto-inicializar tooltips si se desea
// initSidebarTooltips();