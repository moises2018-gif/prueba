<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>

<style>
/* ============================================
   ESTILOS PARA SCROLL Y NAVEGACIÓN MEJORADA
   ============================================ */

/* Contenedor principal con scroll */
.dashboard-container {
    max-height: 85vh;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 15px;
    margin-right: -15px;
}

/* Scrollbar personalizado - WebKit (Chrome, Safari, Edge) */
.dashboard-container::-webkit-scrollbar {
    width: 14px;
}

.dashboard-container::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    margin: 10px 0;
}

.dashboard-container::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

.dashboard-container::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
    transform: scale(1.1);
}

.dashboard-container::-webkit-scrollbar-thumb:active {
    background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
}

/* Scrollbar para Firefox */
.dashboard-container {
    scrollbar-width: thin;
    scrollbar-color: #667eea rgba(255, 255, 255, 0.1);
}

/* Indicador de scroll */
.scroll-indicator {
    position: fixed;
    top: 50%;
    right: 20px;
    transform: translateY(-50%);
    background: rgba(102, 126, 234, 0.8);
    color: white;
    padding: 10px;
    border-radius: 20px;
    font-size: 12px;
    z-index: 1000;
    transition: opacity 0.3s ease;
    backdrop-filter: blur(10px);
}

/* Botón para ir arriba */
.scroll-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    font-size: 20px;
    cursor: pointer;
    opacity: 0;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    z-index: 1000;
}

.scroll-to-top:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.scroll-to-top.visible {
    opacity: 1;
}

/* Optimización de tablas con scroll */
.tabla-scroll {
    max-height: 400px;
    overflow-y: auto;
    overflow-x: auto;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin: 20px 0;
    position: relative;
}

.tabla-scroll::-webkit-scrollbar {
    height: 8px;
    width: 8px;
}

.tabla-scroll::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
}

.tabla-scroll::-webkit-scrollbar-thumb {
    background: rgba(102, 126, 234, 0.6);
    border-radius: 4px;
}

.tabla-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(102, 126, 234, 0.8);
}

/* Headers fijos en tablas */
.table-fixed-header {
    font-size: 14px;
}

.table-fixed-header th {
    position: sticky;
    top: 0;
    z-index: 10;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 12px 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.table-fixed-header td {
    padding: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

/* Navegación interna */
.dashboard-nav {
    position: sticky;
    top: 0;
    background: rgba(255, 255, 255, 0.1);
    padding: 10px;
    border-radius: 10px;
    margin-bottom: 20px;
    backdrop-filter: blur(10px);
    z-index: 100;
}

.dashboard-nav-links {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
}

.dashboard-nav-link {
    color: #2c3e50;
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.8);
    transition: all 0.3s ease;
    font-size: 14px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    font-weight: 600;
}

.dashboard-nav-link:hover {
    background: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
    color: #2c3e50;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

/* Secciones con anclas */
.dashboard-section {
    margin-bottom: 40px;
    scroll-margin-top: 100px;
}

/* Info boxes especiales */
.info-box {
    background: rgba(255, 255, 255, 0.1);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 30px;
}

.info-box h3 {
    color: #28a745 !important;
    margin-bottom: 15px;
}

.info-box h4 {
    color: #ffd700 !important;
    margin-bottom: 10px;
}

.info-box ul li {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.7);
    margin: 5px 0;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 14px;
}

.info-box p {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.7);
    padding: 10px;
    border-radius: 5px;
    margin: 0;
    font-size: 14px;
}

.info-box strong {
    color: #155724 !important;
}

/* Grid para criterios */
.criterios-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
}

.criterio-box {
    background: rgba(255, 255, 255, 0.1);
    padding: 15px;
    border-radius: 8px;
}

/* Grid para distribución */
.distribucion-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

/* Advertencia especial */
.warning-box {
    background: rgba(231, 76, 60, 0.2);
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}

.warning-box h4 {
    color: #e74c3c !important;
}

.warning-box p {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.8);
    padding: 10px;
    border-radius: 5px;
    margin: 0;
}

/* Success box */
.success-box {
    background: rgba(40, 167, 69, 0.2);
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}

.success-box h4 {
    color: #28a745 !important;
    margin-bottom: 10px;
}

.success-box p {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.8);
    padding: 10px;
    border-radius: 5px;
    margin: 0;
    font-size: 14px;
}

