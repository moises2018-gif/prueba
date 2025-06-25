<?php
/**
 * PROCESADOR DE ASIGNACIÓN AUTOMÁTICA AHP - VERSIÓN CORREGIDA
 * Archivo: procesar/procesar_asignacion_automatica.php
 * CORRECCIÓN: Manejo de estudiantes sin materias específicas y debugging mejorado
 */

include '../includes/conexion.php';
include '../includes/config.php';

$conn = ConexionBD();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Vista previa de asignaciones
    if (isset($_POST['preview']) && $_POST['preview'] == '1') {
        $ciclo_academico = $_POST['ciclo_academico'];
        
        try {
            $asignaciones_preview = generarVistaPrevia($conn, $ciclo_academico);
            
            if (!empty($asignaciones_preview)) {
                $preview_data = urlencode(json_encode($asignaciones_preview));
                header("Location: ../pages/asignacion.php?preview_data=$preview_data&ciclo_academico=" . urlencode($ciclo_academico));
            } else {
                $diagnostico = diagnosticarProblema($conn, $ciclo_academico);
                header("Location: ../pages/asignacion.php?error=" . urlencode($diagnostico));
            }
            
        } catch (Exception $e) {
            header("Location: ../pages/asignacion.php?error=" . urlencode("Error en vista previa: " . $e->getMessage()));
        }
    }
    
    // Confirmar asignaciones
    elseif (isset($_POST['confirm']) && $_POST['confirm'] == '1') {
        $ciclo_academico = $_POST['ciclo_academico'];
        $preview_data = json_decode($_POST['preview_data'], true);
        
        try {
            $conn->beginTransaction();
            
            $asignaciones_exitosas = confirmarAsignaciones($conn, $preview_data, $ciclo_academico);
            
            $conn->commit();
            header("Location: ../pages/asignacion.php?success=" . urlencode("Se confirmaron $asignaciones_exitosas asignaciones exitosamente con distribución balanceada"));
            
        } catch (Exception $e) {
            $conn->rollBack();
            header("Location: ../pages/asignacion.php?error=" . urlencode("Error al confirmar asignaciones: " . $e->getMessage()));
        }
    }
    
    // Asignación directa (sin vista previa)
    else {
        $ciclo_academico = $_POST['ciclo_academico'];
        
        try {
            $conn->beginTransaction();
            
            $asignaciones_preview = generarVistaPrevia($conn, $ciclo_academico);
            
            if (!empty($asignaciones_preview)) {
                $asignaciones_exitosas = confirmarAsignaciones($conn, $asignaciones_preview, $ciclo_academico);
                $conn->commit();
                header("Location: ../pages/asignacion.php?success=" . urlencode("Se realizaron $asignaciones_exitosas asignaciones automáticas con distribución equilibrada"));
            } else {
                $conn->rollBack();
                $diagnostico = diagnosticarProblema($conn, $ciclo_academico);
                header("Location: ../pages/asignacion.php?error=" . urlencode($diagnostico));
            }
            
        } catch (Exception $e) {
            $conn->rollBack();
            header("Location: ../pages/asignacion.php?error=" . urlencode("Error en asignación automática: " . $e->getMessage()));
        }
    }
} else {
    header("Location: ../pages/asignacion.php?error=" . urlencode("Método de solicitud no válido"));
}

/**
 * DIAGNÓSTICO MEJORADO - Identifica exactamente dónde está el problema
 */
