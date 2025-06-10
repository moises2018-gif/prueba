<?php include '../includes/header.php'; ?>
<?php include '../includes/nav.php'; ?>
<div id="asignacion" class="tab-content" style="display: block;">
    <h2>Asignación Automática de Docentes</h2>
    <?php
    include '../includes/conexion.php';
    $conn = ConexionBD();
    if ($conn) {
        // Obtener ciclos académicos disponibles
        $query_ciclos = "SELECT DISTINCT ciclo_academico FROM materias ORDER BY ciclo_academico DESC";
        $stmt_ciclos = $conn->prepare($query_ciclos);
        $stmt_ciclos->execute();
        $ciclos = $stmt_ciclos->fetchAll(PDO::FETCH_ASSOC);
    ?>
    
    <!-- Formulario de asignación automática -->
    <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
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
                <button type="submit" class="btn">Vista Previa de Asignaciones</button>
            </div>
        </form>
    </div>
    
    <?php if (isset($_GET['preview_data'])): ?>
        <div style="background: rgba(255, 255, 255, 0.1); padding: 20px; border-radius: 10px; margin-bottom: 30px;">
            <h3>Vista Previa de Asignaciones</h3>
            <?php
            $preview_data = json_decode(urldecode($_GET['preview_data']), true);
            if (!empty($preview_data)): ?>
                <form action="../procesar/procesar_asignacion_automatica.php" method="POST" class="form-group">
                    <input type="hidden" name="confirm" value="1">
                    <input type="hidden" name="ciclo_academico" value="<?php echo htmlspecialchars($_GET['ciclo_academico']); ?>">
                    <input type="hidden" name="preview_data" value="<?php echo htmlspecialchars($_GET['preview_data']); ?>">
                    <table class="table">
                        <tr>
                            <th>Estudiante</th>
                            <th>Tipo de Discapacidad</th>
                            <th>Materia</th>
                            <th>Docente Propuesto</th>
                            <th>Puntuación AHP</th>
                        </tr>
                        <?php foreach ($preview_data as $preview): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($preview['estudiante']); ?></td>
                                <td><?php echo htmlspecialchars($preview['nombre_discapacidad']); ?></td>
                                <td><?php echo htmlspecialchars($preview['materia']); ?></td>
                                <td><?php echo htmlspecialchars($preview['docente']); ?></td>
                                <td><?php echo number_format($preview['puntuacion_ahp'], 3); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <div style="margin-top: 15px;">
                        <button type="submit" class="btn" style="background: #28a745;">Confirmar Asignaciones</button>
                        <a href="asignacion.php" class="btn" style="background: #dc3545; margin-left: 10px;">Cancelar</a>
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
            <button type="submit" class="btn" style="background: #dc3545;" onclick="return confirmarEliminacion('¿Está seguro de que desea eliminar TODAS las asignaciones? Esta acción no se puede deshacer.')">Eliminar Todas las Asignaciones</button>
        </form>
    </div>
    
    <?php
        $query_asignaciones = "
            SELECT a.id_asignacion, d.nombres_completos AS docente, e.nombres_completos AS estudiante,
                   t.nombre_discapacidad, m.nombre_materia, a.ciclo_academico, a.puntuacion_ahp, a.estado
            FROM asignaciones a
            LEFT JOIN docentes d ON a.id_docente = d.id_docente
            LEFT JOIN estudiantes e ON a.id_estudiante = e.id_estudiante
            JOIN tipos_discapacidad t ON a.id_tipo_discapacidad = t.id_tipo_discapacidad
            LEFT JOIN materias m ON a.id_materia = m.id_materia
            WHERE a.estado = 'Activa'
            ORDER BY a.fecha_asignacion DESC";
        $stmt_asignaciones = $conn->prepare($query_asignaciones);
        $stmt_asignaciones->execute();
        $asignaciones = $stmt_asignaciones->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <table class="table">
        <tr>
            <th>Docente</th>
            <th>Estudiante</th>
            <th>Tipo de Discapacidad</th>
            <th>Materia</th>
            <th>Ciclo Académico</th>
            <th>Puntuación AHP</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
        <?php if (count($asignaciones) > 0): ?>
            <?php foreach ($asignaciones as $asignacion): ?>
                <tr>
                    <td><?php echo htmlspecialchars($asignacion['docente'] ?: 'No asignado'); ?></td>
                    <td><?php echo htmlspecialchars($asignacion['estudiante'] ?: 'No asignado'); ?></td>
                    <td><?php echo htmlspecialchars($asignacion['nombre_discapacidad']); ?></td>
                    <td><?php echo htmlspecialchars($asignacion['nombre_materia'] ?: 'No especificada'); ?></td>
                    <td><?php echo htmlspecialchars($asignacion['ciclo_academico']); ?></td>
                    <td><?php echo number_format($asignacion['puntuacion_ahp'], 3); ?></td>
                    <td><span style="color: green;"><?php echo htmlspecialchars($asignacion['estado']); ?></span></td>
                    <td>
                        <form action="../procesar/procesar_eliminar_asignacion.php" method="POST" style="display: inline;">
                            <input type="hidden" name="id_asignacion" value="<?php echo $asignacion['id_asignacion']; ?>">
                            <button type="submit" class="btn" style="background: #dc3545; padding: 5px 10px; font-size: 12px;" onclick="return confirmarEliminacion('¿Está seguro de que desea cancelar esta asignación?')">Cancelar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" style="text-align: center;">No hay asignaciones activas.</td>
            </tr>
        <?php endif; ?>
    </table>
    
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