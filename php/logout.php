<?php
/**
 * Logout.php - Manejo de cierre de sesión
 * 
 * Este archivo maneja el proceso completo de cierre de sesión:
 * - Destruye la sesión actual
 * - Limpia cookies de sesión
 * - Registra el logout (opcional)
 * - Redirige al usuario
 */

// Iniciar sesión para poder destruirla
session_start();

// Función para registrar el logout (opcional)
function registrarLogout() {
    // Aquí puedes agregar lógica para registrar el logout en base de datos
    // Por ejemplo: fecha/hora del logout, IP del usuario, etc.
    
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $logout_time = date('Y-m-d H:i:s');
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Desconocida';
        
        // Ejemplo de registro en log file
        $log_message = "[{$logout_time}] Usuario ID: {$user_id} cerró sesión desde IP: {$ip_address}\n";
        error_log($log_message, 3, '../logs/logout.log');
        
        // Aquí podrías actualizar una tabla de sesiones en la base de datos
        // updateSessionLog($user_id, $logout_time, 'logout');
    }
}

// Función para limpiar cookies de sesión
function limpiarCookiesSesion() {
    // Si se están usando cookies para la sesión, eliminarlas
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

// Función para destruir sesión completamente
function destruirSesionCompleta() {
    // Limpiar todas las variables de sesión
    $_SESSION = array();
    
    // Limpiar cookies de sesión
    limpiarCookiesSesion();
    
    // Destruir la sesión
    session_destroy();
}

// Función para determinar la página de destino
function obtenerPaginaDestino() {
    // Verificar si hay una página de destino específica
    if (isset($_GET['redirect'])) {
        $redirect = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
        // Validar que sea una URL local segura
        if (filter_var($redirect, FILTER_VALIDATE_URL) && 
            strpos($redirect, $_SERVER['HTTP_HOST']) !== false) {
            return $redirect;
        }
    }
    
    // Página de destino por defecto
    return "../login.php";
}

// Función principal para manejar el logout
function procesarLogout() {
    try {
        // Registrar el logout si es necesario
        registrarLogout();
        
        // Destruir sesión completamente
        destruirSesionCompleta();
        
        // Obtener página de destino
        $destino = obtenerPaginaDestino();
        
        // Agregar parámetros de logout exitoso
        $separator = strpos($destino, '?') !== false ? '&' : '?';
        $destino .= $separator . 'logout=success';
        
        return $destino;
        
    } catch (Exception $e) {
        // En caso de error, registrar y continuar con logout básico
        error_log("Error en logout: " . $e->getMessage(), 3, '../logs/error.log');
        
        // Logout básico
        session_unset();
        session_destroy();
        
        return "../login.php?logout=error";
    }
}

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Logout via AJAX
    header('Content-Type: application/json');
    
    try {
        $destino = procesarLogout();
        
        echo json_encode([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente',
            'redirect' => $destino
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al cerrar sesión',
            'error' => $e->getMessage()
        ]);
    }
    
    exit();
} else {
    // Logout tradicional via GET
    $destino = procesarLogout();
    
    // Prevenir caché de la página
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Redirigir al usuario
    header("Location: " . $destino);
    exit();
}
?>