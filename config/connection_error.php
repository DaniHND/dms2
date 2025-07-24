<?php
// config/connection_error.php
// Página de error cuando no hay conexión a la base de datos
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error de Conexión - DMS2</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #F5E6D3 0%, #E6D7C3 100%);
            margin: 0;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        
        .error-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 40px;
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 32px;
        }
        
        .error-title {
            color: #2c3e50;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        
        .error-message {
            color: #5a5a5a;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .error-steps {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .error-steps h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .error-steps ol {
            margin: 0;
            padding-left: 20px;
        }
        
        .error-steps li {
            margin-bottom: 8px;
            color: #5a5a5a;
        }
        
        .error-steps strong {
            color: #D4AF37;
        }
        
        .retry-button {
            background: linear-gradient(135deg, #D4AF37 0%, #B8860B 100%);
            color: #2c3e50;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .retry-button:hover {
            background: linear-gradient(135deg, #B8860B 0%, #9A7209 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }
        
        .technical-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: #856404;
        }
        
        @media (max-width: 480px) {
            .error-container {
                padding: 20px;
            }
            
            .error-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        
        <h1 class="error-title">Error de Conexión a la Base de Datos</h1>
        
        <p class="error-message">
            No se pudo establecer conexión con la base de datos MySQL. 
            Esto puede deberse a que el servidor de base de datos no está ejecutándose.
        </p>
        
        <div class="error-steps">
            <h3>🔧 Pasos para solucionarlo:</h3>
            <ol>
                <li>Abrir el <strong>Panel de Control de XAMPP</strong></li>
                <li>Hacer clic en <strong>"Start"</strong> junto a <strong>MySQL</strong></li>
                <li>Verificar que MySQL muestre estado <strong>"Running"</strong> (verde)</li>
                <li>Abrir <strong>phpMyAdmin</strong> (http://localhost/phpmyadmin)</li>
                <li>Verificar que existe la base de datos <strong>"dms2"</strong></li>
                <li>Si no existe, importar el archivo <strong>dms2.sql</strong></li>
            </ol>
        </div>
        
        <div class="technical-info">
            <strong>Información técnica:</strong><br>
            • Host: localhost<br>
            • Puerto: 3306<br>
            • Base de datos: dms2<br>
            • Usuario: root<br>
        </div>
        
        <br>
        
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="retry-button">
            🔄 Reintentar Conexión
        </a>
        
        <br><br>
        
        <p style="font-size: 14px; color: #888;">
            Si el problema persiste, verifica que XAMPP esté correctamente instalado y configurado.
        </p>
    </div>
</body>
</html>