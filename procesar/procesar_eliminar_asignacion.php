<?php
include '../includes/conexion.php';
$conn = ConexionBD();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Eliminar una asignación individual
    if (isset($_POST['id_asignacion'])) {
        $id_asignacion = (int)$_POST['id_asignacion'];
        
        try {
            // Mover la asignación al historial antes de cancelarla
            $query_historial = "
                INSERT INTO asignaciones_historial (
                    id_asignacion, id_docente, id_estudiante, id_tipo_discapacidad, 
                    id_materia, ciclo_academico, materia, numero_estudiantes, 
                    puntuacion_ahp, estado, fecha_asignacion, fecha_eliminacion
                )
                SELECT 
                    id_asignacion, id_docente, id_estudiante, id_tipo_discapacidad,
                    id_materia, ciclo_academico, materia, numero_estudiantes,
                    puntuacion_ahp, 'Cancelada', fecha_asignacion, NOW()
                FROM asignaciones 
                WHERE id_asignacion = :id";
            
            $stmt_historial = $conn->prepare($query_historial);
            $stmt_historial->execute([':id' => $id_asignacion]);
            
            // Cancelar la asignación
            $query = "UPDATE asignaciones SET estado = 'Cancelada' WHERE id_asignacion = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute([':id' => $id_asignacion]);
            
            header("Location: ../pages/asignacion.php?success=Asignación cancelada exitosamente");
            
        } catch (PDOException $e) {
            header("Location: ../pages/asignacion.php?error=Error al cancelar asignación: " . $e->getMessage());
        }
    }
    
    // Eliminar todas las asignaciones
    elseif (isset($_POST['eliminar_todas'])) {
        try {
            $conn->beginTransaction();
            
            // Mover todas las asignaciones activas al historial
            $query_historial_todas = "
                INSERT INTO asignaciones_historial (
                    id_asignacion, id_docente, id_estudiante, id_tipo_discapacidad, 
                    id_materia, ciclo_academico, materia, numero_estudiantes, 
                    puntuacion_ahp, estado, fecha_asignacion, fecha_eliminacion
                )
                SELECT 
                    id_asignacion, id_docente, id_estudiante, id_tipo_discapacidad,
                    id_materia, ciclo_academico, materia, numero_estudiantes,
                    puntuacion_ahp, 'Cancelada', fecha_asignacion, NOW()
                FROM asignaciones 
                WHERE estado = 'Activa'";
            
            $stmt_historial_todas = $conn->prepare($query_historial_todas);
            $stmt_historial_todas->execute();
            
            // Cancelar todas las asignaciones activas
            $query_todas = "UPDATE asignaciones SET estado = 'Cancelada' WHERE estado = 'Activa'";
            $stmt_todas = $conn->prepare($query_todas);
            $stmt_todas->execute();
            
            $filas_afectadas = $stmt_todas->rowCount();
            
            $conn->commit();
            header("Location: ../pages/asignacion.php?success=Se cancelaron $filas_afectadas asignaciones exitosamente");
            
        } catch (PDOException $e) {
            $conn->rollBack();
            header("Location: ../pages/asignacion.php?error=Error al cancelar todas las asignaciones: " . $e->getMessage());
        }
    }
    
    // Eliminar desde reportes
    elseif (isset($_POST['eliminar_desde_reporte'])) {
        $id_asignacion = (int)$_POST['eliminar_desde_reporte'];
        
        try {
            // Mover la asignación al historial antes de cancelarla
            $query_historial = "
                INSERT INTO asignaciones_historial (
                    id_asignacion, id_docente, id_estudiante, id_tipo_discapacidad, 
                    id_materia, ciclo_academico, materia, numero_estudiantes, 
                    puntuacion_ahp, estado, fecha_asignacion, fecha_eliminacion
                )
                SELECT 
                    id_asignacion, id_docente, id_estudiante, id_tipo_discapacidad,
                    id_materia, ciclo_academico, materia, numero_estudiantes,
                    puntuacion_ahp, 'Cancelada', fecha_asignacion, NOW()
                FROM asignaciones 
                WHERE id_asignacion = :id";
            
            $stmt_historial = $conn->prepare($query_historial);
            $stmt_historial->execute([':id' => $id_asignacion]);
            
            // Cancelar la asignación
            $query = "UPDATE asignaciones SET estado = 'Cancelada' WHERE id_asignacion = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute([':id' => $id_asignacion]);
            
            header("Location: ../pages/reportes.php?success=Asignación eliminada desde reportes exitosamente");
            
        } catch (PDOException $e) {
            header("Location: ../pages/reportes.php?error=Error al eliminar asignación: " . $e->getMessage());
        }
    }
    
    // Limpiar historial de asignaciones
    elseif (isset($_POST['limpiar_historial'])) {
        try {
            $conn->beginTransaction();
            
            // Contar registros antes de eliminar para mostrar en el mensaje
            $total_canceladas = $conn->query("SELECT COUNT(*) FROM asignaciones WHERE estado = 'Cancelada'")->fetchColumn();
            $total_historial = $conn->query("SELECT COUNT(*) FROM asignaciones_historial")->fetchColumn();
            
            // Eliminar todas las asignaciones canceladas de la tabla principal
            $query_canceladas = "DELETE FROM asignaciones WHERE estado = 'Cancelada'";
            $stmt_canceladas = $conn->prepare($query_canceladas);
            $stmt_canceladas->execute();
            
            // Eliminar todo el historial
            $query_historial = "DELETE FROM asignaciones_historial";
            $stmt_historial = $conn->prepare($query_historial);
            $stmt_historial->execute();
            
            $conn->commit();
            
            $total_eliminado = $total_canceladas + $total_historial;
            header("Location: ../pages/reportes.php?success=Historial limpiado exitosamente. Se eliminaron $total_eliminado registros históricos.");
            
        } catch (PDOException $e) {
            $conn->rollBack();
            header("Location: ../pages/reportes.php?error=Error al limpiar historial: " . $e->getMessage());
        }
    }
    
} else {
    header("Location: ../pages/asignacion.php");
}
?>