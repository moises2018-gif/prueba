<?php
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

// Crear una instancia de la clase Docentes y obtener los datos
$docentes = new Docentes();
$profesores = $docentes->obtenerDocentes();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pantalla de Datos de Profesores</title>
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
    }

    .container {
      flex: 1;
      display: flex;
    }

    .leftSide {
      background-color: #0A082D; /* Más oscuro */
      width: 200px;
      padding: 1rem;
      color: #fff;
    }

    .mainContent {
      flex: 1;
      padding: 2rem;
      background-color: #F4F4F4; /* Fondo claro para mejor contraste */
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
  <div class="header">Sistema de Datos de Profesores</div>
  <div class="container">
    <div class="leftSide">
      <p><strong>Menú</strong></p>
      <button class="btn" onclick="mostrarDatos()">Mostrar Datos</button> <br>
    </div>
    <div class="mainContent" id="contenido">
      <?php
      if (!empty($profesores)) {
          echo '<h2>Lista de Profesores</h2>';
          echo '<table>';
          echo '<tr><th>ID</th><th>ID Usuario</th><th>Nombre</th><th>Experiencia</th><th>Especialidad</th><th>Formación en NEE</th></tr>';

          foreach ($profesores as $profesor) {
              echo '<tr>';
              echo '<td>' . htmlspecialchars($profesor['id']) . '</td>';
              echo '<td>' . htmlspecialchars($profesor['id_usuario']) . '</td>';
              echo '<td>' . htmlspecialchars($profesor['nombre']) . '</td>';
              echo '<td>' . htmlspecialchars($profesor['experiencia']) . '</td>';
              echo '<td>' . htmlspecialchars($profesor['especialidad']) . '</td>';
              echo '<td>' . htmlspecialchars($profesor['formacion_nee']) . '</td>';
              echo '</tr>';
          }

          echo '</table>';
      } else {
          echo '<p>No hay datos disponibles.</p>';
      }
      ?>
    </div>
  </div>
</body>
</html>