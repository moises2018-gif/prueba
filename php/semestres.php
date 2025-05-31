<?php
require 'conexion.php';

class Semestres {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerSemestres() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT id_semestre, nombre
                    FROM semestres
                ");
                $stmt->execute();
                $semestres = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $semestres;
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

$semestres = new Semestres();
$semestresData = $semestres->obtenerSemestres();

header('Content-Type: application/json');
echo json_encode($semestresData);
?>