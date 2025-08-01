<?php
/**
 * PROCESADOR DE ASIGNACIÓN AUTOMÁTICA AHP - CORREGIDO
 * Archivo: procesar/procesar_asignacion_automatica.php
 * VERSIÓN CORREGIDA: Arregla el problema de claves faltantes en materias
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
                // Obtener todas las materias disponibles para la selección
                $materias_disponibles = obtenerTodasLasMaterias($conn, $ciclo_academico);
                
                $preview_data = urlencode(json_encode($asignaciones_preview));
                $materias_data = urlencode(json_encode($materias_disponibles));
                header("Location: ../pages/asignacion.php?preview_data=$preview_data&materias_data=$materias_data&ciclo_academico=" . urlencode($ciclo_academico));
            } else {
                $diagnostico = diagnosticarProblema($conn, $ciclo_academico);
                header("Location: ../pages/asignacion.php?error=" . urlencode($diagnostico));
            }
            
        } catch (Exception $e) {
            header("Location: ../pages/asignacion.php?error=" . urlencode("Error en vista previa: " . $e->getMessage()));
        }
    }
    
    // Confirmar asignaciones con materias seleccionadas
    elseif (isset($_POST['confirm_with_materias']) && $_POST['confirm_with_materias'] == '1') {
        $ciclo_academico = $_POST['ciclo_academico'];
        
        try {
            $conn->beginTransaction();
            
            $asignaciones_exitosas = confirmarAsignacionesConMaterias($conn, $_POST, $ciclo_academico);
            
            $conn->commit();
            header("Location: ../pages/asignacion.php?success=" . urlencode("Se confirmaron $asignaciones_exitosas asignaciones exitosamente con materias seleccionadas"));
            
        } catch (Exception $e) {
            $conn->rollBack();
            header("Location: ../pages/asignacion.php?error=" . urlencode("Error al confirmar asignaciones: " . $e->getMessage()));
        }
    }
    
    // Confirmar asignaciones (método original)
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
 * NUEVA FUNCIÓN: Obtiene todas las materias disponibles agrupadas por facultad
 */
function obtenerTodasLasMaterias($conn, $ciclo_academico) {
    $query_materias = "
        SELECT m.id_materia, m.nombre_materia, m.facultad, m.ciclo_academico
        FROM materias m
        WHERE m.ciclo_academico = :ciclo OR m.ciclo_academico IS NULL
        ORDER BY m.facultad, m.nombre_materia";
    
    $stmt_materias = $conn->prepare($query_materias);
    $stmt_materias->execute([':ciclo' => $ciclo_academico]);
    $materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);
    
    // Si no hay materias para el ciclo específico, obtener materias generales
    if (empty($materias)) {
        $query_materias_general = "
            SELECT m.id_materia, m.nombre_materia, m.facultad, m.ciclo_academico
            FROM materias m
            ORDER BY m.facultad, m.nombre_materia";
        
        $stmt_materias_general = $conn->prepare($query_materias_general);
        $stmt_materias_general->execute();
        $materias = $stmt_materias_general->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Agrupar materias por facultad
    $materias_agrupadas = [];
    foreach ($materias as $materia) {
        $facultad = $materia['facultad'];
        if (!isset($materias_agrupadas[$facultad])) {
            $materias_agrupadas[$facultad] = [];
        }
        $materias_agrupadas[$facultad][] = $materia;
    }
    
    return $materias_agrupadas;
}

/**
 * NUEVA FUNCIÓN: Confirma asignaciones con materias seleccionadas por el usuario
 */
function confirmarAsignacionesConMaterias($conn, $post_data, $ciclo_academico) {
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
    
    // Procesar cada asignación con su materia seleccionada
    $indice = 0;
    while (isset($post_data["estudiante_$indice"])) {
        try {
            // Obtener datos de la asignación
            $id_estudiante = $post_data["estudiante_$indice"];
            $id_docente = $post_data["docente_$indice"];
            $id_tipo_discapacidad = $post_data["tipo_discapacidad_$indice"];
            $id_materia = $post_data["materia_$indice"]; // Materia seleccionada por el usuario
            $puntuacion_ahp = $post_data["puntuacion_$indice"];
            
            // Obtener nombre de la materia
            $query_materia = "SELECT nombre_materia FROM materias WHERE id_materia = ?";
            $stmt_materia = $conn->prepare($query_materia);
            $stmt_materia->execute([$id_materia]);
            $materia_info = $stmt_materia->fetch(PDO::FETCH_ASSOC);
            $nombre_materia = $materia_info['nombre_materia'] ?? 'Materia Seleccionada';
            
            // Insertar la asignación
            $stmt_insertar->execute([
                ':id_docente' => $id_docente,
                ':id_estudiante' => $id_estudiante,
                ':id_tipo_discapacidad' => $id_tipo_discapacidad,
                ':id_materia' => $id_materia,
                ':ciclo_academico' => $ciclo_academico,
                ':materia' => $nombre_materia,
                ':puntuacion_ahp' => $puntuacion_ahp
            ]);
            
            $asignaciones_exitosas++;
            
        } catch (PDOException $e) {
            error_log("Error insertando asignación para estudiante índice $indice: " . $e->getMessage());
        }
        
        $indice++;
    }
    
    return $asignaciones_exitosas;
}

