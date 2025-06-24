<?php
/**
 * GENERADOR DE REPORTES PDF
 * Archivo: procesar/generar_pdf.php
 * Genera PDFs de asignaciones activas, canceladas y estadísticas
 */

// Configuración para PDF
ini_set('memory_limit', '256M');
set_time_limit(120);

include '../includes/conexion.php';
include '../includes/config.php';

// Verificar que se enviaron datos
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['tipo_reporte'])) {
    die('Error: Solicitud inválida');
}

$conn = ConexionBD();
if (!$conn) {
    die('Error: No se pudo conectar a la base de datos');
}

// Obtener parámetros
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
 * Genera PDF de asignaciones activas
 */
function generarPDFAsignacionesActivas($conn, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro) {
    $datos = obtenerAsignaciones($conn, 'Activa', $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro);
    
    $titulo = 'Reporte de Asignaciones Activas';
    $filename = 'asignaciones_activas_' . date('Y-m-d_H-i-s') . '.html';
    
    generarPDFBasico($datos, $titulo, $filename, $ciclo_filtro, $discapacidad_filtro, $docente_filtro);
}

/**
 * Genera PDF de asignaciones canceladas
 */
function generarPDFAsignacionesCanceladas($conn, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro) {
    $datos = obtenerAsignaciones($conn, 'Cancelada', $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro);
    
    $titulo = 'Reporte de Asignaciones Canceladas';
    $filename = 'asignaciones_canceladas_' . date('Y-m-d_H-i-s') . '.html';
    
    generarPDFBasico($datos, $titulo, $filename, $ciclo_filtro, $discapacidad_filtro, $docente_filtro);
}

/**
 * Genera PDF del reporte completo
 */
function generarPDFReporteCompleto($conn, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro) {
    $activas = obtenerAsignaciones($conn, 'Activa', $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro);
    $canceladas = obtenerAsignaciones($conn, 'Cancelada', $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $orden_filtro);
    $estadisticas = obtenerEstadisticas($conn, $ciclo_filtro);
    
    $titulo = 'Reporte Completo de Asignaciones AHP';
    $filename = 'reporte_completo_' . date('Y-m-d_H-i-s') . '.html';
    
    generarPDFCompleto($activas, $canceladas, $estadisticas, $titulo, $filename, $ciclo_filtro, $discapacidad_filtro, $docente_filtro);
}

/**
 * Genera PDF de estadísticas
 */
function generarPDFEstadisticas($conn, $ciclo_filtro) {
    $estadisticas = obtenerEstadisticasDetalladas($conn, $ciclo_filtro);
    
    $titulo = 'Estadísticas del Sistema AHP';
    $filename = 'estadisticas_ahp_' . date('Y-m-d_H-i-s') . '.html';
    
    generarPDFSoloEstadisticas($estadisticas, $titulo, $filename, $ciclo_filtro);
}

/**
 * Obtiene asignaciones según filtros
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
        default: // fecha_desc
            $order_clause .= "a.fecha_asignacion DESC";
    }
    
    $query = "
        SELECT a.id_asignacion, 
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
 * Obtiene estadísticas básicas
 */
function obtenerEstadisticas($conn, $ciclo_filtro = '') {
    $where_ciclo = !empty($ciclo_filtro) ? "WHERE ciclo_academico = '$ciclo_filtro'" : '';
    
    $estadisticas = [];
    
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
    
    // Distribución por tipo
    $query = "
        SELECT t.nombre_discapacidad, t.peso_prioridad, COUNT(a.id_asignacion) as cantidad
        FROM tipos_discapacidad t
        LEFT JOIN asignaciones a ON t.id_tipo_discapacidad = a.id_tipo_discapacidad AND a.estado = 'Activa'
        " . (!empty($ciclo_filtro) ? "AND a.ciclo_academico = '$ciclo_filtro'" : '') . "
        GROUP BY t.id_tipo_discapacidad
        ORDER BY t.peso_prioridad DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $estadisticas['distribucion_tipos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $estadisticas;
}

/**
 * Obtiene estadísticas detalladas
 */
function obtenerEstadisticasDetalladas($conn, $ciclo_filtro = '') {
    $estadisticas = obtenerEstadisticas($conn, $ciclo_filtro);
    
    // Docentes más activos
    $where_ciclo = !empty($ciclo_filtro) ? "AND a.ciclo_academico = '$ciclo_filtro'" : '';
    $query = "
        SELECT d.nombres_completos, COUNT(a.id_asignacion) as total_asignaciones,
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
    
    return $estadisticas;
}

/**
 * Genera PDF básico usando HTML/CSS
 */
function generarPDFBasico($datos, $titulo, $filename, $ciclo_filtro, $discapacidad_filtro, $docente_filtro) {
    // Headers para HTML optimizado para PDF
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    // Generar HTML para convertir a PDF usando el navegador
    echo generarHTMLParaPDF($datos, $titulo, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, 'basico');
}

/**
 * Genera PDF completo
 */
function generarPDFCompleto($activas, $canceladas, $estadisticas, $titulo, $filename, $ciclo_filtro, $discapacidad_filtro, $docente_filtro) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    echo generarHTMLCompleto($activas, $canceladas, $estadisticas, $titulo, $ciclo_filtro, $discapacidad_filtro, $docente_filtro);
}

