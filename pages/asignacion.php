<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>
<div id="asignacion" class="tab-content" style="display: block;">
    <h2>Asignación Automática de Docentes - AHP Optimizado</h2>
    
    <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h3 style="color: #28a745;">🎯 Sistema AHP Especializado por Tipo de Discapacidad</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
            <div style="background: rgba(231, 76, 60, 0.2); padding: 15px; border-radius: 8px;">
                <h4 style="color: #e74c3c;">🧠 Psicosocial (40%)</h4>
                <p style="color: white; margin: 5px 0; font-size: 14px;">Prioriza <strong>Experiencia Práctica (50%)</strong> y Formación Específica (26%)</p>
            </div>
            <div style="background: rgba(52, 152, 219, 0.2); padding: 15px; border-radius: 8px;">
                <h4 style="color: #3498db;">🎓 Intelectual (30%)</h4>
                <p style="color: white; margin: 5px 0; font-size: 14px;">Prioriza <strong>Formación Específica (46%)</strong> y Adaptaciones (20%)</p>
            </div>
            <div style="background: rgba(243, 156, 18, 0.2); padding: 15px; border-radius: 8px;">
                <h4 style="color: #f39c12;">👁️ Visual (15%)</h4>
                <p style="color: white; margin: 5px 0; font-size: 14px;">Prioriza <strong>Formación Académica (41%)</strong> y Experiencia General (25%)</p>
            </div>
            <div style="background: rgba(39, 174, 96, 0.2); padding: 15px; border-radius: 8px;">
                <h4 style="color: #27ae60;">👂 Auditiva (10%)</h4>
                <p style="color: white; margin: 5px 0; font-size: 14px;">Prioriza <strong>Experiencia Práctica (42%)</strong> y Experiencia General (27%)</p>
            </div>
            <div style="background: rgba(149, 165, 166, 0.2); padding: 15px; border-radius: 8px;">
                <h4 style="color: #95a5a6;">🦽 Física (5%)</h4>
                <p style="color: white; margin: 5px 0; font-size: 14px;">Prioriza <strong>Adaptaciones Metodológicas (44%)</strong> y Experiencia General (26%)</p>
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
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
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
    
    <!-- Formulario de asignación automática -->
    <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h3>Nueva Asignación Automática AHP</h3>
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
                <button type="submit" class="btn">🔍 Vista Previa de Asignaciones AHP</button>
            </div>
            <small style="color: rgba(255,255,255,0.8); margin-top: 10px; display: block;">
                El sistema usará ranking específico por tipo de discapacidad y bonificaciones por experiencia especializada
            </small>
        </form>
    </div>
    
    <?php if (isset($_GET['preview_data'])): ?>
        <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
            <h3>🔍 Vista Previa de Asignaciones AHP Optimizadas</h3>
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
                <div style="background: rgba(40, 167, 69, 0.2); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <h4 style="color: #28a745;">📊 Estadísticas de la Asignación Propuesta</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
                        <div style="color: white;">
                            <strong>Total de asignaciones:</strong> <?php echo $total_asignaciones; ?>
                        </div>
                        <div style="color: white;">
                            <strong>Puntuación promedio:</strong> <?php echo number_format($puntuacion_promedio, 3); ?>
                        </div>
                        <div style="color: white;">
                            <strong>Con experiencia específica:</strong> <?php echo $con_experiencia; ?> / <?php echo $total_asignaciones; ?> (<?php echo number_format(($con_experiencia / $total_asignaciones) * 100, 1); ?>%)
                        </div>
                        <div style="color: white;">
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
                    
                    <div style="overflow-x: auto; margin: 20px 0; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <table class="table" style="min-width: 1200px;">
                            <tr>
                                <th>Prioridad</th>
                                <th>Estudiante</th>
                                <th>Tipo de Discapacidad</th>
                                <th>Materia</th>
                                <th>Docente Propuesto</th>
                                <th>Puntuación AHP</th>
                                <th>Ranking Específico</th>
                                <th>Experiencia Específica</th>
                            </tr>
                            <?php foreach ($preview_data as $index => $preview): ?>
                                <tr style="<?php echo $preview['peso_discapacidad'] >= 0.3 ? 'background: rgba(231, 76, 60, 0.1);' : ''; ?>">
                                    <td style="text-align: center; font-weight: bold;">
                                        <?php echo number_format($preview['peso_discapacidad'] * 100, 1); ?>%
                                        <?php if ($preview['peso_discapacidad'] >= 0.3): ?>
                                            <span style="color: #e74c3c;">🔥</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($preview['estudiante']); ?></td>
                                    <td>
                                        <span style="color: <?php 
                                            echo $preview['peso_discapacidad'] >= 0.3 ? '#e74c3c' : 
                                                ($preview['peso_discapacidad'] >= 0.15 ? '#f39c12' : '#95a5a6');
                                        ?>">
                                            <?php echo htmlspecialchars($preview['nombre_discapacidad']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($preview['materia']); ?></td>
                                    <td style="font-weight: 600;"><?php echo htmlspecialchars($preview['docente']); ?></td>
                                    <td style="text-align: center; font-weight: bold; color: #28a745;">
                                        <?php echo number_format($preview['puntuacion_ahp'], 3); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        #<?php echo $preview['ranking_original']; ?>
                                        <?php if ($preview['ranking_original'] == 1): ?>
                                            <span style="color: #ffd700;">🥇</span>
                                        <?php elseif ($preview['ranking_original'] <= 3): ?>
                                            <span style="color: #c0c0c0;">🥈</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($preview['tiene_experiencia_especifica']): ?>
                                            <span style="color: #28a745;">✅ <?php echo htmlspecialchars($preview['nivel_competencia']); ?></span>
                                        <?php else: ?>
                                            <span style="color: #e74c3c;">❌ Sin experiencia</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    
                    <div style="margin-top: 15px; text-align: center;">
                        <button type="submit" class="btn" style="background: #28a745; padding: 15px 30px; font-size: 16px;">
                            ✅ Confirmar Asignaciones AHP Optimizadas
                        </button>
                        <a href="asignacion.php" class="btn" style="background: #dc3545; margin-left: 10px; padding: 15px 30px; font-size: 16px;">
                            ❌ Cancelar
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <div class="alert alert-error">No hay estudiantes disponibles para asignar en este ciclo académico.</div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3>Asignaciones Actuales</h3>
        <form action="../procesar/procesar_eliminar_asignacion.php" method="POST" style="display: inline;">
            <input type="hidden" name="eliminar_todas" value="1">
            <button type="submit" class="btn" style="background: #dc3545;" onclick="return confirmarEliminacion('¿Está seguro de que desea eliminar TODAS las asignaciones? Esta acción no se puede deshacer.')">🗑️ Eliminar Todas las Asignaciones</button>
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
        <div style="background: rgba(52, 152, 219, 0.2); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <h4 style="color: #3498db;">📊 Estadísticas de Asignaciones Actuales</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 10px;">
                <div style="color: white;">
                    <strong>Total asignaciones activas:</strong> <?php echo $total_activas; ?>
                </div>
                <div style="color: white;">
                    <strong>Puntuación promedio:</strong> <?php echo number_format($puntuacion_promedio_actual, 3); ?>
                </div>
                <div style="color: white;">
                    <strong>Con experiencia específica:</strong> <?php echo $con_experiencia_actual; ?> / <?php echo $total_activas; ?> (<?php echo number_format(($con_experiencia_actual / $total_activas) * 100, 1); ?>%)
                </div>
                <div style="color: white;">
                    <strong>Última asignación:</strong> <?php echo date('d/m/Y H:i', strtotime($asignaciones[0]['fecha_asignacion'])); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div style="overflow-x: auto; margin: 20px 0; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <table class="table" style="min-width: 1300px;">
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
                        <td style="text-align: center; font-weight: bold;">
                            <?php echo number_format($asignacion['peso_prioridad'] * 100, 1); ?>%
                            <?php if ($asignacion['peso_prioridad'] >= 0.3): ?>
                                <br><span style="color: #e74c3c; font-size: 12px;">ALTA</span>
                            <?php elseif ($asignacion['peso_prioridad'] >= 0.15): ?>
                                <br><span style="color: #f39c12; font-size: 12px;">MEDIA</span>
                            <?php else: ?>
                                <br><span style="color: #95a5a6; font-size: 12px;">BAJA</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($asignacion['docente'] ?: 'No asignado'); ?></td>
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
                        <td style="text-align: center;"><?php echo htmlspecialchars($asignacion['ciclo_academico']); ?></td>
                        <td style="text-align: center; font-weight: bold; color: #28a745;">
                            <?php echo number_format($asignacion['puntuacion_ahp'], 3); ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if ($asignacion['tiene_experiencia_especifica']): ?>
                                <span style="color: #28a745;">✅ <?php echo htmlspecialchars($asignacion['nivel_competencia']); ?></span>
                            <?php else: ?>
                                <span style="color: #e74c3c;">❌ Sin experiencia</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; font-size: 12px;">
                            <?php echo date('d/m/Y', strtotime($asignacion['fecha_asignacion'])); ?><br>
                            <?php echo date('H:i', strtotime($asignacion['fecha_asignacion'])); ?>
                        </td>
                        <td style="text-align: center;">
                            <form action="../procesar/procesar_eliminar_asignacion.php" method="POST" style="display: inline;">
                                <input type="hidden" name="id_asignacion" value="<?php echo $asignacion['id_asignacion']; ?>">
                                <button type="submit" class="btn" style="background: #dc3545; padding: 5px 10px; font-size: 12px;" onclick="return confirmarEliminacion('¿Está seguro de que desea cancelar esta asignación?')">❌ Cancelar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 40px;">
                        <div style="color: #7f8c8d;">
                            <h4>📋 No hay asignaciones activas</h4>
                            <p>Use el formulario de arriba para crear nuevas asignaciones automáticas con AHP</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <?php if (!empty($asignaciones)): ?>
        <div style="background: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 10px; margin-top: 10px;">
            <p style="color: white; margin: 0; font-size: 14px;">
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
<?php include '../includes/footer.php'; ?>
<script>
function confirmarEliminacion(mensaje) {
    return confirm(mensaje);
}
</script>