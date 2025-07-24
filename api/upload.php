<?php
// api/upload.php
// API para procesar subida de documentos - DMS2

require_once '../config/session.php';
require_once '../config/database.php';

// Verificar que el usuario esté logueado
SessionManager::requireLogin();

// Configurar respuesta JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    $currentUser = SessionManager::getCurrentUser();
    
    // Verificar permisos
    if (!checkPermission('upload_documents') && $currentUser['role'] !== 'admin') {
        throw new Exception('No tiene permisos para subir documentos');
    }
    
    // Verificar que se haya enviado un archivo
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió el archivo o hubo un error en la subida');
    }
    
    $file = $_FILES['file'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    
    // Validaciones básicas
    if (empty($title)) {
        throw new Exception('El título es obligatorio');
    }
    
    if (strlen($title) < 3) {
        throw new Exception('El título debe tener al menos 3 caracteres');
    }
    
    if (strlen($title) > 255) {
        throw new Exception('El título no puede exceder 255 caracteres');
    }
    
    // Validar archivo
    $uploadLimits = getUploadLimits($currentUser['id'], $currentUser['role'], $currentUser['company_id']);
    validateUploadedFile($file, $uploadLimits);
    
    // Verificar límites diarios
    checkDailyLimits($currentUser['id'], $currentUser['role'], $currentUser['company_id'], $uploadLimits);
    
    // Generar nombre único para el archivo
    $fileInfo = pathinfo($file['name']);
    $fileExtension = strtolower($fileInfo['extension']);
    $originalName = $fileInfo['filename'];
    $uniqueFileName = generateUniqueFileName($originalName, $fileExtension);
    
    // Determinar carpeta de destino
    $uploadDir = getUploadDirectory($currentUser['company_id']);
    $filePath = $uploadDir . '/' . $uniqueFileName;
    
    // Crear directorio si no existe
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('No se pudo crear el directorio de subida');
        }
    }
    
    // Mover archivo al destino final
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Error al guardar el archivo en el servidor');
    }
    
    // Obtener información adicional del archivo
    $fileSize = filesize($filePath);
    $mimeType = mime_content_type($filePath);
    
    // Extraer metadatos del archivo (opcional para tu esquema actual)
    // $metadata = extractFileMetadata($filePath, $mimeType);
    
    // Buscar o crear categoría
    $categoryId = null;
    if (!empty($category)) {
        $categoryId = findOrCreateDocumentType($category);
    }
    
    // Insertar registro en la base de datos
    $documentData = [
        'name' => $title,
        'original_name' => $file['name'],
        'file_path' => $filePath,
        'file_size' => $fileSize,
        'mime_type' => $mimeType,
        'description' => $description,
        'document_type_id' => $categoryId,
        'user_id' => $currentUser['id'],
        'company_id' => $currentUser['company_id'],
        'department_id' => $currentUser['department_id'],
        'tags' => json_encode(array_map('trim', explode(',', $tags))),
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $documentId = insertDocument($documentData);
    
    if (!$documentId) {
        // Si falla la inserción, eliminar el archivo
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        throw new Exception('Error al guardar la información del documento');
    }
    
    // Procesar etiquetas (simplificado para tu esquema)
    // Las etiquetas ya están guardadas como JSON en el campo tags
    
    // Registrar actividad
    logActivity(
        $currentUser['id'], 
        'upload', 
        'documents', 
        $documentId, 
        "Documento subido: {$title}"
    );
    
    // Indexar documento para búsqueda (opcional)
    // scheduleDocumentIndexing($documentId);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Documento subido correctamente',
        'data' => [
            'id' => $documentId,
            'title' => $title,
            'file_name' => $uniqueFileName,
            'file_size' => $fileSize,
            'formatted_size' => formatBytes($fileSize),
            'upload_date' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log del error
    error_log("Upload error for user {$currentUser['id']}: " . $e->getMessage());
}

// Funciones auxiliares

function getUploadLimits($userId, $role, $companyId) {
    $limits = [
        'max_file_size' => 50 * 1024 * 1024, // 50MB
        'max_daily_uploads' => 100,
        'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif'],
        'max_total_size' => 1024 * 1024 * 1024 // 1GB
    ];
    
    if ($role === 'admin') {
        $limits['max_file_size'] = 100 * 1024 * 1024; // 100MB
        $limits['max_daily_uploads'] = 500;
        $limits['max_total_size'] = 5 * 1024 * 1024 * 1024; // 5GB
    }
    
    return $limits;
}

function validateUploadedFile($file, $limits) {
    // Verificar tamaño
    if ($file['size'] > $limits['max_file_size']) {
        throw new Exception('El archivo excede el tamaño máximo permitido de ' . formatBytes($limits['max_file_size']));
    }
    
    // Verificar extensión
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension'] ?? '');
    
    if (!in_array($extension, $limits['allowed_extensions'])) {
        throw new Exception('Tipo de archivo no permitido. Extensiones válidas: ' . implode(', ', $limits['allowed_extensions']));
    }
    
    // Verificar que no sea un archivo malicioso
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimeTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/gif'
    ];
    
    if (!in_array($mimeType, $allowedMimeTypes)) {
        throw new Exception('Tipo MIME no permitido: ' . $mimeType);
    }
}

function checkDailyLimits($userId, $role, $companyId, $limits) {
    $query = "SELECT COUNT(*) as daily_count 
              FROM documents 
              WHERE user_id = :user_id 
              AND DATE(created_at) = CURDATE() 
              AND status = 'active'";
    
    $result = fetchOne($query, ['user_id' => $userId]);
    $dailyCount = $result['daily_count'] ?? 0;
    
    if ($dailyCount >= $limits['max_daily_uploads']) {
        throw new Exception("Ha alcanzado el límite diario de {$limits['max_daily_uploads']} archivos");
    }
}

