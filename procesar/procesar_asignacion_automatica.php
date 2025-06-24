<?php
/**
 * PROCESADOR DE ASIGNACIÓN AHP CORREGIDO
 * Archivo: procesar/procesar_asignacion_automatica.php
 * 
 * VERSIÓN CORREGIDA - MANEJA VISTA PREVIA Y CONFIRMACIÓN CON LA MISMA LÓGICA
 */

include '../includes/conexion.php';
include '../includes/config.php';

// Cargar clase de asignación si existe
if (file_exists('../classes/AsignacionAHPOptimizada.php')) {
    include '../classes/AsignacionAHPOptimizada.php';
}

$conn = ConexionBD();

if (!$conn) {
    header("Location: ../pages/asignacion.php?error=" . urlencode("No se pudo conectar a la base de datos"));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ciclo_academico = $_POST['ciclo_academico'] ?? '';
    $es_vista_previa = isset($_POST['preview']) && $_POST['preview'] == '1';
    $es_confirmacion = isset($_POST['confirm']) && $_POST['confirm'] == '1';
    $datos_vista_previa = $_POST['preview_data'] ?? '';
    
    if (empty($ciclo_academico)) {
        header("Location: ../pages/asignacion.php?error=" . urlencode("Debe seleccionar un ciclo académico"));
        exit;
    }
    
    try {
        // ============================================
        // MODO VISTA PREVIA
        // ============================================
        if ($es_vista_previa) {
            $resultado = generarVistaPrevia($conn, $ciclo_academico);
            
            if (empty($resultado['asignaciones'])) {
                header("Location: ../pages/asignacion.php?error=" . urlencode("No hay estudiantes disponibles para asignar en este ciclo académico"));
                exit;
            }
            
            // Preparar datos para enviar a la página
            $preview_data = urlencode(json_encode($resultado['asignaciones']));
            $url_params = [
                'preview_data' => $preview_data,
                'ciclo_academico' => urlencode($ciclo_academico),
                'total_asignaciones' => count($resultado['asignaciones']),
                'puntuacion_promedio' => number_format($resultado['estadisticas']['puntuacion_promedio'], 3)
            ];
            
            $query_string = http_build_query($url_params);
            header("Location: ../pages/asignacion.php?$query_string");
            exit;
        }
        
        // ============================================
        // MODO CONFIRMACIÓN
        // ============================================
        elseif ($es_confirmacion && !empty($datos_vista_previa)) {
            $asignaciones_confirmar = json_decode(urldecode($datos_vista_previa), true);
            
            if (!$asignaciones_confirmar || !is_array($asignaciones_confirmar)) {
                throw new Exception("Datos de vista previa inválidos o corruptos");
            }
            
            $resultado = confirmarAsignaciones($conn, $asignaciones_confirmar, $ciclo_academico);
            
            $mensaje = "✅ Asignaciones confirmadas exitosamente: {$resultado['total_confirmadas']} asignaciones creadas";
            if ($resultado['errores'] > 0) {
                $mensaje .= " (⚠️ {$resultado['errores']} errores)";
            }
            
            header("Location: ../pages/asignacion.php?success=" . urlencode($mensaje));
            exit;
        }
        
        // ============================================
        // MODO DIRECTO (SIN VISTA PREVIA)
        // ============================================
        else {
            $resultado = generarVistaPrevia($conn, $ciclo_academico);
            
            if (empty($resultado['asignaciones'])) {
                header("Location: ../pages/asignacion.php?error=" . urlencode("No hay estudiantes disponibles para asignar"));
                exit;
            }
            
            $confirmacion = confirmarAsignaciones($conn, $resultado['asignaciones'], $ciclo_academico);
            
            $mensaje = "✅ Asignación automática completada: {$confirmacion['total_confirmadas']} asignaciones creadas";
            header("Location: ../pages/asignacion.php?success=" . urlencode($mensaje));
            exit;
        }
        
    } catch (Exception $e) {
        error_log("Error en asignación AHP: " . $e->getMessage());
        header("Location: ../pages/asignacion.php?error=" . urlencode("Error en la asignación: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: ../pages/asignacion.php?error=" . urlencode("Método de solicitud no válido"));
    exit;
}

/**
 * ============================================
 * FUNCIÓN: GENERAR VISTA PREVIA
 * ============================================
 * 
 * ALGORITMO UNIFICADO - MISMO PARA VISTA PREVIA Y CONFIRMACIÓN
 */
function generarVistaPrevia($conn, $ciclo_academico) {
    // 1. Obtener estudiantes sin asignar, priorizados por tipo de discapacidad
    $query_estudiantes = "
        SELECT 
            e.id_estudiante, 
            e.nombres_completos, 
            e.facultad,
            e.id_tipo_discapacidad, 
            td.nombre_discapacidad, 
            td.peso_prioridad,
            m.id_materia, 
            m.nombre_materia
        FROM estudiantes e
        JOIN tipos_discapacidad td ON e.id_tipo_discapacidad = td.id_tipo_discapacidad
        LEFT JOIN asignaciones a ON e.id_estudiante = a.id_estudiante AND a.estado = 'Activa'
        LEFT JOIN materias m ON e.facultad = m.facultad AND m.ciclo_academico = :ciclo
        WHERE e.ciclo_academico = :ciclo 
        AND a.id_asignacion IS NULL
        AND m.id_materia IS NOT NULL
        ORDER BY 
            td.peso_prioridad DESC,  -- Psicosocial primero (40%), luego Intelectual (30%), etc.
            e.nombres_completos
    ";
    
    $stmt = $conn->prepare($query_estudiantes);
    $stmt->execute([':ciclo' => $ciclo_academico]);
    $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($estudiantes)) {
        return ['asignaciones' => [], 'estadisticas' => []];
    }
    
    $asignaciones = [];
    $contadores_docente = []; // Para rastrear carga por docente
    
    // 2. Para cada estudiante, encontrar el mejor docente usando AHP especializado
    foreach ($estudiantes as $estudiante) {
        $docente_asignado = encontrarMejorDocente($conn, $estudiante, $contadores_docente);
        
        if ($docente_asignado) {
            $asignaciones[] = [
                'id_estudiante' => $estudiante['id_estudiante'],
                'estudiante' => $estudiante['nombres_completos'],
                'id_tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                'nombre_discapacidad' => $estudiante['nombre_discapacidad'],
                'peso_discapacidad' => $estudiante['peso_prioridad'],
                'id_docente' => $docente_asignado['id_docente'],
                'docente' => $docente_asignado['nombres_completos'],
                'id_materia' => $estudiante['id_materia'],
                'materia' => $estudiante['nombre_materia'],
                'puntuacion_ahp' => $docente_asignado['puntuacion_especifica_discapacidad'],
                'tiene_experiencia_especifica' => $docente_asignado['tiene_experiencia_especifica'],
                'nivel_competencia' => $docente_asignado['nivel_competencia_especifica'],
                'ranking_original' => $docente_asignado['ranking_por_discapacidad']
            ];
            
            // Actualizar contador de carga para este docente
            if (!isset($contadores_docente[$docente_asignado['id_docente']])) {
                $contadores_docente[$docente_asignado['id_docente']] = 0;
            }
            $contadores_docente[$docente_asignado['id_docente']]++;
        }
    }
    
    // 3. Calcular estadísticas
    $estadisticas = calcularEstadisticas($asignaciones);
    
    return [
        'asignaciones' => $asignaciones,
        'estadisticas' => $estadisticas
    ];
}

/**
 * ============================================
 * FUNCIÓN: ENCONTRAR MEJOR DOCENTE
 * ============================================
 */
function encontrarMejorDocente($conn, $estudiante, $contadores_docente) {
    // Usar vista AHP especializada que considera pesos específicos por tipo de discapacidad
    $query_docentes = "
        SELECT 
            vra.id_docente,
            vra.nombres_completos,
            vra.facultad,
            vra.puntuacion_especifica_discapacidad,
            vra.ranking_por_discapacidad,
            vra.tiene_experiencia_especifica,
            vra.nivel_competencia_especifica,
            vdc.capacidad_restante,
            vdc.maximo_estudiantes_nee,
            vdc.estado_carga
        FROM vista_ranking_ahp_especifico vra
        JOIN vista_distribucion_carga vdc ON vra.id_docente = vdc.id_docente
        WHERE vra.id_tipo_discapacidad = :tipo_discapacidad
        AND vra.facultad = :facultad
        AND vdc.capacidad_restante > 0
        ORDER BY 
            vdc.porcentaje_carga ASC,  -- Priorizar docentes con menor carga actual
            vra.puntuacion_especifica_discapacidad DESC  -- Luego por mejor puntuación AHP
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query_docentes);
    $stmt->execute([
        ':tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
        ':facultad' => $estudiante['facultad']
    ]);
    
    $docente_candidato = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$docente_candidato) {
        // Si no hay docente en la misma facultad, buscar en cualquier facultad
        $query_cualquier_facultad = "
            SELECT 
                vra.id_docente,
                vra.nombres_completos,
                vra.facultad,
                vra.puntuacion_especifica_discapacidad,
                vra.ranking_por_discapacidad,
                vra.tiene_experiencia_especifica,
                vra.nivel_competencia_especifica,
                vdc.capacidad_restante,
                vdc.maximo_estudiantes_nee,
                vdc.estado_carga
            FROM vista_ranking_ahp_especifico vra
            JOIN vista_distribucion_carga vdc ON vra.id_docente = vdc.id_docente
            WHERE vra.id_tipo_discapacidad = :tipo_discapacidad
            AND vdc.capacidad_restante > 0
            ORDER BY 
                vdc.porcentaje_carga ASC,
                vra.puntuacion_especifica_discapacidad DESC
            LIMIT 1
        ";
        
        $stmt2 = $conn->prepare($query_cualquier_facultad);
        $stmt2->execute([':tipo_discapacidad' => $estudiante['id_tipo_discapacidad']]);
        $docente_candidato = $stmt2->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($docente_candidato) {
        // Verificar si el docente ya tiene demasiadas asignaciones (considerando las que se van a hacer)
        $carga_actual = $contadores_docente[$docente_candidato['id_docente']] ?? 0;
        $capacidad_disponible = $docente_candidato['capacidad_restante'] - $carga_actual;
        
        if ($capacidad_disponible > 0) {
            return $docente_candidato;
        }
    }
    
    return null;
}

