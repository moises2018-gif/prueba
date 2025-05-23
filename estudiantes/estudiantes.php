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
      <p>Haz clic en "Mostrar Datos" para visualizar la información.</p>
    </div>
  </div>

  <script>
    function mostrarDatos() {
      const datos = [
        { nombre: "Ana Pérez", carrera: "Ingeniería", discapacidad: "Visual", porcentaje: "40%", nivel: "Licenciatura" },
        { nombre: "Carlos Ruiz", carrera: "Psicología", discapacidad: "Auditiva", porcentaje: "60%", nivel: "Maestría" },
        { nombre: "Lucía Torres", carrera: "Educación", discapacidad: "Motora", porcentaje: "50%", nivel: "Licenciatura" },
        { nombre: "David Gómez", carrera: "Derecho", discapacidad: "Intelectual", porcentaje: "70%", nivel: "Doctorado" },
        { nombre: "María López", carrera: "Administración", discapacidad: "Visual", porcentaje: "35%", nivel: "Licenciatura" }
      ];

      let html = `
        <h2>Lista de Estudiantes</h2>
        <table>
          <tr>
            <th>Nombre</th>
            <th>Carrera</th>
            <th>Tipo de Discapacidad</th>
            <th>Porcentaje</th>
            <th>Nivel Académico</th>
          </tr>`;

      datos.forEach(est => {
        html += `
          <tr>
            <td>${est.nombre}</td>
            <td>${est.carrera}</td>
            <td>${est.discapacidad}</td>
            <td>${est.porcentaje}</td>
            <td>${est.nivel}</td>
          </tr>`;
      });

      html += `</table>`;
      document.getElementById("contenido").innerHTML = html;
    }
  </script>
</body>
</html>