function diagnosticarProblema($conn, $ciclo_academico) {
    $diagnosticos = [];
    
    // 1. Verificar estudiantes totales
    $total_estudiantes = $conn->query("SELECT COUNT(*) FROM estudiantes")->fetchColumn();
    $diagnosticos[] = "Total estudiantes en sistema: $total_estudiantes";
    
    // 2. Verificar estudiantes del ciclo específico
    $stmt_ciclo = $conn->prepare("SELECT COUNT(*) FROM estudiantes WHERE ciclo_academico = ?");
    $stmt_ciclo->execute([$ciclo_academico]);
    $estudiantes_ciclo = $stmt_ciclo->fetchColumn();
    $diagnosticos[] = "Estudiantes para ciclo $ciclo_academico: $estudiantes_ciclo";
    
    // 3. Verificar estudiantes ya asignados
    $stmt_asignados = $conn->prepare("
        SELECT COUNT(*) FROM estudiantes e
        JOIN asignaciones a ON e.id_estudiante = a.id_estudiante 
        WHERE e.ciclo_academico = ? AND a.estado = 'Activa'");
    $stmt_asignados->execute([$ciclo_academico]);
    $estudiantes_asignados = $stmt_asignados->fetchColumn();
    $diagnosticos[] = "Estudiantes ya asignados: $estudiantes_asignados";
    
    $disponibles = $estudiantes_ciclo - $estudiantes_asignados;
    $diagnosticos[] = "Estudiantes disponibles: $disponibles";
    
    // 4. Verificar materias disponibles
    $stmt_materias = $conn->prepare("SELECT COUNT(*) FROM materias WHERE ciclo_academico = ?");
    $stmt_materias->execute([$ciclo_academico]);
    $materias_ciclo = $stmt_materias->fetchColumn();
    $diagnosticos[] = "Materias para ciclo $ciclo_academico: $materias_ciclo";
    
    // 5. Verificar docentes disponibles
    $docentes_total = $conn->query("SELECT COUNT(*) FROM docentes")->fetchColumn();
    $diagnosticos[] = "Total docentes: $docentes_total";
    
    // 6. Verificar vista AHP
    try {
        $vista_ahp = $conn->query("SELECT COUNT(*) FROM vista_ranking_ahp_especifico")->fetchColumn();
        $diagnosticos[] = "Registros en vista AHP: $vista_ahp";
    } catch (Exception $e) {
        $diagnosticos[] = "ERROR en vista AHP: " . $e->getMessage();
    }
    
    // 7. Verificar estudiantes específicos disponibles
    if ($disponibles > 0) {
        $stmt_detalle = $conn->prepare("
            SELECT e.nombres_completos, e.facultad, t.nombre_discapacidad
            FROM estudiantes e
            JOIN tipos_discapacidad t ON e.id_tipo_discapacidad = t.id_tipo_discapacidad
            LEFT JOIN asignaciones a ON e.id_estudiante = a.id_estudiante AND a.estado = 'Activa'
            WHERE e.ciclo_academico = ? AND a.id_asignacion IS NULL
            LIMIT 5");
        $stmt_detalle->execute([$ciclo_academico]);
        $estudiantes_detalle = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($estudiantes_detalle)) {
            $diagnosticos[] = "Estudiantes disponibles encontrados:";
            foreach ($estudiantes_detalle as $est) {
                $diagnosticos[] = "- {$est['nombres_completos']} ({$est['nombre_discapacidad']}, {$est['facultad']})";
            }
        }
    }
    
    // Construir mensaje final
    if ($disponibles == 0) {
        return "Todos los estudiantes ya están asignados. " . implode(" | ", $diagnosticos);
    } else {
        return "DIAGNÓSTICO DETALLADO: " . implode(" | ", $diagnosticos) . " | PROBABLE CAUSA: Falta de materias específicas del ciclo o problemas en vista AHP.";
    }
}

/**
 * GENERACIÓN DE VISTA PREVIA CORREGIDA
 */
function generarVistaPrevia($conn, $ciclo_academico) {
    // Obtener estudiantes sin asignar - CONSULTA MEJORADA
    $query_estudiantes = "
        SELECT e.id_estudiante, e.nombres_completos, e.facultad,
               t.id_tipo_discapacidad, t.nombre_discapacidad, t.peso_prioridad
        FROM estudiantes e
        JOIN tipos_discapacidad t ON e.id_tipo_discapacidad = t.id_tipo_discapacidad
        LEFT JOIN asignaciones a ON e.id_estudiante = a.id_estudiante AND a.estado = 'Activa'
        WHERE e.ciclo_academico = :ciclo 
        AND a.id_asignacion IS NULL
        ORDER BY t.peso_prioridad DESC, e.nombres_completos";
    
    $stmt_estudiantes = $conn->prepare($query_estudiantes);
    $stmt_estudiantes->execute([':ciclo' => $ciclo_academico]);
    $estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($estudiantes)) {
        error_log("No se encontraron estudiantes para el ciclo: $ciclo_academico");
        return [];
    }
    
    error_log("Estudiantes encontrados: " . count($estudiantes));
    
    // Obtener materias - MEJORADO CON FALLBACK
    $materias = obtenerMateriasConFallback($conn, $ciclo_academico);
    error_log("Materias disponibles: " . count($materias));
    
    // INICIALIZAR CONTADOR DE CARGA POR DOCENTE
    $carga_docentes = inicializarCargaDocentes($conn, $ciclo_academico);
    error_log("Docentes con carga inicializada: " . count($carga_docentes));
    
    $asignaciones_preview = [];
    $errores_procesamiento = [];
    
    // Para cada estudiante, encontrar el mejor docente disponible
    foreach ($estudiantes as $estudiante) {
        try {
            $docente_recomendado = encontrarMejorDocenteBalanceado($conn, $estudiante, $carga_docentes);
            
            if ($docente_recomendado) {
                // Actualizar carga del docente seleccionado
                $carga_docentes[$docente_recomendado['id_docente']]['asignaciones_actuales']++;
                $carga_docentes[$docente_recomendado['id_docente']]['por_tipo'][$estudiante['id_tipo_discapacidad']] = 
                    ($carga_docentes[$docente_recomendado['id_docente']]['por_tipo'][$estudiante['id_tipo_discapacidad']] ?? 0) + 1;
                
                // Seleccionar materia apropiada - MEJORADO
                $materia_seleccionada = seleccionarMateriaInteligente($materias, $estudiante['facultad']);
                
                // Preparar datos para vista previa
                $asignaciones_preview[] = [
                    'id_estudiante' => $estudiante['id_estudiante'],
                    'estudiante' => $estudiante['nombres_completos'],
                    'id_tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                    'nombre_discapacidad' => $estudiante['nombre_discapacidad'],
                    'peso_discapacidad' => $estudiante['peso_prioridad'],
                    'id_docente' => $docente_recomendado['id_docente'],
                    'docente' => $docente_recomendado['nombres_completos'],
                    'id_materia' => $materia_seleccionada['id_materia'],
                    'materia' => $materia_seleccionada['nombre_materia'],
                    'puntuacion_ahp' => round($docente_recomendado['puntuacion_final'], 3),
                    'ranking_original' => $docente_recomendado['ranking_especifico'] ?? 1,
                    'tiene_experiencia_especifica' => $docente_recomendado['tiene_experiencia_especifica'] ?? 0,
                    'nivel_competencia' => $docente_recomendado['nivel_competencia_especifica'] ?? 'Básico',
                    'capacidad_restante' => $docente_recomendado['capacidad_restante'],
                    'carga_actual' => $carga_docentes[$docente_recomendado['id_docente']]['asignaciones_actuales']
                ];
                
                error_log("Asignación exitosa: {$estudiante['nombres_completos']} -> {$docente_recomendado['nombres_completos']}");
            } else {
                $errores_procesamiento[] = "No se encontró docente para: {$estudiante['nombres_completos']} ({$estudiante['nombre_discapacidad']})";
                error_log("No se encontró docente para: {$estudiante['nombres_completos']}");
            }
            
        } catch (Exception $e) {
            $error_msg = "Error asignando estudiante {$estudiante['nombres_completos']}: " . $e->getMessage();
            $errores_procesamiento[] = $error_msg;
            error_log($error_msg);
            continue;
        }
    }
    
    error_log("Total asignaciones generadas: " . count($asignaciones_preview));
    error_log("Errores de procesamiento: " . count($errores_procesamiento));
    
    return $asignaciones_preview;
}

