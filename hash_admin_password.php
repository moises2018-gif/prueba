<?php
require 'php/conexion.php';

$conn = new Cconexion();
$db = $conn->ConexionBD();

if ($db) {
    try {
        // Datos del usuario administrador
        $username = 'administrador';
        $password = 'zxcvbnm';

        // Hashear la contraseña
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // Actualizar la contraseña hasheada en la base de datos
        $stmt = $db->prepare("UPDATE usuarios SET password = :password WHERE nombre = :nombre");
        $stmt->execute([
            'password' => $passwordHash,
            'nombre' => $username
        ]);

        echo "Contraseña del usuario $username ha sido hasheada correctamente.";
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "Error de conexión a la base de datos.";
}
?>