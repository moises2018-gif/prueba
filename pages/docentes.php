<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>
<div id="docentes" class="tab-content" style="display: block;">
    <h2>Listado de Docentes</h2>
    <?php
    include '../includes/conexion.php';
    $conn = ConexionBD();
    if ($conn) {
        $query = "SELECT d.*, 
                  (SELECT COUNT(*) FROM capacitaciones_nee WHERE id_docente = d.id_docente) as total_capacitaciones,
                  (SELECT COUNT(*) FROM experiencia_docente_discapacidad WHERE id_docente = d.id_docente AND tiene_experiencia = TRUE) as tipos_discapacidad_experiencia
                  FROM docentes d
                  ORDER BY d.nombres_completos";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $docentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="ahp-results">
        <div class="ahp-card">
            <h3>Total Docentes</h3>
            <p><?php echo count($docentes); ?> docentes registrados</p>
        </div>
        <div class="ahp-card">
            <h3>Con Formación en Inclusión</h3>
            <p><?php echo count(array_filter($docentes, function($d) { return $d['formacion_inclusion']; })); ?> docentes</p>
        </div>
        <div class="ahp-card">
            <h3>Experiencia Promedio NEE</h3>
            <p><?php echo number_format(array_sum(array_column($docentes, 'experiencia_nee_años')) / count($docentes), 1); ?> años</p>
        </div>
    </div>
    
    <h3>Información Detallada de Docentes</h3>
    <div style="overflow-x: auto; margin: 20px 0; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <table class="table" style="min-width: 1200px;">
            <tr>
                <th style="min-width: 200px;">Nombre</th>
                <th style="min-width: 150px;">Facultad</th>
                <th style="min-width: 100px;">Modalidad</th>
                <th style="min-width: 120px;">Experiencia Docente</th>
                <th style="min-width: 150px;">Título 3er Nivel</th>
                <th style="min-width: 150px;">Título 4to Nivel</th>
                <th style="min-width: 120px;">Formación Inclusión</th>
                <th style="min-width: 120px;">Experiencia NEE</th>
                <th style="min-width: 120px;">Capacitaciones</th>
                <th style="min-width: 130px;">Estudiantes NEE</th>
            </tr>
            <?php foreach ($docentes as $docente): ?>
                <tr>
                    <td style="font-weight: 600;"><?php echo htmlspecialchars($docente['nombres_completos']); ?></td>
                    <td><?php echo htmlspecialchars(substr($docente['facultad'], 0, 30)) . (strlen($docente['facultad']) > 30 ? '...' : ''); ?></td>
                    <td><?php echo htmlspecialchars($docente['modalidad_enseñanza']); ?></td>
                    <td><?php echo htmlspecialchars($docente['años_experiencia_docente']); ?></td>
                    <td title="<?php echo htmlspecialchars($docente['titulo_tercer_nivel']); ?>">
                        <?php echo htmlspecialchars(substr($docente['titulo_tercer_nivel'], 0, 25)) . (strlen($docente['titulo_tercer_nivel']) > 25 ? '...' : ''); ?>
                    </td>
                    <td title="<?php echo htmlspecialchars($docente['titulo_cuarto_nivel'] ?: 'N/A'); ?>">
                        <?php 
                        $titulo4 = $docente['titulo_cuarto_nivel'] ?: 'N/A';
                        echo htmlspecialchars(substr($titulo4, 0, 25)) . (strlen($titulo4) > 25 ? '...' : ''); 
                        ?>
                    </td>
                    <td style="text-align: center;">
                        <?php if($docente['formacion_inclusion']): ?>
                            <span style="color: green; font-size: 18px;">✓</span>
                        <?php else: ?>
                            <span style="color: red; font-size: 18px;">✗</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center; font-weight: 600;"><?php echo $docente['experiencia_nee_años']; ?> años</td>
                    <td style="text-align: center; font-weight: 600;"><?php echo $docente['total_capacitaciones']; ?></td>
                    <td><?php echo htmlspecialchars($docente['estudiantes_nee_promedio']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <div style="background: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 10px; margin-top: 10px;">
        <p style="color: white; margin: 0; font-size: 14px;">
            <strong>💡 Tip:</strong> Desplázate horizontalmente en la tabla para ver toda la información. 
            Pasa el cursor sobre los títulos truncados para ver el texto completo.
        </p>
    </div>
    
    <h3>Experiencia por Tipo de Discapacidad</h3>
    <?php
        $query_exp = "SELECT d.nombres_completos, t.nombre_discapacidad, e.años_experiencia, e.nivel_competencia
                      FROM experiencia_docente_discapacidad e
                      JOIN docentes d ON e.id_docente = d.id_docente
                      JOIN tipos_discapacidad t ON e.id_tipo_discapacidad = t.id_tipo_discapacidad
                      WHERE e.tiene_experiencia = TRUE
                      ORDER BY d.nombres_completos, t.nombre_discapacidad";
        $stmt_exp = $conn->prepare($query_exp);
        $stmt_exp->execute();
        $experiencias = $stmt_exp->fetchAll(PDO::FETCH_ASSOC);
        
        $docente_actual = '';
    ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 20px;">
        <?php foreach ($experiencias as $exp): ?>
            <?php if ($docente_actual != $exp['nombres_completos']): ?>
                <?php if ($docente_actual != ''): ?>
                    </div></div>
                <?php endif; ?>
                <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 10px;">
                    <h4 style="color: #667eea; margin-bottom: 10px;"><?php echo htmlspecialchars($exp['nombres_completos']); ?></h4>
                    <div>
                <?php $docente_actual = $exp['nombres_completos']; ?>
            <?php endif; ?>
            <div style="padding: 5px 0;">
                <strong><?php echo htmlspecialchars($exp['nombre_discapacidad']); ?>:</strong> 
                <?php echo $exp['años_experiencia']; ?> años - 
                <span style="color: <?php 
                    echo $exp['nivel_competencia'] == 'Experto' ? '#28a745' : 
                        ($exp['nivel_competencia'] == 'Avanzado' ? '#17a2b8' : 
                        ($exp['nivel_competencia'] == 'Intermedio' ? '#ffc107' : '#dc3545')); 
                ?>">
                    <?php echo $exp['nivel_competencia']; ?>
                </span>
            </div>
        <?php endforeach; ?>
        <?php if ($docente_actual != ''): ?>
            </div></div>
        <?php endif; ?>
    </div>
    <?php } ?>
</div>
<?php include '../includes/footer.php'; ?>