/* Tarjetas de distribución especiales */
.distribucion-card {
    color: #ffffff !important;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
    text-align: center;
    transition: transform 0.3s ease;
}

.distribucion-card h4 {
    color: #ffffff !important;
    margin-bottom: 15px;
}

.distribucion-card p {
    color: #ffffff !important;
    margin: 5px 0;
}

.distribucion-card strong {
    color: #ffffff !important;
}

.prioridad-label {
    color: #ffd700 !important;
    font-size: 0.9em;
}

/* Estados de verificación */
.estado-ok {
    color: #28a745 !important;
}

.estado-error {
    color: #dc3545 !important;
}

.estado-warning {
    color: #ffc107 !important;
}

/* Grid para estadísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.info-item {
    background: rgba(255, 255, 255, 0.8);
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.info-item strong {
    color: #155724 !important;
}

/* Responsive mejoras */
@media (max-width: 768px) {
    .dashboard-container {
        max-height: 90vh;
        padding-right: 10px;
        margin-right: -10px;
    }
    
    .scroll-to-top {
        bottom: 20px;
        right: 20px;
        width: 45px;
        height: 45px;
        font-size: 18px;
    }
    
    .dashboard-nav-links {
        justify-content: center;
    }
    
    .dashboard-nav-link {
        font-size: 12px;
        padding: 6px 12px;
    }
    
    .criterios-grid {
        grid-template-columns: 1fr;
    }
    
    .distribucion-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

/* Animaciones suaves */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Estilos adicionales para los gráficos */
#subcriteriosChart, #criteriosPrincipalesChart {
    height: 300px !important;
    max-height: 300px;
}

/* Mejoras de gráficos */
.chart-container {
    background: rgba(255, 255, 255, 0.9);
    padding: 20px;
    border-radius: 10px;
    margin: 15px 0;
}

.chart-container h4 {
    color: #2c3e50 !important;
    text-align: center;
    margin-bottom: 15px;
}

.charts-grid {
    display: flex;
    gap: 20px;
    margin: 30px 0;
    flex-wrap: wrap;
}

.chart-item {
    flex: 1;
    min-width: 300px;
}

/* Lista de verificación */
.verification-list {
    color: #2c3e50 !important;
    background: rgba(255, 255, 255, 0.8);
    padding: 15px;
    border-radius: 8px;
    line-height: 1.6;
}

.verification-list li {
    margin: 8px 0;
}

.verification-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 15px;
}
</style>

