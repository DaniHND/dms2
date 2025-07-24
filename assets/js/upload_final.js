// assets/js/upload_final.js
// JavaScript final para el módulo de upload - DMS2

// Variables globales para el manejo de archivos
let currentFile = null;

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    initializeUpload();
});

function initializeUpload() {
    setupFileHandling();
    setupDepartmentFilter();
    setupFormValidation();
}

// Configurar manejo de archivos
function setupFileHandling() {
    const fileInput = document.getElementById('document_file');
    const fileUploadArea = document.getElementById('fileUploadArea');

    if (!fileInput || !fileUploadArea) return;

    // Manejar click en área de upload
    fileUploadArea.addEventListener('click', function(e) {
        // Evitar abrir selector solo si se hace clic en el botón de remover
        if (e.target.closest('.remove-file')) {
            e.stopPropagation();
            return;
        }

        // Si hay un preview visible, solo abrir selector si se hace clic fuera del preview
        const filePreview = document.getElementById('filePreview');
        if (filePreview && filePreview.style.display === 'flex') {
            if (e.target.closest('.file-preview')) {
                return; // No hacer nada si se hace clic en el preview
            }
        }

        // Abrir selector de archivos
        fileInput.click();
    });

    // Manejar cambio de archivo
    fileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            showFilePreview(e.target.files[0]);
        }
    });

    // Drag and drop
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        fileUploadArea.classList.add('drag-over');
    });

    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        if (!fileUploadArea.contains(e.relatedTarget)) {
            fileUploadArea.classList.remove('drag-over');
        }
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        fileUploadArea.classList.remove('drag-over');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            showFilePreview(files[0]);
        }
    });
}

// Mostrar preview del archivo
function showFilePreview(file) {
    const fileName = file.name;
    const fileSize = formatBytes(file.size);

    const filePreview = document.getElementById('filePreview');
    const fileUploadContent = document.querySelector('.file-upload-content');

    if (filePreview && fileUploadContent) {
        filePreview.querySelector('.file-name').textContent = fileName;
        filePreview.querySelector('.file-size').textContent = fileSize;

        fileUploadContent.style.display = 'none';
        filePreview.style.display = 'flex';

        currentFile = file;

        // Auto-llenar el nombre del documento si está vacío
        const docNameInput = document.getElementById('document_name');
        if (docNameInput && !docNameInput.value.trim()) {
            const nameWithoutExt = fileName.substring(0, fileName.lastIndexOf('.'));
            docNameInput.value = nameWithoutExt;
        }
    }
}

// Remover archivo
function removeFile() {
    const fileInput = document.getElementById('document_file');
    const filePreview = document.getElementById('filePreview');
    const fileUploadContent = document.querySelector('.file-upload-content');

    if (fileInput) fileInput.value = '';
    if (filePreview) filePreview.style.display = 'none';
    if (fileUploadContent) fileUploadContent.style.display = 'block';

    currentFile = null;
}

// Formatear bytes
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Configurar filtro de departamentos
function setupDepartmentFilter() {
    const companySelect = document.getElementById('company_id');
    const departmentSelect = document.getElementById('department_id');

    if (!companySelect || !departmentSelect) return;

    companySelect.addEventListener('change', function() {
        const companyId = this.value;
        const options = departmentSelect.querySelectorAll('option');

        options.forEach(option => {
            if (option.value === '') {
                option.style.display = 'block';
            } else {
                const optionCompany = option.getAttribute('data-company');
                option.style.display = (optionCompany === companyId) ? 'block' : 'none';
            }
        });

        // Resetear selección de departamento
        departmentSelect.value = '';
    });
}

// Configurar validación del formulario
function setupFormValidation() {
    const form = document.querySelector('.upload-form');
    if (!form) return;

    // Validación en tiempo real para campos requeridos
    const requiredFields = form.querySelectorAll('input[required], select[required]');
    requiredFields.forEach(field => {
        field.addEventListener('blur', validateField);
        field.addEventListener('change', validateField);
    });

    // Validación al enviar
    form.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            showAlert('error', 'Por favor completa todos los campos requeridos');
        }
    });
}

// Validar campo individual
function validateField(e) {
    const field = e.target;
    
    if (field.hasAttribute('required') && !field.value.trim()) {
        field.classList.add('error');
        field.classList.remove('success');
    } else {
        field.classList.remove('error');
        field.classList.add('success');
        
        // Remover clase success después de un tiempo
        setTimeout(() => {
            field.classList.remove('success');
        }, 2000);
    }
}

// Validar formulario completo
function validateForm() {
    let isValid = true;
    
    // Validar campos requeridos
    const requiredFields = document.querySelectorAll('input[required], select[required]');
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    // Validar que hay archivo seleccionado
    const fileInput = document.getElementById('document_file');
    if (fileInput && !fileInput.files.length) {
        showAlert('error', 'Por favor selecciona un archivo');
        isValid = false;
    }
    
    return isValid;
}

// Mostrar alerta
function showAlert(type, message) {
    // Remover alertas existentes
    const existingAlert = document.querySelector('.alert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <i data-feather="${type === 'error' ? 'alert-circle' : 'check-circle'}"></i>
        ${message}
    `;
    
    const form = document.querySelector('.upload-form');
    form.insertBefore(alert, form.firstChild);
    feather.replace();
    
    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (alert.parentNode) {
            alert.remove();
        }
    }, 5000);
}

// Funciones de utilidad
const UploadUtils = {
    // Limpiar formulario
    clearForm: function() {
        const form = document.querySelector('.upload-form');
        if (form) {
            form.reset();
            removeFile();
            
            // Remover clases de validación
            const fields = form.querySelectorAll('.form-control');
            fields.forEach(field => {
                field.classList.remove('error', 'success');
            });
        }
    },
    
    // Validar extensión de archivo
    isValidExtension: function(filename) {
        const validExtensions = ['pdf', 'doc', 'docx', 'xlsx', 'xls', 'jpg', 'jpeg', 'png', 'gif'];
        const extension = filename.split('.').pop().toLowerCase();
        return validExtensions.includes(extension);
    },
    
    // Obtener información del archivo actual
    getCurrentFile: function() {
        return currentFile;
    }
};

// Exponer utilidades globalmente
window.UploadUtils = UploadUtils;

console.log('✅ Módulo de Upload Final inicializado correctamente');