<?php
/**
 * GESTORA DE MATERIAS PARA ASIGNACIONES
 * Archivo: classes/GestorMaterias.php
 * 
 * Clase especializada para manejar la selección y gestión de materias
 * en el proceso de asignación de docentes
 */

class GestorMaterias {
    private $conn;
    private $logger;
    
    public function __construct($conexion) {
        $this->conn = $conexion;
        $this->logger = new Logger();
        
        $this->logger->info('GestorMaterias inicializado');
    }
    
    /**
     * Obtiene todas las materias disponibles agrupadas por facultad y ciclo
     */
    public function obtenerMateriasDisponibles($ciclo_academico = null, $incluir_todas = true) {
        $query_base = "
            SELECT m.id_materia, m.nombre_materia, m.facultad, m.ciclo_academico,
                   COUNT(a.id_asignacion) as asignaciones_actuales
            FROM materias m
            LEFT JOIN asignaciones a ON m.id_materia = a.id_materia AND a.estado = 'Activa'
        ";
        
        $conditions = [];
        $params = [];
        
        if ($ciclo_academico && !$incluir_todas) {
            $conditions[] = "m.ciclo_academico = :ciclo";
            $params[':ciclo'] = $ciclo_academico;
        } elseif ($ciclo_academico) {
            $conditions[] = "(m.ciclo_academico = :ciclo OR m.ciclo_academico IS NULL)";
            $params[':ciclo'] = $ciclo_academico;
        }
        
        if (!empty($conditions)) {
            $query_base .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $query_base .= " GROUP BY m.id_materia ORDER BY m.facultad, m.nombre_materia";
        
        $stmt = $this->conn->prepare($query_base);
        $stmt->execute($params);
        $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->logger->info('Materias obtenidas', [
            'ciclo_academico' => $ciclo_academico,
            'total_materias' => count($materias),
            'incluir_todas' => $incluir_todas
        ]);
        
        return $this->agruparMateriasPorFacultad($materias);
    }
    
    /**
     * Sugiere la mejor materia para un estudiante basado en varios factores
     */
    public function sugerirMateriaParaEstudiante($estudiante, $ciclo_academico) {
        $facultad_estudiante = $estudiante['facultad'];
        $tipo_discapacidad = $estudiante['id_tipo_discapacidad'];
        
        // 1. Buscar materias de la misma facultad del estudiante
        $query_facultad = "
            SELECT m.id_materia, m.nombre_materia, m.facultad,
                   COUNT(a.id_asignacion) as uso_frecuente,
                   AVG(CASE WHEN a.id_tipo_discapacidad = :tipo_discapacidad THEN 1 ELSE 0 END) as afinidad_discapacidad
            FROM materias m
            LEFT JOIN asignaciones a ON m.id_materia = a.id_materia AND a.estado = 'Activa'
            WHERE m.facultad = :facultad
            AND (m.ciclo_academico = :ciclo OR m.ciclo_academico IS NULL)
            GROUP BY m.id_materia
            ORDER BY afinidad_discapacidad DESC, uso_frecuente ASC, m.nombre_materia
            LIMIT 1";
        
        $stmt_facultad = $this->conn->prepare($query_facultad);
        $stmt_facultad->execute([
            ':facultad' => $facultad_estudiante,
            ':ciclo' => $ciclo_academico,
            ':tipo_discapacidad' => $tipo_discapacidad
        ]);
        $materia_facultad = $stmt_facultad->fetch(PDO::FETCH_ASSOC);
        
        if ($materia_facultad) {
            $this->logger->info('Materia sugerida por facultad', [
                'estudiante' => $estudiante['nombres_completos'],
                'materia' => $materia_facultad['nombre_materia'],
                'afinidad' => $materia_facultad['afinidad_discapacidad']
            ]);
            
            return $materia_facultad;
        }
        
        // 2. Fallback: buscar cualquier materia apropiada
        $query_general = "
            SELECT m.id_materia, m.nombre_materia, m.facultad
            FROM materias m
            WHERE m.ciclo_academico = :ciclo OR m.ciclo_academico IS NULL
            ORDER BY m.nombre_materia
            LIMIT 1";
        
        $stmt_general = $this->conn->prepare($query_general);
        $stmt_general->execute([':ciclo' => $ciclo_academico]);
        $materia_general = $stmt_general->fetch(PDO::FETCH_ASSOC);
        
        if ($materia_general) {
            $this->logger->info('Materia sugerida general', [
                'estudiante' => $estudiante['nombres_completos'],
                'materia' => $materia_general['nombre_materia']
            ]);
            
            return $materia_general;
        }
        
        // 3. Último fallback: crear materia temporal
        $this->logger->warning('No se encontraron materias, usando fallback', [
            'estudiante' => $estudiante['nombres_completos'],
            'facultad' => $facultad_estudiante
        ]);
        
        return [
            'id_materia' => null,
            'nombre_materia' => 'Materia por Asignar',
            'facultad' => $facultad_estudiante
        ];
    }
    
    /**
     * Valida que una materia seleccionada sea válida para un estudiante
     */
    public function validarMateriaParaEstudiante($id_materia, $estudiante) {
        if (!$id_materia) {
            return [
                'valida' => false,
                'razon' => 'No se ha seleccionado ninguna materia'
            ];
        }
        
        // Verificar que la materia existe
        $query_materia = "
            SELECT m.id_materia, m.nombre_materia, m.facultad, m.ciclo_academico
            FROM materias m
            WHERE m.id_materia = :id_materia";
        
        $stmt_materia = $this->conn->prepare($query_materia);
        $stmt_materia->execute([':id_materia' => $id_materia]);
        $materia = $stmt_materia->fetch(PDO::FETCH_ASSOC);
        
        if (!$materia) {
            return [
                'valida' => false,
                'razon' => 'La materia seleccionada no existe'
            ];
        }
        
        // Verificar compatibilidad con la facultad (advertencia, no error)
        $compatible_facultad = ($materia['facultad'] === $estudiante['facultad']);
        
        return [
            'valida' => true,
            'materia' => $materia,
            'compatible_facultad' => $compatible_facultad,
            'advertencia' => !$compatible_facultad ? 'La materia no pertenece a la facultad del estudiante' : null
        ];
    }
    
    /**
     * Obtiene estadísticas de uso de materias
     */
    public function obtenerEstadisticasMaterias($ciclo_academico = null) {
        $query = "
            SELECT 
                m.facultad,
                COUNT(DISTINCT m.id_materia) as total_materias,
                COUNT(a.id_asignacion) as total_asignaciones,
                AVG(CASE WHEN a.id_asignacion IS NOT NULL THEN 1 ELSE 0 END) as tasa_uso
            FROM materias m
            LEFT JOIN asignaciones a ON m.id_materia = a.id_materia AND a.estado = 'Activa'
        ";
        
        $params = [];
        if ($ciclo_academico) {
            $query .= " WHERE a.ciclo_academico = :ciclo OR a.ciclo_academico IS NULL";
            $params[':ciclo'] = $ciclo_academico;
        }
        
        $query .= " GROUP BY m.facultad ORDER BY total_asignaciones DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->logger->info('Estadísticas de materias calculadas', [
            'facultades_analizadas' => count($estadisticas),
            'ciclo' => $ciclo_academico
        ]);
        
