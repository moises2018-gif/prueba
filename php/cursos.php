<?php
require 'conexion.php';

class Cursos {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerCursos() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT id_curso, id_materia, id_docente, cupo
                    FROM cursos
                ");
                $stmt->execute();
                $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $cursos;
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

$cursos = new Cursos();
$cursosData = $cursos->obtenerCursos();

header('Content-Type: application/json');
echo json_encode($cursosData);
?>