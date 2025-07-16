<?php
/**
 * GENERADOR DE HASH PARA USUARIO ADMIN
 * Archivo: generar_hash_admin.php
 * 
 * INSTRUCCIONES:
 * 1. Guarda este archivo en la raíz del proyecto
 * 2. Abre en el navegador: http://localhost/tu-proyecto/generar_hash_admin.php
 * 3. Copia el SQL generado y ejecútalo en phpMyAdmin
 * 4. Elimina este archivo después de usarlo
 */

echo "<h1>🔐 Generador de Hash para Admin</h1>";

// Contraseña que vamos a hashear
$password = 'admin123';

// Generar hash con PHP 7+
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<div style='background: #f0f8ff; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h2>📋 Información del Usuario Admin</h2>";
echo "<p><strong>Usuario:</strong> admin</p>";
echo "<p><strong>Contraseña:</strong> admin123</p>";
echo "<p><strong>Hash generado:</strong></p>";
echo "<textarea style='width: 100%; height: 100px; font-family: monospace;'>$hash</textarea>";
echo "</div>";

echo "<div style='background: #f0fff0; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h2>🔧 SQL para Actualizar/Crear Usuario</h2>";
echo "<textarea style='width: 100%; height: 200px; font-family: monospace;'>";
echo "-- Opción 1: Actualizar usuario existente\n";
echo "UPDATE usuarios \n";
echo "SET password = '$hash' \n";
echo "WHERE usuario = 'admin';\n\n";

echo "-- Opción 2: Crear usuario si no existe\n";
echo "INSERT INTO usuarios (usuario, email, password, nombre_completo, rol, activo) \n";
echo "VALUES (\n";
echo "    'admin', \n";
echo "    'admin@sistema.com', \n";
echo "    '$hash', \n";
echo "    'Administrador del Sistema', \n";
echo "    'admin', \n";
echo "    1\n";
echo ") ON DUPLICATE KEY UPDATE \n";
echo "    password = '$hash',\n";
echo "    activo = 1;\n\n";

echo "-- Verificar que funciona\n";
echo "SELECT usuario, nombre_completo, rol, activo FROM usuarios WHERE usuario = 'admin';";
echo "</textarea>";
echo "</div>";

echo "<div style='background: #fff5f5; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h2>✅ Test de Verificación</h2>";
$test_verify = password_verify($password, $hash);
echo "<p>Test de password_verify('$password', hash): " . ($test_verify ? "✅ CORRECTO" : "❌ ERROR") . "</p>";
echo "</div>";

echo "<div style='background: #fffbf0; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h2>🚀 Pasos a Seguir</h2>";
echo "<ol>";
echo "<li>Copia el SQL de arriba</li>";
echo "<li>Ve a phpMyAdmin → Base de datos 'asignacion_docente' → SQL</li>";
echo "<li>Pega y ejecuta el SQL</li>";
echo "<li>Ve al login e intenta: <strong>admin / admin123</strong></li>";
echo "<li><strong>Elimina este archivo después de usarlo</strong></li>";
echo "</ol>";
echo "</div>";

// Test de conexión a BD
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
echo "<h2>🔗 Test de Conexión a BD</h2>";

include_once 'includes/config.php';
include_once 'includes/conexion.php';

try {
    $conn = ConexionBD();
    if ($conn) {
        echo "<p style='color: green;'>✅ Conexión a BD exitosa</p>";
        
        // Verificar si existe la tabla usuarios
        $result = $conn->query("SHOW TABLES LIKE 'usuarios'");
        if ($result->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Tabla 'usuarios' existe</p>";
            
            // Verificar si existe el usuario admin
            $stmt = $conn->prepare("SELECT COUNT(*) FROM usuarios WHERE usuario = 'admin'");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                echo "<p style='color: orange;'>⚠️ Usuario 'admin' ya existe - se actualizará la contraseña</p>";
            } else {
                echo "<p style='color: blue;'>ℹ️ Usuario 'admin' no existe - se creará</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Tabla 'usuarios' NO existe - ejecuta el SQL del primer artifact</p>";
        }
        
        // Verificar tabla sesiones_usuario
        $result2 = $conn->query("SHOW TABLES LIKE 'sesiones_usuario'");
        if ($result2->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Tabla 'sesiones_usuario' existe</p>";
        } else {
            echo "<p style='color: red;'>❌ Tabla 'sesiones_usuario' NO existe - ejecuta el SQL del primer artifact</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ No se pudo conectar a la BD</p>";
        echo "<p>Verifica tu configuración en includes/config.php:</p>";
        echo "<ul>";
        echo "<li>DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'No definido') . "</li>";
        echo "<li>DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'No definido') . "</li>";
        echo "<li>DB_USER: " . (defined('DB_USER') ? DB_USER : 'No definido') . "</li>";
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }";
echo "textarea { border: 1px solid #ddd; padding: 10px; border-radius: 5px; }";
echo "h1, h2 { color: #333; }";
echo "li { margin: 5px 0; }";
echo "</style>";
?>