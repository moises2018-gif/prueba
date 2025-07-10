<?php
/**
 * GENERADOR DE REPORTES PDF - VERSIÓN CORREGIDA
 * Archivo: procesar/generar_pdf.php
 */

ini_set('memory_limit', '256M');
set_time_limit(120);

include '../includes/conexion.php';
include '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['tipo_reporte'])) {
    die('Error: Solicitud inválida');
}

$conn = ConexionBD();
if (!$conn) {
    die('Error: No se pudo conectar a la base de datos');
}

$tipo_reporte = $_POST['tipo_reporte'];
$ciclo_filtro = $_POST['ciclo_filtro'] ?? '';
$discapacidad_filtro = $_POST['discapacidad_filtro'] ?? '';
$docente_filtro = $_POST['docente_filtro'] ?? '';
$orden_filtro = $_POST['orden_filtro'] ?? 'fecha_desc';

try {
    switch ($tipo_reporte) {
        case 'activas':
            generarPDFAsignacionesActivas($conn, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro);
            break;
        case 'canceladas':
            generarPDFAsignacionesCanceladas($conn, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro);
            break;
        case 'completo':
            generarPDFReporteCompleto($conn, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro);
            break;
        case 'estadisticas':
            generarPDFEstadisticas($conn, $ciclo_filtro);
            break;
        default:
            die('Error: Tipo de reporte no válido');
    }
} catch (Exception $e) {
    die('Error generando PDF: ' . $e->getMessage());
}

/**
 * Obtiene asignaciones según filtros - VERSIÓN CORREGIDA
 */
