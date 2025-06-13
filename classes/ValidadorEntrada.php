<?php
/**
 * VALIDADOR DE ENTRADAS DEL SISTEMA
 * Archivo: classes/ValidadorEntrada.php
 */

class ValidadorEntrada {
    
    /**
     * Valida datos de docente
     */
    public static function validarDocente($datos) {
        $errores = [];
        
        // Validar nombre
        if (empty(trim($datos['nombres_completos'] ?? ''))) {
            $errores[] = "El nombre completo es requerido";
        } elseif (strlen($datos['nombres_completos']) > 255) {
            $errores[] = "El nombre es demasiado largo (máximo 255 caracteres)";
        } elseif (!preg_match('/^[a-záéíóúñüA-ZÁÉÍÓÚÑÜ\s]+$/', $datos['nombres_completos'])) {
            $errores[] = "El nombre solo puede contener letras y espacios";
        }
        
        // Validar facultad
        if (empty(trim($datos['facultad'] ?? ''))) {
            $errores[] = "La facultad es requerida";
        }
        
        // Validar modalidad
        $modalidades_validas = ['Presencial', 'Virtual', 'Híbrida'];
        if (!in_array($datos['modalidad_enseñanza'] ?? '', $modalidades_validas)) {
            $errores[] = "Modalidad de enseñanza inválida";
        }
        
        // Validar experiencia
        $experiencias_validas = ['Menos de 1 año', '1 a 5 años', '6 a 10 años', 'Más de 10 años'];
        if (!in_array($datos['años_experiencia_docente'] ?? '', $experiencias_validas)) {
            $errores[] = "Años de experiencia docente inválidos";
        }
        
        // Validar títulos
        if (empty(trim($datos['titulo_tercer_nivel'] ?? ''))) {
            $errores[] = "El título de tercer nivel es requerido";
        }
        
        // Validar formación inclusión (debe ser 0 o 1)
        if (!in_array($datos['formacion_inclusion'] ?? '', ['0', '1', 0, 1], true)) {
            $errores[] = "Formación en inclusión inválida";
        }
        
        return $errores;
    }
    
    /**
     * Valida datos de estudiante
     */
    public static function validarEstudiante($datos) {
        $errores = [];
        
        // Validar nombre
        if (empty(trim($datos['nombres_completos'] ?? ''))) {
            $errores[] = "El nombre completo es requerido";
        } elseif (strlen($datos['nombres_completos']) > 255) {
            $errores[] = "El nombre es demasiado largo (máximo 255 caracteres)";
        }
        
        // Validar tipo de discapacidad
        if (!is_numeric($datos['id_tipo_discapacidad'] ?? '') || $datos['id_tipo_discapacidad'] < 1) {
            $errores[] = "Tipo de discapacidad inválido";
        }
        
        // Validar ciclo académico
        if (empty(trim($datos['ciclo_academico'] ?? ''))) {
            $errores[] = "El ciclo académico es requerido";
        } elseif (!preg_match('/^\d{4}-[12]$/', $datos['ciclo_academico'])) {
            $errores[] = "Formato de ciclo académico inválido (use formato: YYYY-1 o YYYY-2)";
        }
        
        // Validar facultad
        if (empty(trim($datos['facultad'] ?? ''))) {
            $errores[] = "La facultad es requerida";
        }
        
        return $errores;
    }
    
    /**
     * Valida parámetros de asignación
     */
    public static function validarAsignacion($datos) {
        $errores = [];
        
        // Validar ciclo académico
        if (empty(trim($datos['ciclo_academico'] ?? ''))) {
            $errores[] = "El ciclo académico es requerido";
        } elseif (!preg_match('/^\d{4}-[12]$/', $datos['ciclo_academico'])) {
            $errores[] = "Formato de ciclo académico inválido";
        }
        
        // Validar que no sea un ciclo muy antiguo o muy futuro
        $año_actual = (int)date('Y');
        $ciclo_año = (int)substr($datos['ciclo_academico'], 0, 4);
        
        if ($ciclo_año < ($año_actual - 2) || $ciclo_año > ($año_actual + 2)) {
            $errores[] = "El año del ciclo académico debe estar entre " . ($año_actual - 2) . " y " . ($año_actual + 2);
        }
        
        return $errores;
    }
    
    /**
     * Sanitiza una cadena de texto
     */
    public static function sanitizarTexto($texto) {
        if ($texto === null) {
            return null;
        }
        
        // Eliminar espacios al inicio y final
        $texto = trim($texto);
        
        // Convertir caracteres especiales
        $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
        
        // Eliminar caracteres de control excepto saltos de línea y tabulaciones
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $texto);
        
        return $texto;
    }
    
    /**
     * Valida un ID numérico
     */
    public static function validarId($id, $nombre_campo = 'ID') {
        if (!is_numeric($id) || $id < 1 || $id != (int)$id) {
            throw new InvalidArgumentException("$nombre_campo inválido");
        }
        return (int)$id;
    }
    
    /**
     * Valida un email
     */
    public static function validarEmail($email) {
        if (empty($email)) {
            return false;
        }
        
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Valida una fecha
     */
    public static function validarFecha($fecha, $formato = 'Y-m-d') {
        if (empty($fecha)) {
            return false;
        }
        
        $d = DateTime::createFromFormat($formato, $fecha);
        return $d && $d->format($formato) === $fecha;
    }
    
    /**
     * Valida un número decimal
     */
    public static function validarDecimal($numero, $min = null, $max = null) {
        if (!is_numeric($numero)) {
            return false;
        }
        
        $numero = (float)$numero;
        
        if ($min !== null && $numero < $min) {
            return false;
        }
        
        if ($max !== null && $numero > $max) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Valida parámetros de configuración AHP
     */
    public static function validarParametrosAHP($parametros) {
        $errores = [];
        
        // Validar límite máximo por docente
        if (!self::validarDecimal($parametros['max_estudiantes_por_docente'] ?? 0, 1, 20)) {
            $errores[] = "Máximo de estudiantes por docente debe ser entre 1 y 20";
        }
        
        // Validar límite por tipo de discapacidad
        if (!self::validarDecimal($parametros['max_por_tipo_discapacidad'] ?? 0, 1, 10)) {
            $errores[] = "Máximo por tipo de discapacidad debe ser entre 1 y 10";
        }
        
        // Validar penalización de carga
        if (!self::validarDecimal($parametros['penalizacion_carga'] ?? 0, 0, 1)) {
            $errores[] = "Penalización de carga debe ser entre 0 y 1";
        }
        
        // Validar bonus de experiencia
        if (!self::validarDecimal($parametros['bonus_experiencia'] ?? 0, 0, 1)) {
            $errores[] = "Bonus de experiencia debe ser entre 0 y 1";
        }
        
        return $errores;
    }
}
?>