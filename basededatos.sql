-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 20-06-2025 a las 08:10:33
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `asignacion_docente`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `actualizar_adaptaciones_docente` (IN `p_id_docente` INT, IN `p_modificacion_contenido` TINYINT, IN `p_uso_recursos_tecnologicos` TINYINT, IN `p_adaptacion_metodologia` TINYINT, IN `p_coordinacion_servicios_apoyo` TINYINT, IN `p_otras_adaptaciones` TEXT)   BEGIN
    UPDATE adaptaciones_metodologicas 
    SET 
        modificacion_contenido = p_modificacion_contenido,
        uso_recursos_tecnologicos = p_uso_recursos_tecnologicos,
        adaptacion_metodologia = p_adaptacion_metodologia,
        coordinacion_servicios_apoyo = p_coordinacion_servicios_apoyo,
        otras_adaptaciones = p_otras_adaptaciones
    WHERE id_docente = p_id_docente;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `actualizar_experiencia_docente` (IN `p_id_docente` INT, IN `p_tipo_discapacidad` INT, IN `p_tiene_experiencia` TINYINT, IN `p_años_experiencia` INT, IN `p_nivel_competencia` VARCHAR(20))   BEGIN
    UPDATE experiencia_docente_discapacidad 
    SET 
        tiene_experiencia = p_tiene_experiencia,
        años_experiencia = p_años_experiencia,
        nivel_competencia = p_nivel_competencia,
        observaciones = CONCAT('Actualizado manualmente el ', NOW())
    WHERE 
        id_docente = p_id_docente 
        AND id_tipo_discapacidad = p_tipo_discapacidad;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `asignar_estudiante_automatico` (IN `p_id_estudiante` INT, IN `p_id_materia` INT, IN `p_ciclo_academico` VARCHAR(20))   BEGIN
    DECLARE v_id_docente INT;
    DECLARE v_tipo_discapacidad INT;
    DECLARE v_facultad_estudiante VARCHAR(255);
    DECLARE v_puntuacion_ahp DECIMAL(5,3);
    DECLARE v_mensaje VARCHAR(500);
    
    -- Obtener información del estudiante
    SELECT id_tipo_discapacidad, facultad 
    INTO v_tipo_discapacidad, v_facultad_estudiante
    FROM estudiantes 
    WHERE id_estudiante = p_id_estudiante;
    
    -- Buscar el mejor docente disponible usando el procedimiento existente
    CALL recomendar_docente_equilibrado(v_tipo_discapacidad, v_facultad_estudiante);
    
    -- Aquí necesitarías capturar el resultado del procedimiento anterior
    -- y hacer la inserción en asignaciones
    
    SELECT 'Funcionalidad en desarrollo - usar recomendar_docente_equilibrado' AS mensaje;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `recalcular_cache_completo` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_id_docente INT;
    DECLARE v_ranking_counter INT DEFAULT 1;
    DECLARE v_fsi, v_epr, v_ami, v_aed, v_nfa, v_final DECIMAL(11,5);
    
    DECLARE cur_docentes CURSOR FOR
        SELECT 
            vp.id_docente,
            vp.puntuacion_fsi,
            vp.puntuacion_epr,
            vp.puntuacion_ami,
            vp.puntuacion_aed,
            vp.puntuacion_nfa,
            (vp.puntuacion_fsi * 0.280 + vp.puntuacion_epr * 0.320 + 
             vp.puntuacion_ami * 0.160 + vp.puntuacion_aed * 0.130 + 
             vp.puntuacion_nfa * 0.110) AS puntuacion_final
        FROM vista_puntuaciones_ahp vp
        ORDER BY puntuacion_final DESC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Limpiar caché existente
    DELETE FROM cache_puntuaciones_ahp;
    DELETE FROM cache_puntuaciones_especificas;
    
    -- Recalcular puntuaciones generales
    SET v_ranking_counter = 1;
    OPEN cur_docentes;
    
    docentes_loop: LOOP
        FETCH cur_docentes INTO v_id_docente, v_fsi, v_epr, v_ami, v_aed, v_nfa, v_final;
        IF done THEN
            LEAVE docentes_loop;
        END IF;
        
        INSERT INTO cache_puntuaciones_ahp (
            id_docente, puntuacion_fsi, puntuacion_epr, puntuacion_ami, 
            puntuacion_aed, puntuacion_nfa, puntuacion_final, ranking_general
        ) VALUES (
            v_id_docente, v_fsi, v_epr, v_ami, v_aed, v_nfa, v_final, v_ranking_counter
        );
        
        SET v_ranking_counter = v_ranking_counter + 1;
    END LOOP;
    
    CLOSE cur_docentes;
    
    -- Recalcular puntuaciones específicas por discapacidad
    INSERT INTO cache_puntuaciones_especificas (
        id_docente, id_tipo_discapacidad, puntuacion_especifica, 
        ranking_especifico, tiene_experiencia_especifica, nivel_competencia_especifica
    )
    SELECT 
        vra.id_docente,
        vra.id_tipo_discapacidad,
        vra.puntuacion_especifica_discapacidad,
        vra.ranking_por_discapacidad,
        vra.tiene_experiencia_especifica,
        vra.nivel_competencia_especifica
    FROM vista_ranking_ahp_especifico vra;
    
    -- Registrar en log
    INSERT INTO log_actualizaciones_cache (
        tabla_afectada, id_registro, tipo_operacion, campos_modificados
    ) VALUES (
        'cache_completo', 0, 'UPDATE', 'Recálculo completo de caché ejecutado'
    );
    
    SELECT 'Caché recalculado completamente' AS resultado;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `recalcular_cache_docente` (IN `p_id_docente` INT)   BEGIN
    DECLARE v_fsi, v_epr, v_ami, v_aed, v_nfa, v_final DECIMAL(11,5);
    DECLARE v_nuevo_ranking INT;
    
    -- Obtener puntuaciones actualizadas del docente
    SELECT 
        puntuacion_fsi, puntuacion_epr, puntuacion_ami, puntuacion_aed, puntuacion_nfa,
        (puntuacion_fsi * 0.280 + puntuacion_epr * 0.320 + puntuacion_ami * 0.160 + 
         puntuacion_aed * 0.130 + puntuacion_nfa * 0.110)
    INTO v_fsi, v_epr, v_ami, v_aed, v_nfa, v_final
    FROM vista_puntuaciones_ahp 
    WHERE id_docente = p_id_docente;
    
    -- Calcular nuevo ranking
    SELECT COUNT(*) + 1 INTO v_nuevo_ranking
    FROM vista_puntuaciones_ahp vp
    WHERE (vp.puntuacion_fsi * 0.280 + vp.puntuacion_epr * 0.320 + 
           vp.puntuacion_ami * 0.160 + vp.puntuacion_aed * 0.130 + 
           vp.puntuacion_nfa * 0.110) > v_final;
    
    -- Actualizar o insertar en caché general
    INSERT INTO cache_puntuaciones_ahp (
        id_docente, puntuacion_fsi, puntuacion_epr, puntuacion_ami, 
        puntuacion_aed, puntuacion_nfa, puntuacion_final, ranking_general
    ) VALUES (
        p_id_docente, v_fsi, v_epr, v_ami, v_aed, v_nfa, v_final, v_nuevo_ranking
    ) ON DUPLICATE KEY UPDATE
        puntuacion_fsi = v_fsi,
        puntuacion_epr = v_epr,
        puntuacion_ami = v_ami,
        puntuacion_aed = v_aed,
        puntuacion_nfa = v_nfa,
        puntuacion_final = v_final,
        ranking_general = v_nuevo_ranking;
    
    -- Actualizar caché específico por discapacidad
    DELETE FROM cache_puntuaciones_especificas WHERE id_docente = p_id_docente;
    
    INSERT INTO cache_puntuaciones_especificas (
        id_docente, id_tipo_discapacidad, puntuacion_especifica, 
        ranking_especifico, tiene_experiencia_especifica, nivel_competencia_especifica
    )
    SELECT 
        id_docente, id_tipo_discapacidad, puntuacion_especifica_discapacidad,
        ranking_por_discapacidad, tiene_experiencia_especifica, nivel_competencia_especifica
    FROM vista_ranking_ahp_especifico 
    WHERE id_docente = p_id_docente;
    
    -- Registrar en log
    INSERT INTO log_actualizaciones_cache (
        tabla_afectada, id_registro, tipo_operacion, campos_modificados
    ) VALUES (
        'cache_docente', p_id_docente, 'UPDATE', 
        CONCAT('Recálculo de caché para docente ID: ', p_id_docente)
    );
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `recomendar_docente_equilibrado` (IN `tipo_discapacidad` INT, IN `facultad_estudiante` VARCHAR(255))   BEGIN
    DECLARE docente_id INT;
    DECLARE docente_nombre VARCHAR(255);
    DECLARE puntuacion_ahp DECIMAL(5,3);
    DECLARE capacidad_restante INT;
    DECLARE puede_tipo BOOLEAN;
    
    -- Buscar el mejor docente disponible
    SELECT 
        vdc.id_docente,
        vdc.nombres_completos,
        vra.puntuacion_especifica_discapacidad,
        vdc.capacidad_restante,
        CASE 
            WHEN tipo_discapacidad = 1 THEN (vdc.psicosocial_actual < vdc.maximo_por_tipo_discapacidad)
            WHEN tipo_discapacidad = 2 THEN (vdc.auditiva_actual < vdc.maximo_por_tipo_discapacidad)
            WHEN tipo_discapacidad = 3 THEN (vdc.intelectual_actual < vdc.maximo_por_tipo_discapacidad)
            WHEN tipo_discapacidad = 4 THEN (vdc.visual_actual < vdc.maximo_por_tipo_discapacidad)
            WHEN tipo_discapacidad = 5 THEN (vdc.fisica_actual < vdc.maximo_por_tipo_discapacidad)
            ELSE FALSE
        END as puede_tipo_discapacidad
    INTO docente_id, docente_nombre, puntuacion_ahp, capacidad_restante, puede_tipo
    FROM vista_distribucion_carga vdc
    JOIN vista_ranking_ahp_especifico vra ON vdc.id_docente = vra.id_docente
    WHERE vdc.capacidad_restante > 0
    AND vra.id_tipo_discapacidad = tipo_discapacidad
    AND vra.facultad = facultad_estudiante
    AND (CASE 
            WHEN tipo_discapacidad = 1 THEN (vdc.psicosocial_actual < vdc.maximo_por_tipo_discapacidad)
            WHEN tipo_discapacidad = 2 THEN (vdc.auditiva_actual < vdc.maximo_por_tipo_discapacidad)
            WHEN tipo_discapacidad = 3 THEN (vdc.intelectual_actual < vdc.maximo_por_tipo_discapacidad)
            WHEN tipo_discapacidad = 4 THEN (vdc.visual_actual < vdc.maximo_por_tipo_discapacidad)
            WHEN tipo_discapacidad = 5 THEN (vdc.fisica_actual < vdc.maximo_por_tipo_discapacidad)
            ELSE FALSE
        END) = TRUE
    ORDER BY 
        vdc.porcentaje_carga ASC,  -- Priorizar docentes con menor carga
        vra.puntuacion_especifica_discapacidad DESC  -- Luego por competencia AHP
    LIMIT 1;
    
    IF docente_id IS NOT NULL THEN
        SELECT 
            docente_id AS id_docente,
            docente_nombre AS nombre_docente,
            puntuacion_ahp AS puntuacion_ahp,
            capacidad_restante AS capacidad_restante,
            'Docente recomendado encontrado' AS mensaje;
    ELSE
        SELECT 
            NULL AS id_docente,
            'No disponible' AS nombre_docente,
            0 AS puntuacion_ahp,
            0 AS capacidad_restante,
            'No hay docentes disponibles con capacidad para este tipo de discapacidad' AS mensaje;
    END IF;
