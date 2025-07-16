<?php
/**
 * CONEXIÓN A BASE DE DATOS - VERSIÓN CORREGIDA SIN DUPLICADOS
 * Archivo: includes/conexion.php
 */

// Prevenir redeclaración de funciones
if (!function_exists('ConexionBD')) {
    
    function ConexionBD() {
        // Cargar configuración si no está cargada
        if (!defined('DB_HOST')) {
            $configPath = __DIR__ . '/config.php';
            if (file_exists($configPath)) {
                require_once $configPath;
            }
        }
        
        // Configuración para BD nueva
        $host = defined('DB_HOST') ? DB_HOST : 'localhost';
        $dbname = defined('DB_NAME') ? DB_NAME : 'asignacion_docente';
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
                PDO::ATTR_TIMEOUT => 10,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $conn = new PDO($dsn, $username, $password, $options);
            
            // Test rápido de conexión
            $conn->query('SELECT 1');
            
            // Log de conexión exitosa (solo en modo debug)
            $debugMode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
            if ($debugMode) {
                error_log("Conexión BD exitosa - Host: $host, DB: $dbname, User: $username");
            }
            
            return $conn;
            
        } catch (PDOException $e) {
            // Log del error
            error_log("Error de conexión BD: " . $e->getMessage() . " - Host: $host, DB: $dbname, User: $username");
            
            // Mostrar error apropiado según modo debug
            $debugMode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
            
            if ($debugMode) {
                echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>";
                echo "<h4>❌ Error de Conexión a la Base de Datos</h4>";
                echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                echo "<p><strong>Host:</strong> $host</p>";
                echo "<p><strong>Base de datos:</strong> $dbname</p>";
                echo "<p><strong>Usuario:</strong> $username</p>";
                echo "<h5>🔧 Pasos para solucionar:</h5>";
                echo "<ol>";
                echo "<li><strong>Verificar MySQL:</strong> Asegúrate de que MySQL/XAMPP esté ejecutándose</li>";
                echo "<li><strong>Verificar puerto:</strong> MySQL debe estar en puerto $puerto</li>";
                echo "<li><strong>Verificar base de datos:</strong> Crear BD '$dbname' si no existe</li>";
                echo "<li><strong>Verificar usuario:</strong> Usuario '$username' debe existir con permisos</li>";
                echo "</ol>";
                echo "<p><strong>💡 Comandos de verificación:</strong></p>";
                echo "<code>mysql -u $username -p$password -h $host -P $puerto</code><br>";
                echo "<code>CREATE DATABASE IF NOT EXISTS $dbname;</code>";
                echo "</div>";
            } else {
                echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 20px; border-radius: 5px;'>";
                echo "<h4>❌ Error de Conexión</h4>";
                echo "<p>No se pudo conectar a la base de datos. Contacte al administrador.</p>";
                echo "<p><small>Para más detalles, active DEBUG_MODE en config.php</small></p>";
                echo "</div>";
            }
            
            return null;
        }
    }
}

// Prevenir redeclaración de verificarConexionBD
if (!function_exists('verificarConexionBD')) {
    
    /**
     * Función para verificar si la conexión está funcionando
     */
    function verificarConexionBD() {
        $conn = ConexionBD();
        
        if (!$conn) {
            return [
                'status' => 'error',
                'mensaje' => 'No se pudo establecer conexión a MySQL',
                'solucion' => 'Verificar que MySQL esté ejecutándose y credenciales sean correctas'
            ];
        }
        
        try {
            // Test con una consulta simple a una tabla que debe existir
            $stmt = $conn->query("SELECT COUNT(*) as total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()");
            $result = $stmt->fetch();
            
            $total_tablas = $result['total'] ?? 0;
            
            if ($total_tablas < 5) {
                return [
                    'status' => 'warning',
                    'mensaje' => "Conexión OK pero BD incompleta ($total_tablas tablas)",
                    'solucion' => 'Ejecutar script SQL para crear todas las tablas necesarias'
                ];
            }
            
            return [
                'status' => 'success',
                'mensaje' => "Conexión exitosa ($total_tablas tablas disponibles)",
                'info' => 'Base de datos funcionando correctamente'
            ];
            
        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'mensaje' => 'Conexión establecida pero error en consulta: ' . $e->getMessage(),
                'solucion' => 'Verificar permisos del usuario en la base de datos'
            ];
        }
    }
}

// Prevenir redeclaración de verificarConexion
if (!function_exists('verificarConexion')) {
    
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
}

// Prevenir redeclaración de cerrarConexion
if (!function_exists('cerrarConexion')) {
    
    /**
     * Cierra la conexión de forma segura
     */
    function cerrarConexion(&$conn) {
        if ($conn) {
            $conn = null;
            
            $debugMode = defined('DEBUG_MODE') ? DEBUG_MODE : false;
            if ($debugMode) {
                error_log("Conexión a BD cerrada");
            }
        }
    }
}

// Prevenir redeclaración de infoConexion
if (!function_exists('infoConexion')) {
    
    /**
     * Obtiene información de la conexión para debugging
     */
    function infoConexion() {
        return [
            'host' => defined('DB_HOST') ? DB_HOST : 'localhost (default)',
            'database' => defined('DB_NAME') ? DB_NAME : 'asignacion_docente (default)',
            'user' => defined('DB_USER') ? DB_USER : 'moises (default)',
            'port' => defined('DB_PORT') ? DB_PORT : '3306 (default)',
            'debug_mode' => defined('DEBUG_MODE') ? (DEBUG_MODE ? 'Activado' : 'Desactivado') : 'No definido',
            'log_enabled' => defined('LOG_ENABLED') ? (LOG_ENABLED ? 'Activado' : 'Desactivado') : 'No definido'
        ];
    }
}

// Prevenir redeclaración de testConexion
if (!function_exists('testConexion')) {
    
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
            $stmt = $conn->query("SELECT COUNT(*) as total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE()");
            $resultado = $stmt->fetch();
            
            return [
                'exito' => true,
                'mensaje' => 'Conexión exitosa',
                'total_tablas' => $resultado['total'],
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
}

// Prevenir redeclaración de ejecutarQuerySegura
if (!function_exists('ejecutarQuerySegura')) {
    
    /**
     * Función auxiliar para manejo seguro de queries
     */
    function ejecutarQuerySegura($conn, $query, $params = []) {
        if (!$conn) {
            return null;
        }
        
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Error en query: " . $e->getMessage() . " | Query: " . substr($query, 0, 100));
            return null;
        }
    }
}
?>