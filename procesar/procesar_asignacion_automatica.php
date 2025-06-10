<?php
include '../includes/conexion.php';
$conn = ConexionBD();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Si es vista previa
    if (isset($_POST['preview']) && $_POST['preview'] == '1') {
        $ciclo_academico = $_POST['ciclo_academico'];
        
        try {
            // Obtener estudiantes no asignados del ciclo seleccionado
            $query_estudiantes = "
                SELECT e.id_estudiante, e.nombres_completos, e.id_tipo_discapacidad, t.nombre_discapacidad
                FROM estudiantes e
                JOIN tipos_discapacidad t ON e.id_tipo_discapacidad = t.id_tipo_discapacidad
                WHERE e.ciclo_academico = :ciclo
                AND NOT EXISTS (
                    SELECT 1 FROM asignaciones a 
                    WHERE a.id_estudiante = e.id_estudiante 
                    AND a.estado = 'Activa'
                )";
            $stmt_estudiantes = $conn->prepare($query_estudiantes);
            $stmt_estudiantes->execute([':ciclo' => $ciclo_academico]);
            $estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener materias del ciclo
            $query_materias = "SELECT id_materia, nombre_materia FROM materias WHERE ciclo_academico = :ciclo";
            $stmt_materias = $conn->prepare($query_materias);
            $stmt_materias->execute([':ciclo' => $ciclo_academico]);
            $materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);
            
            // Obtener ranking de docentes
            $query_ranking = "
                SELECT d.id_docente, d.nombres_completos, vr.puntuacion_final,
                       edd.id_tipo_discapacidad, edd.años_experiencia, edd.nivel_competencia
                FROM docentes d
                JOIN vista_ranking_ahp vr ON d.id_docente = vr.id_docente
                LEFT JOIN experiencia_docente_discapacidad edd ON d.id_docente = edd.id_docente AND edd.tiene_experiencia = TRUE
                ORDER BY vr.puntuacion_final DESC";
            $stmt_ranking = $conn->prepare($query_ranking);
            $stmt_ranking->execute();
            $docentes_raw = $stmt_ranking->fetchAll(PDO::FETCH_ASSOC);
            
            // Organizar docentes por experiencia
            $docentes = [];
            foreach ($docentes_raw as $doc) {
                if (!isset($docentes[$doc['id_docente']])) {
                    $docentes[$doc['id_docente']] = [
                        'id_docente' => $doc['id_docente'],
                        'nombres_completos' => $doc['nombres_completos'],
                        'puntuacion_final' => $doc['puntuacion_final'],
                        'experiencia_tipos' => []
                    ];
                }
                if ($doc['id_tipo_discapacidad']) {
                    $docentes[$doc['id_docente']]['experiencia_tipos'][] = $doc['id_tipo_discapacidad'];
                }
            }
            
            $preview_data = [];
            
            if (!empty($estudiantes) && !empty($materias)) {
                foreach ($estudiantes as $estudiante) {
                    $mejor_docente = null;
                    $mejor_puntuacion = 0;
                    
                    foreach ($docentes as $docente) {
                        $puntuacion = $docente['puntuacion_final'];
                        
                        // Bonus si tiene experiencia con este tipo de discapacidad
                        if (in_array($estudiante['id_tipo_discapacidad'], $docente['experiencia_tipos'])) {
                            $puntuacion *= 1.3; // 30% de bonus
                        }
                        
                        if ($puntuacion > $mejor_puntuacion) {
                            $mejor_puntuacion = $puntuacion;
                            $mejor_docente = $docente;
                        }
                    }
                    
                    if ($mejor_docente) {
                        // Seleccionar materia aleatoria para el estudiante
                        $materia_seleccionada = $materias[array_rand($materias)];
                        
                        $preview_data[] = [
                            'id_estudiante' => $estudiante['id_estudiante'],
                            'estudiante' => $estudiante['nombres_completos'],
                            'id_tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                            'nombre_discapacidad' => $estudiante['nombre_discapacidad'],
                            'id_docente' => $mejor_docente['id_docente'],
                            'docente' => $mejor_docente['nombres_completos'],
                            'id_materia' => $materia_seleccionada['id_materia'],
                            'materia' => $materia_seleccionada['nombre_materia'],
                            'puntuacion_ahp' => $mejor_puntuacion,
                            'ciclo_academico' => $ciclo_academico
                        ];
                    }
                }
            }
            
            $preview_encoded = urlencode(json_encode($preview_data));
            header("Location: ../pages/asignacion.php?preview_data=$preview_encoded&ciclo_academico=" . urlencode($ciclo_academico));
            
        } catch (PDOException $e) {
            header("Location: ../pages/asignacion.php?error=Error al generar vista previa: " . $e->getMessage());
        }
    }
    
    // Si es confirmación de asignaciones
    elseif (isset($_POST['confirm']) && $_POST['confirm'] == '1') {
        $preview_data = json_decode($_POST['preview_data'], true);
        
        try {
            $conn->beginTransaction();
            
            $asignaciones_exitosas = 0;
            foreach ($preview_data as $asignacion) {
                // Verificar que el estudiante no esté ya asignado a la misma materia
                $query_verificar = "
                    SELECT COUNT(*) FROM asignaciones 
                    WHERE id_estudiante = :estudiante 
                    AND id_materia = :materia 
                    AND estado = 'Activa'";
                $stmt_verificar = $conn->prepare($query_verificar);
                $stmt_verificar->execute([
                    ':estudiante' => $asignacion['id_estudiante'],
                    ':materia' => $asignacion['id_materia']
                ]);
                
                if ($stmt_verificar->fetchColumn() == 0) {
                    $query_asignar = "
                        INSERT INTO asignaciones (
                            id_docente, id_estudiante, id_tipo_discapacidad, id_materia,
                            ciclo_academico, materia, numero_estudiantes, puntuacion_ahp, estado
                        ) VALUES (
                            :docente, :estudiante, :discapacidad, :materia,
                            :ciclo, :materia_nombre, 1, :puntuacion, 'Activa'
                        )";
                    
                    $stmt_asignar = $conn->prepare($query_asignar);
                    $stmt_asignar->execute([
                        ':docente' => $asignacion['id_docente'],
                        ':estudiante' => $asignacion['id_estudiante'],
                        ':discapacidad' => $asignacion['id_tipo_discapacidad'],
                        ':materia' => $asignacion['id_materia'],
                        ':ciclo' => $asignacion['ciclo_academico'],
                        ':materia_nombre' => $asignacion['materia'],
                        ':puntuacion' => $asignacion['puntuacion_ahp']
                    ]);
                    
                    $asignaciones_exitosas++;
                }
            }
            
            $conn->commit();
            header("Location: ../pages/asignacion.php?success=Se realizaron $asignaciones_exitosas asignaciones exitosamente");
            
        } catch (PDOException $e) {
            $conn->rollBack();
            header("Location: ../pages/asignacion.php?error=Error al confirmar asignaciones: " . $e->getMessage());
        }
    }
    
} else {
    header("Location: ../pages/asignacion.php");
}
?>