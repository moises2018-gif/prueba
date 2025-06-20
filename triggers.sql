-- =====================================================
-- SCRIPT PARA CREAR EL TRIGGER FALTANTE
-- Base de datos: asignacion_docente
-- =====================================================

-- Primero, asegúrate de estar en la base correcta
USE asignacion_docente;

-- Eliminar el trigger si existe (por si acaso)
DROP TRIGGER IF EXISTS trigger_nuevo_docente;

-- Crear el trigger que falta
DELIMITER $$

CREATE TRIGGER trigger_nuevo_docente 
AFTER INSERT ON docentes FOR EACH ROW
BEGIN
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
    
    -- Insertar límites de asignación automáticamente
    INSERT INTO limites_asignacion (
        id_docente, 
        maximo_estudiantes_nee, 
        maximo_por_tipo_discapacidad, 
        observaciones
    ) VALUES (
        NEW.id_docente,
        -- Límites basados en formación y experiencia
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
    
END$$

DELIMITER ;

-- Verificar que el trigger se creó correctamente
SHOW TRIGGERS LIKE 'docentes';

-- Mostrar información del trigger creado
SELECT 
    TRIGGER_NAME as 'Trigger',
    EVENT_MANIPULATION as 'Evento',
    EVENT_OBJECT_TABLE as 'Tabla',
    ACTION_TIMING as 'Momento',
    'CREADO EXITOSAMENTE' as 'Estado'
FROM information_schema.TRIGGERS 
WHERE TRIGGER_SCHEMA = 'asignacion_docente' 
AND TRIGGER_NAME = 'trigger_nuevo_docente';

-- Mensaje de confirmación
SELECT 'TRIGGER CREADO EXITOSAMENTE - YA PUEDES INSERTAR NUEVOS DOCENTES' as 'RESULTADO';