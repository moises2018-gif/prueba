<?php
/**
 * PROCESADOR DE ASIGNACIÓN AUTOMÁTICA - VERSIÓN FINAL CORREGIDA
 * Archivo: procesar/procesar_asignacion_automatica.php
 * 
 * CORRIGE: Error "Datos de vista previa inválidos"
 */

// Solo cargar conexión básica
require_once '../includes/conexion.php';

// Verificar método de solicitud
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/asignacion.php?error=Método no permitido");
    exit();
}

// Verificar conexión
$conn = ConexionBD();
if (!$conn) {
    header("Location: ../pages/asignacion.php?error=Error de conexión a la base de datos");
    exit();
}

// Obtener y validar ciclo académico
$ciclo_academico = trim($_POST['ciclo_academico'] ?? '');
if (empty($ciclo_academico)) {
    header("Location: ../pages/asignacion.php?error=Ciclo académico requerido");
    exit();
}

// Log básico para debugging
error_log("AHP: Procesando asignación para ciclo: $ciclo_academico");

try {
    // MODO VISTA PREVIA
    if (isset($_POST['preview']) && $_POST['preview'] == '1') {
        
        error_log("AHP: Generando vista previa");
        
        // Obtener estudiantes sin asignación para este ciclo
        $query_estudiantes = "
            SELECT 
                e.id_estudiante, 
                e.nombres_completos, 
                e.id_tipo_discapacidad, 
                e.facultad, 
                td.nombre_discapacidad, 
                td.peso_prioridad,
                m.id_materia, 
                m.nombre_materia
            FROM estudiantes e
            JOIN tipos_discapacidad td ON e.id_tipo_discapacidad = td.id_tipo_discapacidad
            LEFT JOIN asignaciones a ON e.id_estudiante = a.id_estudiante AND a.estado = 'Activa'
            LEFT JOIN materias m ON e.facultad = m.facultad AND m.ciclo_academico = ?
            WHERE e.ciclo_academico = ? 
            AND a.id_asignacion IS NULL
            AND m.id_materia IS NOT NULL
            ORDER BY td.peso_prioridad DESC, e.nombres_completos
            LIMIT 20";
        
        $stmt_estudiantes = $conn->prepare($query_estudiantes);
        $stmt_estudiantes->execute([$ciclo_academico, $ciclo_academico]);
        $estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("AHP: Estudiantes encontrados: " . count($estudiantes));
        
        if (empty($estudiantes)) {
            throw new Exception("No hay estudiantes sin asignación para el ciclo académico $ciclo_academico");
        }
        
        $preview_data = [];
        
        foreach ($estudiantes as $estudiante) {
            
            // Intentar usar vista AHP específica si existe
            $query_docente_ahp = "
                SELECT 
                    id_docente,
                    nombres_completos,
                    puntuacion_especifica_discapacidad,
                    ranking_por_discapacidad,
                    tiene_experiencia_especifica,
                    nivel_competencia_especifica
                FROM vista_ranking_ahp_especifico 
                WHERE id_tipo_discapacidad = ? 
                AND facultad = ?
                ORDER BY ranking_por_discapacidad ASC
                LIMIT 1";
            
            $stmt_ahp = $conn->prepare($query_docente_ahp);
            $stmt_ahp->execute([$estudiante['id_tipo_discapacidad'], $estudiante['facultad']]);
            $mejor_docente_ahp = $stmt_ahp->fetch(PDO::FETCH_ASSOC);
            
            if ($mejor_docente_ahp) {
                // Usar resultado AHP
                $preview_data[] = [
                    'id_estudiante' => $estudiante['id_estudiante'],
                    'estudiante' => $estudiante['nombres_completos'],
                    'id_tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                    'nombre_discapacidad' => $estudiante['nombre_discapacidad'],
                    'peso_discapacidad' => $estudiante['peso_prioridad'],
                    'id_docente' => $mejor_docente_ahp['id_docente'],
                    'docente' => $mejor_docente_ahp['nombres_completos'],
                    'id_materia' => $estudiante['id_materia'],
                    'materia' => $estudiante['nombre_materia'],
                    'puntuacion_ahp' => $mejor_docente_ahp['puntuacion_especifica_discapacidad'],
                    'tiene_experiencia_especifica' => $mejor_docente_ahp['tiene_experiencia_especifica'],
                    'nivel_competencia' => $mejor_docente_ahp['nivel_competencia_especifica'],
                    'ranking_original' => $mejor_docente_ahp['ranking_por_discapacidad']
                ];
            } else {
                // Fallback: usar ranking básico
                $query_docente_basico = "
                    SELECT 
                        d.id_docente,
                        d.nombres_completos,
                        COALESCE(vr.puntuacion_final, 0.5) as puntuacion_final
                    FROM docentes d
                    LEFT JOIN vista_ranking_ahp vr ON d.id_docente = vr.id_docente
                    WHERE d.facultad = ?
                    ORDER BY vr.puntuacion_final DESC
                    LIMIT 1";
                
                $stmt_basico = $conn->prepare($query_docente_basico);
                $stmt_basico->execute([$estudiante['facultad']]);
                $docente_basico = $stmt_basico->fetch(PDO::FETCH_ASSOC);
                
                if ($docente_basico) {
                    $preview_data[] = [
                        'id_estudiante' => $estudiante['id_estudiante'],
                        'estudiante' => $estudiante['nombres_completos'],
                        'id_tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                        'nombre_discapacidad' => $estudiante['nombre_discapacidad'],
                        'peso_discapacidad' => $estudiante['peso_prioridad'],
                        'id_docente' => $docente_basico['id_docente'],
                        'docente' => $docente_basico['nombres_completos'],
                        'id_materia' => $estudiante['id_materia'],
                        'materia' => $estudiante['nombre_materia'],
                        'puntuacion_ahp' => $docente_basico['puntuacion_final'],
                        'tiene_experiencia_especifica' => 0,
                        'nivel_competencia' => 'Básico',
                        'ranking_original' => 1
                    ];
                }
            }
        }
        
        error_log("AHP: Asignaciones generadas: " . count($preview_data));
        
        if (!empty($preview_data)) {
            // Codificar datos para URL - MEJORADO
            $preview_json = json_encode($preview_data, JSON_UNESCAPED_UNICODE);
            if ($preview_json === false) {
                throw new Exception("Error al codificar datos de vista previa: " . json_last_error_msg());
            }
            
            $preview_encoded = urlencode($preview_json);
            
            // Verificar tamaño de URL
            $url_length = strlen($preview_encoded);
            error_log("AHP: Tamaño URL: $url_length caracteres");
            
            if ($url_length > 8000) {
                // URL demasiado larga - usar solo primeros elementos
                $preview_data_reduced = array_slice($preview_data, 0, 10);
                $preview_encoded = urlencode(json_encode($preview_data_reduced, JSON_UNESCAPED_UNICODE));
                error_log("AHP: URL reducida a: " . strlen($preview_encoded) . " caracteres");
            }
            
            $url_params = [
                'preview_data' => $preview_encoded,
                'ciclo_academico' => urlencode($ciclo_academico),
                'total_preview' => count($preview_data)
            ];
            
            $redirect_url = "../pages/asignacion.php?" . http_build_query($url_params);
            
            error_log("AHP: Redirigiendo a vista previa");
            header("Location: $redirect_url");
            exit();
        } else {
            throw new Exception("No se pudieron generar asignaciones para este ciclo académico. Verifique que existan docentes disponibles.");
        }
    }
    
    // MODO CONFIRMACIÓN
    elseif (isset($_POST['confirm']) && $_POST['confirm'] == '1') {
        
        error_log("AHP: Confirmando asignaciones");
        
        // Validar datos de vista previa
        $preview_data_raw = $_POST['preview_data'] ?? '';
        if (empty($preview_data_raw)) {
            throw new Exception("No se encontraron datos de vista previa. Por favor, genere la vista previa nuevamente.");
        }
        
        // Decodificar datos
        $preview_data = json_decode($preview_data_raw, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("AHP: Error JSON: " . json_last_error_msg());
            error_log("AHP: Datos recibidos: " . substr($preview_data_raw, 0, 200) . "...");
            throw new Exception("Error al procesar datos de vista previa: " . json_last_error_msg());
        }
        
        if (!is_array($preview_data) || empty($preview_data)) {
            throw new Exception("Los datos de vista previa están vacíos o son inválidos.");
        }
        
        error_log("AHP: Confirmando " . count($preview_data) . " asignaciones");
        
        // Iniciar transacción
        $conn->beginTransaction();
        
        $query_insertar = "
            INSERT INTO asignaciones (
                id_docente, id_estudiante, id_tipo_discapacidad, id_materia,
                ciclo_academico, materia, numero_estudiantes, puntuacion_ahp, estado
            ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, 'Activa')";
        
        $stmt_insertar = $conn->prepare($query_insertar);
        $asignaciones_exitosas = 0;
        
        foreach ($preview_data as $asignacion) {
            // Validar que tengamos todos los campos necesarios
            $campos_requeridos = ['id_docente', 'id_estudiante', 'id_tipo_discapacidad', 'id_materia', 'materia', 'puntuacion_ahp'];
            foreach ($campos_requeridos as $campo) {
                if (!isset($asignacion[$campo])) {
                    error_log("AHP: Campo faltante: $campo en asignación");
                    continue 2; // Saltar esta asignación
                }
            }
            
            $success = $stmt_insertar->execute([
                $asignacion['id_docente'],
                $asignacion['id_estudiante'],
                $asignacion['id_tipo_discapacidad'],
                $asignacion['id_materia'],
                $ciclo_academico,
                $asignacion['materia'],
                $asignacion['puntuacion_ahp']
            ]);
            
            if ($success) {
                $asignaciones_exitosas++;
            } else {
                error_log("AHP: Error al insertar asignación: " . implode(", ", $stmt_insertar->errorInfo()));
            }
        }
        
        if ($asignaciones_exitosas > 0) {
            $conn->commit();
            
            $mensaje = "✅ Asignación automática completada!\n";
            $mensaje .= "📊 Total de asignaciones: $asignaciones_exitosas\n";
            $mensaje .= "🎯 Ciclo académico: $ciclo_academico";
            
            error_log("AHP: Asignaciones confirmadas exitosamente: $asignaciones_exitosas");
            
            header("Location: ../pages/asignacion.php?success=" . urlencode($mensaje));
            exit();
        } else {
            $conn->rollBack();
            throw new Exception("No se pudo confirmar ninguna asignación. Revise los datos e intente nuevamente.");
        }
    }
    
    // Solicitud inválida
    else {
        throw new Exception("Tipo de solicitud no válido.");
    }
    
} catch (Exception $e) {
    // Rollback si hay transacción activa
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    error_log("AHP: Error en procesamiento: " . $e->getMessage());
    error_log("AHP: POST data: " . json_encode($_POST));
    
    $mensaje_error = "Error en la asignación: " . $e->getMessage();
    
    // Agregar sugerencias según el tipo de error
    if (strpos($e->getMessage(), 'vista previa') !== false) {
        $mensaje_error .= "\n\n💡 Sugerencias:\n";
        $mensaje_error .= "• Intente generar la vista previa nuevamente\n";
        $mensaje_error .= "• Verifique que la base de datos esté actualizada\n";
        $mensaje_error .= "• Asegúrese de que las vistas AHP estén funcionando";
    }
    
    header("Location: ../pages/asignacion.php?error=" . urlencode($mensaje_error));
    exit();
}
?>