<div class="dashboard-container" id="dashboardContainer">
    <!-- Navegación interna del dashboard -->
    <div class="dashboard-nav">
        <div class="dashboard-nav-links">
            <a href="#inicio" class="dashboard-nav-link">🏠 Inicio</a>
            <a href="#estadisticas" class="dashboard-nav-link">📊 Estadísticas</a>
            <a href="#distribucion" class="dashboard-nav-link">📈 Distribución</a>
            <a href="#materias-estudiantes" class="dashboard-nav-link">📚 Materias</a>
            <a href="#analisis-materias" class="dashboard-nav-link">🔍 Análisis</a>
            <a href="#graficos" class="dashboard-nav-link">📉 Gráficos</a>
        </div>
    </div>

    <div id="dashboard" class="tab-content" style="display: block;">
        <!-- SECCIÓN INICIO -->
        <section id="inicio" class="dashboard-section fade-in">
            <h2>Dashboard</h2>
            
            <div class="info-box">
                <h3>🎉 Sistema AHP Optimizado con Triggers Automáticos</h3>
                <div class="criterios-grid">
                    <div class="criterio-box">
                        <h4>📊 Criterios Principales (Tipos de Discapacidad)</h4>
                        <ul>
                            <li><strong>Psicosocial:</strong> 40% (Máxima prioridad)</li>
                            <li><strong>Intelectual:</strong> 30% (Segunda prioridad)</li>
                            <li><strong>Visual:</strong> 15% (Tercera prioridad)</li>
                            <li><strong>Auditiva:</strong> 10% (Cuarta prioridad)</li>
                            <li><strong>Física:</strong> 5% (Quinta prioridad)</li>
                        </ul>
                    </div>
                    <div class="criterio-box">
                        <h4>🎯 Subcriterios Globales</h4>
                        <ul>
                            <li><strong>EPR:</strong> 32% (Experiencia Práctica - MÁS IMPORTANTE)</li>
                            <li><strong>FSI:</strong> 28% (Formación Específica)</li>
                            <li><strong>AMI:</strong> 16% (Adaptaciones Metodológicas)</li>
                            <li><strong>AED:</strong> 13% (Experiencia General)</li>
                            <li><strong>NFA:</strong> 11% (Formación Académica)</li>
                        </ul>
                    </div>
                </div>
                <div class="success-box">
                    <h4>🔧 Triggers Automáticos Activos</h4>
                    <p>
                        <strong>Solo necesitas hacer INSERT en tabla docentes</strong> - Los triggers crean automáticamente todos los registros relacionados<br>
                        <strong>Valores inteligentes por defecto</strong> - Docentes con formación obtienen mejores puntuaciones automáticamente<br>
                        <strong>Sistema AHP siempre funcional</strong> - Imposible tener datos incompletos
                    </p>
                </div>
            </div>
        </section>

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

        <!-- SECCIÓN ESTADÍSTICAS -->
        <section id="estadisticas" class="dashboard-section fade-in">
            <h3>📊 Estadísticas Principales</h3>
            <div class="ahp-results">
                <div class="ahp-card">
                    <h3>Total Docentes Evaluados</h3>
                    <p style="font-size: 2em; margin: 10px 0;"><?php echo $total_docentes; ?></p>
                    <?php if ($triggers_activos): ?>
                        <small class="estado-ok">✅ Triggers activos</small>
                    <?php else: ?>
                        <small class="estado-error">❌ Triggers inactivos</small>
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
        </section>

        <!-- SECCIÓN DISTRIBUCIÓN -->
        <section id="distribucion" class="dashboard-section fade-in">
            <h3>📈 Distribución por Tipo de Discapacidad (Criterios Principales AHP)</h3>
            <div class="distribucion-grid">
                <?php foreach ($distribucion as $dist): ?>
                    <div class="distribucion-card" style="background: linear-gradient(135deg, 
                        <?php 
                        echo $dist['peso_prioridad'] >= 0.3 ? '#e74c3c, #c0392b' :  
                            ($dist['peso_prioridad'] >= 0.15 ? '#f39c12, #e67e22' :  
                            '#95a5a6, #7f8c8d'); 
                        ?> 100%);">
                        <h4><?php echo htmlspecialchars($dist['nombre_discapacidad']); ?></h4>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin: 10px 0;">
                            <span style="font-size: 1.8em; font-weight: bold;"><?php echo number_format($dist['peso_prioridad'] * 100, 1); ?>%</span>
                            <span class="prioridad-label">
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
        </section>

        <!-- SECCIÓN MATERIAS Y ESTUDIANTES -->
        <section id="materias-estudiantes" class="dashboard-section fade-in">
            <h3>📚 Materias con Estudiantes Asignados</h3>
            
            <?php
            try {
                // Obtener materias con estudiantes asignados
                $query_materias_estudiantes = "
                    SELECT 
                        m.id_materia,
                        m.nombre_materia,
                        m.facultad,
                        m.ciclo_academico,
                        COUNT(DISTINCT a.id_estudiante) as total_estudiantes_asignados,
                        COUNT(DISTINCT a.id_docente) as total_docentes_asignando,
                        GROUP_CONCAT(
                            DISTINCT CONCAT(
                                e.nombres_completos, 
                                ' (', td.nombre_discapacidad, ') - ', 
                                d.nombres_completos
                            ) 
                            ORDER BY td.peso_prioridad DESC, e.nombres_completos 
                            SEPARATOR '|'
                        ) as estudiantes_detalle,
                        AVG(a.puntuacion_ahp) as puntuacion_promedio,
                        GROUP_CONCAT(DISTINCT td.nombre_discapacidad ORDER BY td.peso_prioridad DESC) as tipos_discapacidad
                    FROM materias m
                    LEFT JOIN asignaciones a ON m.id_materia = a.id_materia AND a.estado = 'Activa'
                    LEFT JOIN estudiantes e ON a.id_estudiante = e.id_estudiante
                    LEFT JOIN docentes d ON a.id_docente = d.id_docente
                    LEFT JOIN tipos_discapacidad td ON a.id_tipo_discapacidad = td.id_tipo_discapacidad
                    GROUP BY m.id_materia, m.nombre_materia, m.facultad, m.ciclo_academico
                    ORDER BY m.ciclo_academico DESC, total_estudiantes_asignados DESC, m.nombre_materia";
                
                $stmt_materias = $conn->prepare($query_materias_estudiantes);
                $stmt_materias->execute();
                $materias_estudiantes = $stmt_materias->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($materias_estudiantes)) {
                    // Estadísticas generales de materias
                    $total_materias = count($materias_estudiantes);
                    $materias_con_estudiantes = count(array_filter($materias_estudiantes, function($m) {
                        return $m['total_estudiantes_asignados'] > 0;
                    }));
                    $total_asignaciones_materias = array_sum(array_column($materias_estudiantes, 'total_estudiantes_asignados'));
                    
                    echo '<div class="info-box">
                        <h4>📊 Resumen de Materias</h4>
                        <div class="stats-grid">
                            <div class="info-item">
                                <strong>Total de materias:</strong> ' . $total_materias . '
                            </div>
                            <div class="info-item">
                                <strong>Materias con estudiantes NEE:</strong> ' . $materias_con_estudiantes . '
                            </div>
                            <div class="info-item">
                                <strong>Total asignaciones a materias:</strong> ' . $total_asignaciones_materias . '
                            </div>
                            <div class="info-item">
                                <strong>Promedio estudiantes por materia:</strong> ' . 
                                ($materias_con_estudiantes > 0 ? round($total_asignaciones_materias / $materias_con_estudiantes, 1) : 0) . '
                            </div>
                        </div>
                    </div>';
                    
                    // Tabla de materias con scroll optimizado
                    echo '<div class="tabla-scroll">
                        <table class="table table-fixed-header">
                            <thead>
                                <tr>
                                    <th style="width: 20%">Materia</th>
                                    <th style="width: 15%">Facultad</th>
                                    <th style="width: 8%">Ciclo</th>
                                    <th style="width: 8%">Est. NEE</th>
                                    <th style="width: 8%">Docentes</th>
                                    <th style="width: 15%">Tipos Discapacidad</th>
                                    <th style="width: 8%">Punt. Prom.</th>
                                   <th style="width: 18%">Estudiantes Asignados</th>
                               </tr>
                           </thead>
                           <tbody>';
                   
                   foreach ($materias_estudiantes as $materia) {
                       $estudiantes_asignados = $materia['total_estudiantes_asignados'];
                       $clase_fila = '';
                      
                      if ($estudiantes_asignados == 0) {
                          $clase_fila = 'style="opacity: 0.6; background: #f8f9fa;"';
                      } else if ($estudiantes_asignados >= 5) {
                          $clase_fila = 'style="background: rgba(40, 167, 69, 0.1);"'; // Verde claro
                      } else if ($estudiantes_asignados >= 3) {
                          $clase_fila = 'style="background: rgba(255, 193, 7, 0.1);"'; // Amarillo claro
                      }
                      
                      echo '<tr ' . $clase_fila . '>
                          <td>
                              <strong>' . htmlspecialchars($materia['nombre_materia']) . '</strong>
                          </td>
                          <td>' . htmlspecialchars(substr($materia['facultad'], 0, 30)) . 
                              (strlen($materia['facultad']) > 30 ? '...' : '') . '</td>
                          <td style="text-align: center; font-weight: bold;">
                              ' . htmlspecialchars($materia['ciclo_academico']) . '
                          </td>
                          <td style="text-align: center;">
                              <span style="font-weight: bold; color: ' . 
                              ($estudiantes_asignados > 0 ? '#28a745' : '#6c757d') . ';">
                                  ' . $estudiantes_asignados . '
                              </span>
                          </td>
                          <td style="text-align: center;">
                              ' . ($materia['total_docentes_asignando'] ?: 0) . '
                          </td>
                          <td style="font-size: 11px;">
                              ' . ($materia['tipos_discapacidad'] ? 
                                  htmlspecialchars(substr($materia['tipos_discapacidad'], 0, 40)) . 
                                  (strlen($materia['tipos_discapacidad']) > 40 ? '...' : '') : 
                                  '<em>Sin asignaciones</em>') . '
                          </td>
                          <td style="text-align: center; color: #28a745; font-weight: bold;">
                              ' . ($materia['puntuacion_promedio'] ? 
                                  number_format($materia['puntuacion_promedio'], 2) : '-') . '
                          </td>
                          <td style="font-size: 11px;">';
                      
                      if ($materia['estudiantes_detalle']) {
                          $estudiantes_array = explode('|', $materia['estudiantes_detalle']);
                          $max_mostrar = 3;
                          
                          for ($i = 0; $i < min(count($estudiantes_array), $max_mostrar); $i++) {
                              $partes = explode(' - ', $estudiantes_array[$i]);
                              $estudiante_info = $partes[0] ?? '';
                              $docente_nombre = $partes[1] ?? '';
                              
                              echo '<div style="margin: 2px 0; padding: 2px; background: rgba(102, 126, 234, 0.1); border-radius: 3px;">
                                  <strong style="color: #2c3e50;">' . htmlspecialchars($estudiante_info) . '</strong>';
                              if ($docente_nombre) {
                                  echo '<br><small style="color: #6c757d;">👨‍🏫 ' . 
                                       htmlspecialchars(substr($docente_nombre, 0, 25)) . 
                                       (strlen($docente_nombre) > 25 ? '...' : '') . '</small>';
                              }
                              echo '</div>';
                          }
                          
                          if (count($estudiantes_array) > $max_mostrar) {
                              echo '<small style="color: #6c757d; font-style: italic;">
                                  +' . (count($estudiantes_array) - $max_mostrar) . ' estudiante(s) más...
                              </small>';
                          }
                      } else {
                          echo '<em style="color: #6c757d;">Sin estudiantes asignados</em>';
                      }
                      
                      echo '</td></tr>';
                  }
           
                  echo '</tbody>
                      </table>
                  </div>';
                  
                  // Leyenda explicativa
                  echo '<div class="info-box" style="margin-top: 15px;">
                      <h4>💡 Leyenda</h4>
                      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                          <div style="background: rgba(40, 167, 69, 0.1); padding: 8px; border-radius: 5px;">
                              <strong>Verde:</strong> 5+ estudiantes NEE
                          </div>
                          <div style="background: rgba(255, 193, 7, 0.1); padding: 8px; border-radius: 5px;">
                              <strong>Amarillo:</strong> 3-4 estudiantes NEE
                          </div>
                          <div style="background: #f8f9fa; padding: 8px; border-radius: 5px;">
                              <strong>Gris:</strong> Sin estudiantes NEE
                          </div>
                          <div style="background: rgba(102, 126, 234, 0.1); padding: 8px; border-radius: 5px;">
                              <strong>Azul:</strong> Información de estudiantes
                          </div>
                      </div>
                  </div>';
                  
              } else {
                  echo '<div class="warning-box">
                      <h4>⚠️ Sin Datos de Materias</h4>
                      <p>No se encontraron materias registradas en el sistema.</p>
                  </div>';
              }
              
          } catch (Exception $e) {
              echo '<div class="warning-box">
                  <h4>❌ Error al cargar materias</h4>
                  <p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>
              </div>';
          }
          ?>
      </section>

      <!-- SECCIÓN ANÁLISIS DETALLADO POR MATERIA -->
      <section id="analisis-materias" class="dashboard-section fade-in">
          <h3>🔍 Análisis Detallado por Materia</h3>
          
          <?php
          try {
              // Top 5 materias con más estudiantes NEE
              $query_top_materias = "
                  SELECT 
                      m.nombre_materia,
                      m.facultad,
                      m.ciclo_academico,
                      COUNT(a.id_estudiante) as total_estudiantes,
                      AVG(a.puntuacion_ahp) as puntuacion_promedio,
                      COUNT(DISTINCT a.id_docente) as docentes_involucrados,
                      GROUP_CONCAT(DISTINCT td.nombre_discapacidad ORDER BY td.peso_prioridad DESC) as tipos_atencion
                  FROM materias m
                  JOIN asignaciones a ON m.id_materia = a.id_materia AND a.estado = 'Activa'
                  JOIN tipos_discapacidad td ON a.id_tipo_discapacidad = td.id_tipo_discapacidad
                  GROUP BY m.id_materia
                  HAVING total_estudiantes > 0
                  ORDER BY total_estudiantes DESC, puntuacion_promedio DESC
                  LIMIT 5";
              
              $stmt_top = $conn->prepare($query_top_materias);
              $stmt_top->execute();
              $top_materias = $stmt_top->fetchAll(PDO::FETCH_ASSOC);
              
              if (!empty($top_materias)) {
                  echo '<div class="distribucion-grid">';
                  
                  foreach ($top_materias as $index => $materia) {
                      $colores = ['#e74c3c', '#3498db', '#f39c12', '#27ae60', '#9b59b6'];
                      $color = $colores[$index % count($colores)];
                      
                      echo '<div class="distribucion-card" style="background: linear-gradient(135deg, ' . $color . ', ' . $color . '99);">
                          <h4>🏆 #' . ($index + 1) . ' ' . htmlspecialchars($materia['nombre_materia']) . '</h4>
                          <div style="margin: 15px 0;">
                              <p><strong>📚 Facultad:</strong><br>' . htmlspecialchars(substr($materia['facultad'], 0, 35)) . '</p>
                              <p><strong>📅 Ciclo:</strong> ' . htmlspecialchars($materia['ciclo_academico']) . '</p>
                              <p><strong>👥 Estudiantes NEE:</strong> ' . $materia['total_estudiantes'] . '</p>
                              <p><strong>👨‍🏫 Docentes:</strong> ' . $materia['docentes_involucrados'] . '</p>
                              <p><strong>📊 Punt. Promedio:</strong> ' . number_format($materia['puntuacion_promedio'], 2) . '</p>
                              <p><strong>🎯 Tipos atendidos:</strong><br>' . 
                                 htmlspecialchars(substr($materia['tipos_atencion'], 0, 40)) . 
                                 (strlen($materia['tipos_atencion']) > 40 ? '...' : '') . '</p>
                          </div>
                      </div>';
                  }
                  
                  echo '</div>';
              } else {
                  echo '<div class="info-box">
                      <p>No hay materias con estudiantes NEE asignados actualmente.</p>
                  </div>';
              }
              
          } catch (Exception $e) {
              echo '<div class="warning-box">
                  <h4>❌ Error en análisis</h4>
                  <p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>
              </div>';
          }
          ?>
      </section>

      <!-- SECCIÓN GRÁFICOS -->
      <section id="graficos" class="dashboard-section fade-in">
          <h3>📉 Análisis Visual</h3>
          <div class="charts-grid">
              <div class="chart-item">
                  <div class="chart-container">
                      <h4>Pesos de Subcriterios (Orden Corregido)</h4>
                      <canvas id="subcriteriosChart"></canvas>
                  </div>
              </div>
              <div class="chart-item">
                  <div class="chart-container">
                      <h4>Pesos de Criterios Principales</h4>
                      <canvas id="criteriosPrincipalesChart"></canvas>
                  </div>
              </div>
          </div>
      </section>

      <?php 
      } else { ?>
          <div class="alert alert-error">No se pudo conectar a la base de datos.</div>
      <?php } ?>
  </div>
