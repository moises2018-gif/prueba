<?php
/**
 * SCRIPT PARA VERIFICAR TRIGGERS EN BASE DE DATOS
 * Archivo: verificar_triggers.php
 * Colócalo en la carpeta raíz o en /pages/
 */

include '../includes/header.php';
include '../includes/nav.php';
include '../includes/conexion.php';

$conn = ConexionBD();

if (!$conn) {
    echo "<div class='alert alert-error'>No se pudo conectar a la base de datos</div>";
    exit;
}

?>

<div class="tab-content" style="display: block;">
    <h2>🔧 Verificación de Triggers en Base de Datos</h2>
    
    <?php
    try {
        // 1. Verificar triggers específicos de la tabla docentes
        echo "<h3>📋 Triggers en la tabla 'docentes'</h3>";
        $query_triggers = "SHOW TRIGGERS LIKE 'docentes'";
        $stmt_triggers = $conn->prepare($query_triggers);
        $stmt_triggers->execute();
        $triggers = $stmt_triggers->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($triggers)) {
            echo "<div class='alert alert-success'>✅ Se encontraron " . count($triggers) . " trigger(s) activo(s)</div>";
            echo "<table class='table'>";
            echo "<tr><th>Nombre</th><th>Evento</th><th>Momento</th><th>Tabla</th></tr>";
            foreach ($triggers as $trigger) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($trigger['Trigger']) . "</td>";
                echo "<td>" . htmlspecialchars($trigger['Event']) . "</td>";
                echo "<td>" . htmlspecialchars($trigger['Timing']) . "</td>";
                echo "<td>" . htmlspecialchars($trigger['Table']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='alert alert-error'>❌ No se encontraron triggers en la tabla 'docentes'</div>";
        }
        
        // 2. Verificar todos los triggers de la base de datos
        echo "<h3>🗂️ Todos los triggers en la base de datos</h3>";
        $query_all_triggers = "
            SELECT 
                TRIGGER_NAME as nombre,
                EVENT_MANIPULATION as evento,
                EVENT_OBJECT_TABLE as tabla,
                ACTION_TIMING as momento,
                CREATED as fecha_creacion
            FROM information_schema.TRIGGERS 
            WHERE TRIGGER_SCHEMA = DATABASE()
            ORDER BY EVENT_OBJECT_TABLE, ACTION_TIMING";
        
        $stmt_all = $conn->prepare($query_all_triggers);
        $stmt_all->execute();
        $all_triggers = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($all_triggers)) {
            echo "<table class='table'>";
            echo "<tr><th>Nombre</th><th>Evento</th><th>Tabla</th><th>Momento</th><th>Fecha Creación</th></tr>";
            foreach ($all_triggers as $trigger) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($trigger['nombre']) . "</td>";
                echo "<td>" . htmlspecialchars($trigger['evento']) . "</td>";
                echo "<td>" . htmlspecialchars($trigger['tabla']) . "</td>";
                echo "<td>" . htmlspecialchars($trigger['momento']) . "</td>";
                echo "<td>" . htmlspecialchars($trigger['fecha_creacion'] ?: 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='alert alert-warning'>⚠️ No se encontraron triggers en la base de datos</div>";
        }
        
        // 3. Verificar integridad de datos (si los triggers han funcionado)
        echo "<h3>🔍 Verificación de Integridad de Datos</h3>";
        $query_integridad = "
            SELECT 
                (SELECT COUNT(*) FROM docentes) as total_docentes,
                (SELECT COUNT(*) FROM adaptaciones_metodologicas) as total_adaptaciones,
                (SELECT COUNT(*) FROM experiencia_docente_discapacidad) as total_experiencias,
                (SELECT COUNT(*) FROM limites_asignacion) as total_limites";
        
        $stmt_integridad = $conn->prepare($query_integridad);
        $stmt_integridad->execute();
        $integridad = $stmt_integridad->fetch(PDO::FETCH_ASSOC);
        
        echo "<div class='ahp-results'>";
        echo "<div class='ahp-card'>";
        echo "<h3>👥 Docentes</h3>";
        echo "<p style='font-size: 2em;'>" . $integridad['total_docentes'] . "</p>";
        echo "</div>";
        echo "<div class='ahp-card'>";
        echo "<h3>🔧 Adaptaciones</h3>";
        echo "<p style='font-size: 2em;'>" . $integridad['total_adaptaciones'] . "</p>";
        echo "</div>";
        echo "<div class='ahp-card'>";
        echo "<h3>📚 Experiencias</h3>";
        echo "<p style='font-size: 2em;'>" . $integridad['total_experiencias'] . "</p>";
        echo "</div>";
        echo "<div class='ahp-card'>";
        echo "<h3>⚖️ Límites</h3>";
        echo "<p style='font-size: 2em;'>" . $integridad['total_limites'] . "</p>";
        echo "</div>";
        echo "</div>";
        
        // 4. Análisis de funcionamiento de triggers
        echo "<h3>📊 Análisis de Funcionamiento</h3>";
        
        $triggers_funcionando = true;
        $mensajes_analisis = [];
        
        if ($integridad['total_docentes'] > 0) {
            if ($integridad['total_adaptaciones'] == 0) {
                $triggers_funcionando = false;
                $mensajes_analisis[] = "❌ Faltan adaptaciones metodológicas para " . $integridad['total_docentes'] . " docentes";
            } else {
                $mensajes_analisis[] = "✅ Adaptaciones metodológicas creadas correctamente";
            }
            
            if ($integridad['total_experiencias'] == 0) {
                $triggers_funcionando = false;
                $mensajes_analisis[] = "❌ Faltan experiencias por tipo de discapacidad";
            } else {
                $mensajes_analisis[] = "✅ Experiencias por tipo de discapacidad creadas correctamente";
            }
            
            if ($integridad['total_limites'] == 0) {
                $triggers_funcionando = false;
                $mensajes_analisis[] = "❌ Faltan límites de asignación";
            } else {
                $mensajes_analisis[] = "✅ Límites de asignación creados correctamente";
            }
        } else {
            $mensajes_analisis[] = "ℹ️ No hay docentes registrados para analizar";
        }
        
        if ($triggers_funcionando && !empty($triggers)) {
            echo "<div class='alert alert-success'>";
            echo "<h4>🎉 ¡Triggers funcionando correctamente!</h4>";
            echo "<ul>";
            foreach ($mensajes_analisis as $mensaje) {
                echo "<li>" . $mensaje . "</li>";
            }
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-error'>";
            echo "<h4>⚠️ Problemas detectados con los triggers</h4>";
            echo "<ul>";
            foreach ($mensajes_analisis as $mensaje) {
                echo "<li>" . $mensaje . "</li>";
            }
            echo "</ul>";
            echo "</div>";
        }
        
        // 5. Verificación detallada por docente
        echo "<h3>🔬 Verificación Detallada por Docente</h3>";
        $query_detalle = "
            SELECT 
                d.id_docente,
                d.nombres_completos,
                CASE WHEN am.id_adaptacion IS NOT NULL THEN '✅' ELSE '❌' END as tiene_adaptaciones,
                CASE WHEN edd.total_exp > 0 THEN '✅' ELSE '❌' END as tiene_experiencias,
                CASE WHEN la.id_limite IS NOT NULL THEN '✅' ELSE '❌' END as tiene_limites
            FROM docentes d
            LEFT JOIN adaptaciones_metodologicas am ON d.id_docente = am.id_docente
            LEFT JOIN (
                SELECT id_docente, COUNT(*) as total_exp 
                FROM experiencia_docente_discapacidad 
                GROUP BY id_docente
            ) edd ON d.id_docente = edd.id_docente
            LEFT JOIN limites_asignacion la ON d.id_docente = la.id_docente
            ORDER BY d.id_docente";
        
        $stmt_detalle = $conn->prepare($query_detalle);
        $stmt_detalle->execute();
        $detalle = $stmt_detalle->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($detalle)) {
            echo "<table class='table'>";
            echo "<tr><th>ID</th><th>Nombre del Docente</th><th>Adaptaciones</th><th>Experiencias</th><th>Límites</th></tr>";
            foreach ($detalle as $item) {
                echo "<tr>";
                echo "<td>" . $item['id_docente'] . "</td>";
                echo "<td>" . htmlspecialchars($item['nombres_completos']) . "</td>";
                echo "<td style='text-align: center; font-size: 18px;'>" . $item['tiene_adaptaciones'] . "</td>";
                echo "<td style='text-align: center; font-size: 18px;'>" . $item['tiene_experiencias'] . "</td>";
                echo "<td style='text-align: center; font-size: 18px;'>" . $item['tiene_limites'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        // 6. Comando SQL para reactivar triggers si es necesario
        echo "<h3>🛠️ Solución si los triggers no están activos</h3>";
        echo "<div class='alert alert-info'>";
        echo "<h4>Si los triggers no están funcionando, ejecuta este comando SQL:</h4>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
        echo "-- Eliminar trigger existente si existe\n";
        echo "DROP TRIGGER IF EXISTS trigger_nuevo_docente;\n\n";
        echo "-- Crear el trigger nuevamente\n";
        echo "DELIMITER \$\$\n";
        echo "CREATE TRIGGER trigger_nuevo_docente \n";
        echo "AFTER INSERT ON docentes FOR EACH ROW\n";
        echo "BEGIN\n";
        echo "    -- Insertar adaptaciones metodológicas\n";
        echo "    INSERT INTO adaptaciones_metodologicas (\n";
        echo "        id_docente, modificacion_contenido, uso_recursos_tecnologicos,\n";
        echo "        adaptacion_metodologia, coordinacion_servicios_apoyo, otras_adaptaciones\n";
        echo "    ) VALUES (\n";
        echo "        NEW.id_docente,\n";
        echo "        CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE 0 END,\n";
        echo "        CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE 0 END,\n";
        echo "        CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE 0 END,\n";
        echo "        CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE 0 END,\n";
        echo "        CASE WHEN NEW.formacion_inclusion = 1 \n";
        echo "             THEN 'Adaptaciones automáticas por formación'\n";
        echo "             ELSE 'Sin adaptaciones específicas'\n";
        echo "        END\n";
        echo "    );\n";
        echo "    \n";
        echo "    -- Insertar experiencias por tipo de discapacidad\n";
        echo "    INSERT INTO experiencia_docente_discapacidad \n";
        echo "    (id_docente, id_tipo_discapacidad, tiene_experiencia, años_experiencia, nivel_competencia, observaciones)\n";
        echo "    VALUES \n";
        echo "    (NEW.id_docente, 1, 0, 0, 'Básico', 'Generado automáticamente'),\n";
        echo "    (NEW.id_docente, 2, 0, 0, 'Básico', 'Generado automáticamente'),\n";
        echo "    (NEW.id_docente, 3, 0, 0, 'Básico', 'Generado automáticamente'),\n";
        echo "    (NEW.id_docente, 4, 0, 0, 'Básico', 'Generado automáticamente'),\n";
        echo "    (NEW.id_docente, 5, 0, 0, 'Básico', 'Generado automáticamente');\n";
        echo "    \n";
        echo "    -- Insertar límites de asignación\n";
        echo "    INSERT INTO limites_asignacion \n";
        echo "    (id_docente, maximo_estudiantes_nee, maximo_por_tipo_discapacidad, observaciones)\n";
        echo "    VALUES (\n";
        echo "        NEW.id_docente,\n";
        echo "        CASE WHEN NEW.formacion_inclusion = 1 THEN 7 ELSE 3 END,\n";
        echo "        CASE WHEN NEW.formacion_inclusion = 1 THEN 3 ELSE 2 END,\n";
        echo "        CONCAT('Límites automáticos basados en formación: ', NEW.formacion_inclusion)\n";
        echo "    );\n";
        echo "END\$\$\n";
        echo "DELIMITER ;";
        echo "</pre>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='alert alert-error'>Error al verificar triggers: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    ?>
    
    <div style="margin-top: 30px; text-align: center;">
        <a href="dashboard.php" class="btn">🏠 Volver al Dashboard</a>
        <button onclick="location.reload()" class="btn" style="background: #17a2b8;">🔄 Actualizar Verificación</button>
    </div>
</div>

<?php include '../includes/footer.php'; ?>