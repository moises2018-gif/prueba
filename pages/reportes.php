<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>

<style>
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

.btn-pdf-activas {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
}

.btn-pdf-activas:hover {
    box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
}

.btn-pdf-canceladas {
    background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
}

.btn-pdf-canceladas:hover {
    box-shadow: 0 5px 15px rgba(255, 193, 7, 0.4);
}

.btn-pdf-completo {
    background: linear-gradient(135deg, #6f42c1 0%, #6610f2 100%);
}

.btn-pdf-completo:hover {
    box-shadow: 0 5px 15px rgba(111, 66, 193, 0.4);
}

.filtros-pdf {
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    border-radius: 10px;
    margin: 20px 0;
}

.filtros-pdf h4 {
    color: white;
    margin-bottom: 15px;
    text-align: center;
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

@media (max-width: 768px) {
    .pdf-buttons-container {
        flex-direction: column;
        align-items: center;
    }
    
    .btn-pdf {
        width: 100%;
        max-width: 300px;
        justify-content: center;
    }
}
</style>

<div id="reportes" class="tab-content" style="display: block;">
    <h2>Reportes de Asignaciones</h2>
    
    <!-- Botones para generar PDFs -->
    <div class="filtros-pdf">
        <h4>📄 Generar Reportes PDF</h4>
        <form id="filtrosPDF" action="../procesar/generar_pdf.php" method="POST" target="_blank">
            <div class="filtros-grid">
                <div class="filtro-item">
                    <label for="ciclo_filtro">Ciclo Académico:</label>
                    <select id="ciclo_filtro" name="ciclo_filtro">
                        <option value="">Todos los ciclos</option>
                        <?php
                        include '../includes/conexion.php';
                        $conn = ConexionBD();
                        if ($conn) {
                            $query_ciclos = "SELECT DISTINCT ciclo_academico FROM asignaciones ORDER BY ciclo_academico DESC";
                            $stmt_ciclos = $conn->prepare($query_ciclos);
                            $stmt_ciclos->execute();
                            $ciclos = $stmt_ciclos->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($ciclos as $ciclo) {
                                echo "<option value='{$ciclo['ciclo_academico']}'>{$ciclo['ciclo_academico']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="filtro-item">
                    <label for="discapacidad_filtro">Tipo Discapacidad:</label>
                    <select id="discapacidad_filtro" name="discapacidad_filtro">
                        <option value="">Todos los tipos</option>
                        <?php
                        if ($conn) {
                            $query_tipos = "SELECT * FROM tipos_discapacidad ORDER BY peso_prioridad DESC";
                            $stmt_tipos = $conn->prepare($query_tipos);
                            $stmt_tipos->execute();
                            $tipos = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($tipos as $tipo) {
                                echo "<option value='{$tipo['id_tipo_discapacidad']}'>{$tipo['nombre_discapacidad']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="filtro-item">
                    <label for="docente_filtro">Docente:</label>
                    <select id="docente_filtro" name="docente_filtro">
                        <option value="">Todos los docentes</option>
                        <?php
                        if ($conn) {
                            $query_docentes = "SELECT DISTINCT d.id_docente, d.nombres_completos 
                                             FROM docentes d 
                                             JOIN asignaciones a ON d.id_docente = a.id_docente 
                                             ORDER BY d.nombres_completos";
                            $stmt_docentes = $conn->prepare($query_docentes);
                            $stmt_docentes->execute();
                            $docentes = $stmt_docentes->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($docentes as $docente) {
                                echo "<option value='{$docente['id_docente']}'>{$docente['nombres_completos']}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                
                <div class="filtro-item">
                    <label for="orden_filtro">Ordenar por:</label>
                    <select id="orden_filtro" name="orden_filtro">
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
                <button type="submit" name="tipo_reporte" value="activas" class="btn-pdf btn-pdf-activas">
                    📋 PDF Asignaciones Activas
                </button>
                
                <button type="submit" name="tipo_reporte" value="canceladas" class="btn-pdf btn-pdf-canceladas">
                    📋 PDF Asignaciones Canceladas
                </button>
                
                <button type="submit" name="tipo_reporte" value="completo" class="btn-pdf btn-pdf-completo">
                    📋 PDF Reporte Completo
                </button>
                
                <button type="submit" name="tipo_reporte" value="estadisticas" class="btn-pdf">
                    📊 PDF Estadísticas AHP
                </button>
            </div>
        </form>
    </div>

    <?php
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