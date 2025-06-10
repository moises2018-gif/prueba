<?php
include '../includes/conexion.php';
$conn = ConexionBD();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombres_completos = $_POST['nombres_completos'];
    $facultad = $_POST['facultad'];
    $modalidad_enseñanza = $_POST['modalidad_enseñanza'];
    $años_experiencia_docente = $_POST['años_experiencia_docente'];
    $titulo_tercer_nivel = $_POST['titulo_tercer_nivel'];
    $titulo_cuarto_nivel = $_POST['titulo_cuarto_nivel'] ?: null;
    $formacion_inclusion = (int)$_POST['formacion_inclusion'];
    $estudiantes_nee_promedio = $_POST['estudiantes_nee_promedio'];

    try {
        $query = "INSERT INTO docentes (nombres_completos, facultad, modalidad_enseñanza, años_experiencia_docente, titulo_tercer_nivel, titulo_cuarto_nivel, formacion_inclusion, estudiantes_nee_promedio) 
                  VALUES (:nombres, :facultad, :modalidad, :experiencia, :titulo3, :titulo4, :formacion, :estudiantes_nee)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':nombres' => $nombres_completos,
            ':facultad' => $facultad,
            ':modalidad' => $modalidad_enseñanza,
            ':experiencia' => $años_experiencia_docente,
            ':titulo3' => $titulo_tercer_nivel,
            ':titulo4' => $titulo_cuarto_nivel,
            ':formacion' => $formacion_inclusion,
            ':estudiantes_nee' => $estudiantes_nee_promedio
        ]);
        header("Location: ../pages/docentes.php?success=Docente registrado exitosamente");
    } catch (PDOException $e) {
        header("Location: ../pages/docentes.php?error=Error al registrar docente: " . $e->getMessage());
    }
}
?>