<?php
// setup_folders.php
// Script para crear las carpetas necesarias del sistema DMS2

echo "<h2>🗂️ Configuración de Carpetas - DMS2</h2>\n";

// Crear carpetas necesarias
$folders = [
    'uploads' => 'Carpeta principal de archivos subidos',
    'uploads/company_1' => 'Archivos de la empresa ejemplo',
    'uploads/company_2' => 'Archivos de la corporación demo',
    'uploads/temp' => 'Archivos temporales',
    'assets/uploads' => 'Assets subidos por usuarios',
    'backups' => 'Respaldos del sistema',
    'logs' => 'Archivos de registro'
];

$success = 0;
$errors = 0;

foreach ($folders as $folder => $description) {
    if (!is_dir($folder)) {
        if (mkdir($folder, 0755, true)) {
            echo "✅ Creada: $folder ($description)<br>\n";
            
            // Crear archivo .htaccess para proteger uploads
            if (strpos($folder, 'uploads') !== false) {
                $htaccess = $folder . '/.htaccess';
                if (!file_exists($htaccess)) {
                    $htaccessContent = "# Proteger archivos subidos\n";
                    $htaccessContent .= "Options -Indexes\n";
                    $htaccessContent .= "Order Allow,Deny\n";
                    $htaccessContent .= "Allow from all\n";
                    $htaccessContent .= "\n# Prevenir ejecución de scripts\n";
                    $htaccessContent .= "<Files ~ \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">\n";
                    $htaccessContent .= "    Order Allow,Deny\n";
                    $htaccessContent .= "    Deny from all\n";
                    $htaccessContent .= "</Files>\n";
                    
                    if (file_put_contents($htaccess, $htaccessContent)) {
                        echo "   📄 Creado .htaccess de seguridad<br>\n";
                    }
                }
            }
            
            // Crear archivo index.php para prevenir listado
            $indexFile = $folder . '/index.php';
            if (!file_exists($indexFile)) {
                $indexContent = "<?php\n// Acceso denegado\nheader('HTTP/1.0 403 Forbidden');\nexit('Acceso denegado');\n?>";
                if (file_put_contents($indexFile, $indexContent)) {
                    echo "   🚫 Creado index.php de protección<br>\n";
                }
            }
            
            $success++;
        } else {
            echo "❌ Error al crear: $folder<br>\n";
            $errors++;
        }
    } else {
        echo "ℹ️ Ya existe: $folder<br>\n";
    }
}

echo "<br>\n";

// Verificar permisos
echo "<h3>🔐 Verificación de Permisos</h3>\n";

$checkFolders = ['uploads', 'backups', 'logs'];

foreach ($checkFolders as $folder) {
    if (is_dir($folder)) {
        if (is_writable($folder)) {
            echo "✅ $folder - Escribible<br>\n";
        } else {
            echo "⚠️ $folder - No escribible (chmod 755 requerido)<br>\n";
        }
    } else {
        echo "❌ $folder - No existe<br>\n";
    }
}

echo "<br>\n";

// Crear archivo de configuración de ejemplo para uploads
$configFile = 'config/upload_config.php';
if (!file_exists($configFile)) {
    $configContent = "<?php\n";
    $configContent .= "// config/upload_config.php\n";
    $configContent .= "// Configuración de uploads para DMS2\n\n";
    $configContent .= "define('UPLOAD_MAX_SIZE', 20 * 1024 * 1024); // 20MB\n";
    $configContent .= "define('UPLOAD_ALLOWED_TYPES', ['pdf', 'doc', 'docx', 'xlsx', 'jpg', 'jpeg', 'png', 'gif']);\n";
    $configContent .= "define('UPLOAD_BASE_DIR', __DIR__ . '/../uploads/');\n\n";
    $configContent .= "// Tipos MIME permitidos\n";
    $configContent .= "define('ALLOWED_MIME_TYPES', [\n";
    $configContent .= "    'application/pdf',\n";
    $configContent .= "    'application/msword',\n";
    $configContent .= "    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',\n";
    $configContent .= "    'application/vnd.ms-excel',\n";
    $configContent .= "    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',\n";
    $configContent .= "    'image/jpeg',\n";
    $configContent .= "    'image/png',\n";
    $configContent .= "    'image/gif'\n";
    $configContent .= "]);\n\n";
    $configContent .= "?>";
    
    if (file_put_contents($configFile, $configContent)) {
        echo "✅ Creado archivo de configuración: $configFile<br>\n";
    }
}

echo "<br>\n";
echo "<h3>📊 Resumen</h3>\n";
echo "✅ Carpetas creadas exitosamente: $success<br>\n";
echo "❌ Errores: $errors<br>\n";

if ($errors === 0) {
    echo "<br>\n<div style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724;'>";
    echo "🎉 <strong>¡Configuración completada!</strong><br>\n";
    echo "Todas las carpetas necesarias han sido creadas y protegidas.<br>\n";
    echo "El sistema está listo para subir documentos.";
    echo "</div>\n";
} else {
    echo "<br>\n<div style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24;'>";
    echo "⚠️ <strong>Configuración incompleta</strong><br>\n";
    echo "Algunos directorios no pudieron crearse. Verifique los permisos del servidor.";
    echo "</div>\n";
}

echo "<br>\n<a href='dashboard.php'>← Volver al Dashboard</a><br>\n";
echo "<a href='upload.php'>📤 Ir a Subir Documentos</a><br>\n";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 50px auto;
    padding: 20px;
    background: #f8f9fa;
}

h2, h3 {
    color: #333;
    border-bottom: 2px solid #8B4513;
    padding-bottom: 10px;
}

br {
    line-height: 1.6;
}
</style>