        return $estadisticas;
    }
    
    /**
     * Obtiene materias más populares por tipo de discapacidad
     */
    public function obtenerMateriasPopularesPorDiscapacidad($limite = 5) {
        $query = "
            SELECT 
                td.nombre_discapacidad,
                m.nombre_materia,
                m.facultad,
                COUNT(a.id_asignacion) as frecuencia_uso,
                AVG(a.puntuacion_ahp) as puntuacion_promedio
            FROM asignaciones a
            JOIN tipos_discapacidad td ON a.id_tipo_discapacidad = td.id_tipo_discapacidad
            JOIN materias m ON a.id_materia = m.id_materia
            WHERE a.estado = 'Activa'
            GROUP BY td.id_tipo_discapacidad, m.id_materia
            HAVING frecuencia_uso >= 1
            ORDER BY td.peso_prioridad DESC, frecuencia_uso DESC
            LIMIT :limite";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $materias_populares = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->logger->info('Materias populares por discapacidad obtenidas', [
            'total_combinaciones' => count($materias_populares),
            'limite' => $limite
        ]);
        
        return $this->agruparPorDiscapacidad($materias_populares);
    }
    
    /**
     * Recomienda materias basadas en el historial de asignaciones exitosas
     */
    public function recomendarMateriasInteligentes($tipo_discapacidad, $facultad, $limite = 3) {
        $query = "
            SELECT 
                m.id_materia,
                m.nombre_materia,
                m.facultad,
                COUNT(a.id_asignacion) as exito_historico,
                AVG(a.puntuacion_ahp) as puntuacion_promedio,
                COUNT(DISTINCT a.id_docente) as docentes_diferentes,
                CASE 
                    WHEN m.facultad = :facultad THEN 3
                    ELSE 1
                END as bonus_facultad
            FROM materias m
            JOIN asignaciones a ON m.id_materia = a.id_materia
            WHERE a.id_tipo_discapacidad = :tipo_discapacidad
            AND a.estado = 'Activa'
            GROUP BY m.id_materia
            ORDER BY 
                (exito_historico * bonus_facultad * puntuacion_promedio) DESC,
                docentes_diferentes DESC
            LIMIT :limite";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':tipo_discapacidad', $tipo_discapacidad, PDO::PARAM_INT);
        $stmt->bindValue(':facultad', $facultad, PDO::PARAM_STR);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $recomendaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->logger->info('Recomendaciones inteligentes generadas', [
            'tipo_discapacidad' => $tipo_discapacidad,
            'facultad' => $facultad,
            'recomendaciones_encontradas' => count($recomendaciones)
        ]);
        
        return $recomendaciones;
    }
    
    /**
     * Valida un conjunto de asignaciones con materias seleccionadas
     */
    public function validarAsignacionesConMaterias($asignaciones_data) {
        $errores = [];
        $advertencias = [];
        $validas = 0;
        
        foreach ($asignaciones_data as $index => $asignacion) {
            $id_materia = $asignacion['id_materia'] ?? null;
            $estudiante = $asignacion['estudiante_info'] ?? [];
            
            if (!$id_materia) {
                $errores[] = "Asignación #" . ($index + 1) . ": No se ha seleccionado materia";
                continue;
            }
            
            $validacion = $this->validarMateriaParaEstudiante($id_materia, $estudiante);
            
            if (!$validacion['valida']) {
                $errores[] = "Asignación #" . ($index + 1) . ": " . $validacion['razon'];
            } else {
                $validas++;
                if (isset($validacion['advertencia'])) {
                    $advertencias[] = "Asignación #" . ($index + 1) . ": " . $validacion['advertencia'];
                }
            }
        }
        
        $resultado = [
            'valido' => empty($errores),
            'total_asignaciones' => count($asignaciones_data),
            'asignaciones_validas' => $validas,
            'errores' => $errores,
            'advertencias' => $advertencias
        ];
        
        $this->logger->info('Validación de asignaciones completada', $resultado);
        
        return $resultado;
    }
    
    /**
     * Agrupa materias por facultad
     */
    private function agruparMateriasPorFacultad($materias) {
        $agrupadas = [];
        
        foreach ($materias as $materia) {
            $facultad = $materia['facultad'];
            if (!isset($agrupadas[$facultad])) {
                $agrupadas[$facultad] = [];
            }
            $agrupadas[$facultad][] = $materia;
        }
        
        return $agrupadas;
    }
    
    /**
     * Agrupa materias populares por tipo de discapacidad
     */
    private function agruparPorDiscapacidad($materias_populares) {
        $agrupadas = [];
        
        foreach ($materias_populares as $materia) {
            $discapacidad = $materia['nombre_discapacidad'];
            if (!isset($agrupadas[$discapacidad])) {
                $agrupadas[$discapacidad] = [];
            }
            $agrupadas[$discapacidad][] = $materia;
        }
        
        return $agrupadas;
    }
    
    /**
     * Genera reporte de uso de materias
     */
    public function generarReporteUsoMaterias($ciclo_academico = null) {
        $inicio = microtime(true);
        
        $estadisticas_generales = $this->obtenerEstadisticasMaterias($ciclo_academico);
        $materias_populares = $this->obtenerMateriasPopularesPorDiscapacidad(10);
        
        // Materias menos utilizadas (oportunidades)
        $query_subutilizadas = "
            SELECT m.nombre_materia, m.facultad, 
                   COALESCE(COUNT(a.id_asignacion), 0) as uso_actual
            FROM materias m
            LEFT JOIN asignaciones a ON m.id_materia = a.id_materia AND a.estado = 'Activa'
            GROUP BY m.id_materia
            HAVING uso_actual <= 1
            ORDER BY uso_actual ASC, m.nombre_materia
            LIMIT 10";
        
        $stmt_sub = $this->conn->prepare($query_subutilizadas);
        $stmt_sub->execute();
        $materias_subutilizadas = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);
        
        $tiempo_ejecucion = round((microtime(true) - $inicio) * 1000, 2);
        
        $reporte = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ciclo_academico' => $ciclo_academico,
            'estadisticas_por_facultad' => $estadisticas_generales,
            'materias_populares_por_discapacidad' => $materias_populares,
            'materias_subutilizadas' => $materias_subutilizadas,
            'tiempo_ejecucion_ms' => $tiempo_ejecucion,
            'resumen' => [
                'total_facultades_analizadas' => count($estadisticas_generales),
                'materias_con_alta_demanda' => count($materias_populares),
                'oportunidades_mejora' => count($materias_subutilizadas)
            ]
        ];
        
        $this->logger->info('Reporte de uso de materias generado', [
            'ciclo' => $ciclo_academico,
            'tiempo_ms' => $tiempo_ejecucion
        ]);
        
        return $reporte;
    }
}
?>