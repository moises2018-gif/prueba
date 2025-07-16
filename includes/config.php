<?php
/**
 * CONFIGURACIÓN PRINCIPAL DEL SISTEMA AHP - VERSIÓN DEBUG
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

// Configuración de errores y debug - ACTIVAR DEBUG TEMPORALMENTE
if (!defined('DEBUG_MODE')) {
    // FORZAR DEBUG MODE PARA DIAGNOSTICAR PROBLEMAS
    define('DEBUG_MODE', true); // Cambiar a false en producción
}

// Configurar manejo de errores según el modo debug
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // Log adicional para debugging
    error_log("=== DEBUG MODE ACTIVADO ===");
    error_log("DB_HOST: " . DB_HOST);
    error_log("DB_NAME: " . DB_NAME);
    error_log("DB_USER: " . DB_USER);
    error_log("DB_PORT: " . DB_PORT);
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

// Autoloader para las clases - MEJORADO
spl_autoload_register(function ($className) {
    $classFile = __DIR__ . '/../classes/' . $className . '.php';
    if (file_exists($classFile)) {
        require_once $classFile;
        if (DEBUG_MODE) {
            error_log("Clase cargada: $className");
        }
        return true;
    }
    if (DEBUG_MODE) {
        error_log("Clase no encontrada: $className en $classFile");
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

// Mostrar información de configuración en modo debug
if (DEBUG_MODE) {
    $config_status = verificarConfiguracion();
    error_log("Configuración del sistema:");
    error_log("- Configuración completa: " . ($config_status['completa'] ? 'SÍ' : 'NO'));
    error_log("- DEBUG_MODE: " . (DEBUG_MODE ? 'ACTIVADO' : 'DESACTIVADO'));
    error_log("- LOG_ENABLED: " . (LOG_ENABLED ? 'ACTIVADO' : 'DESACTIVADO'));
    error_log("- TIMEZONE: " . TIMEZONE);
    
    if (!$config_status['completa']) {
        error_log("Constantes faltantes: " . implode(', ', $config_status['faltantes']));
    }
}

// Log de inicialización del sistema
if (LOG_ENABLED) {
    try {
        // Solo intentar cargar Logger si la clase existe
        if (class_exists('Logger', false)) {
            $logger = new Logger();
            $config_status = verificarConfiguracion();
            $logger->info("Sistema AHP inicializado", [
                'configuracion_completa' => $config_status['completa'],
                'debug_mode' => DEBUG_MODE,
                'timezone' => TIMEZONE,
                'log_enabled' => LOG_ENABLED
            ]);
        }
    } catch (Exception $e) {
        // Si hay error con Logger, usar error_log básico
        error_log("Error inicializando Logger: " . $e->getMessage());
        error_log("Sistema AHP inicializado - DEBUG: " . (DEBUG_MODE ? 'ON' : 'OFF'));
    }
}

// Función de diagnóstico del sistema
function diagnosticarSistema() {
    $diagnostico = [
        'timestamp' => date('Y-m-d H:i:s'),
        'configuracion' => verificarConfiguracion(),
        'php_version' => phpversion(),
        'extensions' => [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mbstring' => extension_loaded('mbstring'),
            'json' => extension_loaded('json')
        ],
        'permisos' => [
            'logs_escribible' => is_writable(LOG_DIR),
            'logs_existe' => is_dir(LOG_DIR)
        ]
    ];
    
    if (DEBUG_MODE) {
        error_log("=== DIAGNÓSTICO DEL SISTEMA ===");
        error_log(json_encode($diagnostico, JSON_PRETTY_PRINT));
    }
    
    return $diagnostico;
}

// Ejecutar diagnóstico si estamos en modo debug
if (DEBUG_MODE) {
    diagnosticarSistema();
}
?>