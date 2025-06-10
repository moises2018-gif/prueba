<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>
<div id="estudiantes" class="tab-content" style="display: block;">
    <h2>Estudiantes con Necesidades Educativas Especiales</h2>
    <?php
    include '../includes/conexion.php';
    $conn = ConexionBD();
    if ($conn) {
        // Obtener tipos de discapacidad
        $query_tipos = "SELECT * FROM tipos_discapacidad ORDER BY peso_prioridad DESC";
        $stmt_tipos = $conn->prepare($query_tipos);
        $stmt_tipos->execute();
        $tipos_discapacidad = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

        // Obtener estudiantes
        $query_estudiantes = "
            SELECT e.id_estudiante, e.nombres_completos, t.nombre_discapacidad, e.ciclo_academico, e.facultad,
                   a.id_asignacion, a.estado, d.nombres_completos AS docente_asignado
            FROM estudiantes e
            JOIN tipos_discapacidad t ON e.id_tipo_discapacidad = t.id_tipo_discapacidad
            LEFT JOIN asignaciones a ON e.id_estudiante = a.id_estudiante
            LEFT JOIN docentes d ON a.id_docente = d.id_docente
            ORDER BY e.nombres_completos";
        $stmt_estudiantes = $conn->prepare($query_estudiantes);
        $stmt_estudiantes->execute();
        $estudiantes = $stmt_estudiantes->fetchAll(PDO::FETCH_ASSOC);

        // Obtener estadísticas
        $query_stats = "
            SELECT 
                t.nombre_discapacidad,
                t.peso_prioridad,
                COUNT(a.id_asignacion) as total_asignaciones,
                COUNT(e.id_estudiante) as total_estudiantes,
                COUNT(DISTINCT a.ciclo_academico) as ciclos_activos
            FROM tipos_discapacidad t
            LEFT JOIN estudiantes e ON t.id_tipo_discapacidad = e.id_tipo_discapacidad
            LEFT JOIN asignaciones a ON e.id_estudiante = a.id_estudiante
            GROUP BY t.id_tipo_discapacidad
            ORDER BY t.peso_prioridad DESC";
        $stmt_stats = $conn->prepare($query_stats);
        $stmt_stats->execute();
        $estadisticas = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <h3>Distribución de Estudiantes por Tipo de Discapacidad</h3>
    <div class="ahp-results">
        <?php foreach ($estadisticas as $stat): ?>
            <div class="ahp-card">
                <h3><?php echo htmlspecialchars($stat['nombre_discapacidad']); ?></h3>
                <p>Prioridad: <?php echo number_format($stat['peso_prioridad'] * 100, 1); ?>%</p>
                <p>Total Estudiantes: <?php echo $stat['total_estudiantes'] ?: 0; ?></p>
                <p>Asignaciones: <?php echo $stat['total_asignaciones'] ?: 0; ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    
    <h3>Lista de Estudiantes</h3>
    <table class="table">
        <tr>
            <th>Nombre del Estudiante</th>
            <th>Tipo de Discapacidad</th>
            <th>Ciclo Académico</th>
            <th>Facultad</th>
            <th>Estado</th>
            <th>Docente Asignado</th>
        </tr>
        <?php if (count($estudiantes) > 0): ?>
            <?php foreach ($estudiantes as $estudiante): ?>
                <tr>
                    <td><?php echo htmlspecialchars($estudiante['nombres_completos']); ?></td>
                    <td><?php echo htmlspecialchars($estudiante['nombre_discapacidad']); ?></td>
                    <td><?php echo htmlspecialchars($estudiante['ciclo_academico']); ?></td>
                    <td><?php echo htmlspecialchars($estudiante['facultad']); ?></td>
                    <td>
                        <?php if ($estudiante['id_asignacion']): ?>
                            <span style="color: <?php echo $estudiante['estado'] == 'Activa' ? 'green' : ($estudiante['estado'] == 'Finalizada' ? 'blue' : 'red'); ?>">
                                <?php echo htmlspecialchars($estudiante['estado']); ?>
                            </span>
                        <?php else: ?>
                            <span style="color: orange;">⏳ Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $estudiante['docente_asignado'] ? htmlspecialchars($estudiante['docente_asignado']) : 'No asignado'; ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="6" style="text-align: center;">No hay estudiantes registrados.</td>
            </tr>
        <?php endif; ?>
    </table>
    
    <h3>Tipos de Discapacidad</h3>
    <table class="table">
        <tr>
            <th>Tipo de Discapacidad</th>
            <th>Peso de Prioridad</th>
            <th>Descripción</th>
        </tr>
        <?php foreach ($tipos_discapacidad as $tipo): ?>
            <tr>
                <td><?php echo htmlspecialchars($tipo['nombre_discapacidad']); ?></td>
                <td>
                    <div class="progress-bar" style="width: 200px; display: inline-block;">
                        <div class="progress-fill" style="width: <?php echo $tipo['peso_prioridad'] * 100; ?>%;"></div>
                    </div>
                    <?php echo number_format($tipo['peso_prioridad'] * 100, 1); ?>%
                </td>
                <td><?php echo htmlspecialchars($tipo['descripcion']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
    
    <?php } else { ?>
        <div class="alert alert-error">No se pudo conectar a la base de datos.</div>
    <?php } ?>
</div>
<?php include '../includes/footer.php'; ?>