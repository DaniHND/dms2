<?php
// upload.php
// Módulo de subir documentos - DMS2

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/layout.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

$currentUser = SessionManager::getCurrentUser();
$error = '';
$success = '';

// Obtener tipos de documento
$documentTypes = fetchAll("SELECT * FROM document_types WHERE status = 'active' ORDER BY name");

// Obtener empresas (solo la del usuario si no es admin)
if ($currentUser['role'] === 'admin') {
    $companies = fetchAll("SELECT * FROM companies WHERE status = 'active' ORDER BY name");
} else {
    $companies = fetchAll(
        "SELECT * FROM companies WHERE id = :company_id AND status = 'active'",
        ['company_id' => $currentUser['company_id']]
    );
}

// Obtener departamentos
$departments = [];
if ($currentUser['role'] === 'admin') {
    $departments = fetchAll("SELECT d.*, c.name as company_name FROM departments d 
                            LEFT JOIN companies c ON d.company_id = c.id 
                            WHERE d.status = 'active' ORDER BY c.name, d.name");
} else {
    $departments = fetchAll(
        "SELECT d.*, c.name as company_name FROM departments d 
                            LEFT JOIN companies c ON d.company_id = c.id 
                            WHERE d.company_id = :company_id AND d.status = 'active' 
                            ORDER BY d.name",
        ['company_id' => $currentUser['company_id']]
    );
}

// Procesar subida de archivo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_document'])) {
    $documentName = trim($_POST['document_name'] ?? '');
    $documentTypeId = $_POST['document_type_id'] ?? '';
    $companyId = $_POST['company_id'] ?? '';
    $departmentId = $_POST['department_id'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');

    // Validaciones
    if (empty($documentName)) {
        $error = 'El nombre del documento es requerido';
    } elseif (empty($documentTypeId)) {
        $error = 'Debe seleccionar un tipo de documento';
    } elseif (empty($companyId)) {
        $error = 'Debe seleccionar una empresa';
    } elseif (empty($_FILES['document_file']['name'])) {
        $error = 'Debe seleccionar un archivo';
    } else {
        // Verificar permisos de acceso a la empresa
        if (!SessionManager::canAccessCompany($companyId)) {
            $error = 'No tiene permisos para subir documentos a esta empresa';
        } else {
            // Verificar el archivo
            $file = $_FILES['document_file'];
            $fileName = $file['name'];
            $fileTmpName = $file['tmp_name'];
            $fileSize = $file['size'];
            $fileError = $file['error'];

            if ($fileError !== UPLOAD_ERR_OK) {
                $error = 'Error al subir el archivo';
            } else {
                // Obtener configuración del sistema
                $maxFileSize = getSystemConfig('max_file_size') ?? 20971520; // 20MB por defecto
                $allowedExtensions = json_decode(getSystemConfig('allowed_extensions') ?? '["pdf","doc","docx","xlsx","jpg","jpeg","png","gif"]', true);

                // Validar tamaño
                if ($fileSize > $maxFileSize) {
                    $error = 'El archivo es muy grande. Tamaño máximo: ' . formatBytes($maxFileSize);
                } else {
                    // Validar extensión
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                    if (!in_array($fileExtension, $allowedExtensions)) {
                        $error = 'Tipo de archivo no permitido. Extensiones permitidas: ' . implode(', ', $allowedExtensions);
                    } else {
                        // Crear directorio si no existe
                        $uploadDir = 'uploads/documents/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        // Generar nombre único para el archivo
                        $uniqueFileName = uniqid() . '_' . time() . '.' . $fileExtension;
                        $filePath = $uploadDir . $uniqueFileName;

                        // Mover archivo
                        if (move_uploaded_file($fileTmpName, $filePath)) {
                            // Procesar tags
                            $tagsArray = [];
                            if (!empty($tags)) {
                                $tagsArray = array_map('trim', explode(',', $tags));
                                $tagsArray = array_filter($tagsArray);
                            }

                            // Guardar en base de datos
                            $documentData = [
                                'company_id' => $companyId,
                                'department_id' => $departmentId ?: null,
                                'document_type_id' => $documentTypeId,
                                'user_id' => $currentUser['id'],
                                'name' => $documentName,
                                'original_name' => $fileName,
                                'file_path' => $filePath,
                                'file_size' => $fileSize,
                                'mime_type' => mime_content_type($filePath),
                                'description' => $description,
                                'tags' => json_encode($tagsArray),
                                'status' => 'active'
                            ];

                            if (insertRecord('documents', $documentData)) {
                                $success = 'Documento subido exitosamente';

                                // Log de actividad
                                logActivity(
                                    $currentUser['id'],
                                    'upload',
                                    'documents',
                                    null,
                                    'Usuario subió documento: ' . $documentName
                                );

                                // Limpiar formulario
                                $documentName = '';
                                $description = '';
                                $tags = '';
                            } else {
                                $error = 'Error al guardar el documento en la base de datos';
                                unlink($filePath); // Eliminar archivo si falla la BD
                            }
                        } else {
                            $error = 'Error al mover el archivo al directorio de destino';
                        }
                    }
                }
            }
        }
    }
}

// Función para formatear bytes
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}

