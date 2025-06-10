-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS asignacion_docente;
USE asignacion_docente;

-- TABLA: DOCENTES
CREATE TABLE docentes (
    id_docente INT AUTO_INCREMENT PRIMARY KEY,
    nombres_completos VARCHAR(255) NOT NULL,
    facultad VARCHAR(255) NOT NULL,
    modalidad_enseñanza ENUM('Presencial', 'Virtual', 'Híbrida') NOT NULL,
    años_experiencia_docente ENUM('Menos de 1 año', '1 a 5 años', '6 a 10 años', 'Más de 10 años') NOT NULL,
    titulo_tercer_nivel VARCHAR(255) NOT NULL,
    titulo_cuarto_nivel VARCHAR(255) DEFAULT NULL,
    formacion_inclusion BOOLEAN DEFAULT FALSE,
    estudiantes_nee_promedio ENUM('Ninguno', '1 a 5 estudiantes', '6 a 10 estudiantes', 'Más de 10 estudiantes') DEFAULT 'Ninguno',
    capacitaciones_nee INT DEFAULT 0,
    experiencia_nee_años INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- TABLA: TIPOS DE DISCAPACIDAD
CREATE TABLE tipos_discapacidad (
    id_tipo_discapacidad INT AUTO_INCREMENT PRIMARY KEY,
    nombre_discapacidad VARCHAR(100) NOT NULL,
    peso_prioridad DECIMAL(5,3) NOT NULL,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- TABLA: CRITERIOS AHP
CREATE TABLE criterios_ahp (
    id_criterio INT AUTO_INCREMENT PRIMARY KEY,
    nombre_criterio VARCHAR(255) NOT NULL,
    codigo_criterio VARCHAR(10) NOT NULL,
    peso_criterio DECIMAL(5,3) NOT NULL,
    descripcion TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- TABLA: EXPERIENCIA DOCENTE POR TIPO DISCAPACIDAD
CREATE TABLE experiencia_docente_discapacidad (
    id_experiencia INT AUTO_INCREMENT PRIMARY KEY,
    id_docente INT NOT NULL,
    id_tipo_discapacidad INT NOT NULL,
    tiene_experiencia BOOLEAN DEFAULT FALSE,
    años_experiencia INT DEFAULT 0,
    nivel_competencia ENUM('Básico', 'Intermedio', 'Avanzado', 'Experto') DEFAULT 'Básico',
    observaciones TEXT,
    FOREIGN KEY (id_docente) REFERENCES docentes(id_docente) ON DELETE CASCADE,
    FOREIGN KEY (id_tipo_discapacidad) REFERENCES tipos_discapacidad(id_tipo_discapacidad) ON DELETE CASCADE
);

-- TABLA: ADAPTACIONES METODOLÓGICAS
CREATE TABLE adaptaciones_metodologicas (
    id_adaptacion INT AUTO_INCREMENT PRIMARY KEY,
    id_docente INT NOT NULL,
    modificacion_contenido BOOLEAN DEFAULT FALSE,
    uso_recursos_tecnologicos BOOLEAN DEFAULT FALSE,
    adaptacion_metodologia BOOLEAN DEFAULT FALSE,
    coordinacion_servicios_apoyo BOOLEAN DEFAULT FALSE,
    otras_adaptaciones TEXT,
    FOREIGN KEY (id_docente) REFERENCES docentes(id_docente) ON DELETE CASCADE
);

-- TABLA: CAPACITACIONES NEE
CREATE TABLE capacitaciones_nee (
    id_capacitacion INT AUTO_INCREMENT PRIMARY KEY,
    id_docente INT NOT NULL,
    nombre_capacitacion VARCHAR(255) NOT NULL,
    institucion VARCHAR(255),
    fecha_capacitacion DATE,
    duracion_horas INT,
    certificado BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_docente) REFERENCES docentes(id_docente) ON DELETE CASCADE
);

-- TABLA: EVALUACIONES AHP
CREATE TABLE evaluaciones_ahp (
    id_evaluacion INT AUTO_INCREMENT PRIMARY KEY,
    id_docente INT NOT NULL,
    id_criterio INT NOT NULL,
    puntuacion_criterio DECIMAL(5,3) NOT NULL,
    puntuacion_final DECIMAL(5,3) NOT NULL,
    fecha_evaluacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_docente) REFERENCES docentes(id_docente) ON DELETE CASCADE,
    FOREIGN KEY (id_criterio) REFERENCES criterios_ahp(id_criterio) ON DELETE CASCADE
);

-- TABLA: ASIGNACIONES
CREATE TABLE asignaciones (
    id_asignacion INT AUTO_INCREMENT PRIMARY KEY,
    id_docente INT,
    id_tipo_discapacidad INT NOT NULL,
    ciclo_academico VARCHAR(20) NOT NULL,
    materia VARCHAR(255) NOT NULL,
    numero_estudiantes INT NOT NULL,
    puntuacion_ahp DECIMAL(5,3) NOT NULL,
    estado ENUM('Activa', 'Finalizada', 'Cancelada') DEFAULT 'Activa',
    fecha_asignacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_docente) REFERENCES docentes(id_docente) ON DELETE SET NULL,
    FOREIGN KEY (id_tipo_discapacidad) REFERENCES tipos_discapacidad(id_tipo_discapacidad) ON DELETE CASCADE
);

