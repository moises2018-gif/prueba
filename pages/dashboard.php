<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>
<div id="dashboard" class="tab-content" style="display: block;">
    <h2>Dashboard del Sistema AHP - Metodología Corregida con Triggers</h2>
    
    <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
        <h3 style="color: #28a745; margin-bottom: 15px;">🎉 Sistema AHP Optimizado con Triggers Automáticos</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
            <div style="background: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 8px;">
                <h4 style="color: #ffd700;">📊 Criterios Principales (Tipos de Discapacidad)</h4>
                <ul style="color: white; margin: 10px 0; font-size: 14px;">
                    <li><strong>Psicosocial:</strong> 40% (Máxima prioridad)</li>
                    <li><strong>Intelectual:</strong> 30% (Segunda prioridad)</li>
                    <li><strong>Visual:</strong> 15% (Tercera prioridad)</li>
                    <li><strong>Auditiva:</strong> 10% (Cuarta prioridad)</li>
                    <li><strong>Física:</strong> 5% (Quinta prioridad)</li>
                </ul>
            </div>
            <div style="background: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 8px;">
                <h4 style="color: #ffd700;">🎯 Subcriterios Globales</h4>
                <ul style="color: white; margin: 10px 0; font-size: 14px;">
                    <li><strong>EPR:</strong> 32% (Experiencia Práctica - MÁS IMPORTANTE)</li>
                    <li><strong>FSI:</strong> 28% (Formación Específica)</li>
                    <li><strong>AMI:</strong> 16% (Adaptaciones Metodológicas)</li>
                    <li><strong>AED:</strong> 13% (Experiencia General)</li>
                    <li><strong>NFA:</strong> 11% (Formación Académica)</li>
                </ul>
            </div>
        </div>
        <div style="background: rgba(40, 167, 69, 0.2); padding: 15px; border-radius: 8px; margin-top: 15px;">
            <h4 style="color: #28a745; margin-bottom: 10px;">🔧 Triggers Automáticos Activos</h4>
            <p style="color: white; margin: 0; font-size: 14px;">
                ✅ <strong>Solo necesitas hacer INSERT en tabla docentes</strong> - Los triggers crean automáticamente todos los registros relacionados<br>
                ✅ <strong>Valores inteligentes por defecto</strong> - Docentes con formación obtienen mejores puntuaciones automáticamente<br>
                ✅ <strong>Sistema AHP siempre funcional</strong> - Imposible tener datos incompletos
            </p>
        </div>
    </div>

    <?php
    include '../includes/conexion.php';
    $conn = ConexionBD();
    if ($conn) {
        // Verificar que los triggers estén funcionando
        $query_triggers = "SHOW TRIGGERS LIKE 'docentes'";
        $stmt_triggers = $conn->prepare($query_triggers);
        $stmt_triggers->execute();
        $triggers = $stmt_triggers->fetchAll(PDO::FETCH_ASSOC);
        $triggers_activos = count($triggers) > 0;

        // Obtener criterios actualizados
        $query_criterios = "SELECT * FROM criterios_ahp ORDER BY peso_criterio DESC";
        $stmt_criterios = $conn->prepare($query_criterios);
        $stmt_criterios->execute();
        $criterios = $stmt_criterios->fetchAll(PDO::FETCH_ASSOC);

        // Obtener tipos de discapacidad actualizados
        $query_tipos = "SELECT * FROM tipos_discapacidad ORDER BY peso_prioridad DESC";
        $stmt_tipos = $conn->prepare($query_tipos);
        $stmt_tipos->execute();
        $tipos_discapacidad = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

        // Obtener ranking actualizado
        $query_ranking = "SELECT * FROM vista_ranking_ahp ORDER BY ranking LIMIT 10";
        $stmt_ranking = $conn->prepare($query_ranking);
        $stmt_ranking->execute();
        $ranking = $stmt_ranking->fetchAll(PDO::FETCH_ASSOC);

        // Estadísticas generales
        $query_asignaciones = "SELECT COUNT(*) as total FROM asignaciones WHERE estado = 'Activa'";
        $total_asignaciones = $conn->query($query_asignaciones)->fetchColumn();

        $total_docentes = $conn->query("SELECT COUNT(*) FROM docentes")->fetchColumn();
        $docentes_con_formacion = $conn->query("SELECT COUNT(*) FROM docentes WHERE formacion_inclusion = TRUE")->fetchColumn();
        $porcentaje_formacion = ($total_docentes > 0) ? ($docentes_con_formacion / $total_docentes * 100) : 0;

        // Verificar que los triggers han creado registros
        $query_triggers_stats = "
            SELECT 
                COUNT(d.id_docente) as total_docentes,
                COUNT(am.id_adaptacion) as adaptaciones_creadas,
                COUNT(edd.id_experiencia) as experiencias_creadas
            FROM docentes d
            LEFT JOIN adaptaciones_metodologicas am ON d.id_docente = am.id_docente
            LEFT JOIN experiencia_docente_discapacidad edd ON d.id_docente = edd.id_docente";
        $stmt_triggers_stats = $conn->prepare($query_triggers_stats);
        $stmt_triggers_stats->execute();
        $triggers_stats = $stmt_triggers_stats->fetch(PDO::FETCH_ASSOC);

        // Obtener distribución por tipo de discapacidad
        $query_distribucion = "
            SELECT td.nombre_discapacidad, td.peso_prioridad,
                   COUNT(e.id_estudiante) as total_estudiantes,
                   COUNT(a.id_asignacion) as total_asignaciones,
                   COALESCE(ranking_info.mejor_docente, 'No disponible') as mejor_docente
            FROM tipos_discapacidad td
            LEFT JOIN estudiantes e ON td.id_tipo_discapacidad = e.id_tipo_discapacidad
            LEFT JOIN asignaciones a ON e.id_estudiante = a.id_estudiante AND a.estado = 'Activa'
            LEFT JOIN (
                SELECT vr.id_tipo_discapacidad, vr.nombres_completos as mejor_docente
                FROM vista_ranking_ahp_especifico vr 
                WHERE vr.ranking_por_discapacidad = 1
            ) ranking_info ON td.id_tipo_discapacidad = ranking_info.id_tipo_discapacidad
            GROUP BY td.id_tipo_discapacidad
            ORDER BY td.peso_prioridad DESC";
        $stmt_distribucion = $conn->prepare($query_distribucion);
        $stmt_distribucion->execute();
        $distribucion = $stmt_distribucion->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <!-- Tarjetas de estadísticas principales -->
    <div class="ahp-results">
        <div class="ahp-card">
            <h3>Total Docentes Evaluados</h3>
            <p style="font-size: 2em; margin: 10px 0;"><?php echo $total_docentes; ?></p>
            <p>Con metodología AHP corregida</p>
            <?php if ($triggers_activos): ?>
                <small style="color: #90EE90;">✅ Triggers activos</small>
            <?php else: ?>
                <small style="color: #FFB6C1;">⚠️ Verificar triggers</small>
            <?php endif; ?>
        </div>
        
        <div class="ahp-card">
            <h3>Formación en Inclusión</h3>
            <p style="font-size: 1.5em; margin: 10px 0;"><?php echo number_format($porcentaje_formacion, 1); ?>%</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $porcentaje_formacion; ?>%;"></div>
            </div>
            <p><?php echo $docentes_con_formacion; ?> de <?php echo $total_docentes; ?> docentes</p>
        </div>
        
        <div class="ahp-card">
            <h3>Asignaciones Activas</h3>
            <p style="font-size: 2em; margin: 10px 0;"><?php echo $total_asignaciones; ?></p>
            <p>Usando pesos AHP optimizados</p>
        </div>

        <div class="ahp-card">
            <h3>Registros Automáticos</h3>
            <p style="font-size: 1.2em; margin: 5px 0;">Adaptaciones: <?php echo $triggers_stats['adaptaciones_creadas']; ?></p>
            <p style="font-size: 1.2em; margin: 5px 0;">Experiencias: <?php echo $triggers_stats['experiencias_creadas']; ?></p>
            <small>Creados automáticamente por triggers</small>
        </div>
    </div>

    <h3>Distribución por Tipo de Discapacidad (Criterios Principales AHP)</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin: 20px 0;">
        <?php foreach ($distribucion as $dist): ?>
            <div class="ahp-card" style="background: linear-gradient(135deg, 
                <?php 
                // Colores según prioridad
                echo $dist['peso_prioridad'] >= 0.3 ? '#e74c3c, #c0392b' :  // Rojo para alta prioridad
                    ($dist['peso_prioridad'] >= 0.15 ? '#f39c12, #e67e22' :  // Naranja para media prioridad
                    '#95a5a6, #7f8c8d'); // Gris para baja prioridad
                ?> 100%);">
                <h4><?php echo htmlspecialchars($dist['nombre_discapacidad']); ?></h4>
                <div style="display: flex; justify-content: space-between; align-items: center; margin: 10px 0;">
                    <span style="font-size: 1.8em; font-weight: bold;"><?php echo number_format($dist['peso_prioridad'] * 100, 1); ?>%</span>
                    <span style="color: #ffd700; font-size: 0.9em;">
                        <?php 
                        echo $dist['peso_prioridad'] >= 0.3 ? 'ALTA PRIORIDAD' : 
                            ($dist['peso_prioridad'] >= 0.15 ? 'MEDIA PRIORIDAD' : 'BAJA PRIORIDAD');
                        ?>
                    </span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $dist['peso_prioridad'] * 100; ?>%;"></div>
                </div>
                <div style="margin-top: 10px; font-size: 0.9em;">
                    <p><strong>Estudiantes:</strong> <?php echo $dist['total_estudiantes'] ?: 0; ?></p>
                    <p><strong>Asignaciones:</strong> <?php echo $dist['total_asignaciones'] ?: 0; ?></p>
                    <p><strong>Mejor Docente:</strong> <?php echo htmlspecialchars(substr($dist['mejor_docente'], 0, 25)) . (strlen($dist['mejor_docente']) > 25 ? '...' : ''); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <h3>Top 10 Docentes - Ranking AHP Corregido</h3>
    <div style="overflow-x: auto; margin: 20px 0; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <table class="table" style="min-width: 1000px;">
            <tr>
                <th>Ranking</th>
                <th>Docente</th>
                <th>EPR (32%)</th>
                <th>FSI (28%)</th>
                <th>AMI (16%)</th>
                <th>AED (13%)</th>
                <th>NFA (11%)</th>
                <th>Puntuación Final</th>
                <th>Estado Triggers</th>
            </tr>
            <?php foreach ($ranking as $index => $docente): ?>
                <tr style="<?php echo $index < 3 ? 'background: rgba(255, 215, 0, 0.1);' : ''; ?>">
                    <td style="font-weight: bold; text-align: center;">
                        <?php 
                        echo $docente['ranking'];
                        if ($docente['ranking'] == 1) echo " 🥇";
                        elseif ($docente['ranking'] == 2) echo " 🥈";
                        elseif ($docente['ranking'] == 3) echo " 🥉";
                        ?>
                    </td>
                    <td style="font-weight: 600;"><?php echo htmlspecialchars($docente['nombres_completos']); ?></td>
                    <td style="text-align: center; color: #e74c3c; font-weight: bold;"><?php echo number_format($docente['puntuacion_epr'], 2); ?></td>
                    <td style="text-align: center; color: #3498db; font-weight: bold;"><?php echo number_format($docente['puntuacion_fsi'], 2); ?></td>
                    <td style="text-align: center;"><?php echo number_format($docente['puntuacion_ami'], 2); ?></td>
                    <td style="text-align: center;"><?php echo number_format($docente['puntuacion_aed'], 2); ?></td>
                    <td style="text-align: center;"><?php echo number_format($docente['puntuacion_nfa'], 2); ?></td>
                    <td style="text-align: center; font-weight: bold; color: #28a745; font-size: 1.1em;">
                        <?php echo number_format($docente['puntuacion_final'], 3); ?>
                    </td>
                    <td style="text-align: center;">
                        <?php
                        // Verificar si tiene registros creados por triggers
                        $query_check = "SELECT 
                            (SELECT COUNT(*) FROM adaptaciones_metodologicas WHERE id_docente = :id) as adaptaciones,
                            (SELECT COUNT(*) FROM experiencia_docente_discapacidad WHERE id_docente = :id) as experiencias";
                        $stmt_check = $conn->prepare($query_check);
                        $stmt_check->execute([':id' => $docente['id_docente']]);
                        $check = $stmt_check->fetch(PDO::FETCH_ASSOC);
                        
                        if ($check['adaptaciones'] > 0 && $check['experiencias'] >= 5) {
                            echo '<span style="color: #28a745;">✅ Completo</span>';
                        } else {
                            echo '<span style="color: #e74c3c;">⚠️ Incompleto</span>';
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div style="display: flex; gap: 20px; margin: 30px 0;">
        <div style="flex: 1;">
            <h3>Pesos de Subcriterios (Orden Corregido)</h3>
            <canvas id="subcriteriosChart"></canvas>
        </div>
        <div style="flex: 1;">
            <h3>Pesos de Criterios Principales</h3>
            <canvas id="criteriosPrincipalesChart"></canvas>
        </div>
    </div>

    <script>
        // Gráfico de Subcriterios
        const ctxSub = document.getElementById('subcriteriosChart').getContext('2d');
        new Chart(ctxSub, {
            type: 'doughnut',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($criterios, 'codigo_criterio')) . "'"; ?>],
                datasets: [{
                    label: 'Pesos de Subcriterios',
                    data: [<?php echo implode(',', array_column($criterios, 'peso_criterio')); ?>],
                    backgroundColor: ['#e74c3c', '#3498db', '#f39c12', '#27ae60', '#9b59b6'],
                    borderColor: '#FFFFFF',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: { 
                        display: true, 
                        text: 'EPR (32%) > FSI (28%) > AMI (16%) > AED (13%) > NFA (11%)' 
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Gráfico de Criterios Principales
        const ctxPrin = document.getElementById('criteriosPrincipalesChart').getContext('2d');
        new Chart(ctxPrin, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($tipos_discapacidad, 'nombre_discapacidad')) . "'"; ?>],
                datasets: [{
                    label: 'Peso de Prioridad',
                    data: [<?php echo implode(',', array_column($tipos_discapacidad, 'peso_prioridad')); ?>],
                    backgroundColor: ['#e74c3c', '#3498db', '#f39c12', '#27ae60', '#95a5a6'],
                    borderColor: '#FFFFFF',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        max: 0.5,
                        title: { display: true, text: 'Peso AHP' }
                    },
                    x: { 
                        title: { display: true, text: 'Tipos de Discapacidad' }
                    }
                },
                plugins: { 
                    title: { 
                        display: true, 
                        text: 'Psicosocial (40%) > Intelectual (30%) > Visual (15%) > Auditiva (10%) > Física (5%)' 
                    }
                }
            }
        });
    </script>

    <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 10px; margin-top: 30px;">
        <h3 style="color: #ffd700;">📊 Verificación del Sistema AHP</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 15px;">
            <div>
                <h4 style="color: #28a745;">✅ Estado del Sistema:</h4>
                <ul style="color: white; line-height: 1.6;">
                    <li>Pesos de criterios principales: <?php 
                        $suma_principales = array_sum(array_column($tipos_discapacidad, 'peso_prioridad'));
                        echo abs($suma_principales - 1.0) < 0.001 ? '✅ ' . number_format($suma_principales, 3) : '❌ ' . number_format($suma_principales, 3);
                    ?></li>
                    <li>Pesos de subcriterios: <?php 
                        $suma_sub = array_sum(array_column($criterios, 'peso_criterio'));
                        echo abs($suma_sub - 1.0) < 0.001 ? '✅ ' . number_format($suma_sub, 3) : '❌ ' . number_format($suma_sub, 3);
                    ?></li>
                    <li>Triggers automáticos: <?php echo $triggers_activos ? '✅ Funcionando' : '❌ No detectados'; ?></li>
                    <li>Registros automáticos: <?php echo $triggers_stats['adaptaciones_creadas'] > 0 ? '✅ Creándose correctamente' : '⚠️ Verificar funcionamiento'; ?></li>
                </ul>
            </div>
            <div>
                <h4 style="color: #17a2b8;">🔧 Funcionalidades Activas:</h4>
                <ul style="color: white; line-height: 1.6;">
                    <li>✅ Inserción simplificada de docentes</li>
                    <li>✅ Cálculo AHP específico por discapacidad</li>
                    <li>✅ Bonificaciones por experiencia específica</li>
                    <li>✅ Procedimientos auxiliares disponibles</li>
                </ul>
            </div>
        </div>
        
        <?php if (!$triggers_activos): ?>
        <div style="background: rgba(231, 76, 60, 0.2); padding: 15px; border-radius: 8px; margin-top: 15px;">
            <h4 style="color: #e74c3c;">⚠️ Advertencia: Triggers No Detectados</h4>
            <p style="color: white; margin: 0;">
                Los triggers automáticos no están funcionando. Para activarlos, ejecuta el script de base de datos completo nuevamente.
                Sin triggers, necesitarás crear manualmente los registros en las tablas relacionadas.
            </p>
        </div>
        <?php endif; ?>
    </div>

    <?php } else { ?>
        <div class="alert alert-error">No se pudo conectar a la base de datos.</div>
    <?php } ?>
</div>
<?php include '../includes/footer.php'; ?>