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
require 'php/conexion.php';

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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Sistema de Datos</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background-color: #0A082D;
      color: #fff;
      display: flex;
      flex-direction: column;
      height: 100vh;
    }

    .header {
      background-color: #16446A;
      padding: 1rem;
      text-align: center;
      font-size: 1.5rem;
      font-weight: bold;
      display: flex;
      justify-content: space-between;
    }

    .header .logout {
      cursor: pointer;
      background-color: #FF4D4D;
      color: #fff;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 5px;
    }

    .container {
      flex: 1;
      display: flex;
    }

    .sidebar {
      background-color: #0A082D;
      width: 200px;
      padding: 1rem;
      color: #fff;
    }

    .sidebar ul {
      list-style: none;
      padding: 0;
    }

    .sidebar ul li {
      margin-bottom: 1rem;
    }

    .sidebar ul li a {
      text-decoration: none;
      color: #fff;
      font-weight: bold;
    }

    .mainContent {
      flex: 1;
      padding: 2rem;
      background-color: #F4F4F4;
      color: #000;
    }

    .btn {
      background-color: #72AFC1;
      color: #000;
      border: none;
      padding: 10px 20px;
      cursor: pointer;
      font-weight: bold;
      border-radius: 5px;
    }

    table {
      margin-top: 1rem;
      width: 100%;
      border-collapse: collapse;
      background-color: #fff;
    }

    th, td {
      padding: 10px;
      text-align: left;
      border: 1px solid #ccc;
    }

    th {
      background-color: #72AFC1;
      color: #000;
    }
  </style>
</head>
<body>
  <div class="header">
    <div>Sistema de Datos</div>
    <div>
      <button class="logout" onclick="logout()">Logout</button>
    </div>
  </div>
  <div class="container">
    <div class="sidebar">
      <ul>
        <li><a href="#estudiantes" onclick="mostrarEstudiantes()">Estudiantes</a></li>
        <li><a href="#docentes" onclick="mostrarDocentes()">Docentes</a></li>
        <li><a href="#configuracion" onclick="mostrarConfiguracion()">Configuración</a></li>
      </ul>
    </div>
    <div class="mainContent" id="contenido">
      <h2>Bienvenido al Sistema de Datos</h2>
      <p>Seleccione una opción en el menú lateral.</p>
    </div>
  </div>

  <script>
    function mostrarEstudiantes() {
      fetch('php/estudiantes.php')
        .then(response => response.json())
        .then(data => {
          let html = `
            <h2>Lista de Estudiantes</h2>
            <table>
              <tr>
                <th>ID</th>
                <th>ID Usuario</th>
                <th>Nombre</th>
                <th>Discapacidad</th>
                <th>Nivel</th>
                <th>Porcentaje</th>
              </tr>`;
          
          data.forEach(est => {
            html += `
              <tr>
                <td>${est.id}</td>
                <td>${est.id_usuario}</td>
                <td>${est.nombre}</td>
                <td>${est.discapacidad}</td>
                <td>${est.nivel}</td>
                <td>${est.porcentaje}</td>
              </tr>`;
          });

          html += `</table>`;
          document.getElementById("contenido").innerHTML = html;
        })
        .catch(error => {
          console.error('Error:', error);
          document.getElementById("contenido").innerHTML = "<p>Error al cargar los datos.</p>";
        });
    }

    function mostrarDocentes() {
      fetch('php/docentes.php')
        .then(response => response.json())
        .then(data => {
          let html = `
            <h2>Lista de Docentes</h2>
            <table>
              <tr>
                <th>ID</th>
                <th>ID Usuario</th>
                <th>Nombre</th>
                <th>Experiencia</th>
                <th>Especialidad</th>
                <th>Formación en NEE</th>
              </tr>`;
          
          data.forEach(doc => {
            html += `
              <tr>
                <td>${doc.id}</td>
                <td>${doc.id_usuario}</td>
                <td>${doc.nombre}</td>
                <td>${doc.experiencia}</td>
                <td>${doc.especialidad}</td>
                <td>${doc.formacion_nee}</td>
              </tr>`;
          });

          html += `</table>`;
          document.getElementById("contenido").innerHTML = html;
        })
        .catch(error => {
          console.error('Error:', error);
          document.getElementById("contenido").innerHTML = "<p>Error al cargar los datos.</p>";
        });
    }

    function mostrarConfiguracion() {
      document.getElementById("contenido").innerHTML = `
        <h2>Configuración</h2>
        <p>Esta sección está en construcción.</p>
      `;
    }

    function logout() {
      window.location.href = 'logout.php';
    }
  </script>
</body>
</html>