function generateUniqueFileName($originalName, $extension) {
    $timestamp = time();
    $randomString = bin2hex(random_bytes(8));
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
    $safeName = substr($safeName, 0, 50); // Limitar longitud
    
    return $safeName . '_' . $timestamp . '_' . $randomString . '.' . $extension;
}

function getUploadDirectory($companyId) {
    $baseDir = '../uploads';
    $companyDir = $baseDir . '/company_' . $companyId;
    $dateDir = $companyDir . '/' . date('Y/m');
    
    return $dateDir;
}

function extractFileMetadata($filePath, $mimeType) {
    $metadata = [
        'file_type' => $mimeType,
        'extracted_at' => date('Y-m-d H:i:s')
    ];
    
    // Extraer metadatos específicos según el tipo
    switch ($mimeType) {
        case 'application/pdf':
            $metadata = array_merge($metadata, extractPdfMetadata($filePath));
            break;
        case 'image/jpeg':
        case 'image/png':
            $metadata = array_merge($metadata, extractImageMetadata($filePath));
            break;
    }
    
    return $metadata;
}

function extractPdfMetadata($filePath) {
    $metadata = [];
    
    // Intentar extraer información básica del PDF
    try {
        if (function_exists('shell_exec') && !empty(shell_exec('which pdfinfo'))) {
            $output = shell_exec('pdfinfo ' . escapeshellarg($filePath));
            if ($output) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    if (strpos($line, ':') !== false) {
                        list($key, $value) = explode(':', $line, 2);
                        $metadata[trim($key)] = trim($value);
                    }
                }
            }
        }
    } catch (Exception $e) {
        // Si falla la extracción, continuar sin metadatos adicionales
    }
    
    return $metadata;
}

function extractImageMetadata($filePath) {
    $metadata = [];
    
    try {
        $imageInfo = getimagesize($filePath);
        if ($imageInfo) {
            $metadata['width'] = $imageInfo[0];
            $metadata['height'] = $imageInfo[1];
            $metadata['type'] = $imageInfo[2];
        }
        
        // Extraer EXIF si está disponible
        if (function_exists('exif_read_data') && in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM])) {
            $exifData = exif_read_data($filePath);
            if ($exifData) {
                $metadata['exif'] = $exifData;
            }
        }
    } catch (Exception $e) {
        // Si falla la extracción, continuar sin metadatos adicionales
    }
    
    return $metadata;
}

function findOrCreateDocumentType($categoryName) {
    // Buscar tipo de documento existente
    $query = "SELECT id FROM document_types WHERE LOWER(name) = LOWER(:name) AND status = 'active'";
    $result = fetchOne($query, ['name' => $categoryName]);
    
    if ($result) {
        return $result['id'];
    }
    
    // Crear nuevo tipo de documento
    $typeData = [
        'name' => ucfirst(strtolower($categoryName)),
        'description' => 'Tipo creado automáticamente',
        'extensions' => json_encode(['pdf', 'doc', 'docx', 'jpg', 'png']),
        'max_size' => 20971520, // 20MB
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $stmt = executeQuery(
        "INSERT INTO document_types (name, description, extensions, max_size, status, created_at) 
         VALUES (:name, :description, :extensions, :max_size, :status, :created_at)",
        $typeData
    );
    
    if ($stmt) {
        $database = new Database();
        $conn = $database->getConnection();
        return $conn->lastInsertId();
    }
    
    return null;
}

function insertDocument($data) {
    $query = "INSERT INTO documents (
        name, original_name, file_path, file_size, mime_type,
        description, document_type_id, user_id, company_id, department_id, tags,
        status, created_at, updated_at
    ) VALUES (
        :name, :original_name, :file_path, :file_size, :mime_type,
        :description, :document_type_id, :user_id, :company_id, :department_id, :tags,
        :status, :created_at, :updated_at
    )";
    
    $stmt = executeQuery($query, $data);
    
    if ($stmt) {
        $database = new Database();
        $conn = $database->getConnection();
        return $conn->lastInsertId();
    }
    
    return false;
}

function processTags($documentId, $tagsString) {
    $tags = array_map('trim', explode(',', $tagsString));
    $tags = array_filter($tags); // Remover vacíos
    
    foreach ($tags as $tagName) {
        if (strlen($tagName) > 0) {
            // Buscar o crear tag
            $query = "SELECT id FROM document_tags WHERE LOWER(name) = LOWER(:name)";
            $result = fetchOne($query, ['name' => $tagName]);
            
            if (!$result) {
                // Crear nuevo tag
                $tagData = [
                    'name' => strtolower($tagName),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                insertRecord('document_tags', $tagData);
                
                $query = "SELECT id FROM document_tags WHERE LOWER(name) = LOWER(:name)";
                $result = fetchOne($query, ['name' => $tagName]);
            }
            
            if ($result) {
                // Asociar tag con documento
                $relationData = [
                    'document_id' => $documentId,
                    'tag_id' => $result['id'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                insertRecord('document_tag_relations', $relationData);
            }
        }
    }
}

function scheduleDocumentIndexing($documentId) {
    // Aquí se podría implementar indexación para búsqueda
    // Por ahora, solo registramos que debe ser indexado
    $indexData = [
        'document_id' => $documentId,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    insertRecord('document_index_queue', $indexData);
}

function formatBytes($size, $precision = 2) {
    if ($size === 0) return '0 B';
    
    $base = log($size, 1024);
    $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}
?>