/**
 * ============================================
 * FUNCIÓN: CONFIRMAR ASIGNACIONES
 * ============================================
 */
function confirmarAsignaciones($conn, $asignaciones, $ciclo_academico) {
    $conn->beginTransaction();
    
    try {
        $total_confirmadas = 0;
        $errores = 0;
        
        $query_insertar = "
            INSERT INTO asignaciones (
                id_docente, id_estudiante, id_tipo_discapacidad, id_materia,
                ciclo_academico, materia, numero_estudiantes, puntuacion_ahp, estado
            ) VALUES (
                :id_docente, :id_estudiante, :id_tipo_discapacidad, :id_materia,
                :ciclo_academico, :materia, 1, :puntuacion_ahp, 'Activa'
            )
        ";
        
        $stmt = $conn->prepare($query_insertar);
        
        foreach ($asignaciones as $asignacion) {
            try {
                // Verificar que el estudiante no esté ya asignado
                $query_verificar = "
                    SELECT COUNT(*) 
                    FROM asignaciones 
                    WHERE id_estudiante = :estudiante AND estado = 'Activa'
                ";
                $stmt_verificar = $conn->prepare($query_verificar);
                $stmt_verificar->execute([':estudiante' => $asignacion['id_estudiante']]);
                
                if ($stmt_verificar->fetchColumn() > 0) {
                    $errores++;
                    continue; // El estudiante ya está asignado, saltar
                }
                
                // Insertar la asignación
                $stmt->execute([
                    ':id_docente' => $asignacion['id_docente'],
                    ':id_estudiante' => $asignacion['id_estudiante'],
                    ':id_tipo_discapacidad' => $asignacion['id_tipo_discapacidad'],
                    ':id_materia' => $asignacion['id_materia'],
                    ':ciclo_academico' => $ciclo_academico,
                    ':materia' => $asignacion['materia'],
                    ':puntuacion_ahp' => $asignacion['puntuacion_ahp']
                ]);
                
                $total_confirmadas++;
                
            } catch (PDOException $e) {
                $errores++;
                error_log("Error al confirmar asignación individual: " . $e->getMessage());
            }
        }
        
        $conn->commit();
        
        return [
            'total_confirmadas' => $total_confirmadas,
            'errores' => $errores
        ];
        
    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}

/**
 * ============================================
 * FUNCIÓN: CALCULAR ESTADÍSTICAS
 * ============================================
 */
function calcularEstadisticas($asignaciones) {
    if (empty($asignaciones)) {
        return [
            'total_asignaciones' => 0,
            'puntuacion_promedio' => 0,
            'con_experiencia_especifica' => 0,
            'porcentaje_experiencia' => 0
        ];
    }
    
    $total = count($asignaciones);
    $puntuacion_promedio = array_sum(array_column($asignaciones, 'puntuacion_ahp')) / $total;
    $con_experiencia = count(array_filter($asignaciones, function($a) { 
        return $a['tiene_experiencia_especifica']; 
    }));
    
    return [
        'total_asignaciones' => $total,
        'puntuacion_promedio' => round($puntuacion_promedio, 3),
        'con_experiencia_especifica' => $con_experiencia,
        'porcentaje_experiencia' => round(($con_experiencia / $total) * 100, 1)
    ];
}
?>