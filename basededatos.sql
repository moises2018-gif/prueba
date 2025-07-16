-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 16-07-2025 a las 05:14:35
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
(6, 6, 1, 1, 1, 1, 'Sin adaptaciones específicas - actualizar según corresponda | Adaptaciones automáticas activadas - 2025-07-09 20:00:46'),
(7, 7, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(8, 8, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(9, 9, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(10, 10, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(11, 11, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(12, 12, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(13, 13, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(14, 14, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(15, 15, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(16, 16, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(17, 17, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(18, 18, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(19, 19, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(20, 20, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(21, 21, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(22, 22, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(23, 23, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(24, 24, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(25, 25, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(26, 26, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(27, 27, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(28, 28, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(29, 29, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(30, 30, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(31, 31, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(32, 32, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(33, 33, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(34, 34, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(35, 35, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(36, 36, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(37, 37, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(38, 38, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(39, 39, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(40, 40, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(41, 41, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(42, 42, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(43, 43, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(44, 44, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(45, 45, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(46, 46, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(47, 47, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(48, 48, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(49, 49, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(50, 50, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(51, 51, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(52, 52, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(53, 53, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(54, 54, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(55, 55, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(56, 56, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(57, 57, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(58, 58, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(59, 59, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(60, 60, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(61, 61, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(62, 62, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(63, 63, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(64, 64, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(65, 65, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(66, 66, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(67, 67, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(68, 68, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(69, 69, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(70, 70, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(71, 71, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(72, 72, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(73, 73, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(74, 74, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(75, 75, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(76, 76, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(77, 77, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(78, 78, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(79, 79, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(80, 80, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(81, 81, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(82, 82, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(83, 83, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda'),
(84, 84, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión'),
(85, 85, 0, 0, 0, 0, 'Sin adaptaciones específicas - actualizar según corresponda');

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

--
-- Volcado de datos para la tabla `asignaciones`
--

INSERT INTO `asignaciones` (`id_asignacion`, `id_docente`, `id_tipo_discapacidad`, `ciclo_academico`, `materia`, `numero_estudiantes`, `puntuacion_ahp`, `estado`, `fecha_asignacion`, `id_estudiante`, `id_materia`) VALUES
(14, 10, 3, '2025-1', 'Álgebra Lineal', 1, 1.033, 'Cancelada', '2025-06-20 07:39:25', 13, 5),
(15, 7, 3, '2025-1', 'Álgebra Lineal', 1, 1.010, 'Cancelada', '2025-06-20 07:39:25', 8, 5),
(16, 7, 3, '2025-1', 'Álgebra Lineal', 1, 0.960, 'Cancelada', '2025-06-20 07:39:25', 3, 5),
(17, 5, 4, '2025-1', 'Álgebra Lineal', 1, 0.784, 'Cancelada', '2025-06-20 07:39:25', 9, 5),
(18, 9, 4, '2025-1', 'Álgebra Lineal', 1, 0.784, 'Cancelada', '2025-06-20 07:39:25', 19, 5),
(19, 2, 4, '2025-1', 'Álgebra Lineal', 1, 0.784, 'Cancelada', '2025-06-20 07:39:25', 4, 5),
(20, 8, 4, '2025-1', 'Álgebra Lineal', 1, 0.770, 'Cancelada', '2025-06-20 07:39:25', 14, 5),
(21, 5, 2, '2025-1', 'Álgebra Lineal', 1, 0.751, 'Cancelada', '2025-06-20 07:39:25', 2, 5),
(22, 9, 5, '2025-1', 'Álgebra Lineal', 1, 0.802, 'Cancelada', '2025-06-20 07:39:25', 10, 5),
(23, 2, 5, '2025-1', 'Álgebra Lineal', 1, 0.802, 'Cancelada', '2025-06-20 07:39:25', 5, 5),
(24, 5, 5, '2025-1', 'Álgebra Lineal', 1, 0.760, 'Cancelada', '2025-06-20 07:39:25', 20, 5),
(25, 1, 1, '2025-1', 'Álgebra Lineal', 1, 0.500, 'Cancelada', '2025-07-04 16:48:28', 6, 5),
(26, 1, 1, '2025-1', 'Álgebra Lineal', 1, 0.475, 'Cancelada', '2025-07-04 16:48:28', 16, 5),
(27, 8, 1, '2025-1', 'Álgebra Lineal', 1, 0.475, 'Cancelada', '2025-07-04 16:48:28', 11, 5),
(28, 2, 3, '2025-1', 'Álgebra Lineal', 1, 0.450, 'Cancelada', '2025-07-04 16:48:28', 18, 5),
(29, 9, 2, '2025-1', 'Álgebra Lineal', 1, 0.450, 'Cancelada', '2025-07-04 16:48:28', 17, 5),
(30, 7, 2, '2025-1', 'Álgebra Lineal', 1, 0.450, 'Cancelada', '2025-07-04 16:48:28', 12, 5),
(31, 1, 2, '2025-1', 'Álgebra Lineal', 1, 0.450, 'Cancelada', '2025-07-04 16:48:28', 7, 5),
(32, 8, 5, '2025-1', 'Álgebra Lineal', 1, 0.450, 'Cancelada', '2025-07-04 16:48:28', 15, 5),
(33, 10, 1, '2025-1', 'Álgebra Lineal', 1, 1.036, 'Cancelada', '2025-07-04 17:16:26', 1, 5),
(37, 61, 1, '2025-1', 'Álgebra Lineal', 1, 1.071, 'Cancelada', '2025-07-10 01:02:14', 25, 5),
(38, 15, 1, '2025-1', 'Álgebra Lineal', 1, 1.071, 'Cancelada', '2025-07-10 01:02:14', 22, 5),
(39, 11, 1, '2025-1', 'Álgebra Lineal', 1, 0.500, 'Cancelada', '2025-07-10 01:02:14', 46, 5),
(40, 10, 3, '2025-1', 'Álgebra Lineal', 1, 1.021, 'Cancelada', '2025-07-10 01:02:14', 35, 5),
(41, 45, 3, '2025-1', 'Álgebra Lineal', 1, 0.500, 'Cancelada', '2025-07-10 01:02:14', 48, 5),
(42, 35, 3, '2025-1', 'Álgebra Lineal', 1, 1.020, 'Cancelada', '2025-07-10 01:02:14', 30, 5),
(43, 43, 3, '2025-1', 'Álgebra Lineal', 1, 1.020, 'Cancelada', '2025-07-10 01:02:14', 31, 5),
(44, 12, 3, '2025-1', 'Álgebra Lineal', 1, 0.985, 'Cancelada', '2025-07-10 01:02:14', 33, 5),
(45, 46, 3, '2025-1', 'Álgebra Lineal', 1, 0.985, 'Cancelada', '2025-07-10 01:02:14', 34, 5),
(46, 75, 3, '2025-1', 'Álgebra Lineal', 1, 0.985, 'Cancelada', '2025-07-10 01:02:14', 32, 5),
(47, 14, 4, '2025-1', 'Álgebra Lineal', 1, 0.500, 'Cancelada', '2025-07-10 01:02:14', 49, 5),
(48, 6, 4, '2025-1', 'Álgebra Lineal', 1, 0.847, 'Cancelada', '2025-07-10 01:02:14', 36, 5),
(49, 34, 4, '2025-1', 'Álgebra Lineal', 1, 0.841, 'Cancelada', '2025-07-10 01:02:14', 39, 5),
(50, 12, 4, '2025-1', 'Álgebra Lineal', 1, 0.823, 'Cancelada', '2025-07-10 01:02:14', 38, 5),
(51, 46, 4, '2025-1', 'Álgebra Lineal', 1, 0.823, 'Cancelada', '2025-07-10 01:02:14', 37, 5),
(52, 75, 4, '2025-1', 'Álgebra Lineal', 1, 0.823, 'Cancelada', '2025-07-10 01:02:14', 40, 5),
(53, 61, 2, '2025-1', 'Álgebra Lineal', 1, 0.828, 'Cancelada', '2025-07-10 01:02:14', 29, 5),
(54, 15, 2, '2025-1', 'Álgebra Lineal', 1, 0.828, 'Cancelada', '2025-07-10 01:02:14', 28, 5),
(55, 45, 2, '2025-1', 'Álgebra Lineal', 1, 0.828, 'Cancelada', '2025-07-10 01:02:14', 26, 5),
(56, 50, 2, '2025-1', 'Álgebra Lineal', 1, 0.500, 'Cancelada', '2025-07-10 01:02:14', 47, 5),
(57, 35, 2, '2025-1', 'Álgebra Lineal', 1, 0.828, 'Cancelada', '2025-07-10 01:02:14', 27, 5),
(58, 6, 5, '2025-1', 'Álgebra Lineal', 1, 0.876, 'Cancelada', '2025-07-10 01:02:14', 45, 5),
(59, 11, 5, '2025-1', 'Álgebra Lineal', 1, 0.839, 'Cancelada', '2025-07-10 01:02:14', 42, 5),
(60, 43, 5, '2025-1', 'Álgebra Lineal', 1, 0.839, 'Cancelada', '2025-07-10 01:02:14', 41, 5),
(61, 6, 5, '2025-1', 'Álgebra Lineal', 1, 0.836, 'Cancelada', '2025-07-10 01:02:14', 43, 5),
(62, 13, 5, '2025-1', 'Álgebra Lineal', 1, 0.500, 'Cancelada', '2025-07-10 01:02:14', 50, 5),
(63, 37, 5, '2025-1', 'Álgebra Lineal', 1, 0.806, 'Cancelada', '2025-07-10 01:02:14', 44, 5),
(64, 12, 1, '2025-1', 'Álgebra Lineal', 1, 0.986, 'Cancelada', '2025-07-10 01:45:37', 23, 5),
(65, 10, 1, '2025-1', 'Álgebra Lineal', 1, 1.120, 'Cancelada', '2025-07-14 16:42:46', 1, 5),
(66, 46, 1, '2025-1', 'Álgebra Lineal', 1, 1.078, 'Activa', '2025-07-14 16:42:46', 23, 5),
(67, 75, 1, '2025-1', 'Álgebra Lineal', 1, 1.078, 'Activa', '2025-07-14 16:42:46', 24, 5),
(68, 12, 1, '2025-1', 'Álgebra Lineal', 1, 1.078, 'Activa', '2025-07-14 16:42:46', 21, 5),
(69, 10, 1, '2025-1', 'Álgebra Lineal', 1, 1.072, 'Activa', '2025-07-14 16:42:46', 25, 5),
(70, 35, 1, '2025-1', 'Álgebra Lineal', 1, 1.071, 'Activa', '2025-07-14 16:42:46', 22, 5),
(71, 5, 3, '2025-1', 'Álgebra Lineal', 1, 1.068, 'Activa', '2025-07-14 16:42:46', 35, 5),
(72, 9, 3, '2025-1', 'Álgebra Lineal', 1, 1.068, 'Activa', '2025-07-14 16:42:46', 30, 5),
(73, 2, 3, '2025-1', 'Álgebra Lineal', 1, 1.068, 'Activa', '2025-07-14 16:42:46', 31, 5),
(74, 5, 3, '2025-1', 'Álgebra Lineal', 1, 1.023, 'Activa', '2025-07-14 16:42:46', 33, 5),
(75, 9, 3, '2025-1', 'Álgebra Lineal', 1, 1.023, 'Activa', '2025-07-14 16:42:46', 13, 5),
(76, 2, 3, '2025-1', 'Álgebra Lineal', 1, 1.023, 'Activa', '2025-07-14 16:42:46', 34, 5),
(77, 10, 3, '2025-1', 'Álgebra Lineal', 1, 1.021, 'Activa', '2025-07-14 16:42:46', 8, 5),
(78, 43, 3, '2025-1', 'Álgebra Lineal', 1, 1.020, 'Activa', '2025-07-14 16:42:46', 32, 5),
(79, 11, 3, '2025-1', 'Álgebra Lineal', 1, 1.020, 'Activa', '2025-07-14 16:42:46', 3, 5),
(80, 46, 4, '2025-1', 'Álgebra Lineal', 1, 0.861, 'Activa', '2025-07-14 16:42:46', 9, 5),
(81, 75, 4, '2025-1', 'Álgebra Lineal', 1, 0.861, 'Activa', '2025-07-14 16:42:46', 36, 5),
(82, 12, 4, '2025-1', 'Álgebra Lineal', 1, 0.861, 'Activa', '2025-07-14 16:42:46', 19, 5),
(83, 6, 4, '2025-1', 'Álgebra Lineal', 1, 0.847, 'Activa', '2025-07-14 16:42:46', 4, 5),
(84, 34, 4, '2025-1', 'Álgebra Lineal', 1, 0.841, 'Activa', '2025-07-14 16:42:46', 39, 5),
(85, 46, 4, '2025-1', 'Álgebra Lineal', 1, 0.823, 'Activa', '2025-07-14 16:42:46', 38, 5),
(86, 75, 4, '2025-1', 'Álgebra Lineal', 1, 0.823, 'Activa', '2025-07-14 16:42:46', 37, 5),
(87, 12, 4, '2025-1', 'Álgebra Lineal', 1, 0.823, 'Activa', '2025-07-14 16:42:46', 14, 5),
(88, 61, 4, '2025-1', 'Álgebra Lineal', 1, 0.818, 'Activa', '2025-07-14 16:42:46', 40, 5),
(89, 15, 2, '2025-1', 'Álgebra Lineal', 1, 0.865, 'Activa', '2025-07-14 16:42:46', 2, 5),
(90, 45, 2, '2025-1', 'Álgebra Lineal', 1, 0.865, 'Activa', '2025-07-14 16:42:46', 29, 5),
(91, 35, 2, '2025-1', 'Álgebra Lineal', 1, 0.828, 'Activa', '2025-07-14 16:42:46', 28, 5),
(92, 43, 2, '2025-1', 'Álgebra Lineal', 1, 0.828, 'Activa', '2025-07-14 16:42:46', 26, 5),
(93, 11, 2, '2025-1', 'Álgebra Lineal', 1, 0.828, 'Activa', '2025-07-14 16:42:46', 27, 5),
(94, 6, 5, '2025-1', 'Álgebra Lineal', 1, 0.876, 'Activa', '2025-07-14 16:42:46', 45, 5),
(95, 61, 5, '2025-1', 'Álgebra Lineal', 1, 0.839, 'Activa', '2025-07-14 16:42:46', 10, 5),
(96, 15, 5, '2025-1', 'Álgebra Lineal', 1, 0.839, 'Activa', '2025-07-14 16:42:46', 42, 5),
(97, 45, 5, '2025-1', 'Álgebra Lineal', 1, 0.839, 'Activa', '2025-07-14 16:42:46', 41, 5),
(98, 6, 5, '2025-1', 'Álgebra Lineal', 1, 0.836, 'Activa', '2025-07-14 16:42:46', 5, 5),
(99, 14, 5, '2025-1', 'Álgebra Lineal', 1, 0.816, 'Activa', '2025-07-14 16:42:46', 43, 5),
(100, 7, 5, '2025-1', 'Álgebra Lineal', 1, 0.816, 'Activa', '2025-07-14 16:42:46', 20, 5),
(101, 50, 5, '2025-1', 'Álgebra Lineal', 1, 0.816, 'Activa', '2025-07-14 16:42:46', 44, 5);

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

--
-- Volcado de datos para la tabla `asignaciones_historial`
--

INSERT INTO `asignaciones_historial` (`id_historial`, `id_asignacion`, `id_docente`, `id_estudiante`, `id_tipo_discapacidad`, `id_materia`, `ciclo_academico`, `materia`, `numero_estudiantes`, `puntuacion_ahp`, `estado`, `fecha_asignacion`, `fecha_eliminacion`) VALUES
(17, 37, 61, 25, 1, 5, '2025-1', 'Álgebra Lineal', 1, 1.071, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:41'),
(18, 14, 10, 13, 3, 5, '2025-1', 'Álgebra Lineal', 1, 1.033, 'Cancelada', '2025-06-20 07:39:25', '2025-07-10 04:48:45'),
(19, 15, 7, 8, 3, 5, '2025-1', 'Álgebra Lineal', 1, 1.01, 'Cancelada', '2025-06-20 07:39:25', '2025-07-10 04:48:45'),
(20, 16, 7, 3, 3, 5, '2025-1', 'Álgebra Lineal', 1, 0.96, 'Cancelada', '2025-06-20 07:39:25', '2025-07-10 04:48:45'),
(21, 17, 5, 9, 4, 5, '2025-1', 'Álgebra Lineal', 1, 0.784, 'Cancelada', '2025-06-20 07:39:25', '2025-07-10 04:48:45'),
(22, 18, 9, 19, 4, 5, '2025-1', 'Álgebra Lineal', 1, 0.784, 'Cancelada', '2025-06-20 07:39:25', '2025-07-10 04:48:45'),
(23, 19, 2, 4, 4, 5, '2025-1', 'Álgebra Lineal', 1, 0.784, 'Cancelada', '2025-06-20 07:39:25', '2025-07-10 04:48:45'),
(24, 20, 8, 14, 4, 5, '2025-1', 'Álgebra Lineal', 1, 0.77, 'Cancelada', '2025-06-20 07:39:25', '2025-07-10 04:48:45'),
(25, 21, 5, 2, 2, 5, '2025-1', 'Álgebra Lineal', 1, 0.751, 'Cancelada', '2025-06-20 07:39:25', '2025-07-10 04:48:45'),
(26, 22, 9, 10, 5, 5, '2025-1', 'Álgebra Lineal', 1, 0.802, 'Cancelada', '2025-06-20 07:39:25', '2025-07-10 04:48:45'),
(27, 23, 2, 5, 5, 5, '2025-1', 'Álgebra Lineal', 1, 0.802, 'Cancelada', '2025-06-20 07:39:25', '2025-07-10 04:48:45'),
(28, 24, 5, 20, 5, 5, '2025-1', 'Álgebra Lineal', 1, 0.76, 'Cancelada', '2025-06-20 07:39:25', '2025-07-10 04:48:45'),
(29, 25, 1, 6, 1, 5, '2025-1', 'Álgebra Lineal', 1, 0.5, 'Cancelada', '2025-07-04 16:48:28', '2025-07-10 04:48:45'),
(30, 26, 1, 16, 1, 5, '2025-1', 'Álgebra Lineal', 1, 0.475, 'Cancelada', '2025-07-04 16:48:28', '2025-07-10 04:48:45'),
(31, 27, 8, 11, 1, 5, '2025-1', 'Álgebra Lineal', 1, 0.475, 'Cancelada', '2025-07-04 16:48:28', '2025-07-10 04:48:45'),
(32, 28, 2, 18, 3, 5, '2025-1', 'Álgebra Lineal', 1, 0.45, 'Cancelada', '2025-07-04 16:48:28', '2025-07-10 04:48:45'),
(33, 29, 9, 17, 2, 5, '2025-1', 'Álgebra Lineal', 1, 0.45, 'Cancelada', '2025-07-04 16:48:28', '2025-07-10 04:48:45'),
(34, 30, 7, 12, 2, 5, '2025-1', 'Álgebra Lineal', 1, 0.45, 'Cancelada', '2025-07-04 16:48:28', '2025-07-10 04:48:45'),
(35, 31, 1, 7, 2, 5, '2025-1', 'Álgebra Lineal', 1, 0.45, 'Cancelada', '2025-07-04 16:48:28', '2025-07-10 04:48:45'),
(36, 32, 8, 15, 5, 5, '2025-1', 'Álgebra Lineal', 1, 0.45, 'Cancelada', '2025-07-04 16:48:28', '2025-07-10 04:48:45'),
(37, 33, 10, 1, 1, 5, '2025-1', 'Álgebra Lineal', 1, 1.036, 'Cancelada', '2025-07-04 17:16:26', '2025-07-10 04:48:45'),
(38, 38, 15, 22, 1, 5, '2025-1', 'Álgebra Lineal', 1, 1.071, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(39, 39, 11, 46, 1, 5, '2025-1', 'Álgebra Lineal', 1, 0.5, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(40, 40, 10, 35, 3, 5, '2025-1', 'Álgebra Lineal', 1, 1.021, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(41, 41, 45, 48, 3, 5, '2025-1', 'Álgebra Lineal', 1, 0.5, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(42, 42, 35, 30, 3, 5, '2025-1', 'Álgebra Lineal', 1, 1.02, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(43, 43, 43, 31, 3, 5, '2025-1', 'Álgebra Lineal', 1, 1.02, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(44, 44, 12, 33, 3, 5, '2025-1', 'Álgebra Lineal', 1, 0.985, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(45, 45, 46, 34, 3, 5, '2025-1', 'Álgebra Lineal', 1, 0.985, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(46, 46, 75, 32, 3, 5, '2025-1', 'Álgebra Lineal', 1, 0.985, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(47, 47, 14, 49, 4, 5, '2025-1', 'Álgebra Lineal', 1, 0.5, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(48, 48, 6, 36, 4, 5, '2025-1', 'Álgebra Lineal', 1, 0.847, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(49, 49, 34, 39, 4, 5, '2025-1', 'Álgebra Lineal', 1, 0.841, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(50, 50, 12, 38, 4, 5, '2025-1', 'Álgebra Lineal', 1, 0.823, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(51, 51, 46, 37, 4, 5, '2025-1', 'Álgebra Lineal', 1, 0.823, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(52, 52, 75, 40, 4, 5, '2025-1', 'Álgebra Lineal', 1, 0.823, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(53, 53, 61, 29, 2, 5, '2025-1', 'Álgebra Lineal', 1, 0.828, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(54, 54, 15, 28, 2, 5, '2025-1', 'Álgebra Lineal', 1, 0.828, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(55, 55, 45, 26, 2, 5, '2025-1', 'Álgebra Lineal', 1, 0.828, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(56, 56, 50, 47, 2, 5, '2025-1', 'Álgebra Lineal', 1, 0.5, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(57, 57, 35, 27, 2, 5, '2025-1', 'Álgebra Lineal', 1, 0.828, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(58, 58, 6, 45, 5, 5, '2025-1', 'Álgebra Lineal', 1, 0.876, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(59, 59, 11, 42, 5, 5, '2025-1', 'Álgebra Lineal', 1, 0.839, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(60, 60, 43, 41, 5, 5, '2025-1', 'Álgebra Lineal', 1, 0.839, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(61, 61, 6, 43, 5, 5, '2025-1', 'Álgebra Lineal', 1, 0.836, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(62, 62, 13, 50, 5, 5, '2025-1', 'Álgebra Lineal', 1, 0.5, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(63, 63, 37, 44, 5, 5, '2025-1', 'Álgebra Lineal', 1, 0.806, 'Cancelada', '2025-07-10 01:02:14', '2025-07-10 04:48:45'),
(64, 64, 12, 23, 1, 5, '2025-1', 'Álgebra Lineal', 1, 0.986, 'Cancelada', '2025-07-10 01:45:37', '2025-07-10 04:48:45'),
(65, 65, 10, 1, 1, 5, '2025-1', 'Álgebra Lineal', 1, 1.12, 'Cancelada', '2025-07-14 16:42:46', '2025-07-14 16:42:54');

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
(31, 75, 0.90, 0.90, 0.90, 0.90, 0.90, 0.90000, 1, '2025-07-10 01:00:47'),
(32, 12, 0.90, 0.90, 0.90, 0.90, 0.90, 0.90000, 2, '2025-07-10 01:00:47'),
(33, 46, 0.90, 0.90, 0.90, 0.90, 0.90, 0.90000, 3, '2025-07-10 01:00:47'),
(34, 9, 0.90, 0.90, 0.90, 0.90, 0.70, 0.87800, 4, '2025-07-10 01:00:47'),
(35, 10, 0.90, 0.90, 0.90, 0.90, 0.70, 0.87800, 5, '2025-07-10 01:00:47'),
(36, 11, 0.90, 0.90, 0.90, 0.90, 0.70, 0.87800, 6, '2025-07-10 01:00:47'),
(37, 43, 0.90, 0.90, 0.90, 0.90, 0.70, 0.87800, 7, '2025-07-10 01:00:47'),
(38, 2, 0.90, 0.90, 0.90, 0.90, 0.70, 0.87800, 8, '2025-07-10 01:00:47'),
(39, 45, 0.90, 0.90, 0.90, 0.90, 0.70, 0.87800, 9, '2025-07-10 01:00:47'),
(40, 35, 0.90, 0.90, 0.90, 0.90, 0.70, 0.87800, 10, '2025-07-10 01:00:47'),
(41, 15, 0.90, 0.90, 0.90, 0.90, 0.70, 0.87800, 11, '2025-07-10 01:00:47'),
(42, 5, 0.90, 0.90, 0.90, 0.90, 0.70, 0.87800, 12, '2025-07-10 01:00:47'),
(43, 61, 0.90, 0.90, 0.90, 0.90, 0.70, 0.87800, 13, '2025-07-10 01:00:47'),
(44, 34, 0.90, 0.75, 0.90, 0.70, 0.90, 0.82600, 14, '2025-07-10 01:00:47'),
(45, 14, 0.90, 0.75, 0.90, 0.70, 0.70, 0.80400, 15, '2025-07-10 01:00:47'),
(46, 7, 0.90, 0.75, 0.90, 0.70, 0.70, 0.80400, 16, '2025-07-10 01:00:47'),
(47, 50, 0.90, 0.75, 0.90, 0.70, 0.70, 0.80400, 17, '2025-07-10 01:00:47'),
(48, 37, 0.75, 0.75, 0.90, 0.70, 0.90, 0.78400, 18, '2025-07-10 01:00:47'),
(49, 52, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 19, '2025-07-10 01:00:47'),
(50, 63, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 20, '2025-07-10 01:00:47'),
(51, 42, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 21, '2025-07-10 01:00:47'),
(52, 65, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 22, '2025-07-10 01:00:47'),
(53, 13, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 23, '2025-07-10 01:00:47'),
(54, 67, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 24, '2025-07-10 01:00:47'),
(55, 47, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 25, '2025-07-10 01:00:47'),
(56, 79, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 26, '2025-07-10 01:00:47'),
(57, 26, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 27, '2025-07-10 01:00:47'),
(58, 6, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 28, '2025-07-10 01:00:47'),
(59, 17, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 29, '2025-07-10 01:00:47'),
(60, 60, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 30, '2025-07-10 01:00:47'),
(61, 82, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 31, '2025-07-10 01:00:47'),
(62, 72, 0.75, 0.75, 0.90, 0.70, 0.70, 0.76200, 32, '2025-07-10 01:00:47'),
(63, 1, 0.90, 0.60, 0.90, 0.50, 0.90, 0.75200, 33, '2025-07-10 01:00:47'),
(64, 59, 0.90, 0.60, 0.90, 0.50, 0.90, 0.75200, 34, '2025-07-10 01:00:47'),
(65, 30, 0.75, 0.60, 0.90, 0.70, 0.70, 0.71400, 35, '2025-07-10 01:00:47'),
(66, 62, 0.75, 0.60, 0.90, 0.70, 0.70, 0.71400, 36, '2025-07-10 01:00:47'),
(67, 73, 0.75, 0.60, 0.90, 0.70, 0.70, 0.71400, 37, '2025-07-10 01:00:47'),
(68, 20, 0.75, 0.60, 0.90, 0.70, 0.70, 0.71400, 38, '2025-07-10 01:00:47'),
(69, 84, 0.75, 0.60, 0.90, 0.70, 0.70, 0.71400, 39, '2025-07-10 01:00:47'),
(70, 32, 0.75, 0.60, 0.90, 0.70, 0.70, 0.71400, 40, '2025-07-10 01:00:47'),
(71, 16, 0.75, 0.60, 0.90, 0.70, 0.70, 0.71400, 41, '2025-07-10 01:00:47'),
(72, 80, 0.75, 0.60, 0.90, 0.70, 0.70, 0.71400, 42, '2025-07-10 01:00:47'),
(73, 27, 0.75, 0.60, 0.90, 0.70, 0.70, 0.71400, 43, '2025-07-10 01:00:47'),
(74, 18, 0.75, 0.60, 0.90, 0.70, 0.70, 0.71400, 44, '2025-07-10 01:00:47'),
(75, 40, 0.75, 0.60, 0.90, 0.70, 0.70, 0.71400, 45, '2025-07-10 01:00:47'),
(76, 8, 0.90, 0.40, 0.90, 0.50, 0.90, 0.68800, 46, '2025-07-10 01:00:47'),
(77, 33, 0.40, 0.60, 0.20, 0.90, 0.70, 0.53000, 47, '2025-07-10 01:00:47'),
(78, 76, 0.40, 0.60, 0.20, 0.70, 0.70, 0.50400, 48, '2025-07-10 01:00:47'),
(79, 48, 0.40, 0.60, 0.20, 0.70, 0.70, 0.50400, 49, '2025-07-10 01:00:47'),
(80, 38, 0.40, 0.60, 0.20, 0.70, 0.70, 0.50400, 50, '2025-07-10 01:00:47'),
(81, 81, 0.40, 0.60, 0.20, 0.70, 0.70, 0.50400, 51, '2025-07-10 01:00:47'),
(82, 19, 0.40, 0.60, 0.20, 0.70, 0.70, 0.50400, 52, '2025-07-10 01:00:47'),
(83, 51, 0.40, 0.60, 0.20, 0.70, 0.70, 0.50400, 53, '2025-07-10 01:00:47'),
(84, 3, 0.40, 0.60, 0.20, 0.70, 0.50, 0.48200, 54, '2025-07-10 01:00:47'),
(85, 28, 0.40, 0.60, 0.20, 0.70, 0.50, 0.48200, 55, '2025-07-10 01:00:47'),
(86, 22, 0.40, 0.40, 0.20, 0.70, 0.70, 0.44000, 56, '2025-07-10 01:00:47'),
(87, 41, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 57, '2025-07-10 01:00:47'),
(88, 31, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 58, '2025-07-10 01:00:47'),
(89, 74, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 59, '2025-07-10 01:00:47'),
(90, 21, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 60, '2025-07-10 01:00:47'),
(91, 53, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 61, '2025-07-10 01:00:47'),
(92, 85, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 62, '2025-07-10 01:00:47'),
(93, 64, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 63, '2025-07-10 01:00:47'),
(94, 54, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 64, '2025-07-10 01:00:47'),
(95, 44, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 65, '2025-07-10 01:00:47'),
(96, 23, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 66, '2025-07-10 01:00:47'),
(97, 55, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 67, '2025-07-10 01:00:47'),
(98, 66, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 68, '2025-07-10 01:00:47'),
(99, 77, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 69, '2025-07-10 01:00:47'),
(100, 56, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 70, '2025-07-10 01:00:47'),
(101, 78, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 71, '2025-07-10 01:00:47'),
(102, 25, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 72, '2025-07-10 01:00:47'),
(103, 57, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 73, '2025-07-10 01:00:47'),
(104, 4, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 74, '2025-07-10 01:00:47'),
(105, 36, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 75, '2025-07-10 01:00:47'),
(106, 68, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 76, '2025-07-10 01:00:47'),
(107, 58, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 77, '2025-07-10 01:00:47'),
(108, 69, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 78, '2025-07-10 01:00:47'),
(109, 70, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 79, '2025-07-10 01:00:47'),
(110, 49, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 80, '2025-07-10 01:00:47'),
(111, 39, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 81, '2025-07-10 01:00:47'),
(112, 71, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 82, '2025-07-10 01:00:47'),
(113, 29, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 83, '2025-07-10 01:00:47'),
(114, 83, 0.40, 0.40, 0.20, 0.50, 0.70, 0.41400, 84, '2025-07-10 01:00:47'),
(115, 24, 0.20, 0.20, 0.20, 0.50, 0.50, 0.27200, 85, '2025-07-10 01:00:47');

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
(177, 10, 1, 1.0177500, 1, 1, 'Experto', '2025-07-10 01:00:47'),
(178, 75, 1, 0.9801000, 2, 1, 'Avanzado', '2025-07-10 01:00:47'),
(179, 12, 1, 0.9801000, 2, 1, 'Avanzado', '2025-07-10 01:00:47'),
(180, 46, 1, 0.9801000, 2, 1, 'Avanzado', '2025-07-10 01:00:47'),
(181, 9, 1, 0.9735000, 5, 1, 'Avanzado', '2025-07-10 01:00:47'),
(182, 15, 1, 0.9735000, 5, 1, 'Avanzado', '2025-07-10 01:00:47'),
(183, 2, 1, 0.9735000, 5, 1, 'Avanzado', '2025-07-10 01:00:47'),
(184, 43, 1, 0.9735000, 5, 1, 'Avanzado', '2025-07-10 01:00:47'),
(185, 61, 1, 0.9735000, 5, 1, 'Avanzado', '2025-07-10 01:00:47'),
(186, 35, 1, 0.9735000, 5, 1, 'Avanzado', '2025-07-10 01:00:47'),
(187, 5, 1, 0.9735000, 5, 1, 'Avanzado', '2025-07-10 01:00:47'),
(188, 11, 1, 0.9735000, 5, 1, 'Avanzado', '2025-07-10 01:00:47'),
(189, 45, 1, 0.9735000, 5, 1, 'Avanzado', '2025-07-10 01:00:47'),
(190, 34, 1, 0.8822000, 14, 1, 'Avanzado', '2025-07-10 01:00:47'),
(191, 50, 1, 0.8756000, 15, 1, 'Avanzado', '2025-07-10 01:00:47'),
(192, 14, 1, 0.8756000, 15, 1, 'Avanzado', '2025-07-10 01:00:47'),
(193, 7, 1, 0.8756000, 15, 1, 'Avanzado', '2025-07-10 01:00:47'),
(194, 37, 1, 0.8393000, 18, 1, 'Avanzado', '2025-07-10 01:00:47'),
(195, 63, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(196, 82, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(197, 42, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(198, 13, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(199, 67, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(200, 60, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(201, 47, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(202, 79, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(203, 72, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(204, 17, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(205, 26, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(206, 52, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(207, 65, 1, 0.8327000, 19, 1, 'Avanzado', '2025-07-10 01:00:47'),
(208, 1, 1, 0.7843000, 32, 1, 'Avanzado', '2025-07-10 01:00:47'),
(209, 6, 1, 0.7570000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(210, 59, 1, 0.7486500, 34, 1, 'Intermedio', '2025-07-10 01:00:47'),
(211, 16, 1, 0.7161000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(212, 84, 1, 0.7161000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(213, 30, 1, 0.7161000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(214, 62, 1, 0.7161000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(215, 20, 1, 0.7161000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(216, 80, 1, 0.7161000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(217, 73, 1, 0.7161000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(218, 18, 1, 0.7161000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(219, 40, 1, 0.7161000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(220, 27, 1, 0.7161000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(221, 32, 1, 0.7161000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(222, 8, 1, 0.6743000, 46, 1, 'Avanzado', '2025-07-10 01:00:47'),
(223, 33, 1, 0.5140000, 47, 0, 'Básico', '2025-07-10 01:00:47'),
(224, 38, 1, 0.5000000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(225, 51, 1, 0.5000000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(226, 76, 1, 0.5000000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(227, 81, 1, 0.5000000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(228, 48, 1, 0.5000000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(229, 19, 1, 0.5000000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(230, 3, 1, 0.4940000, 54, 0, 'Básico', '2025-07-10 01:00:47'),
(231, 28, 1, 0.4940000, 54, 0, 'Básico', '2025-07-10 01:00:47'),
(232, 22, 1, 0.4000000, 56, 0, 'Básico', '2025-07-10 01:00:47'),
(233, 25, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(234, 77, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(235, 64, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(236, 31, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(237, 57, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(238, 44, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(239, 70, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(240, 83, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(241, 21, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(242, 56, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(243, 69, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(244, 36, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(245, 23, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(246, 49, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(247, 29, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(248, 55, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(249, 68, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(250, 74, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(251, 41, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(252, 54, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(253, 53, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(254, 66, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(255, 85, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(256, 39, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(257, 78, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(258, 4, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(259, 58, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(260, 71, 1, 0.3860000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(261, 24, 1, 0.2280000, 85, 0, 'Básico', '2025-07-10 01:00:47'),
(262, 10, 3, 1.0154500, 1, 1, 'Experto', '2025-07-10 01:00:47'),
(263, 9, 3, 0.9713000, 2, 1, 'Avanzado', '2025-07-10 01:00:47'),
(264, 2, 3, 0.9713000, 2, 1, 'Avanzado', '2025-07-10 01:00:47'),
(265, 5, 3, 0.9713000, 2, 1, 'Avanzado', '2025-07-10 01:00:47'),
(266, 75, 3, 0.9355500, 5, 1, 'Intermedio', '2025-07-10 01:00:47'),
(267, 12, 3, 0.9355500, 5, 1, 'Intermedio', '2025-07-10 01:00:47'),
(268, 46, 3, 0.9355500, 5, 1, 'Intermedio', '2025-07-10 01:00:47'),
(269, 15, 3, 0.9271500, 8, 1, 'Intermedio', '2025-07-10 01:00:47'),
(270, 43, 3, 0.9271500, 8, 1, 'Intermedio', '2025-07-10 01:00:47'),
(271, 35, 3, 0.9271500, 8, 1, 'Intermedio', '2025-07-10 01:00:47'),
(272, 61, 3, 0.9271500, 8, 1, 'Intermedio', '2025-07-10 01:00:47'),
(273, 11, 3, 0.9271500, 8, 1, 'Intermedio', '2025-07-10 01:00:47'),
(274, 45, 3, 0.9271500, 8, 1, 'Intermedio', '2025-07-10 01:00:47'),
(275, 7, 3, 0.9185000, 14, 1, 'Avanzado', '2025-07-10 01:00:47'),
(276, 1, 3, 0.9142500, 15, 1, 'Experto', '2025-07-10 01:00:47'),
(277, 34, 3, 0.8851500, 16, 1, 'Intermedio', '2025-07-10 01:00:47'),
(278, 50, 3, 0.8767500, 17, 1, 'Intermedio', '2025-07-10 01:00:47'),
(279, 14, 3, 0.8767500, 17, 1, 'Intermedio', '2025-07-10 01:00:47'),
(280, 59, 3, 0.8347500, 19, 1, 'Intermedio', '2025-07-10 01:00:47'),
(281, 8, 3, 0.8305000, 20, 1, 'Avanzado', '2025-07-10 01:00:47'),
(282, 37, 3, 0.8127000, 21, 1, 'Intermedio', '2025-07-10 01:00:47'),
(283, 63, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(284, 82, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(285, 42, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(286, 13, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(287, 67, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(288, 60, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(289, 47, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(290, 79, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(291, 72, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(292, 17, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(293, 26, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(294, 52, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(295, 65, 3, 0.8043000, 22, 1, 'Intermedio', '2025-07-10 01:00:47'),
(296, 30, 3, 0.7728000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(297, 62, 3, 0.7728000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(298, 20, 3, 0.7728000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(299, 80, 3, 0.7728000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(300, 73, 3, 0.7728000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(301, 40, 3, 0.7728000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(302, 18, 3, 0.7728000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(303, 27, 3, 0.7728000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(304, 32, 3, 0.7728000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(305, 16, 3, 0.7728000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(306, 84, 3, 0.7728000, 35, 1, 'Intermedio', '2025-07-10 01:00:47'),
(307, 6, 3, 0.7660000, 46, 0, 'Básico', '2025-07-10 01:00:47'),
(308, 33, 3, 0.4530000, 47, 0, 'Básico', '2025-07-10 01:00:47'),
(309, 76, 3, 0.4350000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(310, 81, 3, 0.4350000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(311, 48, 3, 0.4350000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(312, 19, 3, 0.4350000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(313, 38, 3, 0.4350000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(314, 51, 3, 0.4350000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(315, 28, 3, 0.4270000, 54, 0, 'Básico', '2025-07-10 01:00:47'),
(316, 3, 3, 0.4270000, 54, 0, 'Básico', '2025-07-10 01:00:47'),
(317, 22, 3, 0.3950000, 56, 0, 'Básico', '2025-07-10 01:00:47'),
(318, 64, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(319, 31, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(320, 57, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(321, 44, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(322, 70, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(323, 83, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(324, 56, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(325, 69, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(326, 36, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(327, 23, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(328, 49, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(329, 29, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(330, 55, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(331, 68, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(332, 74, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(333, 41, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(334, 54, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(335, 21, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(336, 53, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(337, 66, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(338, 85, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(339, 39, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(340, 78, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(341, 4, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(342, 58, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(343, 71, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(344, 25, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(345, 77, 3, 0.3770000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(346, 24, 3, 0.2370000, 85, 0, 'Básico', '2025-07-10 01:00:47'),
(347, 75, 4, 0.9000000, 1, 0, 'Básico', '2025-07-10 01:00:47'),
(348, 12, 4, 0.9000000, 1, 0, 'Básico', '2025-07-10 01:00:47'),
(349, 46, 4, 0.9000000, 1, 0, 'Básico', '2025-07-10 01:00:47'),
(350, 34, 4, 0.8410000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(351, 9, 4, 0.8180000, 5, 0, 'Básico', '2025-07-10 01:00:47'),
(352, 15, 4, 0.8180000, 5, 0, 'Básico', '2025-07-10 01:00:47'),
(353, 2, 4, 0.8180000, 5, 0, 'Básico', '2025-07-10 01:00:47'),
(354, 43, 4, 0.8180000, 5, 0, 'Básico', '2025-07-10 01:00:47'),
(355, 35, 4, 0.8180000, 5, 0, 'Básico', '2025-07-10 01:00:47'),
(356, 61, 4, 0.8180000, 5, 0, 'Básico', '2025-07-10 01:00:47'),
(357, 5, 4, 0.8180000, 5, 0, 'Básico', '2025-07-10 01:00:47'),
(358, 11, 4, 0.8180000, 5, 0, 'Básico', '2025-07-10 01:00:47'),
(359, 10, 4, 0.8180000, 5, 0, 'Básico', '2025-07-10 01:00:47'),
(360, 45, 4, 0.8180000, 5, 0, 'Básico', '2025-07-10 01:00:47'),
(361, 37, 4, 0.8155000, 15, 0, 'Básico', '2025-07-10 01:00:47'),
(362, 1, 4, 0.7820000, 16, 0, 'Básico', '2025-07-10 01:00:47'),
(363, 59, 4, 0.7820000, 16, 0, 'Básico', '2025-07-10 01:00:47'),
(364, 6, 4, 0.7701750, 18, 1, 'Intermedio', '2025-07-10 01:00:47'),
(365, 8, 4, 0.7700000, 19, 0, 'Básico', '2025-07-10 01:00:47'),
(366, 50, 4, 0.7590000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(367, 14, 4, 0.7590000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(368, 7, 4, 0.7590000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(369, 63, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(370, 82, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(371, 42, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(372, 13, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(373, 67, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(374, 60, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(375, 47, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(376, 79, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(377, 72, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(378, 17, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(379, 26, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(380, 52, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(381, 65, 4, 0.7335000, 23, 0, 'Básico', '2025-07-10 01:00:47'),
(382, 30, 4, 0.7245000, 36, 0, 'Básico', '2025-07-10 01:00:47'),
(383, 62, 4, 0.7245000, 36, 0, 'Básico', '2025-07-10 01:00:47'),
(384, 20, 4, 0.7245000, 36, 0, 'Básico', '2025-07-10 01:00:47'),
(385, 80, 4, 0.7245000, 36, 0, 'Básico', '2025-07-10 01:00:47'),
(386, 73, 4, 0.7245000, 36, 0, 'Básico', '2025-07-10 01:00:47'),
(387, 40, 4, 0.7245000, 36, 0, 'Básico', '2025-07-10 01:00:47'),
(388, 18, 4, 0.7245000, 36, 0, 'Básico', '2025-07-10 01:00:47'),
(389, 27, 4, 0.7245000, 36, 0, 'Básico', '2025-07-10 01:00:47'),
(390, 32, 4, 0.7245000, 36, 0, 'Básico', '2025-07-10 01:00:47'),
(391, 16, 4, 0.7245000, 36, 0, 'Básico', '2025-07-10 01:00:47'),
(392, 84, 4, 0.7245000, 36, 0, 'Básico', '2025-07-10 01:00:47'),
(393, 33, 4, 0.6380000, 47, 0, 'Básico', '2025-07-10 01:00:47'),
(394, 76, 4, 0.5880000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(395, 81, 4, 0.5880000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(396, 48, 4, 0.5880000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(397, 19, 4, 0.5880000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(398, 38, 4, 0.5880000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(399, 51, 4, 0.5880000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(400, 22, 4, 0.5760000, 54, 0, 'Básico', '2025-07-10 01:00:47'),
(401, 4, 4, 0.5523000, 55, 1, 'Intermedio', '2025-07-10 01:00:47'),
(402, 3, 4, 0.5313000, 56, 1, 'Intermedio', '2025-07-10 01:00:47'),
(403, 31, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(404, 57, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(405, 44, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(406, 70, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(407, 83, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(408, 56, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(409, 69, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(410, 36, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(411, 23, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(412, 49, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(413, 29, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(414, 55, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(415, 68, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(416, 74, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(417, 41, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(418, 54, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(419, 21, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(420, 53, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(421, 66, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(422, 85, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(423, 39, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(424, 78, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(425, 58, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(426, 71, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(427, 25, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(428, 77, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(429, 64, 4, 0.5260000, 57, 0, 'Básico', '2025-07-10 01:00:47'),
(430, 28, 4, 0.5060000, 84, 0, 'Básico', '2025-07-10 01:00:47'),
(431, 24, 4, 0.3980000, 85, 0, 'Básico', '2025-07-10 01:00:47'),
(432, 75, 2, 0.8910000, 1, 0, 'Básico', '2025-07-10 01:00:47'),
(433, 12, 2, 0.8910000, 1, 0, 'Básico', '2025-07-10 01:00:47'),
(434, 46, 2, 0.8910000, 1, 0, 'Básico', '2025-07-10 01:00:47'),
(435, 9, 2, 0.8650000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(436, 15, 2, 0.8650000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(437, 2, 2, 0.8650000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(438, 43, 2, 0.8650000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(439, 35, 2, 0.8650000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(440, 61, 2, 0.8650000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(441, 5, 2, 0.8650000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(442, 11, 2, 0.8650000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(443, 10, 2, 0.8650000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(444, 45, 2, 0.8650000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(445, 34, 2, 0.7740000, 14, 0, 'Básico', '2025-07-10 01:00:47'),
(446, 6, 2, 0.7728000, 15, 1, 'Intermedio', '2025-07-10 01:00:47'),
(447, 37, 2, 0.7620000, 16, 0, 'Básico', '2025-07-10 01:00:47'),
(448, 50, 2, 0.7480000, 17, 0, 'Básico', '2025-07-10 01:00:47'),
(449, 14, 2, 0.7480000, 17, 0, 'Básico', '2025-07-10 01:00:47'),
(450, 7, 2, 0.7480000, 17, 0, 'Básico', '2025-07-10 01:00:47'),
(451, 63, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(452, 82, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(453, 42, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(454, 13, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(455, 67, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(456, 60, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(457, 47, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(458, 79, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(459, 72, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(460, 17, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(461, 26, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(462, 52, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(463, 65, 2, 0.7360000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(464, 30, 2, 0.6730000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(465, 62, 2, 0.6730000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(466, 20, 2, 0.6730000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(467, 80, 2, 0.6730000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(468, 73, 2, 0.6730000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(469, 18, 2, 0.6730000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(470, 40, 2, 0.6730000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(471, 27, 2, 0.6730000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(472, 32, 2, 0.6730000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(473, 16, 2, 0.6730000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(474, 84, 2, 0.6730000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(475, 1, 2, 0.6570000, 44, 0, 'Básico', '2025-07-10 01:00:47'),
(476, 59, 2, 0.6570000, 44, 0, 'Básico', '2025-07-10 01:00:47'),
(477, 33, 2, 0.6360000, 46, 0, 'Básico', '2025-07-10 01:00:47'),
(478, 3, 2, 0.5838000, 47, 1, 'Intermedio', '2025-07-10 01:00:47'),
(479, 51, 2, 0.5820000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(480, 76, 2, 0.5820000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(481, 81, 2, 0.5820000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(482, 48, 2, 0.5820000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(483, 19, 2, 0.5820000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(484, 38, 2, 0.5820000, 48, 0, 'Básico', '2025-07-10 01:00:47'),
(485, 8, 2, 0.5730000, 54, 0, 'Básico', '2025-07-10 01:00:47'),
(486, 28, 2, 0.5560000, 55, 0, 'Básico', '2025-07-10 01:00:47'),
(487, 22, 2, 0.4980000, 56, 0, 'Básico', '2025-07-10 01:00:47'),
(488, 4, 2, 0.4662000, 57, 1, 'Intermedio', '2025-07-10 01:00:47'),
(489, 25, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(490, 77, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(491, 64, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(492, 31, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(493, 57, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(494, 44, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(495, 70, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(496, 83, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(497, 21, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(498, 56, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(499, 69, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(500, 36, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(501, 23, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(502, 49, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(503, 29, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(504, 55, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(505, 68, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(506, 74, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(507, 41, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(508, 54, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(509, 53, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(510, 66, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(511, 85, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(512, 39, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(513, 78, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(514, 58, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(515, 71, 2, 0.4440000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(516, 24, 2, 0.3180000, 85, 0, 'Básico', '2025-07-10 01:00:47'),
(517, 75, 5, 0.8910000, 1, 0, 'Básico', '2025-07-10 01:00:47'),
(518, 12, 5, 0.8910000, 1, 0, 'Básico', '2025-07-10 01:00:47'),
(519, 46, 5, 0.8910000, 1, 0, 'Básico', '2025-07-10 01:00:47'),
(520, 15, 5, 0.8770000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(521, 2, 5, 0.8770000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(522, 43, 5, 0.8770000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(523, 35, 5, 0.8770000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(524, 61, 5, 0.8770000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(525, 5, 5, 0.8770000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(526, 11, 5, 0.8770000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(527, 10, 5, 0.8770000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(528, 45, 5, 0.8770000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(529, 9, 5, 0.8770000, 4, 0, 'Básico', '2025-07-10 01:00:47'),
(530, 6, 5, 0.8316000, 14, 1, 'Intermedio', '2025-07-10 01:00:47'),
(531, 34, 5, 0.8300000, 15, 0, 'Básico', '2025-07-10 01:00:47'),
(532, 50, 5, 0.8160000, 16, 0, 'Básico', '2025-07-10 01:00:47'),
(533, 14, 5, 0.8160000, 16, 0, 'Básico', '2025-07-10 01:00:47'),
(534, 7, 5, 0.8160000, 16, 0, 'Básico', '2025-07-10 01:00:47'),
(535, 37, 5, 0.8060000, 19, 0, 'Básico', '2025-07-10 01:00:47'),
(536, 63, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(537, 82, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(538, 42, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(539, 13, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(540, 67, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(541, 60, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(542, 47, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(543, 79, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(544, 72, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(545, 17, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(546, 26, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(547, 52, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(548, 65, 5, 0.7920000, 20, 0, 'Básico', '2025-07-10 01:00:47'),
(549, 30, 5, 0.7830000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(550, 20, 5, 0.7830000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(551, 62, 5, 0.7830000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(552, 80, 5, 0.7830000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(553, 73, 5, 0.7830000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(554, 40, 5, 0.7830000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(555, 18, 5, 0.7830000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(556, 27, 5, 0.7830000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(557, 32, 5, 0.7830000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(558, 16, 5, 0.7830000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(559, 84, 5, 0.7830000, 33, 0, 'Básico', '2025-07-10 01:00:47'),
(560, 1, 5, 0.7690000, 44, 0, 'Básico', '2025-07-10 01:00:47'),
(561, 59, 5, 0.7690000, 44, 0, 'Básico', '2025-07-10 01:00:47'),
(562, 8, 5, 0.7570000, 46, 0, 'Básico', '2025-07-10 01:00:47'),
(563, 33, 5, 0.4710000, 47, 0, 'Básico', '2025-07-10 01:00:47'),
(564, 3, 5, 0.4252500, 48, 1, 'Intermedio', '2025-07-10 01:00:47'),
(565, 76, 5, 0.4190000, 49, 0, 'Básico', '2025-07-10 01:00:47'),
(566, 81, 5, 0.4190000, 49, 0, 'Básico', '2025-07-10 01:00:47'),
(567, 48, 5, 0.4190000, 49, 0, 'Básico', '2025-07-10 01:00:47'),
(568, 19, 5, 0.4190000, 49, 0, 'Básico', '2025-07-10 01:00:47'),
(569, 38, 5, 0.4190000, 49, 0, 'Básico', '2025-07-10 01:00:47'),
(570, 51, 5, 0.4190000, 49, 0, 'Básico', '2025-07-10 01:00:47'),
(571, 22, 5, 0.4070000, 55, 0, 'Básico', '2025-07-10 01:00:47'),
(572, 28, 5, 0.4050000, 56, 0, 'Básico', '2025-07-10 01:00:47'),
(573, 4, 5, 0.3727500, 57, 1, 'Intermedio', '2025-07-10 01:00:47'),
(574, 44, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(575, 70, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(576, 83, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(577, 56, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(578, 69, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(579, 36, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(580, 23, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(581, 49, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(582, 55, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(583, 29, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(584, 68, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(585, 74, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(586, 54, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(587, 41, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(588, 21, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(589, 53, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(590, 66, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(591, 85, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(592, 39, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(593, 78, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(594, 58, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(595, 71, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(596, 25, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(597, 77, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(598, 64, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(599, 31, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(600, 57, 5, 0.3550000, 58, 0, 'Básico', '2025-07-10 01:00:47'),
(601, 24, 5, 0.2970000, 85, 0, 'Básico', '2025-07-10 01:00:47');

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
(1, 'JACOME MORALES GLADYS CRISTINA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Ingeniero en Computación', 'Doctor en Ciencias Pedagógicas', 1, '1 a 5 estudiantes', 6, 3, '2025-06-20 06:06:58', '2025-07-10 01:00:46'),
(2, 'GUIJARRO RODRIGUEZ ALFONSO ANIBAL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero en Sistemas Computacionales', 'Master en Ciberseguridad, Master en Administración de Empresas', 1, '1 a 5 estudiantes', 5, 8, '2025-06-20 06:06:58', '2025-07-10 01:00:46'),
(3, 'CRUZ NAVARRETE EDISON LUIS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniero en Software', NULL, 0, '1 a 5 estudiantes', 2, 3, '2025-06-20 06:06:58', '2025-07-10 01:00:46'),
(4, 'ALCIVAR MALDONADO TATIANA MABEL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '1 a 5 años', 'Ingeniera en Contabilidad y Auditoría', 'Master en Administración de Empresas', 0, '1 a 5 estudiantes', 1, 2, '2025-06-20 06:06:58', '2025-07-10 01:00:46'),
(5, 'COLLANTES FARAH ALEX ROBERTO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero en Sistemas Computacionales', 'Master en Administración de Empresas', 1, '6 a 10 estudiantes', 7, 9, '2025-06-20 06:06:58', '2025-07-10 01:00:46'),
(6, 'GARCIA ENRIQUEZ MYRIAM CECILIA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniera en Sistemas', 'Master en Seguridad Informática', 1, '1 a 5 estudiantes', 4, 5, '2025-06-20 06:06:58', '2025-07-10 01:00:46'),
(7, 'MENDOZA SILVA CARLOS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniero en Software', 'Master en Educación Especial', 1, '1 a 5 estudiantes', 6, 5, '2025-06-20 06:06:58', '2025-07-10 01:00:46'),
(8, 'LOOR CEDEÑO ANA PATRICIA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '1 a 5 años', 'Ingeniera en Sistemas', 'Doctor en Educación Inclusiva', 1, '1 a 5 estudiantes', 8, 1, '2025-06-20 06:06:58', '2025-07-10 01:00:46'),
(9, 'CASTRO MORALES MARÍA FERNANDA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Licenciada en Psicología Educativa', 'Master en Educación Especial y Inclusiva', 1, '6 a 10 estudiantes', 9, 8, '2025-06-20 06:06:58', '2025-07-10 01:00:46'),
(10, 'GILCES TUTIVEN WILSON', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', 'Más de 10 años', 'Ingeniero en Sistemas Computacionales', 'Master en Administración de Empresas', 1, '6 a 10 estudiantes', 15, 15, '2025-06-20 06:06:58', '2025-07-10 01:00:46'),
(11, 'NARANJO PEÑA IRMA ELIZABETH', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniera en Sistemas Computacionales', 'Master en Educación Inclusiva', 1, '6 a 10 estudiantes', 8, 12, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(12, 'VERGARA BELLO OSWALDO GASTON', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', 'Más de 10 años', 'Licenciado en Matemáticas', 'Doctor en Ciencias Pedagógicas', 1, '1 a 5 estudiantes', 6, 8, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(13, 'MERO BAQUERIZO CESAR ANDRES', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Licenciado en Comunicación Social', 'Master en Educación Superior', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(14, 'ORDOÑEZ VALENCIA MAYLEE LISBETH', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniera Matemática', 'Master en Matemáticas Aplicadas', 1, '1 a 5 estudiantes', 5, 6, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(15, 'MINDA GILCES DIANA ELIZABETH', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', 'Más de 10 años', 'Ingeniera en Software', 'Master en Gestión Educativa', 1, '6 a 10 estudiantes', 7, 10, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(16, 'DAVILA MACIAS ARACELI MARITZA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Ingeniera en Sistemas', 'Master en Tecnologías Educativas', 1, '1 a 5 estudiantes', 3, 4, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(17, 'VALENCIA MARTINEZ NELLY AMERICA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Licenciada en Matemáticas', 'Master en Administración Educativa', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(18, 'CALERO VILLARREAL RICHARD MANOLO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Licenciado en Ciencias Sociales', 'Master en Educación', 1, '1 a 5 estudiantes', 3, 4, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(19, 'CRUZ CHOEZ ANGELICA MARIA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniera Industrial', 'Master en Investigación de Operaciones', 0, '1 a 5 estudiantes', 2, 3, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(20, 'RAMOS MOSQUERA BOLIVAR', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniero en Computación', 'Master en Sistemas Computacionales', 1, '1 a 5 estudiantes', 3, 4, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(21, 'LOPEZ CARVAJAL GLOBER GIOVANNY', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Licenciado en Lenguas Extranjeras', 'Master en Lingüística Aplicada', 0, '1 a 5 estudiantes', 1, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(22, 'GARCIA ARIAS PEDRO MANUEL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniero Matemático', 'Master en Matemáticas', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(23, 'VERA CARRIEL BORIS TEOFILO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '1 a 5 años', 'Licenciado en Ciencias Políticas', 'Master en Ciencias Políticas', 0, '1 a 5 estudiantes', 1, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(24, 'MERCHAN MOSQUERA LUIS ALBERTO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Licenciado en Comunicación', NULL, 0, 'Ninguno', 0, 0, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(25, 'VARGAS CAICEDO NOEMI ESTEFANIA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Licenciada en Lenguas Extranjeras', 'Master en Enseñanza del Inglés', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(26, 'LARA GAVILANEZ HECTOR RAUL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Ingeniero en Software', 'Master en Ingeniería de Software', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(27, 'GALARZA SOLEDISPA MARIA ISABEL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniera en Sistemas', 'Master en Sistemas de Información', 1, '1 a 5 estudiantes', 3, 4, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(28, 'CRUZ NAVARRETE EDISON LUIS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniero en Software', NULL, 0, '1 a 5 estudiantes', 2, 3, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(29, 'ALCIVAR MALDONADO TATIANA MABEL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '1 a 5 años', 'Ingeniera en Contabilidad y Auditoría', 'Master en Administración de Empresas', 0, '1 a 5 estudiantes', 1, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(30, 'CEVALLOS ZHUNIO JORGE EDUARDO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Ingeniero en Sistemas', 'Master en Gestión de TI', 1, '1 a 5 estudiantes', 3, 4, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(31, 'RUATA AVILES SILVIA ADRIANA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Licenciada en Ciencias Sociales', 'Master en Gestión Pública', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(32, 'ALVAREZ SOLIS FRANCISCO XAVIER', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniero en Software', 'Master en Tecnologías Web', 1, '1 a 5 estudiantes', 3, 4, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(33, 'BEJARANO OSPINA LUZ MARINA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', 'Más de 10 años', 'Contadora Pública', 'Master en Finanzas', 0, '1 a 5 estudiantes', 2, 3, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(34, 'GARZON RODAS MAURICIO FERNANDO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Licenciado en Investigación', 'Doctor en Metodología de la Investigación', 1, '1 a 5 estudiantes', 5, 6, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(35, 'MOLINA CALDERON MIGUEL ALFONSO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', 'Más de 10 años', 'Ingeniero en Software', 'Master en Arquitectura de Software', 1, '6 a 10 estudiantes', 6, 8, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(36, 'ABAD SACOTO KARLA YADIRA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniera en Software', 'Master en Desarrollo Web', 0, '1 a 5 estudiantes', 2, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(37, 'HECHAVARRIA HERNANDEZ JESÚS RAFAEL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Ingeniero Matemático', 'Doctor en Matemáticas Aplicadas', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(38, 'ORTIZ ZAMBRANO MIRELLA CARMINA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Abogada', 'Master en Derecho Educativo', 0, '1 a 5 estudiantes', 2, 3, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(39, 'SARMIENTO LAVAYEN ROBERTO CARLOS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniero en Software', 'Master en Programación', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(40, 'CASTRO MARIDUEÑA ADRIANA MARIA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniera Industrial', 'Master en Gestión de Proyectos', 1, '1 a 5 estudiantes', 3, 4, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(41, 'MACIAS YANQUI OSCAR ALBERTO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '1 a 5 años', 'Ingeniero Matemático', 'Master en Estadística', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(42, 'GONZALES QUIÑONEZ ROSA ELENA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Licenciada en Investigación', 'Master en Metodología de Investigación', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(43, 'COLLANTES FARAH ALEX ROBERTO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero en Sistemas Computacionales', 'Master en Administración de Empresas', 1, '6 a 10 estudiantes', 7, 9, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(44, 'CALDERON GAVILANES MARLON ADRIAN', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Ingeniero en Software', 'Master en Programación Orientada a Objetos', 0, '1 a 5 estudiantes', 2, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(45, 'SANTOS DIAZ LILIA BEATRIZ', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', 'Más de 10 años', 'Ingeniera en Sistemas', 'Master en Estructuras de Datos', 1, '6 a 10 estudiantes', 8, 10, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(46, 'REYES WAGNIO MANUEL FABRICIO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', 'Más de 10 años', 'Ingeniero en Software', 'Doctor en Ingeniería de Software', 1, '6 a 10 estudiantes', 9, 12, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(47, 'RAMIREZ VELIZ RICARDO BOLIVAR', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Ingeniero en Sistemas', 'Master en Sistemas Operativos', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(48, 'CHÁVEZ CUJILÁN YELENA TAMARA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Estadística', 'Master en Estadística Aplicada', 0, '1 a 5 estudiantes', 2, 3, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(49, 'CEVALLOS TORRES LORENZO JOVANNY', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniero Estadístico', 'Master en Ciencia de Datos', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(50, 'CEVALLOS ORDOÑEZ ROSA BELEN', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Ingeniera en Software', 'Master en Ingeniería de Requerimientos', 1, '1 a 5 estudiantes', 5, 6, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(51, 'LOPEZDOMINGUEZ RIVAS LEILI GENOVEVA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Estadística', 'Master en Estadística Bayesiana', 0, '1 a 5 estudiantes', 2, 3, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(52, 'YANZA MONTALVAN ANGELA OLIVIA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniera en Sistemas', 'Master en Arquitectura de Software', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(53, 'ESPINOZA ORTIZ JULIANA MARLENE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '1 a 5 años', 'Ingeniera en Software', 'Master en Gestión de Requerimientos', 0, '1 a 5 estudiantes', 2, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(54, 'GUERRERO ARMENDÁRIZ PEDRO JAVIER', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniero en Software', 'Master en Procesos de Software', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(55, 'LEYVA VASQUEZ MAIKEL YELANDI', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniero Estadístico', 'Master en Machine Learning', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(56, 'BARZOLA MUÑOZ EDDY VICENTE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '1 a 5 años', 'Ingeniero en Sistemas', 'Master en Gestión de TI', 0, '1 a 5 estudiantes', 1, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(57, 'CRESPO LEON CHRISTOPHER GABRIEL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Ingeniero en Software', 'Master en Sistemas Operativos', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(58, 'VIVAS LUCAS CARLOS FELICIANO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Estadístico', 'Master en Análisis de Datos', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(59, 'JACOME MORALES GLADYS CRISTINA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Ingeniera en Computación', 'Doctor en Ciencias Pedagógicas', 1, '1 a 5 estudiantes', 6, 3, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(60, 'REYES ZAMBRANO GARY XAVIER', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniero en Sistemas', 'Master en Base de Datos', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(61, 'GUIJARRO RODRIGUEZ ALFONSO ANIBAL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero en Sistemas Computacionales', 'Master en Ciberseguridad', 1, '1 a 5 estudiantes', 5, 8, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(62, 'CEDEÑO RODRIGUEZ JUAN CARLOS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniero en Software', 'Master en Desarrollo Web', 1, '1 a 5 estudiantes', 3, 4, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(63, 'ESPIN RIOFRIO CESAR HUMBERTO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Ingeniero en Telecomunicaciones', 'Master en Redes de Computadoras', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(64, 'ALARCON SALVATIERRA JOSE ABEL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniero en Sistemas', 'Master en Base de Datos', 0, '1 a 5 estudiantes', 2, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(65, 'AVILES MONROY JORGE ISAAC', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Diseñador Gráfico', 'Master en Interacción Humano-Computadora', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(66, 'VARELA TAPIA ELEANOR ALEXANDRA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Psicóloga', 'Master en UX/UI Design', 0, '1 a 5 estudiantes', 2, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(67, 'RODRIGUEZ REVELO ELSY', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Licenciada en Investigación', 'Master en Metodología de la Investigación', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(68, 'ORTIZ ZAMBRANO JENNY ALEXANDRA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Licenciada en Investigación', 'Master en Investigación Educativa', 0, 'Ninguno', 2, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(69, 'MENDOZA MORAN VERONICA DEL ROCIO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Ingeniera en Software', 'Master en Programación de Eventos', 0, '1 a 5 estudiantes', 2, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(70, 'NUÑEZ GAIBOR JEFFERSON ELIAS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniero en Software', 'Master en Desarrollo de Aplicaciones', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(71, 'ARTEAGA YAGUAR ELVIS RUDDY', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '1 a 5 años', 'Diseñador Web', 'Master en Experiencia de Usuario', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(72, 'GARCIA ENRIQUEZ MYRIAM CECILIA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniera en Sistemas', 'Master en Seguridad Informática', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(73, 'PARRALES BRAVO FRANKLIN RICARDO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniero en Software', 'Master en Construcción de Software', 1, '1 a 5 estudiantes', 3, 4, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(74, 'ZUMBA GAMBOA JOHANNA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '1 a 5 años', 'Ingeniera en Software', 'Master en Desarrollo Web', 0, '1 a 5 estudiantes', 2, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(75, 'CASTRO AGUILAR GILBERTO FERNANDO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', 'Más de 10 años', 'Ingeniero en Software', 'Doctor en Calidad de Software', 1, '6 a 10 estudiantes', 8, 11, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(76, 'SALTOS SANTANA GISSELA MONSERRATE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Administradora de Empresas', 'Master en Comportamiento Organizacional', 0, '1 a 5 estudiantes', 2, 3, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(77, 'SANCHEZ PAZMIÑO DIANA PRISCILA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '1 a 5 años', 'Diseñadora UX', 'Master en Experiencia de Usuario', 0, '1 a 5 estudiantes', 2, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(78, 'BARBA SALAZAR JOEL ALEJANDRO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniero en Software', 'Master en Desarrollo Web', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(79, 'PERALTA GUARACA TANIA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniera en Calidad', 'Master en Gestión de Calidad', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(80, 'CHARCO AGUIRRE JORGE LUIS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniero en Software', 'Master en Desarrollo Móvil', 1, '1 a 5 estudiantes', 3, 4, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(81, 'MARTILLO ALCIVAR INELDA ANABELLE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Administradora de Empresas', 'Master en Emprendimiento e Innovación', 0, '1 a 5 estudiantes', 2, 3, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(82, 'TEJADA YEPEZ SILVIA LILIANA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', '6 a 10 años', 'Ingeniera en Sistemas', 'Master en Seguridad de la Información', 1, '1 a 5 estudiantes', 4, 5, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(83, 'PATIÑO PEREZ DARWIN', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniero en Software', 'Master en Inteligencia Artificial', 0, 'Ninguno', 2, 2, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(84, 'BENAVIDES LOPEZ DAVID GONZALO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniero en Sistemas', 'Master en Auditoría de Sistemas', 1, '1 a 5 estudiantes', 3, 4, '2025-07-10 01:00:45', '2025-07-10 01:00:45'),
(85, 'GUAMAN TUMBACO ANA LOURDES', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Licenciada en Lenguas Extranjeras', 'Master en Enseñanza del Inglés', 0, 'Ninguno', 1, 1, '2025-07-10 01:00:45', '2025-07-10 01:00:45');

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
(20, 'Tomás Ignacio Paredes Gómez', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-20 06:06:58'),
(21, 'RODRIGUEZ MENDEZ CARLOS ALEXANDER', 1, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(22, 'TORRES VALENCIA MARIA ESPERANZA', 1, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(23, 'JIMENEZ CASTRO LUIS FERNANDO', 1, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(24, 'MORALES PINEDA ANA CRISTINA', 1, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(25, 'SANTOS RAMIREZ DIEGO ANTONIO', 1, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(26, 'PEREZ GONZALEZ SOFIA ALEJANDRA', 2, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(27, 'VARGAS HERRERA MIGUEL ANGEL', 2, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(28, 'DELGADO RUIZ PATRICIA ISABEL', 2, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(29, 'CHAVEZ MORENO ANDRES FELIPE', 2, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(30, 'FLORES ORTIZ CAMILA VALENTINA', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(31, 'GUTIERREZ SILVA ROBERTO CARLOS', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(32, 'RAMOS AGUILAR LUCIANA FERNANDA', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(33, 'LEON VEGA SEBASTIAN EDUARDO', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(34, 'MARTINEZ CRUZ DANIELA ELIZABETH', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(35, 'CASTRO JIMENEZ GABRIEL ESTEBAN', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(36, 'HERNANDEZ LOPEZ VALERIA NICOLE', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(37, 'SALAZAR MEDINA JONATHAN DAVID', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(38, 'ROJAS PAREDES ISABELLA SOFIA', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(39, 'MENDOZA TORRES EMILIO ALFONSO', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(40, 'VILLAREAL SANTOS CAROLINA ANDREA', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(41, 'GUERRERO CHAVEZ FRANCISCO JAVIER', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(42, 'CORDOVA REYES ALEJANDRA MONSERRAT', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(43, 'MOLINA VARGAS RICARDO BENJAMIN', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(44, 'VERA GONZALEZ NICOLE STEPHANIE', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(45, 'AGUIRRE FLORES KEVIN ALEXANDER', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-07-10 01:00:46'),
(46, 'ZAMBRANO SILVA PAULA MICHELLE', 1, '2025-1', 'FACULTAD DE CIENCIAS SOCIALES Y HUMANAS', '2025-07-10 01:00:46'),
(47, 'SANCHEZ MORALES DAVID ORLANDO', 2, '2025-1', 'FACULTAD DE INGENIERÍA', '2025-07-10 01:00:46'),
(48, 'CEDEÑO LOPEZ SARA VALENTINA', 3, '2025-1', 'FACULTAD DE CIENCIAS SOCIALES Y HUMANAS', '2025-07-10 01:00:46'),
(49, 'ALVARADO CRUZ MATEO SEBASTIAN', 4, '2025-1', 'FACULTAD DE INGENIERÍA', '2025-07-10 01:00:46'),
(50, 'PONCE HERRERA SAMANTHA NICOLE', 5, '2025-1', 'FACULTAD DE CIENCIAS SOCIALES Y HUMANAS', '2025-07-10 01:00:46');

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
(6, 2, 1, 1, 3, 'Avanzado', 'Experiencia ajustada para mejor balance AHP'),
(7, 2, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(8, 2, 3, 1, 3, 'Avanzado', 'Experiencia ajustada para mejor balance AHP'),
(9, 2, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(10, 2, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(11, 3, 1, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(12, 3, 2, 1, 2, 'Intermedio', 'Experiencia especializada agregada'),
(13, 3, 3, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(14, 3, 4, 1, 2, 'Intermedio', 'Experiencia especializada agregada'),
(15, 3, 5, 1, 2, 'Intermedio', 'Experiencia especializada agregada'),
(16, 4, 1, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(17, 4, 2, 1, 2, 'Intermedio', 'Experiencia especializada agregada'),
(18, 4, 3, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(19, 4, 4, 1, 2, 'Intermedio', 'Experiencia especializada agregada'),
(20, 4, 5, 1, 2, 'Intermedio', 'Experiencia especializada agregada'),
(21, 5, 1, 1, 3, 'Avanzado', 'Experiencia ajustada para mejor balance AHP'),
(22, 5, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(23, 5, 3, 1, 3, 'Avanzado', 'Experiencia ajustada para mejor balance AHP'),
(24, 5, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(25, 5, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(26, 6, 1, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(27, 6, 2, 1, 2, 'Intermedio', 'Experiencia especializada agregada'),
(28, 6, 3, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(29, 6, 4, 1, 2, 'Intermedio', 'Experiencia especializada agregada'),
(30, 6, 5, 1, 2, 'Intermedio', 'Experiencia especializada agregada'),
(31, 7, 1, 1, 3, 'Avanzado', 'Experiencia ajustada para mejor balance AHP'),
(32, 7, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(33, 7, 3, 1, 3, 'Avanzado', 'Experiencia ajustada para mejor balance AHP'),
(34, 7, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(35, 7, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(36, 8, 1, 1, 3, 'Avanzado', 'Experiencia ajustada para mejor balance AHP'),
(37, 8, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(38, 8, 3, 1, 3, 'Avanzado', 'Experiencia ajustada para mejor balance AHP'),
(39, 8, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(40, 8, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(41, 9, 1, 1, 3, 'Avanzado', 'Experiencia ajustada para mejor balance AHP'),
(42, 9, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(43, 9, 3, 1, 3, 'Avanzado', 'Experiencia ajustada para mejor balance AHP'),
(44, 9, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(45, 9, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(46, 10, 1, 1, 5, 'Experto', 'Ajustado - Experiencia excepcional de 20 años en NEE'),
(47, 10, 2, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(48, 10, 3, 1, 5, 'Experto', 'Ajustado - Experiencia excepcional de 20 años en NEE'),
(49, 10, 4, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(50, 10, 5, 0, 0, 'Básico', 'Generado automáticamente en creación de BD'),
(51, 11, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(52, 11, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(53, 11, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(54, 11, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(55, 11, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(56, 12, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(57, 12, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(58, 12, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(59, 12, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(60, 12, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(61, 13, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(62, 13, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(63, 13, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(64, 13, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(65, 13, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(66, 14, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(67, 14, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(68, 14, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(69, 14, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(70, 14, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(71, 15, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(72, 15, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(73, 15, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(74, 15, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(75, 15, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(76, 16, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(77, 16, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(78, 16, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(79, 16, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(80, 16, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(81, 17, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(82, 17, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(83, 17, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(84, 17, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(85, 17, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(86, 18, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(87, 18, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(88, 18, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(89, 18, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(90, 18, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(91, 19, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(92, 19, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(93, 19, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(94, 19, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(95, 19, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(96, 20, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(97, 20, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(98, 20, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(99, 20, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(100, 20, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(101, 21, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(102, 21, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(103, 21, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(104, 21, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(105, 21, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(106, 22, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(107, 22, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(108, 22, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(109, 22, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(110, 22, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(111, 23, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(112, 23, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(113, 23, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(114, 23, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(115, 23, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(116, 24, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(117, 24, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(118, 24, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(119, 24, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(120, 24, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(121, 25, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(122, 25, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(123, 25, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(124, 25, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(125, 25, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(126, 26, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(127, 26, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(128, 26, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(129, 26, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(130, 26, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(131, 27, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(132, 27, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(133, 27, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(134, 27, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(135, 27, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(136, 28, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(137, 28, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(138, 28, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(139, 28, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(140, 28, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(141, 29, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(142, 29, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(143, 29, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(144, 29, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(145, 29, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(146, 30, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(147, 30, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(148, 30, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(149, 30, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(150, 30, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(151, 31, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(152, 31, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(153, 31, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(154, 31, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(155, 31, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(156, 32, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(157, 32, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(158, 32, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(159, 32, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(160, 32, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(161, 33, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(162, 33, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(163, 33, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(164, 33, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(165, 33, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(166, 34, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(167, 34, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(168, 34, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(169, 34, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(170, 34, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(171, 35, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(172, 35, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(173, 35, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(174, 35, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(175, 35, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(176, 36, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(177, 36, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(178, 36, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(179, 36, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(180, 36, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(181, 37, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(182, 37, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(183, 37, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(184, 37, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(185, 37, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(186, 38, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(187, 38, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(188, 38, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(189, 38, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(190, 38, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(191, 39, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(192, 39, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(193, 39, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(194, 39, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(195, 39, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(196, 40, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(197, 40, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(198, 40, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(199, 40, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(200, 40, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(201, 41, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(202, 41, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(203, 41, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(204, 41, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(205, 41, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(206, 42, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(207, 42, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(208, 42, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(209, 42, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(210, 42, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(211, 43, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(212, 43, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(213, 43, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(214, 43, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(215, 43, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(216, 44, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(217, 44, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(218, 44, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(219, 44, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(220, 44, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(221, 45, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(222, 45, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(223, 45, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(224, 45, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(225, 45, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(226, 46, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(227, 46, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(228, 46, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(229, 46, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(230, 46, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(231, 47, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(232, 47, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(233, 47, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(234, 47, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(235, 47, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(236, 48, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(237, 48, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(238, 48, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(239, 48, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(240, 48, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(241, 49, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(242, 49, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(243, 49, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(244, 49, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(245, 49, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(246, 50, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(247, 50, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(248, 50, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(249, 50, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(250, 50, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(251, 51, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(252, 51, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(253, 51, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(254, 51, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(255, 51, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(256, 52, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(257, 52, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(258, 52, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(259, 52, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(260, 52, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(261, 53, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(262, 53, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(263, 53, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(264, 53, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(265, 53, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(266, 54, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(267, 54, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(268, 54, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(269, 54, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(270, 54, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(271, 55, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(272, 55, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(273, 55, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(274, 55, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(275, 55, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(276, 56, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(277, 56, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(278, 56, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(279, 56, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(280, 56, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(281, 57, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(282, 57, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(283, 57, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(284, 57, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(285, 57, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(286, 58, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(287, 58, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(288, 58, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(289, 58, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(290, 58, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(291, 59, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(292, 59, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(293, 59, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(294, 59, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(295, 59, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(296, 60, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(297, 60, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(298, 60, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(299, 60, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(300, 60, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(301, 61, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(302, 61, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(303, 61, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(304, 61, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(305, 61, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(306, 62, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(307, 62, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(308, 62, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(309, 62, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(310, 62, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(311, 63, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(312, 63, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(313, 63, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(314, 63, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(315, 63, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(316, 64, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(317, 64, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(318, 64, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(319, 64, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(320, 64, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(321, 65, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(322, 65, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(323, 65, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(324, 65, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(325, 65, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(326, 66, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(327, 66, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(328, 66, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(329, 66, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(330, 66, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(331, 67, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(332, 67, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(333, 67, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(334, 67, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(335, 67, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(336, 68, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(337, 68, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(338, 68, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(339, 68, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(340, 68, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(341, 69, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(342, 69, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(343, 69, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(344, 69, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(345, 69, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(346, 70, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(347, 70, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(348, 70, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(349, 70, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(350, 70, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(351, 71, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(352, 71, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(353, 71, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(354, 71, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(355, 71, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(356, 72, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(357, 72, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(358, 72, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(359, 72, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(360, 72, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(361, 73, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(362, 73, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(363, 73, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(364, 73, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(365, 73, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(366, 74, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(367, 74, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(368, 74, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(369, 74, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(370, 74, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(371, 75, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(372, 75, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(373, 75, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(374, 75, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(375, 75, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(376, 76, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(377, 76, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(378, 76, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(379, 76, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(380, 76, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(381, 77, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(382, 77, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(383, 77, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(384, 77, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(385, 77, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(386, 78, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(387, 78, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(388, 78, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(389, 78, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(390, 78, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(391, 79, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(392, 79, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(393, 79, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(394, 79, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(395, 79, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(396, 80, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(397, 80, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(398, 80, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(399, 80, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(400, 80, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(401, 81, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(402, 81, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(403, 81, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(404, 81, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(405, 81, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(406, 82, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(407, 82, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(408, 82, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(409, 82, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(410, 82, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(411, 83, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(412, 83, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(413, 83, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(414, 83, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(415, 83, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(416, 84, 1, 1, 3, 'Intermedio', 'Generado automáticamente por trigger'),
(417, 84, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(418, 84, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(419, 84, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(420, 84, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(421, 85, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(422, 85, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(423, 85, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(424, 85, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(425, 85, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger');

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
(1, 1, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=1 años | Límites aumentados para balance - 2025-06-20 02:36:49 | Actualizado automáticamente: formación=1, experiencia=3 años - 2025-07-09 20:00:46', '2025-06-20 06:07:00'),
(2, 2, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=6 años | Límites aumentados para balance - 2025-06-20 02:36:49 | Actualizado automáticamente: formación=1, experiencia=8 años - 2025-07-09 20:00:46', '2025-06-20 06:07:00'),
(3, 3, 4, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=4 años | Actualizado automáticamente: formación=0, experiencia=3 años - 2025-07-09 20:00:46', '2025-06-20 06:07:00'),
(4, 4, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-06-20 06:07:00'),
(5, 5, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=7 años | Límites aumentados para balance - 2025-06-20 02:36:49 | Actualizado automáticamente: formación=1, experiencia=9 años - 2025-07-09 20:00:46', '2025-06-20 06:07:00'),
(6, 6, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años | Actualizado automáticamente: formación=1, experiencia=5 años - 2025-07-09 20:00:46', '2025-06-20 06:07:00'),
(7, 7, 6, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años | Límites aumentados para balance - 2025-06-20 02:36:49', '2025-06-20 06:07:00'),
(8, 8, 6, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=1 años | Límites aumentados para balance - 2025-06-20 02:36:49', '2025-06-20 06:07:00'),
(9, 9, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=6 años | Límites aumentados para balance - 2025-06-20 02:36:49 | Actualizado automáticamente: formación=1, experiencia=8 años - 2025-07-09 20:00:46', '2025-06-20 06:07:00'),
(10, 10, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=20 años | Límites ajustados para mejor balance - 2025-06-20 02:36:49 | Actualizado automáticamente: formación=1, experiencia=15 años - 2025-07-09 20:00:46', '2025-06-20 06:07:00'),
(11, 11, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=12 años', '2025-07-10 01:00:45'),
(12, 12, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=8 años', '2025-07-10 01:00:45'),
(13, 13, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(14, 14, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=6 años', '2025-07-10 01:00:45'),
(15, 15, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=10 años', '2025-07-10 01:00:45'),
(16, 16, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=4 años', '2025-07-10 01:00:45'),
(17, 17, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(18, 18, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=4 años', '2025-07-10 01:00:45'),
(19, 19, 4, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=3 años', '2025-07-10 01:00:45'),
(20, 20, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=4 años', '2025-07-10 01:00:45'),
(21, 21, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(22, 22, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(23, 23, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(24, 24, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=0 años', '2025-07-10 01:00:45'),
(25, 25, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(26, 26, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(27, 27, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=4 años', '2025-07-10 01:00:45'),
(28, 28, 4, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=3 años', '2025-07-10 01:00:45'),
(29, 29, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(30, 30, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=4 años', '2025-07-10 01:00:45'),
(31, 31, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(32, 32, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=4 años', '2025-07-10 01:00:45'),
(33, 33, 4, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=3 años', '2025-07-10 01:00:45'),
(34, 34, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=6 años', '2025-07-10 01:00:45'),
(35, 35, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=8 años', '2025-07-10 01:00:45'),
(36, 36, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(37, 37, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(38, 38, 4, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=3 años', '2025-07-10 01:00:45'),
(39, 39, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(40, 40, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=4 años', '2025-07-10 01:00:45'),
(41, 41, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(42, 42, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(43, 43, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=9 años', '2025-07-10 01:00:45'),
(44, 44, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(45, 45, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=10 años', '2025-07-10 01:00:45'),
(46, 46, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=12 años', '2025-07-10 01:00:45'),
(47, 47, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(48, 48, 4, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=3 años', '2025-07-10 01:00:45'),
(49, 49, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(50, 50, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=6 años', '2025-07-10 01:00:45'),
(51, 51, 4, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=3 años', '2025-07-10 01:00:45'),
(52, 52, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(53, 53, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(54, 54, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(55, 55, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(56, 56, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(57, 57, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(58, 58, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(59, 59, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=3 años', '2025-07-10 01:00:45'),
(60, 60, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(61, 61, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=8 años', '2025-07-10 01:00:45'),
(62, 62, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=4 años', '2025-07-10 01:00:45'),
(63, 63, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(64, 64, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(65, 65, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(66, 66, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(67, 67, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(68, 68, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(69, 69, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(70, 70, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(71, 71, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(72, 72, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(73, 73, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=4 años', '2025-07-10 01:00:45'),
(74, 74, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(75, 75, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=11 años', '2025-07-10 01:00:45'),
(76, 76, 4, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=3 años', '2025-07-10 01:00:45'),
(77, 77, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(78, 78, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45'),
(79, 79, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(80, 80, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=4 años', '2025-07-10 01:00:45'),
(81, 81, 4, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=3 años', '2025-07-10 01:00:45'),
(82, 82, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-07-10 01:00:45'),
(83, 83, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-07-10 01:00:45'),
(84, 84, 5, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=4 años', '2025-07-10 01:00:45'),
(85, 85, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-07-10 01:00:45');

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
(9, 'cache_completo', 0, 'UPDATE', 'Recálculo completo de caché ejecutado', 'root@localhost', '2025-06-20 06:09:23'),
(10, 'experiencia_docente_discapacidad', 2, 'UPDATE', 'Experiencia modificada para discapacidad ID: 1', 'root@localhost', '2025-06-20 07:36:50'),
(11, 'experiencia_docente_discapacidad', 2, 'UPDATE', 'Experiencia modificada para discapacidad ID: 3', 'root@localhost', '2025-06-20 07:36:50'),
(12, 'experiencia_docente_discapacidad', 5, 'UPDATE', 'Experiencia modificada para discapacidad ID: 1', 'root@localhost', '2025-06-20 07:36:50'),
(13, 'experiencia_docente_discapacidad', 5, 'UPDATE', 'Experiencia modificada para discapacidad ID: 3', 'root@localhost', '2025-06-20 07:36:50'),
(14, 'experiencia_docente_discapacidad', 7, 'UPDATE', 'Experiencia modificada para discapacidad ID: 1', 'root@localhost', '2025-06-20 07:36:50'),
(15, 'experiencia_docente_discapacidad', 7, 'UPDATE', 'Experiencia modificada para discapacidad ID: 3', 'root@localhost', '2025-06-20 07:36:50'),
(16, 'experiencia_docente_discapacidad', 8, 'UPDATE', 'Experiencia modificada para discapacidad ID: 1', 'root@localhost', '2025-06-20 07:36:50'),
(17, 'experiencia_docente_discapacidad', 8, 'UPDATE', 'Experiencia modificada para discapacidad ID: 3', 'root@localhost', '2025-06-20 07:36:50'),
(18, 'experiencia_docente_discapacidad', 9, 'UPDATE', 'Experiencia modificada para discapacidad ID: 1', 'root@localhost', '2025-06-20 07:36:50'),
(19, 'experiencia_docente_discapacidad', 9, 'UPDATE', 'Experiencia modificada para discapacidad ID: 3', 'root@localhost', '2025-06-20 07:36:50'),
(20, 'experiencia_docente_discapacidad', 3, 'UPDATE', 'Experiencia modificada para discapacidad ID: 2', 'root@localhost', '2025-06-20 07:36:50'),
(21, 'experiencia_docente_discapacidad', 3, 'UPDATE', 'Experiencia modificada para discapacidad ID: 4', 'root@localhost', '2025-06-20 07:36:50'),
(22, 'experiencia_docente_discapacidad', 3, 'UPDATE', 'Experiencia modificada para discapacidad ID: 5', 'root@localhost', '2025-06-20 07:36:50'),
(23, 'experiencia_docente_discapacidad', 4, 'UPDATE', 'Experiencia modificada para discapacidad ID: 2', 'root@localhost', '2025-06-20 07:36:50'),
(24, 'experiencia_docente_discapacidad', 4, 'UPDATE', 'Experiencia modificada para discapacidad ID: 4', 'root@localhost', '2025-06-20 07:36:50'),
(25, 'experiencia_docente_discapacidad', 4, 'UPDATE', 'Experiencia modificada para discapacidad ID: 5', 'root@localhost', '2025-06-20 07:36:50'),
(26, 'experiencia_docente_discapacidad', 6, 'UPDATE', 'Experiencia modificada para discapacidad ID: 2', 'root@localhost', '2025-06-20 07:36:50'),
(27, 'experiencia_docente_discapacidad', 6, 'UPDATE', 'Experiencia modificada para discapacidad ID: 4', 'root@localhost', '2025-06-20 07:36:50'),
(28, 'experiencia_docente_discapacidad', 6, 'UPDATE', 'Experiencia modificada para discapacidad ID: 5', 'root@localhost', '2025-06-20 07:36:50'),
(29, 'cache_completo', 0, 'UPDATE', 'Recálculo completo de caché ejecutado', 'root@localhost', '2025-06-20 07:36:50'),
(30, 'docentes', 11, 'INSERT', 'Nuevo docente insertado: NARANJO PEÑA IRMA ELIZABETH', 'root@localhost', '2025-07-10 01:00:45'),
(31, 'docentes', 12, 'INSERT', 'Nuevo docente insertado: VERGARA BELLO OSWALDO GASTON', 'root@localhost', '2025-07-10 01:00:45'),
(32, 'docentes', 13, 'INSERT', 'Nuevo docente insertado: MERO BAQUERIZO CESAR ANDRES', 'root@localhost', '2025-07-10 01:00:45'),
(33, 'docentes', 14, 'INSERT', 'Nuevo docente insertado: ORDOÑEZ VALENCIA MAYLEE LISBETH', 'root@localhost', '2025-07-10 01:00:45'),
(34, 'docentes', 15, 'INSERT', 'Nuevo docente insertado: MINDA GILCES DIANA ELIZABETH', 'root@localhost', '2025-07-10 01:00:45'),
(35, 'docentes', 16, 'INSERT', 'Nuevo docente insertado: DAVILA MACIAS ARACELI MARITZA', 'root@localhost', '2025-07-10 01:00:45'),
(36, 'docentes', 17, 'INSERT', 'Nuevo docente insertado: VALENCIA MARTINEZ NELLY AMERICA', 'root@localhost', '2025-07-10 01:00:45'),
(37, 'docentes', 18, 'INSERT', 'Nuevo docente insertado: CALERO VILLARREAL RICHARD MANOLO', 'root@localhost', '2025-07-10 01:00:45'),
(38, 'docentes', 19, 'INSERT', 'Nuevo docente insertado: CRUZ CHOEZ ANGELICA MARIA', 'root@localhost', '2025-07-10 01:00:45'),
(39, 'docentes', 20, 'INSERT', 'Nuevo docente insertado: RAMOS MOSQUERA BOLIVAR', 'root@localhost', '2025-07-10 01:00:45'),
(40, 'docentes', 21, 'INSERT', 'Nuevo docente insertado: LOPEZ CARVAJAL GLOBER GIOVANNY', 'root@localhost', '2025-07-10 01:00:45'),
(41, 'docentes', 22, 'INSERT', 'Nuevo docente insertado: GARCIA ARIAS PEDRO MANUEL', 'root@localhost', '2025-07-10 01:00:45'),
(42, 'docentes', 23, 'INSERT', 'Nuevo docente insertado: VERA CARRIEL BORIS TEOFILO', 'root@localhost', '2025-07-10 01:00:45'),
(43, 'docentes', 24, 'INSERT', 'Nuevo docente insertado: MERCHAN MOSQUERA LUIS ALBERTO', 'root@localhost', '2025-07-10 01:00:45'),
(44, 'docentes', 25, 'INSERT', 'Nuevo docente insertado: VARGAS CAICEDO NOEMI ESTEFANIA', 'root@localhost', '2025-07-10 01:00:45'),
(45, 'docentes', 26, 'INSERT', 'Nuevo docente insertado: LARA GAVILANEZ HECTOR RAUL', 'root@localhost', '2025-07-10 01:00:45'),
(46, 'docentes', 27, 'INSERT', 'Nuevo docente insertado: GALARZA SOLEDISPA MARIA ISABEL', 'root@localhost', '2025-07-10 01:00:45'),
(47, 'docentes', 28, 'INSERT', 'Nuevo docente insertado: CRUZ NAVARRETE EDISON LUIS', 'root@localhost', '2025-07-10 01:00:45'),
(48, 'docentes', 29, 'INSERT', 'Nuevo docente insertado: ALCIVAR MALDONADO TATIANA MABEL', 'root@localhost', '2025-07-10 01:00:45'),
(49, 'docentes', 30, 'INSERT', 'Nuevo docente insertado: CEVALLOS ZHUNIO JORGE EDUARDO', 'root@localhost', '2025-07-10 01:00:45'),
(50, 'docentes', 31, 'INSERT', 'Nuevo docente insertado: RUATA AVILES SILVIA ADRIANA', 'root@localhost', '2025-07-10 01:00:45'),
(51, 'docentes', 32, 'INSERT', 'Nuevo docente insertado: ALVAREZ SOLIS FRANCISCO XAVIER', 'root@localhost', '2025-07-10 01:00:45'),
(52, 'docentes', 33, 'INSERT', 'Nuevo docente insertado: BEJARANO OSPINA LUZ MARINA', 'root@localhost', '2025-07-10 01:00:45'),
(53, 'docentes', 34, 'INSERT', 'Nuevo docente insertado: GARZON RODAS MAURICIO FERNANDO', 'root@localhost', '2025-07-10 01:00:45'),
(54, 'docentes', 35, 'INSERT', 'Nuevo docente insertado: MOLINA CALDERON MIGUEL ALFONSO', 'root@localhost', '2025-07-10 01:00:45'),
(55, 'docentes', 36, 'INSERT', 'Nuevo docente insertado: ABAD SACOTO KARLA YADIRA', 'root@localhost', '2025-07-10 01:00:45'),
(56, 'docentes', 37, 'INSERT', 'Nuevo docente insertado: HECHAVARRIA HERNANDEZ JESÚS RAFAEL', 'root@localhost', '2025-07-10 01:00:45'),
(57, 'docentes', 38, 'INSERT', 'Nuevo docente insertado: ORTIZ ZAMBRANO MIRELLA CARMINA', 'root@localhost', '2025-07-10 01:00:45'),
(58, 'docentes', 39, 'INSERT', 'Nuevo docente insertado: SARMIENTO LAVAYEN ROBERTO CARLOS', 'root@localhost', '2025-07-10 01:00:45'),
(59, 'docentes', 40, 'INSERT', 'Nuevo docente insertado: CASTRO MARIDUEÑA ADRIANA MARIA', 'root@localhost', '2025-07-10 01:00:45'),
(60, 'docentes', 41, 'INSERT', 'Nuevo docente insertado: MACIAS YANQUI OSCAR ALBERTO', 'root@localhost', '2025-07-10 01:00:45'),
(61, 'docentes', 42, 'INSERT', 'Nuevo docente insertado: GONZALES QUIÑONEZ ROSA ELENA', 'root@localhost', '2025-07-10 01:00:45'),
(62, 'docentes', 43, 'INSERT', 'Nuevo docente insertado: COLLANTES FARAH ALEX ROBERTO', 'root@localhost', '2025-07-10 01:00:45'),
(63, 'docentes', 44, 'INSERT', 'Nuevo docente insertado: CALDERON GAVILANES MARLON ADRIAN', 'root@localhost', '2025-07-10 01:00:45'),
(64, 'docentes', 45, 'INSERT', 'Nuevo docente insertado: SANTOS DIAZ LILIA BEATRIZ', 'root@localhost', '2025-07-10 01:00:45'),
(65, 'docentes', 46, 'INSERT', 'Nuevo docente insertado: REYES WAGNIO MANUEL FABRICIO', 'root@localhost', '2025-07-10 01:00:45'),
(66, 'docentes', 47, 'INSERT', 'Nuevo docente insertado: RAMIREZ VELIZ RICARDO BOLIVAR', 'root@localhost', '2025-07-10 01:00:45'),
(67, 'docentes', 48, 'INSERT', 'Nuevo docente insertado: CHÁVEZ CUJILÁN YELENA TAMARA', 'root@localhost', '2025-07-10 01:00:45'),
(68, 'docentes', 49, 'INSERT', 'Nuevo docente insertado: CEVALLOS TORRES LORENZO JOVANNY', 'root@localhost', '2025-07-10 01:00:45'),
(69, 'docentes', 50, 'INSERT', 'Nuevo docente insertado: CEVALLOS ORDOÑEZ ROSA BELEN', 'root@localhost', '2025-07-10 01:00:45'),
(70, 'docentes', 51, 'INSERT', 'Nuevo docente insertado: LOPEZDOMINGUEZ RIVAS LEILI GENOVEVA', 'root@localhost', '2025-07-10 01:00:45'),
(71, 'docentes', 52, 'INSERT', 'Nuevo docente insertado: YANZA MONTALVAN ANGELA OLIVIA', 'root@localhost', '2025-07-10 01:00:45'),
(72, 'docentes', 53, 'INSERT', 'Nuevo docente insertado: ESPINOZA ORTIZ JULIANA MARLENE', 'root@localhost', '2025-07-10 01:00:45'),
(73, 'docentes', 54, 'INSERT', 'Nuevo docente insertado: GUERRERO ARMENDÁRIZ PEDRO JAVIER', 'root@localhost', '2025-07-10 01:00:45'),
(74, 'docentes', 55, 'INSERT', 'Nuevo docente insertado: LEYVA VASQUEZ MAIKEL YELANDI', 'root@localhost', '2025-07-10 01:00:45'),
(75, 'docentes', 56, 'INSERT', 'Nuevo docente insertado: BARZOLA MUÑOZ EDDY VICENTE', 'root@localhost', '2025-07-10 01:00:45'),
(76, 'docentes', 57, 'INSERT', 'Nuevo docente insertado: CRESPO LEON CHRISTOPHER GABRIEL', 'root@localhost', '2025-07-10 01:00:45'),
(77, 'docentes', 58, 'INSERT', 'Nuevo docente insertado: VIVAS LUCAS CARLOS FELICIANO', 'root@localhost', '2025-07-10 01:00:45'),
(78, 'docentes', 59, 'INSERT', 'Nuevo docente insertado: JACOME MORALES GLADYS CRISTINA', 'root@localhost', '2025-07-10 01:00:45'),
(79, 'docentes', 60, 'INSERT', 'Nuevo docente insertado: REYES ZAMBRANO GARY XAVIER', 'root@localhost', '2025-07-10 01:00:45'),
(80, 'docentes', 61, 'INSERT', 'Nuevo docente insertado: GUIJARRO RODRIGUEZ ALFONSO ANIBAL', 'root@localhost', '2025-07-10 01:00:45'),
(81, 'docentes', 62, 'INSERT', 'Nuevo docente insertado: CEDEÑO RODRIGUEZ JUAN CARLOS', 'root@localhost', '2025-07-10 01:00:45'),
(82, 'docentes', 63, 'INSERT', 'Nuevo docente insertado: ESPIN RIOFRIO CESAR HUMBERTO', 'root@localhost', '2025-07-10 01:00:45'),
(83, 'docentes', 64, 'INSERT', 'Nuevo docente insertado: ALARCON SALVATIERRA JOSE ABEL', 'root@localhost', '2025-07-10 01:00:45'),
(84, 'docentes', 65, 'INSERT', 'Nuevo docente insertado: AVILES MONROY JORGE ISAAC', 'root@localhost', '2025-07-10 01:00:45'),
(85, 'docentes', 66, 'INSERT', 'Nuevo docente insertado: VARELA TAPIA ELEANOR ALEXANDRA', 'root@localhost', '2025-07-10 01:00:45'),
(86, 'docentes', 67, 'INSERT', 'Nuevo docente insertado: RODRIGUEZ REVELO ELSY', 'root@localhost', '2025-07-10 01:00:45'),
(87, 'docentes', 68, 'INSERT', 'Nuevo docente insertado: ORTIZ ZAMBRANO JENNY ALEXANDRA', 'root@localhost', '2025-07-10 01:00:45'),
(88, 'docentes', 69, 'INSERT', 'Nuevo docente insertado: MENDOZA MORAN VERONICA DEL ROCIO', 'root@localhost', '2025-07-10 01:00:45'),
(89, 'docentes', 70, 'INSERT', 'Nuevo docente insertado: NUÑEZ GAIBOR JEFFERSON ELIAS', 'root@localhost', '2025-07-10 01:00:45'),
(90, 'docentes', 71, 'INSERT', 'Nuevo docente insertado: ARTEAGA YAGUAR ELVIS RUDDY', 'root@localhost', '2025-07-10 01:00:45'),
(91, 'docentes', 72, 'INSERT', 'Nuevo docente insertado: GARCIA ENRIQUEZ MYRIAM CECILIA', 'root@localhost', '2025-07-10 01:00:45'),
(92, 'docentes', 73, 'INSERT', 'Nuevo docente insertado: PARRALES BRAVO FRANKLIN RICARDO', 'root@localhost', '2025-07-10 01:00:45'),
(93, 'docentes', 74, 'INSERT', 'Nuevo docente insertado: ZUMBA GAMBOA JOHANNA', 'root@localhost', '2025-07-10 01:00:45'),
(94, 'docentes', 75, 'INSERT', 'Nuevo docente insertado: CASTRO AGUILAR GILBERTO FERNANDO', 'root@localhost', '2025-07-10 01:00:45'),
(95, 'docentes', 76, 'INSERT', 'Nuevo docente insertado: SALTOS SANTANA GISSELA MONSERRATE', 'root@localhost', '2025-07-10 01:00:45'),
(96, 'docentes', 77, 'INSERT', 'Nuevo docente insertado: SANCHEZ PAZMIÑO DIANA PRISCILA', 'root@localhost', '2025-07-10 01:00:45'),
(97, 'docentes', 78, 'INSERT', 'Nuevo docente insertado: BARBA SALAZAR JOEL ALEJANDRO', 'root@localhost', '2025-07-10 01:00:45'),
(98, 'docentes', 79, 'INSERT', 'Nuevo docente insertado: PERALTA GUARACA TANIA', 'root@localhost', '2025-07-10 01:00:45'),
(99, 'docentes', 80, 'INSERT', 'Nuevo docente insertado: CHARCO AGUIRRE JORGE LUIS', 'root@localhost', '2025-07-10 01:00:45'),
(100, 'docentes', 81, 'INSERT', 'Nuevo docente insertado: MARTILLO ALCIVAR INELDA ANABELLE', 'root@localhost', '2025-07-10 01:00:45'),
(101, 'docentes', 82, 'INSERT', 'Nuevo docente insertado: TEJADA YEPEZ SILVIA LILIANA', 'root@localhost', '2025-07-10 01:00:45'),
(102, 'docentes', 83, 'INSERT', 'Nuevo docente insertado: PATIÑO PEREZ DARWIN', 'root@localhost', '2025-07-10 01:00:45'),
(103, 'docentes', 84, 'INSERT', 'Nuevo docente insertado: BENAVIDES LOPEZ DAVID GONZALO', 'root@localhost', '2025-07-10 01:00:45'),
(104, 'docentes', 85, 'INSERT', 'Nuevo docente insertado: GUAMAN TUMBACO ANA LOURDES', 'root@localhost', '2025-07-10 01:00:45'),
(105, 'adaptaciones_metodologicas', 1, 'UPDATE', 'Adaptaciones metodológicas modificadas', 'root@localhost', '2025-07-10 01:00:46'),
(106, 'docentes', 1, 'UPDATE', 'Docente modificado: JACOME MORALES GLADYS CRISTINA', 'root@localhost', '2025-07-10 01:00:46'),
(107, 'adaptaciones_metodologicas', 2, 'UPDATE', 'Adaptaciones metodológicas modificadas', 'root@localhost', '2025-07-10 01:00:46'),
(108, 'docentes', 2, 'UPDATE', 'Docente modificado: GUIJARRO RODRIGUEZ ALFONSO ANIBAL', 'root@localhost', '2025-07-10 01:00:46'),
(109, 'adaptaciones_metodologicas', 3, 'UPDATE', 'Adaptaciones metodológicas modificadas', 'root@localhost', '2025-07-10 01:00:46'),
(110, 'docentes', 3, 'UPDATE', 'Docente modificado: CRUZ NAVARRETE EDISON LUIS', 'root@localhost', '2025-07-10 01:00:46'),
(111, 'adaptaciones_metodologicas', 5, 'UPDATE', 'Adaptaciones metodológicas modificadas', 'root@localhost', '2025-07-10 01:00:46'),
(112, 'docentes', 5, 'UPDATE', 'Docente modificado: COLLANTES FARAH ALEX ROBERTO', 'root@localhost', '2025-07-10 01:00:46'),
(113, 'adaptaciones_metodologicas', 6, 'UPDATE', 'Adaptaciones metodológicas modificadas', 'root@localhost', '2025-07-10 01:00:46'),
(114, 'docentes', 6, 'UPDATE', 'Docente modificado: GARCIA ENRIQUEZ MYRIAM CECILIA', 'root@localhost', '2025-07-10 01:00:46'),
(115, 'adaptaciones_metodologicas', 9, 'UPDATE', 'Adaptaciones metodológicas modificadas', 'root@localhost', '2025-07-10 01:00:46'),
(116, 'docentes', 9, 'UPDATE', 'Docente modificado: CASTRO MORALES MARÍA FERNANDA', 'root@localhost', '2025-07-10 01:00:46'),
(117, 'adaptaciones_metodologicas', 10, 'UPDATE', 'Adaptaciones metodológicas modificadas', 'root@localhost', '2025-07-10 01:00:46'),
(118, 'docentes', 10, 'UPDATE', 'Docente modificado: GILCES TUTIVEN WILSON', 'root@localhost', '2025-07-10 01:00:46'),
(119, 'cache_completo', 0, 'UPDATE', 'Recálculo completo de caché ejecutado', 'root@localhost', '2025-07-10 01:00:47');

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
(10, 'Cálculo Integral', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-20 06:06:58'),
(11, 'ALGORITMOS Y LÓGICA DE PROGRAMACIÓN', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(12, 'CÁLCULO DIFERENCIAL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(13, 'DEMOCRACIA, CIUDADANÍA Y GLOBALIZACIÓN', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(14, 'ESTRUCTURAS DISCRETAS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(15, 'INTRODUCCIÓN A INGENIERÍA DE SOFTWARE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(16, 'LENGUAJE Y COMUNICACIÓN', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(17, 'INGLÉS I', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(18, 'ALGEBRA LINEAL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(19, 'CONTABILIDAD', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(20, 'CÁLCULO INTEGRAL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(21, 'METODOLOGÍA DE LA INVESTIGACIÓN I', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(22, 'ORGANIZACIÓN Y ARQUITECTURA COMPUTACIONAL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(23, 'PROGRAMACIÓN ORIENTADA A OBJETOS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(24, 'INGLÉS II', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(25, 'ESTRUCTURA DE DATOS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(26, 'INGENIERÍA DE REQUERIMIENTOS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(27, 'PROCESO DE SOFTWARE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(28, 'SISTEMAS OPERATIVOS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(29, 'ESTADÍSTICA I', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(30, 'INGLÉS III', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(31, 'INVESTIGACIÓN DE OPERACIONES', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(32, 'MODELAMIENTO DE SOFTWARE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(33, 'BASE DE DATOS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(34, 'ESTADÍSTICA II', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(35, 'REDES DE COMPUTADORAS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(36, 'DISEÑO Y ARQUITECTURA DE SOFTWARE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(37, 'FINANZAS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(38, 'INTERACCIÓN HOMBRE-MÁQUINA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(39, 'METODOLOGÍA DE LA INVESTIGACIÓN II', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(40, 'PROGRAMACIÓN ORIENTADA A EVENTOS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(41, 'BASE DE DATOS AVANZADO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(42, 'COMPORTAMIENTO ORGANIZACIONAL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(43, 'CONSTRUCCIÓN DE SOFTWARE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(44, 'DESARROLLO DE APLICACIONES WEB', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(45, 'DISEÑO DE EXPERIENCIA DE USUARIO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(46, 'CALIDAD DEL SOFTWARE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(47, 'DESARROLLO DE APLICACIONES WEB AVANZADO', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(48, 'INTELIGENCIA DE NEGOCIOS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(49, 'MARCO LEGAL DE LA PROFESIÓN', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(50, 'DESARROLLO DE APLICACIONES MÓVILES', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(51, 'EMPRENDIMIENTO E INNOVACIÓN', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(52, 'SEGURIDAD INFORMÁTICA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(53, 'VERIFICACIÓN Y VALIDACIÓN DE SOFTWARE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(54, 'APLICACIONES DISTRIBUIDAS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(55, 'ELABORACIÓN DE PROYECTOS', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(56, 'GESTIÓN DE LA CONFIGURACIÓN DEL SOFTWARE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(57, 'INTELIGENCIA ARTIFICIAL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(58, 'AUDITORÍA DE SOFTWARE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(59, 'GESTIÓN DE PROYECTOS DE SOFTWARE', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45'),
(60, 'SISTEMAS DE INFORMACIÓN GERENCIAL', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-07-10 01:00:45');

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
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `nombre_completo` varchar(255) NOT NULL,
  `rol` enum('admin','coordinador','docente') DEFAULT 'coordinador',
  `activo` tinyint(1) DEFAULT 1,
  `ultimo_acceso` timestamp NULL DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`),
  ADD KEY `idx_usuario_activo` (`usuario`,`activo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `adaptaciones_metodologicas`
--
ALTER TABLE `adaptaciones_metodologicas`
  MODIFY `id_adaptacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  MODIFY `id_asignacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT de la tabla `asignaciones_historial`
--
ALTER TABLE `asignaciones_historial`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT de la tabla `cache_puntuaciones_ahp`
--
ALTER TABLE `cache_puntuaciones_ahp`
  MODIFY `id_cache` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT de la tabla `cache_puntuaciones_especificas`
--
ALTER TABLE `cache_puntuaciones_especificas`
  MODIFY `id_cache_especifico` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=602;

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
  MODIFY `id_docente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT de la tabla `experiencia_docente_discapacidad`
--
ALTER TABLE `experiencia_docente_discapacidad`
  MODIFY `id_experiencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=426;

--
-- AUTO_INCREMENT de la tabla `limites_asignacion`
--
ALTER TABLE `limites_asignacion`
  MODIFY `id_limite` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT de la tabla `log_actualizaciones_cache`
--
ALTER TABLE `log_actualizaciones_cache`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id_materia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

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
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
