<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Configuración de la base de datos
$host = '127.0.0.1';
$dbname = 'asignacion_nee';
$username = 'root'; // Ajusta según tu configuración
$password = '';     // Ajusta según tu configuración

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión: ' . $e->getMessage()]);
    exit;
}

// Obtener datos de la solicitud
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'cargar_datos':
        cargarDatos($pdo);
        break;
    case 'ejecutar_asignacion':
        ejecutarAsignacion($pdo);
        break;
    case 'limpiar_asignaciones':
        limpiarAsignaciones($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}

function cargarDatos($pdo) {
    try {
        // Cargar estudiantes
        $stmt = $pdo->query("SELECT * FROM estudiantes ORDER BY nombre");
        $estudiantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cargar docentes
        $stmt = $pdo->query("SELECT * FROM docentes ORDER BY nombre");
        $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cargar materias
        $stmt = $pdo->query("SELECT * FROM materias ORDER BY nombre");
        $materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cargar criterios
        $stmt = $pdo->query("SELECT * FROM criterios ORDER BY id");
        $criterios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cargar pesos (resultados)
        $stmt = $pdo->query("SELECT * FROM resultados ORDER BY criterio_id");
        $pesos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Cargar relación docentes-materias
        $stmt = $pdo->query("SELECT * FROM docentes_materias ORDER BY docente_id, materia_id");
        $docentes_materias = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'estudiantes' => $estudiantes,
            'docentes' => $docentes,
            'materias' => $materias,
            'criterios' => $criterios,
            'pesos' => $pesos,
            'docentes_materias' => $docentes_materias
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function ejecutarAsignacion($pdo) {
    try {
        // Cargar todos los datos necesarios
        $estudiantes = obtenerEstudiantes($pdo);
        $docentes = obtenerDocentes($pdo);
        $materias = obtenerMaterias($pdo);
        $pesos = obtenerPesos($pdo);
        $docentes_materias = obtenerDocentesMaterias($pdo);
        
        // Mapear tipos de discapacidad a columnas de seminarios
        $mapeoDiscapacidad = [
            'visual' => 'seminarios_visual',
            'auditiva' => 'seminarios_auditiva',
            'psicosocial' => 'seminarios_psicosocial',
            'fisica' => 'seminarios_fisica',
            'intelectual' => 'seminarios_intelectual'
        ];
        
        // Mapear criterios a tipos de discapacidad
        $mapeoCariterios = [
            1 => 'visual',      // Discapacidad Visual
            2 => 'auditiva',    // Discapacidad Auditiva
            3 => 'psicosocial', // Discapacidad Psicosocial
            4 => 'fisica',      // Discapacidad Física
            5 => 'intelectual'  // Discapacidad Intelectual
        ];
        
        // Crear mapas para acceso rápido
        $pesosPorCriterio = [];
        foreach ($pesos as $peso) {
            $pesosPorCriterio[$peso['criterio_id']] = floatval($peso['peso']);
        }
        
        // Crear mapa de materias por docente
        $materiasPorDocente = [];
        foreach ($docentes_materias as $dm) {
            if (!isset($materiasPorDocente[$dm['docente_id']])) {
                $materiasPorDocente[$dm['docente_id']] = [];
            }
            $materiasPorDocente[$dm['docente_id']][] = $dm['materia_id'];
        }
        
        $asignaciones = [];
        $docentesUsados = []; // Para evitar asignar múltiples estudiantes al mismo docente
        
        // Procesar cada estudiante
        foreach ($estudiantes as $estudiante) {
            $mejorDocente = null;
            $mejorMateria = null;
            $mejorPuntuacion = -1;
            
            $tipoDiscapacidad = $estudiante['tipo_discapacidad'];
            $porcentajeDiscapacidad = intval($estudiante['porcentaje_discapacidad']);
            
            // Evaluar cada docente
            foreach ($docentes as $docente) {
                // Saltar si el docente ya fue asignado
                if (in_array($docente['id'], $docentesUsados)) {
                    continue;
                }
                
                // Verificar si el docente tiene materias asignadas
                if (!isset($materiasPorDocente[$docente['id']])) {
                    continue;
                }
                
                // Calcular puntuación para este docente
                $puntuacion = calcularPuntuacionDocente(
                    $docente, 
                    $tipoDiscapacidad, 
                    $porcentajeDiscapacidad, 
                    $pesosPorCriterio, 
                    $mapeoDiscapacidad,
                    $mapeoCariterios
                );
                
                if ($puntuacion > $mejorPuntuacion) {
                    $mejorPuntuacion = $puntuacion;
                    $mejorDocente = $docente;
                    // Seleccionar la primera materia disponible del docente
                    $mejorMateria = $materiasPorDocente[$docente['id']][0];
                }
            }
            
            // Si se encontró un docente adecuado, crear la asignación
            if ($mejorDocente && $mejorMateria) {
                // Marcar docente como usado
                $docentesUsados[] = $mejorDocente['id'];
                
                // Buscar información de la materia
                $materiaNombre = '';
                foreach ($materias as $materia) {
                    if ($materia['id'] == $mejorMateria) {
                        $materiaNombre = $materia['nombre'];
                        break;
                    }
                }
                
                $asignacion = [
                    'estudiante_id' => $estudiante['id'],
                    'estudiante_nombre' => $estudiante['nombre'],
                    'estudiante_apellido' => $estudiante['apellido'],
                    'tipo_discapacidad' => $tipoDiscapacidad,
                    'docente_id' => $mejorDocente['id'],
                    'docente_nombre' => $mejorDocente['nombre'],
                    'docente_apellido' => $mejorDocente['apellido'],
                    'materia_id' => $mejorMateria,
                    'materia_nombre' => $materiaNombre,
                    'puntuacion' => $mejorPuntuacion
                ];
                
                $asignaciones[] = $asignacion;
                
                // Guardar en la base de datos
                guardarAsignacion($pdo, $estudiante['id'], $mejorDocente['id'], $mejorMateria);
            }
        }
        
        echo json_encode([
            'success' => true,
            'asignaciones' => $asignaciones,
            'total_asignados' => count($asignaciones),
            'total_estudiantes' => count($estudiantes)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function calcularPuntuacionDocente($docente, $tipoDiscapacidad, $porcentajeDiscapacidad, $pesosPorCriterio, $mapeoDiscapacidad, $mapeoCariterios) {
    $puntuacionTotal = 0;
    
    // Obtener experiencia del docente en el tipo de discapacidad específico
    $columnaExperiencia = $mapeoDiscapacidad[$tipoDiscapacidad];
    $experienciaDocente = intval($docente[$columnaExperiencia]);
    
    // Encontrar el criterio correspondiente al tipo de discapacidad
    $criterioId = null;
    foreach ($mapeoCariterios as $id => $tipo) {
        if ($tipo === $tipoDiscapacidad) {
            $criterioId = $id;
            break;
        }
    }
    
    if ($criterioId && isset($pesosPorCriterio[$criterioId])) {
        $peso = $pesosPorCriterio[$criterioId];
        
        // Normalizar la experiencia del docente (máximo 10 seminarios)
        $experienciaNormalizada = min($experienciaDocente / 10.0, 1.0);
        
        // Considerar el porcentaje de discapacidad (mayor porcentaje = mayor necesidad de experiencia)
        $factorDiscapacidad = $porcentajeDiscapacidad / 100.0;
        
        // Calcular puntuación: peso * experiencia * factor de discapacidad
        $puntuacionTotal = $peso * $experienciaNormalizada * (1 + $factorDiscapacidad);
        
        // Bonus por experiencia alta en el área específica
        if ($experienciaDocente >= 3) {
            $puntuacionTotal *= 1.2; // 20% de bonus
        }
        
        // Considerar experiencia general en otras áreas (menor peso)
        $experienciaGeneral = 0;
        foreach ($mapeoDiscapacidad as $tipoOtro => $columnaOtra) {
            if ($tipoOtro !== $tipoDiscapacidad) {
                $experienciaGeneral += intval($docente[$columnaOtra]);
            }
        }
        
        // Agregar un pequeño bonus por experiencia general
        $bonusGeneral = min($experienciaGeneral / 20.0, 0.1); // Máximo 10% de bonus
        $puntuacionTotal += $bonusGeneral;
    }
    
    return $puntuacionTotal;
}

function guardarAsignacion($pdo, $estudianteId, $docenteId, $materiaId) {
    try {
        $stmt = $pdo->prepare("INSERT INTO asignaciones (estudiante_id, docente_id, materia_id) VALUES (?, ?, ?)");
        $stmt->execute([$estudianteId, $docenteId, $materiaId]);
    } catch (Exception $e) {
        // Si hay error, no interrumpir el proceso completo
        error_log("Error al guardar asignación: " . $e->getMessage());
    }
}

function obtenerEstudiantes($pdo) {
    $stmt = $pdo->query("SELECT * FROM estudiantes ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerDocentes($pdo) {
    $stmt = $pdo->query("SELECT * FROM docentes ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerMaterias($pdo) {
    $stmt = $pdo->query("SELECT * FROM materias ORDER BY nombre");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerPesos($pdo) {
    $stmt = $pdo->query("SELECT * FROM resultados ORDER BY criterio_id");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerDocentesMaterias($pdo) {
    $stmt = $pdo->query("SELECT * FROM docentes_materias ORDER BY docente_id, materia_id");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function limpiarAsignaciones($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM asignaciones");
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Todas las asignaciones han sido eliminadas'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

// Función adicional para obtener estadísticas de asignaciones
function obtenerEstadisticasAsignaciones($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_asignaciones,
                COUNT(DISTINCT estudiante_id) as estudiantes_asignados,
                COUNT(DISTINCT docente_id) as docentes_utilizados
            FROM asignaciones
        ");
        $estadisticas = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Obtener distribución por tipo de discapacidad
        $stmt = $pdo->query("
            SELECT 
                e.tipo_discapacidad,
                COUNT(*) as cantidad
            FROM asignaciones a
            JOIN estudiantes e ON a.estudiante_id = e.id
            GROUP BY e.tipo_discapacidad
        ");
        $distribucion = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'estadisticas_generales' => $estadisticas,
            'distribucion_por_discapacidad' => $distribucion
        ];
    } catch (Exception $e) {
        return null;
    }
}

// Función para validar la consistencia de los datos
function validarDatos($pdo) {
    $errores = [];
    
    try {
        // Verificar que existan estudiantes
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM estudiantes");
        $totalEstudiantes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        if ($totalEstudiantes == 0) {
            $errores[] = "No hay estudiantes registrados";
        }
        
        // Verificar que existan docentes
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM docentes");
        $totalDocentes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        if ($totalDocentes == 0) {
            $errores[] = "No hay docentes registrados";
        }
        
        // Verificar que existan materias
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM materias");
        $totalMaterias = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        if ($totalMaterias == 0) {
            $errores[] = "No hay materias registradas";
        }
        
        // Verificar que existan pesos calculados
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM resultados WHERE peso > 0");
        $totalPesos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        if ($totalPesos == 0) {
            $errores[] = "No hay pesos calculados para los criterios";
        }
        
        // Verificar relación docentes-materias
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM docentes_materias");
        $totalRelaciones = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        if ($totalRelaciones == 0) {
            $errores[] = "No hay relaciones docente-materia definidas";
        }
        
    } catch (Exception $e) {
        $errores[] = "Error al validar datos: " . $e->getMessage();
    }
    
    return $errores;
}

// Función para generar reporte de asignaciones
function generarReporte($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                e.nombre as estudiante_nombre,
                e.apellido as estudiante_apellido,
                e.tipo_discapacidad,
                e.porcentaje_discapacidad,
                d.nombre as docente_nombre,
                d.apellido as docente_apellido,
                m.nombre as materia_nombre,
                CASE e.tipo_discapacidad
                    WHEN 'visual' THEN d.seminarios_visual
                    WHEN 'auditiva' THEN d.seminarios_auditiva
                    WHEN 'psicosocial' THEN d.seminarios_psicosocial
                    WHEN 'fisica' THEN d.seminarios_fisica
                    WHEN 'intelectual' THEN d.seminarios_intelectual
                END as experiencia_docente
            FROM asignaciones a
            JOIN estudiantes e ON a.estudiante_id = e.id
            JOIN docentes d ON a.docente_id = d.id
            JOIN materias m ON a.materia_id = m.id
            ORDER BY e.tipo_discapacidad, e.nombre
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

?>