</div>

<!-- Indicador de scroll y botón para ir arriba -->
<div id="scrollIndicator" class="scroll-indicator" style="opacity: 0;">
  📍 Navegando...
</div>

<button id="scrollToTop" class="scroll-to-top" title="Ir arriba">
  ↑
</button>

<script>
// ============================================
// FUNCIONALIDAD DE SCROLL Y NAVEGACIÓN
// ============================================

document.addEventListener('DOMContentLoaded', function() {
  const container = document.getElementById('dashboardContainer');
  const scrollIndicator = document.getElementById('scrollIndicator');
  const scrollToTopBtn = document.getElementById('scrollToTop');
  
  // Mostrar/ocultar indicador de scroll y botón
  container.addEventListener('scroll', function() {
      const scrollPercent = (container.scrollTop / (container.scrollHeight - container.clientHeight)) * 100;
      
      // Mostrar indicador si hay scroll
      if (container.scrollTop > 100) {
          scrollIndicator.style.opacity = '1';
          scrollIndicator.textContent = `📍 ${Math.round(scrollPercent)}%`;
          scrollToTopBtn.classList.add('visible');
      } else {
          scrollIndicator.style.opacity = '0';
          scrollToTopBtn.classList.remove('visible');
      }
  });
  
  // Botón ir arriba
  scrollToTopBtn.addEventListener('click', function() {
      container.scrollTo({
          top: 0,
          behavior: 'smooth'
      });
  });
  
  // Navegación suave para los enlaces internos
  document.querySelectorAll('.dashboard-nav-link').forEach(link => {
      link.addEventListener('click', function(e) {
          e.preventDefault();
          const targetId = this.getAttribute('href').substring(1);
          const targetElement = document.getElementById(targetId);
          
          if (targetElement) {
              const containerRect = container.getBoundingClientRect();
              const targetRect = targetElement.getBoundingClientRect();
              const offsetTop = targetRect.top - containerRect.top + container.scrollTop - 100;
              
              container.scrollTo({
                  top: offsetTop,
                  behavior: 'smooth'
              });
              
              // Highlight del enlace activo
              document.querySelectorAll('.dashboard-nav-link').forEach(l => l.style.background = 'rgba(255, 255, 255, 0.8)');
              this.style.background = 'rgba(255, 255, 255, 0.9)';
          }
      });
  });
  
  // Animación de aparición para las secciones
  const observerOptions = {
      root: container,
      rootMargin: '0px',
      threshold: 0.1
  };
  
  const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
          if (entry.isIntersecting) {
              entry.target.style.opacity = '1';
              entry.target.style.transform = 'translateY(0)';
          }
      });
  }, observerOptions);
  
  // Observar todas las secciones
  document.querySelectorAll('.dashboard-section').forEach(section => {
      section.style.opacity = '0';
      section.style.transform = 'translateY(20px)';
      section.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
      observer.observe(section);
  });
});

