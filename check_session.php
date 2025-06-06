<?php
/**
 * check_session.php - Verificador de estado de sesión
 * 
 * Este archivo verifica si la sesión del usuario está activa
 * y proporciona información sobre el estado de la sesión
 */

// Iniciar sesión
session_start();

// Configurar header para JSON
header('Content-Type: application/json');

// Función para verificar si la sesión es válida
function verificarSesionValida() {
    // Verificar si existe una sesión activa
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    
    // Verificar tiempo de última actividad (opcional)
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        $session_timeout = 5 * 60; // 5 minutos en segundos
        
        if ($inactive_time > $session_timeout) {
            return false;
        }
    }
    
    // Verificar IP del usuario (seguridad adicional - opcional)
    if (isset($_SESSION['user_ip'])) {
        $current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($_SESSION['user_ip'] !== $current_ip) {
            // Posible hijacking de sesión
            return false;
        }
    }
    
    return true;
}

// Función para actualizar la actividad de la sesión
function actualizarActividadSesion() {
    $_SESSION['last_activity'] = time();
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
}

// Función para obtener información de la sesión
function obtenerInfoSesion() {
    $info = [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'user_role' => $_SESSION['user_role'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
    ];
    
    // Calcular tiempo de sesión activa
    if (isset($_SESSION['login_time'])) {
        $info['session_duration'] = time() - $_SESSION['login_time'];
    }
    
    // Calcular tiempo desde última actividad
    if (isset($_SESSION['last_activity'])) {
        $info['time_since_activity'] = time() - $_SESSION['last_activity'];
    }
    
    return $info;
}

// Procesar la solicitud
try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // Verificar estado de sesión
        $session_active = verificarSesionValida();
        
        if ($session_active) {
            // Actualizar última actividad
            actualizarActividadSesion();
            
            // Obtener información de sesión
            $session_info = obtenerInfoSesion();
            
            echo json_encode([
                'success' => true,
                'active' => true,
                'message' => 'Sesión activa',
                'session_info' => $session_info,
                'server_time' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Sesión no válida - limpiar sesión
            session_unset();
            session_destroy();
            
            echo json_encode([
                'success' => true,
                'active' => false,
                'message' => 'Sesión inactiva o expirada',
                'server_time' => date('Y-m-d H:i:s')
            ]);
        }
    } else {
        // Método no permitido
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => 'Método no permitido',
            'allowed_methods' => ['POST']
        ]);
    }
    
} catch (Exception $e) {
    // Error en el servidor
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ]);
    
    // Registrar error en log
    error_log("Error en check_session.php: " . $e->getMessage(), 3, '../logs/error.log');
}

exit();
?>