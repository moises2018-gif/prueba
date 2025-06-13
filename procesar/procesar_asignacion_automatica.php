<?php
/**
 * PROCESADOR DE ASIGNACIÓN AUTOMÁTICA - VERSIÓN OPTIMIZADA COMPLETA
 * Archivo: procesar/procesar_asignacion_automatica.php
 * 
 * REEMPLAZA TU ARCHIVO ACTUAL CON ESTE CÓDIGO COMPLETO
 */

// Cargar configuración y clases
require_once '../includes/config.php';
require_once '../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/asignacion.php?error=Método no permitido");
    exit();
}

$conn = ConexionBD();
if (!$conn) {
    header("Location: ../pages/asignacion.php?error=Error de conexión a la base de datos");
    exit();
}

$ciclo_academico = trim($_POST['ciclo_academico'] ?? '');

// Validar entrada
if (empty($ciclo_academico)) {
    header("Location: ../pages/asignacion.php?error=Ciclo académico requerido");
    exit();
}

try {
    // Inicializar el algoritmo AHP optimizado
    $asignador = new AsignacionAHPOptimizada($conn);
    $logger = new Logger();
    
    $logger->info("Procesando asignación automática", [
        'ciclo' => $ciclo_academico,
        'usuario_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    // Verificar si es vista previa o confirmación
    if (isset($_POST['preview']) && $_POST['preview'] == '1') {
        // MODO VISTA PREVIA
        $logger->info("Generando vista previa para ciclo: $ciclo_academico");
        
        $resultado = $asignador->ejecutarAsignacionEquilibrada($ciclo_academico, true);
        
        if (!empty($resultado['asignaciones'])) {
            // Codificar datos para URL
            $preview_encoded = urlencode(json_encode($resultado['asignaciones']));
            $estadisticas_encoded = urlencode(json_encode($resultado['estadisticas']));
            
            $logger->info("Vista previa generada exitosamente", [
                'total_asignaciones' => count($resultado['asignaciones']),
                'rechazados' => count($resultado['rechazados']),
                'tiempo_ms' => $resultado['tiempo_ejecucion']
            ]);
            
            // Redirigir con vista previa
            $url = "../pages/asignacion.php?" . http_build_query([
                'preview_data' => $preview_encoded,
                'estadisticas' => $estadisticas_encoded,
                'ciclo_academico' => $ciclo_academico,
                'rechazados' => count($resultado['rechazados']),
                'tiempo_ejecucion' => $resultado['tiempo_ejecucion']
            ]);
            
            header("Location: $url");
            exit();
        } else {
            throw new Exception("No hay estudiantes disponibles para asignar en este ciclo académico o todos los docentes han alcanzado su capacidad máxima.");
        }
    }
    
    elseif (isset($_POST['confirm']) && $_POST['confirm'] == '1') {
        // MODO CONFIRMACIÓN
        $logger->info("Confirmando asignaciones para ciclo: $ciclo_academico");
        
        // Verificar que vengan los datos de la vista previa
        if (!isset($_POST['preview_data'])) {
            throw new Exception("Datos de vista previa no encontrados. Por favor, genere la vista previa nuevamente.");
        }
        
        $preview_data = json_decode($_POST['preview_data'], true);
        if (!$preview_data || !is_array($preview_data)) {
            throw new Exception("Datos de vista previa inválidos.");
        }
        
        // Ejecutar asignación real
        $resultado = $asignador->ejecutarAsignacionEquilibrada($ciclo_academico, false);
        
        if ($resultado['exito']) {
            $logger->info("Asignaciones confirmadas exitosamente", [
                'ciclo' => $ciclo_academico,
                'total_asignaciones' => $resultado['total'],
                'tiempo_ms' => $resultado['tiempo_ejecucion'],
                'puntuacion_promedio' => $resultado['estadisticas']['puntuacion_promedio']
            ]);
            
            $mensaje = "✅ Asignación automática completada exitosamente!\n";
            $mensaje .= "📊 Total de asignaciones: {$resultado['total']}\n";
            $mensaje .= "⏱️ Tiempo de ejecución: {$resultado['tiempo_ejecucion']}ms\n";
            $mensaje .= "🎯 Puntuación promedio AHP: {$resultado['estadisticas']['puntuacion_promedio']}\n";
            $mensaje .= "👨‍🏫 Con experiencia específica: {$resultado['estadisticas']['con_experiencia_especifica']} ({$resultado['estadisticas']['porcentaje_experiencia']}%)";
            
            header("Location: ../pages/asignacion.php?success=" . urlencode($mensaje));
            exit();
        } else {
            throw new Exception("No se pudieron confirmar las asignaciones.");
        }
    }
    
    else {
        // Solicitud inválida
        $logger->warning("Solicitud de asignación inválida", $_POST);
        header("Location: ../pages/asignacion.php?error=Solicitud inválida");
        exit();
    }
    
} catch (Exception $e) {
    $logger->error("Error en procesamiento de asignación", [
        'mensaje' => $e->getMessage(),
        'archivo' => $e->getFile(),
        'linea' => $e->getLine(),
        'ciclo' => $ciclo_academico,
        'post_data' => $_POST
    ]);
    
    // Mensaje de error más amigable para el usuario
    $mensaje_error = "Error en la asignación automática: " . $e->getMessage();
    if (strpos($e->getMessage(), 'disponibles') !== false) {
        $mensaje_error .= "\n\n💡 Sugerencias:\n";
        $mensaje_error .= "• Verifique que hay docentes disponibles en la facultad\n";
        $mensaje_error .= "• Revise los límites de asignación por docente\n";
        $mensaje_error .= "• Considere ajustar los parámetros del algoritmo AHP";
    }
    
    header("Location: ../pages/asignacion.php?error=" . urlencode($mensaje_error));
    exit();
    
} finally {
    // Limpiar logs antiguos ocasionalmente (1% de probabilidad)
    if (random_int(1, 100) === 1) {
        try {
            $logger = new Logger();
            $logger->limpiarLogsAntiguos(30); // Limpiar logs de más de 30 días
        } catch (Exception $e) {
            // Ignorar errores de limpieza de logs
        }
    }
}
?>