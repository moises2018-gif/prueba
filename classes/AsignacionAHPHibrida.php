<?php
/**
 * CLASE IMPLEMENTACIÓN AHP HÍBRIDO
 * Archivo: classes/AsignacionAHPHibrida.php
 * 
 * Combina AHP Tradicional para criterios objetivos y AHP Difuso para criterios subjetivos
 */

class AsignacionAHPHibrida {
    private $conn;
    private $logger;
    
    // Configuración del método híbrido
    private $criterios_objetivos = ['AED', 'NFA']; // Años Experiencia, Nivel Formación
    private $criterios_subjetivos = ['FSI', 'EPR', 'AMI']; // Formación Específica, Experiencia Práctica, Adaptaciones
    
    // Números triangulares difusos
    private $escala_difusa = [
        1 => [1, 1, 1],     // Igual importancia
        2 => [1, 2, 3],     // Intermedio
        3 => [2, 3, 4],     // Moderada
        4 => [3, 4, 5],     // Intermedio
        5 => [4, 5, 6],     // Fuerte
        6 => [5, 6, 7],     // Intermedio
        7 => [6, 7, 8],     // Muy fuerte
        8 => [7, 8, 9],     // Intermedio
        9 => [9, 9, 9]      // Extrema
    ];
    
    public function __construct($conexion) {
        $this->conn = $conexion;
        $this->logger = new Logger();
        
        $this->logger->info('AsignacionAHPHibrida inicializada', [
            'criterios_objetivos' => $this->criterios_objetivos,
            'criterios_subjetivos' => $this->criterios_subjetivos
        ]);
    }
    
