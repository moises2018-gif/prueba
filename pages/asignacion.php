<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>

<style>
/* Estilos para selector de método */
.metodo-selector {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
    border: 2px solid rgba(102, 126, 234, 0.3);
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 30px;
}

.metodo-selector h3 {
    color: #667eea !important;
    text-align: center;
    margin-bottom: 20px;
}

.metodos-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.metodo-card {
    padding: 20px;
    border-radius: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.metodo-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.metodo-card.selected {
    border-color: #667eea;
    background: rgba(102, 126, 234, 0.1);
}

.metodo-tradicional {
    background: linear-gradient(135deg, rgba(52, 152, 219, 0.15), rgba(41, 128, 185, 0.15));
}

.metodo-hibrido {
    background: linear-gradient(135deg, rgba(155, 89, 182, 0.15), rgba(142, 68, 173, 0.15));
}

.metodo-card h4 {
    color: #2c3e50 !important;
    margin-bottom: 15px;
}

.metodo-card p {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.8);
    padding: 10px;
    border-radius: 6px;
    margin: 5px 0;
    font-size: 13px;
}

.metodo-info {
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 10px;
    margin-top: 15px;
    display: none;
}

.metodo-info.active {
    display: block;
}

.metodo-info h5 {
    color: #2c3e50 !important;
    margin-bottom: 10px;
}

.metodo-info ul {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.8);
    padding: 10px 15px;
    border-radius: 6px;
    margin: 0;
}

.metodo-info li {
    margin: 5px 0;
    font-size: 14px;
}

/* Responsive */
@media (max-width: 768px) {
    .metodos-grid {
        grid-template-columns: 1fr;
    }
}

/* Estilos existentes conservados */
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

.metodo-badge {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    display: inline-block;
    margin-left: 5px;
}

.interval-badge {
    background: rgba(155, 89, 182, 0.2);
    color: #8e44ad;
    padding: 2px 6px;
    border-radius: 8px;
    font-size: 10px;
    margin-left: 5px;
}
</style>