/**
 * OBTENER MATERIAS CON FALLBACK MEJORADO
 */
function obtenerMateriasConFallback($conn, $ciclo_academico) {
    // Intentar obtener materias del ciclo específico
    $query_materias = "
        SELECT id_materia, nombre_materia, facultad 
        FROM materias 
        WHERE ciclo_academico = ? 
        ORDER BY nombre_materia";
    $stmt_materias = $conn->prepare($query_materias);
    $stmt_materias->execute([$ciclo_academico]);
    $materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);
    
    // Si no hay materias para el ciclo específico, usar materias generales
    if (empty($materias)) {
        error_log("No se encontraron materias para ciclo $ciclo_academico, usando materias generales");
        $query_materias_general = "
            SELECT id_materia, nombre_materia, facultad 
            FROM materias 
            ORDER BY nombre_materia";
        $stmt_materias_general = $conn->prepare($query_materias_general);
        $stmt_materias_general->execute();
        $materias = $stmt_materias_general->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Si aún no hay materias, crear una materia por defecto
    if (empty($materias)) {
        error_log("No se encontraron materias en la BD, creando materia por defecto");
        $materias = [[
            'id_materia' => 5, // ID de Álgebra Lineal que existe en tu BD
            'nombre_materia' => 'Álgebra Lineal',
            'facultad' => 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS'
        ]];
    }
    
    return $materias;
}

