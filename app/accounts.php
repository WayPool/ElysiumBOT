<?php
//+------------------------------------------------------------------+
//| accounts.php                                                     |
//| Sistema de Gestión de Cuentas - Elysium v7.0                   |
//| Copyright 2025, Elysium Media FZCO                              |
//| Gestión Profesional de Fondos de Trading                        |
//+------------------------------------------------------------------+

// Configuración de sesión y seguridad
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Configuración de errores para producción
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Configuración de zona horaria
date_default_timezone_set('UTC');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Gestión de Cuentas - Elysium Trading v7.0</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    
    <!-- CSS Base del Sistema -->
<link rel="stylesheet" href="assets/css/main.css">
<link rel="stylesheet" href="assets/css/components.css">
<link rel="stylesheet" href="assets/css/accounts.css">
<link rel="stylesheet" href="assets/css/accounts-fix.css">
<link rel="stylesheet" href="assets/css/modal-fix.css">
<link rel="stylesheet" href="assets/css/delete-modal-fix.css">
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Top Navigation Bar (igual que index.php) -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-left">
                <div class="logo">
                    <span class="logo-icon">⚡</span>
                    <span class="logo-text">ELYSIUM</span>
                    <span class="logo-subtitle">ACCOUNTS v7.0</span>
                </div>
            </div>
            <div class="nav-center">
                <div class="search-box">
                    <span class="material-icons-outlined">search</span>
                    <input type="text" id="accountSearch" placeholder="Buscar cuentas por número, nombre o broker...">
                </div>
            </div>
            <div class="nav-right">
                <div class="nav-item notification-bell">
                    <span class="material-icons-outlined">notifications</span>
                    <span id="notification-badge" class="notification-badge pulse">0</span>
                </div>
                <div class="nav-item" onclick="window.location.href='index.php'">
                    <span class="material-icons-outlined">dashboard</span>
                </div>
                <div class="nav-item" onclick="toggleFullscreen()">
                    <span class="material-icons-outlined">fullscreen</span>
                </div>
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin&background=6366f1&color=fff" alt="Profile">
                    <div class="user-info">
                        <span class="user-name">Administrator</span>
                        <span class="user-role">Fund Manager</span>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- Content Area -->
        <main class="content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-left">
                    <h1>Gestión de Cuentas de Trading</h1>
                    <p>Total: <span id="total-accounts">0</span> cuentas | 
                       Equity Total: <span id="total-equity">$0.00</span> | 
                       Balance Total: <span id="total-balance">$0.00</span></p>
                </div>
                <div class="header-right">
                    <button class="btn btn-primary" onclick="refreshAccounts()">
                        <span class="material-icons-outlined">refresh</span>
                        Actualizar
                    </button>
                    <button class="btn btn-export" onclick="exportAccounts()">
                        <span class="material-icons-outlined">download</span>
                        Exportar
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-bar">
                <div class="filter-group">
                    <label>Estado</label>
                    <select id="filter-status" class="filter-select">
                        <option value="all">Todas</option>
                        <option value="active">Activas</option>
                        <option value="inactive">Inactivas</option>
                        <option value="warning">En Alerta</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Broker</label>
                    <select id="filter-broker" class="filter-select">
                        <option value="all">Todos</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Moneda</label>
                    <select id="filter-currency" class="filter-select">
                        <option value="all">Todas</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Ordenar por</label>
                    <select id="sort-by" class="filter-select">
                        <option value="equity_desc">Mayor Equity</option>
                        <option value="equity_asc">Menor Equity</option>
                        <option value="profit_desc">Mayor Profit</option>
                        <option value="profit_asc">Menor Profit</option>
                        <option value="recent">Más Reciente</option>
                    </select>
                </div>
                <div class="filter-stats">
                    <div class="stat-badge success">
                        <span class="material-icons-outlined">check_circle</span>
                        <span id="active-count">0</span> Activas
                    </div>
                    <div class="stat-badge warning">
                        <span class="material-icons-outlined">warning</span>
                        <span id="warning-count">0</span> En Alerta
                    </div>
                    <div class="stat-badge danger">
                        <span class="material-icons-outlined">error</span>
                        <span id="critical-count">0</span> Críticas
                    </div>
                </div>
            </div>

            <!-- Grid de Cuentas -->
            <div id="accounts-grid" class="accounts-grid">
                <!-- Las cuentas se cargarán dinámicamente aquí -->
            </div>

            <!-- Paginación -->
            <div class="table-pagination">
                <div class="pagination-info">
                    Mostrando <strong id="accounts-showing">0-0</strong> de <strong id="accounts-total">0</strong> cuentas
                </div>
                <div class="pagination-controls">
                    <div class="pagination-nav-group">
                        <button class="btn-pagination" onclick="goToPage('first')">
                            <span class="material-icons-outlined">first_page</span>
                        </button>
                        <button class="btn-pagination" onclick="goToPage('prev')">
                            <span class="material-icons-outlined">chevron_left</span>
                        </button>
                        <div class="page-numbers" id="page-numbers">
                            <button class="page-num active">1</button>
                        </div>
                        <button class="btn-pagination" onclick="goToPage('next')">
                            <span class="material-icons-outlined">chevron_right</span>
                        </button>
                        <button class="btn-pagination" onclick="goToPage('last')">
                            <span class="material-icons-outlined">last_page</span>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de Detalles de Cuenta -->
    <div id="accountModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <div class="modal-title">
                    <span class="material-icons-outlined">account_balance_wallet</span>
                    <span id="modal-account-title">Cuenta #0000000</span>
                    <span id="modal-account-status" class="account-status">ACTIVA</span>
                </div>
                <button class="modal-close" onclick="closeAccountModal()">
                    <span class="material-icons-outlined">close</span>
                </button>
            </div>
            
            <div class="modal-body">
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('overview')">
                        <span class="material-icons-outlined">dashboard</span>
                        Resumen
                    </button>
                    <button class="tab" onclick="switchTab('positions')">
                        <span class="material-icons-outlined">show_chart</span>
                        Posiciones Abiertas
                    </button>
                    <button class="tab" onclick="switchTab('history')">
                        <span class="material-icons-outlined">history</span>
                        Historial
                    </button>
                    <button class="tab" onclick="switchTab('statistics')">
                        <span class="material-icons-outlined">analytics</span>
                        Estadísticas
                    </button>
                    <button class="tab" onclick="switchTab('risk')">
                        <span class="material-icons-outlined">warning_amber</span>
                        Análisis de Riesgo
                    </button>
                </div>

                <!-- Tab Content: Overview -->
                <div id="tab-overview" class="tab-content active">
                    <!-- Summary Cards -->
                    <div class="summary-cards">
                        <div class="summary-card">
                            <div class="summary-icon">
                                <span class="material-icons-outlined">account_balance</span>
                            </div>
                            <div class="summary-value" id="modal-equity">$0.00</div>
                            <div class="summary-label">Equity Total</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon">
                                <span class="material-icons-outlined">account_balance_wallet</span>
                            </div>
                            <div class="summary-value" id="modal-balance">$0.00</div>
                            <div class="summary-label">Balance</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon">
                                <span class="material-icons-outlined">trending_up</span>
                            </div>
                            <div class="summary-value" id="modal-profit">$0.00</div>
                            <div class="summary-label">Profit/Loss</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon">
                                <span class="material-icons-outlined">speed</span>
                            </div>
                            <div class="summary-value" id="modal-margin-level">0%</div>
                            <div class="summary-label">Nivel de Margen</div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="charts-row">
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3>Evolución del Equity</h3>
                            </div>
                            <div class="chart-body" style="height: 300px;">
                                <canvas id="modalEquityChart"></canvas>
                            </div>
                        </div>
                        <div class="chart-container">
                            <div class="chart-header">
                                <h3>Distribución P&L</h3>
                            </div>
                            <div class="chart-body" style="height: 300px;">
                                <canvas id="modalPLChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Cards -->
                    <div class="detail-grid">
                        <div class="detail-card">
                            <div class="detail-card-title">
                                <span class="material-icons-outlined">info</span>
                                Información de Cuenta
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Número de Cuenta</span>
                                <span class="info-value" id="modal-login">0000000</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Nombre</span>
                                <span class="info-value" id="modal-name">-</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Servidor</span>
                                <span class="info-value" id="modal-server">-</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Broker</span>
                                <span class="info-value" id="modal-company">-</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Moneda</span>
                                <span class="info-value" id="modal-currency">USD</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Leverage</span>
                                <span class="info-value" id="modal-leverage">1:100</span>
                            </div>
                        </div>

                        <div class="detail-card">
                            <div class="detail-card-title">
                                <span class="material-icons-outlined">account_balance</span>
                                Estado Financiero
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Balance</span>
                                <span class="info-value" id="modal-balance-detail">$0.00</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Equity</span>
                                <span class="info-value" id="modal-equity-detail">$0.00</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Margen Usado</span>
                                <span class="info-value" id="modal-margin">$0.00</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Margen Libre</span>
                                <span class="info-value" id="modal-margin-free">$0.00</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Crédito</span>
                                <span class="info-value" id="modal-credit">$0.00</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Profit Actual</span>
                                <span class="info-value" id="modal-profit-detail">$0.00</span>
                            </div>
                        </div>

                        <div class="detail-card">
                            <div class="detail-card-title">
                                <span class="material-icons-outlined">update</span>
                                Información Temporal
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Última Actualización</span>
                                <span class="info-value" id="modal-last-update">-</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Primera Operación</span>
                                <span class="info-value" id="modal-first-trade">-</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Última Operación</span>
                                <span class="info-value" id="modal-last-trade">-</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Días Activo</span>
                                <span class="info-value" id="modal-days-active">0</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Estado EA</span>
                                <span class="info-value" id="modal-ea-status">Activo</span>
                            </div>
                            <div class="stats-row">
                                <span class="info-label">Timestamp EA</span>
                                <span class="info-value" id="modal-ea-timestamp">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="modal-actions">
                        <button class="btn btn-primary" onclick="exportAccountData()">
                            <span class="material-icons-outlined">download</span>
                            Exportar Datos
                        </button>
                        <button class="btn btn-secondary" onclick="generateAccountReport()">
                            <span class="material-icons-outlined">description</span>
                            Generar Reporte
                        </button>
                        <button class="btn btn-secondary" onclick="syncAccountData()">
                            <span class="material-icons-outlined">sync</span>
                            Sincronizar
                        </button>
                        <button class="btn danger" onclick="confirmDeleteAccount()">
                            <span class="material-icons-outlined">delete</span>
                            Eliminar Cuenta
                        </button>
                    </div>
                </div>

                <!-- Tab Content: Positions -->
                <div id="tab-positions" class="tab-content">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Símbolo</th>
                                    <th>Tipo</th>
                                    <th>Volumen</th>
                                    <th>Apertura</th>
                                    <th>Actual</th>
                                    <th>S/L</th>
                                    <th>T/P</th>
                                    <th>Swap</th>
                                    <th>Profit</th>
                                    <th>Duración</th>
                                </tr>
                            </thead>
                            <tbody id="modal-positions-tbody">
                                <tr>
                                    <td colspan="11" style="text-align: center;">No hay posiciones abiertas</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Content: History -->
                <div id="tab-history" class="tab-content">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Fecha</th>
                                    <th>Símbolo</th>
                                    <th>Tipo</th>
                                    <th>Volumen</th>
                                    <th>Precio</th>
                                    <th>Comisión</th>
                                    <th>Swap</th>
                                    <th>Profit</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="modal-history-tbody">
                                <tr>
                                    <td colspan="10" style="text-align: center;">Cargando historial...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab Content: Statistics -->
                <div id="tab-statistics" class="tab-content">
                    <div class="stats-grid">
                        <!-- Se llenará dinámicamente con estadísticas -->
                    </div>
                </div>

                <!-- Tab Content: Risk Analysis -->
                <div id="tab-risk" class="tab-content">
                    <div class="risk-analysis">
                        <!-- Se llenará dinámicamente con análisis de riesgo -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación para Eliminar -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <div class="modal-title">
                <span class="material-icons-outlined">warning</span>
                <span>Confirmar Eliminación</span>
            </div>
        </div>
        
        <div class="modal-body">
            <!-- Alert Card -->
            <div class="alert-card critical">
                <div class="alert-icon">
                    <span class="material-icons-outlined">delete_forever</span>
                </div>
                <div class="alert-content">
                    <div class="alert-title">¿Está seguro de eliminar esta cuenta?</div>
                    <div class="alert-message">
                        <strong>Cuenta: #<span id="delete-account-number">0000000</span></strong>
                        <span id="delete-account-name" style="display: block; margin-top: 5px; color: #718096;">-</span>
                        
                        Esta acción eliminará permanentemente:
                        <ul>
                            <li>Todos los datos de la cuenta</li>
                            <li>Historial completo de operaciones</li>
                            <li>Posiciones registradas</li>
                            <li>Estadísticas y métricas</li>
                        </ul>
                        
                        <strong>⚠️ Esta acción NO se puede deshacer.</strong>
                    </div>
                </div>
            </div>
            
            <!-- Campo de contraseña -->
            <div class="password-section">
                <label class="password-label">
                    <span class="material-icons-outlined">lock</span>
                    Ingrese la contraseña de autorización:
                </label>
                <input type="password" 
                       id="delete-password" 
                       placeholder="Contraseña requerida"
                       onkeypress="if(event.key === 'Enter') deleteAccount()"
                       autocomplete="off">
                <div id="delete-error-message">
                    <span class="material-icons-outlined">error</span>
                    <span id="delete-error-text">Contraseña incorrecta</span>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">
                    <span class="material-icons-outlined">close</span>
                    Cancelar
                </button>
                <button class="btn danger" onclick="deleteAccount()" id="btn-confirm-delete">
                    <span class="material-icons-outlined">delete_forever</span>
                    Eliminar Permanentemente
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Scripts -->
    <script src="assets/js/sidebar-counters.js"></script>
	<script src="assets/js/system-status.js"></script>
	<script src="assets/js/accounts.js"></script>
	<script src="assets/js/modal_charts.js"></script>
	<script>
	// Forzar charts en el modal con los datos visibles
	function forceModalCharts() {
		setInterval(function() {
			const modal = document.getElementById('accountModal');
			if (!modal || !modal.classList.contains('show')) return;

			const equityCanvas = document.getElementById('modalEquityChart');
			if (!equityCanvas || equityCanvas.getAttribute('data-initialized')) return;

			// Obtener valores del modal
			const equity = parseFloat(document.getElementById('modal-equity').textContent.replace(/[^0-9.-]/g, ''));
			const balance = parseFloat(document.getElementById('modal-balance').textContent.replace(/[^0-9.-]/g, ''));

			if (isNaN(equity) || isNaN(balance)) return;

			// Generar datos
			const data = [];
			for (let i = 0; i < 20; i++) {
				const progress = i / 19;
				data.push(balance + ((equity - balance) * progress));
			}

			// Crear chart
			new Chart(equityCanvas.getContext('2d'), {
				type: 'line',
				data: {
					labels: Array(20).fill(''),
					datasets: [{
						label: 'Equity',
						data: data,
						borderColor: '#6366f1',
						borderWidth: 3,
						tension: 0.4
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false
				}
			});

			equityCanvas.setAttribute('data-initialized', 'true');
			console.log('Chart creado con equity:', equity, 'balance:', balance);
		}, 500);
	}

	document.addEventListener('DOMContentLoaded', forceModalCharts);
	</script>
	<!-- Scripts -->
	<script src="assets/js/sidebar-counters.js"></script>
	<script src="assets/js/system-status.js"></script>
	<script src="assets/js/accounts.js"></script>

	<!-- Sistema Profesional de Charts para Modal v7.0 -->
	<script src="assets/js/modal_charts_fixed.js"></script>
	<script src="assets/js/pl_chart_fix.js"></script>
	<!-- Sistema de Eliminación de Cuentas con Contraseña -->
	<script src="assets/js/account_delete.js"></script>
	<!-- Fix para cambiar -Demo por -Live -->
	<script src="assets/js/server_name_fix.js"></script>
</body>
</html>