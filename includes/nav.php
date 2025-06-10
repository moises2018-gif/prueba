<div class="nav-tabs">
    <button class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" onclick="window.location.href='dashboard.php'">Dashboard</button>
    <button class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'docentes.php' ? 'active' : ''; ?>" onclick="window.location.href='docentes.php'">Gestión Docentes</button>
    <button class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'estudiantes.php' ? 'active' : ''; ?>" onclick="window.location.href='estudiantes.php'">Gestión Estudiantes</button>
    <button class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'asignacion.php' ? 'active' : ''; ?>" onclick="window.location.href='asignacion.php'">Asignación AHP</button>
    <button class="nav-tab <?php echo basename($_SERVER['PHP_SELF']) == 'reportes.php' ? 'active' : ''; ?>" onclick="window.location.href='reportes.php'">Reportes</button>
</div>