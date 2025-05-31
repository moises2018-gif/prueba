<?php
require 'conexion.php';

class Turnos {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerTurnos() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT id_turno, nombre_turno
                    FROM turnos
                ");
                $stmt->execute();
                $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $turnos;
            } catch (PDOException $e) {
                echo "Error al obtener los datos: " . $e->getMessage();
                return [];
            }
        } else {
            echo "Error de conexión a la base de datos.";
            return [];
        }
    }
}

$turnos = new Turnos();
$turnosData = $turnos->obtenerTurnos();

header('Content-Type: application/json');
echo json_encode($turnosData);
?>