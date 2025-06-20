<?php
/**
 * CONEXIÓN A BASE DE DATOS CORREGIDA
 * Archivo: includes/conexion.php
 * 
 * VERSIÓN CORREGIDA - SIN ERRORES DE CONSTANTES
 */

function ConexionBD() {
    // Cargar configuración si no está cargada
    if (!defined('DB_HOST')) {
        $configPath = __DIR__ . '/config.php';
        if (file_exists($configPath)) {
            require_once $configPath;
        }
    }
    
    // Usar valores por defecto si las constantes no están definidas
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $dbname = defined('DB_NAME') ? DB_NAME : 'asignacion_docente1';
    $username = defined('DB_USER') ? DB_USER : 'moises';
    $password = defined('DB_PASS') ? DB_PASS : 'moises';
    $puerto = defined('DB_PORT') ? DB_PORT : 3306;

    try {
        $dsn = "mysql:host=$host;port=$puerto;dbname=$dbname;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::ATTR_TIMEOUT => 30,
            PDO::ATTR_PERSISTENT => false
        ];
        
        $conn = new PDO($dsn, $username, $password, $options);
        
        // Log de conexión exitosa solo si está habilitado el logging y modo debug
        $logEnabled = defined('LOG_ENABLED') ? LOG_ENABLED : false;
        $debugMode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
        
        if ($logEnabled && $debugMode && class_exists('Logger')) {
            $logger = new Logger();
            $logger->debug("Conexión a BD establecida", [
                'host' => $host,
                'database' => $dbname,
                'user' => $username
            ]);
        }
        
        return $conn;
        
    } catch (PDOException $e) {
        // Log del error si la clase Logger existe
        if (class_exists('Logger')) {
            try {
                $logger = new Logger();
                $logger->error("Error de conexión a BD", [
                    'mensaje' => $e->getMessage(),
                    'host' => $host,
                    'database' => $dbname,
                    'user' => $username
                ]);
            } catch (Exception $logError) {
                // Si falla el log, continuar sin logging
            }
        }
        
        // Verificar si estamos en modo debug
        $debugMode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
        
        // En producción, no mostrar detalles de la conexión
        if (!$debugMode) {
            echo "<div class='alert alert-error'>Error de conexión a la base de datos. Por favor, contacte al administrador.</div>";
        } else {
            echo "<div class='alert alert-error'>Error de conexión: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        return null;
    }
}

/**
 * Verifica si la conexión está activa
 */
function verificarConexion($conn) {
    if (!$conn) {
        return false;
    }
    
    try {
        $conn->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Cierra la conexión de forma segura
 */
function cerrarConexion(&$conn) {
    if ($conn) {
        $conn = null;
        
        // Log solo si está disponible
        $logEnabled = defined('LOG_ENABLED') ? LOG_ENABLED : false;
        if ($logEnabled && class_exists('Logger')) {
            try {
                $logger = new Logger();
                $logger->debug("Conexión a BD cerrada");
            } catch (Exception $e) {
                // Ignorar errores de logging
            }
        }
    }
}

/**
 * Obtiene información de la conexión para debugging
 */
function infoConexion() {
    return [
        'host' => defined('DB_HOST') ? DB_HOST : 'localhost (default)',
        'database' => defined('DB_NAME') ? DB_NAME : 'asignacion_docente (default)',
        'user' => defined('DB_USER') ? DB_USER : 'moises (default)',
        'debug_mode' => defined('DEBUG_MODE') ? (DEBUG_MODE ? 'Activado' : 'Desactivado') : 'No definido',
        'log_enabled' => defined('LOG_ENABLED') ? (LOG_ENABLED ? 'Activado' : 'Desactivado') : 'No definido'
    ];
}
?>