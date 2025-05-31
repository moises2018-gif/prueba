<?php
require 'conexion.php';

class Materias {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerMaterias() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT id_materia, nombre_materia, creditos
                    FROM materias
                ");
                $stmt->execute();
                $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $materias;
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

$materias = new Materias();
$materiasData = $materias->obtenerMaterias();

header('Content-Type: application/json');
echo json_encode($materiasData);
?>