-- INSERTAR DATOS INICIALES

-- Tipos de discapacidad
INSERT INTO tipos_discapacidad (nombre_discapacidad, peso_prioridad, descripcion) VALUES
('Psicosocial', 0.286, 'Discapacidad relacionada con aspectos psicológicos y sociales'),
('Auditiva', 0.286, 'Discapacidad relacionada con la audición'),
('Intelectual', 0.286, 'Discapacidad relacionada con el desarrollo intelectual'),
('Visual', 0.071, 'Discapacidad relacionada con la visión'),
('Física', 0.071, 'Discapacidad relacionada con la movilidad física');

-- Criterios AHP
INSERT INTO criterios_ahp (nombre_criterio, codigo_criterio, peso_criterio, descripcion) VALUES
('Formación Específica en Inclusión', 'FSI', 0.416, 'Capacitaciones y formación específica en NEE'),
('Experiencia Práctica con NEE', 'EPR', 0.262, 'Años de experiencia trabajando con estudiantes NEE'),
('Adaptaciones Metodológicas Implementadas', 'AMI', 0.161, 'Modificaciones realizadas en la metodología de enseñanza'),
('Años de Experiencia Docente General', 'AED', 0.099, 'Experiencia total como docente'),
('Nivel de Formación Académica', 'NFA', 0.063, 'Títulos de tercer y cuarto nivel');

