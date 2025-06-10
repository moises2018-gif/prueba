<?php
include '../includes/conexion.php';
$conn = ConexionBD();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ciclo_academico = $_POST['ciclo_academico'];
    
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Lista de estudiantes simulados para asignar
        $estudiantes = [
            ['nombre' => 'Juan Pérez García', 'tipo_discapacidad' => 1, 'materia' => 'Matemáticas Discretas'],
            ['nombre' => 'María López Rodríguez', 'tipo_discapacidad' => 2, 'materia' => 'Programación I'],
            ['nombre' => 'Carlos Mendoza Silva', 'tipo_discapacidad' => 3, 'materia' => 'Física I'],
            ['nombre' => 'Ana Martínez Vera', 'tipo_discapacidad' => 4, 'materia' => 'Cálculo Diferencial'],
            ['nombre' => 'Luis González Torres', 'tipo_discapacidad' => 5, 'materia' => 'Álgebra Lineal'],
            ['nombre' => 'Patricia Ruiz Flores', 'tipo_discapacidad' => 1, 'materia' => 'Base de Datos'],
            ['nombre' => 'Roberto Díaz Morales', 'tipo_discapacidad' => 2, 'materia' => 'Estadística'],
            ['nombre' => 'Elena Vargas Jiménez', 'tipo_discapacidad' => 3, 'materia' => 'Química'],
            ['nombre' => 'Fernando Castro León', 'tipo_discapacidad' => 4, 'materia' => 'Programación II'],
            ['nombre' => 'Sofía Herrera Ponce', 'tipo_discapacidad' => 5, 'materia' => 'Cálculo Integral']
        ];
        
        // Obtener el ranking de docentes con sus puntuaciones
        $query_ranking = "SELECT d.id_docente, d.nombres_completos, vr.puntuacion_final,
                         (SELECT GROUP_CONCAT(DISTINCT ed.id_tipo_discapacidad) 
                          FROM experiencia_docente_discapacidad ed 
                          WHERE ed.id_docente = d.id_docente 
                          AND ed.tiene_experiencia = TRUE) as tipos_experiencia
                         FROM docentes d
                         JOIN vista_ranking_ahp vr ON d.id_docente = vr.id_docente
                         ORDER BY vr.puntuacion_final DESC";
        $stmt_ranking = $conn->prepare($query_ranking);
        $stmt_ranking->execute();
        $docentes = $stmt_ranking->fetchAll(PDO::FETCH_ASSOC);
        
        // Contador de asignaciones por docente
        $asignaciones_por_docente = array();
        foreach ($docentes as $docente) {
            $asignaciones_por_docente[$docente['id_docente']] = 0;
        }
        
        // Asignar cada estudiante
        $asignaciones_exitosas = 0;
        foreach ($estudiantes as $estudiante) {
            $docente_asignado = null;
            $mejor_puntuacion = 0;
            
            // Buscar el mejor docente para este tipo de discapacidad
            foreach ($docentes as $docente) {
                // Verificar si el docente tiene experiencia con este tipo de discapacidad
                $tipos_exp = explode(',', $docente['tipos_experiencia'] ?? '');
                $tiene_experiencia = in_array($estudiante['tipo_discapacidad'], $tipos_exp);
                
                // Calcular puntuación ajustada
                $puntuacion_ajustada = $docente['puntuacion_final'];
                if ($tiene_experiencia) {
                    $puntuacion_ajustada *= 1.2; // Bonus del 20% si tiene experiencia específica
                }
                
                // Penalizar si ya tiene muchas asignaciones
                $puntuacion_ajustada *= (1 - ($asignaciones_por_docente[$docente['id_docente']] * 0.1));
                
                if ($puntuacion_ajustada > $mejor_puntuacion) {
                    $mejor_puntuacion = $puntuacion_ajustada;
                    $docente_asignado = $docente;
                }
            }
            
            // Realizar la asignación
            if ($docente_asignado) {
                $query_asignar = "INSERT INTO asignaciones (id_docente, id_tipo_discapacidad, ciclo_academico, 
                                 materia, numero_estudiantes, puntuacion_ahp, estado) 
                                 VALUES (:docente, :discapacidad, :ciclo, :materia, :numero, :puntuacion, 'Activa')";
                
                $stmt_asignar = $conn->prepare($query_asignar);
                $stmt_asignar->execute([
                    ':docente' => $docente_asignado['id_docente'],
                    ':discapacidad' => $estudiante['tipo_discapacidad'],
                    ':ciclo' => $ciclo_academico,
                    ':materia' => $estudiante['materia'],
                    ':numero' => 1,
                    ':puntuacion' => $mejor_puntuacion
                ]);
                
                $asignaciones_por_docente[$docente_asignado['id_docente']]++;
                $asignaciones_exitosas++;
            }
        }
        
        // Confirmar transacción
        $conn->commit();
        
        header("Location: ../pages/asignacion.php?success=Se asignaron exitosamente $asignaciones_exitosas estudiantes");
        
    } catch (PDOException $e) {
        // Revertir transacción en caso de error
        $conn->rollBack();
        header("Location: ../pages/asignacion.php?error=Error en la asignación automática: " . $e->getMessage());
    }
} else {
    header("Location: ../pages/asignacion.php");
}
?>