// ============================================
// GRÁFICOS CON CHART.JS
// ============================================

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
          borderWidth: 2,
          hoverOffset: 4
      }]
  },
  options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
          title: { 
              display: true, 
              text: 'EPR (32%) > FSI (28%) > AMI (16%) > AED (13%) > NFA (11%)',
              color: '#2c3e50',
              font: { size: 14 }
          },
          legend: {
              position: 'bottom',
              labels: { color: '#2c3e50' }
          },
          tooltip: {
              callbacks: {
                  label: function(context) {
                      return context.label + ': ' + (context.parsed * 100).toFixed(1) + '%';
                  }
              }
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
          borderWidth: 1,
          borderRadius: 5,
          borderSkipped: false,
      }]
  },
  options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
          y: { 
              beginAtZero: true, 
              max: 0.5,
              title: { 
                  display: true, 
                  text: 'Peso AHP',
                  color: '#2c3e50'
              },
              ticks: { color: '#2c3e50' },
              grid: { color: 'rgba(44, 62, 80, 0.1)' }
          },
          x: { 
              title: { 
                  display: true, 
                  text: 'Tipos de Discapacidad',
                  color: '#2c3e50'
              },
              ticks: { 
                  color: '#2c3e50',
                  maxRotation: 45
              },
              grid: { color: 'rgba(44, 62, 80, 0.1)' }
          }
      },
      plugins: { 
          title: { 
              display: true, 
              text: 'Psicosocial (40%) > Intelectual (30%) > Visual (15%) > Auditiva (10%) > Física (5%)',
              color: '#2c3e50',
              font: { size: 14 }
          },
          legend: {
              display: false
          },
          tooltip: {
              callbacks: {
                  label: function(context) {
                      return 'Peso: ' + (context.parsed.y * 100).toFixed(1) + '%';
                  }
              }
          }
      },
      animation: {
          duration: 2000,
          easing: 'easeInOutQuart'
      }
  }
});