    /**
     * MÉTODO PRINCIPAL: Ejecuta asignación usando AHP Híbrido
     */
    public function ejecutarAsignacionHibrida($ciclo_academico, $preview = false) {
        $inicio = microtime(true);
        
        try {
            $this->logger->info("Iniciando asignación híbrida para ciclo: $ciclo_academico", ['preview' => $preview]);
            
            if (!$preview) {
                $this->conn->beginTransaction();
            }
            
            // 1. Obtener estudiantes priorizados
            $estudiantes = $this->obtenerEstudiantesPriorizados($ciclo_academico);
            
            if (empty($estudiantes)) {
                throw new Exception("No hay estudiantes sin asignación para el ciclo académico $ciclo_academico");
            }
            
            // 2. Calcular ranking híbrido para cada docente y tipo de discapacidad
            $ranking_hibrido = $this->calcularRankingHibrido();
            
            // 3. Obtener carga actual de docentes
            $carga_docentes = $this->obtenerCargaDocentes();
            
            // 4. Ejecutar asignaciones
            $asignaciones = [];
            $rechazados = [];
            
            foreach ($estudiantes as $estudiante) {
                $resultado = $this->asignarEstudianteHibrido($estudiante, $ranking_hibrido, $carga_docentes);
                
                if ($resultado['exito']) {
                    $asignaciones[] = $resultado['asignacion'];
                    $this->actualizarCargaDocente($carga_docentes, $resultado['asignacion']);
                } else {
                    $rechazados[] = $resultado['razon'];
                }
            }
            
            // 5. Calcular estadísticas híbridas
            $estadisticas = $this->calcularEstadisticasHibridas($asignaciones);
            
            if ($preview) {
                $tiempo_ejecucion = round((microtime(true) - $inicio) * 1000, 2);
                
                return [
                    'asignaciones' => $asignaciones,
                    'rechazados' => $rechazados,
                    'estadisticas' => $estadisticas,
                    'metodo' => 'AHP_HIBRIDO',
                    'tiempo_ejecucion' => $tiempo_ejecucion
                ];
            }
            
            // 6. Confirmar asignaciones en BD
            if (!empty($asignaciones)) {
                $this->confirmarAsignacionesHibridas($asignaciones, $ciclo_academico);
                $this->conn->commit();
                
                $tiempo_ejecucion = round((microtime(true) - $inicio) * 1000, 2);
                
                return [
                    'exito' => true,
                    'total' => count($asignaciones),
                    'estadisticas' => $estadisticas,
                    'metodo' => 'AHP_HIBRIDO',
                    'tiempo_ejecucion' => $tiempo_ejecucion
                ];
            } else {
                $this->conn->rollBack();
                throw new Exception("No se pudo realizar ninguna asignación híbrida.");
            }
            
        } catch (Exception $e) {
            if (!$preview) {
                $this->conn->rollBack();
            }
            
            $this->logger->error('Error en asignación híbrida', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * NÚCLEO DEL ALGORITMO: Calcula ranking híbrido combinando métodos
     */
    private function calcularRankingHibrido() {
        $this->logger->info('Iniciando cálculo de ranking híbrido');
        
        // Obtener datos base de docentes
        $query_docentes = "
            SELECT d.id_docente, d.nombres_completos, d.facultad,
                   vp.puntuacion_fsi, vp.puntuacion_epr, vp.puntuacion_ami,
                   vp.puntuacion_aed, vp.puntuacion_nfa
            FROM docentes d
            JOIN vista_puntuaciones_ahp vp ON d.id_docente = vp.id_docente
            ORDER BY d.id_docente
        ";
        
        $stmt = $this->conn->prepare($query_docentes);
        $stmt->execute();
        $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener tipos de discapacidad y sus pesos
        $query_tipos = "
            SELECT id_tipo_discapacidad, nombre_discapacidad, peso_prioridad 
            FROM tipos_discapacidad 
            ORDER BY peso_prioridad DESC
        ";
        $stmt_tipos = $this->conn->prepare($query_tipos);
        $stmt_tipos->execute();
        $tipos_discapacidad = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);
        
        $ranking_hibrido = [];
        
        foreach ($tipos_discapacidad as $tipo) {
            $tipo_id = $tipo['id_tipo_discapacidad'];
            
            // Obtener pesos específicos para este tipo de discapacidad
            $pesos_especificos = $this->obtenerPesosEspecificos($tipo_id);
            
            foreach ($docentes as $docente) {
                // Calcular puntuación híbrida
                $puntuacion_hibrida = $this->calcularPuntuacionHibrida(
                    $docente, 
                    $pesos_especificos, 
                    $tipo_id
                );
                
                // Obtener experiencia específica
                $experiencia_especifica = $this->obtenerExperienciaEspecifica(
                    $docente['id_docente'], 
                    $tipo_id
                );
                
                // Aplicar bonificación por experiencia específica (método difuso)
                $puntuacion_final = $this->aplicarBonificacionDifusa(
                    $puntuacion_hibrida, 
                    $experiencia_especifica
                );
                
                $ranking_hibrido[$tipo_id][] = [
                    'id_docente' => $docente['id_docente'],
                    'nombres_completos' => $docente['nombres_completos'],
                    'facultad' => $docente['facultad'],
                    'puntuacion_hibrida' => $puntuacion_hibrida,
                    'puntuacion_final' => $puntuacion_final,
                    'experiencia_especifica' => $experiencia_especifica,
                    'metodo_calculo' => 'hibrido',
                    'intervalos_confianza' => $this->calcularIntervalosConfianza($puntuacion_hibrida)
                ];
            }
            
            // Ordenar por puntuación final descendente
            usort($ranking_hibrido[$tipo_id], function($a, $b) {
                return $b['puntuacion_final'] <=> $a['puntuacion_final'];
            });
            
            // Asignar rankings
            foreach ($ranking_hibrido[$tipo_id] as $index => &$docente) {
                $docente['ranking_hibrido'] = $index + 1;
            }
        }
        
        $this->logger->info('Ranking híbrido calculado', [
            'tipos_discapacidad' => count($tipos_discapacidad),
            'docentes_por_tipo' => array_map('count', $ranking_hibrido)
        ]);
        
        return $ranking_hibrido;
    }
    
    /**
     * CÁLCULO HÍBRIDO: Combina AHP tradicional y difuso según criterio
     */
    private function calcularPuntuacionHibrida($docente, $pesos_especificos, $tipo_discapacidad) {
        $puntuacion_total = 0;
        
        // CRITERIOS OBJETIVOS - AHP Tradicional (valores exactos)
        foreach ($this->criterios_objetivos as $criterio) {
            $codigo_criterio = strtolower($criterio);
            $puntuacion_criterio = $docente["puntuacion_$codigo_criterio"];
            $peso_criterio = $pesos_especificos[$criterio] ?? 0;
            
            // Aplicar AHP tradicional (valores crisp)
            $contribucion = $puntuacion_criterio * $peso_criterio;
            $puntuacion_total += $contribucion;
            
            $this->logger->debug("Criterio objetivo $criterio", [
                'docente' => $docente['nombres_completos'],
                'puntuacion_crisp' => $puntuacion_criterio,
                'peso' => $peso_criterio,
                'contribucion' => $contribucion
            ]);
        }
        
        // CRITERIOS SUBJETIVOS - AHP Difuso (con intervalos de confianza)
        foreach ($this->criterios_subjetivos as $criterio) {
            $codigo_criterio = strtolower($criterio);
            $puntuacion_base = $docente["puntuacion_$codigo_criterio"];
            $peso_base = $pesos_especificos[$criterio] ?? 0;
            
            // Convertir a números triangulares difusos
            $puntuacion_difusa = $this->convertirAPuntuacionDifusa($puntuacion_base, $criterio);
            $peso_difuso = $this->convertirAPesoDifuso($peso_base, $criterio, $tipo_discapacidad);
            
            // Aplicar operaciones difusas
            $contribucion_difusa = $this->multiplicacionDifusa($puntuacion_difusa, $peso_difuso);
            
            // Defuzzificar usando método del centroide
            $contribucion = $this->defuzzificar($contribucion_difusa);
            $puntuacion_total += $contribucion;
            
            $this->logger->debug("Criterio subjetivo $criterio", [
                'docente' => $docente['nombres_completos'],
                'puntuacion_base' => $puntuacion_base,
                'puntuacion_difusa' => $puntuacion_difusa,
                'peso_difuso' => $peso_difuso,
                'contribucion_defuzzificada' => $contribucion
            ]);
        }
        
        return round($puntuacion_total, 6);
    }
    
    /**
     * Convierte puntuación crisp a número triangular difuso
     */
    private function convertirAPuntuacionDifusa($puntuacion_base, $criterio) {
        // Definir incertidumbre según el criterio
        $incertidumbre = [
            'FSI' => 0.1,  // Formación específica: 10% incertidumbre
            'EPR' => 0.15, // Experiencia práctica: 15% incertidumbre (más subjetiva)
            'AMI' => 0.12  // Adaptaciones metodológicas: 12% incertidumbre
        ];
        
        $delta = $incertidumbre[$criterio] ?? 0.1;
        
        return [
            'l' => max(0, $puntuacion_base - $delta),  // Límite inferior
            'm' => $puntuacion_base,                   // Valor modal
            'u' => min(1, $puntuacion_base + $delta)   // Límite superior
        ];
    }
    
    /**
     * Convierte peso crisp a peso difuso
     */
    private function convertirAPesoDifuso($peso_base, $criterio, $tipo_discapacidad) {
        // Incertidumbre en pesos según importancia del criterio para el tipo de discapacidad
        $incertidumbre_pesos = [
            1 => ['FSI' => 0.03, 'EPR' => 0.05, 'AMI' => 0.02], // Psicosocial
            2 => ['FSI' => 0.02, 'EPR' => 0.04, 'AMI' => 0.02], // Auditiva
            3 => ['FSI' => 0.05, 'EPR' => 0.03, 'AMI' => 0.03], // Intelectual
            4 => ['FSI' => 0.03, 'EPR' => 0.02, 'AMI' => 0.02], // Visual
            5 => ['FSI' => 0.03, 'EPR' => 0.02, 'AMI' => 0.04]  // Física
        ];
        
        $delta = $incertidumbre_pesos[$tipo_discapacidad][$criterio] ?? 0.03;
        
        return [
            'l' => max(0, $peso_base - $delta),
            'm' => $peso_base,
            'u' => min(1, $peso_base + $delta)
        ];
    }
    
    /**
     * Multiplicación de números triangulares difusos
     */
    private function multiplicacionDifusa($num1, $num2) {
        return [
            'l' => $num1['l'] * $num2['l'],
            'm' => $num1['m'] * $num2['m'],
            'u' => $num1['u'] * $num2['u']
        ];
    }
    
    /**
     * Defuzzificación usando método del centroide
     */
    private function defuzzificar($numero_difuso) {
        return ($numero_difuso['l'] + $numero_difuso['m'] + $numero_difuso['u']) / 3;
    }
    
    /**
     * Calcula intervalos de confianza para la puntuación
     */
    private function calcularIntervalosConfianza($puntuacion_hibrida) {
        // Simular intervalo de confianza del 95%
        $margen_error = $puntuacion_hibrida * 0.05; // 5% de margen
        
        return [
            'inferior' => max(0, $puntuacion_hibrida - $margen_error),
            'superior' => min(1, $puntuacion_hibrida + $margen_error),
            'confianza' => '95%'
        ];
    }
    
    /**
     * Aplica bonificación difusa por experiencia específica
     */
    private function aplicarBonificacionDifusa($puntuacion_base, $experiencia) {
        if (!$experiencia['tiene_experiencia']) {
            return $puntuacion_base;
        }
        
        // Bonificación difusa según nivel de competencia
        $bonificaciones_difusas = [
            'Básico'     => ['l' => 1.00, 'm' => 1.02, 'u' => 1.05],
            'Intermedio' => ['l' => 1.03, 'm' => 1.05, 'u' => 1.08],
            'Avanzado'   => ['l' => 1.08, 'm' => 1.10, 'u' => 1.15],
            'Experto'    => ['l' => 1.12, 'm' => 1.15, 'u' => 1.20]
        ];
        
        $bonificacion = $bonificaciones_difusas[$experiencia['nivel_competencia']] ?? 
                       $bonificaciones_difusas['Básico'];
        
        // Aplicar bonificación difusa y defuzzificar
        $factor_bonificacion = $this->defuzzificar($bonificacion);
        
        return $puntuacion_base * $factor_bonificacion;
    }
    
    /**
     * Obtiene estudiantes priorizados por peso de discapacidad
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
     * Obtiene pesos específicos por tipo de discapacidad
     */
    private function obtenerPesosEspecificos($tipo_discapacidad) {
        $query = "
            SELECT ca.codigo_criterio, pcd.peso_especifico
            FROM pesos_criterios_discapacidad pcd
            JOIN criterios_ahp ca ON pcd.id_criterio = ca.id_criterio
            WHERE pcd.id_tipo_discapacidad = :tipo
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':tipo' => $tipo_discapacidad]);
        $pesos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $pesos_array = [];
        foreach ($pesos as $peso) {
            $pesos_array[$peso['codigo_criterio']] = $peso['peso_especifico'];
        }
        
        return $pesos_array;
    }
    
