<?php
/**
 * CONFIGURACIÓN PRINCIPAL DEL SISTEMA AHP - CORREGIDA
 * Archivo: includes/config.php
 */

// Cargar variables de entorno si existe el archivo .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    if ($env !== false) {
        foreach ($env as $key => $value) {
            $_ENV[$key] = $value;
        }
    }
}

// Configuración de la base de datos (con valores por defecto)
if (!defined('DB_HOST')) {
    define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', $_ENV['DB_NAME'] ?? 'asignacion_docente');
}
if (!defined('DB_USER')) {
    define('DB_USER', $_ENV['DB_USER'] ?? 'moises');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', $_ENV['DB_PASS'] ?? 'moises');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);
}

// Configuración del algoritmo AHP (con valores por defecto)
if (!defined('AHP_MAX_ESTUDIANTES_POR_DOCENTE')) {
    define('AHP_MAX_ESTUDIANTES_POR_DOCENTE', $_ENV['AHP_MAX_ESTUDIANTES_POR_DOCENTE'] ?? 7);
}
if (!defined('AHP_MAX_POR_TIPO_DISCAPACIDAD')) {
    define('AHP_MAX_POR_TIPO_DISCAPACIDAD', $_ENV['AHP_MAX_POR_TIPO_DISCAPACIDAD'] ?? 3);
}
if (!defined('AHP_PENALIZACION_CARGA')) {
    define('AHP_PENALIZACION_CARGA', $_ENV['AHP_PENALIZACION_CARGA'] ?? 0.15);
}
if (!defined('AHP_BONUS_EXPERIENCIA')) {
    define('AHP_BONUS_EXPERIENCIA', $_ENV['AHP_BONUS_EXPERIENCIA'] ?? 0.20);
}

// Configuración de logs (con valores por defecto)
if (!defined('LOG_ENABLED')) {
    define('LOG_ENABLED', isset($_ENV['LOG_ENABLED']) ? filter_var($_ENV['LOG_ENABLED'], FILTER_VALIDATE_BOOLEAN) : true);
}
if (!defined('LOG_LEVEL')) {
    define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'INFO');
}
if (!defined('LOG_DIR')) {
    define('LOG_DIR', __DIR__ . '/../logs/');
}

// Configuración de errores y debug (con valores por defecto)
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', isset($_ENV['DEBUG_MODE']) ? filter_var($_ENV['DEBUG_MODE'], FILTER_VALIDATE_BOOLEAN) : false);
}

// Configurar manejo de errores según el modo debug
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
}

// Configurar log de errores
ini_set('log_errors', 1);
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}
ini_set('error_log', LOG_DIR . 'php_errors.log');

// Zona horaria (con valor por defecto)
if (!defined('TIMEZONE')) {
    define('TIMEZONE', $_ENV['TIMEZONE'] ?? 'America/Guayaquil');
}
date_default_timezone_set(TIMEZONE);

// Autoloader para las clases
spl_autoload_register(function ($className) {
    $classFile = __DIR__ . '/../classes/' . $className . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
        return true;
    }
    return false;
});

// Crear directorio de logs si no existe
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// Función para verificar que todas las constantes están definidas
function verificarConfiguracion() {
    $constantes_requeridas = [
        'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PORT',
        'AHP_MAX_ESTUDIANTES_POR_DOCENTE', 'AHP_MAX_POR_TIPO_DISCAPACIDAD',
        'AHP_PENALIZACION_CARGA', 'AHP_BONUS_EXPERIENCIA',
        'LOG_ENABLED', 'LOG_LEVEL', 'LOG_DIR',
        'DEBUG_MODE', 'TIMEZONE'
    ];
    
    $faltantes = [];
    foreach ($constantes_requeridas as $constante) {
        if (!defined($constante)) {
            $faltantes[] = $constante;
        }
    }
    
    return [
        'completa' => empty($faltantes),
        'faltantes' => $faltantes,
        'debug_mode' => DEBUG_MODE,
        'log_enabled' => LOG_ENABLED
    ];
}

// Log de inicialización del sistema (solo si todo está configurado)
if (LOG_ENABLED && class_exists('Logger', false)) {
    try {
        $logger = new Logger();
        $config_status = verificarConfiguracion();
        $logger->info("Sistema AHP inicializado", [
            'configuracion_completa' => $config_status['completa'],
            'debug_mode' => DEBUG_MODE,
            'timezone' => TIMEZONE
        ]);
    } catch (Exception $e) {
        // Ignorar errores de logging durante la inicialización
    }
}
?>