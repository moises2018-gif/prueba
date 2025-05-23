<?php

class Cconexion {

    function ConexionBD() {
        $host = 'localhost'; // Dirección del servidor MySQL
        $dbname = 'asignacion_docente'; // Nombre de la base de datos
        $username = 'moises'; // Usuario de la base de datos
        $password = 'moises'; // Contraseña del usuario
        $puerto = 3306; // Puerto predeterminado de MySQL

        $conn = null; // Inicializa $conn

        try {
            // DSN para MySQL
            $dsn = "mysql:host=$host;port=$puerto;dbname=$dbname;charset=utf8";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            // Conectar con el usuario y la contraseña
            $conn = new PDO($dsn, $username, $password, $options);
            // echo "Se conectó correctamente a la base de datos";
        } catch (PDOException $exp) {
            // echo "No se logró conectar correctamente con la base de datos: $dbname, error: " . $exp->getMessage();
        }

        return $conn; // Retorna $conn
    }
}
?>