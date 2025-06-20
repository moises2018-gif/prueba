<?php
/**
 * PROCESADOR DE ASIGNACIÓN AUTOMÁTICA UNIFICADO
 * Archivo: procesar/procesar_asignacion_automatica.php
 * 
 * Soporta tanto método tradicional como híbrido
 */

include '../includes/conexion.php';
require_once '../includes/config.php';

// Cargar clases necesarias
require_once '../classes/Logger.php';

// Cargar clase híbrida solo si se necesita
if (isset($_POST['metodo']) && $_POST['metodo'] === 'hibrido') {
    require_once '../classes/AsignacionAHPHibrida.php';
}

$conn = ConexionBD();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ciclo_academico'])) {
    $ciclo_academico = $_POST['ciclo_academico'];
    $metodo = $_POST['metodo'] ?? 'tradicional';
    $logger = new Logger();
    
    try {
        $logger->info('Iniciando procesamiento de asignación automática', [
            'ciclo' => $ciclo_academico,
            'metodo' => $metodo,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'desconocida'
        ]);
        
        // MÉTODO HÍBRIDO
        if ($metodo === 'hibrido') {
            $logger->info('Usando método AHP Híbrido');
            
            $asignadorHibrido = new AsignacionAHPHibrida($conn);
            
            // VISTA PREVIA HÍBRIDA
            if (isset($_POST['preview'])) {
                $resultado = $asignadorHibrido->ejecutarAsignacionHibrida($ciclo_academico, true);
                
                if (!empty($resultado['asignaciones'])) {
                    $preview_data = [];
                    foreach ($resultado['asignaciones'] as $asignacion) {
                        $preview_data[] = [
                            'estudiante' => $asignacion['estudiante'],
                            'docente' => $asignacion['docente'],
                            'nombre_discapacidad' => $asignacion['nombre_discapacidad'],
                            'peso_discapacidad' => $asignacion['peso_discapacidad'],
                            'materia' => $asignacion['materia'],
                            'puntuacion_ahp' => $asignacion['puntuacion_ahp'],
                            'ranking_hibrido' => $asignacion['ranking_hibrido'],
                            'intervalos_confianza' => $asignacion['intervalos_confianza'],
                            'tiene_experiencia_especifica' => $asignacion['tiene_experiencia_especifica'],
                            'nivel_competencia' => $asignacion['nivel_competencia']
                        ];
                    }
                    
                    $url_redirect = "../pages/asignacion.php?" . http_build_query([
                        'preview_data' => urlencode(json_encode($preview_data)),
                        'estadisticas' => urlencode(json_encode($resultado['estadisticas'])),
                        'ciclo_academico' => $ciclo_academico,
                        'metodo' => 'hibrido'
                    ]);
                    
                    $logger->info('Vista previa híbrida generada exitosamente', [
                        'total_asignaciones' => count($preview_data),
                        'tiempo_ejecucion' => $resultado['tiempo_ejecucion']
                    ]);
                    
                    header("Location: $url_redirect");
                    exit;
                } else {
                    throw new Exception("No se generaron asignaciones en la vista previa híbrida");
                }
            }
            
            // CONFIRMAR ASIGNACIONES HÍBRIDAS
            elseif (isset($_POST['confirm'])) {
                $resultado = $asignadorHibrido->ejecutarAsignacionHibrida($ciclo_academico, false);
                
                if ($resultado['exito']) {
                    $mensaje_exito = "🔬 Asignaciones híbridas completadas exitosamente!\n\n";
                    $mensaje_exito .= "✅ Total asignaciones: {$resultado['total']}\n";
                    $mensaje_exito .= "📊 Puntuación promedio: " . round($resultado['estadisticas']['puntuacion_promedio'], 4) . "\n";
                    $mensaje_exito .= "🎓 Con experiencia específica: {$resultado['estadisticas']['con_experiencia_especifica']} ({$resultado['estadisticas']['porcentaje_experiencia']}%)\n";
                    $mensaje_exito .= "📈 Intervalo confianza: [{$resultado['estadisticas']['intervalo_confianza_promedio']['inferior']}, {$resultado['estadisticas']['intervalo_confianza_promedio']['superior']}]\n";
                    $mensaje_exito .= "⚡ Tiempo de ejecución: {$resultado['tiempo_ejecucion']}ms\n";
                    $mensaje_exito .= "🔬 Método: AHP Híbrido (Tradicional + Difuso)";
                    
                    header("Location: ../pages/asignacion.php?success=" . urlencode($mensaje_exito));
                    exit;
                } else {
                    throw new Exception("Error al confirmar asignaciones híbridas");
                }
            }
        }
        
        // MÉTODO TRADICIONAL (CÓDIGO ORIGINAL MEJORADO)
        else {
            $logger->info('Usando método AHP Tradicional');
            
            // VISTA PREVIA TRADICIONAL
            if (isset($_POST['preview'])) {
                $resultado_tradicional = procesarMetodoTradicional($conn, $ciclo_academico, true);
                
                if (!empty($resultado_tradicional['asignaciones'])) {
                    $preview_data = $resultado_tradicional['asignaciones'];
                    
                    $url_redirect = "../pages/asignacion.php?" . http_build_query([
                        'preview_data' => urlencode(json_encode($preview_data)),
                        'ciclo_academico' => $ciclo_academico,
                        'metodo' => 'tradicional'
                    ]);
                    
                    $logger->info('Vista previa tradicional generada exitosamente', [
                        'total_asignaciones' => count($preview_data)
                    ]);
                    
                    header("Location: $url_redirect");
                    exit;
                } else {
                    throw new Exception("No se generaron asignaciones en la vista previa tradicional");
                }
            }
            
            // CONFIRMAR ASIGNACIONES TRADICIONALES
            elseif (isset($_POST['confirm'])) {
                $resultado_tradicional = procesarMetodoTradicional($conn, $ciclo_academico, false);
                
                if ($resultado_tradicional['exito']) {
                    $mensaje_exito = "📊 Asignaciones tradicionales completadas exitosamente!\n\n";
                    $mensaje_exito .= "✅ Total asignaciones: {$resultado_tradicional['total']}\n";
                    $mensaje_exito .= "📈 Método: AHP Tradicional (valores exactos)";
                    
                    header("Location: ../pages/asignacion.php?success=" . urlencode($mensaje_exito));
                    exit;
                } else {
                    throw new Exception("Error al confirmar asignaciones tradicionales");
                }
            }
            
            // DEFAULT: Generar vista previa tradicional
            else {
                $_POST['preview'] = 1;
                $resultado_tradicional = procesarMetodoTradicional($conn, $ciclo_academico, true);
                
                if (!empty($resultado_tradicional['asignaciones'])) {
                    $preview_data = $resultado_tradicional['asignaciones'];
                    
                    $url_redirect = "../pages/asignacion.php?" . http_build_query([
                        'preview_data' => urlencode(json_encode($preview_data)),
                        'ciclo_academico' => $ciclo_academico,
                        'metodo' => 'tradicional'
                    ]);
                    
                    header("Location: $url_redirect");
                    exit;
                }
            }
        }
        
    } catch (Exception $e) {
        $logger->error('Error en procesamiento automático', [
            'mensaje' => $e->getMessage(),
            'archivo' => $e->getFile(),
            'linea' => $e->getLine(),
            'ciclo' => $ciclo_academico,
            'metodo' => $metodo
        ]);
        
        $error_message = "Error en asignación {$metodo}: " . $e->getMessage();
        header("Location: ../pages/asignacion.php?error=" . urlencode($error_message));
        exit;
    }
    
} else {
    // Petición inválida
    header("Location: ../pages/asignacion.php?error=" . urlencode("Datos de formulario incompletos"));
    exit;
}

