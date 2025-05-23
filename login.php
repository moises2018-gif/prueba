<?php
session_start();
require 'php/conexion.php'; // Asegúrate de que la ruta sea correcta

$error = ''; // Variable para almacenar mensajes de error

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $conn = new Cconexion();
    $db = $conn->ConexionBD();

    if ($db) {
        try {
            // Preparar la consulta SQL para buscar el usuario por nombre y verificar la contraseña y el rol
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE nombre = :nombre AND password = :password AND rol = 'admin'");
            $stmt->execute([
                'nombre' => $username,
                'password' => $password
            ]);
            $user = $stmt->fetch();

            if ($user) {
                // Iniciar sesión
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['nombre'];
                $_SESSION['rol'] = $user['rol'];
                
                // Redirigir al dashboard
                header("Location: estudiantes/estudiantes.php");
                exit();
            } else {
                // Mostrar mensaje de error si las credenciales son incorrectas o el rol no es admin
                $error = "<div style='color: red; text-align: center; margin-top: 20px;'>Nombre de usuario, contraseña o rol incorrectos.</div>";
            }
        } catch (PDOException $e) {
            // Mostrar mensaje de error si hay un problema con la consulta
            $error = "<div style='color: red; text-align: center; margin-top: 20px;'>Error: " . $e->getMessage() . "</div>";
        }
    } else {
        // Mostrar mensaje de error si no se puede conectar a la base de datos
        $error = "<div style='color: red; text-align: center; margin-top: 20px;'>Error de conexión a la base de datos.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="header">
        Sistema de Asignación para Estudiantes con NEE
    </div>
    <div class="login-container">
        <img src="img/logo.png" alt="Logo">
        <h2>Login</h2>
        
        <?php 
        // Mostrar errores si existen
        if (!empty($error)) {
            echo $error;
        }
        ?>
        
        <form action="" method="POST">
            <input type="text" name="username" placeholder="usuario" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <input type="submit" value="Login">
        </form>
    </div>
</body>
</html>