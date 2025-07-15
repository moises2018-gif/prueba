<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>

<style>
/* Estilos específicos para la página de asignación */
.asignacion-info-box {
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.asignacion-info-box h3 {
    color: #28a745 !important;
    margin-bottom: 15px;
}

/* Estilos para el panel desplegable */
.panel-tecnico {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    margin-bottom: 20px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.panel-header {
    background: rgba(255, 255, 255, 0.2);
    padding: 15px 20px;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    transition: background 0.3s ease;
}

.panel-header:hover {
    background: rgba(255, 255, 255, 0.25);
}

.panel-header h4 {
    color: #2c3e50 !important;
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.panel-toggle {
    color: #2c3e50 !important;
    font-size: 18px;
    font-weight: bold;
    transition: transform 0.3s ease;
}

.panel-toggle.rotated {
    transform: rotate(180deg);
}

.panel-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.4s ease, padding 0.3s ease;
    padding: 0 20px;
}

.panel-content.expanded {
    max-height: 1000px;
    padding: 20px;
}

.asignacion-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.tipo-discapacidad-card {
    padding: 15px;
    border-radius: 8px;
}

.tipo-discapacidad-card h4 {
    margin-bottom: 10px;
    font-weight: 600;
}

.tipo-discapacidad-card p {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.8);
    padding: 8px;
    border-radius: 5px;
    margin: 5px 0;
    font-size: 14px;
}

.tipo-discapacidad-card strong {
    color: #155724 !important;
}

.formulario-box {
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.formulario-box h3 {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.8);
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.formulario-box small {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.8);
    padding: 8px;
    border-radius: 5px;
    margin-top: 10px;
    display: block;
}

.preview-box {
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.preview-box h3 {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.8);
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.stats-box {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.stats-box h4 {
    margin-bottom: 10px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.stats-item {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.8);
    padding: 10px;
    border-radius: 5px;
}

.stats-item strong {
    color: #155724 !important;
}

.success-stats {
    background: rgba(40, 167, 69, 0.2);
}

.success-stats h4 {
    color: #28a745 !important;
}

.info-stats {
    background: rgba(52, 152, 219, 0.2);
}

.info-stats h4 {
    color: #3498db !important;
}

.leyenda-box {
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 10px;
    margin-top: 10px;
}

.leyenda-box p {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.8);
    padding: 10px;
    border-radius: 5px;
    margin: 0;
    font-size: 14px;
}

.leyenda-box strong {
    color: #155724 !important;
}

.empty-state {
    text-align: center;
    padding: 40px;
}

.empty-state h4 {
    color: #7f8c8d !important;
    margin-bottom: 10px;
}

.empty-state p {
    color: #7f8c8d !important;
}

/* Responsive */
@media (max-width: 768px) {
    .asignacion-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .panel-header h4 {
        font-size: 14px;
    }
}
</style>

<div id="asignacion" class="tab-content" style="display: block;">
    <h2>Asignación Automática de Docentes</h2>
    
    <!-- Panel desplegable para información técnica -->
    <div class="panel-tecnico">
        <div class="panel-header" onclick="togglePanel()">
            <h4>📊 Ver Información Técnica del Sistema AHP</h4>
            <span class="panel-toggle" id="panelToggle">▼</span>
        </div>
        <div class="panel-content" id="panelContent">
            <div class="asignacion-info-box">
                <h3>🎯 Sistema AHP Especializado por Tipo de Discapacidad</h3>
                <div class="asignacion-grid">
                    <div class="tipo-discapacidad-card" style="background: rgba(231, 76, 60, 0.2);">
                        <h4 style="color: #e74c3c;">🧠 Psicosocial (40%)</h4>
                        <p>Prioriza <strong>Experiencia Práctica (50%)</strong> y Formación Específica (26%)</p>
                    </div>
                    <div class="tipo-discapacidad-card" style="background: rgba(52, 152, 219, 0.2);">
                        <h4 style="color: #3498db;">🎓 Intelectual (30%)</h4>
                        <p>Prioriza <strong>Formación Específica (46%)</strong> y Adaptaciones (20%)</p>
                    </div>
                    <div class="tipo-discapacidad-card" style="background: rgba(243, 156, 18, 0.2);">
                        <h4 style="color: #f39c12;">👁️ Visual (15%)</h4>
                        <p>Prioriza <strong>Formación Académica (41%)</strong> y Experiencia General (25%)</p>
                    </div>
                    <div class="tipo-discapacidad-card" style="background: rgba(39, 174, 96, 0.2);">
                        <h4 style="color: #27ae60;">👂 Auditiva (10%)</h4>
                        <p>Prioriza <strong>Experiencia Práctica (42%)</strong> y Experiencia General (27%)</p>
                    </div>
                    <div class="tipo-discapacidad-card" style="background: rgba(149, 165, 166, 0.2);">
                        <h4 style="color: #95a5a6;">🦽 Física (5%)</h4>
                        <p>Prioriza <strong>Adaptaciones Metodológicas (44%)</strong> y Experiencia General (26%)</p>
                    </div>
                </div>
            </div>
            
            <?php
            include '../includes/conexion.php';
            $conn = ConexionBD();
            if ($conn) {
                // Obtener estadísticas de estudiantes por tipo de discapacidad
                $query_stats_estudiantes = "
                    SELECT td.nombre_discapacidad, td.peso_prioridad,
                           COUNT(e.id_estudiante) as total_estudiantes,
                           COUNT(CASE WHEN a.estado = 'Activa' THEN 1 END) as con_asignacion,
                           COUNT(CASE WHEN a.estado IS NULL THEN 1 END) as sin_asignacion
                    FROM tipos_discapacidad td
                    LEFT JOIN estudiantes e ON td.id_tipo_discapacidad = e.id_tipo_discapacidad
                    LEFT JOIN asignaciones a ON e.id_estudiante = a.id_estudiante
                    GROUP BY td.id_tipo_discapacidad
                    ORDER BY td.peso_prioridad DESC";
                $stmt_stats = $conn->prepare($query_stats_estudiantes);
                $stmt_stats->execute();
                $stats_estudiantes = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <!-- Estadísticas de estudiantes por tipo de discapacidad -->
            <h3>Estado de Estudiantes por Tipo de Discapacidad</h3>
            <div class="ahp-results">
                <?php foreach ($stats_estudiantes as $stat): ?>
                    <div class="ahp-card" style="background: linear-gradient(135deg, 
                        <?php 
                        echo $stat['peso_prioridad'] >= 0.3 ? '#e74c3c, #c0392b' :  
                            ($stat['peso_prioridad'] >= 0.15 ? '#f39c12, #e67e22' : '#95a5a6, #7f8c8d'); 
                        ?> 100%);">
                        <h4><?php echo htmlspecialchars($stat['nombre_discapacidad']); ?></h4>
                        <p><strong>Prioridad:</strong> <?php echo number_format($stat['peso_prioridad'] * 100, 1); ?>%</p>
                        <p><strong>Total:</strong> <?php echo $stat['total_estudiantes'] ?: 0; ?> estudiantes</p>
                        <p><strong>Con asignación:</strong> <?php echo $stat['con_asignacion'] ?: 0; ?></p>
                        <p><strong>Sin asignación:</strong> <?php echo $stat['sin_asignacion'] ?: 0; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php } ?>
        </div>
    </div>
    
    <?php
    if (!$conn) {
        $conn = ConexionBD();
    }
    
    if ($conn) {
        // Obtener ciclos académicos disponibles
        $query_ciclos = "SELECT DISTINCT ciclo_academico FROM materias ORDER BY ciclo_academico DESC";
        $stmt_ciclos = $conn->prepare($query_ciclos);
        $stmt_ciclos->execute();
        $ciclos = $stmt_ciclos->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <!-- Formulario de asignación automática -->
    <div class="formulario-box">
        <h3>Nueva Asignación Automática</h3>
        <form action="../procesar/procesar_asignacion_automatica.php" method="POST" class="form-group">
            <label for="ciclo_academico">Ciclo Académico:</label>
            <select name="ciclo_academico" id="ciclo_academico" required>
                <option value="">Seleccione un ciclo académico</option>
                <?php foreach ($ciclos as $ciclo): ?>
                    <option value="<?php echo htmlspecialchars($ciclo['ciclo_academico']); ?>">
                        <?php echo htmlspecialchars($ciclo['ciclo_academico']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="preview" value="1">
            <div style="margin-top: 15px;">
                <button type="submit" class="btn">🔍 Vista Previa de Asignaciones</button>
            </div>
            <small>
                El sistema asignará automáticamente docentes a estudiantes usando criterios optimizados
            </small>
        </form>
    </div>
    
    <?php if (isset($_GET['preview_data'])): ?>
        <div class="preview-box">
            <h3>🔍 Vista Previa de Asignaciones Optimizadas</h3>
            <?php
            $preview_data = json_decode(urldecode($_GET['preview_data']), true);
            if (!empty($preview_data)): 
                // Calcular estadísticas de la vista previa
                $total_asignaciones = count($preview_data);
                $puntuacion_promedio = array_sum(array_column($preview_data, 'puntuacion_ahp')) / $total_asignaciones;
                $con_experiencia = count(array_filter($preview_data, function($item) { 
                    return $item['tiene_experiencia_especifica']; 
                }));
                $por_discapacidad = array();
                foreach ($preview_data as $item) {
                    $tipo = $item['nombre_discapacidad'];
                    if (!isset($por_discapacidad[$tipo])) {
                        $por_discapacidad[$tipo] = 0;
                    }
                    $por_discapacidad[$tipo]++;
                }
            ?>
                
                <!-- Estadísticas de la vista previa -->
                <div class="stats-box success-stats">
                    <h4>📊 Estadísticas de la Asignación Propuesta</h4>
                    <div class="stats-grid">
                        <div class="stats-item">
                            <strong>Total de asignaciones:</strong> <?php echo $total_asignaciones; ?>
                        </div>
                        <div class="stats-item">
                            <strong>Asignaciones exitosas:</strong> <?php echo $total_asignaciones; ?>
                        </div>
                        <div class="stats-item">
                            <strong>Con experiencia específica:</strong> <?php echo $con_experiencia; ?> / <?php echo $total_asignaciones; ?> (<?php echo number_format(($con_experiencia / $total_asignaciones) * 100, 1); ?>%)
                        </div>
                        <div class="stats-item">
                            <strong>Distribución:</strong> 
                            <?php foreach ($por_discapacidad as $tipo => $cantidad): ?>
                                <?php echo $tipo . ': ' . $cantidad . ' '; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <form action="../procesar/procesar_asignacion_automatica.php" method="POST" class="form-group">
                    <input type="hidden" name="confirm" value="1">
                    <input type="hidden" name="ciclo_academico" value="<?php echo htmlspecialchars($_GET['ciclo_academico']); ?>">
                    <input type="hidden" name="preview_data" value="<?php echo htmlspecialchars($_GET['preview_data']); ?>">
                    
                    <div class="table-container">
                        <table class="table">
                            <tr>
                                <th>Estudiante</th>
                                <th>Tipo de Discapacidad</th>
                                <th>Materia</th>
                                <th>Docente Propuesto</th>
                                <th>Experiencia Específica</th>
                            </tr>
                            <?php foreach ($preview_data as $index => $preview): ?>
                                <tr>
                                    <td class="font-semibold"><?php echo htmlspecialchars($preview['estudiante']); ?></td>
                                    <td><?php echo htmlspecialchars($preview['nombre_discapacidad']); ?></td>
                                    <td><?php echo htmlspecialchars($preview['materia']); ?></td>
                                    <td class="font-semibold"><?php echo htmlspecialchars($preview['docente']); ?></td>
                                    <td class="text-center">
                                        <?php if ($preview['tiene_experiencia_especifica']): ?>
                                            <span class="text-success">✅ <?php echo htmlspecialchars($preview['nivel_competencia']); ?></span>
                                        <?php else: ?>
                                            <span class="text-danger">❌ Sin experiencia</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    
                    <div class="text-center mt-20">
                        <button type="submit" class="btn bg-success" style="padding: 15px 30px; font-size: 16px;">
                            ✅ Confirmar Asignaciones Optimizadas
                        </button>
                        <a href="asignacion.php" class="btn bg-danger ml-10" style="padding: 15px 30px; font-size: 16px;">
                            ❌ Cancelar
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-error">No hay estudiantes disponibles para asignar en este ciclo académico.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div class="d-flex justify-between align-center mb-20">
        <h3>Asignaciones Actuales</h3>
        <form action="../procesar/procesar_eliminar_asignacion.php" method="POST">
            <input type="hidden" name="eliminar_todas" value="1">
            <button type="submit" class="btn bg-danger" onclick="return confirmarEliminacion('¿Está seguro de que desea eliminar TODAS las asignaciones? Esta acción no se puede deshacer.')">
                🗑️ Eliminar Todas las Asignaciones
            </button>
        </form>
    </div>
    
    <?php
        $query_asignaciones = "
            SELECT a.id_asignacion, d.nombres_completos AS docente, e.nombres_completos AS estudiante,
                   t.nombre_discapacidad, t.peso_prioridad, m.nombre_materia, a.ciclo_academico, 
                   a.puntuacion_ahp, a.estado, a.fecha_asignacion,
                   COALESCE(edd.tiene_experiencia, 0) as tiene_experiencia_especifica,
                   COALESCE(edd.nivel_competencia, 'Básico') as nivel_competencia
            FROM asignaciones a
            LEFT JOIN docentes d ON a.id_docente = d.id_docente
            LEFT JOIN estudiantes e ON a.id_estudiante = e.id_estudiante
            JOIN tipos_discapacidad t ON a.id_tipo_discapacidad = t.id_tipo_discapacidad
            LEFT JOIN materias m ON a.id_materia = m.id_materia
            LEFT JOIN experiencia_docente_discapacidad edd ON d.id_docente = edd.id_docente 
                                                           AND a.id_tipo_discapacidad = edd.id_tipo_discapacidad
            WHERE a.estado = 'Activa'
            ORDER BY a.fecha_asignacion DESC";
        $stmt_asignaciones = $conn->prepare($query_asignaciones);
        $stmt_asignaciones->execute();
        $asignaciones = $stmt_asignaciones->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular estadísticas de asignaciones actuales
        if (!empty($asignaciones)) {
            $total_activas = count($asignaciones);
            $puntuacion_promedio_actual = array_sum(array_column($asignaciones, 'puntuacion_ahp')) / $total_activas;
            $con_experiencia_actual = count(array_filter($asignaciones, function($item) { 
                return $item['tiene_experiencia_especifica']; 
            }));
        }
    ?>
    
    <?php if (!empty($asignaciones)): ?>
        <!-- Estadísticas de asignaciones actuales -->
        <div class="stats-box info-stats">
            <h4>📊 Estadísticas de Asignaciones Actuales</h4>
            <div class="stats-grid">
                <div class="stats-item">
                    <strong>Total asignaciones activas:</strong> <?php echo $total_activas; ?>
                </div>
                <div class="stats-item">
                    <strong>Asignaciones completadas:</strong> <?php echo $total_activas; ?>
                </div>
                <div class="stats-item">
                    <strong>Con experiencia específica:</strong> <?php echo $con_experiencia_actual; ?> / <?php echo $total_activas; ?> (<?php echo number_format(($con_experiencia_actual / $total_activas) * 100, 1); ?>%)
                </div>
                <div class="stats-item">
                    <strong>Última asignación:</strong> <?php echo date('d/m/Y H:i', strtotime($asignaciones[0]['fecha_asignacion'])); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="table-container">
        <table class="table">
            <tr>
                <th>Docente</th>
                <th>Estudiante</th>
                <th>Tipo de Discapacidad</th>
                <th>Materia</th>
                <th>Experiencia Específica</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
            <?php if (count($asignaciones) > 0): ?>
                <?php foreach ($asignaciones as $asignacion): ?>
                    <tr>
                        <td class="font-semibold"><?php echo htmlspecialchars($asignacion['docente'] ?: 'No asignado'); ?></td>
                        <td><?php echo htmlspecialchars($asignacion['estudiante'] ?: 'No asignado'); ?></td>
                        <td><?php echo htmlspecialchars($asignacion['nombre_discapacidad']); ?></td>
                        <td><?php echo htmlspecialchars($asignacion['nombre_materia'] ?: 'No especificada'); ?></td>
                        <td class="text-center">
                            <?php if ($asignacion['tiene_experiencia_especifica']): ?>
                                <span class="text-success">✅ <?php echo htmlspecialchars($asignacion['nivel_competencia']); ?></span>
                            <?php else: ?>
                                <span class="text-danger">❌ Sin experiencia</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center font-small">
                            <?php echo date('d/m/Y', strtotime($asignacion['fecha_asignacion'])); ?><br>
                            <?php echo date('H:i', strtotime($asignacion['fecha_asignacion'])); ?>
                        </td>
                        <td class="text-center">
                            <form action="../procesar/procesar_eliminar_asignacion.php" method="POST" style="display: inline;">
                                <input type="hidden" name="id_asignacion" value="<?php echo $asignacion['id_asignacion']; ?>">
                                <button type="submit" class="btn bg-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirmarEliminacion('¿Está seguro de que desea cancelar esta asignación?')">
                                    ❌ Cancelar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="empty-state">
                        <h4>📋 No hay asignaciones activas</h4>
                        <p>Use el formulario de arriba para crear nuevas asignaciones automáticas</p>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <?php } else { ?>
        <div class="alert alert-error">No se pudo conectar a la base de datos.</div>
    <?php } ?>
</div>

<script>
// Función para alternar el panel desplegable
function togglePanel() {
    const content = document.getElementById('panelContent');
    const toggle = document.getElementById('panelToggle');
    
    content.classList.toggle('expanded');
    toggle.classList.toggle('rotated');
    
    if (content.classList.contains('expanded')) {
        toggle.textContent = '▲';
    } else {
        toggle.textContent = '▼';
    }
}

function confirmarEliminacion(mensaje) {
    return confirm(mensaje);
}
</script>

<?php include '../includes/footer.php'; ?>