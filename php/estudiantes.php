<?php
require 'conexion.php';

class Estudiantes {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerEstudiantes() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT id_estudiante, nombres, discapacidad, porcentaje_discapacidad, nivel_academico, no_vez, creditos, fecha_registro
                    FROM estudiantes
                ");
                $stmt->execute();
                $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $estudiantes;
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

$estudiantes = new Estudiantes();
$estudiantesData = $estudiantes->obtenerEstudiantes();

header('Content-Type: application/json');
echo json_encode($estudiantesData);
?>