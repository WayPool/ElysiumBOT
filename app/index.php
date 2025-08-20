<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elysium Trading Dashboard v7.0 - Professional Fund Management</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Top Navigation Bar -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-left">
                <div class="logo">
                    <span class="logo-icon">⚡</span>
                    <span class="logo-text">ELYSIUM</span>
                    <span class="logo-subtitle">TRADING v7.0</span>
                </div>
            </div>
            <div class="nav-center">
                <div class="search-box">
                    <span class="material-icons-outlined">search</span>
                    <input type="text" id="globalSearch" placeholder="Buscar cuentas, símbolos, operaciones...">
                </div>
            </div>
            <div class="nav-right">
                <div class="nav-item notification-bell">
                    <span class="material-icons-outlined">notifications</span>
                    <span id="notification-badge" class="notification-badge pulse">0</span>
                </div>
                <div class="nav-item" onclick="openSettings()">
                    <span class="material-icons-outlined">settings</span>
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
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        <!-- Content Area -->
        <main class="content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-left">
                    <h1>Dashboard Profesional de Trading</h1>
                    <p>Última actualización: <span id="last-update-time">--:--:--</span> | 
                       <span id="total-accounts">0</span> cuentas activas | 
                       <span id="total-positions">0</span> posiciones abiertas</p>
                </div>
                <div class="header-right">
                    <select id="broker-filter" class="broker-filter-select">
                        <option value="all">Todos los Brokers</option>
                        <option value="standard">Formato Estándar</option>
                        <option value="special">Formato Especial</option>
                    </select>
                    <select id="time-filter" class="time-filter">
                        <option value="RT">Tiempo Real</option>
                        <option value="1D">Hoy</option>
                        <option value="7D">7 Días</option>
                        <option value="30D" selected>30 Días</option>
                        <option value="90D">3 Meses</option>
                        <option value="YTD">Año Actual</option>
                        <option value="ALL">Todo</option>
                    </select>
                    <button class="btn btn-export" onclick="exportData()">
                        <span class="material-icons-outlined">download</span>
                        Exportar
                    </button>
                    <button class="btn btn-primary" onclick="refreshDashboard()">
                        <span class="material-icons-outlined">refresh</span>
                        Actualizar
                    </button>
                </div>
            </div>

            <!-- Filtro especial de broker (solo visible cuando se necesite) -->
            <div id="brokerFilterInfo" class="broker-filter-container" style="display: none;">
                <span class="broker-filter-label">Filtro Activo:</span>
                <span class="broker-badge" id="activeBrokerFilter">Todos</span>
                <button class="btn btn-secondary" onclick="clearBrokerFilter()">
                    <span class="material-icons-outlined">clear</span>
                    Limpiar Filtro
                </button>
            </div>

			
			<!-- HEADER STATS -->
            <div class="footer-stats" id="footer-stats">
                <div class="footer-stat">
                    <span class="footer-label">P&L Hoy</span>
                    <span id="pl-today" class="footer-value">$0.00</span>
                </div>
                <div class="footer-stat">
                    <span class="footer-label">P&L Semana</span>
                    <span id="pl-week" class="footer-value">$0.00</span>
                </div>
                <div class="footer-stat">
                    <span class="footer-label">P&L Mes</span>
                    <span id="pl-month" class="footer-value">$0.00</span>
                </div>
                <div class="footer-stat">
                    <span class="footer-label">P&L Año</span>
                    <span id="pl-year" class="footer-value">$0.00</span>
                </div>
                <div class="footer-stat">
                    <span class="footer-label">Tiempo Servidor</span>
                    <span id="server-time" class="footer-value">--:--:--</span>
                </div>
            </div>
			<div>&nbsp;</div>
			
            <!-- KPI PRINCIPAL -->
            <div class="metrics-section">
				<div></div>
                <h2 class="section-title">🔑 KPI Principal del Fondo</h2>
                <div class="metrics-grid">
                    <!-- Capital Total -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon success">
                                <span class="material-icons-outlined">account_balance</span>
                            </span>
                            <span class="metric-label">CAPITAL TOTAL</span>
                        </div>
                        <div id="kpi-total-capital" class="metric-value">$0.00</div>
                        <div id="kpi-capital-change" class="metric-subtitle">Capital Depositado</div>
                        <div class="mini-chart">
                            <canvas id="miniChart1"></canvas>
                        </div>
                    </div>

                    <!-- Equity Total -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon info">
                                <span class="material-icons-outlined">trending_up</span>
                            </span>
                            <span class="metric-label">EQUITY TOTAL</span>
                        </div>
                        <div id="kpi-total-equity" class="metric-value">$0.00</div>
                        <div id="kpi-equity-change" class="metric-change positive">+$0.00 (0.00%)</div>
                        <div class="mini-chart">
                            <canvas id="miniChart2"></canvas>
                        </div>
                    </div>

                    <!-- Balance Total -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon warning">
                                <span class="material-icons-outlined">account_balance_wallet</span>
                            </span>
                            <span class="metric-label">BALANCE TOTAL</span>
                        </div>
                        <div id="kpi-total-balance" class="metric-value">$0.00</div>
                        <div id="kpi-balance-vs-equity" class="metric-subtitle">Diferencia: $0.00</div>
                        <div class="mini-chart">
                            <canvas id="miniChart3"></canvas>
                        </div>
                    </div>

                    <!-- P&L Flotante -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon danger">
                                <span class="material-icons-outlined">monetization_on</span>
                            </span>
                            <span class="metric-label">P&L FLOTANTE</span>
                        </div>
                        <div id="kpi-floating-pl" class="metric-value">$0.00</div>
                        <div id="kpi-floating-pl-pct" class="metric-subtitle">0.00% del capital</div>
                        <div class="mini-chart">
                            <canvas id="miniChart4"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MÉTRICAS DE PERFORMANCE -->
            <div class="metrics-section">
                <h2 class="section-title">🚀 Métricas de Performance</h2>
                <div class="metrics-grid">
                    <!-- Win Rate -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon success">
                                <span class="material-icons-outlined">military_tech</span>
                            </span>
                            <span class="metric-label">WIN RATE</span>
                        </div>
                        <div id="perf-win-rate" class="metric-value">0.00%</div>
                        <div id="perf-wins-losses" class="metric-subtitle">W: 0 / L: 0</div>
                        <div class="metric-progress">
                            <div id="perf-win-rate-bar" class="progress-bar" style="width: 0%;"></div>
                        </div>
                    </div>

                    <!-- Profit Factor -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon info">
                                <span class="material-icons-outlined">attach_money</span>
                            </span>
                            <span class="metric-label">PROFIT FACTOR</span>
                        </div>
                        <div id="perf-profit-factor" class="metric-value">0.00</div>
                        <div id="perf-gross-pl" class="metric-subtitle">GP: $0 / GL: $0</div>
                        <div class="metric-subtitle-detail">Ideal > 1.75</div>
                    </div>

                    <!-- Sharpe Ratio -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon warning">
                                <span class="material-icons-outlined">show_chart</span>
                            </span>
                            <span class="metric-label">SHARPE RATIO</span>
                        </div>
                        <div id="perf-sharpe-ratio" class="metric-value">0.00</div>
                        <div class="metric-subtitle">Retorno ajustado al riesgo</div>
                        <div class="metric-subtitle-detail">Excelente > 1.0</div>
                    </div>

                    <!-- Max Drawdown -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon danger">
                                <span class="material-icons-outlined">trending_down</span>
                            </span>
                            <span class="metric-label">MAX DRAWDOWN</span>
                        </div>
                        <div id="perf-max-dd" class="metric-value negative">0.00%</div>
                        <div id="perf-max-dd-value" class="metric-subtitle">$0.00</div>
                        <div class="metric-subtitle-detail">Fecha: --</div>
                    </div>
                </div>
            </div>

            <!-- MÉTRICAS DE RIESGO -->
            <div class="metrics-section">
                <h2 class="section-title">⚠️ Métricas de Riesgo y Trading</h2>
                <div class="metrics-grid">
                    <!-- Sortino Ratio -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon">
                                <span class="material-icons-outlined">speed</span>
                            </span>
                            <span class="metric-label">SORTINO RATIO</span>
                        </div>
                        <div id="risk-sortino" class="metric-value">0.00</div>
                        <div class="metric-subtitle">Retorno vs riesgo negativo</div>
                        <div class="metric-subtitle-detail">Óptimo > 2.0</div>
                    </div>

                    <!-- Calmar Ratio -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon">
                                <span class="material-icons-outlined">calculate</span>
                            </span>
                            <span class="metric-label">CALMAR RATIO</span>
                        </div>
                        <div id="risk-calmar" class="metric-value">0.00</div>
                        <div class="metric-subtitle">Retorno anual / Max DD</div>
                        <div class="metric-subtitle-detail">Bueno > 3.0</div>
                    </div>

                    <!-- Expectancy -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon">
                                <span class="material-icons-outlined">casino</span>
                            </span>
                            <span class="metric-label">EXPECTANCY</span>
                        </div>
                        <div id="risk-expectancy" class="metric-value">$0.00</div>
                        <div class="metric-subtitle">Ganancia esperada/trade</div>
                        <div class="metric-subtitle-detail" style="font-size: 0.7rem;">
                            (WR × AvgWin) - (LR × AvgLoss)
                        </div>
                    </div>

                    <!-- Total Trades -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <span class="metric-icon">
                                <span class="material-icons-outlined">bar_chart</span>
                            </span>
                            <span class="metric-label">TRADES TOTALES</span>
                        </div>
                        <div id="risk-total-trades" class="metric-value">0</div>
                        <div id="risk-avg-daily" class="metric-subtitle">Promedio: 0 trades/día</div>
                        <div id="risk-long-short" class="metric-subtitle-detail">Long: 0 / Short: 0</div>
                    </div>
                </div>
            </div>

            <!-- GRÁFICOS PRINCIPALES -->
            <div class="charts-row">
                <!-- Equity Curve -->
                <div class="chart-container" style="grid-column: span 2;">
                    <div class="chart-header">
                        <h3>📈 Curva de Equity del Fondo</h3>
                        <div class="chart-controls">
                            <button class="chart-btn" data-period="1D">1D</button>
                            <button class="chart-btn" data-period="7D">7D</button>
                            <button class="chart-btn active" data-period="30D">1M</button>
                            <button class="chart-btn" data-period="90D">3M</button>
                            <button class="chart-btn" data-period="YTD">YTD</button>
                            <button class="chart-btn" data-period="ALL">ALL</button>
                        </div>
                    </div>
                    <div class="chart-body" style="height: 400px; position: relative;">
                        <canvas id="equityChart"></canvas>
                    </div>
                </div>

                <!-- P&L Distribution -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>📊 Distribución P&L</h3>
                    </div>
                    <div class="chart-body" style="height: 350px; position: relative;">
                        <canvas id="plDistribution"></canvas>
                    </div>
                </div>
            </div>

            <!-- Segunda fila de gráficos -->
            <div class="charts-row">
                <!-- Monthly Returns Heatmap -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>🗓️ Retornos Mensuales</h3>
                    </div>
                    <div class="chart-body" style="height: 350px; position: relative;">
                        <div id="heatmapChart"></div>
                    </div>
                </div>

                <!-- Risk Metrics Radar -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>📊 Métricas de Riesgo</h3>
                    </div>
                    <div class="chart-body" style="height: 350px; position: relative;">
                        <canvas id="riskRadar"></canvas>
                    </div>
                </div>

                <!-- Symbol Performance -->
                <div class="chart-container">
                    <div class="chart-header">
                        <h3>💱 Performance por Símbolo</h3>
                    </div>
                    <div class="chart-body" style="height: 350px; position: relative;">
                        <canvas id="symbolPerformance"></canvas>
                    </div>
                </div>
            </div>

            <!-- DATA BLOCKS ROW 1 -->
            <div class="data-blocks-row">
                <!-- Trading Statistics -->
                <div class="data-block">
                    <h3>📈 Estadísticas de Trading</h3>
                    <div id="trading-stats" class="stats-grid">
                        <!-- Generado dinámicamente -->
                    </div>
                </div>

                <!-- Account Distribution -->
                <div class="data-block">
                    <h3>💰 Distribución por Cuenta</h3>
                    <div id="account-distribution" class="account-distribution">
                        <!-- Generado dinámicamente -->
                    </div>
                </div>
            </div>

            <!-- TABLA DE POSICIONES ABIERTAS -->
            <div class="table-section">
                <div class="table-header">
                    <h2>🔴 Posiciones Abiertas en Tiempo Real</h2>
                    <div class="table-actions">
                        <input type="text" id="positions-search" class="table-search" placeholder="Buscar posición...">
                        <select id="positions-filter" class="table-filter">
                            <option value="all">Todas</option>
                            <option value="profit">En Profit</option>
                            <option value="loss">En Pérdida</option>
                            <option value="buy">Solo Buy</option>
                            <option value="sell">Solo Sell</option>
                        </select>
                        <button class="btn btn-secondary" onclick="filterPositions()">
                            <span class="material-icons-outlined">filter_list</span>
                            Filtrar
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="positions-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Cuenta</th>
                                <th>Broker</th>
                                <th>Símbolo</th>
                                <th>Tipo</th>
                                <th>Volumen</th>
                                <th>Apertura</th>
                                <th>Actual</th>
                                <th>S/L</th>
                                <th>T/P</th>
                                <th>Swap</th>
                                <th>Comisión</th>
                                <th>Profit</th>
                                <th>Total P&L</th>
                                <th>Duración</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="positions-tbody">
                            <tr>
                                <td colspan="16" style="text-align: center; padding: 20px;">
                                    Cargando posiciones...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination">
                    <div class="pagination-info">
                        Mostrando <strong id="positions-showing">0-0</strong> de <strong id="positions-total">0</strong>
                        <span class="total-badge" id="positions-pl-total">P&L Total: $0.00</span>
                    </div>
                    <div class="pagination-controls">
                        <div class="items-per-page">
                            <label>Mostrar:</label>
                            <select id="positions-per-page" onchange="updatePositionsPagination()">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                        <div class="pagination-nav-group">
                            <button class="btn-pagination" onclick="goToPositionsPage('first')">
                                <span class="material-icons-outlined">first_page</span>
                            </button>
                            <button class="btn-pagination" onclick="goToPositionsPage('prev')">
                                <span class="material-icons-outlined">chevron_left</span>
                            </button>
                            <div class="page-numbers" id="positions-pages">
                                <button class="page-num active">1</button>
                            </div>
                            <button class="btn-pagination" onclick="goToPositionsPage('next')">
                                <span class="material-icons-outlined">chevron_right</span>
                            </button>
                            <button class="btn-pagination" onclick="goToPositionsPage('last')">
                                <span class="material-icons-outlined">last_page</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DATA BLOCKS ROW 2 -->
            <div class="data-blocks-row">
                <!-- Margin Status -->
                <div class="data-block">
                    <h3>💵 Estado del Margen</h3>
                    <div id="margin-status" class="margin-status">
                        <!-- Generado dinámicamente -->
                    </div>
                </div>

                <!-- Active Operations -->
                <div class="data-block">
                    <h3>🎯 Resumen de Operaciones</h3>
                    <div id="operations-summary" class="operations-summary">
                        <!-- Generado dinámicamente -->
                    </div>
                </div>
            </div>

            <!-- DATA BLOCKS ROW 3 -->
            <div class="data-blocks-row">
                <!-- Top Symbols -->
                <div class="data-block">
                    <h3>💱 Top Símbolos Operados</h3>
                    <div id="top-symbols" class="symbols-list">
                        <!-- Generado dinámicamente -->
                    </div>
                </div>

                <!-- Risk Metrics -->
                <div class="data-block">
                    <h3>⚠️ Métricas de Riesgo Actuales</h3>
                    <div id="current-risk" class="correlation-matrix">
                        <!-- Generado dinámicamente -->
                    </div>
                </div>
            </div>

            <!-- TABLA DE HISTORIAL -->
            <div class="table-section">
                <div class="table-header">
                    <h2>📜 Historial de Operaciones Cerradas</h2>
                    <div class="table-actions">
                        <input type="date" id="history-date-from" class="table-filter">
                        <input type="date" id="history-date-to" class="table-filter">
                        <select id="history-filter" class="table-filter">
                            <option value="all">Todas</option>
                            <option value="today">Hoy</option>
                            <option value="week">Esta Semana</option>
                            <option value="month">Este Mes</option>
                        </select>
                        <button class="btn btn-secondary" onclick="filterHistory()">
                            <span class="material-icons-outlined">search</span>
                            Buscar
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="history-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Cuenta</th>
                                <th>Fecha Cierre</th>
                                <th>Símbolo</th>
                                <th>Tipo</th>
                                <th>Volumen</th>
                                <th>Apertura</th>
                                <th>Cierre</th>
                                <th>Comisión</th>
                                <th>Swap</th>
                                <th>Profit</th>
                                <th>Total</th>
                                <th>Duración</th>
                                <th>Pips</th>
                            </tr>
                        </thead>
                        <tbody id="history-tbody">
                            <tr>
                                <td colspan="14" style="text-align: center; padding: 20px;">
                                    Cargando historial...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination">
                    <div class="pagination-info">
                        Mostrando <strong id="history-showing">0-0</strong> de <strong id="history-total">0</strong>
                        <span class="total-badge" id="history-pl-total">P&L Total: $0.00</span>
                    </div>
                    <div class="pagination-controls">
                        <div class="items-per-page">
                            <label>Mostrar:</label>
                            <select id="history-per-page" onchange="updateHistoryPagination()">
                                <option value="25">25</option>
                                <option value="50" selected>50</option>
                                <option value="100">100</option>
                                <option value="250">250</option>
                            </select>
                        </div>
                        <div class="pagination-nav-group">
                            <button class="btn-pagination" onclick="goToHistoryPage('first')">
                                <span class="material-icons-outlined">first_page</span>
                            </button>
                            <button class="btn-pagination" onclick="goToHistoryPage('prev')">
                                <span class="material-icons-outlined">chevron_left</span>
                            </button>
                            <div class="page-numbers" id="history-pages">
                                <button class="page-num active">1</button>
                            </div>
                            <button class="btn-pagination" onclick="goToHistoryPage('next')">
                                <span class="material-icons-outlined">chevron_right</span>
                            </button>
                            <button class="btn-pagination" onclick="goToHistoryPage('last')">
                                <span class="material-icons-outlined">last_page</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ALERTAS Y TIMELINE -->
            <div class="data-blocks-row">
                <!-- Recent Alerts -->
                <div class="data-block" style="grid-column: span 2;">
                    <h3>🔔 Alertas y Notificaciones Recientes</h3>
                    <div id="recent-alerts" class="alerts-grid extended">
                        <!-- Generado dinámicamente -->
                    </div>
                </div>

                <!-- Activity Timeline -->
                <div class="data-block">
                    <h3>📜 Línea de Tiempo</h3>
                    <div id="activity-timeline" class="timeline">
                        <!-- Generado dinámicamente -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Floating Action Button -->
    <div class="fab-container">
        <button class="fab">
            <span class="material-icons-outlined">add</span>
        </button>
        <div class="fab-menu">
            <button class="fab-item" title="Enviar Histórico Completo" onclick="sendFullHistory()">
                <span class="material-icons-outlined">sync</span>
            </button>
            <button class="fab-item" title="Nueva Alerta" onclick="createAlert()">
                <span class="material-icons-outlined">notifications_active</span>
            </button>
            <button class="fab-item" title="Generar Reporte" onclick="generateReport()">
                <span class="material-icons-outlined">description</span>
            </button>
            <button class="fab-item" title="Configuración" onclick="openSettings()">
                <span class="material-icons-outlined">settings</span>
            </button>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/charts.js"></script>
	<script src="assets/js/sidebar-counters.js"></script>
	<script src="assets/js/system-status.js"></script>
	<script src="assets/js/dashboard.js"></script>
    <script src="assets/js/api.js"></script>
	<script>
	// Fix de navegación - debe ejecutarse DESPUÉS de todos los demás scripts
	setTimeout(function() {
		document.querySelectorAll('.sidebar .menu-item').forEach(function(link) {
			var href = link.getAttribute('href');
			if (href && href.endsWith('.php')) {
				var newLink = link.cloneNode(true);
				link.parentNode.replaceChild(newLink, link);
			}
		});
	}, 100);
	</script>
</body>
</html>
</body>
</html>