<?php
session_start();
session_unset(); // Elimina todas las variables de sesión
session_destroy(); // Destruye la sesión actual

// Redirigir al usuario a la página de login
header("Location: ../login.php");
exit();
?>