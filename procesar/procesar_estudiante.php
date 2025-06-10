<?php
include '../includes/conexion.php';
$conn = ConexionBD();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres_completos = $_POST['nombres_completos'];
    $id_tipo_discapacidad = (int)$_POST['id_tipo_discapacidad'];
    $ciclo_academico = $_POST['ciclo_academico'];
    $facultad = $_POST['facultad'];

    try {
        $query = "
            INSERT INTO estudiantes (nombres_completos, id_tipo_discapacidad, ciclo_academico, facultad) 
            VALUES (:nombres, :discapacidad, :ciclo, :facultad)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nombres' => $nombres_completos,
            ':discapacidad' => $id_tipo_discapacidad,
            ':ciclo' => $ciclo_academico,
            ':facultad' => $facultad
        ]);
        header("Location: ../pages/estudiantes.php?success=Estudiante registrado exitosamente");
    } catch (PDOException $e) {
        header("Location: ../pages/estudiantes.php?error=Error al registrar estudiante: " . $e->getMessage());
    }
} else {
    header("Location: ../pages/estudiantes.php");
}
?>