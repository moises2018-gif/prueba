<?php
/**
 * CLASE PRINCIPAL DEL ALGORITMO AHP OPTIMIZADO
 * Archivo: classes/AsignacionAHPOptimizada.php
 */

class AsignacionAHPOptimizada {
    private $conn;
    private $limites;
    private $logger;
    
    public function __construct($conexion) {
        $this->conn = $conexion;
        $this->logger = new Logger();
        
        // Cargar límites desde configuración
        $this->limites = [
            'max_estudiantes_por_docente' => AHP_MAX_ESTUDIANTES_POR_DOCENTE,
            'max_por_tipo_discapacidad' => AHP_MAX_POR_TIPO_DISCAPACIDAD,
            'penalizacion_carga' => AHP_PENALIZACION_CARGA,
            'bonus_experiencia_especifica' => AHP_BONUS_EXPERIENCIA
        ];
        
        $this->logger->log('INFO', 'AsignacionAHPOptimizada inicializada', $this->limites);
    }
    
    /**
     * Método principal para ejecutar asignación automática
     */
    public function ejecutarAsignacionEquilibrada($ciclo_academico, $preview = false) {
        $inicio = microtime(true);
        
        try {
            $this->logger->log('INFO', "Iniciando asignación para ciclo: $ciclo_academico", ['preview' => $preview]);
            
            if (!$preview) {
                $this->conn->beginTransaction();
            }
            
            // 1. Obtener estudiantes priorizados
            $estudiantes = $this->obtenerEstudiantesPriorizados($ciclo_academico);
            $this->logger->log('INFO', 'Estudiantes obtenidos', ['total' => count($estudiantes)]);
            
            if (empty($estudiantes)) {
                throw new Exception("No hay estudiantes sin asignación para el ciclo académico $ciclo_academico");
            }
            
            // 2. Obtener estado actual de carga docente
            $carga_docentes = $this->obtenerCargaDocentes($ciclo_academico);
            $this->logger->log('INFO', 'Carga docentes obtenida', ['total_docentes' => count($carga_docentes)]);
            
            // 3. Ejecutar algoritmo de asignación
            $asignaciones = [];
            $rechazados = [];
            
            foreach ($estudiantes as $estudiante) {
                $resultado = $this->asignarEstudiante($estudiante, $carga_docentes);
                
                if ($resultado['exito']) {
                    $asignaciones[] = $resultado['asignacion'];
                    $this->actualizarCargaDocente($carga_docentes, $resultado['asignacion']);
                    
                    $this->logger->log('INFO', 'Estudiante asignado exitosamente', [
                        'estudiante' => $estudiante['nombres_completos'],
                        'docente' => $resultado['asignacion']['docente'],
                        'puntuacion' => $resultado['asignacion']['puntuacion_ahp']
                    ]);
                } else {
                    $rechazados[] = $resultado['razon'];
                    $this->logger->log('WARNING', 'Estudiante rechazado', $resultado['razon']);
                }
            }
            
            // 4. Calcular estadísticas
            $estadisticas = $this->calcularEstadisticas($asignaciones);
            
            if ($preview) {
                if (!$preview) {
                    $this->conn->rollBack();
                }
                
                $tiempo_ejecucion = round((microtime(true) - $inicio) * 1000, 2);
                $this->logger->log('INFO', 'Vista previa generada', [
                    'tiempo_ms' => $tiempo_ejecucion,
                    'asignaciones' => count($asignaciones),
                    'rechazados' => count($rechazados)
                ]);
                
                return [
                    'asignaciones' => $asignaciones,
                    'rechazados' => $rechazados,
                    'estadisticas' => $estadisticas,
                    'limites' => $this->limites,
                    'tiempo_ejecucion' => $tiempo_ejecucion
                ];
            }
            
            // 5. Confirmar asignaciones en BD
            if (!empty($asignaciones)) {
                $this->confirmarAsignaciones($asignaciones, $ciclo_academico);
                $this->conn->commit();
                
                $tiempo_ejecucion = round((microtime(true) - $inicio) * 1000, 2);
                $this->logger->log('INFO', 'Asignaciones confirmadas exitosamente', [
                    'ciclo' => $ciclo_academico,
                    'total_asignaciones' => count($asignaciones),
                    'tiempo_ms' => $tiempo_ejecucion
                ]);
                
                return [
                    'exito' => true, 
                    'total' => count($asignaciones),
                    'estadisticas' => $estadisticas,
                    'tiempo_ejecucion' => $tiempo_ejecucion
                ];
            } else {
                $this->conn->rollBack();
                throw new Exception("No se pudo realizar ninguna asignación. Verifique la disponibilidad de docentes.");
            }
            
        } catch (Exception $e) {
            if (!$preview) {
                $this->conn->rollBack();
            }
            
            $this->logger->log('ERROR', 'Error en asignación', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Obtiene estudiantes ordenados por prioridad del tipo de discapacidad
     */
    private function obtenerEstudiantesPriorizados($ciclo_academico) {
        $query = "
            SELECT e.id_estudiante, e.nombres_completos, e.id_tipo_discapacidad, 
                   e.facultad, td.nombre_discapacidad, td.peso_prioridad,
                   m.id_materia, m.nombre_materia
            FROM estudiantes e
            JOIN tipos_discapacidad td ON e.id_tipo_discapacidad = td.id_tipo_discapacidad
            LEFT JOIN asignaciones a ON e.id_estudiante = a.id_estudiante AND a.estado = 'Activa'
            LEFT JOIN materias m ON e.facultad = m.facultad AND m.ciclo_academico = :ciclo
            WHERE e.ciclo_academico = :ciclo 
            AND a.id_asignacion IS NULL
            AND m.id_materia IS NOT NULL
            ORDER BY 
                td.peso_prioridad DESC,
                e.nombres_completos
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':ciclo' => $ciclo_academico]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene carga actual de todos los docentes con límites configurados
     */
    private function obtenerCargaDocentes($ciclo_academico) {
        $query = "
            SELECT 
                d.id_docente,
                d.nombres_completos,
                d.facultad,
                COALESCE(la.maximo_estudiantes_nee, :max_default) as capacidad_maxima,
                COALESCE(la.maximo_por_tipo_discapacidad, :max_tipo_default) as max_por_tipo,
                COALESCE(carga.total_actual, 0) as asignaciones_actuales,
                COALESCE(carga.distribucion_json, '{}') as distribucion_tipos
            FROM docentes d
            LEFT JOIN limites_asignacion la ON d.id_docente = la.id_docente
            LEFT JOIN (
                SELECT 
                    id_docente,
                    COUNT(*) as total_actual,
                    JSON_OBJECTAGG(id_tipo_discapacidad, cantidad) as distribucion_json
                FROM (
                    SELECT id_docente, id_tipo_discapacidad, COUNT(*) as cantidad
                    FROM asignaciones 
                    WHERE estado = 'Activa' AND ciclo_academico = :ciclo
                    GROUP BY id_docente, id_tipo_discapacidad
                ) sub
                GROUP BY id_docente
            ) carga ON d.id_docente = carga.id_docente
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':ciclo' => $ciclo_academico,
            ':max_default' => $this->limites['max_estudiantes_por_docente'],
            ':max_tipo_default' => $this->limites['max_por_tipo_discapacidad']
        ]);
        $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convertir a array asociativo
        $carga = [];
        foreach ($docentes as $docente) {
            $carga[$docente['id_docente']] = [
                'nombres_completos' => $docente['nombres_completos'],
                'facultad' => $docente['facultad'],
                'capacidad_maxima' => $docente['capacidad_maxima'],
                'max_por_tipo' => $docente['max_por_tipo'],
                'total_actual' => $docente['asignaciones_actuales'],
                'por_tipo' => json_decode($docente['distribucion_tipos'], true) ?: []
            ];
        }
        
        return $carga;
    }
    
    /**
     * Usa la función de la BD para obtener recomendación equilibrada
     */
    public function obtenerRecomendacionEquilibrada($tipo_discapacidad, $facultad) {
        $query = "SELECT recomendar_docente_equilibrado(:tipo_discapacidad, :facultad) as recomendacion";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':tipo_discapacidad' => $tipo_discapacidad,
            ':facultad' => $facultad
        ]);
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return json_decode($resultado['recomendacion'], true);
    }
    
    /**
     * Asigna un estudiante al mejor docente disponible
     */
    private function asignarEstudiante($estudiante, &$carga_docentes) {
        // Intentar usar la función de BD primero
        $recomendacion = $this->obtenerRecomendacionEquilibrada(
            $estudiante['id_tipo_discapacidad'],
            $estudiante['facultad']
        );
        
        if (isset($recomendacion['error'])) {
            return [
                'exito' => false,
                'razon' => [
                    'estudiante' => $estudiante['nombres_completos'],
                    'discapacidad' => $estudiante['nombre_discapacidad'],
                    'motivo' => $recomendacion['error']
                ]
            ];
        }
        
        // Obtener datos completos del docente recomendado
        $query_docente = "
            SELECT 
                vr.id_docente,
                vr.nombres_completos,
                vr.facultad,
                vr.puntuacion_especifica_discapacidad,
                vr.ranking_por_discapacidad,
                vr.tiene_experiencia_especifica,
                vr.nivel_competencia_especifica
            FROM vista_ranking_ahp_especifico vr
            WHERE vr.id_docente = :id_docente
            AND vr.id_tipo_discapacidad = :tipo_discapacidad
        ";
        
        $stmt = $this->conn->prepare($query_docente);
        $stmt->execute([
            ':id_docente' => $recomendacion['id_docente'],
            ':tipo_discapacidad' => $estudiante['id_tipo_discapacidad']
        ]);
        $docente_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($docente_data) {
            return [
                'exito' => true,
                'asignacion' => [
                    'id_estudiante' => $estudiante['id_estudiante'],
                    'estudiante' => $estudiante['nombres_completos'],
                    'id_tipo_discapacidad' => $estudiante['id_tipo_discapacidad'],
                    'nombre_discapacidad' => $estudiante['nombre_discapacidad'],
                    'peso_discapacidad' => $estudiante['peso_prioridad'],
                    'id_docente' => $docente_data['id_docente'],
                    'docente' => $docente_data['nombres_completos'],
                    'id_materia' => $estudiante['id_materia'],
                    'materia' => $estudiante['nombre_materia'],
                    'puntuacion_ahp' => $recomendacion['puntuacion_ahp'],
                    'tiene_experiencia_especifica' => $docente_data['tiene_experiencia_especifica'],
                    'nivel_competencia' => $docente_data['nivel_competencia_especifica'],
                    'ranking_original' => $docente_data['ranking_por_discapacidad'],
                    'capacidad_restante' => $recomendacion['capacidad_restante']
                ]
            ];
        }
        
        return [
            'exito' => false,
            'razon' => [
                'estudiante' => $estudiante['nombres_completos'],
                'discapacidad' => $estudiante['nombre_discapacidad'],
                'motivo' => 'No se pudo obtener información completa del docente recomendado'
            ]
        ];
    }
    
    /**
     * Actualiza la carga de un docente después de una asignación
     */
    private function actualizarCargaDocente(&$carga_docentes, $asignacion) {
        $docente_id = $asignacion['id_docente'];
        $tipo_discapacidad = $asignacion['id_tipo_discapacidad'];
        
        if (isset($carga_docentes[$docente_id])) {
            $carga_docentes[$docente_id]['total_actual']++;
            
            if (!isset($carga_docentes[$docente_id]['por_tipo'][$tipo_discapacidad])) {
                $carga_docentes[$docente_id]['por_tipo'][$tipo_discapacidad] = 0;
            }
            $carga_docentes[$docente_id]['por_tipo'][$tipo_discapacidad]++;
        }
    }
    
    /**
     * Confirma las asignaciones en la base de datos
     */
    private function confirmarAsignaciones($asignaciones, $ciclo_academico) {
        $query = "
            INSERT INTO asignaciones (
                id_docente, id_estudiante, id_tipo_discapacidad, id_materia,
                ciclo_academico, materia, numero_estudiantes, puntuacion_ahp, estado
            ) VALUES (
                :id_docente, :id_estudiante, :id_tipo_discapacidad, :id_materia,
                :ciclo_academico, :materia, 1, :puntuacion_ahp, 'Activa'
            )
        ";
        
        $stmt = $this->conn->prepare($query);
        
        foreach ($asignaciones as $asignacion) {
            $stmt->execute([
                ':id_docente' => $asignacion['id_docente'],
                ':id_estudiante' => $asignacion['id_estudiante'],
                ':id_tipo_discapacidad' => $asignacion['id_tipo_discapacidad'],
                ':id_materia' => $asignacion['id_materia'],
                ':ciclo_academico' => $ciclo_academico,
                ':materia' => $asignacion['materia'],
                ':puntuacion_ahp' => $asignacion['puntuacion_ahp']
            ]);
        }
    }
    
    /**
     * Calcula estadísticas de la asignación
     */
    private function calcularEstadisticas($asignaciones) {
        if (empty($asignaciones)) {
            return [
                'total_asignaciones' => 0,
                'puntuacion_promedio' => 0,
                'con_experiencia_especifica' => 0,
                'porcentaje_experiencia' => 0,
                'distribucion_por_tipo' => []
            ];
        }
        
        $total = count($asignaciones);
        $puntuacion_promedio = array_sum(array_column($asignaciones, 'puntuacion_ahp')) / $total;
        $con_experiencia = count(array_filter($asignaciones, function($a) { 
            return $a['tiene_experiencia_especifica']; 
        }));
        
        // Distribución por tipo de discapacidad
        $por_discapacidad = [];
        foreach ($asignaciones as $asignacion) {
            $tipo = $asignacion['nombre_discapacidad'];
            $por_discapacidad[$tipo] = ($por_discapacidad[$tipo] ?? 0) + 1;
        }
        
        return [
            'total_asignaciones' => $total,
            'puntuacion_promedio' => round($puntuacion_promedio, 3),
            'con_experiencia_especifica' => $con_experiencia,
            'porcentaje_experiencia' => round(($con_experiencia / $total) * 100, 1),
            'distribucion_por_tipo' => $por_discapacidad
        ];
    }
}
?>