END$$

--
-- Funciones
--
CREATE DEFINER=`root`@`localhost` FUNCTION `estado_cache` () RETURNS VARCHAR(500) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE total_docentes INT;
    DECLARE cache_general INT;
    DECLARE cache_especifico INT;
    DECLARE ultima_actualizacion TIMESTAMP;
    
    SELECT COUNT(*) INTO total_docentes FROM docentes;
    SELECT COUNT(*) INTO cache_general FROM cache_puntuaciones_ahp;
    SELECT COUNT(*) INTO cache_especifico FROM cache_puntuaciones_especificas;
    SELECT MAX(fecha_calculo) INTO ultima_actualizacion FROM cache_puntuaciones_ahp;
    
    RETURN CONCAT(
        'Docentes: ', total_docentes,
        ' | Caché general: ', cache_general,
        ' | Caché específico: ', cache_especifico,
        ' | Última actualización: ', COALESCE(ultima_actualizacion, 'Nunca')
    );
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `mejor_docente_por_discapacidad` (`p_tipo_discapacidad` INT) RETURNS VARCHAR(255) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_nombre_docente VARCHAR(255);
    
    SELECT d.nombres_completos 
    INTO v_nombre_docente
    FROM cache_puntuaciones_especificas ce
    JOIN docentes d ON ce.id_docente = d.id_docente
    WHERE ce.id_tipo_discapacidad = p_tipo_discapacidad
    AND ce.ranking_especifico = 1
    LIMIT 1;
    
    RETURN COALESCE(v_nombre_docente, 'No encontrado');
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `verificar_triggers_activos` () RETURNS VARCHAR(500) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE total_triggers INT;
    DECLARE trigger_insert INT DEFAULT 0;
    DECLARE trigger_update INT DEFAULT 0;
    
    SELECT COUNT(*) INTO total_triggers
    FROM information_schema.TRIGGERS 
    WHERE TRIGGER_SCHEMA = DATABASE();
    
    SELECT COUNT(*) INTO trigger_insert
    FROM information_schema.TRIGGERS 
    WHERE TRIGGER_SCHEMA = DATABASE() 
    AND TRIGGER_NAME = 'trigger_nuevo_docente';
    
    SELECT COUNT(*) INTO trigger_update
    FROM information_schema.TRIGGERS 
    WHERE TRIGGER_SCHEMA = DATABASE() 
    AND TRIGGER_NAME = 'trigger_actualizar_docente';
    
    RETURN CONCAT(
        'Total triggers: ', total_triggers,
        ' | INSERT: ', CASE WHEN trigger_insert > 0 THEN '✅' ELSE '❌' END,
        ' | UPDATE: ', CASE WHEN trigger_update > 0 THEN '✅' ELSE '❌' END
    );
END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `verificar_vista_ranking` () RETURNS VARCHAR(500) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE total_registros INT;
    DECLARE tipos_discapacidad INT;
    DECLARE docentes_activos INT;
    
    SELECT COUNT(*) INTO total_registros FROM vista_ranking_ahp_especifico;
    SELECT COUNT(DISTINCT id_tipo_discapacidad) INTO tipos_discapacidad FROM vista_ranking_ahp_especifico;
    SELECT COUNT(DISTINCT id_docente) INTO docentes_activos FROM vista_ranking_ahp_especifico;
    
    RETURN CONCAT(
        'Vista funcionando ✅ | ',
        'Registros: ', total_registros, ' | ',
        'Tipos discapacidad: ', tipos_discapacidad, ' | ',
        'Docentes: ', docentes_activos
    );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `adaptaciones_metodologicas`
--

