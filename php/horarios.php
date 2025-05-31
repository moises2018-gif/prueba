<?php
require 'conexion.php';

class Horarios {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerHorarios() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT id_horario, id_curso, id_turno, dia_semana, hora_inicio, hora_fin
                    FROM horarios
                ");
                $stmt->execute();
                $horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $horarios;
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

$horarios = new Horarios();
$horariosData = $horarios->obtenerHorarios();

header('Content-Type: application/json');
echo json_encode($horariosData);
?>