/**
 * SELECCIONAR MATERIA DE FORMA INTELIGENTE
 */
function seleccionarMateriaInteligente($materias, $facultad_estudiante) {
    // 1. Buscar materia de la misma facultad
    foreach ($materias as $materia) {
        if (strpos($materia['facultad'], 'CIENCIAS MATEMATICAS') !== false && 
            strpos($facultad_estudiante, 'CIENCIAS MATEMATICAS') !== false) {
            return $materia;
        }
    }
    
    // 2. Buscar cualquier materia de la facultad exacta
    foreach ($materias as $materia) {
        if ($materia['facultad'] == $facultad_estudiante) {
            return $materia;
        }
    }
    
    // 3. Usar la primera materia disponible
    if (!empty($materias)) {
        return $materias[0];
    }
    
    // 4. Crear materia de emergencia
    return [
        'id_materia' => 5, // Álgebra Lineal existe en tu BD
        'nombre_materia' => 'Álgebra Lineal',
        'facultad' => $facultad_estudiante
    ];
}

/**
 * INICIALIZAR CARGA DOCENTES - SIN CAMBIOS (ya está bien)
 */
function inicializarCargaDocentes($conn, $ciclo_academico) {
    $query_carga = "
        SELECT 
            d.id_docente,
            d.nombres_completos,
            d.facultad,
            COALESCE(la.maximo_estudiantes_nee, 7) as limite_maximo,
            COALESCE(la.maximo_por_tipo_discapacidad, 3) as limite_por_tipo,
            COALESCE(asign_actuales.total, 0) as asignaciones_actuales
        FROM docentes d
        LEFT JOIN limites_asignacion la ON d.id_docente = la.id_docente
        LEFT JOIN (
            SELECT id_docente, COUNT(*) as total
            FROM asignaciones 
            WHERE estado = 'Activa' AND ciclo_academico = ?
            GROUP BY id_docente
        ) asign_actuales ON d.id_docente = asign_actuales.id_docente";
    
    $stmt_carga = $conn->prepare($query_carga);
    $stmt_carga->execute([$ciclo_academico]);
    $docentes = $stmt_carga->fetchAll(PDO::FETCH_ASSOC);
    
    $carga = [];
    foreach ($docentes as $docente) {
        $carga[$docente['id_docente']] = [
            'nombres_completos' => $docente['nombres_completos'],
            'facultad' => $docente['facultad'],
            'limite_maximo' => $docente['limite_maximo'],
            'limite_por_tipo' => $docente['limite_por_tipo'],
            'asignaciones_actuales' => $docente['asignaciones_actuales'],
            'por_tipo' => []
        ];
    }
    
    // Obtener distribución actual por tipo de discapacidad
    $query_por_tipo = "
        SELECT id_docente, id_tipo_discapacidad, COUNT(*) as cantidad
        FROM asignaciones 
        WHERE estado = 'Activa' AND ciclo_academico = ?
        GROUP BY id_docente, id_tipo_discapacidad";
    
    $stmt_por_tipo = $conn->prepare($query_por_tipo);
    $stmt_por_tipo->execute([$ciclo_academico]);
    $por_tipo = $stmt_por_tipo->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($por_tipo as $registro) {
        if (isset($carga[$registro['id_docente']])) {
            $carga[$registro['id_docente']]['por_tipo'][$registro['id_tipo_discapacidad']] = $registro['cantidad'];
        }
    }
    
    return $carga;
}

