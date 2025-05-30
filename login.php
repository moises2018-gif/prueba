<?php
session_start();
require 'php/conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $conn = new Cconexion();
    $db = $conn->ConexionBD();

    if ($db) {
        try {
            // Preparar la consulta SQL para buscar el usuario por nombre
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE nombre = :nombre");
            $stmt->execute(['nombre' => $username]);
            $user = $stmt->fetch();

            if ($user) {
                // Verificar la contraseña hasheada
                if (password_verify($password, $user['password'])) {
                    // Verificar el rol
                    if ($user['rol'] == 'admin') {
                        // Iniciar sesión
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['nombre'];
                        $_SESSION['rol'] = $user['rol'];
                        
                        // Regenerar el ID de sesión para mayor seguridad
                        session_regenerate_id(true);

                        // Redirigir al dashboard
                        header("Location: inicio/inicio.php");
                        exit();
                    } else {
                        $error = "Rol incorrecto. Solo los administradores pueden iniciar sesión.";
                    }
                } else {
                    $error = "Contraseña incorrecta.";
                }
            } else {
                $error = "Nombre de usuario incorrecto.";
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Error de conexión a la base de datos.";
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
        if (!empty($error)) {
            echo "<div style='color: red; text-align: center; margin-top: 20px;'>$error</div>";
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