<div id="asignacion" class="tab-content" style="display: block;">
    <h2>🎯 Sistema de Asignación Automática - AHP Avanzado</h2>
    
    <!-- Selector de método -->
    <div class="metodo-selector">
        <h3>🔬 Seleccione el Método de Asignación</h3>
        <div class="metodos-grid">
            <div class="metodo-card metodo-tradicional" data-metodo="tradicional">
                <h4>📊 AHP Tradicional</h4>
                <p><strong>Método Original</strong></p>
                <p>• Valores exactos para todos los criterios</p>
                <p>• Alta precisión numérica</p>
                <p>• Procesamiento rápido</p>
                <p>• Resultados determinísticos</p>
            </div>
            
            <div class="metodo-card metodo-hibrido" data-metodo="hibrido">
                <h4>🔬 AHP Híbrido</h4>
                <p><strong>Método Innovador</strong></p>
                <p>• Tradicional para criterios objetivos</p>
                <p>• Difuso para criterios subjetivos</p>
                <p>• Intervalos de confianza</p>
                <p>• Mayor robustez y flexibilidad</p>
            </div>
        </div>
        
        <!-- Información detallada del método tradicional -->
        <div id="info-tradicional" class="metodo-info">
            <h5>📊 Método AHP Tradicional - Características</h5>
            <ul>
                <li><strong>Todos los criterios:</strong> Valores crisp exactos</li>
                <li><strong>AED:</strong> Años experiencia docente (tradicional)</li>
                <li><strong>NFA:</strong> Nivel formación académica (tradicional)</li>
                <li><strong>FSI:</strong> Formación específica inclusión (tradicional)</li>
                <li><strong>EPR:</strong> Experiencia práctica NEE (tradicional)</li>
                <li><strong>AMI:</strong> Adaptaciones metodológicas (tradicional)</li>
                <li><strong>Ventajas:</strong> Simplicidad, velocidad, resultados exactos</li>
            </ul>
        </div>
        
        <!-- Información detallada del método híbrido -->
        <div id="info-hibrido" class="metodo-info">
            <h5>🔬 Método AHP Híbrido - Características</h5>
            <ul>
                <li><strong>Criterios objetivos (Tradicional):</strong> AED, NFA</li>
                <li><strong>Criterios subjetivos (Difuso):</strong> FSI, EPR, AMI</li>
                <li><strong>Números triangulares:</strong> (inferior, modal, superior)</li>
                <li><strong>Defuzzificación:</strong> Método del centroide</li>
                <li><strong>Bonificación difusa:</strong> Según experiencia específica</li>
                <li><strong>Ventajas:</strong> Manejo incertidumbre, intervalos confianza, mayor robustez</li>
            </ul>
        </div>
    </div>
    
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
        // Obtener ciclos académicos disponibles
        $query_ciclos = "SELECT DISTINCT ciclo_academico FROM materias ORDER BY ciclo_academico DESC";
        $stmt_ciclos = $conn->prepare($query_ciclos);
        $stmt_ciclos->execute();
        $ciclos = $stmt_ciclos->fetchAll(PDO::FETCH_ASSOC);
        
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
    
    <!-- Formulario de asignación unificado -->
    <div class="formulario-box">
        <h3 id="titulo-formulario">🔍 Nueva Asignación AHP</h3>
        <form id="form-asignacion" action="../procesar/procesar_asignacion_automatica.php" method="POST" class="form-group">
            <input type="hidden" id="metodo-seleccionado" name="metodo" value="tradicional">
            
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
                <button type="submit" class="btn" id="btn-preview">
                    🔍 Vista Previa de Asignaciones AHP
                </button>
            </div>
            <small id="descripcion-metodo">
                El sistema usará el método AHP tradicional con valores exactos para todos los criterios
            </small>
        </form>
    </div>
    
    <?php if (isset($_GET['preview_data'])): ?>
        <div class="preview-box">
            <h3 id="titulo-preview">🔍 Vista Previa de Asignaciones AHP</h3>
            <?php
            $preview_data = json_decode(urldecode($_GET['preview_data']), true);
            $metodo_usado = $_GET['metodo'] ?? 'tradicional';
            $estadisticas = isset($_GET['estadisticas']) ? json_decode(urldecode($_GET['estadisticas']), true) : null;
            
            if (!empty($preview_data)): 
                // Calcular estadísticas básicas si no vienen del método híbrido
                if (!$estadisticas) {
                    $total_asignaciones = count($preview_data);
                    $puntuacion_promedio = array_sum(array_column($preview_data, 'puntuacion_ahp')) / $total_asignaciones;
                    $con_experiencia = count(array_filter($preview_data, function($item) { 
                        return isset($item['tiene_experiencia_especifica']) ? $item['tiene_experiencia_especifica'] : false; 
                    }));
                    $por_discapacidad = array();
                    foreach ($preview_data as $item) {
                        $tipo = $item['nombre_discapacidad'];
                        if (!isset($por_discapacidad[$tipo])) {
                            $por_discapacidad[$tipo] = 0;
                        }
                        $por_discapacidad[$tipo]++;
                    }
                    
                    $estadisticas = [
                        'total_asignaciones' => $total_asignaciones,
                        'puntuacion_promedio' => round($puntuacion_promedio, 3),
                        'con_experiencia_especifica' => $con_experiencia,
                        'porcentaje_experiencia' => round(($con_experiencia / $total_asignaciones) * 100, 1),
                        'distribucion_por_tipo' => $por_discapacidad
                    ];
                }
            ?>
                
                <!-- Estadísticas de la vista previa -->
                <div class="stats-box success-stats">
                    <h4>📊 Estadísticas de la Asignación Propuesta 
                        <span class="metodo-badge"><?php echo strtoupper($metodo_usado); ?></span>
                    </h4>
                    <div class="stats-grid">
                        <div class="stats-item">
                            <strong>Total de asignaciones:</strong> <?php echo $estadisticas['total_asignaciones']; ?>
                        </div>
                        <div class="stats-item">
                            <strong>Puntuación promedio:</strong> <?php echo $estadisticas['puntuacion_promedio']; ?>
                        </div>
                        <div class="stats-item">
                            <strong>Con experiencia específica:</strong> <?php echo $estadisticas['con_experiencia_especifica']; ?> / <?php echo $estadisticas['total_asignaciones']; ?> (<?php echo $estadisticas['porcentaje_experiencia']; ?>%)
                        </div>
                        <div class="stats-item">
                            <strong>Distribución:</strong> 
                            <?php foreach ($estadisticas['distribucion_por_tipo'] as $tipo => $cantidad): ?>
                                <?php echo $tipo . ': ' . $cantidad . ' '; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($metodo_usado === 'hibrido' && isset($estadisticas['intervalo_confianza_promedio'])): ?>
                            <div class="stats-item">
                                <strong>Intervalo confianza promedio:</strong>
                                <span class="interval-badge">
                                    [<?php echo $estadisticas['intervalo_confianza_promedio']['inferior']; ?>, 
                                     <?php echo $estadisticas['intervalo_confianza_promedio']['superior']; ?>]
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <form action="../procesar/procesar_asignacion_automatica.php" method="POST" class="form-group">
                    <input type="hidden" name="confirm" value="1">
                    <input type="hidden" name="metodo" value="<?php echo htmlspecialchars($metodo_usado); ?>">
                    <input type="hidden" name="ciclo_academico" value="<?php echo htmlspecialchars($_GET['ciclo_academico']); ?>">
                    <input type="hidden" name="preview_data" value="<?php echo htmlspecialchars($_GET['preview_data']); ?>">
                    
                    <div class="table-container">
                        <table class="table">
                            <tr>
                                <th>Prioridad</th>
                                <th>Estudiante</th>
                                <th>Tipo de Discapacidad</th>
                                <th>Materia</th>
                                <th>Docente Propuesto</th>
                                <th>Puntuación AHP</th>
                                <th>Ranking</th>
                                <th>Experiencia Específica</th>
                                <?php if ($metodo_usado === 'hibrido'): ?>
                                    <th>Intervalo Confianza</th>
                                <?php endif; ?>
                            </tr>
                            <?php foreach ($preview_data as $index => $preview): ?>
                                <tr style="<?php echo ($preview['peso_discapacidad'] ?? 0) >= 0.3 ? 'background: rgba(231, 76, 60, 0.1);' : ''; ?>">
                                    <td class="text-center font-bold">
                                        <?php echo number_format(($preview['peso_discapacidad'] ?? 0) * 100, 1); ?>%
                                        <?php if (($preview['peso_discapacidad'] ?? 0) >= 0.3): ?>
                                            <span style="color: #e74c3c;">🔥</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="font-semibold"><?php echo htmlspecialchars($preview['estudiante']); ?></td>
                                    <td>
                                        <span style="color: <?php 
                                            $peso = $preview['peso_discapacidad'] ?? 0;
                                            echo $peso >= 0.3 ? '#e74c3c' : 
                                                ($peso >= 0.15 ? '#f39c12' : '#95a5a6');
                                        ?>">
                                            <?php echo htmlspecialchars($preview['nombre_discapacidad']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($preview['materia']); ?></td>
                                    <td class="font-semibold"><?php echo htmlspecialchars($preview['docente']); ?></td>
                                    <td class="text-center font-bold text-success">
                                        <?php echo number_format($preview['puntuacion_ahp'], 3); ?>
                                        <?php if ($metodo_usado === 'hibrido'): ?>
                                            <span class="metodo-badge">HÍBRIDO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        #<?php echo $preview['ranking_original'] ?? ($preview['ranking_hibrido'] ?? 'N/A'); ?>
                                        <?php 
                                        $ranking = $preview['ranking_original'] ?? ($preview['ranking_hibrido'] ?? 999);
                                        if ($ranking == 1): ?>
                                            <span style="color: #ffd700;">🥇</span>
                                        <?php elseif ($ranking <= 3): ?>
                                            <span style="color: #c0c0c0;">🥈</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (isset($preview['tiene_experiencia_especifica']) && $preview['tiene_experiencia_especifica']): ?>
                                            <span class="text-success">✅ <?php echo htmlspecialchars($preview['nivel_competencia'] ?? 'Sí'); ?></span>
                                        <?php else: ?>
                                            <span class="text-danger">❌ Sin experiencia</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($metodo_usado === 'hibrido'): ?>
                                        <td class="text-center">
                                            <?php if (isset($preview['intervalos_confianza'])): ?>
                                                <span class="interval-badge">
                                                    [<?php echo number_format($preview['intervalos_confianza']['inferior'], 3); ?>, 
                                                     <?php echo number_format($preview['intervalos_confianza']['superior'], 3); ?>]
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    
                    <div class="text-center mt-20">
                        <button type="submit" class="btn bg-success" style="padding: 15px 30px; font-size: 16px;">
                            ✅ Confirmar Asignaciones <?php echo strtoupper($metodo_usado); ?>
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
    
    <!-- Resto del contenido existente (asignaciones actuales, etc.) -->
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
            ORDER BY t.peso_prioridad DESC, a.puntuacion_ahp DESC, a.fecha_asignacion DESC";
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
                    <strong>Puntuación promedio:</strong> <?php echo number_format($puntuacion_promedio_actual, 3); ?>
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
                <th>Prioridad AHP</th>
                <th>Docente</th>
                <th>Estudiante</th>
                <th>Tipo de Discapacidad</th>
                <th>Materia</th>
                <th>Ciclo Académico</th>
                <th>Puntuación AHP</th>
                <th>Experiencia Específica</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
            <?php if (count($asignaciones) > 0): ?>
                <?php foreach ($asignaciones as $asignacion): ?>
                    <tr style="<?php echo $asignacion['peso_prioridad'] >= 0.3 ? 'background: rgba(231, 76, 60, 0.1);' : ''; ?>">
                        <td class="text-center font-bold">
                            <?php echo number_format($asignacion['peso_prioridad'] * 100, 1); ?>%
                            <?php if ($asignacion['peso_prioridad'] >= 0.3): ?>
                                <br><span style="color: #e74c3c; font-size: 12px;">ALTA</span>
                            <?php elseif ($asignacion['peso_prioridad'] >= 0.15): ?>
                                <br><span style="color: #f39c12; font-size: 12px;">MEDIA</span>
                            <?php else: ?>
                                <br><span style="color: #95a5a6; font-size: 12px;">BAJA</span>
                            <?php endif; ?>
                        </td>
                        <td class="font-semibold"><?php echo htmlspecialchars($asignacion['docente'] ?: 'No asignado'); ?></td>
                        <td><?php echo htmlspecialchars($asignacion['estudiante'] ?: 'No asignado'); ?></td>
                        <td>
                            <span style="color: <?php 
                                echo $asignacion['peso_prioridad'] >= 0.3 ? '#e74c3c' : 
                                    ($asignacion['peso_prioridad'] >= 0.15 ? '#f39c12' : '#95a5a6');
                            ?>; font-weight: bold;">
                                <?php echo htmlspecialchars($asignacion['nombre_discapacidad']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($asignacion['nombre_materia'] ?: 'No especificada'); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($asignacion['ciclo_academico']); ?></td>
                        <td class="text-center font-bold text-success">
                            <?php echo number_format($asignacion['puntuacion_ahp'], 3); ?>
                        </td>
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
                    <td colspan="10" class="empty-state">
                        <h4>📋 No hay asignaciones activas</h4>
                        <p>Use el formulario de arriba para crear nuevas asignaciones automáticas con AHP</p>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <?php if (!empty($asignaciones)): ?>
        <div class="leyenda-box">
            <p>
                <strong>🔍 Leyenda:</strong> 
                <span style="color: #e74c3c;">■</span> Alta prioridad (≥30%) | 
                <span style="color: #f39c12;">■</span> Media prioridad (15-29%) | 
                <span style="color: #95a5a6;">■</span> Baja prioridad (<15%) | 
                ✅ Con experiencia específica | ❌ Sin experiencia específica
            </p>
        </div>
    <?php endif; ?>
    
    <?php } else { ?>
        <div class="alert alert-error">No se pudo conectar a la base de datos.</div>
    <?php } ?>
</div>

<script>
// ============================================
// FUNCIONALIDAD SELECTOR DE MÉTODO
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const metodosCards = document.querySelectorAll('.metodo-card');
    const metodoInput = document.getElementById('metodo-seleccionado');
    const formAsignacion = document.getElementById('form-asignacion');
    const tituloFormulario = document.getElementById('titulo-formulario');
    const btnPreview = document.getElementById('btn-preview');
    const descripcionMetodo = document.getElementById('descripcion-metodo');
    
    // Información para cada método
    const metodosInfo = {
        tradicional: {
            titulo: '📊 Nueva Asignación AHP Tradicional',
            action: '../procesar/procesar_asignacion_automatica.php',
            btnText: '🔍 Vista Previa de Asignaciones AHP Tradicional',
            descripcion: 'El sistema usará el método AHP tradicional con valores exactos para todos los criterios'
        },
        hibrido: {
            titulo: '🔬 Nueva Asignación AHP Híbrida',
            action: '../procesar/procesar_asignacion_hibrida.php',
            btnText: '🔍 Vista Previa de Asignaciones AHP Híbrida',
            descripcion: 'El sistema combinará AHP tradicional (criterios objetivos) y AHP difuso (criterios subjetivos) con intervalos de confianza'
        }
    };
    
    // Manejar selección de método
    metodosCards.forEach(card => {
        card.addEventListener('click', function() {
            const metodo = this.dataset.metodo;
            
            // Actualizar selección visual
            metodosCards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            
            // Actualizar información del método
            const info = metodosInfo[metodo];
            metodoInput.value = metodo;
            tituloFormulario.textContent = info.titulo;
            formAsignacion.action = info.action;
            btnPreview.textContent = info.btnText;
            descripcionMetodo.textContent = info.descripcion;
            
            // Mostrar/ocultar información detallada
            document.querySelectorAll('.metodo-info').forEach(info => info.classList.remove('active'));
            document.getElementById(`info-${metodo}`).classList.add('active');
            
            // Log para debugging
            console.log(`Método seleccionado: ${metodo}`);
            console.log(`Action del formulario: ${formAsignacion.action}`);
        });
    });
    
    // Seleccionar método tradicional por defecto
    document.querySelector('[data-metodo="tradicional"]').click();
    
    // Validación del formulario
    formAsignacion.addEventListener('submit', function(e) {
        const ciclo = document.getElementById('ciclo_academico').value;
        const metodo = metodoInput.value;
        
        if (!ciclo) {
            e.preventDefault();
            alert('Por favor seleccione un ciclo académico');
            return false;
        }
        
        if (!metodo) {
            e.preventDefault();
            alert('Por favor seleccione un método de asignación');
            return false;
        }
        
        // Confirmación para método híbrido (es experimental)
        if (metodo === 'hibrido') {
            const confirmacion = confirm(
                '🔬 Método AHP Híbrido\n\n' +
                'Está a punto de usar el método experimental que combina:\n' +
                '• AHP Tradicional para criterios objetivos (AED, NFA)\n' +
                '• AHP Difuso para criterios subjetivos (FSI, EPR, AMI)\n\n' +
                '¿Desea continuar?'
            );
            
            if (!confirmacion) {
                e.preventDefault();
                return false;
            }
        }
        
        // Mostrar indicador de carga
        btnPreview.textContent = '⏳ Procesando...';
        btnPreview.disabled = true;
        
        console.log(`Enviando formulario con método: ${metodo}, ciclo: ${ciclo}`);
    });
    
    // Efecto hover mejorado para las tarjetas
    metodosCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            if (!this.classList.contains('selected')) {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            if (!this.classList.contains('selected')) {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '';
            }
        });
    });
});