function obtenerAsignaciones($conn, $estado, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro) {
    $where_conditions = ["a.estado = :estado"];
    $params = [':estado' => $estado];
    
    if (!empty($ciclo_filtro)) {
        $where_conditions[] = "a.ciclo_academico = :ciclo";
        $params[':ciclo'] = $ciclo_filtro;
    }
    
    if (!empty($discapacidad_filtro)) {
        $where_conditions[] = "a.id_tipo_discapacidad = :discapacidad";
        $params[':discapacidad'] = $discapacidad_filtro;
    }
    
    if (!empty($docente_filtro)) {
        $where_conditions[] = "a.id_docente = :docente";
        $params[':docente'] = $docente_filtro;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Determinar orden
    $order_clause = "ORDER BY ";
    switch ($orden_filtro) {
        case 'fecha_asc':
            $order_clause .= "a.fecha_asignacion ASC";
            break;
        case 'prioridad_desc':
            $order_clause .= "t.peso_prioridad DESC, a.fecha_asignacion DESC";
            break;
        case 'docente_asc':
            $order_clause .= "d.nombres_completos ASC";
            break;
        case 'estudiante_asc':
            $order_clause .= "e.nombres_completos ASC";
            break;
        case 'puntuacion_desc':
            $order_clause .= "a.puntuacion_ahp DESC";
            break;
        default:
            $order_clause .= "a.fecha_asignacion DESC";
    }
    
    // Consulta simplificada sin usar vista problemática
    $query = "
        SELECT 
            a.id_asignacion, 
            d.nombres_completos AS docente, 
            e.nombres_completos AS estudiante,
            t.nombre_discapacidad, 
            t.peso_prioridad,
            m.nombre_materia, 
            a.ciclo_academico, 
            a.puntuacion_ahp, 
            a.estado, 
            a.fecha_asignacion,
            COALESCE(edd.tiene_experiencia, 0) as tiene_experiencia,
            COALESCE(edd.nivel_competencia, 'Básico') as nivel_competencia
        FROM asignaciones a
        LEFT JOIN docentes d ON a.id_docente = d.id_docente
        LEFT JOIN estudiantes e ON a.id_estudiante = e.id_estudiante
        JOIN tipos_discapacidad t ON a.id_tipo_discapacidad = t.id_tipo_discapacidad
        LEFT JOIN materias m ON a.id_materia = m.id_materia
        LEFT JOIN experiencia_docente_discapacidad edd ON d.id_docente = edd.id_docente 
                                                        AND a.id_tipo_discapacidad = edd.id_tipo_discapacidad
        WHERE $where_clause
        $order_clause";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Obtiene estadísticas básicas - VERSIÓN SIMPLIFICADA
 */
function obtenerEstadisticas($conn, $ciclo_filtro = '') {
    $where_ciclo = !empty($ciclo_filtro) ? "WHERE ciclo_academico = '$ciclo_filtro'" : '';
    
    $estadisticas = [];
    
    try {
        // Total asignaciones
        $query = "SELECT COUNT(*) as total FROM asignaciones $where_ciclo";
        $estadisticas['total_asignaciones'] = $conn->query($query)->fetchColumn();
        
        // Asignaciones activas
        $query = "SELECT COUNT(*) as total FROM asignaciones WHERE estado = 'Activa' " . 
                 (!empty($ciclo_filtro) ? "AND ciclo_academico = '$ciclo_filtro'" : '');
        $estadisticas['asignaciones_activas'] = $conn->query($query)->fetchColumn();
        
        // Asignaciones canceladas
        $query = "SELECT COUNT(*) as total FROM asignaciones WHERE estado = 'Cancelada' " . 
                 (!empty($ciclo_filtro) ? "AND ciclo_academico = '$ciclo_filtro'" : '');
        $estadisticas['asignaciones_canceladas'] = $conn->query($query)->fetchColumn();
        
        // Promedio puntuación AHP
        $query = "SELECT AVG(puntuacion_ahp) as promedio FROM asignaciones WHERE estado = 'Activa' " . 
                 (!empty($ciclo_filtro) ? "AND ciclo_academico = '$ciclo_filtro'" : '');
        $estadisticas['promedio_puntuacion'] = $conn->query($query)->fetchColumn() ?: 0;
        
        // Distribución por tipo (simplificada)
        $query = "
            SELECT 
                t.nombre_discapacidad, 
                t.peso_prioridad, 
                COUNT(a.id_asignacion) as cantidad
            FROM tipos_discapacidad t
            LEFT JOIN asignaciones a ON t.id_tipo_discapacidad = a.id_tipo_discapacidad 
                AND a.estado = 'Activa'
                " . (!empty($ciclo_filtro) ? "AND a.ciclo_academico = '$ciclo_filtro'" : '') . "
            GROUP BY t.id_tipo_discapacidad
            ORDER BY t.peso_prioridad DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $estadisticas['distribucion_tipos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        // Si hay error, devolver estadísticas básicas
        $estadisticas = [
            'total_asignaciones' => 0,
            'asignaciones_activas' => 0,
            'asignaciones_canceladas' => 0,
            'promedio_puntuacion' => 0,
            'distribucion_tipos' => []
        ];
    }
    
    return $estadisticas;
}

// Resto de funciones (generarPDFAsignacionesActivas, etc.) igual que antes...

function generarPDFAsignacionesActivas($conn, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro) {
    $datos = obtenerAsignaciones($conn, 'Activa', $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro);
    
    $titulo = 'Reporte de Asignaciones Activas';
    $filename = 'asignaciones_activas_' . date('Y-m-d_H-i-s') . '.html';
    
    generarPDFBasico($datos, $titulo, $filename, $ciclo_filtro, $discapacidad_filtro, $docente_filtro);
}

function generarPDFAsignacionesCanceladas($conn, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro) {
    $datos = obtenerAsignaciones($conn, 'Cancelada', $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro);
    
    $titulo = 'Reporte de Asignaciones Canceladas';
    $filename = 'asignaciones_canceladas_' . date('Y-m-d_H-i-s') . '.html';
    
    generarPDFBasico($datos, $titulo, $filename, $ciclo_filtro, $discapacidad_filtro, $docente_filtro);
}

function generarPDFReporteCompleto($conn, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro) {
    $activas = obtenerAsignaciones($conn, 'Activa', $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro);
    $canceladas = obtenerAsignaciones($conn, 'Cancelada', $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro);
    $estadisticas = obtenerEstadisticas($conn, $ciclo_filtro);
    
    $titulo = 'Reporte Completo de Asignaciones AHP';
    $filename = 'reporte_completo_' . date('Y-m-d_H-i-s') . '.html';
    
    generarPDFCompleto($activas, $canceladas, $estadisticas, $titulo, $filename, $ciclo_filtro, $discapacidad_filtro, $docente_filtro);
}

function generarPDFEstadisticas($conn, $ciclo_filtro) {
    $estadisticas = obtenerEstadisticasDetalladas($conn, $ciclo_filtro);
    
    $titulo = 'Estadísticas del Sistema AHP';
    $filename = 'estadisticas_ahp_' . date('Y-m-d_H-i-s') . '.html';
    
    generarPDFSoloEstadisticas($estadisticas, $titulo, $filename, $ciclo_filtro);
}

function obtenerEstadisticasDetalladas($conn, $ciclo_filtro = '') {
    $estadisticas = obtenerEstadisticas($conn, $ciclo_filtro);
    
    // Docentes más activos (simplificado)
    $where_ciclo = !empty($ciclo_filtro) ? "AND a.ciclo_academico = '$ciclo_filtro'" : '';
    
    try {
        $query = "
            SELECT 
                d.nombres_completos, 
                COUNT(a.id_asignacion) as total_asignaciones,
                AVG(a.puntuacion_ahp) as promedio_puntuacion
            FROM docentes d
            JOIN asignaciones a ON d.id_docente = a.id_docente
            WHERE a.estado = 'Activa' $where_ciclo
            GROUP BY d.id_docente
            ORDER BY total_asignaciones DESC, promedio_puntuacion DESC
            LIMIT 10";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $estadisticas['docentes_activos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Criterios AHP
        $query = "SELECT * FROM criterios_ahp ORDER BY peso_criterio DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $estadisticas['criterios_ahp'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $estadisticas['docentes_activos'] = [];
        $estadisticas['criterios_ahp'] = [];
    }
    
    return $estadisticas;
}

// Incluir el resto de funciones de generación HTML (generarPDFBasico, etc.)...
function generarPDFBasico($datos, $titulo, $filename, $ciclo_filtro, $discapacidad_filtro, $docente_filtro) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    echo generarHTMLParaPDF($datos, $titulo, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, 'basico');
}

function generarHTMLParaPDF($datos, $titulo, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $tipo = 'basico') {
    $fecha_generacion = date('d/m/Y H:i:s');
    $total_registros = count($datos);
    
    $filtros_aplicados = [];
    if (!empty($ciclo_filtro)) $filtros_aplicados[] = "Ciclo: $ciclo_filtro";
    if (!empty($discapacidad_filtro)) $filtros_aplicados[] = "Discapacidad ID: $discapacidad_filtro";
    if (!empty($docente_filtro)) $filtros_aplicados[] = "Docente ID: $docente_filtro";
    $filtros_texto = !empty($filtros_aplicados) ? implode(' | ', $filtros_aplicados) : 'Sin filtros';
    
    $html = '
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>' . $titulo . '</title>
        <style>
            @page { margin: 2cm; size: A4; }
            body { 
                font-family: Arial, sans-serif; 
                font-size: 10px; 
                line-height: 1.4;
                margin: 0;
                color: #333;
            }
            .header {
                text-align: center;
                margin-bottom: 20px;
                border-bottom: 2px solid #667eea;
                padding-bottom: 15px;
            }
            .header h1 {
                color: #667eea;
                font-size: 18px;
                margin: 0 0 5px 0;
            }
            .info-section {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 15px;
                border-left: 4px solid #667eea;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 15px;
                font-size: 9px;
            }
            th { 
                background: #667eea; 
                color: white; 
                padding: 8px 5px; 
                text-align: left;
                font-weight: bold;
                border: 1px solid #5a67d8;
            }
            td { 
                padding: 6px 5px; 
                border: 1px solid #e1e8ed;
                vertical-align: top;
            }
            tr:nth-child(even) { 
                background: #f8f9fa; 
            }
            .print-button {
                background: #667eea;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                margin: 10px;
                font-size: 12px;
            }
            @media print {
                .print-button { display: none; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button class="print-button" onclick="window.print()">🖨️ Imprimir/Guardar como PDF</button>
        </div>
        
        <div class="header">
            <h1>' . $titulo . '</h1>
            <div>Sistema AHP - Asignación de Docentes NEE</div>
            <div>Universidad Estatal de Guayaquil</div>
        </div>
        
        <div class="info-section">
            <strong>Fecha:</strong> ' . $fecha_generacion . ' | 
            <strong>Registros:</strong> ' . $total_registros . ' | 
            <strong>Filtros:</strong> ' . $filtros_texto . '
        </div>';
    
    if (!empty($datos)) {
        $html .= '<table>
            <thead>
                <tr>
                    <th>Docente</th>
                    <th>Estudiante</th>
                    <th>Discapacidad</th>
                    <th>Materia</th>
                    <th>Ciclo</th>
                    <th>Puntuación</th>
                    <th>Experiencia</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($datos as $row) {
            $experiencia_texto = $row['tiene_experiencia'] ? '✓ ' . $row['nivel_competencia'] : '✗ Sin experiencia';
            
            $html .= '<tr>
                <td>' . htmlspecialchars($row['docente'] ?: 'No asignado') . '</td>
                <td>' . htmlspecialchars($row['estudiante'] ?: 'No asignado') . '</td>
                <td>' . htmlspecialchars($row['nombre_discapacidad']) . '</td>
                <td>' . htmlspecialchars($row['nombre_materia'] ?: 'No especificada') . '</td>
                <td>' . htmlspecialchars($row['ciclo_academico']) . '</td>
                <td>' . number_format($row['puntuacion_ahp'], 3) . '</td>
                <td>' . $experiencia_texto . '</td>
                <td>' . date('d/m/Y H:i', strtotime($row['fecha_asignacion'])) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    } else {
        $html .= '<div style="text-align: center; padding: 30px;">
            <h3>📋 No se encontraron registros</h3>
            <p>No hay datos que coincidan con los filtros aplicados.</p>
        </div>';
    }
    
    $html .= '
        <div style="margin-top: 30px; text-align: center; font-size: 8px; color: #666;">
            <p>Sistema AHP - Universidad Estatal de Guayaquil</p>
            <p>Generado: ' . $fecha_generacion . ' | Registros: ' . $total_registros . '</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Agregar stubs para las otras funciones si no están definidas
function generarPDFCompleto($activas, $canceladas, $estadisticas, $titulo, $filename, $ciclo_filtro, $discapacidad_filtro, $docente_filtro) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    echo "<h1>$titulo</h1><p>Activas: " . count($activas) . " | Canceladas: " . count($canceladas) . "</p>";
}

function generarPDFSoloEstadisticas($estadisticas, $titulo, $filename, $ciclo_filtro) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    echo "<h1>$titulo</h1><p>Total asignaciones: " . ($estadisticas['total_asignaciones'] ?? 0) . "</p>";
}
?>