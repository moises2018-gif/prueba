<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>
<div id="dashboard" class="tab-content" style="display: block;">
    <h2>Dashboard del Sistema AHP</h2>
    <?php
    include '../includes/conexion.php';
    $conn = ConexionBD();
    if ($conn) {
        $query_criterios = "SELECT * FROM criterios_ahp";
        $stmt_criterios = $conn->prepare($query_criterios);
        $stmt_criterios->execute();
        $criterios = $stmt_criterios->fetchAll(PDO::FETCH_ASSOC);

        $query_subcriterios = "SELECT * FROM tipos_discapacidad";
        $stmt_subcriterios = $conn->prepare($query_subcriterios);
        $stmt_subcriterios->execute();
        $subcriterios = $stmt_subcriterios->fetchAll(PDO::FETCH_ASSOC);

        $query_ranking = "SELECT * FROM vista_ranking_ahp";
        $stmt_ranking = $conn->prepare($query_ranking);
        $stmt_ranking->execute();
        $ranking = $stmt_ranking->fetchAll(PDO::FETCH_ASSOC);

        $query_asignaciones = "
            SELECT COUNT(*) as total FROM asignaciones WHERE estado = 'Activa'";
        $total_asignaciones = $conn->query($query_asignaciones)->fetchColumn();

        $total_docentes = count($ranking);
        $docentes_con_formacion = $conn->query("SELECT COUNT(*) FROM docentes WHERE formacion_inclusion = TRUE")->fetchColumn();
        $porcentaje_formacion = ($total_docentes > 0) ? ($docentes_con_formacion / $total_docentes * 100) : 0;
    ?>
    <div class="criteria-weights">
        <?php foreach ($criterios as $criterio): ?>
            <div class="criteria-item">
                <div class="weight"><?php echo number_format($criterio['peso_criterio'] * 100, 1); ?>%</div>
                <div><?php echo htmlspecialchars($criterio['nombre_criterio']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="ahp-results">
        <div class="ahp-card">
            <h3>Total Docentes</h3>
            <p><?php echo $total_docentes; ?> docentes evaluados</p>
        </div>
        <div class="ahp-card">
            <h3>Formación en Inclusión</h3>
            <p><?php echo number_format($porcentaje_formacion, 1); ?>% con formación</p>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $porcentaje_formacion; ?>%;"></div>
            </div>
        </div>
        <div class="ahp-card">
            <h3>Asignaciones Activas</h3>
            <p><?php echo $total_asignaciones; ?> asignaciones activas</p>
        </div>
    </div>
    <canvas id="criteriosChart"></canvas>
    <script>
        const ctx = document.getElementById('criteriosChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_column($criterios, 'codigo_criterio')) . "'"; ?>],
                datasets: [{
                    label: 'Pesos de Criterios',
                    data: [<?php echo implode(',', array_column($criterios, 'peso_criterio')); ?>],
                    backgroundColor: ['#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF'],
                    borderColor: ['#FFFFFF'],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true, max: 0.5, title: { display: true, text: 'Peso' } },
                    x: { title: { display: true, text: 'Criterios' } }
                },
                plugins: { title: { display: true, text: 'Pesos de los Criterios para Asignación de Docentes' } }
            }
        });
    </script>
    <?php } else { ?>
        <div class="alert alert-error">No se pudo conectar a la base de datos.</div>
    <?php } ?>
</div>
<?php include '../includes/footer.php'; ?>