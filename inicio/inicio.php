<?php
session_start();

// Función para cerrar sesión
function logout() {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Verificar si se ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
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
      margin-bottom: 1rem;
    }
    .sidebar a:hover {
      background-color: #343a40;
      padding: 0.5rem;
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
        <button class="btn logout-btn" onclick="logout()">Cerrar Sesión</button>
      </div>
    </nav>

    <!-- Sidebar -->
    <nav class="col-md-2 d-flex flex-column sidebar">
      <h4 class="mb-4">UG</h4>
      <div class="mb-3">
        <strong>MOISES DAVID OCHOA NARANJO</strong>
        <div class="text-success">● en línea</div>
      </div>
      <a href="#" class="nav-link">Inicio</a>
      <div class="menu">
        <button class="menu-button">Estudiantes</button>
        <div class="submenu">
          <a href="estudiantes.php">Visualización</a>
          <a href="asignacion.php">Asignación</a>
        </div>
      </div>
      <a href="docentes.php" class="nav-link">Docentes</a>
      <a href="#" class="nav-link">Consultas Generales</a>
      <div class="mt-auto">
        <button class="btn btn-danger w-100">Cerrar sesión</button>
      </div>
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
        <div class="card-header">
          Redes Sociales UG
        </div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div><img src="https://cdn-icons-png.flaticon.com/512/145/145802.png" class="social-icon me-2">Facebook</div>
            <span>📘</span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div><img src="https://cdn-icons-png.flaticon.com/512/1384/1384060.png" class="social-icon me-2">Youtube</div>
            <span>▶️</span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div><img src="https://cdn-icons-png.flaticon.com/512/174/174855.png" class="social-icon me-2">Instagram</div>
            <span>📷</span>
          </li>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div><img src="https://cdn-icons-png.flaticon.com/512/3670/3670151.png" class="social-icon me-2">X</div>
            <span>❌</span>
          </li>
        </ul>
      </div>
    </main>
  </div>
</div>

<script>
  function logout() {
    window.location.href = 'logout.php';
  }
</script>

</body>
</html>