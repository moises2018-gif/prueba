<?php
// Ruta relativa al archivo conexion.php
require '../php/conexion.php';

// Función para obtener los estudiantes de la base de datos
function obtenerEstudiantes() {
    $conn = new Cconexion();
    $db = $conn->ConexionBD();

    if ($db) {
        try {
            $stmt = $db->prepare("SELECT id, id_usuario, nombre, discapacidad, nivel, porcentaje FROM estudiantes");
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

// Obtener los datos de los estudiantes
$estudiantes = obtenerEstudiantes();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Pantalla de Datos</title>
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
  <div class="header">Sistema de Datos</div>
  <div class="container">
    <div class="leftSide">
      <p><strong>Menú</strong></p>
      <button class="btn" onclick="mostrarDatos()">Mostrar Datos</button>
    </div>
    <div class="mainContent" id="contenido">
      <?php
      if (!empty($estudiantes)) {
          echo '<h2>Lista de Estudiantes</h2>';
          echo '<table>';
          echo '<tr><th>ID</th><th>ID Usuario</th><th>Nombre</th><th>Discapacidad</th><th>Nivel</th><th>Porcentaje</th></tr>';

          foreach ($estudiantes as $est) {
              echo '<tr>';
              echo '<td>' . htmlspecialchars($est['id']) . '</td>';
              echo '<td>' . htmlspecialchars($est['id_usuario']) . '</td>';
              echo '<td>' . htmlspecialchars($est['nombre']) . '</td>';
              echo '<td>' . htmlspecialchars($est['discapacidad']) . '</td>';
              echo '<td>' . htmlspecialchars($est['nivel']) . '</td>';
              echo '<td>' . htmlspecialchars($est['porcentaje']) . '</td>';
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