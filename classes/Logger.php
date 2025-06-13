<?php
/**
 * SISTEMA DE LOGS PARA EL PROYECTO AHP
 * Archivo: classes/Logger.php
 */

class Logger {
    private $logDir;
    private $enabled;
    private $level;
    
    const LEVELS = [
        'DEBUG' => 1,
        'INFO' => 2,
        'WARNING' => 3,
        'ERROR' => 4,
        'CRITICAL' => 5
    ];
    
    public function __construct() {
        $this->logDir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../logs/';
        $this->enabled = defined('LOG_ENABLED') ? LOG_ENABLED : true;
        $this->level = defined('LOG_LEVEL') ? LOG_LEVEL : 'INFO';
        
        // Crear directorio si no existe
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    /**
     * Registra un mensaje en el log
     */
    public function log($level, $mensaje, $contexto = []) {
        if (!$this->enabled) {
            return;
        }
        
        // Verificar si el nivel es suficiente para registrar
        if (self::LEVELS[$level] < self::LEVELS[$this->level]) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($contexto) ? ' | Contexto: ' . json_encode($contexto, JSON_UNESCAPED_UNICODE) : '';
        
        $logEntry = "[$timestamp] [$level] $mensaje$contextStr" . PHP_EOL;
        
        // Escribir en archivo principal
        file_put_contents($this->logDir . 'ahp_system.log', $logEntry, FILE_APPEND | LOCK_EX);
        
        // Escribir en archivo específico según el nivel
        if ($level === 'ERROR' || $level === 'CRITICAL') {
            file_put_contents($this->logDir . 'errores.log', $logEntry, FILE_APPEND | LOCK_EX);
        }
        
        if (strpos(strtolower($mensaje), 'asignacion') !== false) {
            file_put_contents($this->logDir . 'asignaciones.log', $logEntry, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Métodos de conveniencia para diferentes niveles
     */
    public function debug($mensaje, $contexto = []) {
        $this->log('DEBUG', $mensaje, $contexto);
    }
    
    public function info($mensaje, $contexto = []) {
        $this->log('INFO', $mensaje, $contexto);
    }
    
    public function warning($mensaje, $contexto = []) {
        $this->log('WARNING', $mensaje, $contexto);
    }
    
    public function error($mensaje, $contexto = []) {
        $this->log('ERROR', $mensaje, $contexto);
    }
    
    public function critical($mensaje, $contexto = []) {
        $this->log('CRITICAL', $mensaje, $contexto);
    }
    
    /**
     * Limpia logs antiguos (más de X días)
     */
    public function limpiarLogsAntiguos($dias = 30) {
        $archivos = glob($this->logDir . '*.log');
        $tiempoLimite = time() - ($dias * 24 * 60 * 60);
        
        foreach ($archivos as $archivo) {
            if (filemtime($archivo) < $tiempoLimite) {
                unlink($archivo);
                $this->info("Log antiguo eliminado: " . basename($archivo));
            }
        }
    }
    
    /**
     * Obtiene estadísticas de los logs
     */
    public function obtenerEstadisticas() {
        $archivos = glob($this->logDir . '*.log');
        $estadisticas = [];
        
        foreach ($archivos as $archivo) {
            $nombre = basename($archivo, '.log');
            $estadisticas[$nombre] = [
                'tamano' => filesize($archivo),
                'modificado' => date('Y-m-d H:i:s', filemtime($archivo)),
                'lineas' => count(file($archivo))
            ];
        }
        
        return $estadisticas;
    }
}
?>