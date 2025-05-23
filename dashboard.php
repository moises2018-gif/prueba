<?php
session_start();

if (!isset($_SESSION['username'])) {
    // Si no hay sesión iniciada, redirigir al login
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f9; }
        .header { background: #333; color: white; padding: 10px; text-align: center; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; background: white; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="header">
        Bienvenido al Panel de Administración
    </div>
    <div class="container">
        <h1>Hola, <?php echo $_SESSION['username']; ?> (Administrador)</h1>
        <p>Esta es la página exclusiva para administradores.</p>
        <a href="logout.php">Cerrar sesión</a>
    </div>
</body>
</html>