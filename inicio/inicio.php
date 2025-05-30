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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .sidebar {
            height: 100vh;
            background-color: #1e1e2f;
            color: white;
            padding: 1rem;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            margin-bottom: 1.5rem;
            padding: 0.75rem 0;
        }
        .sidebar a:hover {
            background-color: #343a40;
            padding: 0.75rem;
            border-radius: 5px;
        }
        .social-icon {
            width: 20px;
        }
        .logout-btn {
            background-color: #f94f6d;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
        }
        .navbar {
            background-color: #3498db;
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .menu-button {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .menu-button:hover {
            background-color: #2980b9;
        }
        .submenu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background-color: white;
            min-width: 160px;
            box-shadow: 0px 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1;
            border-radius: 5px;
        }
        .submenu a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
        }
        .submenu a:hover {
            background-color: #ddd;
        }
        .menu:hover .submenu {
            display: block;
        }
        .mainContent {
            padding: 2rem;
            background-color: #F4F4F4;
            color: #000;
        }
        .card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #72AFC1;
            color: white;
            padding: 1rem;
            border-radius: 5px 5px 0 0;
        }
        .list-group-item {
            padding: 1rem;
            border-bottom: 1px solid #ddd;
        }
    </style>
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