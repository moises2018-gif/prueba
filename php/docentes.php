<?php
require 'conexion.php';

class Docentes {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerDocentes() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT d.id_docente, d.nombres, d.carrera, 
                           e.tipo_discapacidad, e.años_experiencia
                    FROM docentes d
                    LEFT JOIN experienciadocente e ON d.id_docente = e.id_docente
                ");
                $stmt->execute();
                $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $docentes;
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

$docentes = new Docentes();
$docentesData = $docentes->obtenerDocentes();

header('Content-Type: application/json');
echo json_encode($docentesData);
?>