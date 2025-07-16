<?php
/**
 * GENERADOR DE HASH PARA CONTRASEÑA
 * Archivo: generar_hash.php (crear en la raíz del proyecto)
 * 
 * Este script genera el hash correcto para la contraseña admin123
 */

echo "<h1>🔐 Generador de Hash para Contraseña</h1>";

// Contraseña que queremos hashear
$password = 'admin123';

// Generar hash con PHP
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h2>📋 Resultado:</h2>";
echo "<p><strong>Contraseña original:</strong> $password</p>";
echo "<p><strong>Hash generado:</strong></p>";
echo "<textarea style='width: 100%; height: 100px; font-family: monospace;'>$hash</textarea>";

echo "<h2>🔧 SQL para actualizar la base de datos:</h2>";
echo "<textarea style='width: 100%; height: 150px; font-family: monospace;'>";
echo "-- Actualizar el usuario admin con el hash correcto\n";
echo "UPDATE usuarios \n";
echo "SET password = '$hash' \n";
echo "WHERE usuario = 'admin';\n\n";
echo "-- Verificar que se actualizó correctamente\n";
echo "SELECT usuario, nombre_completo, rol, activo \n";
echo "FROM usuarios \n";
echo "WHERE usuario = 'admin';";
echo "</textarea>";

echo "<h2>✅ Test de verificación:</h2>";
$test_verify = password_verify($password, $hash);
echo "<p>Test de password_verify('$password', hash): " . ($test_verify ? "✅ CORRECTO" : "❌ ERROR") . "</p>";

echo "<h2>🚀 Instrucciones:</h2>";
echo "<ol>";
echo "<li>Copia el SQL de arriba</li>";
echo "<li>Ejecutalo en phpMyAdmin</li>";
echo "<li>Intenta el login nuevamente</li>";
echo "<li>Elimina este archivo después</li>";
echo "</ol>";

// Información adicional
echo "<h2>📊 Información del Sistema:</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Password Hash Algorithm: " . PASSWORD_DEFAULT . "</p>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2 { color: #333; }
textarea { border: 1px solid #ddd; padding: 10px; border-radius: 5px; }
ol li { margin: 5px 0; }
</style>