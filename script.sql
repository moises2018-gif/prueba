-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-06-2025 a las 19:16:08
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

--
-- Funciones
--
CREATE DEFINER=`root`@`localhost` FUNCTION `recomendar_docente_equilibrado` (`tipo_discapacidad` INT, `facultad_estudiante` VARCHAR(255)) RETURNS LONGTEXT CHARSET utf8mb4 COLLATE utf8mb4_bin DETERMINISTIC READS SQL DATA BEGIN
    DECLARE resultado JSON;
    DECLARE done INT DEFAULT FALSE;
    DECLARE docente_id INT;
    DECLARE docente_nombre VARCHAR(255);
    DECLARE puntuacion_ahp DECIMAL(5,3);
    DECLARE capacidad_restante INT;
    DECLARE puede_tipo BOOLEAN;
    
    -- Cursor para docentes disponibles ordenados por equilibrio de carga
    DECLARE cur CURSOR FOR
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
        FROM vista_distribucion_carga vdc
        JOIN vista_ranking_ahp_especifico vra ON vdc.id_docente = vra.id_docente
        WHERE vdc.capacidad_restante > 0
        AND vra.id_tipo_discapacidad = tipo_discapacidad
        AND vra.facultad = facultad_estudiante
        HAVING puede_tipo_discapacidad = TRUE
        ORDER BY 
            vdc.porcentaje_carga ASC,  -- Priorizar docentes con menor carga
            vra.puntuacion_especifica_discapacidad DESC  -- Luego por competencia AHP
        LIMIT 1;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO docente_id, docente_nombre, puntuacion_ahp, capacidad_restante, puede_tipo;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET resultado = JSON_OBJECT(
            'id_docente', docente_id,
            'nombre_docente', docente_nombre,
            'puntuacion_ahp', puntuacion_ahp,
            'capacidad_restante', capacidad_restante,
            'puede_tipo', puede_tipo
        );
    END LOOP;
    CLOSE cur;
    
    IF resultado IS NULL THEN
        SET resultado = JSON_OBJECT(
            'error', 'No hay docentes disponibles con capacidad para este tipo de discapacidad'
        );
    END IF;
    
    RETURN resultado;
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
(9, 9, 1, 1, 1, 1, 'Adaptaciones automáticas por formación en inclusión');

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
(1, 8, 1, '2025-1', 'Base de Datos', 1, 1.066, 'Activa', '2025-06-13 07:34:38', 1, 6),
(2, 8, 3, '2025-1', 'Base de Datos', 1, 1.055, 'Activa', '2025-06-13 07:34:38', 3, 6),
(3, 8, 4, '2025-1', 'Matemáticas Discretas', 1, 0.914, 'Activa', '2025-06-13 07:34:38', 19, 1),
(4, 8, 4, '2025-1', 'Química', 1, 0.914, 'Activa', '2025-06-13 07:34:38', 4, 8),
(5, 8, 2, '2025-1', 'Programación I', 1, 0.900, 'Activa', '2025-06-13 07:34:38', 2, 2),
(6, 8, 5, '2025-1', 'Química', 1, 0.895, 'Activa', '2025-06-13 07:34:38', 5, 8),
(7, 8, 5, '2025-1', 'Química', 1, 0.895, 'Activa', '2025-06-13 07:34:38', 20, 8);

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
(1, 'Formación Específica en Inclusión', 'FSI', 0.280, 'Capacitaciones y formación específica en NEE', '2025-06-13 07:04:30'),
(2, 'Experiencia Práctica con NEE', 'EPR', 0.320, 'Años de experiencia trabajando con estudiantes NEE - CRITERIO MÁS IMPORTANTE', '2025-06-13 07:04:30'),
(3, 'Adaptaciones Metodológicas Implementadas', 'AMI', 0.160, 'Modificaciones realizadas en la metodología de enseñanza', '2025-06-13 07:04:30'),
(4, 'Años de Experiencia Docente General', 'AED', 0.130, 'Experiencia total como docente', '2025-06-13 07:04:30'),
(5, 'Nivel de Formación Académica', 'NFA', 0.110, 'Títulos de tercer y cuarto nivel', '2025-06-13 07:04:30');

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
(1, 'JACOME MORALES GLADYS CRISTINA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', 'Más de 10 años', 'Ingeniero en Computación', 'Doctor en Ciencias Pedagógicas', 1, '1 a 5 estudiantes', 5, 8, '2025-06-13 07:04:31', '2025-06-13 07:04:31'),
(2, 'ALFONSO ANÍBAL GUIJARRO RODRÍGUEZ', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero sistemas computacionales', 'Master en ciberseguridad, Master en administración de empresas', 1, '1 a 5 estudiantes', 3, 6, '2025-06-13 07:04:31', '2025-06-13 07:04:31'),
(3, 'Edison Luis Cruz Navarrete', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniero en software', NULL, 0, '1 a 5 estudiantes', 0, 4, '2025-06-13 07:04:31', '2025-06-13 07:04:31'),
(4, 'Tatiana Mabel Alcivar Maldonado', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Ing en contabilidad y Auditoría', 'Master en administración de empresas', 0, '1 a 5 estudiantes', 1, 2, '2025-06-13 07:04:31', '2025-06-13 07:04:31'),
(5, 'Alex Roberto Collantes Farah', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero en Sistemas Computacionales', 'Master en administración de empresas', 1, '6 a 10 estudiantes', 4, 7, '2025-06-13 07:04:31', '2025-06-13 07:04:31'),
(6, 'Myriam Garcia', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniero en Computación', NULL, 0, '1 a 5 estudiantes', 0, 1, '2025-06-13 07:04:31', '2025-06-13 07:04:31'),
(7, 'Carlos Mendoza Silva', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniero en Software', 'Master en Educación Especial', 1, '1 a 5 estudiantes', 6, 5, '2025-06-13 07:04:31', '2025-06-13 07:04:31'),
(8, 'Ana Patricia Loor Cedeño', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero en Sistemas', 'Doctor en Educación Inclusiva', 1, '1 a 5 estudiantes', 8, 9, '2025-06-13 07:04:31', '2025-06-13 07:04:31'),
(9, 'MARÍA FERNANDA CASTRO MORALES', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Licenciada en Psicología Educativa', 'Master en Educación Especial y Inclusiva', 1, '6 a 10 estudiantes', 4, 6, '2025-06-13 07:06:03', '2025-06-13 07:06:03');

--
-- Disparadores `docentes`
--
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
        -- Valores inteligentes según formación
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
    -- Psicosocial (ID=1) - Prioritario
    (NEW.id_docente, 1, 
     CASE WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años > 0 THEN 1 ELSE 0 END,
     CASE WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años > 0 THEN LEAST(NEW.experiencia_nee_años, 3) ELSE 0 END,
     CASE WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años >= 5 THEN 'Avanzado'
          WHEN NEW.formacion_inclusion = 1 AND NEW.experiencia_nee_años > 0 THEN 'Intermedio'
          ELSE 'Básico' END,
     'Generado automáticamente por trigger'),
     
    -- Auditiva (ID=2)
    (NEW.id_docente, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
    
    -- Intelectual (ID=3) - Prioritario
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
(1, 'Ana María López García', 1, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32'),
(2, 'Carlos Andrés Pérez Rojas', 2, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32'),
(3, 'Sofía Alejandra Gómez Martínez', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32'),
(4, 'Juan Pablo Morales Vargas', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32'),
(5, 'Lucía Fernanda Torres Cruz', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32'),
(6, 'Diego Armando Sánchez Díaz', 1, '2025-1', 'FACULTAD DE CIENCIAS SOCIALES Y HUMANAS', '2025-06-13 07:04:32'),
(7, 'Valeria Isabel Ramírez Ortiz', 2, '2025-1', 'FACULTAD DE CIENCIAS SOCIALES Y HUMANAS', '2025-06-13 07:04:32'),
(8, 'Miguel Ángel Castro Paredes', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32'),
(9, 'Camila Estefanía Ruiz Salazar', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32'),
(10, 'Andrés Felipe Mendoza López', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32'),
(11, 'Laura Daniela Chávez Morales', 1, '2025-1', 'FACULTAD DE INGENIERÍA', '2025-06-13 07:04:32'),
(12, 'Gabriel Esteban Flores Gómez', 2, '2025-1', 'FACULTAD DE INGENIERÍA', '2025-06-13 07:04:32'),
(13, 'Mariana Sofía Herrera Vásquez', 3, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32'),
(14, 'Sebastián Alejandro Ortiz Peña', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32'),
(15, 'Paula Valentina Rojas Castro', 5, '2025-1', 'FACULTAD DE CIENCIAS SOCIALES Y HUMANAS', '2025-06-13 07:04:32'),
(16, 'Julián David Vargas Sánchez', 1, '2025-1', 'FACULTAD DE CIENCIAS SOCIALES Y HUMANAS', '2025-06-13 07:04:32'),
(17, 'Catalina María Díaz Torres', 2, '2025-1', 'FACULTAD DE INGENIERÍA', '2025-06-13 07:04:32'),
(18, 'Felipe Nicolás Martínez Cruz', 3, '2025-1', 'FACULTAD DE INGENIERÍA', '2025-06-13 07:04:32'),
(19, 'Isabella Fernanda Salazar Ramírez', 4, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32'),
(20, 'Tomás Ignacio Paredes Gómez', 5, '2025-1', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-06-13 07:04:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluaciones_ahp`
--

CREATE TABLE `evaluaciones_ahp` (
  `id_evaluacion` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_criterio` int(11) NOT NULL,
  `puntuacion_criterio` decimal(5,3) NOT NULL,
  `puntuacion_final` decimal(5,3) NOT NULL,
  `fecha_evaluacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 1, 1, 1, 5, 'Avanzado', 'Actualizado manualmente el 2025-06-13 07:04:32'),
(2, 1, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(3, 1, 3, 1, 6, 'Experto', 'Actualizado manualmente el 2025-06-13 07:04:32'),
(4, 1, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(5, 1, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(6, 2, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(7, 2, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(8, 2, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(9, 2, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(10, 2, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(11, 3, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(12, 3, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(13, 3, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(14, 3, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(15, 3, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(16, 4, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(17, 4, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(18, 4, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(19, 4, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(20, 4, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(21, 5, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(22, 5, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(23, 5, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(24, 5, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(25, 5, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(26, 6, 1, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(27, 6, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(28, 6, 3, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(29, 6, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(30, 6, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(31, 7, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(32, 7, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(33, 7, 3, 1, 5, 'Experto', 'Actualizado manualmente el 2025-06-13 07:04:32'),
(34, 7, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(35, 7, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(36, 8, 1, 1, 7, 'Experto', 'Actualizado manualmente el 2025-06-13 07:04:32'),
(37, 8, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(38, 8, 3, 1, 8, 'Experto', 'Actualizado manualmente el 2025-06-13 07:04:32'),
(39, 8, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(40, 8, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(41, 9, 1, 1, 3, 'Avanzado', 'Generado automáticamente por trigger'),
(42, 9, 2, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(43, 9, 3, 1, 2, 'Intermedio', 'Generado automáticamente por trigger'),
(44, 9, 4, 0, 0, 'Básico', 'Generado automáticamente por trigger'),
(45, 9, 5, 0, 0, 'Básico', 'Generado automáticamente por trigger');

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
(1, 1, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=8 años', '2025-06-13 07:43:13'),
(2, 2, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=6 años', '2025-06-13 07:43:13'),
(3, 3, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=4 años', '2025-06-13 07:43:13'),
(4, 4, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=2 años', '2025-06-13 07:43:13'),
(5, 5, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=7 años', '2025-06-13 07:43:13'),
(6, 6, 3, 2, NULL, 'Límites automáticos basados en perfil: formación=0, experiencia=1 años', '2025-06-13 07:43:13'),
(7, 7, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=5 años', '2025-06-13 07:43:13'),
(8, 8, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=9 años', '2025-06-13 07:43:13'),
(9, 9, 7, 3, NULL, 'Límites automáticos basados en perfil: formación=1, experiencia=6 años', '2025-06-13 07:43:13');

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
(1, 'Matemáticas Discretas', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(2, 'Programación I', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(3, 'Física I', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(4, 'Cálculo Diferencial', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(5, 'Álgebra Lineal', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(6, 'Base de Datos', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(7, 'Estadística', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(8, 'Química', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(9, 'Programación II', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(10, 'Cálculo Integral', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(11, 'Estructuras de Datos', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(12, 'Sistemas Operativos', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(13, 'Redes de Computadoras', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31'),
(14, 'Ingeniería de Software', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', '2025-1', '2025-06-13 07:04:31');

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
(1, 1, 1, 0.260, 'FSI para Psicosocial: 26%', '2025-06-13 07:04:30'),
(2, 1, 2, 0.500, 'EPR para Psicosocial: 50% - CRÍTICO para manejo emocional', '2025-06-13 07:04:30'),
(3, 1, 3, 0.130, 'AMI para Psicosocial: 13%', '2025-06-13 07:04:30'),
(4, 1, 4, 0.070, 'AED para Psicosocial: 7%', '2025-06-13 07:04:30'),
(5, 1, 5, 0.030, 'NFA para Psicosocial: 3%', '2025-06-13 07:04:30'),
(6, 2, 1, 0.080, 'FSI para Auditiva: 8%', '2025-06-13 07:04:30'),
(7, 2, 2, 0.420, 'EPR para Auditiva: 42% - Experiencia práctica fundamental', '2025-06-13 07:04:30'),
(8, 2, 3, 0.090, 'AMI para Auditiva: 9%', '2025-06-13 07:04:30'),
(9, 2, 4, 0.270, 'AED para Auditiva: 27%', '2025-06-13 07:04:30'),
(10, 2, 5, 0.130, 'NFA para Auditiva: 13%', '2025-06-13 07:04:30'),
(11, 3, 1, 0.460, 'FSI para Intelectual: 46% - Formación especializada fundamental', '2025-06-13 07:04:30'),
(12, 3, 2, 0.200, 'EPR para Intelectual: 20%', '2025-06-13 07:04:30'),
(13, 3, 3, 0.200, 'AMI para Intelectual: 20% - Adaptaciones curriculares necesarias', '2025-06-13 07:04:30'),
(14, 3, 4, 0.090, 'AED para Intelectual: 9%', '2025-06-13 07:04:30'),
(15, 3, 5, 0.040, 'NFA para Intelectual: 4%', '2025-06-13 07:04:30'),
(16, 4, 1, 0.170, 'FSI para Visual: 17%', '2025-06-13 07:04:30'),
(17, 4, 2, 0.060, 'EPR para Visual: 6%', '2025-06-13 07:04:30'),
(18, 4, 3, 0.110, 'AMI para Visual: 11%', '2025-06-13 07:04:30'),
(19, 4, 4, 0.250, 'AED para Visual: 25%', '2025-06-13 07:04:30'),
(20, 4, 5, 0.410, 'NFA para Visual: 41% - Formación técnica crucial', '2025-06-13 07:04:30'),
(21, 5, 1, 0.160, 'FSI para Física: 16%', '2025-06-13 07:04:30'),
(22, 5, 2, 0.060, 'EPR para Física: 6%', '2025-06-13 07:04:30'),
(23, 5, 3, 0.440, 'AMI para Física: 44% - Adaptaciones metodológicas críticas', '2025-06-13 07:04:30'),
(24, 5, 4, 0.260, 'AED para Física: 26%', '2025-06-13 07:04:30'),
(25, 5, 5, 0.070, 'NFA para Física: 7%', '2025-06-13 07:04:30');

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
(1, 'Psicosocial', 0.400, 'Discapacidad relacionada con aspectos psicológicos y sociales - MAYOR PRIORIDAD', '2025-06-13 07:04:30'),
(2, 'Auditiva', 0.100, 'Discapacidad relacionada con la audición', '2025-06-13 07:04:30'),
(3, 'Intelectual', 0.300, 'Discapacidad relacionada con el desarrollo intelectual - SEGUNDA PRIORIDAD', '2025-06-13 07:04:30'),
(4, 'Visual', 0.150, 'Discapacidad relacionada con la visión', '2025-06-13 07:04:30'),
(5, 'Física', 0.050, 'Discapacidad relacionada con la movilidad física - MENOR PRIORIDAD', '2025-06-13 07:04:30');

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
-- Estructura para la vista `vista_distribucion_carga`
--
DROP TABLE IF EXISTS `vista_distribucion_carga`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_distribucion_carga`  AS SELECT `d`.`id_docente` AS `id_docente`, `d`.`nombres_completos` AS `nombres_completos`, `d`.`formacion_inclusion` AS `formacion_inclusion`, `d`.`experiencia_nee_años` AS `experiencia_nee_años`, `la`.`maximo_estudiantes_nee` AS `maximo_estudiantes_nee`, `la`.`maximo_por_tipo_discapacidad` AS `maximo_por_tipo_discapacidad`, count(`a`.`id_asignacion`) AS `asignaciones_actuales`, count(case when `a`.`id_tipo_discapacidad` = 1 then 1 end) AS `psicosocial_actual`, count(case when `a`.`id_tipo_discapacidad` = 2 then 1 end) AS `auditiva_actual`, count(case when `a`.`id_tipo_discapacidad` = 3 then 1 end) AS `intelectual_actual`, count(case when `a`.`id_tipo_discapacidad` = 4 then 1 end) AS `visual_actual`, count(case when `a`.`id_tipo_discapacidad` = 5 then 1 end) AS `fisica_actual`, `la`.`maximo_estudiantes_nee`- count(`a`.`id_asignacion`) AS `capacidad_restante`, round(count(`a`.`id_asignacion`) / `la`.`maximo_estudiantes_nee` * 100,1) AS `porcentaje_carga`, CASE WHEN count(`a`.`id_asignacion`) >= `la`.`maximo_estudiantes_nee` THEN 'SOBRECARGADO' WHEN count(`a`.`id_asignacion`) >= `la`.`maximo_estudiantes_nee` * 0.8 THEN 'ALTA CARGA' WHEN count(`a`.`id_asignacion`) >= `la`.`maximo_estudiantes_nee` * 0.5 THEN 'CARGA MEDIA' WHEN count(`a`.`id_asignacion`) > 0 THEN 'CARGA BAJA' ELSE 'SIN CARGA' END AS `estado_carga` FROM ((`docentes` `d` left join `limites_asignacion` `la` on(`d`.`id_docente` = `la`.`id_docente`)) left join `asignaciones` `a` on(`d`.`id_docente` = `a`.`id_docente` and `a`.`estado` = 'Activa')) GROUP BY `d`.`id_docente`, `d`.`nombres_completos`, `d`.`formacion_inclusion`, `d`.`experiencia_nee_años`, `la`.`maximo_estudiantes_nee`, `la`.`maximo_por_tipo_discapacidad` ORDER BY round(count(`a`.`id_asignacion`) / `la`.`maximo_estudiantes_nee` * 100,1) DESC ;

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
  ADD KEY `id_materia` (`id_materia`);

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
  ADD KEY `id_tipo_discapacidad` (`id_tipo_discapacidad`);

--
-- Indices de la tabla `evaluaciones_ahp`
--
ALTER TABLE `evaluaciones_ahp`
  ADD PRIMARY KEY (`id_evaluacion`),
  ADD KEY `id_docente` (`id_docente`),
  ADD KEY `id_criterio` (`id_criterio`);

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
  MODIFY `id_adaptacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  MODIFY `id_asignacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `asignaciones_historial`
--
ALTER TABLE `asignaciones_historial`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id_docente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id_estudiante` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `evaluaciones_ahp`
--
ALTER TABLE `evaluaciones_ahp`
  MODIFY `id_evaluacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `experiencia_docente_discapacidad`
--
ALTER TABLE `experiencia_docente_discapacidad`
  MODIFY `id_experiencia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT de la tabla `limites_asignacion`
--
ALTER TABLE `limites_asignacion`
  MODIFY `id_limite` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id_materia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

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
-- Filtros para la tabla `evaluaciones_ahp`
--
ALTER TABLE `evaluaciones_ahp`
  ADD CONSTRAINT `evaluaciones_ahp_ibfk_1` FOREIGN KEY (`id_docente`) REFERENCES `docentes` (`id_docente`) ON DELETE CASCADE,
  ADD CONSTRAINT `evaluaciones_ahp_ibfk_2` FOREIGN KEY (`id_criterio`) REFERENCES `criterios_ahp` (`id_criterio`) ON DELETE CASCADE;

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
