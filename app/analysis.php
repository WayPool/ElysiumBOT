<?php
//+------------------------------------------------------------------+
//| analysis.php                                                    |
//| Página principal de Análisis y Rendimiento                     |
//| Copyright 2025, Elysium Media FZCO                              |
//| Sistema de Reportes de Trading v7.0                             |
//+------------------------------------------------------------------+

// Configuración de errores para producción
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Configuración de zona horaria
date_default_timezone_set('UTC');

// Incluir autenticación si existe
if (file_exists(__DIR__ . '/includes/auth_check.php')) {
    require_once __DIR__ . '/includes/auth_check.php';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Análisis & Rendimiento - Elysium Trading v7.0</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- ECharts -->
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    
    <!-- CSS Base del Sistema (NO MODIFICAR) -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/components.css">
    
    <!-- CSS Específico de esta página -->
    <link rel="stylesheet" href="assets/css/analysis.page.css">
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Top Navigation Bar (igual que otras páginas) -->
    <nav class="top-nav">
        <div class="nav-container">
            <div class="nav-left">
                <div class="logo">
                    <span class="logo-icon">⚡</span>
                    <span class="logo-text">ELYSIUM</span>
                    <span class="logo-subtitle">ANALYSIS v7.0</span>
                </div>
            </div>
            <div class="nav-center">
                <div class="search-box">
                    <span class="material-icons-outlined">search</span>
                    <input type="text" id="globalSearch" placeholder="Buscar bots, símbolos, operaciones...">
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
                <div class="nav-item" onclick="window.location.href='accounts.php'">
                    <span class="material-icons-outlined">account_balance</span>
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
        <!-- Sidebar desde includes -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Content Area -->
        <div class="content">
            <!-- Panel de Periodo y Filtros -->
            <div class="analysis-period-selector">
                <div class="period-buttons">
                    <button class="period-btn active" data-period="today">Hoy</button>
                    <button class="period-btn" data-period="ytd">YTD</button>
                    <button class="period-btn" data-period="mtd">MTD</button>
                    <button class="period-btn" data-period="30">30 días</button>
                    <button class="period-btn" data-period="90">90 días</button>
                    <button class="period-btn" data-period="365">365 días</button>
                    <button class="period-btn" data-period="custom">Personalizado</button>
                </div>
                
                <div class="filter-controls">
                    <select id="filterBot" class="filter-select">
                        <option value="">Todos los Bots</option>
                    </select>
                    <select id="filterAccount" class="filter-select">
                        <option value="">Todas las Cuentas</option>
                    </select>
                    <select id="filterSymbol" class="filter-select">
                        <option value="">Todos los Símbolos</option>
                    </select>
                    <button class="btn-reset-filters">🔄 Reiniciar</button>
                </div>
            </div>

            <!-- Panel de KPIs Principal -->
            <div class="analysis-kpis-hero">
                <div class="kpi-card">
                    <div class="kpi-label">Rentabilidad</div>
                    <div class="kpi-value" id="kpiReturn">--</div>
                    <div class="kpi-change positive">↑ +0.0%</div>
                    <div class="kpi-info" data-tooltip="Retorno total del periodo seleccionado">ℹ</div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-label">APR</div>
                    <div class="kpi-value" id="kpiAPR">--</div>
                    <div class="kpi-change">--</div>
                    <div class="kpi-info" data-tooltip="Tasa de Porcentaje Anual">ℹ</div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-label">Sharpe Ratio</div>
                    <div class="kpi-value" id="kpiSharpe">--</div>
                    <div class="kpi-change">--</div>
                    <div class="kpi-info" data-tooltip="Retorno ajustado al riesgo">ℹ</div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-label">Sortino Ratio</div>
                    <div class="kpi-value" id="kpiSortino">--</div>
                    <div class="kpi-change">--</div>
                    <div class="kpi-info" data-tooltip="Retorno ajustado al riesgo negativo">ℹ</div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-label">Max Drawdown</div>
                    <div class="kpi-value negative" id="kpiMaxDD">--</div>
                    <div class="kpi-change">--</div>
                    <div class="kpi-info" data-tooltip="Máxima pérdida desde un pico">ℹ</div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-label">Profit Factor</div>
                    <div class="kpi-value" id="kpiPF">--</div>
                    <div class="kpi-change">--</div>
                    <div class="kpi-info" data-tooltip="Ratio de ganancias vs pérdidas">ℹ</div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-label">Win Rate</div>
                    <div class="kpi-value" id="kpiWinRate">--</div>
                    <div class="kpi-change">--</div>
                    <div class="kpi-info" data-tooltip="Porcentaje de operaciones ganadoras">ℹ</div>
                </div>
                
                <div class="kpi-card">
                    <div class="kpi-label">Volatilidad</div>
                    <div class="kpi-value" id="kpiVolatility">--</div>
                    <div class="kpi-change">--</div>
                    <div class="kpi-info" data-tooltip="Volatilidad anualizada">ℹ</div>
                </div>
            </div>

            <!-- Comparador de Bots (Tabla Ranking) -->
            <div class="analysis-section">
                <div class="section-header">
                    <h2>🤖 Comparación de Bots</h2>
                    <div class="section-actions">
                        <select id="botRankingSort">
                            <option value="pnl">Ordenar por P&L</option>
                            <option value="return">Ordenar por Rentabilidad %</option>
                            <option value="sharpe">Ordenar por Sharpe</option>
                            <option value="maxdd">Ordenar por Max DD</option>
                            <option value="pf">Ordenar por Profit Factor</option>
                        </select>
                        <button class="btn-export" data-table="bots">📥 Exportar</button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="analysis-table" id="botsComparisonTable">
                        <thead>
                            <tr>
                                <th>Bot</th>
                                <th>Categoría</th>
                                <th>P&L</th>
                                <th>Rentabilidad %</th>
                                <th>Sharpe</th>
                                <th>Max DD</th>
                                <th>Profit Factor</th>
                                <th>Win Rate</th>
                                <th>Trades</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="botsTableBody">
                            <!-- Se llena dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Gráficos de Series Temporales -->
            <div class="analysis-charts-row">
                <div class="analysis-chart-container large">
                    <div class="chart-header">
                        <h3>📈 Equity Curve</h3>
                        <div class="chart-controls">
                            <button class="chart-btn active" data-chart="equity" data-type="linear">Lineal</button>
                            <button class="chart-btn" data-chart="equity" data-type="log">Log</button>
                        </div>
                    </div>
                    <canvas id="equityChart"></canvas>
                </div>
                
                <div class="analysis-chart-container">
                    <div class="chart-header">
                        <h3>📊 Distribución de Retornos</h3>
                    </div>
                    <canvas id="returnsDistribution"></canvas>
                </div>
            </div>

            <!-- Métricas Rolling -->
            <div class="analysis-charts-row">
                <div class="analysis-chart-container">
                    <div class="chart-header">
                        <h3>📉 Rolling Sharpe</h3>
                        <select class="rolling-window">
                            <option value="30">30 días</option>
                            <option value="60">60 días</option>
                            <option value="90">90 días</option>
                        </select>
                    </div>
                    <canvas id="rollingSharpe"></canvas>
                </div>
                
                <div class="analysis-chart-container">
                    <div class="chart-header">
                        <h3>📊 Rolling Volatilidad</h3>
                    </div>
                    <canvas id="rollingVolatility"></canvas>
                </div>
                
                <div class="analysis-chart-container">
                    <div class="chart-header">
                        <h3>🔻 Rolling Max DD</h3>
                    </div>
                    <canvas id="rollingMaxDD"></canvas>
                </div>
            </div>

            <!-- Diversificación -->
            <div class="analysis-section">
                <div class="section-header">
                    <h2>🎯 Análisis de Diversificación</h2>
                </div>
                
                <div class="analysis-charts-row">
                    <div class="analysis-chart-container large">
                        <div class="chart-header">
                            <h3>🗺️ Mapa de Distribución</h3>
                            <select id="treemapType">
                                <option value="asset">Por Activo</option>
                                <option value="bot">Por Bot</option>
                                <option value="account">Por Cuenta</option>
                            </select>
                        </div>
                        <div id="treemapChart" style="height: 400px;"></div>
                    </div>
                    
                    <div class="analysis-chart-container">
                        <div class="chart-header">
                            <h3>🔄 Flujo de Capital</h3>
                        </div>
                        <div id="sankeyChart" style="height: 400px;"></div>
                    </div>
                </div>
            </div>

            <!-- Matriz de Correlación -->
            <div class="analysis-section">
                <div class="section-header">
                    <h2>🔗 Matriz de Correlación</h2>
                    <div class="section-actions">
                        <select id="correlationType">
                            <option value="pearson">Pearson</option>
                            <option value="spearman">Spearman</option>
                        </select>
                    </div>
                </div>
                
                <div class="analysis-chart-container full">
                    <div id="correlationMatrix" style="height: 500px;"></div>
                </div>
            </div>

            <!-- Análisis de Estabilidad -->
            <div class="analysis-section">
                <div class="section-header">
                    <h2>📅 Análisis de Estabilidad</h2>
                </div>
                
                <div class="analysis-charts-row">
                    <div class="analysis-chart-container large">
                        <div class="chart-header">
                            <h3>🗓️ Calendario de Retornos</h3>
                        </div>
                        <div id="calendarHeatmap" style="height: 300px;"></div>
                    </div>
                    
                    <div class="analysis-chart-container">
                        <div class="chart-header">
                            <h3>⏰ Rendimiento por Hora</h3>
                        </div>
                        <canvas id="hourlyPerformance"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tabla de Operaciones -->
            <div class="analysis-section">
                <div class="section-header">
                    <h2>📊 Operaciones Detalladas</h2>
                    <div class="section-actions">
                        <input type="text" class="search-input" placeholder="Buscar...">
                        <select id="operationsFilter">
                            <option value="all">Todas</option>
                            <option value="open">Abiertas</option>
                            <option value="closed">Cerradas</option>
                        </select>
                        <button class="btn-export" data-table="operations">📥 Exportar</button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="analysis-table" id="operationsTable">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Fecha/Hora</th>
                                <th>Bot</th>
                                <th>Símbolo</th>
                                <th>Tipo</th>
                                <th>Volumen</th>
                                <th>Precio Entrada</th>
                                <th>Precio Salida</th>
                                <th>P&L</th>
                                <th>Duración</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="operationsTableBody">
                            <!-- Se llena dinámicamente -->
                        </tbody>
                    </table>
                    
                    <div class="table-pagination">
                        <button class="pagination-btn" id="prevPage">⬅</button>
                        <span id="pageInfo">Página 1 de 1</span>
                        <button class="pagination-btn" id="nextPage">➡</button>
                    </div>
                </div>
            </div>

            <!-- Modal de Detalle de Bot -->
            <div id="botDetailModal" class="analysis-modal" style="display: none;">
                <div class="modal-content">
                    <span class="modal-close">&times;</span>
                    <h2 id="botDetailTitle">Detalle del Bot</h2>
                    
                    <div class="bot-detail-content">
                        <div class="bot-detail-stats">
                            <!-- KPIs específicos del bot -->
                        </div>
                        
                        <div class="bot-detail-charts">
                            <canvas id="botEquityChart"></canvas>
                        </div>
                        
                        <div class="bot-detail-info">
                            <!-- Información adicional -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Glosario Lateral (Plegable) -->
            <div id="glossaryPanel" class="analysis-glossary">
                <button class="glossary-toggle">📖</button>
                <div class="glossary-content">
                    <h3>Glosario de Términos</h3>
                    <dl>
                        <dt>Sharpe Ratio</dt>
                        <dd>Mide el rendimiento ajustado al riesgo. Valores > 1 son buenos, > 2 excelentes.</dd>
                        
                        <dt>Sortino Ratio</dt>
                        <dd>Similar al Sharpe pero solo considera la volatilidad negativa.</dd>
                        
                        <dt>Max Drawdown</dt>
                        <dd>Máxima pérdida desde un pico hasta un valle.</dd>
                        
                        <dt>Profit Factor</dt>
                        <dd>Ratio entre ganancias brutas y pérdidas brutas. > 1.5 es bueno.</dd>
                        
                        <dt>APR</dt>
                        <dd>Annual Percentage Rate - Tasa de retorno anualizada.</dd>
                        
                        <dt>APY</dt>
                        <dd>Annual Percentage Yield - Rendimiento anual con interés compuesto.</dd>
                        
                        <dt>Volatilidad</dt>
                        <dd>Medida de la variabilidad de los retornos.</dd>
                        
                        <dt>Win Rate</dt>
                        <dd>Porcentaje de operaciones ganadoras sobre el total.</dd>
                        
                        <dt>Expectancy</dt>
                        <dd>Ganancia esperada por operación.</dd>
                        
                        <dt>VaR</dt>
                        <dd>Value at Risk - Pérdida máxima esperada con cierto nivel de confianza.</dd>
                        
                        <dt>CVaR</dt>
                        <dd>Conditional VaR - Pérdida esperada más allá del VaR.</dd>
                    </dl>
                </div>
            </div>

            <!-- Disclaimer -->
            <div class="analysis-disclaimer">
                <p>⚠️ Los rendimientos pasados no garantizan resultados futuros. El trading conlleva riesgos significativos.</p>
            </div>
        </div>
    </div>

    <!-- Scripts Base (NO MODIFICAR) -->
    <script src="assets/js/api.js"></script>
    
    <!-- Script de configuración de bots -->
    <script src="assets/js/bot-names.config.js"></script>
    
    <!-- Script Específico de esta página -->
    <script src="assets/js/analysis.page.js"></script>
    
    <!-- Script para funciones globales -->
    <script>
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }
    </script>
</body>
</html>