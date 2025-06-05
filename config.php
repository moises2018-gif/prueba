<?php
// config.php - Archivo de configuración de la base de datos

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'asignacion_docente');
define('DB_USER', 'moises');
define('DB_PASS', 'moises');
define('DB_CHARSET', 'utf8mb4'); // Asegúrate de que el charset sea correcto

// Configuración del algoritmo
define('MAX_SEMINARIOS', 10); // Máximo de seminarios para normalización
define('BONUS_EXPERIENCIA_ALTA', 1.2); // Bonus por experiencia alta (20%)
define('BONUS_EXPERIENCIA_GENERAL', 0.1); // Bonus máximo por experiencia general (10%)

// Configuración de la aplicación
define('TIMEZONE', 'America/Guayaquil');
define('DEBUG_MODE', true); // Cambiar a false en producción

// Configurar zona horaria
date_default_timezone_set(TIMEZONE);

// Función para obtener conexión a la base de datos
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (DEBUG_MODE) {
                die("Error de conexión: " . $e->getMessage());
            } else {
                die("Error de conexión a la base de datos");
            }
        }
    }
    
    return $pdo;
}

// Función para logging de errores
function logError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    
    $logMessage = "[$timestamp] ERROR: $message";
    if ($contextStr) {
        $logMessage .= " | Context: $contextStr";
    }
    
    error_log($logMessage . PHP_EOL, 3, 'errors.log');
}

// Función para logging de información
function logInfo($message, $context = []) {
    if (!DEBUG_MODE) return;
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    
    $logMessage = "[$timestamp] INFO: $message";
    if ($contextStr) {
        $logMessage .= " | Context: $contextStr";
    }
    
    error_log($logMessage . PHP_EOL, 3, 'info.log');
}

// Función para validar entrada JSON
function validateJsonInput($input) {
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('JSON inválido: ' . json_last_error_msg());
    }
    
    if (!is_array($input)) {
        throw new InvalidArgumentException('El input debe ser un array');
    }
    
    return true;
}

// Función para sanitizar output
function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    } elseif (is_string($data)) {
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    } else {
        return $data;
    }
}

// Función para respuesta JSON estandarizada
function jsonResponse($success, $data = null, $error = null) {
    $response = ['success' => $success];
    
    if ($data !== null) {
        $response['data'] = sanitizeOutput($data);
    }
    
    if ($error !== null) {
        $response['error'] = $error;
        if (DEBUG_MODE) {
            logError($error);
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar que las tablas existen
function verifyDatabaseTables() {
    $pdo = getDBConnection();
    $requiredTables = [
        'estudiantes', 'docentes', 'materias', 'criterios', 
        'resultados', 'comparaciones', 'docentes_materias', 'asignaciones'
    ];
    
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $missingTables = array_diff($requiredTables, $existingTables);
    
    if (!empty($missingTables)) {
        throw new Exception("Faltan las siguientes tablas en la base de datos: " . implode(', ', $missingTables));
    }
    
    return true;
}

// Inicialización
try {
    verifyDatabaseTables();
    logInfo("Sistema inicializado correctamente");
} catch (Exception $e) {
    logError("Error en la inicialización: " . $e->getMessage());
    if (DEBUG_MODE) {
        die("Error de inicialización: " . $e->getMessage());
    }
}
?>