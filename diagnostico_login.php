<?php
/**
 * DIAGNÓSTICO COMPLETO DEL SISTEMA DE LOGIN
 * Archivo: diagnostico_login.php
 * 
 * INSTRUCCIONES:
 * 1. Guarda este archivo en la raíz del proyecto
 * 2. Abre en navegador: http://localhost/tu-proyecto/diagnostico_login.php
 * 3. Revisa todos los resultados
 * 4. ELIMINA este archivo después de usarlo (contiene información sensible)
 */

echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.box { background: white; padding: 20px; margin: 10px 0; border-radius: 10px; border-left: 5px solid #007bff; }
.success { border-left-color: #28a745; background: #f8fff8; }
.error { border-left-color: #dc3545; background: #fff8f8; }
.warning { border-left-color: #ffc107; background: #fffef8; }
.info { border-left-color: #17a2b8; background: #f8fdff; }
pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
.test-section { margin: 20px 0; padding: 20px; border: 2px solid #dee2e6; border-radius: 10px; }
</style>";

echo "<h1>🔍 Diagnóstico Completo del Sistema de Login</h1>";

// ===========================================
// 1. VERIFICAR ARCHIVOS Y CONFIGURACIÓN
// ===========================================
echo "<div class='test-section'>";
echo "<h2>1. 📁 Verificación de Archivos</h2>";

$archivos_necesarios = [
    'includes/config.php' => 'Configuración principal',
    'includes/conexion.php' => 'Conexión a base de datos',
    'includes/auth.php' => 'Sistema de autenticación',
    'login.php' => 'Página de login',
    'procesar/procesar_login.php' => 'Procesador de login'
];

foreach ($archivos_necesarios as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "<div class='box success'>✅ $archivo - $descripcion</div>";
    } else {
        echo "<div class='box error'>❌ $archivo - $descripcion (FALTANTE)</div>";
    }
}
echo "</div>";

// ===========================================
// 2. VERIFICAR CONFIGURACIÓN
// ===========================================
echo "<div class='test-section'>";
echo "<h2>2. ⚙️ Verificación de Configuración</h2>";

try {
    include_once 'includes/config.php';
    
    echo "<div class='box info'>";
    echo "<h3>Configuración cargada:</h3>";
    echo "<ul>";
    echo "<li><strong>DB_HOST:</strong> " . (defined('DB_HOST') ? DB_HOST : 'NO DEFINIDO') . "</li>";
    echo "<li><strong>DB_NAME:</strong> " . (defined('DB_NAME') ? DB_NAME : 'NO DEFINIDO') . "</li>";
    echo "<li><strong>DB_USER:</strong> " . (defined('DB_USER') ? DB_USER : 'NO DEFINIDO') . "</li>";
    echo "<li><strong>DB_PASS:</strong> " . (defined('DB_PASS') ? (DB_PASS ? '[DEFINIDA]' : '[VACÍA]') : 'NO DEFINIDO') . "</li>";
    echo "<li><strong>DEBUG_MODE:</strong> " . (defined('DEBUG_MODE') ? (DEBUG_MODE ? 'true' : 'false') : 'NO DEFINIDO') . "</li>";
    echo "</ul>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='box error'>❌ Error cargando configuración: " . $e->getMessage() . "</div>";
}
echo "</div>";

// ===========================================
// 3. VERIFICAR CONEXIÓN A BASE DE DATOS
// ===========================================
echo "<div class='test-section'>";
echo "<h2>3. 🔗 Verificación de Conexión a BD</h2>";

try {
    include_once 'includes/conexion.php';
    $conn = ConexionBD();
    
    if ($conn) {
        echo "<div class='box success'>✅ Conexión a base de datos exitosa</div>";
        
        // Probar una consulta simple
        $test_query = $conn->query("SELECT NOW() as fecha_actual");
        $result = $test_query->fetch();
        echo "<div class='box info'>🕒 Fecha/hora del servidor MySQL: " . $result['fecha_actual'] . "</div>";
        
    } else {
        echo "<div class='box error'>❌ No se pudo conectar a la base de datos</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='box error'>❌ Error de conexión: " . $e->getMessage() . "</div>";
}
echo "</div>";

// ===========================================
// 4. VERIFICAR TABLAS NECESARIAS
// ===========================================
if (isset($conn) && $conn) {
    echo "<div class='test-section'>";
    echo "<h2>4. 🗃️ Verificación de Tablas</h2>";
    
    $tablas_necesarias = ['usuarios', 'sesiones_usuario'];
    
    foreach ($tablas_necesarias as $tabla) {
        try {
            $result = $conn->query("SHOW TABLES LIKE '$tabla'");
            if ($result->rowCount() > 0) {
                echo "<div class='box success'>✅ Tabla '$tabla' existe</div>";
                
                // Mostrar estructura de la tabla
                $desc = $conn->query("DESCRIBE $tabla");
                echo "<div class='box info'>";
                echo "<h4>Estructura de $tabla:</h4>";
                echo "<pre>";
                while ($row = $desc->fetch()) {
                    echo "{$row['Field']} | {$row['Type']} | {$row['Null']} | {$row['Key']} | {$row['Default']}\n";
                }
                echo "</pre>";
                echo "</div>";
                
            } else {
                echo "<div class='box error'>❌ Tabla '$tabla' NO existe</div>";
            }
        } catch (Exception $e) {
            echo "<div class='box error'>❌ Error verificando tabla '$tabla': " . $e->getMessage() . "</div>";
        }
    }
    echo "</div>";
}

// ===========================================
// 5. VERIFICAR USUARIO ADMIN
// ===========================================
if (isset($conn) && $conn) {
    echo "<div class='test-section'>";
    echo "<h2>5. 👤 Verificación del Usuario Admin</h2>";
    
    try {
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = 'admin'");
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            echo "<div class='box success'>✅ Usuario 'admin' encontrado</div>";
            echo "<div class='box info'>";
            echo "<h4>Datos del usuario admin:</h4>";
            echo "<ul>";
            echo "<li><strong>ID:</strong> " . $usuario['id_usuario'] . "</li>";
            echo "<li><strong>Usuario:</strong> " . $usuario['usuario'] . "</li>";
            echo "<li><strong>Nombre:</strong> " . $usuario['nombre_completo'] . "</li>";
            echo "<li><strong>Rol:</strong> " . $usuario['rol'] . "</li>";
            echo "<li><strong>Activo:</strong> " . ($usuario['activo'] ? 'SÍ' : 'NO') . "</li>";
            echo "<li><strong>Hash de contraseña:</strong> " . substr($usuario['password'], 0, 20) . "...</li>";
            echo "</ul>";
            echo "</div>";
            
            // Test de la contraseña
            echo "<div class='box warning'>";
            echo "<h4>🔐 Test de Contraseña:</h4>";
            $password_test = 'admin123';
            $verify_result = password_verify($password_test, $usuario['password']);
            
            if ($verify_result) {
                echo "<p style='color: green; font-weight: bold;'>✅ La contraseña 'admin123' ES CORRECTA para este hash</p>";
            } else {
                echo "<p style='color: red; font-weight: bold;'>❌ La contraseña 'admin123' NO COINCIDE con este hash</p>";
                echo "<p>Esto significa que necesitas actualizar la contraseña.</p>";
            }
            echo "</div>";
            
        } else {
            echo "<div class='box error'>❌ Usuario 'admin' NO encontrado en la base de datos</div>";
            echo "<div class='box warning'>";
            echo "<h4>Solución: Ejecuta este SQL</h4>";
            echo "<pre>";
            echo "INSERT INTO usuarios (usuario, password, nombre_completo, rol, activo) \n";
            echo "VALUES ('admin', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador del Sistema', 'admin', 1);";
            echo "</pre>";
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='box error'>❌ Error consultando usuario: " . $e->getMessage() . "</div>";
    }
    echo "</div>";
}

// ===========================================
// 6. TEST COMPLETO DE LOGIN
// ===========================================
if (isset($conn) && $conn) {
    echo "<div class='test-section'>";
    echo "<h2>6. 🧪 Test Completo de Login</h2>";
    
    try {
        $usuario_test = 'admin';
        $password_test = 'admin123';
        
        echo "<div class='box info'>";
        echo "<h4>Simulando proceso de login...</h4>";
        echo "<p><strong>Usuario probado:</strong> $usuario_test</p>";
        echo "<p><strong>Contraseña probada:</strong> $password_test</p>";
        echo "</div>";
        
        // Paso 1: Buscar usuario
        $query = "SELECT id_usuario, usuario, password, nombre_completo, rol, email, activo 
                  FROM usuarios 
                  WHERE usuario = :usuario AND activo = 1";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([':usuario' => $usuario_test]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_data) {
            echo "<div class='box error'>❌ FALLO EN PASO 1: Usuario no encontrado o inactivo</div>";
        } else {
            echo "<div class='box success'>✅ PASO 1 OK: Usuario encontrado</div>";
            
            // Paso 2: Verificar contraseña
            if (!password_verify($password_test, $user_data['password'])) {
                echo "<div class='box error'>❌ FALLO EN PASO 2: Contraseña incorrecta</div>";
                echo "<div class='box warning'>";
                echo "<h4>🔧 Solución: Actualiza la contraseña</h4>";
                $new_hash = password_hash($password_test, PASSWORD_DEFAULT);
                echo "<pre>UPDATE usuarios SET password = '$new_hash' WHERE usuario = 'admin';</pre>";
                echo "</div>";
            } else {
                echo "<div class='box success'>✅ PASO 2 OK: Contraseña verificada correctamente</div>";
                echo "<div class='box success'>";
                echo "<h4>🎉 ¡LOGIN DEBERÍA FUNCIONAR!</h4>";
                echo "<p>Si aún no funciona, el problema está en el procesamiento del formulario.</p>";
                echo "</div>";
            }
        }
        
    } catch (Exception $e) {
        echo "<div class='box error'>❌ Error en test de login: " . $e->getMessage() . "</div>";
    }
    echo "</div>";
}

// ===========================================
// 7. VERIFICAR PROCESADOR DE LOGIN
// ===========================================
echo "<div class='test-section'>";
echo "<h2>7. 🔄 Verificación del Procesador de Login</h2>";

if (file_exists('procesar/procesar_login.php')) {
    echo "<div class='box success'>✅ Archivo procesar_login.php existe</div>";
    
    // Verificar que el formulario apunte al lugar correcto
    if (file_exists('login.php')) {
        $login_content = file_get_contents('login.php');
        if (strpos($login_content, 'procesar/procesar_login.php') !== false) {
            echo "<div class='box success'>✅ Formulario apunta correctamente a procesar/procesar_login.php</div>";
        } else {
            echo "<div class='box error'>❌ El formulario no apunta a procesar/procesar_login.php</div>";
        }
    }
} else {
    echo "<div class='box error'>❌ Archivo procesar/procesar_login.php NO existe</div>";
}
echo "</div>";

// ===========================================
// 8. GENERAR HASH ACTUALIZADO
// ===========================================
echo "<div class='test-section'>";
echo "<h2>8. 🔐 Generador de Hash Actualizado</h2>";

$password_correcta = 'admin123';
$hash_nuevo = password_hash($password_correcta, PASSWORD_DEFAULT);

echo "<div class='box warning'>";
echo "<h4>Si nada más funciona, ejecuta este SQL:</h4>";
echo "<pre>";
echo "-- Borrar usuario admin existente y crear uno nuevo\n";
echo "DELETE FROM usuarios WHERE usuario = 'admin';\n\n";
echo "-- Insertar usuario admin con hash nuevo\n";
echo "INSERT INTO usuarios (usuario, email, password, nombre_completo, rol, activo) \n";
echo "VALUES (\n";
echo "    'admin',\n";
echo "    'admin@sistema.com',\n";
echo "    '$hash_nuevo',\n";
echo "    'Administrador del Sistema',\n";
echo "    'admin',\n";
echo "    1\n";
echo ");\n\n";
echo "-- Verificar\n";
echo "SELECT usuario, nombre_completo, rol, activo FROM usuarios WHERE usuario = 'admin';";
echo "</pre>";
echo "</div>";
echo "</div>";

// ===========================================
// RESUMEN FINAL
// ===========================================
echo "<div class='test-section'>";
echo "<h2>📋 Resumen y Próximos Pasos</h2>";

echo "<div class='box info'>";
echo "<h4>🎯 Credenciales a usar:</h4>";
echo "<ul>";
echo "<li><strong>Usuario:</strong> admin</li>";
echo "<li><strong>Contraseña:</strong> admin123</li>";
echo "</ul>";
echo "</div>";

echo "<div class='box warning'>";
echo "<h4>⚠️ IMPORTANTE:</h4>";
echo "<ul>";
echo "<li>ELIMINA este archivo después de usarlo (contiene información sensible)</li>";
echo "<li>Si el problema persiste, copia TODOS los resultados de esta página</li>";
echo "<li>Verifica que tu servidor web (Apache/Nginx) esté funcionando</li>";
echo "<li>Asegúrate de que PHP esté correctamente configurado</li>";
echo "</ul>";
echo "</div>";
echo "</div>";

echo "<hr><p style='text-align: center; color: #666;'>Diagnóstico completado - " . date('Y-m-d H:i:s') . "</p>";
?>