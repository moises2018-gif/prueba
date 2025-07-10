<?php 
// Debug activado
ini_set('display_errors', 1);
error_reporting(E_ALL);

include '../includes/header.php'; 
include '../includes/nav.php'; 
?>

<style>
/* Estilos para PDF */
.pdf-buttons-container {
    display: flex;
    gap: 15px;
    margin: 20px 0;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-pdf {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-pdf:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
    color: white;
    text-decoration: none;
}

.filtros-pdf {
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    border-radius: 10px;
    margin: 20px 0;
}

.filtros-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.filtro-item {
    display: flex;
    flex-direction: column;
}

.filtro-item label {
    color: white;
    margin-bottom: 5px;
    font-weight: 600;
}

.filtro-item select {
    padding: 8px;
    border-radius: 5px;
    border: 1px solid #ddd;
}
</style>

<div id="reportes" class="tab-content" style="display: block;">
    <h2>Reportes de Asignaciones</h2>
    
    <?php
    try {
        include '../includes/conexion.php';
        $conn = ConexionBD();
        
        if (!$conn) {
            throw new Exception("No se pudo conectar a la base de datos");
        }
        
        echo "<!-- DEBUG: Conexión exitosa -->\n";
        
        // Obtener ciclos académicos (consulta simplificada)
        $ciclos = [];
        try {
            $query_ciclos = "SELECT DISTINCT ciclo_academico FROM asignaciones WHERE ciclo_academico IS NOT NULL ORDER BY ciclo_academico DESC";
            $stmt_ciclos = $conn->prepare($query_ciclos);
            $stmt_ciclos->execute();
            $ciclos = $stmt_ciclos->fetchAll(PDO::FETCH_ASSOC);
            echo "<!-- DEBUG: Ciclos obtenidos: " . count($ciclos) . " -->\n";
        } catch (Exception $e) {
            echo "<!-- ERROR en ciclos: " . $e->getMessage() . " -->\n";
        }
        
        // Obtener tipos de discapacidad (directo de tabla)
        $tipos = [];
        try {
            $query_tipos = "SELECT id_tipo_discapacidad, nombre_discapacidad FROM tipos_discapacidad ORDER BY peso_prioridad DESC";
            $stmt_tipos = $conn->prepare($query_tipos);
            $stmt_tipos->execute();
            $tipos = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);
            echo "<!-- DEBUG: Tipos obtenidos: " . count($tipos) . " -->\n";
        } catch (Exception $e) {
            echo "<!-- ERROR en tipos: " . $e->getMessage() . " -->\n";
        }
        
        // Obtener docentes (directo de tabla)
        $docentes = [];
        try {
            $query_docentes = "SELECT DISTINCT d.id_docente, d.nombres_completos 
                             FROM docentes d 
                             JOIN asignaciones a ON d.id_docente = a.id_docente 
                             ORDER BY d.nombres_completos";
            $stmt_docentes = $conn->prepare($query_docentes);
            $stmt_docentes->execute();
            $docentes = $stmt_docentes->fetchAll(PDO::FETCH_ASSOC);
            echo "<!-- DEBUG: Docentes obtenidos: " . count($docentes) . " -->\n";
        } catch (Exception $e) {
            echo "<!-- ERROR en docentes: " . $e->getMessage() . " -->\n";
        }
    ?>
    
    <!-- Formulario de filtros PDF -->
    <div class="filtros-pdf">
        <h4 style="color: white; margin-bottom: 15px; text-align: center;">📄 Generar Reportes PDF</h4>
        <form id="filtrosPDF" action="../procesar/generar_pdf.php" method="POST" target="_blank">
            <div class="filtros-grid">
                
                <div class="filtro-item">
                    <label>Ciclo Académico:</label>
                    <select name="ciclo_filtro">
                        <option value="">Todos los ciclos</option>
                        <?php foreach ($ciclos as $ciclo): ?>
                            <option value="<?php echo htmlspecialchars($ciclo['ciclo_academico']); ?>">
                                <?php echo htmlspecialchars($ciclo['ciclo_academico']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filtro-item">
                    <label>Tipo Discapacidad:</label>
                    <select name="discapacidad_filtro">
                        <option value="">Todos los tipos</option>
                        <?php foreach ($tipos as $tipo): ?>
                            <option value="<?php echo $tipo['id_tipo_discapacidad']; ?>">
                                <?php echo htmlspecialchars($tipo['nombre_discapacidad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filtro-item">
                    <label>Docente:</label>
                    <select name="docente_filtro">
                        <option value="">Todos los docentes</option>
                        <?php foreach ($docentes as $docente): ?>
                            <option value="<?php echo $docente['id_docente']; ?>">
                                <?php echo htmlspecialchars($docente['nombres_completos']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filtro-item">
                    <label>Ordenar por:</label>
                    <select name="orden_filtro">
                        <option value="fecha_desc">Fecha (Más reciente)</option>
                        <option value="fecha_asc">Fecha (Más antigua)</option>
                        <option value="prioridad_desc">Prioridad (Mayor a menor)</option>
                        <option value="docente_asc">Docente (A-Z)</option>
                        <option value="estudiante_asc">Estudiante (A-Z)</option>
                        <option value="puntuacion_desc">Puntuación AHP (Mayor)</option>
                    </select>
                </div>
            </div>
            
            <div class="pdf-buttons-container">
                <button type="submit" name="tipo_reporte" value="activas" class="btn-pdf">
                    📋 PDF Asignaciones Activas
                </button>
                
                <button type="submit" name="tipo_reporte" value="canceladas" class="btn-pdf">
                    📋 PDF Asignaciones Canceladas
                </button>
                
                <button type="submit" name="tipo_reporte" value="completo" class="btn-pdf">
                    📋 PDF Reporte Completo
                </button>
                
                <button type="submit" name="tipo_reporte" value="estadisticas" class="btn-pdf">
                    📊 PDF Estadísticas AHP
                </button>
            </div>
        </form>
    </div>

    <?php
        // Obtener resumen usando consultas directas (evitando la vista problemática)
        try {
            echo "<!-- DEBUG: Iniciando resumen -->\n";
            
            $query_resumen = "
                SELECT 
                    t.nombre_discapacidad, 
                    COUNT(a.id_asignacion) as total_asignaciones, 
                    SUM(CASE WHEN a.estado = 'Activa' THEN 1 ELSE 0 END) as asignaciones_activas,
                    SUM(CASE WHEN a.estado = 'Cancelada' THEN 1 ELSE 0 END) as asignaciones_canceladas,
                    AVG(CASE WHEN a.estado = 'Activa' THEN a.puntuacion_ahp END) as promedio_puntuacion
                FROM tipos_discapacidad t
                LEFT JOIN asignaciones a ON t.id_tipo_discapacidad = a.id_tipo_discapacidad
                GROUP BY t.id_tipo_discapacidad, t.nombre_discapacidad
                ORDER BY t.peso_prioridad DESC";
            
            $stmt_resumen = $conn->prepare($query_resumen);
            $stmt_resumen->execute();
            $resumen = $stmt_resumen->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<!-- DEBUG: Resumen obtenido: " . count($resumen) . " tipos -->\n";
        ?>
        
        <h3>Resumen de Asignaciones por Tipo de Discapacidad</h3>
        <table class="table">
            <tr>
                <th>Tipo de Discapacidad</th>
                <th>Total Asignaciones</th>
                <th>Asignaciones Activas</th>
                <th>Asignaciones Canceladas</th>
                <th>Puntuación Promedio</th>
            </tr>
            <?php foreach ($resumen as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['nombre_discapacidad']); ?></td>
                    <td><?php echo $row['total_asignaciones'] ?: 0; ?></td>
                    <td style="color: green; font-weight: bold;"><?php echo $row['asignaciones_activas'] ?: 0; ?></td>
                    <td style="color: red;"><?php echo $row['asignaciones_canceladas'] ?: 0; ?></td>
                    <td><?php echo $row['promedio_puntuacion'] ? number_format($row['promedio_puntuacion'], 3) : 'N/A'; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        
        <?php
        } catch (Exception $e) {
            echo "<div class='alert alert-error'>Error en resumen: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<!-- ERROR DETALLE: " . $e->getFile() . " línea " . $e->getLine() . " -->\n";
        }
        
        // Obtener asignaciones detalladas (consulta directa)
        try {
            echo "<!-- DEBUG: Iniciando asignaciones detalladas -->\n";
            
            $query_asignaciones_detalle = "
                SELECT 
                    a.id_asignacion, 
                    d.nombres_completos AS docente, 
                    e.nombres_completos AS estudiante,
                    t.nombre_discapacidad, 
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
                WHERE a.estado = 'Activa'
                ORDER BY a.fecha_asignacion DESC
                LIMIT 50";
            
            $stmt_detalle = $conn->prepare($query_asignaciones_detalle);
            $stmt_detalle->execute();
            $asignaciones_detalle = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<!-- DEBUG: Asignaciones detalladas: " . count($asignaciones_detalle) . " -->\n";
        ?>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>Detalle de Asignaciones Activas (Últimas 50)</h3>
            <?php
            // Verificar historial
            $query_count_historial = "SELECT COUNT(*) FROM asignaciones_historial";
            $count_historial = $conn->query($query_count_historial)->fetchColumn();
            
            if ($count_historial > 0):
            ?>
            <form action="../procesar/procesar_eliminar_asignacion.php" method="POST" style="display: inline;">
                <input type="hidden" name="limpiar_historial" value="1">
                <button type="submit" class="btn" style="background: #ffc107; color: #212529;" onclick="return confirmarLimpiarHistorial()">
                    Limpiar Historial (<?php echo $count_historial; ?> registros)
                </button>
            </form>
            <?php endif; ?>
        </div>
        
        <div style="overflow-x: auto; margin: 20px 0; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <table class="table" style="min-width: 1000px;">
                <tr>
                    <th>Docente</th>
                    <th>Estudiante</th>
                    <th>Tipo de Discapacidad</th>
                    <th>Materia</th>
                    <th>Ciclo Académico</th>
                    <th>Puntuación AHP</th>
                    <th>Experiencia</th>
                    <th>Fecha Asignación</th>
                    <th>Acciones</th>
                </tr>
                <?php if (count($asignaciones_detalle) > 0): ?>
                    <?php foreach ($asignaciones_detalle as $asignacion): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($asignacion['docente'] ?: 'No asignado'); ?></td>
                            <td><?php echo htmlspecialchars($asignacion['estudiante'] ?: 'No asignado'); ?></td>
                            <td><?php echo htmlspecialchars($asignacion['nombre_discapacidad']); ?></td>
                            <td><?php echo htmlspecialchars($asignacion['nombre_materia'] ?: 'No especificada'); ?></td>
                            <td><?php echo htmlspecialchars($asignacion['ciclo_academico']); ?></td>
                            <td><?php echo number_format($asignacion['puntuacion_ahp'], 3); ?></td>
                            <td>
                                <?php if ($asignacion['tiene_experiencia']): ?>
                                    <span style="color: #28a745;">✓ <?php echo htmlspecialchars($asignacion['nivel_competencia']); ?></span>
                                <?php else: ?>
                                    <span style="color: #dc3545;">✗ Sin experiencia</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($asignacion['fecha_asignacion'])); ?></td>
                            <td>
                                <form action="../procesar/procesar_eliminar_asignacion.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="eliminar_desde_reporte" value="<?php echo $asignacion['id_asignacion']; ?>">
                                    <button type="submit" class="btn" style="background: #dc3545; padding: 5px 10px; font-size: 12px;" onclick="return confirmarEliminacion('¿Está seguro de que desea eliminar esta asignación?')">
                                        Eliminar
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No hay asignaciones activas para mostrar.</td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <?php
        } catch (Exception $e) {
            echo "<div class='alert alert-error'>Error en asignaciones detalladas: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<!-- ERROR DETALLE: " . $e->getFile() . " línea " . $e->getLine() . " -->\n";
        }
        
        // Historial de asignaciones canceladas
        try {
            echo "<!-- DEBUG: Iniciando historial -->\n";
            
            $query_historial = "
                SELECT 
                    ah.id_historial, 
                    d.nombres_completos AS docente, 
                    e.nombres_completos AS estudiante,
                    t.nombre_discapacidad, 
                    m.nombre_materia, 
                    ah.ciclo_academico, 
                    ah.puntuacion_ahp, 
                    ah.estado, 
                    ah.fecha_asignacion, 
                    ah.fecha_eliminacion
                FROM asignaciones_historial ah
                LEFT JOIN docentes d ON ah.id_docente = d.id_docente
                LEFT JOIN estudiantes e ON ah.id_estudiante = e.id_estudiante
                JOIN tipos_discapacidad t ON ah.id_tipo_discapacidad = t.id_tipo_discapacidad
                LEFT JOIN materias m ON ah.id_materia = m.id_materia
                ORDER BY ah.fecha_eliminacion DESC
                LIMIT 50";
            
            $stmt_historial = $conn->prepare($query_historial);
            $stmt_historial->execute();
            $historial_asignaciones = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<!-- DEBUG: Historial: " . count($historial_asignaciones) . " registros -->\n";
        ?>
        
        <h3>Historial de Asignaciones Canceladas (Últimas 50)</h3>
        
        <?php if (count($historial_asignaciones) > 0): ?>
            <div style="overflow-x: auto; margin: 20px 0; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <table class="table" style="min-width: 1000px;">
                    <tr>
                        <th>Docente</th>
                        <th>Estudiante</th>
                        <th>Tipo de Discapacidad</th>
                        <th>Materia</th>
                        <th>Ciclo Académico</th>
                        <th>Puntuación AHP</th>
                        <th>Fecha Asignación</th>
                        <th>Fecha Cancelación</th>
                        <th>Estado</th>
                    </tr>
                    <?php foreach ($historial_asignaciones as $hist): ?>
                        <tr style="opacity: 0.7;">
                            <td><?php echo htmlspecialchars($hist['docente'] ?: 'No asignado'); ?></td>
                            <td><?php echo htmlspecialchars($hist['estudiante'] ?: 'No asignado'); ?></td>
                            <td><?php echo htmlspecialchars($hist['nombre_discapacidad']); ?></td>
                            <td><?php echo htmlspecialchars($hist['nombre_materia'] ?: 'No especificada'); ?></td>
                            <td><?php echo htmlspecialchars($hist['ciclo_academico']); ?></td>
                            <td><?php echo number_format($hist['puntuacion_ahp'], 3); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($hist['fecha_asignacion'])); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($hist['fecha_eliminacion'])); ?></td>
                            <td><span style="color: #dc3545;"><?php echo htmlspecialchars($hist['estado']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php else: ?>
            <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 10px; text-align: center;">
                <p style="color: white; margin: 0; font-size: 16px;">
                    📝 No hay registros en el historial de asignaciones canceladas
                </p>
            </div>
        <?php endif; ?>
        
        <?php
        } catch (Exception $e) {
            echo "<div class='alert alert-error'>Error en historial: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<!-- ERROR DETALLE: " . $e->getFile() . " línea " . $e->getLine() . " -->\n";
        }
        
        // Gráfico usando datos disponibles
        try {
            echo "<!-- DEBUG: Preparando datos para gráfico -->\n";
        ?>
        
        <h3>Gráfico de Distribución de Asignaciones</h3>
        <div style="width: 100%; height: 400px; margin: 20px 0;">
            <canvas id="asignacionesChart"></canvas>
        </div>
        
        <?php
        } catch (Exception $e) {
            echo "<div class='alert alert-error'>Error preparando gráfico: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='alert alert-error'>Error general: " . htmlspecialchars($e->getMessage()) . "</div>";
        echo "<!-- ERROR GENERAL: " . $e->getFile() . " línea " . $e->getLine() . " -->\n";
    }
    ?>
    
    <script>
        function confirmarEliminacion(mensaje) {
            return confirm(mensaje);
        }
        
        function confirmarLimpiarHistorial() {
            return confirm('⚠️ ATENCIÓN: Esta acción eliminará permanentemente todo el historial de asignaciones canceladas.\n\nEsto incluye:\n• Todas las asignaciones canceladas\n• Todo el registro histórico\n\n¿Está seguro de que desea continuar?\n\nEsta acción NO se puede deshacer.');
        }
        
        // Crear gráfico de asignaciones
        <?php if (isset($resumen) && !empty($resumen)): ?>
        const ctx = document.getElementById('asignacionesChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: [<?php echo "'" . implode("','", array_map(function($item) { return addslashes($item['nombre_discapacidad']); }, $resumen)) . "'"; ?>],
                    datasets: [
                        {
                            label: 'Asignaciones Activas',
                            data: [<?php echo implode(',', array_map(function($item) { return $item['asignaciones_activas'] ?: 0; }, $resumen)); ?>],
                            backgroundColor: '#36A2EB',
                            borderColor: '#FFFFFF',
                            borderWidth: 1
                        },
                        {
                            label: 'Asignaciones Canceladas',
                            data: [<?php echo implode(',', array_map(function($item) { return $item['asignaciones_canceladas'] ?: 0; }, $resumen)); ?>],
                            backgroundColor: '#FF6384',
                            borderColor: '#FFFFFF',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            title: { display: true, text: 'Número de Asignaciones' } 
                        },
                        x: { 
                            title: { display: true, text: 'Tipo de Discapacidad' } 
                        }
                    },
                    plugins: { 
                        title: { 
                            display: true, 
                            text: 'Distribución de Asignaciones por Tipo de Discapacidad' 
                        },
                        legend: {
                            display: true,
                            position: 'top'
                        }
                    }
                }
            });
        }
        <?php endif; ?>
    </script>
</div>

<?php include '../includes/footer.php'; ?>