// ============================================
// FUNCIONES ADICIONALES
// ============================================

// Función para detectar si el usuario está navegando
let scrollTimeout;
container.addEventListener('scroll', function() {
  clearTimeout(scrollTimeout);
  container.style.scrollBehavior = 'auto';
  
  scrollTimeout = setTimeout(function() {
      container.style.scrollBehavior = 'smooth';
  }, 150);
});

// Mejorar rendimiento del scroll
let ticking = false;
function updateScrollIndicator() {
  if (!ticking) {
      requestAnimationFrame(function() {
          // Aquí van las actualizaciones del scroll
          ticking = false;
      });
      ticking = true;
  }
}

// Event listener optimizado
container.addEventListener('scroll', updateScrollIndicator, { passive: true });

// Función para imprimir o exportar el dashboard
function exportDashboard() {
  window.print();
}

// Función para alternar modo compacto
function toggleCompactMode() {
  document.querySelectorAll('.ahp-card').forEach(card => {
      card.classList.toggle('compact-mode');
  });
}

// Añadir clase CSS para modo compacto
const style = document.createElement('style');
style.textContent = `
  .ahp-card.compact-mode {
      padding: 15px !important;
      margin: 10px 0 !important;
  }
  .ahp-card.compact-mode h3 {
      font-size: 1.1em !important;
      margin-bottom: 8px !important;
  }
  .ahp-card.compact-mode p {
      font-size: 0.9em !important;
      margin: 5px 0 !important;
  }
`;
document.head.appendChild(style);
</script>

<?php include '../includes/footer.php'; ?>