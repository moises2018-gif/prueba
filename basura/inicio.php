<?php
session_start();

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); // Redirigir a login.php si no ha iniciado sesión
    exit();
}

// Incluir la clase de conexión
require '../php/conexion.php';

// Clase para manejar los datos de los docentes
class Docentes {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerDocentes() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("SELECT id, id_usuario, nombre, experiencia, especialidad, formacion_nee FROM docentes");
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

// Clase para manejar los datos de los estudiantes
class Estudiantes {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerEstudiantes() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("SELECT id, id_usuario, nombre, discapacidad, nivel, porcentaje FROM estudiantes");
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inicio - Sistema de Asignación</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../css/styles1.css"> 
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Navbar -->
        <nav class="col-12 navbar">
            <div class="d-flex align-items-center">
                <h4 class="mb-0">Sistema de Asignación</h4>
            </div>
            <div>
                <a href="../php/logout.php" class="btn logout-btn">Cerrar Sesión</a>
            </div>
        </nav>

        <!-- Sidebar -->
        <nav class="col-md-2 d-flex flex-column sidebar">
            <h4 class="mb-4">UG</h4>
            <div class="mb-3">
                <strong>Administrador</strong>
            </div>
            <a href="" class="nav-link">Inicio</a>
            <div class="menu">
                <button class="nav-link">Estudiantes</button>
                <div class="submenu">
                    <a href="estudiantes.php">Visualización</a>
                    <a href="asignacion.php">Asignación</a>
                </div>
            </div>
            <a href="docentes.php" class="nav-link">Docentes</a>
            <a href="#" class="nav-link">Consultas Generales</a>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Sistema de Asignación</h2>
            </div>

            <div class="bg-primary text-white p-4 rounded mb-4">
                <h3 class="text-center">Sistema de Asignación UG</h3>
            </div>

            <div class="card w-50">
                <!-- Additional content can be added here -->
            </div>
        </main>
    </div>
</div>

</body>
</html>