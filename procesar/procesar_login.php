<?php
/**
 * PROCESADOR DE LOGIN DEL SISTEMA AHP
 * Archivo: procesar/procesar_login.php
 */

session_start();

include '../includes/config.php';
include '../includes/conexion.php';

// Función para generar token seguro
function generarTokenSeguro($longitud = 64) {
    return bin2hex(random_bytes($longitud / 2));
}

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

// Verificar que sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php?error=' . urlencode('Método de acceso no válido'));
    exit;
}

// Obtener datos del formulario
$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';

// Validaciones básicas
if (empty($usuario) || empty($password)) {
    header('Location: ../login.php?error=' . urlencode('Usuario y contraseña son requeridos') . '&usuario=' . urlencode($usuario));
    exit;
}

if (strlen($usuario) < 3) {
    header('Location: ../login.php?error=' . urlencode('El usuario debe tener al menos 3 caracteres') . '&usuario=' . urlencode($usuario));
    exit;
}

if (strlen($password) < 6) {
    header('Location: ../login.php?error=' . urlencode('La contraseña debe tener al menos 6 caracteres') . '&usuario=' . urlencode($usuario));
    exit;
}

try {
    $conn = ConexionBD();
    
    if (!$conn) {
        throw new Exception('No se pudo conectar a la base de datos');
    }
    
    // Buscar usuario en la base de datos
    $query = "SELECT id_usuario, usuario, password, nombre_completo, rol, email, activo 
              FROM usuarios 
              WHERE usuario = :usuario AND activo = 1";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([':usuario' => $usuario]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si el usuario existe
    if (!$user_data) {
        // Log del intento fallido (sin revelar si el usuario existe o no)
        error_log("Intento de login fallido - Usuario: $usuario - IP: " . obtenerIPCliente());
        header('Location: ../login.php?error=' . urlencode('Usuario o contraseña incorrectos') . '&usuario=' . urlencode($usuario));
        exit;
    }
    
    // Verificar la contraseña
    if (!password_verify($password, $user_data['password'])) {
        // Log del intento fallido
        error_log("Intento de login fallido - Usuario válido pero contraseña incorrecta: $usuario - IP: " . obtenerIPCliente());
        header('Location: ../login.php?error=' . urlencode('Usuario o contraseña incorrectos') . '&usuario=' . urlencode($usuario));
        exit;
    }
    
    // Login exitoso - Crear sesión
    $token_sesion = generarTokenSeguro();
    $ip_cliente = obtenerIPCliente();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Desconocido';
    
    // Limpiar sesiones anteriores del usuario (opcional)
    $query_limpiar = "UPDATE sesiones_usuario 
                      SET activa = 0 
                      WHERE id_usuario = :id_usuario AND fecha_expiracion < NOW()";
    $stmt_limpiar = $conn->prepare($query_limpiar);
    $stmt_limpiar->execute([':id_usuario' => $user_data['id_usuario']]);
    
    // Crear nueva sesión en la base de datos
    $query_sesion = "INSERT INTO sesiones_usuario 
                     (id_usuario, token_sesion, ip_address, user_agent, fecha_expiracion, activa) 
                     VALUES 
                     (:id_usuario, :token, :ip, :user_agent, DATE_ADD(NOW(), INTERVAL 24 HOUR), 1)";
    
    $stmt_sesion = $conn->prepare($query_sesion);
    $stmt_sesion->execute([
        ':id_usuario' => $user_data['id_usuario'],
        ':token' => $token_sesion,
        ':ip' => $ip_cliente,
        ':user_agent' => $user_agent
    ]);
    
    // Actualizar último acceso
    $query_acceso = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id_usuario = :id_usuario";
    $stmt_acceso = $conn->prepare($query_acceso);
    $stmt_acceso->execute([':id_usuario' => $user_data['id_usuario']]);
    
    // Crear variables de sesión
    $_SESSION['usuario_id'] = $user_data['id_usuario'];
    $_SESSION['usuario'] = $user_data['usuario'];
    $_SESSION['nombre_completo'] = $user_data['nombre_completo'];
    $_SESSION['rol'] = $user_data['rol'];
    $_SESSION['email'] = $user_data['email'];
    $_SESSION['token_sesion'] = $token_sesion;
    $_SESSION['login_time'] = time();
    $_SESSION['ip_login'] = $ip_cliente;
    
    // Log del login exitoso
    error_log("Login exitoso - Usuario: {$user_data['usuario']} ({$user_data['nombre_completo']}) - Rol: {$user_data['rol']} - IP: $ip_cliente");
    
    // Regenerar ID de sesión por seguridad
    session_regenerate_id(true);
    
    // Redirigir al dashboard
    header('Location: ../pages/dashboard.php?success=' . urlencode('Bienvenido al Sistema AHP, ' . $user_data['nombre_completo']));
    exit;
    
} catch (PDOException $e) {
    error_log("Error de base de datos en login: " . $e->getMessage());
    header('Location: ../login.php?error=' . urlencode('Error interno del sistema. Intente nuevamente.'));
    exit;
    
} catch (Exception $e) {
    error_log("Error general en login: " . $e->getMessage());
    header('Location: ../login.php?error=' . urlencode('Error del sistema. Contacte al administrador.'));
    exit;
}
?>