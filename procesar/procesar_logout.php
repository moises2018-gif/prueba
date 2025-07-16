<?php
/**
 * PROCESADOR DE LOGOUT DEL SISTEMA AHP
 * Archivo: procesar/procesar_logout.php
 */

session_start();

include '../includes/config.php';
include '../includes/conexion.php';

// Función para obtener IP del cliente
function obtenerIPCliente() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

try {
    $usuario_nombre = $_SESSION['usuario'] ?? 'Desconocido';
    $usuario_completo = $_SESSION['nombre_completo'] ?? 'Usuario';
    
    // Si existe token de sesión, marcarlo como inactivo en la BD
    if (isset($_SESSION['token_sesion'])) {
        $conn = ConexionBD();
        
        if ($conn) {
            // Marcar sesión como inactiva
            $query = "UPDATE sesiones_usuario 
                      SET activa = 0 
                      WHERE token_sesion = :token";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([':token' => $_SESSION['token_sesion']]);
            
            // Log del logout
            $ip_cliente = obtenerIPCliente();
            error_log("Logout exitoso - Usuario: $usuario_nombre ($usuario_completo) - IP: $ip_cliente");
            
            // Opcional: Limpiar sesiones expiradas del usuario
            if (isset($_SESSION['usuario_id'])) {
                $query_limpiar = "UPDATE sesiones_usuario 
                                  SET activa = 0 
                                  WHERE id_usuario = :user_id 
                                  AND fecha_expiracion < NOW()";
                
                $stmt_limpiar = $conn->prepare($query_limpiar);
                $stmt_limpiar->execute([':user_id' => $_SESSION['usuario_id']]);
            }
        }
    }
    
} catch (Exception $e) {
    // Log del error pero continuar con el logout
    error_log("Error durante logout: " . $e->getMessage());
}

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al login con mensaje de confirmación
header('Location: ../login.php?logout=1&success=' . urlencode('Sesión cerrada exitosamente'));
exit;
?>