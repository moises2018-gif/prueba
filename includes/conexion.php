<?php
function ConexionBD() {
    $host = 'localhost';
    $dbname = 'asignacion_docente';
    $username = 'moises';
    $password = 'moises';
    $puerto = 3306;

    try {
        $conn = new PDO("mysql:host=$host;port=$puerto;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch (PDOException $e) {
        echo "<div class='alert alert-error'>Error de conexión: " . $e->getMessage() . "</div>";
        return null;
    }
}
?>