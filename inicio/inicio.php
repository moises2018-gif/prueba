<?php
session_start();

// Función para cerrar sesión
function logout() {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Verificar si se ha iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
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
                $stmt = $this->conn->prepare("
                    SELECT id_estudiante, nombres, discapacidad, porcentaje_discapacidad, nivel_academico, no_vez, creditos, fecha_registro
                    FROM estudiantes
                ");
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

// Clase para manejar los datos de los cursos
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

// Clase para manejar los datos de los horarios
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

// Clase para manejar los datos de las inscripciones
class Inscripciones {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerInscripciones() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT id_inscripcion, id_estudiante, id_curso, fecha_inscripcion
                    FROM inscripciones
                ");
                $stmt->execute();
                $inscripciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $inscripciones;
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

// Clase para manejar los datos de las materias
class Materias {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerMaterias() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT id_materia, nombre_materia, creditos
                    FROM materias
                ");
                $stmt->execute();
                $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $materias;
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

// Clase para manejar los datos de los turnos
class Turnos {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerTurnos() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT id_turno, nombre_turno
                    FROM turnos
                ");
                $stmt->execute();
                $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $turnos;
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

// Clase para manejar los datos de los usuarios
class Usuarios {
    private $conn;

    public function __construct() {
        $conexion = new Cconexion();
        $this->conn = $conexion->ConexionBD();
    }

    public function obtenerUsuarios() {
        if ($this->conn) {
            try {
                $stmt = $this->conn->prepare("
                    SELECT id, nombre, rol, creado_en
                    FROM usuarios
                ");
                $stmt->execute();
                $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return $usuarios;
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
      <button class="logout-btn" onclick="location.href='../php/logout.php'">Cerrar Sesión</button>
    </div>
  </div>
  <div class="container">
    <div class="sidebar">
      <ul>
        <li><a href="#estudiantes" onclick="mostrarEstudiantes()">Estudiantes</a></li>
        <li><a href="#docentes" onclick="mostrarDocentes()">Docentes</a></li>
        <li><a href="#cursos" onclick="mostrarCursos()">Cursos</a></li>
        <li><a href="#horarios" onclick="mostrarHorarios()">Horarios</a></li>
        <li><a href="#inscripciones" onclick="mostrarInscripciones()">Inscripciones</a></li>
        <li><a href="#materias" onclick="mostrarMaterias()">Materias</a></li>
        <li><a href="#turnos" onclick="mostrarTurnos()">Turnos</a></li>
        <li><a href="#usuarios" onclick="mostrarUsuarios()">Usuarios</a></li>
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
      fetch('../php/estudiantes.php')
        .then(response => response.json())
        .then(data => {
          let html = `
            <h2>Lista de Estudiantes</h2>
            <table>
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Discapacidad</th>
                <th>Porcentaje Discapacidad</th>
                <th>Nivel Académico</th>
                <th>Número de Veces</th>
                <th>Créditos</th>
                <th>Fecha de Registro</th>
              </tr>`;
          
          data.forEach(est => {
            html += `
              <tr>
                <td>${est.id_estudiante}</td>
                <td>${est.nombres}</td>
                <td>${est.discapacidad}</td>
                <td>${est.porcentaje_discapacidad}</td>
                <td>${est.nivel_academico}</td>
                <td>${est.no_vez}</td>
                <td>${est.creditos}</td>
                <td>${est.fecha_registro}</td>
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
      fetch('../php/docentes.php')
        .then(response => response.json())
        .then(data => {
          let html = `
            <h2>Lista de Docentes</h2>
            <table>
              <tr>
                <th>ID</th>
                <th>Nombres</th>
                <th>Carrera</th>
                <th>Tipo de Discapacidad</th>
                <th>Años de Experiencia</th>
              </tr>`;
          
          data.forEach(doc => {
            html += `
              <tr>
                <td>${doc.id_docente}</td>
                <td>${doc.nombres}</td>
                <td>${doc.carrera}</td>
                <td>${doc.tipo_discapacidad}</td>
                <td>${doc.años_experiencia}</td>
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

    function mostrarCursos() {
      fetch('../php/cursos.php')
        .then(response => response.json())
        .then(data => {
          let html = `
            <h2>Lista de Cursos</h2>
            <table>
              <tr>
                <th>ID</th>
                <th>ID Materia</th>
                <th>ID Docente</th>
                <th>Cupo</th>
              </tr>`;
          
          data.forEach(curso => {
            html += `
              <tr>
                <td>${curso.id_curso}</td>
                <td>${curso.id_materia}</td>
                <td>${curso.id_docente}</td>
                <td>${curso.cupo}</td>
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

    function mostrarHorarios() {
      fetch('../php/horarios.php')
        .then(response => response.json())
        .then(data => {
          let html = `
            <h2>Lista de Horarios</h2>
            <table>
              <tr>
                <th>ID</th>
                <th>ID Curso</th>
                <th>ID Turno</th>
                <th>Día de la Semana</th>
                <th>Hora de Inicio</th>
                <th>Hora de Fin</th>
              </tr>`;
          
          data.forEach(horario => {
            html += `
              <tr>
                <td>${horario.id_horario}</td>
                <td>${horario.id_curso}</td>
                <td>${horario.id_turno}</td>
                <td>${horario.dia_semana}</td>
                <td>${horario.hora_inicio}</td>
                <td>${horario.hora_fin}</td>
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

    function mostrarInscripciones() {
      fetch('../php/inscripciones.php')
        .then(response => response.json())
        .then(data => {
          let html = `
            <h2>Lista de Inscripciones</h2>
            <table>
              <tr>
                <th>ID</th>
                <th>ID Estudiante</th>
                <th>ID Curso</th>
                <th>Fecha de Inscripción</th>
              </tr>`;
          
          data.forEach(inscripcion => {
            html += `
              <tr>
                <td>${inscripcion.id_inscripcion}</td>
                <td>${inscripcion.id_estudiante}</td>
                <td>${inscripcion.id_curso}</td>
                <td>${inscripcion.fecha_inscripcion}</td>
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

    function mostrarMaterias() {
      fetch('../php/materias.php')
        .then(response => response.json())
        .then(data => {
          let html = `
            <h2>Lista de Materias</h2>
            <table>
              <tr>
                <th>ID</th>
                <th>Nombre de la Materia</th>
                <th>Créditos</th>
              </tr>`;
          
          data.forEach(materia => {
            html += `
              <tr>
                <td>${materia.id_materia}</td>
                <td>${materia.nombre_materia}</td>
                <td>${materia.creditos}</td>
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

    function mostrarTurnos() {
      fetch('../php/turnos.php')
        .then(response => response.json())
        .then(data => {
          let html = `
            <h2>Lista de Turnos</h2>
            <table>
              <tr>
                <th>ID</th>
                <th>Nombre del Turno</th>
              </tr>`;
          
          data.forEach(turno => {
            html += `
              <tr>
                <td>${turno.id_turno}</td>
                <td>${turno.nombre_turno}</td>
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

    function mostrarUsuarios() {
      fetch('../php/usuarios.php')
        .then(response => response.json())
        .then(data => {
          let html = `
            <h2>Lista de Usuarios</h2>
            <table>
              <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Rol</th>
                <th>Fecha de Creación</th>
              </tr>`;
          
          data.forEach(usuario => {
            html += `
              <tr>
                <td>${usuario.id}</td>
                <td>${usuario.nombre}</td>
                <td>${usuario.rol}</td>
                <td>${usuario.creado_en}</td>
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
  </script>
</body>
</html>