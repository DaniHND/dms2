<?php
// config/upload_config.php
// Configuración de uploads para DMS2

define('UPLOAD_MAX_SIZE', 20 * 1024 * 1024); // 20MB
define('UPLOAD_ALLOWED_TYPES', ['pdf', 'doc', 'docx', 'xlsx', 'jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_BASE_DIR', __DIR__ . '/../uploads/');

// Tipos MIME permitidos
define('ALLOWED_MIME_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg',
    'image/png',
    'image/gif'
]);

?>