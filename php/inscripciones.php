<?php
require 'conexion.php';

class Inscripciones {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerInscripciones() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT id_inscripcion, id_estudiante, id_curso, fecha_inscripcion
                    FROM inscripciones
                ");
                $stmt->execute();
                $inscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $inscripciones;
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

$inscripciones = new Inscripciones();
$inscripcionesData = $inscripciones->obtenerInscripciones();

header('Content-Type: application/json');
echo json_encode($inscripcionesData);
?>