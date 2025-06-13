<?php
/**
 * SOLUCIÓN 1: LÍMITE DE ASIGNACIONES POR DOCENTE
 * Modifica el algoritmo de asignación para evitar sobrecarga
 */

// En procesar_asignacion_automatica.php - VERSIÓN MEJORADA

include '../includes/conexion.php';
$conn = ConexionBD();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ciclo_academico = $_POST['ciclo_academico'];
    
    // CONFIGURACIÓN DE LÍMITES
    $LIMITE_MAXIMO_POR_DOCENTE = 5; // Máximo 5 estudiantes NEE por docente
    $LIMITE_POR_TIPO_DISCAPACIDAD = 3; // Máximo 3 del mismo tipo por docente
    $PENALIZACION_POR_ASIGNACION = 0.15; // Reducir 15% por cada asignación existente
    
    try {
        $conn->beginTransaction();
        
        // Obtener estudiantes ordenados por PRIORIDAD del tipo de discapacidad
        $query_estudiantes = "
            SELECT e.id_estudiante, e.nombres_completos, e.id_tipo_discapacidad, 
                   e.ciclo_academico, e.facultad, td.nombre_discapacidad, td.peso_prioridad
            FROM estudiantes e
            JOIN tipos_discapacidad td ON e.id_tipo_discapacidad = td.id_tipo_discapacidad
            LEFT JOIN asignaciones a ON e.id_estudiante = a.id_estudiante AND a.estado = 'Activa'
            WHERE e.ciclo_academico = :ciclo AND a.id_asignacion IS NULL
            ORDER BY td.peso_prioridad DESC, e.nombres_completos";
        
        $stmt_estudiantes = $conn->prepare($query_estudiantes);
        $stmt_estudiantes->execute([':ciclo' => $ciclo_academico]);
        $estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($estudiantes)) {
            throw new Exception("No hay estudiantes sin asignación para el ciclo académico $ciclo_academico");
        }
        
        // CONTADOR DE ASIGNACIONES POR DOCENTE
        $asignaciones_por_docente = array();
        $asignaciones_por_tipo = array(); // [id_docente][id_tipo_discapacidad] = cantidad
        
        // Inicializar contadores con asignaciones existentes
        $query_existentes = "
            SELECT id_docente, id_tipo_discapacidad, COUNT(*) as cantidad
            FROM asignaciones 
            WHERE estado = 'Activa' AND ciclo_academico = :ciclo
            GROUP BY id_docente, id_tipo_discapacidad";
        $stmt_existentes = $conn->prepare($query_existentes);
        $stmt_existentes->execute([':ciclo' => $ciclo_academico]);
        $existentes = $stmt_existentes->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($existentes as $exist) {
            $docente_id = $exist['id_docente'];
            $tipo_id = $exist['id_tipo_discapacidad'];
            
            if (!isset($asignaciones_por_docente[$docente_id])) {
                $asignaciones_por_docente[$docente_id] = 0;
                $asignaciones_por_tipo[$docente_id] = array();
            }
            
            $asignaciones_por_docente[$docente_id] += $exist['cantidad'];
            $asignaciones_por_tipo[$docente_id][$tipo_id] = $exist['cantidad'];
        }
        
        if (isset($_POST['preview']) && $_POST['preview'] == '1') {
            $preview_data = array();
            $rechazados = array();
            
            foreach ($estudiantes as $estudiante) {
                // Obtener candidatos docentes para este tipo de discapacidad
                $query_candidatos = "
                    SELECT vr.id_docente, vr.nombres_completos, vr.puntuacion_especifica_discapacidad,
                           vr.ranking_por_discapacidad, vr.tiene_experiencia_especifica,
                           vr.nivel_competencia_especifica
                    FROM vista_ranking_ahp_especifico vr
                    WHERE vr.id_tipo_discapacidad = :tipo_discapacidad
                    AND vr.facultad = :facultad
                    ORDER BY vr.puntuacion_especifica_discapacidad DESC";
                
                $stmt_candidatos = $conn->prepare($query_candidatos);
                $stmt_candidatos->execute([
                    ':tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                    ':facultad' => $estudiante['facultad']
                ]);
                $candidatos = $stmt_candidatos->fetchAll(PDO::FETCH_ASSOC);
                
                $docente_asignado = null;
                $mejor_puntuacion = 0;
                $razon_rechazo = "";
                
                foreach ($candidatos as $candidato) {
                    $docente_id = $candidato['id_docente'];
                    $tipo_id = $estudiante['id_tipo_discapacidad'];
                    
                    // VERIFICAR LÍMITES
                    $total_asignaciones = $asignaciones_por_docente[$docente_id] ?? 0;
                    $asignaciones_tipo = $asignaciones_por_tipo[$docente_id][$tipo_id] ?? 0;
                    
                    // REGLA 1: No exceder límite máximo por docente
                    if ($total_asignaciones >= $LIMITE_MAXIMO_POR_DOCENTE) {
                        continue; // Buscar siguiente candidato
                    }
                    
                    // REGLA 2: No exceder límite por tipo de discapacidad
                    if ($asignaciones_tipo >= $LIMITE_POR_TIPO_DISCAPACIDAD) {
                        continue; // Buscar siguiente candidato
                    }
                    
                    // CALCULAR PUNTUACIÓN PENALIZADA
                    $puntuacion_base = $candidato['puntuacion_especifica_discapacidad'];
                    
                    // Penalización por carga actual
                    $factor_penalizacion = 1 - ($total_asignaciones * $PENALIZACION_POR_ASIGNACION);
                    $puntuacion_ajustada = $puntuacion_base * $factor_penalizacion;
                    
                    // Penalización adicional por mismo tipo de discapacidad
                    if ($asignaciones_tipo > 0) {
                        $puntuacion_ajustada *= (1 - ($asignaciones_tipo * 0.1)); // 10% menos por cada del mismo tipo
                    }
                    
                    if ($puntuacion_ajustada > $mejor_puntuacion) {
                        $mejor_puntuacion = $puntuacion_ajustada;
                        $docente_asignado = $candidato;
                        $docente_asignado['puntuacion_ajustada'] = $puntuacion_ajustada;
                        $docente_asignado['total_asignaciones_actuales'] = $total_asignaciones;
                        $docente_asignado['asignaciones_tipo_actuales'] = $asignaciones_tipo;
                    }
                }
                
                if ($docente_asignado) {
                    // Registrar asignación exitosa
                    $docente_id = $docente_asignado['id_docente'];
                    $tipo_id = $estudiante['id_tipo_discapacidad'];
                    
                    // Actualizar contadores
                    if (!isset($asignaciones_por_docente[$docente_id])) {
                        $asignaciones_por_docente[$docente_id] = 0;
                        $asignaciones_por_tipo[$docente_id] = array();
                    }
                    
                    $asignaciones_por_docente[$docente_id]++;
                    $asignaciones_por_tipo[$docente_id][$tipo_id] = ($asignaciones_por_tipo[$docente_id][$tipo_id] ?? 0) + 1;
                    
                    $preview_data[] = array(
                        'id_estudiante' => $estudiante['id_estudiante'],
                        'estudiante' => $estudiante['nombres_completos'],
                        'id_tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                        'nombre_discapacidad' => $estudiante['nombre_discapacidad'],
                        'peso_discapacidad' => $estudiante['peso_prioridad'],
                        'id_docente' => $docente_asignado['id_docente'],
                        'docente' => $docente_asignado['nombres_completos'],
                        'puntuacion_ahp' => $docente_asignado['puntuacion_ajustada'],
                        'puntuacion_base' => $docente_asignado['puntuacion_especifica_discapacidad'],
                        'total_asignaciones' => $docente_asignado['total_asignaciones_actuales'] + 1,
                        'asignaciones_tipo' => $docente_asignado['asignaciones_tipo_actuales'] + 1,
                        'tiene_experiencia_especifica' => $docente_asignado['tiene_experiencia_especifica'],
                        'nivel_competencia' => $docente_asignado['nivel_competencia_especifica'],
                        'ranking_original' => $docente_asignado['ranking_por_discapacidad']
                    );
                } else {
                    // No se pudo asignar - todos los docentes están sobrecargados
                    $rechazados[] = array(
                        'estudiante' => $estudiante['nombres_completos'],
                        'discapacidad' => $estudiante['nombre_discapacidad'],
                        'razon' => 'Todos los docentes competentes han alcanzado el límite de asignaciones'
                    );
                }
            }
            
            // Agregar información de rechazados al preview
            $preview_data['rechazados'] = $rechazados;
            $preview_data['limites'] = array(
                'maximo_por_docente' => $LIMITE_MAXIMO_POR_DOCENTE,
                'maximo_por_tipo' => $LIMITE_POR_TIPO_DISCAPACIDAD,
                'penalizacion' => $PENALIZACION_POR_ASIGNACION
            );
            
            // Redirigir con vista previa
            $preview_encoded = urlencode(json_encode($preview_data));
            header("Location: ../pages/asignacion.php?preview_data_limitado=$preview_encoded&ciclo_academico=" . urlencode($ciclo_academico));
            exit();
        }
        
        // Resto del código para confirmación...
        
    } catch (Exception $e) {
        $conn->rollBack();
        header("Location: ../pages/asignacion.php?error=Error en la asignación con límites: " . $e->getMessage());
    }
}
?>