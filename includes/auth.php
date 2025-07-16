<?php
/**
 * MIDDLEWARE DE AUTENTICACIÓN SIMPLIFICADO
 * Archivo: includes/auth.php
 * 
 * Sistema simplificado solo para verificar que el usuario esté logueado
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir configuración y conexión
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/conexion.php';

/**
 * Verifica si el usuario está autenticado
 */
function verificarAutenticacion() {
    // Verificar si existe sesión básica
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['token_sesion'])) {
        redirigirLogin('Debe iniciar sesión para acceder');
        return false;
    }
    
    // Verificar que la sesión no haya expirado (24 horas)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 86400) {
        cerrarSesion('Sesión expirada. Inicie sesión nuevamente.');
        return false;
    }
    
    try {
        $conn = ConexionBD();
        if (!$conn) {
            throw new Exception('Error de conexión a la base de datos');
        }
        
        // Verificar sesión en la base de datos
        $query = "SELECT u.id_usuario, u.usuario, u.nombre_completo, u.rol, u.activo,
                         s.fecha_expiracion, s.activa as sesion_activa
                  FROM usuarios u
                  JOIN sesiones_usuario s ON u.id_usuario = s.id_usuario
                  WHERE s.token_sesion = :token 
                  AND u.id_usuario = :user_id
                  AND u.activo = 1 
                  AND s.activa = 1 
                  AND s.fecha_expiracion > NOW()";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':token' => $_SESSION['token_sesion'],
            ':user_id' => $_SESSION['usuario_id']
        ]);
        
        $sesion_valida = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sesion_valida) {
            cerrarSesion('Sesión inválida o expirada');
            return false;
        }
        
        // Actualizar datos de sesión si es necesario
        if ($_SESSION['nombre_completo'] !== $sesion_valida['nombre_completo']) {
            $_SESSION['nombre_completo'] = $sesion_valida['nombre_completo'];
            $_SESSION['rol'] = $sesion_valida['rol'];
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error en verificación de autenticación: " . $e->getMessage());
        cerrarSesion('Error del sistema. Inicie sesión nuevamente.');
        return false;
    }
}

/**
 * Obtiene información del usuario actual
 */
function obtenerUsuarioActual() {
    if (!isset($_SESSION['usuario_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['usuario_id'],
        'usuario' => $_SESSION['usuario'],
        'nombre_completo' => $_SESSION['nombre_completo'],
        'rol' => $_SESSION['rol'],
        'email' => $_SESSION['email'] ?? '',
        'login_time' => $_SESSION['login_time'] ?? 0
    ];
}

/**
 * Cierra la sesión del usuario
 */
function cerrarSesion($mensaje = 'Sesión cerrada') {
    if (isset($_SESSION['token_sesion'])) {
        try {
            $conn = ConexionBD();
            if ($conn) {
                // Marcar sesión como inactiva en la BD
                $query = "UPDATE sesiones_usuario SET activa = 0 WHERE token_sesion = :token";
                $stmt = $conn->prepare($query);
                $stmt->execute([':token' => $_SESSION['token_sesion']]);
            }
        } catch (Exception $e) {
            error_log("Error al cerrar sesión en BD: " . $e->getMessage());
        }
    }
    
    // Limpiar variables de sesión
    $_SESSION = array();
    
    // Destruir la cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir la sesión
    session_destroy();
    
    // Redirigir al login
    redirigirLogin($mensaje);
}

/**
 * Redirige al login con mensaje
 */
function redirigirLogin($mensaje = '') {
    $base_url = obtenerBaseURL();
    $url = $base_url . 'login.php';
    
    if (!empty($mensaje)) {
        $url .= '?error=' . urlencode($mensaje);
    }
    
    header('Location: ' . $url);
    exit;
}

/**
 * Obtiene la URL base del sistema
 */
function obtenerBaseURL() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['SCRIPT_NAME']);
    
    // Ajustar path según la estructura del proyecto
    if (strpos($path, '/pages') !== false) {
        $path = str_replace('/pages', '', $path);
    } elseif (strpos($path, '/procesar') !== false) {
        $path = str_replace('/procesar', '', $path);
    } elseif (strpos($path, '/includes') !== false) {
        $path = str_replace('/includes', '', $path);
    }
    
    return $protocol . $host . $path . '/';
}

/**
 * Middleware principal - Llamar en páginas protegidas
 */
function requireAuth() {
    if (!verificarAutenticacion()) {
        exit; // La función de verificación ya maneja la redirección
    }
}

/**
 * Limpiar sesiones expiradas (llamar periódicamente)
 */
function limpiarSesionesExpiradas() {
    try {
        $conn = ConexionBD();
        if ($conn) {
            $query = "DELETE FROM sesiones_usuario 
                      WHERE fecha_expiracion < NOW() OR activa = 0";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            
            return $stmt->rowCount();
        }
    } catch (Exception $e) {
        error_log("Error limpiando sesiones expiradas: " . $e->getMessage());
    }
    
    return 0;
}

// Auto-ejecutar limpieza de sesiones (1% de probabilidad)
if (rand(1, 100) === 1) {
    limpiarSesionesExpiradas();
}
?>