    /**
     * Obtiene experiencia específica de un docente para un tipo de discapacidad
     */
    private function obtenerExperienciaEspecifica($id_docente, $tipo_discapacidad) {
        $query = "
            SELECT tiene_experiencia, años_experiencia, nivel_competencia
            FROM experiencia_docente_discapacidad
            WHERE id_docente = :docente AND id_tipo_discapacidad = :tipo
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':docente' => $id_docente, ':tipo' => $tipo_discapacidad]);
        $experiencia = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'tiene_experiencia' => $experiencia['tiene_experiencia'] ?? false,
            'años_experiencia' => $experiencia['años_experiencia'] ?? 0,
            'nivel_competencia' => $experiencia['nivel_competencia'] ?? 'Básico'
        ];
    }
    
    /**
     * Asigna estudiante usando ranking híbrido
     */
    private function asignarEstudianteHibrido($estudiante, $ranking_hibrido, &$carga_docentes) {
        $tipo_discapacidad = $estudiante['id_tipo_discapacidad'];
        $facultad_estudiante = $estudiante['facultad'];
        
        // Obtener ranking para este tipo de discapacidad
        $docentes_candidatos = $ranking_hibrido[$tipo_discapacidad] ?? [];
        
        // Filtrar por facultad y disponibilidad
        foreach ($docentes_candidatos as $docente) {
            if ($docente['facultad'] === $facultad_estudiante) {
                $id_docente = $docente['id_docente'];
                
                // Verificar disponibilidad
                if ($this->verificarDisponibilidad($id_docente, $tipo_discapacidad, $carga_docentes)) {
                    return [
                        'exito' => true,
                        'asignacion' => [
                            'id_estudiante' => $estudiante['id_estudiante'],
                            'estudiante' => $estudiante['nombres_completos'],
                            'id_tipo_discapacidad' => $tipo_discapacidad,
                            'nombre_discapacidad' => $estudiante['nombre_discapacidad'],
                            'peso_discapacidad' => $estudiante['peso_prioridad'],
                            'id_docente' => $id_docente,
                            'docente' => $docente['nombres_completos'],
                            'id_materia' => $estudiante['id_materia'],
                            'materia' => $estudiante['nombre_materia'],
                            'puntuacion_ahp' => $docente['puntuacion_final'],
                            'metodo' => 'AHP_HIBRIDO',
                            'ranking_hibrido' => $docente['ranking_hibrido'],
                            'intervalos_confianza' => $docente['intervalos_confianza'],
                            'tiene_experiencia_especifica' => $docente['experiencia_especifica']['tiene_experiencia'],
                            'nivel_competencia' => $docente['experiencia_especifica']['nivel_competencia']
                        ]
                    ];
                }
            }
        }
        
        return [
            'exito' => false,
            'razon' => [
                'estudiante' => $estudiante['nombres_completos'],
                'discapacidad' => $estudiante['nombre_discapacidad'],
                'motivo' => 'No hay docentes disponibles con el método híbrido'
            ]
        ];
    }
    
    /**
     * Verifica disponibilidad de docente
     */
    private function verificarDisponibilidad($id_docente, $tipo_discapacidad, $carga_docentes) {
        if (!isset($carga_docentes[$id_docente])) {
            return false;
        }
        
        $carga = $carga_docentes[$id_docente];
        
        // Verificar capacidad total
        if ($carga['total_actual'] >= $carga['capacidad_maxima']) {
            return false;
        }
        
        // Verificar límite por tipo de discapacidad
        $actual_tipo = $carga['por_tipo'][$tipo_discapacidad] ?? 0;
        if ($actual_tipo >= $carga['max_por_tipo']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtiene carga actual de docentes
     */
    private function obtenerCargaDocentes() {
        $query = "
            SELECT 
                d.id_docente,
                d.nombres_completos,
                d.facultad,
                COALESCE(la.maximo_estudiantes_nee, 7) as capacidad_maxima,
                COALESCE(la.maximo_por_tipo_discapacidad, 3) as max_por_tipo,
                COALESCE(carga.total_actual, 0) as total_actual,
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
                    WHERE estado = 'Activa'
                    GROUP BY id_docente, id_tipo_discapacidad
                ) sub
                GROUP BY id_docente
            ) carga ON d.id_docente = carga.id_docente
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $carga = [];
        foreach ($docentes as $docente) {
            $carga[$docente['id_docente']] = [
                'nombres_completos' => $docente['nombres_completos'],
                'facultad' => $docente['facultad'],
                'capacidad_maxima' => $docente['capacidad_maxima'],
                'max_por_tipo' => $docente['max_por_tipo'],
                'total_actual' => $docente['total_actual'],
                'por_tipo' => json_decode($docente['distribucion_tipos'], true) ?: []
            ];
        }
        
        return $carga;
    }
    
    /**
     * Actualiza carga de docente después de asignación
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
     * Confirma asignaciones híbridas en BD
     */
    private function confirmarAsignacionesHibridas($asignaciones, $ciclo_academico) {
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
        
        $this->logger->info('Asignaciones híbridas confirmadas', [
            'total' => count($asignaciones),
            'ciclo' => $ciclo_academico
        ]);
    }
    
    /**
     * Calcula estadísticas específicas del método híbrido
     */
    private function calcularEstadisticasHibridas($asignaciones) {
        if (empty($asignaciones)) {
            return [
                'total_asignaciones' => 0,
                'metodo' => 'AHP_HIBRIDO',
                'criterios_objetivos' => $this->criterios_objetivos,
                'criterios_subjetivos' => $this->criterios_subjetivos
            ];
        }
        
        $total = count($asignaciones);
        $puntuacion_promedio = array_sum(array_column($asignaciones, 'puntuacion_ahp')) / $total;
        $con_experiencia = count(array_filter($asignaciones, function($a) { 
            return $a['tiene_experiencia_especifica']; 
        }));
        
        // Calcular intervalo de confianza promedio
        $intervalos = array_column($asignaciones, 'intervalos_confianza');
        $promedio_inferior = array_sum(array_column($intervalos, 'inferior')) / $total;
        $promedio_superior = array_sum(array_column($intervalos, 'superior')) / $total;
        
        // Distribución por tipo de discapacidad
        $por_discapacidad = [];
        foreach ($asignaciones as $asignacion) {
            $tipo = $asignacion['nombre_discapacidad'];
            $por_discapacidad[$tipo] = ($por_discapacidad[$tipo] ?? 0) + 1;
        }
        
        return [
            'total_asignaciones' => $total,
            'puntuacion_promedio' => round($puntuacion_promedio, 4),
            'con_experiencia_especifica' => $con_experiencia,
            'porcentaje_experiencia' => round(($con_experiencia / $total) * 100, 1),
            'intervalo_confianza_promedio' => [
                'inferior' => round($promedio_inferior, 4),
                'superior' => round($promedio_superior, 4)
            ],
            'distribucion_por_tipo' => $por_discapacidad,
            'metodo' => 'AHP_HIBRIDO',
            'criterios_objetivos' => $this->criterios_objetivos,
            'criterios_subjetivos' => $this->criterios_subjetivos,
            'ventajas_hibrido' => [
                'precision_objetiva' => 'Criterios AED y NFA con valores exactos',
                'flexibilidad_subjetiva' => 'Criterios FSI, EPR y AMI con intervalos difusos',
                'robustez_mejorada' => 'Combina precisión numérica con manejo de incertidumbre'
            ]
        ];
    }
    
    /**
     * Genera reporte comparativo entre métodos
     */
    public function generarReporteComparativo($ciclo_academico) {
        $inicio = microtime(true);
        
        // Ejecutar método híbrido
        $resultado_hibrido = $this->ejecutarAsignacionHibrida($ciclo_academico, true);
        
        // Simular método tradicional para comparación
        $resultado_tradicional = $this->simularMetodoTradicional($ciclo_academico);
        
        $tiempo_total = round((microtime(true) - $inicio) * 1000, 2);
        
        return [
            'comparacion' => [
                'hibrido' => $resultado_hibrido['estadisticas'],
                'tradicional' => $resultado_tradicional
            ],
            'recomendacion' => $this->analizarMejorMetodo($resultado_hibrido, $resultado_tradicional),
            'tiempo_analisis' => $tiempo_total
        ];
    }
    
    /**
     * Simula método tradicional para comparación
     */
    private function simularMetodoTradicional($ciclo_academico) {
        // Usar vista existente del sistema tradicional
        $query = "
            SELECT 
                COUNT(*) as total_posibles,
                AVG(vra.puntuacion_especifica_discapacidad) as puntuacion_promedio_tradicional,
                COUNT(CASE WHEN edd.tiene_experiencia = 1 THEN 1 END) as con_experiencia_tradicional
            FROM vista_ranking_ahp_especifico vra
            LEFT JOIN experiencia_docente_discapacidad edd 
                ON vra.id_docente = edd.id_docente 
                AND vra.id_tipo_discapacidad = edd.id_tipo_discapacidad
            WHERE vra.facultad IN (
                SELECT DISTINCT facultad 
                FROM estudiantes 
                WHERE ciclo_academico = :ciclo
            )
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':ciclo' => $ciclo_academico]);
        $stats_tradicional = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'metodo' => 'AHP_TRADICIONAL',
            'puntuacion_promedio' => round($stats_tradicional['puntuacion_promedio_tradicional'], 4),
            'estimacion_experiencia' => round(($stats_tradicional['con_experiencia_tradicional'] / $stats_tradicional['total_posibles']) * 100, 1),
            'caracteristicas' => [
                'precision_numerica' => 'Alta para todos los criterios',
                'manejo_incertidumbre' => 'Limitado',
                'flexibilidad' => 'Baja'
            ]
        ];
    }
    
    /**
     * Analiza cuál método es mejor según contexto
     */
    private function analizarMejorMetodo($hibrido, $tradicional) {
        $puntuacion_hibrida = $hibrido['estadisticas']['puntuacion_promedio'];
        $puntuacion_tradicional = $tradicional['puntuacion_promedio'];
        
        $diferencia_porcentual = (($puntuacion_hibrida - $puntuacion_tradicional) / $puntuacion_tradicional) * 100;
        
        if (abs($diferencia_porcentual) < 2) {
            $recomendacion = 'HIBRIDO_PREFERIBLE';
            $razon = 'Resultados similares, pero el método híbrido ofrece mayor robustez y manejo de incertidumbre';
        } elseif ($diferencia_porcentual > 2) {
            $recomendacion = 'HIBRIDO_SUPERIOR';
            $razon = 'El método híbrido supera al tradicional en puntuación promedio';
        } else {
            $recomendacion = 'ANALISIS_DETALLADO';
            $razon = 'Se requiere análisis más detallado según contexto específico';
        }
        
        return [
            'metodo_recomendado' => $recomendacion,
            'razon' => $razon,
            'diferencia_porcentual' => round($diferencia_porcentual, 2),
            'ventajas_hibrido' => [
                'Manejo de incertidumbre en criterios subjetivos',
                'Precisión numérica en criterios objetivos',
                'Intervalos de confianza para decisiones más informadas',
                'Mayor flexibilidad en evaluación de competencias'
            ],
            'ventajas_tradicional' => [
                'Simplicidad computacional',
                'Resultados determinísticos',
                'Fácil interpretación numérica'
            ]
        ];
    }
}
?>