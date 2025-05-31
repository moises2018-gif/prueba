<?php
require_once 'conexion.php';

$experiencia = new ExperienciaDocente();
$experienciaData = $experiencia->obtenerExperienciaDocente();

header('Content-Type: application/json');
echo json_encode($experienciaData);
exit(); // Asegurar que el script se detiene aquí
?>