// Función para confirmar eliminación (reutilizada)
function confirmarEliminacion(mensaje) {
    return confirm(mensaje);
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================

// Función para actualizar título de preview según método
function actualizarTituloPreview() {
    const metodo = new URLSearchParams(window.location.search).get('metodo');
    const tituloPreview = document.getElementById('titulo-preview');
    
    if (tituloPreview && metodo) {
        if (metodo === 'hibrido') {
            tituloPreview.textContent = '🔬 Vista Previa de Asignaciones AHP Híbridas';
        } else {
            tituloPreview.textContent = '🔍 Vista Previa de Asignaciones AHP Tradicionales';
        }
    }
}

// Ejecutar al cargar la página
document.addEventListener('DOMContentLoaded', actualizarTituloPreview);

// Función para mostrar detalles del método híbrido
function mostrarDetallesHibrido() {
    const detalles = `
🔬 MÉTODO AHP HÍBRIDO - DETALLES TÉCNICOS

📊 CRITERIOS OBJETIVOS (AHP Tradicional):
• AED: Años de Experiencia Docente
• NFA: Nivel de Formación Académica
→ Valores exactos, sin incertidumbre

🌀 CRITERIOS SUBJETIVOS (AHP Difuso):
• FSI: Formación Específica en Inclusión
• EPR: Experiencia Práctica con NEE  
• AMI: Adaptaciones Metodológicas
→ Números triangulares (l, m, u)
→ Intervalos de confianza al 95%

🎯 PROCESO HÍBRIDO:
1. Aplicar AHP tradicional a criterios objetivos
2. Aplicar AHP difuso a criterios subjetivos
3. Combinar resultados mediante defuzzificación
4. Aplicar bonificación difusa por experiencia específica
5. Generar ranking final con intervalos de confianza

📈 VENTAJAS:
• Mayor robustez en decisiones
• Manejo de incertidumbre experta
• Precisión numérica donde corresponde
• Flexibilidad en evaluación cualitativa
    `;
    
    alert(detalles);
}

// Función para comparar métodos (si se implementa)
function compararMetodos() {
    console.log('Funcionalidad de comparación de métodos - Por implementar');
    // Aquí se podría implementar una comparación side-by-side
}
</script>

<?php include '../includes/footer.php'; ?>