-- Docentes
INSERT INTO docentes (nombres_completos, facultad, modalidad_enseñanza, años_experiencia_docente, titulo_tercer_nivel, titulo_cuarto_nivel, formacion_inclusion, estudiantes_nee_promedio, capacitaciones_nee, experiencia_nee_años) VALUES
('JACOME MORALES GLADYS CRISTINA', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', 'Más de 10 años', 'Ingeniero en Computación', 'Doctor en Ciencias Pedagógicas', TRUE, '1 a 5 estudiantes', 5, 8),
('ALFONSO ANÍBAL GUIJARRO RODRÍGUEZ', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero sistemas computacionales', 'Master en ciberseguridad, Master en administración de empresas', TRUE, '1 a 5 estudiantes', 3, 6),
('Edison Luis Cruz Navarrete', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '6 a 10 años', 'Ingeniero en software', NULL, FALSE, '1 a 5 estudiantes', 0, 4),
('Tatiana Mabel Alcivar Maldonado', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '1 a 5 años', 'Ing en contabilidad y Auditoría', 'Master en administración de empresas', FALSE, '1 a 5 estudiantes', 1, 2),
('Alex Roberto Collantes Farah', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero en Sistemas Computacionales', 'Master en administración de empresas', TRUE, '6 a 10 estudiantes', 4, 7),
('Myriam Garcia', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Virtual', '1 a 5 años', 'Ingeniero en Computación', NULL, FALSE, '1 a 5 estudiantes', 0, 1),
('Carlos Mendoza Silva', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Presencial', '6 a 10 años', 'Ingeniero en Software', 'Master en Educación Especial', TRUE, '1 a 5 estudiantes', 6, 5),
('Ana Patricia Loor Cedeño', 'FACULTAD DE CIENCIAS MATEMATICAS Y FISICAS', 'Híbrida', 'Más de 10 años', 'Ingeniero en Sistemas', 'Doctor en Educación Inclusiva', TRUE, '1 a 5 estudiantes', 8, 9);

-- Experiencia por tipo de discapacidad
INSERT INTO experiencia_docente_discapacidad (id_docente, id_tipo_discapacidad, tiene_experiencia, años_experiencia, nivel_competencia) VALUES
(1, 1, TRUE, 5, 'Avanzado'), (1, 2, TRUE, 4, 'Avanzado'), (1, 3, TRUE, 6, 'Experto'), (1, 4, TRUE, 3, 'Intermedio'), (1, 5, TRUE, 2, 'Intermedio'),
(2, 1, TRUE, 4, 'Avanzado'), (2, 2, TRUE, 5, 'Avanzado'), (2, 3, TRUE, 3, 'Intermedio'), (2, 4, TRUE, 2, 'Intermedio'), (2, 5, TRUE, 1, 'Básico'),
(3, 1, TRUE, 2, 'Intermedio'), (3, 2, TRUE, 3, 'Intermedio'), (3, 3, TRUE, 1, 'Básico'), (3, 4, FALSE, 0, 'Básico'), (3, 5, FALSE, 0, 'Básico'),
(4, 1, TRUE, 1, 'Básico'), (4, 2, FALSE, 0, 'Básico'), (4, 3, TRUE, 1, 'Básico'), (4, 4, FALSE, 0, 'Básico'), (4, 5, FALSE, 0, 'Básico'),
(5, 1, TRUE, 6, 'Avanzado'), (5, 2, TRUE, 5, 'Avanzado'), (5, 3, TRUE, 4, 'Avanzado'), (5, 4, TRUE, 3, 'Intermedio'), (5, 5, TRUE, 3, 'Intermedio'),
(6, 1, TRUE, 1, 'Básico'), (6, 2, FALSE, 0, 'Básico'), (6, 3, FALSE, 0, 'Básico'), (6, 4, FALSE, 0, 'Básico'), (6, 5, FALSE, 0, 'Básico'),
(7, 1, TRUE, 4, 'Avanzado'), (7, 2, TRUE, 3, 'Avanzado'), (7, 3, TRUE, 5, 'Experto'), (7, 4, TRUE, 2, 'Intermedio'), (7, 5, TRUE, 2, 'Intermedio'),
(8, 1, TRUE, 7, 'Experto'), (8, 2, TRUE, 6, 'Experto'), (8, 3, TRUE, 8, 'Experto'), (8, 4, TRUE, 5, 'Avanzado'), (8, 5, TRUE, 4, 'Avanzado');

-- Adaptaciones metodológicas
INSERT INTO adaptaciones_metodologicas (id_docente, modificacion_contenido, uso_recursos_tecnologicos, adaptacion_metodologia, coordinacion_servicios_apoyo, otras_adaptaciones) VALUES
(1, TRUE, TRUE, TRUE, TRUE, 'Uso de metodologías activas y participativas'),
(2, TRUE, TRUE, FALSE, TRUE, 'Implementación de recursos multimedia'),
(3, FALSE, TRUE, TRUE, FALSE, 'Generación de audio de las clases'),
(4, FALSE, FALSE, TRUE, FALSE, 'Adaptación básica de metodología'),
(5, TRUE, TRUE, TRUE, TRUE, 'Coordinación constante con servicios de apoyo'),
(6, FALSE, FALSE, TRUE, FALSE, 'Adaptaciones mínimas'),
(7, TRUE, TRUE, TRUE, TRUE, 'Especialización en metodologías inclusivas'),
(8, TRUE, TRUE, TRUE, TRUE, 'Desarrollo de materiales adaptados personalizados');

-- Capacitaciones específicas
INSERT INTO capacitaciones_nee (id_docente, nombre_capacitacion, institucion, fecha_capacitacion, duracion_horas, certificado) VALUES
(1, 'Metodologías inclusivas', 'Universidad Estatal', '2023-06-15', 40, TRUE),
(1, 'Educación Especial Avanzada', 'MINEDUC', '2023-03-20', 60, TRUE),
(1, 'Tecnologías Asistivas', 'CONADIS', '2022-11-10', 30, TRUE),
(2, 'Inclusión en el aula de clases', 'Universidad Estatal', '2023-01-15', 35, TRUE),
(2, 'Adaptaciones Curriculares', 'MINEDUC', '2022-09-20', 25, TRUE),
(5, 'Metodologías inclusivas', 'Universidad Estatal', '2023-04-10', 40, TRUE),
(5, 'Atención a la Diversidad', 'CONADIS', '2022-12-05', 45, TRUE),
(7, 'Educación Especial Integral', 'Universidad Central', '2023-02-28', 80, TRUE),
(7, 'Inclusión Educativa', 'MINEDUC', '2022-10-15', 50, TRUE),
(8, 'Metodologías Inclusivas Avanzadas', 'Universidad Estatal', '2023-05-20', 60, TRUE),
(8, 'Diseño Universal de Aprendizaje', 'CONADIS', '2023-01-10', 45, TRUE),
(8, 'Tecnologías para Inclusión', 'Universidad Central', '2022-08-15', 35, TRUE);

-- Asignaciones de ejemplo
INSERT INTO asignaciones (id_docente, id_tipo_discapacidad, ciclo_academico, materia, numero_estudiantes, puntuacion_ahp, estado) VALUES
(1, 1, '2025-1', 'Matemáticas Inclusivas', 5, 0.901, 'Activa'),
(2, 2, '2025-1', 'Programación Adaptada', 3, 0.753, 'Activa'),
(5, 3, '2025-1', 'Física Inclusiva', 4, 0.792, 'Activa'),
(7, 4, '2025-1', 'Estadística para NEE', 2, 0.789, 'Activa'),
(8, 5, '2025-1', 'Informática Básica', 3, 0.901, 'Activa');

-- Vista para cálculo de puntuaciones AHP
CREATE VIEW vista_puntuaciones_ahp AS
SELECT 
    d.id_docente,
    d.nombres_completos,
    d.facultad,
    CASE 
        WHEN d.formacion_inclusion = TRUE AND d.capacitaciones_nee >= 5 THEN 0.90
        WHEN d.formacion_inclusion = TRUE AND d.capacitaciones_nee >= 3 THEN 0.75
        WHEN d.formacion_inclusion = TRUE AND d.capacitaciones_nee >= 1 THEN 0.60
        WHEN d.capacitaciones_nee >= 1 THEN 0.40
        ELSE 0.20
    END as puntuacion_fsi,
    CASE 
        WHEN d.experiencia_nee_años >= 8 THEN 0.90
        WHEN d.experiencia_nee_años >= 5 THEN 0.75
        WHEN d.experiencia_nee_años >= 3 THEN 0.60
        WHEN d.experiencia_nee_años >= 1 THEN 0.40
        ELSE 0.20
    END as puntuacion_epr,
    CASE 
        WHEN (am.modificacion_contenido + am.uso_recursos_tecnologicos + am.adaptacion_metodologia + am.coordinacion_servicios_apoyo) >= 4 THEN 0.90
        WHEN (am.modificacion_contenido + am.uso_recursos_tecnologicos + am.adaptacion_metodologia + am.coordinacion_servicios_apoyo) >= 3 THEN 0.75
        WHEN (am.modificacion_contenido + am.uso_recursos_tecnologicos + am.adaptacion_metodologia + am.coordinacion_servicios_apoyo) >= 2 THEN 0.60
        WHEN (am.modificacion_contenido + am.uso_recursos_tecnologicos + am.adaptacion_metodologia + am.coordinacion_servicios_apoyo) >= 1 THEN 0.40
        ELSE 0.20
    END as puntuacion_ami,
    CASE 
        WHEN d.años_experiencia_docente = 'Más de 10 años' THEN 0.90
        WHEN d.años_experiencia_docente = '6 a 10 años' THEN 0.70
        WHEN d.años_experiencia_docente = '1 a 5 años' THEN 0.50
        ELSE 0.30
    END as puntuacion_aed,
    CASE 
        WHEN d.titulo_cuarto_nivel LIKE '%Doctor%' OR d.titulo_cuarto_nivel LIKE '%PhD%' THEN 0.90
        WHEN d.titulo_cuarto_nivel LIKE '%Master%' OR d.titulo_cuarto_nivel LIKE '%Maestr%' THEN 0.70
        ELSE 0.50
    END as puntuacion_nfa
FROM docentes d
LEFT JOIN adaptaciones_metodologicas am ON d.id_docente = am.id_docente;

-- Vista para ranking final AHP
CREATE VIEW vista_ranking_ahp AS
SELECT 
    vp.*,
    (vp.puntuacion_fsi * 0.416 + 
     vp.puntuacion_epr * 0.262 + 
     vp.puntuacion_ami * 0.161 + 
     vp.puntuacion_aed * 0.099 + 
     vp.puntuacion_nfa * 0.063) as puntuacion_final,
    RANK() OVER (ORDER BY (vp.puntuacion_fsi * 0.416 + 
                          vp.puntuacion_epr * 0.262 + 
                          vp.puntuacion_ami * 0.161 + 
                          vp.puntuacion_aed * 0.099 + 
                          vp.puntuacion_nfa * 0.063) DESC) as ranking
FROM vista_puntuaciones_ahp vp
ORDER BY puntuacion_final DESC;