// Preparar contenido de la página
ob_start();
?>

<div class="upload-content">
    <div class="upload-container">
        <div class="upload-card">
            <div class="upload-header">
                <h2>Subir Nuevo Documento</h2>
                <p>Seleccione un archivo y complete la información requerida</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i data-feather="alert-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i data-feather="check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="document_name">Nombre del Documento *</label>
                        <input type="text" id="document_name" name="document_name"
                            class="form-control" required
                            value="<?php echo htmlspecialchars($documentName ?? ''); ?>"
                            placeholder="Ej: Factura 001-2024">
                    </div>

                    <div class="form-group">
                        <label for="document_type_id">Tipo de Documento *</label>
                        <select id="document_type_id" name="document_type_id" class="form-control" required>
                            <option value="">Seleccionar tipo</option>
                            <?php foreach ($documentTypes as $type): ?>
                                <option value="<?php echo $type['id']; ?>"
                                    <?php echo (isset($documentTypeId) && $documentTypeId == $type['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="company_id">Empresa *</label>
                        <select id="company_id" name="company_id" class="form-control" required>
                            <option value="">Seleccionar empresa</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>"
                                    <?php echo (isset($companyId) && $companyId == $company['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="department_id">Departamento</label>
                        <select id="department_id" name="department_id" class="form-control">
                            <option value="">Seleccionar departamento</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"
                                    data-company="<?php echo $dept['company_id']; ?>"
                                    <?php echo (isset($departmentId) && $departmentId == $dept['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                    <?php if ($currentUser['role'] === 'admin'): ?>
                                        (<?php echo htmlspecialchars($dept['company_name']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="document_file">Archivo *</label>
                    <div class="file-upload-area" id="fileUploadArea">
                        <input type="file" id="document_file" name="document_file"
                            class="file-input" required accept=".pdf,.doc,.docx,.xlsx,.jpg,.jpeg,.png,.gif">
                        <div class="file-upload-content">
                            <i data-feather="upload-cloud" class="icon-grande"></i>
                            <p style="font-size: 1.4rem; font-weight: 500; text-align: center;">Haz clic aquí para seleccionar un archivo<br><small style="font-size: 1.3rem; font-weight: 500; text-align: center;">o arrastra y suelta un archivo</small></p>
                            <small style="font-size: 0.8rem; font-weight: 500; text-align: center;">Tamaño máximo: <?php echo formatBytes(getSystemConfig('max_file_size') ?? 20971520); ?></small>
                        </div>
                        <div class="file-preview" id="filePreview" style="display: none;">
                            <div class="file-info">
                                <i data-feather="file"></i>
                                <div class="file-details">
                                    <span class="file-name"></span>
                                    <span class="file-size"></span>
                                </div>
                            </div>
                            <button type="button" class="remove-file" onclick="removeFile()">
                                <i data-feather="x"></i>
                            </button>
                        </div>
                    </div>
                    <small class="form-help">Haz clic en cualquier parte del área para cambiar el archivo</small>
                </div>

                <div class="form-group">
                    <label for="description">Descripción</label>
                    <textarea id="description" name="description" class="form-control"
                        rows="3" placeholder="Descripción opcional del documento"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="tags">Etiquetas</label>
                    <input type="text" id="tags" name="tags" class="form-control"
                        value="<?php echo htmlspecialchars($tags ?? ''); ?>"
                        placeholder="Etiquetas separadas por comas (ej: factura, 2024, cliente)">
                    <small class="form-help">Las etiquetas ayudan a organizar y buscar documentos</small>
                </div>

                <div class="form-actions">
                    <button type="submit" name="upload_document" class="btn btn-primary">
                        <i data-feather="upload"></i>
                        Subir Documento
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i data-feather="arrow-left"></i>
                        Volver al Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$uploadContent = ob_get_clean();

// CSS adicional específico para upload
$additionalCSS = ['assets/css/upload_final.css'];

// JavaScript adicional específico para upload
$additionalJS = ['assets/js/upload_final.js'];

// Renderizar la página usando el layout
renderPage('Subir Documentos', $uploadContent, $additionalCSS, $additionalJS, 'upload');
?>