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
    color: white;
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.1);
    transition: all 0.3s ease;
    font-size: 14px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.dashboard-nav-link:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
    color: white;
}

/* Secciones con anclas */
.dashboard-section {
    margin-bottom: 40px;
    scroll-margin-top: 100px;
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
}

/* Animaciones suaves */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="dashboard-container" id="dashboardContainer">
    <!-- Navegación interna del dashboard -->
    <div class="dashboard-nav">
        <div class="dashboard-nav-links">
            <a href="#inicio" class="dashboard-nav-link">🏠 Inicio</a>
            <a href="#estadisticas" class="dashboard-nav-link">📊 Estadísticas</a>
            <a href="#distribucion" class="dashboard-nav-link">📈 Distribución</a>
            <a href="#ranking" class="dashboard-nav-link">🏆 Ranking</a>
            <a href="#graficos" class="dashboard-nav-link">📉 Gráficos</a>
            <a href="#verificacion" class="dashboard-nav-link">✅ Verificación</a>
        </div>
    </div>

    <div id="dashboard" class="tab-content" style="display: block;">
        <!-- SECCIÓN INICIO -->
        <section id="inicio" class="dashboard-section fade-in">
            <h2>Dashboard</h2>
            
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
        </section>

        <!-- SECCIÓN DISTRIBUCIÓN -->
        <section id="distribucion" class="dashboard-section fade-in">
            <h3>📈 Distribución por Tipo de Discapacidad (Criterios Principales AHP)</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin: 20px 0;">
                <?php foreach ($distribucion as $dist): ?>
                    <div class="ahp-card" style="background: linear-gradient(135deg, 
                        <?php 
                        echo $dist['peso_prioridad'] >= 0.3 ? '#e74c3c, #c0392b' :  
                            ($dist['peso_prioridad'] >= 0.15 ? '#f39c12, #e67e22' :  
                            '#95a5a6, #7f8c8d'); 
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
        </section>

        
        <!-- SECCIÓN GRÁFICOS -->
        <section id="graficos" class="dashboard-section fade-in">
            <h3>📉 Análisis Visual</h3>
            <div style="display: flex; gap: 20px; margin: 30px 0; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <h4>Pesos de Subcriterios (Orden Corregido)</h4>
                    <canvas id="subcriteriosChart"></canvas>
                </div>
                <div style="flex: 1; min-width: 300px;">
                    <h4>Pesos de Criterios Principales</h4>
                    <canvas id="criteriosPrincipalesChart"></canvas>
                </div>
            </div>
        </section>

        <!-- SECCIÓN VERIFICACIÓN -->
        <section id="verificacion" class="dashboard-section fade-in">
            <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 10px; margin-top: 30px;">
                <h3 style="color: #ffd700;">✅ Verificación del Sistema AHP</h3>
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
        </section>

        <?php } else { ?>
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
                document.querySelectorAll('.dashboard-nav-link').forEach(l => l.style.background = 'rgba(255, 255, 255, 0.1)');
                this.style.background = 'rgba(255, 255, 255, 0.3)';
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
                color: 'white',
                font: { size: 14 }
            },
            legend: {
                position: 'bottom',
                labels: { color: 'white' }
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
                    color: 'white'
                },
                ticks: { color: 'white' },
                grid: { color: 'rgba(255, 255, 255, 0.1)' }
            },
            x: { 
                title: { 
                    display: true, 
                    text: 'Tipos de Discapacidad',
                    color: 'white'
                },
                ticks: { 
                    color: 'white',
                    maxRotation: 45
                },
                grid: { color: 'rgba(255, 255, 255, 0.1)' }
            }
        },
        plugins: { 
            title: { 
                display: true, 
                text: 'Psicosocial (40%) > Intelectual (30%) > Visual (15%) > Auditiva (10%) > Física (5%)',
                color: 'white',
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

<style>
/* Estilos adicionales para los gráficos */
#subcriteriosChart, #criteriosPrincipalesChart {
    height: 300px !important;
    max-height: 300px;
}

/* Mejorar la tabla en dispositivos móviles */
@media (max-width: 768px) {
    .tabla-scroll {
        max-height: 300px;
    }
    
    .table-fixed-header th,
    .table-fixed-header td {
        font-size: 12px;
        padding: 8px 6px;
    }
    
    .ahp-results {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    }
    
    .dashboard-nav {
        position: static;
    }
    
    .dashboard-nav-links {
        flex-direction: column;
        align-items: center;
    }
    
    .dashboard-nav-link {
        width: 100%;
        text-align: center;
        margin: 2px 0;
    }
}

/* Animaciones adicionales */
.ahp-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(31, 38, 135, 0.5);
    transition: all 0.3s ease;
}

.progress-bar {
    position: relative;
    overflow: hidden;
}

.progress-fill {
    transition: width 1.5s ease-in-out;
    position: relative;
}

.progress-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Indicador visual de sección activa */
.dashboard-section.active {
    border-left: 4px solid #667eea;
    padding-left: 16px;
    transition: all 0.3s ease;
}

/* Mejoras para accesibilidad */
.dashboard-nav-link:focus,
.scroll-to-top:focus {
    outline: 2px solid #667eea;
    outline-offset: 2px;
}

/* Tooltip personalizado */
[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
}
</style>

<?php include '../includes/footer.php'; ?>