/**
 * ENCONTRAR MEJOR DOCENTE - MEJORADO CON MÁS FALLBACKS
 */
function encontrarMejorDocenteBalanceado($conn, $estudiante, $carga_docentes) {
    // 1. Intentar con vista AHP específica
    $query_candidatos = "
        SELECT vra.id_docente, d.nombres_completos,
               vra.puntuacion_especifica_discapacidad as puntuacion_base,
               vra.ranking_por_discapacidad as ranking_especifico,
               vra.tiene_experiencia_especifica,
               vra.nivel_competencia_especifica
        FROM vista_ranking_ahp_especifico vra
        JOIN docentes d ON vra.id_docente = d.id_docente
        WHERE vra.id_tipo_discapacidad = ?
        AND vra.facultad = ?
        ORDER BY vra.puntuacion_especifica_discapacidad DESC";
    
    $stmt_candidatos = $conn->prepare($query_candidatos);
    $stmt_candidatos->execute([$estudiante['id_tipo_discapacidad'], $estudiante['facultad']]);
    $candidatos = $stmt_candidatos->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Si no hay candidatos específicos, buscar de la misma facultad
    if (empty($candidatos)) {
        $query_facultad = "
            SELECT d.id_docente, d.nombres_completos,
                   0.700 as puntuacion_base,
                   50 as ranking_especifico,
                   COALESCE(edd.tiene_experiencia, 0) as tiene_experiencia_especifica,
                   COALESCE(edd.nivel_competencia, 'Básico') as nivel_competencia_especifica
            FROM docentes d
            LEFT JOIN experiencia_docente_discapacidad edd ON d.id_docente = edd.id_docente 
                AND edd.id_tipo_discapacidad = ?
            WHERE d.facultad = ?
            ORDER BY d.experiencia_nee_años DESC, d.formacion_inclusion DESC";
        
        $stmt_facultad = $conn->prepare($query_facultad);
        $stmt_facultad->execute([$estudiante['id_tipo_discapacidad'], $estudiante['facultad']]);
        $candidatos = $stmt_facultad->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 3. Si aún no hay candidatos, buscar cualquier docente con formación
    if (empty($candidatos)) {
        $query_cualquiera = "
            SELECT d.id_docente, d.nombres_completos,
                   0.500 as puntuacion_base,
                   99 as ranking_especifico,
                   0 as tiene_experiencia_especifica,
                   'Básico' as nivel_competencia_especifica
            FROM docentes d
            WHERE d.formacion_inclusion = 1
            ORDER BY d.experiencia_nee_años DESC";
        
        $stmt_cualquiera = $conn->prepare($query_cualquiera);
        $stmt_cualquiera->execute();
        $candidatos = $stmt_cualquiera->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // 4. Última opción: cualquier docente
    if (empty($candidatos)) {
        $query_ultimo = "
            SELECT d.id_docente, d.nombres_completos,
                   0.300 as puntuacion_base,
                   999 as ranking_especifico,
                   0 as tiene_experiencia_especifica,
                   'Básico' as nivel_competencia_especifica
            FROM docentes d
            ORDER BY d.experiencia_nee_años DESC
            LIMIT 10";
        
        $stmt_ultimo = $conn->prepare($query_ultimo);
        $stmt_ultimo->execute();
        $candidatos = $stmt_ultimo->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if (empty($candidatos)) {
        error_log("No se encontraron candidatos para estudiante: {$estudiante['nombres_completos']}");
        return null;
    }
    
    $mejor_candidato = null;
    $mejor_puntuacion_final = -1;
    
    foreach ($candidatos as $candidato) {
        $docente_id = $candidato['id_docente'];
        
        // Verificar si el docente tiene capacidad
        if (!isset($carga_docentes[$docente_id])) {
            continue;
        }
        
        $carga_info = $carga_docentes[$docente_id];
        
        // Verificar límite general
        if ($carga_info['asignaciones_actuales'] >= $carga_info['limite_maximo']) {
            continue;
        }
        
        // Verificar límite por tipo de discapacidad
        $actual_por_tipo = $carga_info['por_tipo'][$estudiante['id_tipo_discapacidad']] ?? 0;
        if ($actual_por_tipo >= $carga_info['limite_por_tipo']) {
            continue;
        }
        
        // CALCULAR PUNTUACIÓN FINAL CON PENALIZACIÓN POR CARGA
        $puntuacion_base = $candidato['puntuacion_base'];
        
        // Factor de penalización por carga (más carga = menor puntuación)
        $porcentaje_carga = $carga_info['asignaciones_actuales'] / $carga_info['limite_maximo'];
        $penalizacion_carga = 1 - ($porcentaje_carga * 0.3); // Hasta 30% de penalización
        
        // Bonus por experiencia específica
        $bonus_experiencia = $candidato['tiene_experiencia_especifica'] ? 1.1 : 1.0;
        
        // Puntuación final balanceada
        $puntuacion_final = $puntuacion_base * $penalizacion_carga * $bonus_experiencia;
        
        if ($puntuacion_final > $mejor_puntuacion_final) {
            $mejor_puntuacion_final = $puntuacion_final;
            $mejor_candidato = $candidato;
            $mejor_candidato['puntuacion_final'] = $puntuacion_final;
            $mejor_candidato['capacidad_restante'] = $carga_info['limite_maximo'] - $carga_info['asignaciones_actuales'];
            $mejor_candidato['penalizacion_aplicada'] = $penalizacion_carga;
        }
    }
    
    return $mejor_candidato;
}

/**
 * CONFIRMAR ASIGNACIONES - SIN CAMBIOS (ya está bien)
 */
function confirmarAsignaciones($conn, $asignaciones_preview, $ciclo_academico) {
    $query_insertar = "
        INSERT INTO asignaciones (
            id_docente, id_estudiante, id_tipo_discapacidad, 
            id_materia, ciclo_academico, materia, 
            numero_estudiantes, puntuacion_ahp, estado
        ) VALUES (
            :id_docente, :id_estudiante, :id_tipo_discapacidad,
            :id_materia, :ciclo_academico, :materia,
            1, :puntuacion_ahp, 'Activa'
        )";
    
    $stmt_insertar = $conn->prepare($query_insertar);
    $asignaciones_exitosas = 0;
    
    foreach ($asignaciones_preview as $asignacion) {
        try {
            $stmt_insertar->execute([
                ':id_docente' => $asignacion['id_docente'],
                ':id_estudiante' => $asignacion['id_estudiante'],
                ':id_tipo_discapacidad' => $asignacion['id_tipo_discapacidad'],
                ':id_materia' => $asignacion['id_materia'],
                ':ciclo_academico' => $ciclo_academico,
                ':materia' => $asignacion['materia'],
                ':puntuacion_ahp' => $asignacion['puntuacion_ahp']
            ]);
            $asignaciones_exitosas++;
            
        } catch (PDOException $e) {
            error_log("Error insertando asignación para estudiante {$asignacion['estudiante']}: " . $e->getMessage());
        }
    }
    
    return $asignaciones_exitosas;
}
?>