CREATE TABLE `adaptaciones_metodologicas` (
  `id_adaptacion` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `modificacion_contenido` tinyint(1) DEFAULT 0,
  `uso_recursos_tecnologicos` tinyint(1) DEFAULT 0,
  `adaptacion_metodologia` tinyint(1) DEFAULT 0,
  `coordinacion_servicios_apoyo` tinyint(1) DEFAULT 0,
  `otras_adaptaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `adaptaciones_metodologicas`
--

INSERT INTO `adaptaciones_metodologicas` (`id_adaptacion`, `id_docente`, `modificacion_contenido`, `uso_recursos_tecnologicos`, `adaptacion_metodologia`, `coordinacion_servicios_apoyo`, `otras_adaptaciones`) VALUES
(1, 1, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(2, 2, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(3, 3, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(4, 4, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(5, 5, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(6, 6, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(7, 7, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(8, 8, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(9, 9, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(10, 10, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión');

--
-- Disparadores `adaptaciones_metodologicas`
--
DELIMITER $$
CREATE TRIGGER `trigger_cache_actualizar_adaptaciones` AFTER UPDATE ON `adaptaciones_metodologicas` FOR EACH ROW BEGIN
    INSERT INTO log_actualizaciones_cache (
        tabla_afectada, id_registro, tipo_operacion, campos_modificados
    ) VALUES (
        'adaptaciones_metodologicas', NEW.id_docente, 'UPDATE',
        'Adaptaciones metodológicas modificadas'
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones`
--

CREATE TABLE `asignaciones` (
  `id_asignacion` int(11) NOT NULL,
  `id_docente` int(11) DEFAULT NULL,
  `id_tipo_discapacidad` int(11) NOT NULL,
  `ciclo_academico` varchar(20) NOT NULL,
  `materia` varchar(255) DEFAULT NULL,
  `numero_estudiantes` int(11) NOT NULL,
  `puntuacion_ahp` decimal(5,3) NOT NULL,
  `estado` enum('Activa','Finalizada','Cancelada') DEFAULT 'Activa',
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_estudiante` int(11) DEFAULT NULL,
  `id_materia` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones_historial`
--

CREATE TABLE `asignaciones_historial` (
  `id_historial` int(11) NOT NULL,
  `id_asignacion` int(11) DEFAULT NULL,
  `id_docente` int(11) DEFAULT NULL,
  `id_estudiante` int(11) DEFAULT NULL,
  `id_tipo_discapacidad` int(11) NOT NULL,
  `id_materia` int(11) DEFAULT NULL,
  `ciclo_academico` varchar(20) NOT NULL,
  `materia` varchar(255) DEFAULT NULL,
  `numero_estudiantes` int(11) DEFAULT NULL,
  `puntuacion_ahp` float DEFAULT NULL,
  `estado` varchar(50) NOT NULL,
  `fecha_asignacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_eliminacion` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache_puntuaciones_ahp`
--

CREATE TABLE `cache_puntuaciones_ahp` (
  `id_cache` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `puntuacion_fsi` decimal(3,2) NOT NULL,
  `puntuacion_epr` decimal(3,2) NOT NULL,
  `puntuacion_ami` decimal(3,2) NOT NULL,
  `puntuacion_aed` decimal(3,2) NOT NULL,
  `puntuacion_nfa` decimal(3,2) NOT NULL,
  `puntuacion_final` decimal(11,5) NOT NULL,
  `ranking_general` int(11) NOT NULL,
  `fecha_calculo` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cache_puntuaciones_ahp`
--

INSERT INTO `cache_puntuaciones_ahp` (`id_cache`, `id_docente`, `puntuacion_fsi`, `puntuacion_epr`, `puntuacion_ami`, `puntuacion_aed`, `puntuacion_nfa`, `puntuacion_final`, `ranking_general`, `fecha_calculo`) VALUES
(11, 10, 0.90, 0.90, 0.90, 0.90, 0.70, 0.87800, 1, '2025-06-20 06:09:23'),
(12, 7, 0.90, 0.75, 0.90, 0.70, 0.70, 0.80400, 2, '2025-06-20 06:09:23'),
(13, 5, 0.75, 0.75, 0.90, 0.90, 0.70, 0.78800, 3, '2025-06-20 06:09:23'),
(14, 9, 0.75, 0.75, 0.90, 0.90, 0.70, 0.78800, 4, '2025-06-20 06:09:23'),
(15, 2, 0.75, 0.75, 0.90, 0.90, 0.70, 0.78800, 5, '2025-06-20 06:09:23'),
(16, 8, 0.90, 0.40, 0.90, 0.50, 0.90, 0.68800, 6, '2025-06-20 06:09:23'),
(17, 1, 0.90, 0.40, 0.90, 0.50, 0.90, 0.68800, 7, '2025-06-20 06:09:23'),
(18, 3, 0.20, 0.60, 0.20, 0.70, 0.50, 0.42600, 8, '2025-06-20 06:09:23'),
(19, 4, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 9, '2025-06-20 06:09:23'),
(20, 6, 0.20, 0.40, 0.20, 0.50, 0.50, 0.33600, 10, '2025-06-20 06:09:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache_puntuaciones_especificas`
--

CREATE TABLE `cache_puntuaciones_especificas` (
  `id_cache_especifico` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_tipo_discapacidad` int(11) NOT NULL,
  `puntuacion_especifica` decimal(15,7) NOT NULL,
  `ranking_especifico` int(11) NOT NULL,
  `tiene_experiencia_especifica` tinyint(1) DEFAULT 0,
  `nivel_competencia_especifica` varchar(20) DEFAULT 'Básico',
  `fecha_calculo` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cache_puntuaciones_especificas`
--

INSERT INTO `cache_puntuaciones_especificas` (`id_cache_especifico`, `id_docente`, `id_tipo_discapacidad`, `puntuacion_especifica`, `ranking_especifico`, `tiene_experiencia_especifica`, `nivel_competencia_especifica`, `fecha_calculo`) VALUES
(64, 10, 1, 1.0177500, 1, 1, 'Experto', '2025-06-20 06:09:23'),
(65, 7, 1, 0.8756000, 2, 1, 'Avanzado', '2025-06-20 06:09:23'),
(66, 9, 1, 0.8481000, 3, 1, 'Avanzado', '2025-06-20 06:09:23'),
(67, 2, 1, 0.8481000, 3, 1, 'Avanzado', '2025-06-20 06:09:23'),
(68, 5, 1, 0.8481000, 3, 1, 'Avanzado', '2025-06-20 06:09:23'),
(69, 8, 1, 0.7049500, 6, 1, 'Experto', '2025-06-20 06:09:23'),
(70, 1, 1, 0.6743000, 7, 1, 'Avanzado', '2025-06-20 06:09:23'),
(71, 3, 1, 0.4420000, 8, 0, 'Básico', '2025-06-20 06:09:23'),
(72, 4, 1, 0.3860000, 9, 0, 'Básico', '2025-06-20 06:09:23'),
(73, 6, 1, 0.3280000, 10, 0, 'Básico', '2025-06-20 06:09:23'),
(74, 10, 3, 1.0154500, 1, 1, 'Experto', '2025-06-20 06:09:23'),
(75, 7, 3, 0.9602500, 2, 1, 'Experto', '2025-06-20 06:09:23'),
(76, 8, 3, 0.8682500, 3, 1, 'Experto', '2025-06-20 06:09:23'),
(77, 1, 3, 0.8682500, 3, 1, 'Experto', '2025-06-20 06:09:23'),
(78, 9, 3, 0.8232000, 5, 1, 'Intermedio', '2025-06-20 06:09:23'),
(79, 2, 3, 0.8232000, 5, 1, 'Intermedio', '2025-06-20 06:09:23'),
(80, 5, 3, 0.8232000, 5, 1, 'Intermedio', '2025-06-20 06:09:23'),
(81, 4, 3, 0.3770000, 8, 0, 'Básico', '2025-06-20 06:09:23'),
(82, 3, 3, 0.3350000, 9, 0, 'Básico', '2025-06-20 06:09:23'),
(83, 6, 3, 0.2770000, 10, 0, 'Básico', '2025-06-20 06:09:23'),
(84, 10, 4, 0.8180000, 1, 0, 'Básico', '2025-06-20 06:09:23'),
(85, 9, 4, 0.7835000, 2, 0, 'Básico', '2025-06-20 06:09:23'),
(86, 2, 4, 0.7835000, 2, 0, 'Básico', '2025-06-20 06:09:23'),
(87, 5, 4, 0.7835000, 2, 0, 'Básico', '2025-06-20 06:09:23'),
(88, 8, 4, 0.7700000, 5, 0, 'Básico', '2025-06-20 06:09:23'),
(89, 1, 4, 0.7700000, 5, 0, 'Básico', '2025-06-20 06:09:23'),
(90, 7, 4, 0.7590000, 7, 0, 'Básico', '2025-06-20 06:09:23'),
(91, 4, 4, 0.5260000, 8, 0, 'Básico', '2025-06-20 06:09:23'),
(92, 3, 4, 0.4720000, 9, 0, 'Básico', '2025-06-20 06:09:23'),
(93, 6, 4, 0.4100000, 10, 0, 'Básico', '2025-06-20 06:09:23'),
(94, 10, 2, 0.8650000, 1, 0, 'Básico', '2025-06-20 06:09:23'),
(95, 9, 2, 0.7900000, 2, 0, 'Básico', '2025-06-20 06:09:23'),
(96, 2, 2, 0.7900000, 2, 0, 'Básico', '2025-06-20 06:09:23'),
(97, 5, 2, 0.7900000, 2, 0, 'Básico', '2025-06-20 06:09:23'),
(98, 7, 2, 0.7480000, 5, 0, 'Básico', '2025-06-20 06:09:23'),
(99, 8, 2, 0.5730000, 6, 0, 'Básico', '2025-06-20 06:09:23'),
(100, 1, 2, 0.5730000, 6, 0, 'Básico', '2025-06-20 06:09:23'),
(101, 3, 2, 0.5400000, 8, 0, 'Básico', '2025-06-20 06:09:23'),
(102, 4, 2, 0.4440000, 9, 0, 'Básico', '2025-06-20 06:09:23'),
(103, 6, 2, 0.4020000, 10, 0, 'Básico', '2025-06-20 06:09:23'),
(104, 10, 5, 0.8770000, 1, 0, 'Básico', '2025-06-20 06:09:23'),
(105, 9, 5, 0.8440000, 2, 0, 'Básico', '2025-06-20 06:09:23'),
(106, 2, 5, 0.8440000, 2, 0, 'Básico', '2025-06-20 06:09:23'),
(107, 5, 5, 0.8440000, 2, 0, 'Básico', '2025-06-20 06:09:23'),
(108, 7, 5, 0.8160000, 5, 0, 'Básico', '2025-06-20 06:09:23'),
(109, 8, 5, 0.7570000, 6, 0, 'Básico', '2025-06-20 06:09:23'),
(110, 1, 5, 0.7570000, 6, 0, 'Básico', '2025-06-20 06:09:23'),
(111, 3, 5, 0.3730000, 8, 0, 'Básico', '2025-06-20 06:09:23'),
(112, 4, 5, 0.3550000, 9, 0, 'Básico', '2025-06-20 06:09:23'),
(113, 6, 5, 0.3090000, 10, 0, 'Básico', '2025-06-20 06:09:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `capacitaciones_nee`
--

CREATE TABLE `capacitaciones_nee` (
  `id_capacitacion` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `nombre_capacitacion` varchar(255) NOT NULL,
  `institucion` varchar(255) DEFAULT NULL,
  `fecha_capacitacion` date DEFAULT NULL,
  `duracion_horas` int(11) DEFAULT NULL,
  `certificado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `capacitaciones_nee`
--

INSERT INTO `capacitaciones_nee` (`id_capacitacion`, `id_docente`, `nombre_capacitacion`, `institucion`, `fecha_capacitacion`, `duracion_horas`, `certificado`) VALUES
(1, 1, 'Metodologías inclusivas', 'Universidad Estatal', '2023-06-15', 40, 1),
(2, 1, 'Educación Especial Avanzada', 'MINEDUC', '2023-03-20', 60, 1),
(3, 2, 'Inclusión en el aula de clases', 'Universidad Estatal', '2023-01-15', 35, 1),
(4, 5, 'Metodologías inclusivas', 'Universidad Estatal', '2023-04-10', 40, 1),
(5, 7, 'Educación Especial Integral', 'Universidad Central', '2023-02-28', 80, 1),
(6, 8, 'Metodologías Inclusivas Avanzadas', 'Universidad Estatal', '2023-05-20', 60, 1),
(7, 8, 'Diseño Universal de Aprendizaje', 'CONADIS', '2023-01-10', 45, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `criterios_ahp`
--

CREATE TABLE `criterios_ahp` (
  `id_criterio` int(11) NOT NULL,
  `nombre_criterio` varchar(255) NOT NULL,
  `codigo_criterio` varchar(10) NOT NULL,
  `peso_criterio` decimal(5,3) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `criterios_ahp`
--

INSERT INTO `criterios_ahp` (`id_criterio`, `nombre_criterio`, `codigo_criterio`, `peso_criterio`, `descripcion`, `created_at`) VALUES
(1, 'Formación Específica en Inclusión', 'FSI', 0.280, 'Capacitaciones y formación específica en NEE', '2025-06-20 06:06:57'),
(2, 'Experiencia Práctica con NEE', 'EPR', 0.320, 'Años de experiencia trabajando con estudiantes NEE - CRITERIO MÁS IMPORTANTE', '2025-06-20 06:06:57'),
(3, 'Adaptaciones Metodológicas Implementadas', 'AMI', 0.160, 'Modificaciones realizadas en la metodología de enseñanza', '2025-06-20 06:06:57'),
(4, 'Años de Experiencia Docente General', 'AED', 0.130, 'Experiencia total como docente', '2025-06-20 06:06:57'),
(5, 'Nivel de Formación Académica', 'NFA', 0.110, 'Títulos de tercer y cuarto nivel', '2025-06-20 06:06:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `docentes`
--

CREATE TABLE `docentes` (
  `id_docente` int(11) NOT NULL,
  `nombres_completos` varchar(255) NOT NULL,
  `facultad` varchar(255) NOT NULL,
  `modalidad_enseñanza` enum('Presencial','Virtual','Híbrida') NOT NULL,
  `años_experiencia_docente` enum('Menos de 1 año','1 a 5 años','6 a 10 años','Más de 10 años') NOT NULL,
  `titulo_tercer_nivel` varchar(255) NOT NULL,
  `titulo_cuarto_nivel` varchar(255) DEFAULT NULL,
  `formacion_inclusion` tinyint(1) DEFAULT 0,
  `estudiantes_nee_promedio` enum('Ninguno','1 a 5 estudiantes','6 a 10 estudiantes','Más de 10 estudiantes') DEFAULT 'Ninguno',
  `capacitaciones_nee` int(11) DEFAULT 0,
  `experiencia_nee_años` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `docentes`
--

INSERT INTO `docentes` (`id_docente`, `nombres_completos`, `facultad`, `modalidad_enseñanza`, `años_experiencia_docente`, `titulo_tercer_nivel`, `titulo_cuarto_nivel`, `formacion_inclusion`, `estudiantes_nee_promedio`, `capacitaciones_nee`, `experiencia_nee_años`, `created_at`, `updated_at`) VALUES
(1, 'JACOME MORALES GLADYS CRISTINA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Ingeniero en Computación', 'Doctor en Ciencias Pedagógicas', 1, '1 a 5 estudiantes', 5, 1, '2025-06-20 06:06:58', '2025-06-20 06:06:58'),
(2, 'ALFONSO ANÍBAL GUIJARRO RODRÍGUEZ', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero sistemas computacionales', 'Master en ciberseguridad, Master en administración de empresas', 1, '1 a 5 estudiantes', 3, 6, '2025-06-20 06:06:58', '2025-06-20 06:06:58'),
(3, 'Edison Luis Cruz Navarrete', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniero en software', NULL, 0, '1 a 5 estudiantes', 0, 4, '2025-06-20 06:06:58', '2025-06-20 06:06:58'),
(4, 'Tatiana Mabel Alcivar Maldonado', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Ing en contabilidad y Auditoría', 'Master en administración de empresas', 0, '1 a 5 estudiantes', 1, 2, '2025-06-20 06:06:58', '2025-06-20 06:06:58'),
(5, 'Alex Roberto Collantes Farah', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero en Sistemas Computacionales', 'Master en administración de empresas', 1, '6 a 10 estudiantes', 4, 7, '2025-06-20 06:06:58', '2025-06-20 06:06:58'),
(6, 'Myriam Garcia', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniero en Computación', NULL, 0, '1 a 5 estudiantes', 0, 1, '2025-06-20 06:06:58', '2025-06-20 06:06:58'),
(7, 'Carlos Mendoza Silva', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniero en Software', 'Master en Educación Especial', 1, '1 a 5 estudiantes', 6, 5, '2025-06-20 06:06:58', '2025-06-20 06:06:58'),
(8, 'Ana Patricia Loor Cedeño', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '1 a 5 años', 'Ingeniero en Sistemas', 'Doctor en Educación Inclusiva', 1, '1 a 5 estudiantes', 8, 1, '2025-06-20 06:06:58', '2025-06-20 06:06:58'),
(9, 'MARÍA FERNANDA CASTRO MORALES', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Licenciada en Psicología Educativa', 'Master en Educación Especial y Inclusiva', 1, '6 a 10 estudiantes', 4, 6, '2025-06-20 06:06:58', '2025-06-20 06:06:58'),
(10, 'Wilson Gilces Tutiven', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', 'Más de 10 años', 'Ingeniero en Sistemas Computacionales', 'Master en administración de empresas', 1, '6 a 10 estudiantes', 30, 20, '2025-06-20 06:06:58', '2025-06-20 06:06:58');

--
-- Disparadores `docentes`
--
DELIMITER $$
CREATE TRIGGER `trigger_actualizar_docente` AFTER UPDATE ON `docentes` FOR EACH ROW BEGIN
    DECLARE cambio_formacion BOOLEAN DEFAULT FALSE;
    DECLARE cambio_experiencia BOOLEAN DEFAULT FALSE;
    DECLARE cambio_capacitaciones BOOLEAN DEFAULT FALSE;
    
    -- Detectar cambios significativos
    IF OLD.formacion_inclusion != NEW.formacion_inclusion THEN
        SET cambio_formacion = TRUE;
    END IF;
    
    IF OLD.experiencia_nee_años != NEW.experiencia_nee_años THEN
        SET cambio_experiencia = TRUE;
    END IF;
    
    IF OLD.capacitaciones_nee != NEW.capacitaciones_nee THEN
        SET cambio_capacitaciones = TRUE;
    END IF;
    
    -- Solo actualizar si hay cambios relevantes
    IF cambio_formacion OR cambio_experiencia OR cambio_capacitaciones THEN
        
        -- Actualizar adaptaciones metodológicas
        UPDATE adaptaciones_metodologicas 
        SET 
            modificacion_contenido = CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE modificacion_contenido END,
            uso_recursos_tecnologicos = CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE uso_recursos_tecnologicos END,
            adaptacion_metodologia = CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE adaptacion_metodologia END,
            coordinacion_servicios_apoyo = CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE coordinacion_servicios_apoyo END,
            otras_adaptaciones = CASE 
                WHEN NEW.formacion_inclusion = 1 AND OLD.formacion_inclusion = 0 THEN 
                    CONCAT(COALESCE(otras_adaptaciones, ''), ' | Adaptaciones automáticas activadas - ', NOW())
                ELSE otras_adaptaciones
            END
        WHERE id_docente = NEW.id_docente;
        
        -- Actualizar límites de asignación
        UPDATE limites_asignacion 
        SET 
            maximo_estudiantes_nee = CASE 
                WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años >= 5 THEN 7
                WHEN NEW.formacion_inclusion = 1 THEN 5
                WHEN NEW.experiencia_nee_años >= 3 THEN 4
                ELSE 3 
            END,
            maximo_por_tipo_discapacidad = CASE 
                WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años >= 5 THEN 3
                WHEN NEW.formacion_inclusion = 1 THEN 3
                ELSE 2 
            END,
            observaciones = CONCAT(
                COALESCE(observaciones, ''), 
                ' | Actualizado automáticamente: formación=', NEW.formacion_inclusion, 
                ', experiencia=', NEW.experiencia_nee_años, ' años - ', NOW()
            )
        WHERE id_docente = NEW.id_docente;
        
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trigger_cache_actualizar_docente` AFTER UPDATE ON `docentes` FOR EACH ROW BEGIN
    IF (OLD.formacion_inclusion != NEW.formacion_inclusion OR
        OLD.experiencia_nee_años != NEW.experiencia_nee_años OR
        OLD.capacitaciones_nee != NEW.capacitaciones_nee OR
        OLD.años_experiencia_docente != NEW.años_experiencia_docente OR
        OLD.titulo_cuarto_nivel != NEW.titulo_cuarto_nivel) THEN
        
        INSERT INTO log_actualizaciones_cache (
            tabla_afectada, id_registro, tipo_operacion, campos_modificados
        ) VALUES (
            'docentes', NEW.id_docente, 'UPDATE',
            CONCAT('Docente modificado: ', NEW.nombres_completos)
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trigger_cache_nuevo_docente` AFTER INSERT ON `docentes` FOR EACH ROW BEGIN
    INSERT INTO log_actualizaciones_cache (
        tabla_afectada, id_registro, tipo_operacion, campos_modificados
    ) VALUES (
        'docentes', NEW.id_docente, 'INSERT',
        CONCAT('Nuevo docente insertado: ', NEW.nombres_completos)
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trigger_nuevo_docente` AFTER INSERT ON `docentes` FOR EACH ROW BEGIN
    -- Insertar adaptaciones metodológicas automáticamente
    INSERT INTO adaptaciones_metodologicas (
        id_docente, 
        modificacion_contenido, 
        uso_recursos_tecnologicos, 
        adaptacion_metodologia, 
        coordinacion_servicios_apoyo,
        otras_adaptaciones
    ) VALUES (
        NEW.id_docente, 
        CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE 0 END,
        CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE 0 END,
        CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE 0 END,
        CASE WHEN NEW.formacion_inclusion = 1 THEN 1 ELSE 0 END,
        CASE WHEN NEW.formacion_inclusion = 1 
             THEN 'Adaptaciones automáticas por formación en inclusión' 
             ELSE 'Sin adaptaciones específicas - actualizar según corresponda' 
        END
    );
    
    -- Insertar experiencia por defecto para cada tipo de discapacidad
    INSERT INTO experiencia_docente_discapacidad (
        id_docente, 
        id_tipo_discapacidad, 
        tiene_experiencia, 
        años_experiencia, 
        nivel_competencia,
        observaciones
    ) VALUES 
    -- Psicosocial (ID=1)
    (NEW.id_docente, 1, 
     CASE WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años > 0 THEN 1 ELSE 0 END,
     CASE WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años > 0 THEN LEAST(NEW.experiencia_nee_años, 3) ELSE 0 END,
     CASE WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años >= 5 THEN 'Avanzado'
          WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años > 0 THEN 'Intermedio'
          ELSE 'Básico' END,
     'Generado automáticamente por trigger'),
     
    -- Auditiva (ID=2)
    (NEW.id_docente, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
    
    -- Intelectual (ID=3)
    (NEW.id_docente, 3,
     CASE WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años > 0 THEN 1 ELSE 0 END,
     CASE WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años > 0 THEN LEAST(NEW.experiencia_nee_años, 2) ELSE 0 END,
     CASE WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años >= 3 THEN 'Intermedio'
          ELSE 'Básico' END,
     'Generado automáticamente por trigger'),
     
    -- Visual (ID=4)
    (NEW.id_docente, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
    
    -- Física (ID=5)
    (NEW.id_docente, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger');
    
    -- Insertar límites de asignación automáticamente
    INSERT INTO limites_asignacion (
        id_docente, 
        maximo_estudiantes_nee, 
        maximo_por_tipo_discapacidad, 
        observaciones
    ) VALUES (
        NEW.id_docente,
        CASE 
            WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años >= 5 THEN 7
            WHEN NEW.formacion_inclusion = 1 THEN 5
            WHEN NEW.experiencia_nee_años >= 3 THEN 4
            ELSE 3 
        END,
        CASE 
            WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años >= 5 THEN 3
            WHEN NEW.formacion_inclusion = 1 THEN 3
            ELSE 2 
        END,
        CONCAT('Límites automáticos basados en perfil: formación=', 
               NEW.formacion_inclusion, ', experiencia=', NEW.experiencia_nee_años, ' años')
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id_estudiante` int(11) NOT NULL,
  `nombres_completos` varchar(255) NOT NULL,
  `id_tipo_discapacidad` int(11) NOT NULL,
  `ciclo_academico` varchar(20) NOT NULL,
  `facultad` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `estudiantes`
--

INSERT INTO `estudiantes` (`id_estudiante`, `nombres_completos`, `id_tipo_discapacidad`, `ciclo_academico`, `facultad`, `created_at`) VALUES
(1, 'Ana María López García', 1, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(2, 'Carlos Andrés Pérez Rojas', 2, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(3, 'Sofía Alejandra Gómez Martínez', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(4, 'Juan Pablo Morales Vargas', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(5, 'Lucía Fernanda Torres Cruz', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(6, 'Diego Armando Sánchez Díaz', 1, '2025-1', 'FACULTAD DE CIENCIAS SOCIALES Y HUMANAS', '2025-06-20 06:06:58'),
(7, 'Valeria Isabel Ramírez Ortiz', 2, '2025-1', 'FACULTAD DE CIENCIAS SOCIALES Y HUMANAS', '2025-06-20 06:06:58'),
(8, 'Miguel Ángel Castro Paredes', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(9, 'Camila Estefanía Ruiz Salazar', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(10, 'Andrés Felipe Mendoza López', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(11, 'Laura Daniela Chávez Morales', 1, '2025-1', 'FACULTAD DE INGENIERÍA', '2025-06-20 06:06:58'),
(12, 'Gabriel Esteban Flores Gómez', 2, '2025-1', 'FACULTAD DE INGENIERÍA', '2025-06-20 06:06:58'),
(13, 'Mariana Sofía Herrera Vásquez', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(14, 'Sebastián Alejandro Ortiz Peña', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(15, 'Paula Valentina Rojas Castro', 5, '2025-1', 'FACULTAD DE CIENCIAS SOCIALES Y HUMANAS', '2025-06-20 06:06:58'),
(16, 'Julián David Vargas Sánchez', 1, '2025-1', 'FACULTAD DE CIENCIAS SOCIALES Y HUMANAS', '2025-06-20 06:06:58'),
(17, 'Catalina María Díaz Torres', 2, '2025-1', 'FACULTAD DE INGENIERÍA', '2025-06-20 06:06:58'),
(18, 'Felipe Nicolás Martínez Cruz', 3, '2025-1', 'FACULTAD DE INGENIERÍA', '2025-06-20 06:06:58'),
(19, 'Isabella Fernanda Salazar Ramírez', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(20, 'Tomás Ignacio Paredes Gómez', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `experiencia_docente_discapacidad`
--

CREATE TABLE `experiencia_docente_discapacidad` (
  `id_experiencia` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_tipo_discapacidad` int(11) NOT NULL,
  `tiene_experiencia` tinyint(1) DEFAULT 0,
  `años_experiencia` int(11) DEFAULT 0,
  `nivel_competencia` enum('Básico','Intermedio','Avanzado','Experto') DEFAULT 'Básico',
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `experiencia_docente_discapacidad`
--

INSERT INTO `experiencia_docente_discapacidad` (`id_experiencia`, `id_docente`, `id_tipo_discapacidad`, `tiene_experiencia`, `años_experiencia`, `nivel_competencia`, `observaciones`) VALUES
(1, 1, 1, 1, 5, 'Avanzado', 'Generado automáticamente en creación de BD'),
(2, 1, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(3, 1, 3, 1, 6, 'Experto', 'Generado automáticamente en creación de BD'),
(4, 1, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(5, 1, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(6, 2, 1, 1, 3, 'Avanzado', 'Generado automáticamente en creación de BD'),
(7, 2, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(8, 2, 3, 1, 2, 'Intermedio', 'Generado automáticamente en creación de BD'),
(9, 2, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(10, 2, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(11, 3, 1, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(12, 3, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(13, 3, 3, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(14, 3, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(15, 3, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(16, 4, 1, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(17, 4, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(18, 4, 3, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(19, 4, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(20, 4, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(21, 5, 1, 1, 3, 'Avanzado', 'Generado automáticamente en creación de BD'),
(22, 5, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(23, 5, 3, 1, 2, 'Intermedio', 'Generado automáticamente en creación de BD'),
(24, 5, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(25, 5, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(26, 6, 1, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(27, 6, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(28, 6, 3, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(29, 6, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(30, 6, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(31, 7, 1, 1, 3, 'Avanzado', 'Generado automáticamente en creación de BD'),
(32, 7, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(33, 7, 3, 1, 5, 'Experto', 'Generado automáticamente en creación de BD'),
(34, 7, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(35, 7, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(36, 8, 1, 1, 7, 'Experto', 'Generado automáticamente en creación de BD'),
(37, 8, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(38, 8, 3, 1, 8, 'Experto', 'Generado automáticamente en creación de BD'),
(39, 8, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(40, 8, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(41, 9, 1, 1, 3, 'Avanzado', 'Generado automáticamente en creación de BD'),
(42, 9, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(43, 9, 3, 1, 2, 'Intermedio', 'Generado automáticamente en creación de BD'),
(44, 9, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(45, 9, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(46, 10, 1, 1, 5, 'Experto', 'Ajustado - Experiencia excepcional de 20 años en NEE'),
(47, 10, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(48, 10, 3, 1, 5, 'Experto', 'Ajustado - Experiencia excepcional de 20 años en NEE'),
(49, 10, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(50, 10, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD');

--
-- Disparadores `experiencia_docente_discapacidad`
--
DELIMITER $$
CREATE TRIGGER `trigger_cache_actualizar_experiencia` AFTER UPDATE ON `experiencia_docente_discapacidad` FOR EACH ROW BEGIN
    INSERT INTO log_actualizaciones_cache (
        tabla_afectada, id_registro, tipo_operacion, campos_modificados
    ) VALUES (
        'experiencia_docente_discapacidad', NEW.id_docente, 'UPDATE',
        CONCAT('Experiencia modificada para discapacidad ID: ', NEW.id_tipo_discapacidad)
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `limites_asignacion`
--

CREATE TABLE `limites_asignacion` (
  `id_limite` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `maximo_estudiantes_nee` int(11) NOT NULL DEFAULT 5,
  `maximo_por_tipo_discapacidad` int(11) NOT NULL DEFAULT 3,
  `disponible_ciclo` varchar(20) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `limites_asignacion`
--

INSERT INTO `limites_asignacion` (`id_limite`, `id_docente`, `maximo_estudiantes_nee`, `maximo_por_tipo_discapacidad`, `disponible_ciclo`, `observaciones`, `created_at`) VALUES
(1, 1, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=1 años', '2025-06-20 06:07:00'),
(2, 2, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=6 años', '2025-06-20 06:07:00'),
(3, 3, 4, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=4 años', '2025-06-20 06:07:00'),
(4, 4, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-06-20 06:07:00'),
(5, 5, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=7 años', '2025-06-20 06:07:00'),
(6, 6, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-06-20 06:07:00'),
(7, 7, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-06-20 06:07:00'),
(8, 8, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=1 años', '2025-06-20 06:07:00'),
(9, 9, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=6 años', '2025-06-20 06:07:00'),
(10, 10, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=20 años', '2025-06-20 06:07:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `log_actualizaciones_cache`
--

CREATE TABLE `log_actualizaciones_cache` (
  `id_log` int(11) NOT NULL,
  `tabla_afectada` varchar(100) NOT NULL,
  `id_registro` int(11) NOT NULL,
  `tipo_operacion` enum('INSERT','UPDATE','DELETE') NOT NULL,
  `campos_modificados` text DEFAULT NULL,
  `usuario` varchar(100) DEFAULT user(),
  `fecha_operacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `log_actualizaciones_cache`
--

INSERT INTO `log_actualizaciones_cache` (`id_log`, `tabla_afectada`, `id_registro`, `tipo_operacion`, `campos_modificados`, `usuario`, `fecha_operacion`) VALUES
(1, 'experiencia_docente_discapacidad', 1, 'UPDATE', 'Experiencia modificada para discapacidad ID: 1', 'root@localhost', '2025-06-20 06:07:00'),
(2, 'experiencia_docente_discapacidad', 1, 'UPDATE', 'Experiencia modificada para discapacidad ID: 3', 'root@localhost', '2025-06-20 06:07:00'),
(3, 'experiencia_docente_discapacidad', 7, 'UPDATE', 'Experiencia modificada para discapacidad ID: 3', 'root@localhost', '2025-06-20 06:07:00'),
(4, 'experiencia_docente_discapacidad', 8, 'UPDATE', 'Experiencia modificada para discapacidad ID: 1', 'root@localhost', '2025-06-20 06:07:00'),
(5, 'experiencia_docente_discapacidad', 8, 'UPDATE', 'Experiencia modificada para discapacidad ID: 3', 'root@localhost', '2025-06-20 06:07:00'),
(6, 'cache_completo', 0, 'UPDATE', 'Recálculo completo de caché ejecutado', 'root@localhost', '2025-06-20 06:07:00'),
(7, 'experiencia_docente_discapacidad', 10, 'UPDATE', 'Experiencia modificada para discapacidad ID: 1', 'root@localhost', '2025-06-20 06:09:23'),
(8, 'experiencia_docente_discapacidad', 10, 'UPDATE', 'Experiencia modificada para discapacidad ID: 3', 'root@localhost', '2025-06-20 06:09:23'),
(9, 'cache_completo', 0, 'UPDATE', 'Recálculo completo de caché ejecutado', 'root@localhost', '2025-06-20 06:09:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `id_materia` int(11) NOT NULL,
  `nombre_materia` varchar(255) NOT NULL,
  `facultad` varchar(255) NOT NULL,
  `ciclo_academico` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`id_materia`, `nombre_materia`, `facultad`, `ciclo_academico`, `created_at`) VALUES
(1, 'Matemáticas Discretas', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-20 06:06:58'),
(2, 'Programación I', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-20 06:06:58'),
(3, 'Física I', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-20 06:06:58'),
(4, 'Cálculo Diferencial', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-20 06:06:58'),
(5, 'Álgebra Lineal', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-20 06:06:58'),
(6, 'Base de Datos', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-20 06:06:58'),
(7, 'Estadística', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-20 06:06:58'),
(8, 'Química', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-20 06:06:58'),
(9, 'Programación II', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-20 06:06:58'),
(10, 'Cálculo Integral', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-20 06:06:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pesos_criterios_discapacidad`
--

CREATE TABLE `pesos_criterios_discapacidad` (
  `id_peso` int(11) NOT NULL,
  `id_tipo_discapacidad` int(11) NOT NULL,
  `id_criterio` int(11) NOT NULL,
  `peso_especifico` decimal(5,3) NOT NULL,
  `descripcion_peso` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pesos_criterios_discapacidad`
--

INSERT INTO `pesos_criterios_discapacidad` (`id_peso`, `id_tipo_discapacidad`, `id_criterio`, `peso_especifico`, `descripcion_peso`, `created_at`) VALUES
(1, 1, 1, 0.260, 'FSI para Psicosocial: 26%', '2025-06-20 06:06:57'),
(2, 1, 2, 0.500, 'EPR para Psicosocial: 50% - CRÍTICO para manejo emocional', '2025-06-20 06:06:57'),
(3, 1, 3, 0.130, 'AMI para Psicosocial: 13%', '2025-06-20 06:06:57'),
(4, 1, 4, 0.070, 'AED para Psicosocial: 7%', '2025-06-20 06:06:57'),
(5, 1, 5, 0.030, 'NFA para Psicosocial: 3%', '2025-06-20 06:06:57'),
(6, 2, 1, 0.080, 'FSI para Auditiva: 8%', '2025-06-20 06:06:57'),
(7, 2, 2, 0.420, 'EPR para Auditiva: 42% - Experiencia práctica fundamental', '2025-06-20 06:06:57'),
(8, 2, 3, 0.090, 'AMI para Auditiva: 9%', '2025-06-20 06:06:57'),
(9, 2, 4, 0.270, 'AED para Auditiva: 27%', '2025-06-20 06:06:57'),
(10, 2, 5, 0.130, 'NFA para Auditiva: 13%', '2025-06-20 06:06:57'),
(11, 3, 1, 0.460, 'FSI para Intelectual: 46% - Formación especializada fundamental', '2025-06-20 06:06:57'),
(12, 3, 2, 0.200, 'EPR para Intelectual: 20%', '2025-06-20 06:06:57'),
(13, 3, 3, 0.200, 'AMI para Intelectual: 20% - Adaptaciones curriculares necesarias', '2025-06-20 06:06:57'),
(14, 3, 4, 0.090, 'AED para Intelectual: 9%', '2025-06-20 06:06:57'),
(15, 3, 5, 0.040, 'NFA para Intelectual: 4%', '2025-06-20 06:06:57'),
(16, 4, 1, 0.170, 'FSI para Visual: 17%', '2025-06-20 06:06:57'),
(17, 4, 2, 0.060, 'EPR para Visual: 6%', '2025-06-20 06:06:57'),
(18, 4, 3, 0.110, 'AMI para Visual: 11%', '2025-06-20 06:06:57'),
(19, 4, 4, 0.250, 'AED para Visual: 25%', '2025-06-20 06:06:57'),
(20, 4, 5, 0.410, 'NFA para Visual: 41% - Formación técnica crucial', '2025-06-20 06:06:57'),
(21, 5, 1, 0.160, 'FSI para Física: 16%', '2025-06-20 06:06:57'),
(22, 5, 2, 0.060, 'EPR para Física: 6%', '2025-06-20 06:06:57'),
(23, 5, 3, 0.440, 'AMI para Física: 44% - Adaptaciones metodológicas críticas', '2025-06-20 06:06:57'),
(24, 5, 4, 0.260, 'AED para Física: 26%', '2025-06-20 06:06:57'),
(25, 5, 5, 0.070, 'NFA para Física: 7%', '2025-06-20 06:06:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_discapacidad`
--

CREATE TABLE `tipos_discapacidad` (
  `id_tipo_discapacidad` int(11) NOT NULL,
  `nombre_discapacidad` varchar(100) NOT NULL,
  `peso_prioridad` decimal(5,3) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipos_discapacidad`
--

INSERT INTO `tipos_discapacidad` (`id_tipo_discapacidad`, `nombre_discapacidad`, `peso_prioridad`, `descripcion`, `created_at`) VALUES
(1, 'Psicosocial', 0.400, 'Discapacidad relacionada con aspectos psicológicos y sociales - MAYOR PRIORIDAD', '2025-06-20 06:06:57'),
(2, 'Auditiva', 0.100, 'Discapacidad relacionada con la audición', '2025-06-20 06:06:57'),
(3, 'Intelectual', 0.300, 'Discapacidad relacionada con el desarrollo intelectual - SEGUNDA PRIORIDAD', '2025-06-20 06:06:57'),
(4, 'Visual', 0.150, 'Discapacidad relacionada con la visión', '2025-06-20 06:06:57'),
(5, 'Física', 0.050, 'Discapacidad relacionada con la movilidad física - MENOR PRIORIDAD', '2025-06-20 06:06:57');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_distribucion_carga`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_distribucion_carga` (
`id_docente` int(11)
,`nombres_completos` varchar(255)
,`formacion_inclusion` tinyint(1)
,`experiencia_nee_años` int(11)
,`maximo_estudiantes_nee` int(11)
,`maximo_por_tipo_discapacidad` int(11)
,`asignaciones_actuales` bigint(21)
,`psicosocial_actual` bigint(21)
,`auditiva_actual` bigint(21)
,`intelectual_actual` bigint(21)
,`visual_actual` bigint(21)
,`fisica_actual` bigint(21)
,`capacidad_restante` bigint(22)
,`porcentaje_carga` decimal(25,1)
,`estado_carga` varchar(12)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_estadisticas_sistema`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_estadisticas_sistema` (
`metrica` varchar(36)
,`valor` decimal(26,1)
,`unidad` varchar(14)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_puntuaciones_ahp`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_puntuaciones_ahp` (
`id_docente` int(11)
,`nombres_completos` varchar(255)
,`facultad` varchar(255)
,`puntuacion_fsi` decimal(3,2)
,`puntuacion_epr` decimal(3,2)
,`puntuacion_ami` decimal(3,2)
,`puntuacion_aed` decimal(3,2)
,`puntuacion_nfa` decimal(3,2)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_ranking_ahp`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_ranking_ahp` (
`id_docente` int(11)
,`nombres_completos` varchar(255)
,`facultad` varchar(255)
,`puntuacion_fsi` decimal(3,2)
,`puntuacion_epr` decimal(3,2)
,`puntuacion_ami` decimal(3,2)
,`puntuacion_aed` decimal(3,2)
,`puntuacion_nfa` decimal(3,2)
,`puntuacion_final` decimal(11,5)
,`ranking` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_ranking_ahp_especifico`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_ranking_ahp_especifico` (
`id_docente` int(11)
,`nombres_completos` varchar(255)
,`facultad` varchar(255)
,`id_tipo_discapacidad` int(11)
,`nombre_discapacidad` varchar(100)
,`peso_discapacidad` decimal(5,3)
,`puntuacion_fsi` decimal(3,2)
,`puntuacion_epr` decimal(3,2)
,`puntuacion_ami` decimal(3,2)
,`puntuacion_aed` decimal(3,2)
,`puntuacion_nfa` decimal(3,2)
,`tiene_experiencia_especifica` int(4)
,`años_experiencia_especifica` int(11)
,`nivel_competencia_especifica` varchar(10)
,`puntuacion_especifica_discapacidad` decimal(15,7)
,`ranking_por_discapacidad` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_ranking_especifico_rapido`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_ranking_especifico_rapido` (
`id_docente` int(11)
,`nombres_completos` varchar(255)
,`facultad` varchar(255)
,`nombre_discapacidad` varchar(100)
,`puntuacion_especifica` decimal(15,7)
,`ranking_especifico` int(11)
,`tiene_experiencia_especifica` tinyint(1)
,`nivel_competencia_especifica` varchar(20)
,`fecha_calculo` timestamp
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vista_ranking_rapido`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vista_ranking_rapido` (
`id_docente` int(11)
,`nombres_completos` varchar(255)
,`facultad` varchar(255)
,`puntuacion_fsi` decimal(3,2)
,`puntuacion_epr` decimal(3,2)
,`puntuacion_ami` decimal(3,2)
,`puntuacion_aed` decimal(3,2)
,`puntuacion_nfa` decimal(3,2)
,`puntuacion_final` decimal(11,5)
,`ranking_general` int(11)
,`fecha_calculo` timestamp
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_distribucion_carga`
--
DROP TABLE IF EXISTS `vista_distribucion_carga`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_distribucion_carga`  AS SELECT `d`.`id_docente` AS `id_docente`, `d`.`nombres_completos` AS `nombres_completos`, `d`.`formacion_inclusion` AS `formacion_inclusion`, `d`.`experiencia_nee_años` AS `experiencia_nee_años`, `la`.`maximo_estudiantes_nee` AS `maximo_estudiantes_nee`, `la`.`maximo_por_tipo_discapacidad` AS `maximo_por_tipo_discapacidad`, count(`a`.`id_asignacion`) AS `asignaciones_actuales`, count(case when `a`.`id_tipo_discapacidad` = 1 then 1 end) AS `psicosocial_actual`, count(case when `a`.`id_tipo_discapacidad` = 2 then 1 end) AS `auditiva_actual`, count(case when `a`.`id_tipo_discapacidad` = 3 then 1 end) AS `intelectual_actual`, count(case when `a`.`id_tipo_discapacidad` = 4 then 1 end) AS `visual_actual`, count(case when `a`.`id_tipo_discapacidad` = 5 then 1 end) AS `fisica_actual`, `la`.`maximo_estudiantes_nee`- count(`a`.`id_asignacion`) AS `capacidad_restante`, round(count(`a`.`id_asignacion`) / `la`.`maximo_estudiantes_nee` * 100,1) AS `porcentaje_carga`, CASE WHEN count(`a`.`id_asignacion`) >= `la`.`maximo_estudiantes_nee` THEN 'SOBRECARGADO' WHEN count(`a`.`id_asignacion`) >= `la`.`maximo_estudiantes_nee` * 0.8 THEN 'ALTA CARGA' WHEN count(`a`.`id_asignacion`) >= `la`.`maximo_estudiantes_nee` * 0.5 THEN 'CARGA MEDIA' WHEN count(`a`.`id_asignacion`) > 0 THEN 'CARGA BAJA' ELSE 'SIN CARGA' END AS `estado_carga` FROM ((`docentes` `d` left join `limites_asignacion` `la` on(`d`.`id_docente` = `la`.`id_docente`)) left join `asignaciones` `a` on(`d`.`id_docente` = `a`.`id_docente` and `a`.`estado` = 'Activa')) GROUP BY `d`.`id_docente`, `d`.`nombres_completos`, `d`.`formacion_inclusion`, `d`.`experiencia_nee_años`, `la`.`maximo_estudiantes_nee`, `la`.`maximo_por_tipo_discapacidad` ORDER BY round(count(`a`.`id_asignacion`) / `la`.`maximo_estudiantes_nee` * 100,1) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_estadisticas_sistema`
--
DROP TABLE IF EXISTS `vista_estadisticas_sistema`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_estadisticas_sistema`  AS SELECT 'Docentes Totales' AS `metrica`, count(0) AS `valor`, 'personas' AS `unidad` FROM `docentes`union all select 'Estudiantes NEE Totales' AS `metrica`,count(0) AS `valor`,'personas' AS `unidad` from `estudiantes` union all select 'Docentes con Formación en Inclusión' AS `metrica`,sum(`docentes`.`formacion_inclusion`) AS `valor`,'personas' AS `unidad` from `docentes` union all select 'Promedio Años Experiencia NEE' AS `metrica`,round(avg(`docentes`.`experiencia_nee_años`),1) AS `valor`,'años' AS `unidad` from `docentes` union all select 'Total Capacitaciones Registradas' AS `metrica`,count(0) AS `valor`,'capacitaciones' AS `unidad` from `capacitaciones_nee` union all select 'Docentes Ranking #1 por Discapacidad' AS `metrica`,count(0) AS `valor`,'especialidades' AS `unidad` from `cache_puntuaciones_especificas` where `cache_puntuaciones_especificas`.`ranking_especifico` = 1  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_puntuaciones_ahp`
--
DROP TABLE IF EXISTS `vista_puntuaciones_ahp`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_puntuaciones_ahp`  AS SELECT `d`.`id_docente` AS `id_docente`, `d`.`nombres_completos` AS `nombres_completos`, `d`.`facultad` AS `facultad`, CASE WHEN `d`.`formacion_inclusion` = 1 AND `d`.`capacitaciones_nee` >= 5 THEN 0.90 WHEN `d`.`formacion_inclusion` = 1 AND `d`.`capacitaciones_nee` >= 3 THEN 0.75 WHEN `d`.`formacion_inclusion` = 1 AND `d`.`capacitaciones_nee` >= 1 THEN 0.60 WHEN `d`.`capacitaciones_nee` >= 1 THEN 0.40 ELSE 0.20 END AS `puntuacion_fsi`, CASE WHEN `d`.`experiencia_nee_años` >= 8 THEN 0.90 WHEN `d`.`experiencia_nee_años` >= 5 THEN 0.75 WHEN `d`.`experiencia_nee_años` >= 3 THEN 0.60 WHEN `d`.`experiencia_nee_años` >= 1 THEN 0.40 ELSE 0.20 END AS `puntuacion_epr`, CASE WHEN `am`.`id_adaptacion` is null THEN CASE WHEN `d`.`formacion_inclusion` = 1 THEN 0.40 ELSE 0.20 END WHEN coalesce(`am`.`modificacion_contenido`,0) + coalesce(`am`.`uso_recursos_tecnologicos`,0) + coalesce(`am`.`adaptacion_metodologia`,0) + coalesce(`am`.`coordinacion_servicios_apoyo`,0) >= 4 THEN 0.90 WHEN coalesce(`am`.`modificacion_contenido`,0) + coalesce(`am`.`uso_recursos_tecnologicos`,0) + coalesce(`am`.`adaptacion_metodologia`,0) + coalesce(`am`.`coordinacion_servicios_apoyo`,0) >= 3 THEN 0.75 WHEN coalesce(`am`.`modificacion_contenido`,0) + coalesce(`am`.`uso_recursos_tecnologicos`,0) + coalesce(`am`.`adaptacion_metodologia`,0) + coalesce(`am`.`coordinacion_servicios_apoyo`,0) >= 2 THEN 0.60 WHEN coalesce(`am`.`modificacion_contenido`,0) + coalesce(`am`.`uso_recursos_tecnologicos`,0) + coalesce(`am`.`adaptacion_metodologia`,0) + coalesce(`am`.`coordinacion_servicios_apoyo`,0) >= 1 THEN 0.40 ELSE 0.20 END AS `puntuacion_ami`, CASE WHEN `d`.`años_experiencia_docente` = 'Más de 10 años' THEN 0.90 WHEN `d`.`años_experiencia_docente` = '6 a 10 años' THEN 0.70 WHEN `d`.`años_experiencia_docente` = '1 a 5 años' THEN 0.50 ELSE 0.30 END AS `puntuacion_aed`, CASE WHEN `d`.`titulo_cuarto_nivel` like '%Doctor%' OR `d`.`titulo_cuarto_nivel` like '%PhD%' THEN 0.90 WHEN `d`.`titulo_cuarto_nivel` like '%Master%' OR `d`.`titulo_cuarto_nivel` like '%Maestr%' THEN 0.70 ELSE 0.50 END AS `puntuacion_nfa` FROM (`docentes` `d` left join `adaptaciones_metodologicas` `am` on(`d`.`id_docente` = `am`.`id_docente`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_ranking_ahp`
--
DROP TABLE IF EXISTS `vista_ranking_ahp`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ranking_ahp`  AS SELECT `vp`.`id_docente` AS `id_docente`, `vp`.`nombres_completos` AS `nombres_completos`, `vp`.`facultad` AS `facultad`, `vp`.`puntuacion_fsi` AS `puntuacion_fsi`, `vp`.`puntuacion_epr` AS `puntuacion_epr`, `vp`.`puntuacion_ami` AS `puntuacion_ami`, `vp`.`puntuacion_aed` AS `puntuacion_aed`, `vp`.`puntuacion_nfa` AS `puntuacion_nfa`, `vp`.`puntuacion_fsi`* 0.280 + `vp`.`puntuacion_epr` * 0.320 + `vp`.`puntuacion_ami` * 0.160 + `vp`.`puntuacion_aed` * 0.130 + `vp`.`puntuacion_nfa` * 0.110 AS `puntuacion_final`, rank() over ( order by `vp`.`puntuacion_fsi` * 0.280 + `vp`.`puntuacion_epr` * 0.320 + `vp`.`puntuacion_ami` * 0.160 + `vp`.`puntuacion_aed` * 0.130 + `vp`.`puntuacion_nfa` * 0.110 desc) AS `ranking` FROM `vista_puntuaciones_ahp` AS `vp` ORDER BY `vp`.`puntuacion_fsi`* 0.280 + `vp`.`puntuacion_epr` * 0.320 + `vp`.`puntuacion_ami` * 0.160 + `vp`.`puntuacion_aed` * 0.130 + `vp`.`puntuacion_nfa` * 0.110 DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_ranking_ahp_especifico`
--
DROP TABLE IF EXISTS `vista_ranking_ahp_especifico`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ranking_ahp_especifico`  AS SELECT `d`.`id_docente` AS `id_docente`, `d`.`nombres_completos` AS `nombres_completos`, `d`.`facultad` AS `facultad`, `td`.`id_tipo_discapacidad` AS `id_tipo_discapacidad`, `td`.`nombre_discapacidad` AS `nombre_discapacidad`, `td`.`peso_prioridad` AS `peso_discapacidad`, `vp`.`puntuacion_fsi` AS `puntuacion_fsi`, `vp`.`puntuacion_epr` AS `puntuacion_epr`, `vp`.`puntuacion_ami` AS `puntuacion_ami`, `vp`.`puntuacion_aed` AS `puntuacion_aed`, `vp`.`puntuacion_nfa` AS `puntuacion_nfa`, coalesce(`edd`.`tiene_experiencia`,0) AS `tiene_experiencia_especifica`, coalesce(`edd`.`años_experiencia`,0) AS `años_experiencia_especifica`, coalesce(`edd`.`nivel_competencia`,'Básico') AS `nivel_competencia_especifica`, (`vp`.`puntuacion_fsi` * coalesce(`pcd_fsi`.`peso_especifico`,0) + `vp`.`puntuacion_epr` * coalesce(`pcd_epr`.`peso_especifico`,0) + `vp`.`puntuacion_ami` * coalesce(`pcd_ami`.`peso_especifico`,0) + `vp`.`puntuacion_aed` * coalesce(`pcd_aed`.`peso_especifico`,0) + `vp`.`puntuacion_nfa` * coalesce(`pcd_nfa`.`peso_especifico`,0)) * CASE WHEN coalesce(`edd`.`tiene_experiencia`,0) = 1 THEN CASE ENDcoalesce(`edd`.`nivel_competencia`,'Básico') WHEN 'Experto' THEN 1.15 WHEN 'Avanzado' THEN 1.10 WHEN 'Intermedio' THEN 1.05 ELSE 1.02 END END FROM ((((((((`vista_puntuaciones_ahp` `vp` join `tipos_discapacidad` `td`) join `docentes` `d` on(`vp`.`id_docente` = `d`.`id_docente`)) left join `experiencia_docente_discapacidad` `edd` on(`d`.`id_docente` = `edd`.`id_docente` and `td`.`id_tipo_discapacidad` = `edd`.`id_tipo_discapacidad`)) left join `pesos_criterios_discapacidad` `pcd_fsi` on(`td`.`id_tipo_discapacidad` = `pcd_fsi`.`id_tipo_discapacidad` and `pcd_fsi`.`id_criterio` = 1)) left join `pesos_criterios_discapacidad` `pcd_epr` on(`td`.`id_tipo_discapacidad` = `pcd_epr`.`id_tipo_discapacidad` and `pcd_epr`.`id_criterio` = 2)) left join `pesos_criterios_discapacidad` `pcd_ami` on(`td`.`id_tipo_discapacidad` = `pcd_ami`.`id_tipo_discapacidad` and `pcd_ami`.`id_criterio` = 3)) left join `pesos_criterios_discapacidad` `pcd_aed` on(`td`.`id_tipo_discapacidad` = `pcd_aed`.`id_tipo_discapacidad` and `pcd_aed`.`id_criterio` = 4)) left join `pesos_criterios_discapacidad` `pcd_nfa` on(`td`.`id_tipo_discapacidad` = `pcd_nfa`.`id_tipo_discapacidad` and `pcd_nfa`.`id_criterio` = 5)) ORDER BY (`vp`.`puntuacion_fsi` * coalesce(`pcd_fsi`.`peso_especifico`,0) + `vp`.`puntuacion_epr` * coalesce(`pcd_epr`.`peso_especifico`,0) + `vp`.`puntuacion_ami` * coalesce(`pcd_ami`.`peso_especifico`,0) + `vp`.`puntuacion_aed` * coalesce(`pcd_aed`.`peso_especifico`,0) + `vp`.`puntuacion_nfa` * coalesce(`pcd_nfa`.`peso_especifico`,0)) * CASE WHEN coalesce(`edd`.`tiene_experiencia`,0) = 1 THEN CASE ENDcoalesce(`edd`.`nivel_competencia`,'Básico') WHEN 'Experto' THEN 1.15 WHEN 'Avanzado' THEN 1.10 WHEN 'Intermedio' THEN 1.05 ELSE 1.02 END AS `ASCdesc` END AS `ranking_por_discapacidad` ASCorder by `td`.`peso_prioridad` desc,(`vp`.`puntuacion_fsi` * coalesce(`pcd_fsi`.`peso_especifico`,0) + `vp`.`puntuacion_epr` * coalesce(`pcd_epr`.`peso_especifico`,0) + `vp`.`puntuacion_ami` * coalesce(`pcd_ami`.`peso_especifico`,0) + `vp`.`puntuacion_aed` * coalesce(`pcd_aed`.`peso_especifico`,0) + `vp`.`puntuacion_nfa` * coalesce(`pcd_nfa`.`peso_especifico`,0)) * case when coalesce(`edd`.`tiene_experiencia`,0) = 1 then case coalesce(`edd`.`nivel_competencia`,'Básico') when 'Experto' then 1.15 when 'Avanzado' then 1.10 when 'Intermedio' then 1.05 else 1.02 end else 1.00 end desc  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_ranking_especifico_rapido`
--
DROP TABLE IF EXISTS `vista_ranking_especifico_rapido`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ranking_especifico_rapido`  AS SELECT `d`.`id_docente` AS `id_docente`, `d`.`nombres_completos` AS `nombres_completos`, `d`.`facultad` AS `facultad`, `td`.`nombre_discapacidad` AS `nombre_discapacidad`, `ce`.`puntuacion_especifica` AS `puntuacion_especifica`, `ce`.`ranking_especifico` AS `ranking_especifico`, `ce`.`tiene_experiencia_especifica` AS `tiene_experiencia_especifica`, `ce`.`nivel_competencia_especifica` AS `nivel_competencia_especifica`, `ce`.`fecha_calculo` AS `fecha_calculo` FROM ((`docentes` `d` join `cache_puntuaciones_especificas` `ce` on(`d`.`id_docente` = `ce`.`id_docente`)) join `tipos_discapacidad` `td` on(`ce`.`id_tipo_discapacidad` = `td`.`id_tipo_discapacidad`)) ORDER BY `td`.`peso_prioridad` DESC, `ce`.`ranking_especifico` ASC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vista_ranking_rapido`
--
DROP TABLE IF EXISTS `vista_ranking_rapido`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ranking_rapido`  AS SELECT `d`.`id_docente` AS `id_docente`, `d`.`nombres_completos` AS `nombres_completos`, `d`.`facultad` AS `facultad`, `c`.`puntuacion_fsi` AS `puntuacion_fsi`, `c`.`puntuacion_epr` AS `puntuacion_epr`, `c`.`puntuacion_ami` AS `puntuacion_ami`, `c`.`puntuacion_aed` AS `puntuacion_aed`, `c`.`puntuacion_nfa` AS `puntuacion_nfa`, `c`.`puntuacion_final` AS `puntuacion_final`, `c`.`ranking_general` AS `ranking_general`, `c`.`fecha_calculo` AS `fecha_calculo` FROM (`docentes` `d` join `cache_puntuaciones_ahp` `c` on(`d`.`id_docente` = `c`.`id_docente`)) ORDER BY `c`.`ranking_general` ASC ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `adaptaciones_metodologicas`
--
ALTER TABLE `adaptaciones_metodologicas`
  ADD PRIMARY KEY (`id_adaptacion`),
  ADD KEY `id_docente` (`id_docente`);

--
-- Indices de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD PRIMARY KEY (`id_asignacion`),
  ADD KEY `id_docente` (`id_docente`),
  ADD KEY `id_tipo_discapacidad` (`id_tipo_discapacidad`),
  ADD KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `id_materia` (`id_materia`),
  ADD KEY `idx_asignaciones_ciclo_estado` (`ciclo_academico`,`estado`);

--
-- Indices de la tabla `asignaciones_historial`
--
ALTER TABLE `asignaciones_historial`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_docente` (`id_docente`),
  ADD KEY `id_estudiante` (`id_estudiante`),
  ADD KEY `id_tipo_discapacidad` (`id_tipo_discapacidad`),
  ADD KEY `id_materia` (`id_materia`);

--
-- Indices de la tabla `cache_puntuaciones_ahp`
--
ALTER TABLE `cache_puntuaciones_ahp`
  ADD PRIMARY KEY (`id_cache`),
  ADD UNIQUE KEY `unique_docente` (`id_docente`);

--
-- Indices de la tabla `cache_puntuaciones_especificas`
--
ALTER TABLE `cache_puntuaciones_especificas`
  ADD PRIMARY KEY (`id_cache_especifico`),
  ADD UNIQUE KEY `unique_docente_discapacidad` (`id_docente`,`id_tipo_discapacidad`),
  ADD KEY `id_tipo_discapacidad` (`id_tipo_discapacidad`);

--
-- Indices de la tabla `capacitaciones_nee`
--
ALTER TABLE `capacitaciones_nee`
  ADD PRIMARY KEY (`id_capacitacion`),
  ADD KEY `id_docente` (`id_docente`);

--
-- Indices de la tabla `criterios_ahp`
--
ALTER TABLE `criterios_ahp`
  ADD PRIMARY KEY (`id_criterio`);

--
-- Indices de la tabla `docentes`
--
ALTER TABLE `docentes`
  ADD PRIMARY KEY (`id_docente`);

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id_estudiante`),
  ADD KEY `id_tipo_discapacidad` (`id_tipo_discapacidad`),
  ADD KEY `idx_estudiantes_ciclo_facultad` (`ciclo_academico`,`facultad`);

--
-- Indices de la tabla `experiencia_docente_discapacidad`
--
ALTER TABLE `experiencia_docente_discapacidad`
  ADD PRIMARY KEY (`id_experiencia`),
  ADD KEY `id_docente` (`id_docente`),
  ADD KEY `id_tipo_discapacidad` (`id_tipo_discapacidad`);

--
-- Indices de la tabla `limites_asignacion`
--
ALTER TABLE `limites_asignacion`
  ADD PRIMARY KEY (`id_limite`),
  ADD KEY `id_docente` (`id_docente`);

--
-- Indices de la tabla `log_actualizaciones_cache`
--
ALTER TABLE `log_actualizaciones_cache`
  ADD PRIMARY KEY (`id_log`);

--
-- Indices de la tabla `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id_materia`);

--
-- Indices de la tabla `pesos_criterios_discapacidad`
--
ALTER TABLE `pesos_criterios_discapacidad`
  ADD PRIMARY KEY (`id_peso`),
  ADD KEY `id_tipo_discapacidad` (`id_tipo_discapacidad`),
  ADD KEY `id_criterio` (`id_criterio`);

--
-- Indices de la tabla `tipos_discapacidad`
--
ALTER TABLE `tipos_discapacidad`
  ADD PRIMARY KEY (`id_tipo_discapacidad`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `adaptaciones_metodologicas`
--
ALTER TABLE `adaptaciones_metodologicas`
  MODIFY `id_adaptacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  MODIFY `id_asignacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `asignaciones_historial`
--
ALTER TABLE `asignaciones_historial`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cache_puntuaciones_ahp`
--
ALTER TABLE `cache_puntuaciones_ahp`
  MODIFY `id_cache` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `cache_puntuaciones_especificas`
--
ALTER TABLE `cache_puntuaciones_especificas`
  MODIFY `id_cache_especifico` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT de la tabla `capacitaciones_nee`
--
ALTER TABLE `capacitaciones_nee`
  MODIFY `id_capacitacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `criterios_ahp`
--
ALTER TABLE `criterios_ahp`
  MODIFY `id_criterio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `docentes`
--
ALTER TABLE `docentes`
  MODIFY `id_docente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `experiencia_docente_discapacidad`
--
ALTER TABLE `experiencia_docente_discapacidad`
  MODIFY `id_experiencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT de la tabla `limites_asignacion`
--
ALTER TABLE `limites_asignacion`
  MODIFY `id_limite` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `log_actualizaciones_cache`
--
ALTER TABLE `log_actualizaciones_cache`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id_materia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `pesos_criterios_discapacidad`
--
ALTER TABLE `pesos_criterios_discapacidad`
  MODIFY `id_peso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT de la tabla `tipos_discapacidad`
--
ALTER TABLE `tipos_discapacidad`
  MODIFY `id_tipo_discapacidad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `adaptaciones_metodologicas`
--
ALTER TABLE `adaptaciones_metodologicas`
  ADD CONSTRAINT `adaptaciones_metodologicas_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE CASCADE;

--
-- Filtros para la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD CONSTRAINT `asignaciones_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_ibfk_2` FOREIGN KEY (`id_tipo_discapacidad`) REFERENCES `tipos_discapacidad` (`id_tipo_discapacidad`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_ibfk_3` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE SET NULL,
  ADD CONSTRAINT `asignaciones_ibfk_4` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE SET NULL;

--
-- Filtros para la tabla `asignaciones_historial`
--
ALTER TABLE `asignaciones_historial`
  ADD CONSTRAINT `asignaciones_historial_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE SET NULL,
  ADD CONSTRAINT `asignaciones_historial_ibfk_2` FOREIGN KEY (`id_estudiante`) REFERENCES `estudiantes` (`id_estudiante`) ON DELETE SET NULL,
  ADD CONSTRAINT `asignaciones_historial_ibfk_3` FOREIGN KEY (`id_tipo_discapacidad`) REFERENCES `tipos_discapacidad` (`id_tipo_discapacidad`) ON DELETE CASCADE,
  ADD CONSTRAINT `asignaciones_historial_ibfk_4` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE SET NULL;

--
-- Filtros para la tabla `cache_puntuaciones_ahp`
--
ALTER TABLE `cache_puntuaciones_ahp`
  ADD CONSTRAINT `cache_puntuaciones_ahp_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cache_puntuaciones_especificas`
--
ALTER TABLE `cache_puntuaciones_especificas`
  ADD CONSTRAINT `cache_puntuaciones_especificas_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE CASCADE,
  ADD CONSTRAINT `cache_puntuaciones_especificas_ibfk_2` FOREIGN KEY (`id_tipo_discapacidad`) REFERENCES `tipos_discapacidad` (`id_tipo_discapacidad`) ON DELETE CASCADE;

--
-- Filtros para la tabla `capacitaciones_nee`
--
ALTER TABLE `capacitaciones_nee`
  ADD CONSTRAINT `capacitaciones_nee_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE CASCADE;

--
-- Filtros para la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD CONSTRAINT `estudiantes_ibfk_1` FOREIGN KEY (`id_tipo_discapacidad`) REFERENCES `tipos_discapacidad` (`id_tipo_discapacidad`) ON DELETE CASCADE;

--
-- Filtros para la tabla `experiencia_docente_discapacidad`
--
ALTER TABLE `experiencia_docente_discapacidad`
  ADD CONSTRAINT `experiencia_docente_discapacidad_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE CASCADE,
  ADD CONSTRAINT `experiencia_docente_discapacidad_ibfk_2` FOREIGN KEY (`id_tipo_discapacidad`) REFERENCES `tipos_discapacidad` (`id_tipo_discapacidad`) ON DELETE CASCADE;

--
-- Filtros para la tabla `limites_asignacion`
--
ALTER TABLE `limites_asignacion`
  ADD CONSTRAINT `limites_asignacion_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pesos_criterios_discapacidad`
--
ALTER TABLE `pesos_criterios_discapacidad`
  ADD CONSTRAINT `pesos_criterios_discapacidad_ibfk_1` FOREIGN KEY (`id_tipo_discapacidad`) REFERENCES `tipos_discapacidad` (`id_tipo_discapacidad`) ON DELETE CASCADE,
  ADD CONSTRAINT `pesos_criterios_discapacidad_ibfk_2` FOREIGN KEY (`id_criterio`) REFERENCES `criterios_ahp` (`id_criterio`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
