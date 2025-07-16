<?php
// Navegación simplificada - solo para administrador
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>

<div class="nav-tabs">
    <button class="nav-tab <?php echo $pagina_actual == 'dashboard.php' ? 'active' : ''; ?>" 
            onclick="window.location.href='dashboard.php'">
        📊 Dashboard
    </button>
    
    <button class="nav-tab <?php echo $pagina_actual == 'docentes.php' ? 'active' : ''; ?>" 
            onclick="window.location.href='docentes.php'">
        👨‍🏫 Gestión Docentes
    </button>
    
    <button class="nav-tab <?php echo $pagina_actual == 'estudiantes.php' ? 'active' : ''; ?>" 
            onclick="window.location.href='estudiantes.php'">
        🎓 Gestión Estudiantes
    </button>
    
    <button class="nav-tab <?php echo $pagina_actual == 'asignacion.php' ? 'active' : ''; ?>" 
            onclick="window.location.href='asignacion.php'">
        🎯 Asignación AHP
    </button>
    
    <button class="nav-tab <?php echo $pagina_actual == 'reportes.php' ? 'active' : ''; ?>" 
            onclick="window.location.href='reportes.php'">
        📋 Reportes
    </button>
</div>

<style>
/* Estilos para navegación simplificada */
.nav-tabs {
    display: flex;
    gap: 5px;
    padding: 15px 20px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    flex-wrap: wrap;
    justify-content: center;
}

.nav-tab {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.nav-tab:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

.nav-tab.active {
    background: rgba(255, 255, 255, 0.9);
    color: #2c3e50;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

@media (max-width: 768px) {
    .nav-tabs {
        padding: 10px;
        flex-direction: column;
        align-items: center;
    }
    
    .nav-tab {
        width: 100%;
        max-width: 250px;
        text-align: center;
        margin: 2px 0;
    }
}
</style>