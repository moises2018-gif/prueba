<?php
/**
 * CONEXIÓN A BASE DE DATOS - VERSIÓN CORREGIDA PARA BD NUEVA
 * Archivo: includes/conexion.php
 */

function ConexionBD() {
    // Cargar configuración si no está cargada
    if (!defined('DB_HOST')) {
        $configPath = __DIR__ . '/config.php';
        if (file_exists($configPath)) {
            require_once $configPath;
        }
    }
    
    // Configuración para BD nueva (ajustada)
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $dbname = defined('DB_NAME') ? DB_NAME : 'asignacion_docente';
    $username = defined('DB_USER') ? DB_USER : 'moises'; // Cambiado para BD nueva
    $password = defined('DB_PASS') ? DB_PASS : 'moises'; // Vacío para BD nueva local
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
        
        // Verificar que la BD tiene las tablas necesarias
        verificarEstructuraBaseDatos($conn);
        
        // Log de conexión exitosa (solo en modo debug)
        $debugMode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
        if ($debugMode) {
            logSeguro('INFO', "Conexión a BD establecida", [
                'host' => $host,
                'database' => $dbname,
                'user' => $username
            ]);
        }
        
        return $conn;
        
    } catch (PDOException $e) {
        // Log del error
        logSeguro('ERROR', "Error de conexión a BD", [
            'mensaje' => $e->getMessage(),
            'host' => $host,
            'database' => $dbname,
            'user' => $username
        ]);
        
        // Mostrar error apropiado según modo debug
        $debugMode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
        
        if (!$debugMode) {
            echo "<div class='alert alert-error'>Error de conexión a la base de datos. Por favor, contacte al administrador.</div>";
        } else {
            echo "<div class='alert alert-error'>
                    <strong>Error de conexión:</strong> " . htmlspecialchars($e->getMessage()) . "<br>
                    <strong>Host:</strong> $host<br>
                    <strong>Base de datos:</strong> $dbname<br>
                    <strong>Usuario:</strong> $username<br>
                    <small>Verifique que la base de datos existe y las credenciales son correctas.</small>
                  </div>";
        }
        
        return null;
    }
}

/**
 * Verifica que las tablas y vistas necesarias existen
 */
function verificarEstructuraBaseDatos($conn) {
    $tablas_requeridas = [
        'docentes' => 'Tabla principal de docentes',
        'estudiantes' => 'Tabla principal de estudiantes', 
        'tipos_discapacidad' => 'Tipos de discapacidad con pesos AHP',
        'asignaciones' => 'Tabla de asignaciones activas',
        'criterios_ahp' => 'Criterios del algoritmo AHP',
        'vista_ranking_ahp_especifico' => 'Vista con ranking específico por discapacidad'
    ];
    
    $tablas_faltantes = [];
    
    foreach ($tablas_requeridas as $tabla => $descripcion) {
        try {
            $stmt = $conn->query("SELECT 1 FROM $tabla LIMIT 1");
        } catch (PDOException $e) {
            $tablas_faltantes[] = [
                'tabla' => $tabla,
                'descripcion' => $descripcion,
                'error' => $e->getMessage()
            ];
        }
    }
    
    if (!empty($tablas_faltantes)) {
        $debugMode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
        
        if ($debugMode) {
            echo "<div class='alert alert-error'>
                    <strong>⚠️ Estructura de BD incompleta:</strong><br>";
            foreach ($tablas_faltantes as $faltante) {
                echo "• <strong>{$faltante['tabla']}</strong>: {$faltante['descripcion']}<br>";
            }
            echo "<small>Ejecute el script SQL completo para crear todas las tablas necesarias.</small>
                  </div>";
        }
        
        logSeguro('WARNING', 'Tablas faltantes en BD', $tablas_faltantes);
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
        
        $debugMode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
        if ($debugMode) {
            logSeguro('DEBUG', "Conexión a BD cerrada");
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
        'user' => defined('DB_USER') ? DB_USER : 'root (default)',
        'debug_mode' => defined('DEBUG_MODE') ? (DEBUG_MODE ? 'Activado' : 'Desactivado') : 'No definido',
        'log_enabled' => defined('LOG_ENABLED') ? (LOG_ENABLED ? 'Activado' : 'Desactivado') : 'No definido'
    ];
}

/**
 * Función auxiliar para logging seguro
 */
function logSeguro($nivel, $mensaje, $contexto = []) {
    // Solo hacer log si está habilitado
    $logEnabled = defined('LOG_ENABLED') ? LOG_ENABLED : false;
    if (!$logEnabled) {
        return;
    }
    
    if (function_exists('logSeguro') === false || !class_exists('Logger', false)) {
        // Fallback a error_log nativo
        $contextStr = !empty($contexto) ? ' | ' . json_encode($contexto) : '';
        error_log("[$nivel] $mensaje$contextStr");
        return;
    }
    
    try {
        $logger = new Logger();
        $logger->log($nivel, $mensaje, $contexto);
    } catch (Exception $e) {
        // Si falla el logger, usar error_log nativo
        error_log("[$nivel] $mensaje | Error Logger: " . $e->getMessage());
    }
}

/**
 * Test de conexión para verificar que todo funciona
 */
function testConexion() {
    $conn = ConexionBD();
    
    if (!$conn) {
        return [
            'exito' => false,
            'mensaje' => 'No se pudo establecer conexión a la base de datos'
        ];
    }
    
    try {
        // Test básico
        $stmt = $conn->query("SELECT COUNT(*) as total FROM docentes");
        $resultado = $stmt->fetch();
        
        return [
            'exito' => true,
            'mensaje' => 'Conexión exitosa',
            'total_docentes' => $resultado['total'],
            'info_conexion' => infoConexion()
        ];
        
    } catch (PDOException $e) {
        return [
            'exito' => false,
            'mensaje' => 'Conexión establecida pero error al consultar: ' . $e->getMessage()
        ];
    } finally {
        cerrarConexion($conn);
    }
}
?>