/**
 * FUNCIÓN PARA PROCESAR MÉTODO TRADICIONAL
 * Implementación mejorada del método original
 */
function procesarMetodoTradicional($conn, $ciclo_academico, $preview = false) {
    $logger = new Logger();
    
    try {
        if (!$preview) {
            $conn->beginTransaction();
        }
        
        // Obtener estudiantes sin asignar del ciclo seleccionado
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
        
        if (count($estudiantes) == 0) {
            return ['exito' => false, 'mensaje' => 'No hay estudiantes sin asignar'];
        }
        
        // Obtener materias del ciclo
        $query_materias = "SELECT DISTINCT id_materia, nombre_materia FROM materias WHERE ciclo_academico = :ciclo";
        $stmt_materias = $conn->prepare($query_materias);
        $stmt_materias->execute([':ciclo' => $ciclo_academico]);
        $materias = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($materias) == 0) {
            // Usar materias generales si no hay específicas del ciclo
            $query_materias_general = "SELECT DISTINCT id_materia, nombre_materia FROM materias LIMIT 10";
            $stmt_materias_general = $conn->prepare($query_materias_general);
            $stmt_materias_general->execute();
            $materias = $stmt_materias_general->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $asignaciones_exitosas = 0;
        $asignaciones_preview = [];
        $errores = [];
        
        // Para cada estudiante, encontrar el mejor docente usando método tradicional
        foreach ($estudiantes as $estudiante) {
            try {
                // Usar función de BD optimizada para recomendación equilibrada
                $query_mejor_docente = "SELECT recomendar_docente_equilibrado(:tipo_discapacidad, :facultad) as resultado";
                $stmt_mejor = $conn->prepare($query_mejor_docente);
                $stmt_mejor->execute([
                    ':tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                    ':facultad' => $estudiante['facultad']
                ]);
                
                $resultado_json = $stmt_mejor->fetchColumn();
                $resultado = json_decode($resultado_json, true);
                
                $docente_id = null;
                $puntuacion_ahp = 0;
                $docente_nombre = '';
                
                if (!isset($resultado['error']) && isset($resultado['id_docente'])) {
                    $docente_id = $resultado['id_docente'];
                    $puntuacion_ahp = $resultado['puntuacion_ahp'];
                    $docente_nombre = $resultado['nombre_docente'];
                } else {
                    // Fallback: consulta directa al ranking tradicional
                    $query_docente_directo = "
                        SELECT vra.id_docente, vra.nombres_completos, vra.puntuacion_especifica_discapacidad as puntuacion_ahp
                        FROM vista_ranking_ahp_especifico vra
                        JOIN vista_distribucion_carga vdc ON vra.id_docente = vdc.id_docente
                        WHERE vra.id_tipo_discapacidad = :tipo_discapacidad
                        AND vra.facultad = :facultad
                        AND vdc.capacidad_restante > 0
                        ORDER BY vra.puntuacion_especifica_discapacidad DESC
                        LIMIT 1";
                    
                    $stmt_directo = $conn->prepare($query_docente_directo);
                    $stmt_directo->execute([
                        ':tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                        ':facultad' => $estudiante['facultad']
                    ]);
                    
                    $docente_directo = $stmt_directo->fetch(PDO::FETCH_ASSOC);
                    
                    if ($docente_directo) {
                        $docente_id = $docente_directo['id_docente'];
                        $puntuacion_ahp = $docente_directo['puntuacion_ahp'];
                        $docente_nombre = $docente_directo['nombres_completos'];
                    }
                }
                
                // Si encontramos un docente, procesar la asignación
                if ($docente_id) {
                    // Seleccionar una materia
                    $materia = $materias[array_rand($materias)];
                    
                    // Obtener experiencia específica del docente
                    $query_experiencia = "
                        SELECT tiene_experiencia, nivel_competencia
                        FROM experiencia_docente_discapacidad
                        WHERE id_docente = :docente AND id_tipo_discapacidad = :tipo
                    ";
                    $stmt_exp = $conn->prepare($query_experiencia);
                    $stmt_exp->execute([':docente' => $docente_id, ':tipo' => $estudiante['id_tipo_discapacidad']]);
                    $experiencia = $stmt_exp->fetch(PDO::FETCH_ASSOC);
                    
                    $asignacion_data = [
                        'estudiante' => $estudiante['nombres_completos'],
                        'docente' => $docente_nombre,
                        'nombre_discapacidad' => $estudiante['nombre_discapacidad'],
                        'peso_discapacidad' => $estudiante['peso_prioridad'],
                        'materia' => $materia['nombre_materia'],
                        'puntuacion_ahp' => $puntuacion_ahp,
                        'ranking_original' => 1, // Siempre es el mejor disponible
                        'tiene_experiencia_especifica' => $experiencia['tiene_experiencia'] ?? false,
                        'nivel_competencia' => $experiencia['nivel_competencia'] ?? 'Básico'
                    ];
                    
                    if ($preview) {
                        $asignaciones_preview[] = $asignacion_data;
                    } else {
                        // Insertar la asignación real
                        $query_asignar = "
                            INSERT INTO asignaciones (
                                id_docente, id_estudiante, id_tipo_discapacidad, 
                                id_materia, ciclo_academico, materia, 
                                numero_estudiantes, puntuacion_ahp, estado
                            ) VALUES (
                                :docente, :estudiante, :tipo_discapacidad,
                                :materia_id, :ciclo, :materia_nombre,
                                1, :puntuacion, 'Activa'
                            )";
                        
                        $stmt_asignar = $conn->prepare($query_asignar);
                        $stmt_asignar->execute([
                            ':docente' => $docente_id,
                            ':estudiante' => $estudiante['id_estudiante'],
                            ':tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                            ':materia_id' => $materia['id_materia'],
                            ':ciclo' => $ciclo_academico,
                            ':materia_nombre' => $materia['nombre_materia'],
                            ':puntuacion' => $puntuacion_ahp
                        ]);
                    }
                    
                    $asignaciones_exitosas++;
                } else {
                    $errores[] = "No se encontró docente disponible para " . $estudiante['nombres_completos'];
                }
                
            } catch (Exception $e) {
                $errores[] = "Error al asignar a " . $estudiante['nombres_completos'] . ": " . $e->getMessage();
            }
        }
        
        if ($preview) {
            return [
                'exito' => true,
                'asignaciones' => $asignaciones_preview,
                'total' => count($asignaciones_preview),
                'errores' => $errores
            ];
        } else {
            // Confirmar transacción
            $conn->commit();
            
            return [
                'exito' => true,
                'total' => $asignaciones_exitosas,
                'errores' => $errores
            ];
        }
        
    } catch (Exception $e) {
        if (!$preview) {
            $conn->rollBack();
        }
        
        throw $e;
    }
}
?>