<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>
<div id="reportes" class="tab-content" style="display: block;">
    <h2>Reportes de Asignaciones</h2>
    <?php
    include '../includes/conexion.php';
    $conn = ConexionBD();
    if ($conn) {
        // Obtener tipos de discapacidad para subcriterios
        $query_subcriterios = "SELECT * FROM tipos_discapacidad ORDER BY peso_prioridad DESC";
        $stmt_subcriterios = $conn->prepare($query_subcriterios);
        $stmt_subcriterios->execute();
        $subcriterios = $stmt_subcriterios->fetchAll(PDO::FETCH_ASSOC);

        // Obtener resumen de asignaciones por tipo de discapacidad
        $query_resumen = "
            SELECT t.nombre_discapacidad, 
                   COUNT(a.id_asignacion) as total_asignaciones, 
                   SUM(CASE WHEN a.estado = 'Activa' THEN 1 ELSE 0 END) as asignaciones_activas,
                   SUM(CASE WHEN a.estado = 'Cancelada' THEN 1 ELSE 0 END) as asignaciones_canceladas,
                   AVG(CASE WHEN a.estado = 'Activa' THEN a.puntuacion_ahp END) as promedio_puntuacion
            FROM tipos_discapacidad t
            LEFT JOIN asignaciones a ON t.id_tipo_discapacidad = a.id_tipo_discapacidad
            GROUP BY t.id_tipo_discapacidad
            ORDER BY t.peso_prioridad DESC";
        $stmt_resumen = $conn->prepare($query_resumen);
        $stmt_resumen->execute();
        $resumen = $stmt_resumen->fetchAll(PDO::FETCH_ASSOC);

        // Obtener asignaciones detalladas
        $query_asignaciones_detalle = "
            SELECT a.id_asignacion, d.nombres_completos AS docente, e.nombres_completos AS estudiante,
                   t.nombre_discapacidad, m.nombre_materia, a.ciclo_academico, a.puntuacion_ahp, 
                   a.estado, a.fecha_asignacion
            FROM asignaciones a
            LEFT JOIN docentes d ON a.id_docente = d.id_docente
            LEFT JOIN estudiantes e ON a.id_estudiante = e.id_estudiante
            JOIN tipos_discapacidad t ON a.id_tipo_discapacidad = t.id_tipo_discapacidad
            LEFT JOIN materias m ON a.id_materia = m.id_materia
            WHERE a.estado = 'Activa'
            ORDER BY a.fecha_asignacion DESC";
        $stmt_detalle = $conn->prepare($query_asignaciones_detalle);
        $stmt_detalle->execute();
        $asignaciones_detalle = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <h3>Subcriterios (Tipos de Discapacidad)</h3>
    <table class="table">
        <tr>
            <th>Tipo de Discapacidad</th>
            <th>Peso de Prioridad</th>
            <th>Descripción</th>
        </tr>
        <?php foreach ($subcriterios as $subcriterio): ?>
            <tr>
                <td><?php echo htmlspecialchars($subcriterio['nombre_discapacidad']); ?></td>
                <td>
                    <div style="display: flex; align-items: center;">
                        <div class="progress-bar" style="width: 150px; margin-right: 10px;">
                            <div class="progress-fill" style="width: <?php echo $subcriterio['peso_prioridad'] * 100; ?>%;"></div>
                        </div>
                        <?php echo number_format($subcriterio['peso_prioridad'] * 100, 1); ?>%
                    </div>
                </td>
                <td><?php echo htmlspecialchars($subcriterio['descripcion']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    
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
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3>Detalle de Asignaciones Activas</h3>
        <?php
        // Verificar si hay historial para mostrar el botón
        $query_count_historial = "SELECT COUNT(*) FROM asignaciones_historial";
        $count_historial = $conn->query($query_count_historial)->fetchColumn();
        
        if ($count_historial > 0):
        ?>
        <form action="../procesar/procesar_eliminar_asignacion.php" method="POST" style="display: inline;">
            <input type="hidden" name="limpiar_historial" value="1">
            <button type="submit" class="btn" style="background: #ffc107; color: #212529;" onclick="return confirmarLimpiarHistorial()">Limpiar Historial (<?php echo $count_historial; ?> registros)</button>
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
                        <td><?php echo date('d/m/Y H:i', strtotime($asignacion['fecha_asignacion'])); ?></td>
                        <td>
                            <form action="../procesar/procesar_eliminar_asignacion.php" method="POST" style="display: inline;">
                                <input type="hidden" name="eliminar_desde_reporte" value="<?php echo $asignacion['id_asignacion']; ?>">
                                <button type="submit" class="btn" style="background: #dc3545; padding: 5px 10px; font-size: 12px;" onclick="return confirmarEliminacion('¿Está seguro de que desea eliminar esta asignación?')">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center;">No hay asignaciones activas para mostrar.</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <h3>Historial de Asignaciones Canceladas</h3>
    <?php
        // Obtener historial de asignaciones canceladas
        $query_historial = "
            SELECT ah.id_historial, d.nombres_completos AS docente, e.nombres_completos AS estudiante,
                   t.nombre_discapacidad, m.nombre_materia, ah.ciclo_academico, ah.puntuacion_ahp, 
                   ah.estado, ah.fecha_asignacion, ah.fecha_eliminacion
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
    ?>
    
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
        <div style="background: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 10px; margin-top: 10px;">
            <p style="color: white; margin: 0; font-size: 14px;">
                <strong>📋 Historial:</strong> Mostrando las últimas 50 asignaciones canceladas. 
                Use "Limpiar Historial" para eliminar permanentemente estos registros.
            </p>
        </div>
    <?php else: ?>
        <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 10px; text-align: center;">
            <p style="color: white; margin: 0; font-size: 16px;">
                📝 No hay registros en el historial de asignaciones canceladas
            </p>
        </div>
    <?php endif; ?>
    
    <h3>Gráfico de Distribución de Asignaciones</h3>
    <div style="width: 100%; height: 400px; margin: 20px 0;">
        <canvas id="asignacionesChart"></canvas>
    </div>
    
    <script>
        // Función para confirmar eliminación
        function confirmarEliminacion(mensaje) {
            return confirm(mensaje);
        }
        
        // Función para confirmar limpieza de historial
        function confirmarLimpiarHistorial() {
            return confirm('⚠️ ATENCIÓN: Esta acción eliminará permanentemente todo el historial de asignaciones canceladas.\n\nEsto incluye:\n• Todas las asignaciones canceladas\n• Todo el registro histórico\n\n¿Está seguro de que desea continuar?\n\nEsta acción NO se puede deshacer.');
        }
        
        // Crear gráfico de asignaciones
        const ctx = document.getElementById('asignacionesChart').getContext('2d');
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
    </script>
    
    <?php } else { ?>
        <div class="alert alert-error">No se pudo conectar a la base de datos.</div>
    <?php } ?>
</div>
<?php include '../includes/footer.php'; ?>