/**
 * Genera PDF solo de estadísticas
 */
function generarPDFSoloEstadisticas($estadisticas, $titulo, $filename, $ciclo_filtro) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    echo generarHTMLEstadisticas($estadisticas, $titulo, $ciclo_filtro);
}

/**
 * Genera HTML base para PDFs
 */
function generarHTMLParaPDF($datos, $titulo, $ciclo_filtro, $discapacidad_filtro, $docente_filtro, $tipo = 'basico') {
    $fecha_generacion = date('d/m/Y H:i:s');
    $total_registros = count($datos);
    
    // Información de filtros
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
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $titulo . '</title>
        <style>
            @page { 
                margin: 2cm; 
                size: A4;
            }
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
            .header .subtitle {
                color: #666;
                font-size: 12px;
                margin: 5px 0;
            }
            .info-section {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 15px;
                border-left: 4px solid #667eea;
            }
            .info-section h3 {
                margin: 0 0 8px 0;
                color: #667eea;
                font-size: 12px;
            }
            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
            }
            .info-item {
                background: white;
                padding: 8px;
                border-radius: 3px;
                border: 1px solid #e1e8ed;
            }
            .info-item strong {
                color: #667eea;
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
            .priority-high { 
                background: #ffebee !important; 
                border-left: 3px solid #f44336;
            }
            .priority-medium { 
                background: #fff3e0 !important; 
                border-left: 3px solid #ff9800;
            }
            .priority-low { 
                background: #f3e5f5 !important; 
                border-left: 3px solid #9c27b0;
            }
            .experience-yes { 
                color: #4caf50; 
                font-weight: bold; 
            }
            .experience-no { 
                color: #f44336; 
            }
            .score-high { 
                color: #4caf50; 
                font-weight: bold; 
            }
            .score-medium { 
                color: #ff9800; 
                font-weight: bold; 
            }
            .score-low { 
                color: #f44336; 
            }
            .footer {
                margin-top: 30px;
                text-align: center;
                font-size: 8px;
                color: #666;
                border-top: 1px solid #e1e8ed;
                padding-top: 10px;
            }
            .no-data {
                text-align: center;
                padding: 30px;
                color: #666;
                font-style: italic;
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
            <div class="subtitle">Sistema AHP - Asignación de Docentes NEE</div>
            <div class="subtitle">Facultad de Ciencias Matemáticas y Físicas</div>
            <div class="subtitle">Universidad Estatal de Guayaquil</div>
        </div>
        
        <div class="info-section">
            <h3>📊 Información del Reporte</h3>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Fecha de generación:</strong><br>' . $fecha_generacion . '
                </div>
                <div class="info-item">
                    <strong>Total de registros:</strong><br>' . $total_registros . '
                </div>
                <div class="info-item">
                    <strong>Filtros aplicados:</strong><br>' . $filtros_texto . '
                </div>
                <div class="info-item">
                    <strong>Tipo de reporte:</strong><br>' . ucfirst($tipo) . '
                </div>
            </div>
        </div>';
    
    if (!empty($datos)) {
        $html .= '<table>
            <thead>
                <tr>
                    <th style="width: 15%">Docente</th>
                    <th style="width: 15%">Estudiante</th>
                    <th style="width: 12%">Discapacidad</th>
                    <th style="width: 8%">Prioridad</th>
                    <th style="width: 12%">Materia</th>
                    <th style="width: 8%">Ciclo</th>
                    <th style="width: 8%">Puntuación</th>
                    <th style="width: 10%">Experiencia</th>
                    <th style="width: 12%">Fecha</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($datos as $row) {
            $prioridad = $row['peso_prioridad'] * 100;
            $clase_prioridad = $prioridad >= 30 ? 'priority-high' : ($prioridad >= 15 ? 'priority-medium' : 'priority-low');
            
            $puntuacion = floatval($row['puntuacion_ahp']);
            $clase_puntuacion = $puntuacion >= 0.8 ? 'score-high' : ($puntuacion >= 0.5 ? 'score-medium' : 'score-low');
            
            $experiencia_clase = $row['tiene_experiencia'] ? 'experience-yes' : 'experience-no';
            $experiencia_texto = $row['tiene_experiencia'] ? '✓ ' . $row['nivel_competencia'] : '✗ Sin experiencia';
            
            $html .= '<tr class="' . $clase_prioridad . '">
                <td>' . htmlspecialchars($row['docente'] ?: 'No asignado') . '</td>
                <td>' . htmlspecialchars($row['estudiante'] ?: 'No asignado') . '</td>
                <td>' . htmlspecialchars($row['nombre_discapacidad']) . '</td>
                <td>' . number_format($prioridad, 1) . '%</td>
                <td>' . htmlspecialchars($row['nombre_materia'] ?: 'No especificada') . '</td>
                <td>' . htmlspecialchars($row['ciclo_academico']) . '</td>
                <td class="' . $clase_puntuacion . '">' . number_format($puntuacion, 3) . '</td>
                <td class="' . $experiencia_clase . '">' . $experiencia_texto . '</td>
                <td>' . date('d/m/Y H:i', strtotime($row['fecha_asignacion'])) . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
    } else {
        $html .= '<div class="no-data">
            <h3>📋 No se encontraron registros</h3>
            <p>No hay datos que coincidan con los filtros aplicados.</p>
        </div>';
    }
    
    $html .= '
        <div class="footer">
            <p>Este reporte fue generado automáticamente por el Sistema AHP de Asignación de Docentes NEE</p>
            <p>Facultad de Ciencias Matemáticas y Físicas - Universidad Estatal de Guayaquil</p>
            <p>Fecha: ' . $fecha_generacion . ' | Total de registros: ' . $total_registros . '</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Genera HTML completo para reporte integral
 */
function generarHTMLCompleto($activas, $canceladas, $estadisticas, $titulo, $ciclo_filtro, $discapacidad_filtro, $docente_filtro) {
    $fecha_generacion = date('d/m/Y H:i:s');
    
    $html = '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>' . $titulo . '</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 10px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #667eea; padding-bottom: 15px; }
            .section { margin: 20px 0; page-break-inside: avoid; }
            .section h2 { color: #667eea; border-bottom: 1px solid #e1e8ed; padding-bottom: 5px; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 9px; }
            th { background: #667eea; color: white; padding: 8px 5px; }
            td { padding: 6px 5px; border: 1px solid #e1e8ed; }
            tr:nth-child(even) { background: #f8f9fa; }
            .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0; }
            .stat-card { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #667eea; }
            .print-button { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 10px; }
            @media print { .print-button { display: none; } }
        </style>
    </head>
    <body>
        <div style="text-align: center; margin-bottom: 20px;">
            <button class="print-button" onclick="window.print()">🖨️ Imprimir/Guardar como PDF</button>
        </div>
        
        <div class="header">
            <h1>' . $titulo . '</h1>
            <p>Generado el: ' . $fecha_generacion . '</p>
        </div>
        
        <div class="section">
            <h2>📊 Resumen Ejecutivo</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <strong>Asignaciones Activas:</strong><br>' . $estadisticas['asignaciones_activas'] . '
                </div>
                <div class="stat-card">
                    <strong>Asignaciones Canceladas:</strong><br>' . $estadisticas['asignaciones_canceladas'] . '
                </div>
                <div class="stat-card">
                    <strong>Total Asignaciones:</strong><br>' . $estadisticas['total_asignaciones'] . '
                </div>
                <div class="stat-card">
                    <strong>Promedio Puntuación AHP:</strong><br>' . number_format($estadisticas['promedio_puntuacion'], 3) . '
                </div>
            </div>
        </div>';
    
    // Tabla de asignaciones activas
    if (!empty($activas)) {
        $html .= '<div class="section">
            <h2>✅ Asignaciones Activas (' . count($activas) . ')</h2>';
        $html .= generarTablaAsignaciones($activas);
        $html .= '</div>';
    }
    
    // Tabla de asignaciones canceladas
    if (!empty($canceladas)) {
        $html .= '<div class="section">
            <h2>❌ Asignaciones Canceladas (' . count($canceladas) . ')</h2>';
        $html .= generarTablaAsignaciones($canceladas);
        $html .= '</div>';
    }
    
    // Distribución por tipo de discapacidad
    if (!empty($estadisticas['distribucion_tipos'])) {
        $html .= '<div class="section">
            <h2>📈 Distribución por Tipo de Discapacidad</h2>
            <table>
                <tr>
                    <th>Tipo de Discapacidad</th>
                    <th>Peso Prioridad AHP</th>
                    <th>Asignaciones Activas</th>
                </tr>';
        
        foreach ($estadisticas['distribucion_tipos'] as $tipo) {
            $html .= '<tr>
                <td>' . htmlspecialchars($tipo['nombre_discapacidad']) . '</td>
                <td>' . number_format($tipo['peso_prioridad'] * 100, 1) . '%</td>
                <td>' . $tipo['cantidad'] . '</td>
            </tr>';
        }
        
        $html .= '</table></div>';
    }
    
    $html .= '</body></html>';
    
    return $html;
}

/**
 * Genera tabla HTML para asignaciones
 */
function generarTablaAsignaciones($datos) {
    $html = '<table>
        <tr>
            <th>Docente</th>
            <th>Estudiante</th>
            <th>Discapacidad</th>
            <th>Puntuación AHP</th>
            <th>Experiencia</th>
            <th>Fecha</th>
        </tr>';
    
    foreach ($datos as $row) {
        $experiencia = $row['tiene_experiencia'] ? '✓ ' . $row['nivel_competencia'] : '✗ Sin exp.';
        
        $html .= '<tr>
            <td>' . htmlspecialchars($row['docente'] ?: 'No asignado') . '</td>
            <td>' . htmlspecialchars($row['estudiante'] ?: 'No asignado') . '</td>
            <td>' . htmlspecialchars($row['nombre_discapacidad']) . '</td>
            <td>' . number_format($row['puntuacion_ahp'], 3) . '</td>
            <td>' . $experiencia . '</td>
            <td>' . date('d/m/Y', strtotime($row['fecha_asignacion'])) . '</td>
        </tr>';
    }
    
    $html .= '</table>';
    return $html;
}

/**
 * Genera HTML para estadísticas
 */
function generarHTMLEstadisticas($estadisticas, $titulo, $ciclo_filtro) {
    $fecha_generacion = date('d/m/Y H:i:s');
    
    $html = '<!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>' . $titulo . '</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .stats-section { margin: 20px 0; }
            .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; }
            .stat-card { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #667eea; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th { background: #667eea; color: white; padding: 10px; }
            td { padding: 8px; border: 1px solid #e1e8ed; }
            .print-button { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 10px; }
            @media print { .print-button { display: none; } }
        </style>
    </head>
    <body>
        <div style="text-align: center; margin-bottom: 20px;">
            <button class="print-button" onclick="window.print()">🖨️ Imprimir/Guardar como PDF</button>
        </div>
        
        <div class="header">
            <h1>' . $titulo . '</h1>
            <p>Generado el: ' . $fecha_generacion . '</p>
            ' . (!empty($ciclo_filtro) ? '<p>Ciclo: ' . $ciclo_filtro . '</p>' : '') . '
        </div>
        
        <div class="stats-section">
            <h2>📊 Métricas Principales</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total de Asignaciones</h3>
                    <div style="font-size: 24px; color: #667eea; font-weight: bold;">' . $estadisticas['total_asignaciones'] . '</div>
                </div>
                <div class="stat-card">
                    <h3>Asignaciones Activas</h3>
                    <div style="font-size: 24px; color: #28a745; font-weight: bold;">' . $estadisticas['asignaciones_activas'] . '</div>
                </div>
                <div class="stat-card">
                    <h3>Asignaciones Canceladas</h3>
                    <div style="font-size: 24px; color: #dc3545; font-weight: bold;">' . $estadisticas['asignaciones_canceladas'] . '</div>
                </div>
                <div class="stat-card">
                    <h3>Promedio Puntuación AHP</h3>
                    <div style="font-size: 24px; color: #ffc107; font-weight: bold;">' . number_format($estadisticas['promedio_puntuacion'], 3) . '</div>
                </div>
            </div>
        </div>';
    
    // Distribución por tipo de discapacidad
    if (!empty($estadisticas['distribucion_tipos'])) {
        $html .= '<div class="stats-section">
            <h2>📈 Distribución por Tipo de Discapacidad</h2>
            <table>
                <tr>
                    <th>Tipo de Discapacidad</th>
                    <th>Peso AHP (%)</th>
                    <th>Asignaciones</th>
                    <th>Porcentaje del Total</th>
                </tr>';
        
        foreach ($estadisticas['distribucion_tipos'] as $tipo) {
            $porcentaje_total = $estadisticas['asignaciones_activas'] > 0 ? 
                              ($tipo['cantidad'] / $estadisticas['asignaciones_activas'] * 100) : 0;
            
            $html .= '<tr>
                <td>' . htmlspecialchars($tipo['nombre_discapacidad']) . '</td>
                <td>' . number_format($tipo['peso_prioridad'] * 100, 1) . '%</td>
                <td>' . $tipo['cantidad'] . '</td>
                <td>' . number_format($porcentaje_total, 1) . '%</td>
            </tr>';
        }
        
        $html .= '</table></div>';
    }
    
    // Docentes más activos
    if (!empty($estadisticas['docentes_activos'])) {
        $html .= '<div class="stats-section">
            <h2>👨‍🏫 Docentes Más Activos</h2>
            <table>
                <tr>
                    <th>Docente</th>
                    <th>Total Asignaciones</th>
                    <th>Promedio Puntuación</th>
                </tr>';
        
        foreach ($estadisticas['docentes_activos'] as $docente) {
            $html .= '<tr>
                <td>' . htmlspecialchars($docente['nombres_completos']) . '</td>
                <td>' . $docente['total_asignaciones'] . '</td>
                <td>' . number_format($docente['promedio_puntuacion'], 3) . '</td>
            </tr>';
        }
        
        $html .= '</table></div>';
    }
    
    // Criterios AHP
    if (!empty($estadisticas['criterios_ahp'])) {
        $html .= '<div class="stats-section">
            <h2>⚖️ Criterios del Algoritmo AHP</h2>
            <table>
                <tr>
                    <th>Criterio</th>
                    <th>Código</th>
                    <th>Peso (%)</th>
                    <th>Descripción</th>
                </tr>';
        
        foreach ($estadisticas['criterios_ahp'] as $criterio) {
            $html .= '<tr>
                <td>' . htmlspecialchars($criterio['nombre_criterio']) . '</td>
                <td><strong>' . htmlspecialchars($criterio['codigo_criterio']) . '</strong></td>
                <td>' . number_format($criterio['peso_criterio'] * 100, 1) . '%</td>
                <td>' . htmlspecialchars($criterio['descripcion']) . '</td>
            </tr>';
        }
        
        $html .= '</table></div>';
    }
    
    $html .= '<div class="stats-section">
        <h2>📋 Notas Importantes</h2>
        <ul>
            <li><strong>Sistema AHP:</strong> Proceso Analítico Jerárquico para asignación óptima de docentes</li>
            <li><strong>Criterios principales:</strong> EPR (32%), FSI (28%), AMI (16%), AED (13%), NFA (11%)</li>
            <li><strong>Prioridades por discapacidad:</strong> Psicosocial (40%), Intelectual (30%), Visual (15%), Auditiva (10%), Física (5%)</li>
            <li><strong>Experiencia específica:</strong> Bonificación del 2%-15% según nivel de competencia</li>
            <li><strong>Balanceado de carga:</strong> Penalización hasta 30% por sobrecarga de docentes</li>
        </ul>
    </div>';
    
    $html .= '</body></html>';
    
    return $html;
}
?>