/**
 * Diagnostica por qué no hay estudiantes disponibles
 */
function diagnosticarProblema($conn, $ciclo_academico) {
    $total_estudiantes = $conn->query("SELECT COUNT(*) FROM estudiantes")->fetchColumn();
    
    $stmt_ciclo = $conn->prepare("SELECT COUNT(*) FROM estudiantes WHERE ciclo_academico = ?");
    $stmt_ciclo->execute([$ciclo_academico]);
    $estudiantes_ciclo = $stmt_ciclo->fetchColumn();
    
    $stmt_asignados = $conn->prepare("
        SELECT COUNT(*) FROM estudiantes e
        JOIN asignaciones a ON e.id_estudiante = a.id_estudiante 
        WHERE e.ciclo_academico = ? AND a.estado = 'Activa'");
    $stmt_asignados->execute([$ciclo_academico]);
    $estudiantes_asignados = $stmt_asignados->fetchColumn();
    
    $disponibles = $estudiantes_ciclo - $estudiantes_asignados;
    
    if ($total_estudiantes == 0) {
        return "No hay estudiantes registrados en el sistema. Agregue estudiantes primero.";
    } elseif ($estudiantes_ciclo == 0) {
        return "No hay estudiantes para el ciclo $ciclo_academico. Total en sistema: $total_estudiantes";
    } elseif ($disponibles == 0) {
        return "Todos los estudiantes del ciclo $ciclo_academico ya están asignados ($estudiantes_asignados de $estudiantes_ciclo)";
    } else {
        return "Hay $disponibles estudiantes disponibles pero no se pudieron procesar. Verifique la configuración.";
    }
}

/**
 * FUNCIÓN CORREGIDA: Genera vista previa de asignaciones usando AHP optimizado CON BALANCEADO DE CARGA
 */
function generarVistaPrevia($conn, $ciclo_academico) {
    // Obtener estudiantes sin asignar
    $query_estudiantes = "
        SELECT e.id_estudiante, e.nombres_completos, e.facultad,
               t.id_tipo_discapacidad, t.nombre_discapacidad, t.peso_prioridad
        FROM estudiantes e
        JOIN tipos_discapacidad t ON e.id_tipo_discapacidad = t.id_tipo_discapacidad
        LEFT JOIN asignaciones a ON e.id_estudiante = a.id_estudiante AND a.estado = 'Activa'
        WHERE e.ciclo_academico = :ciclo AND a.id_asignacion IS NULL
        ORDER BY t.peso_prioridad DESC, e.nombres_completos";
    
    $stmt_estudiantes = $conn->prepare($query_estudiantes);
    $stmt_estudiantes->execute([':ciclo' => $ciclo_academico]);
    $estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($estudiantes)) {
        return [];
    }
    
    // Obtener materias disponibles
    $materias = obtenerMaterias($conn, $ciclo_academico);
    
    // INICIALIZAR CONTADOR DE CARGA POR DOCENTE
    $carga_docentes = inicializarCargaDocentes($conn, $ciclo_academico);
    
    $asignaciones_preview = [];
    
    // Para cada estudiante, encontrar el mejor docente disponible CON BALANCEADO
    foreach ($estudiantes as $estudiante) {
        try {
            $docente_recomendado = encontrarMejorDocenteBalanceado($conn, $estudiante, $carga_docentes);
            
            if ($docente_recomendado) {
                // Actualizar carga del docente seleccionado
                $carga_docentes[$docente_recomendado['id_docente']]['asignaciones_actuales']++;
                $carga_docentes[$docente_recomendado['id_docente']]['por_tipo'][$estudiante['id_tipo_discapacidad']] = 
                    ($carga_docentes[$docente_recomendado['id_docente']]['por_tipo'][$estudiante['id_tipo_discapacidad']] ?? 0) + 1;
                
                // CORRECCIÓN: Seleccionar materia sugerida con validación
                $materia_sugerida = seleccionarMateria($materias, $estudiante['facultad']);
                
                // ASEGURAR que la materia tenga las claves necesarias
                $id_materia_sugerida = $materia_sugerida['id_materia'] ?? null;
                $nombre_materia_sugerida = $materia_sugerida['nombre_materia'] ?? 'Materia por Asignar';
                
                // CORRECCIÓN: Preparar datos para vista previa - CON TODAS LAS CLAVES NECESARIAS
                $asignaciones_preview[] = [
                    'id_estudiante' => $estudiante['id_estudiante'],
                    'estudiante' => $estudiante['nombres_completos'],
                    'facultad_estudiante' => $estudiante['facultad'],
                    'id_tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                    'nombre_discapacidad' => $estudiante['nombre_discapacidad'],
                    'peso_discapacidad' => $estudiante['peso_prioridad'],
                    'id_docente' => $docente_recomendado['id_docente'],
                    'docente' => $docente_recomendado['nombres_completos'],
                    // CLAVES CORREGIDAS para materias
                    'id_materia_sugerida' => $id_materia_sugerida,
                    'materia_sugerida' => $nombre_materia_sugerida,
                    // También incluir como fallback para compatibilidad
                    'id_materia' => $id_materia_sugerida,
                    'materia' => $nombre_materia_sugerida,
                    'puntuacion_ahp' => round($docente_recomendado['puntuacion_final'], 3),
                    'ranking_original' => $docente_recomendado['ranking_especifico'] ?? 1,
                    'tiene_experiencia_especifica' => $docente_recomendado['tiene_experiencia_especifica'] ?? 0,
                    'nivel_competencia' => $docente_recomendado['nivel_competencia_especifica'] ?? 'Básico',
                    'capacidad_restante' => $docente_recomendado['capacidad_restante'] ?? 0,
                    'carga_actual' => $carga_docentes[$docente_recomendado['id_docente']]['asignaciones_actuales']
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error asignando estudiante {$estudiante['nombres_completos']}: " . $e->getMessage());
            continue;
        }
    }
    
    return $asignaciones_preview;
}

/**
 * Inicializa el contador de carga de todos los docentes
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
            'por_tipo' => [] // Se llenará dinámicamente
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
 * Encuentra el mejor docente disponible CON BALANCEADO DE CARGA
 */
function encontrarMejorDocenteBalanceado($conn, $estudiante, $carga_docentes) {
    // Obtener candidatos ordenados por AHP
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
    
    if (empty($candidatos)) {
        // Fallback: buscar cualquier docente de la facultad
        $query_fallback = "
            SELECT d.id_docente, d.nombres_completos,
                   0.500 as puntuacion_base,
                   99 as ranking_especifico,
                   0 as tiene_experiencia_especifica,
                   'Básico' as nivel_competencia_especifica
            FROM docentes d
            WHERE d.facultad = ?
            ORDER BY d.experiencia_nee_años DESC";
        
        $stmt_fallback = $conn->prepare($query_fallback);
        $stmt_fallback->execute([$estudiante['facultad']]);
        $candidatos = $stmt_fallback->fetchAll(PDO::FETCH_ASSOC);
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
 * FUNCIÓN CORREGIDA: Obtiene materias disponibles con validación
 */
function obtenerMaterias($conn, $ciclo_academico) {
    $query_materias = "
        SELECT id_materia, nombre_materia, facultad 
        FROM materias 
        WHERE ciclo_academico = ? 
        ORDER BY nombre_materia";
    $stmt_materias = $conn->prepare($query_materias);
    $stmt_materias->execute([$ciclo_academico]);
    $materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($materias)) {
        $query_materias_general = "
            SELECT id_materia, nombre_materia, facultad 
            FROM materias 
            ORDER BY nombre_materia 
            LIMIT 10";
        $stmt_materias_general = $conn->prepare($query_materias_general);
        $stmt_materias_general->execute();
        $materias = $stmt_materias_general->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // CORRECCIÓN: Asegurar que siempre haya al menos una materia fallback
    if (empty($materias)) {
        $materias = [[
            'id_materia' => null,
            'nombre_materia' => 'Materia Asignada Automáticamente',
            'facultad' => 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS'
        ]];
    }
    
    return $materias;
}

/**
 * FUNCIÓN CORREGIDA: Selecciona la mejor materia para un estudiante con validación
 */
function seleccionarMateria($materias, $facultad_estudiante) {
    // Buscar materia de la misma facultad
    foreach ($materias as $materia) {
        if (isset($materia['facultad']) && $materia['facultad'] == $facultad_estudiante) {
            // ASEGURAR que tenga las claves necesarias
            return [
                'id_materia' => $materia['id_materia'] ?? null,
                'nombre_materia' => $materia['nombre_materia'] ?? 'Materia de ' . $facultad_estudiante,
                'facultad' => $materia['facultad']
            ];
        }
    }
    
    // Si no hay materia específica, usar la primera disponible
    if (!empty($materias) && isset($materias[0])) {
        $primera_materia = $materias[0];
        return [
            'id_materia' => $primera_materia['id_materia'] ?? null,
            'nombre_materia' => $primera_materia['nombre_materia'] ?? 'Materia General',
            'facultad' => $primera_materia['facultad'] ?? $facultad_estudiante
        ];
    }
    
    // Fallback final
    return [
        'id_materia' => null,
        'nombre_materia' => 'Materia por Asignar',
        'facultad' => $facultad_estudiante
    ];
}

/**
 * Confirma las asignaciones en la base de datos (método original)
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
                ':id_materia' => $asignacion['id_materia'] ?? $asignacion['id_materia_sugerida'],
                ':ciclo_academico' => $ciclo_academico,
                ':materia' => $asignacion['materia'] ?? $asignacion['materia_sugerida'],
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