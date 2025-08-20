<?php
// includes/sidebar.php
// Obtener el nombre del archivo actual para determinar qué menú está activo
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-menu">
        <div class="menu-section">
            <div class="menu-title">PRINCIPAL</div>
            <a href="index.php" class="menu-item <?php echo ($current_page == 'index') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a href="accounts.php" class="menu-item <?php echo ($current_page == 'accounts') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">account_balance_wallet</span>
                <span>Cuentas</span>
                <span id="sidebar-accounts-badge" class="menu-badge">0</span>
            </a>
            <a href="#positions" class="menu-item <?php echo ($current_page == 'positions') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">show_chart</span>
                <span>Posiciones</span>
                <span id="sidebar-positions-badge" class="menu-badge live">0</span>
            </a>
            <a href="#history" class="menu-item <?php echo ($current_page == 'history') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">history</span>
                <span>Historial</span>
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-title">ANÁLISIS</div>
            <a href="#performance" class="menu-item <?php echo ($current_page == 'performance') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">analytics</span>
                <span>Rendimiento</span>
            </a>
            <a href="#risk" class="menu-item <?php echo ($current_page == 'risk') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">warning_amber</span>
                <span>Gestión de Riesgo</span>
            </a>
            <a href="#stats" class="menu-item <?php echo ($current_page == 'stats') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">insights</span>
                <span>Estadísticas</span>
            </a>
            <a href="#reports" class="menu-item <?php echo ($current_page == 'reports') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">assessment</span>
                <span>Reportes</span>
            </a>
        </div>
        
        <div class="menu-section">
            <div class="menu-title">CONFIGURACIÓN</div>
            <a href="#alerts" class="menu-item <?php echo ($current_page == 'alerts') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">notifications_active</span>
                <span>Alertas</span>
                <span class="menu-badge" id="alerts-count">0</span>
            </a>
            <a href="#settings" class="menu-item <?php echo ($current_page == 'settings') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">tune</span>
                <span>Ajustes</span>
            </a>
            <a href="#api" class="menu-item <?php echo ($current_page == 'api') ? 'active' : ''; ?>">
                <span class="material-icons-outlined">api</span>
                <span>API & EA</span>
            </a>
        </div>
    </div>
    
    <div class="system-status">
        <div class="status-header">Estado del Sistema</div>
        <div class="status-item">
            <span class="status-dot online" id="api-status"></span>
            <span>API MT5</span>
            <span class="status-value" id="api-status-text">Online</span>
        </div>
        <div class="status-item">
            <span class="status-dot" id="db-status"></span>
            <span>Base de Datos</span>
            <span class="status-value" id="db-status-text">Conectado</span>
        </div>
        <div class="status-item">
            <span class="status-dot" id="ea-status"></span>
            <span>EA Reporter</span>
            <span class="status-value" id="ea-status-text">Activo</span>
        </div>
    </div>
</aside>