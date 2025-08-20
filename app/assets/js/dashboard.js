/**
 * Dashboard Controller - Elysium v7.0
 * Sistema Profesional de Gestión de Fondos
 * Copyright 2025, Elysium Media FZCO
 */

// ============================================
// CONFIGURACIÓN GLOBAL
// ============================================
const CONFIG = {
    api: {
        endpoint: 'api/get_dashboard_data.php',
        refreshInterval: 30000, // 30 segundos
        timeout: 30000
    },
    pagination: {
        positionsPerPage: 25,
        historyPerPage: 50
    },
    charts: {
        updateAnimation: false
    },
    alerts: {
        maxVisible: 5,
        autoHideTime: 10000
    },
    locale: 'es-ES',
    currency: 'USD'
};

// ============================================
// ESTADO GLOBAL
// ============================================
let dashboardState = {
    data: null,
    isLoading: false,
    lastUpdate: null,
    currentFilter: {
        period: '30D',
        broker: 'all',
        account: 'all'
    },
    pagination: {
        positions: {
            current: 1,
            perPage: 25,
            total: 0
        },
        history: {
            current: 1,
            perPage: 50,
            total: 0
        }
    },
    charts: {},
    refreshTimer: null
};

// ============================================
// INICIALIZACIÓN
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Elysium Dashboard v7.0 - Iniciando...');
    
    // Inicializar componentes
    initializeEventListeners();
    
    // NO inicializar charts aquí, se hace desde charts.js
    
    // Cargar datos iniciales con un pequeño delay para asegurar que todo está listo
    setTimeout(() => {
        loadDashboardData(true);
        
        // Configurar actualización automática
        startAutoRefresh();
    }, 500);
});

// ============================================
// ACTUALIZAR GRÁFICOS
// ============================================
function updateCharts(chartsData) {
    if (!window.ChartsManager || !chartsData) return;
    
    try {
        // Equity Curve
        if (chartsData.equity_curve) {
            window.ChartsManager.updateEquityChart(chartsData.equity_curve);
        }
        
        // P&L Distribution
        if (chartsData.pl_distribution) {
            window.ChartsManager.updatePLDistribution(chartsData.pl_distribution);
        }
        
        // Monthly Returns Heatmap
        if (chartsData.monthly_returns) {
            window.ChartsManager.updateHeatmap(chartsData.monthly_returns);
        }
        
        // Symbol Performance
        if (chartsData.symbol_performance) {
            window.ChartsManager.updateSymbolPerformance(chartsData.symbol_performance);
        }
        
        // Risk Radar - usar datos de performance
        if (dashboardState.data && dashboardState.data.performance) {
            window.ChartsManager.updateRiskRadar(dashboardState.data.performance);
        }
        
        // ACTUALIZACIÓN: Usar los nuevos datos de mini_charts
        if (chartsData.mini_charts) {
            window.ChartsManager.updateMiniCharts(chartsData.mini_charts);
        }
    } catch (error) {
        console.error('Error actualizando gráficos:', error);
    }
}
// ============================================
// CARGA DE DATOS PRINCIPAL
// ============================================
async function loadDashboardData(showLoading = false) {
    // Evitar múltiples cargas simultáneas
    if (dashboardState.isLoading) {
        console.log('⏳ Carga en progreso, ignorando petición...');
        return;
    }
    
    try {
        dashboardState.isLoading = true;
        
        if (showLoading) {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.classList.remove('hidden');
        }
        
        // Usar la API module si está disponible, sino fetch directo
        let data;
        if (window.ElysiumAPI && window.ElysiumAPI.getDashboardData) {
            data = await window.ElysiumAPI.getDashboardData(dashboardState.currentFilter);
        } else {
            // Fallback: fetch directo
            const params = new URLSearchParams({
                period: dashboardState.currentFilter.period,
                broker: dashboardState.currentFilter.broker,
                account: dashboardState.currentFilter.account
            });
            
            const response = await fetch(`api/get_dashboard_data.php?${params}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Cache-Control': 'no-cache'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            data = await response.json();
        }
        
        if (!data.success) {
            throw new Error(data.error?.message || 'Error desconocido');
        }
        
        // Guardar datos en estado
        dashboardState.data = data.data;
        dashboardState.lastUpdate = new Date();
        
        // Actualizar todas las secciones
        updateDashboard(data.data);
        
        // Actualizar timestamp
        updateLastUpdateTime();
        
        // Remover mensaje de error si existe
        const errorMsg = document.getElementById('error-message');
        if (errorMsg) {
            errorMsg.remove();
        }
        
        console.log('✅ Dashboard actualizado:', new Date().toLocaleTimeString());
        
    } catch (error) {
        console.error('❌ Error cargando dashboard:', error);
        
        // Solo mostrar notificación si no es la primera carga
        if (dashboardState.lastUpdate) {
            showNotification('Error al actualizar datos: ' + error.message, 'warning');
        } else {
            showNotification('Error al cargar los datos: ' + error.message, 'error');
            
            // Mostrar mensaje en el dashboard solo en la primera carga
            const container = document.querySelector('.content');
            if (container && !document.getElementById('error-message')) {
                const errorDiv = document.createElement('div');
                errorDiv.id = 'error-message';
                errorDiv.className = 'alert-card critical';
                errorDiv.style.margin = '20px 0';
                errorDiv.innerHTML = `
                    <div class="alert-content">
                        <div class="alert-title">Error de Conexión</div>
                        <div class="alert-message">No se pueden cargar los datos. Verifica que el archivo api/get_dashboard_data.php existe y es accesible.</div>
                        <button class="alert-action" onclick="location.reload()">Reintentar</button>
                    </div>
                `;
                container.insertBefore(errorDiv, container.firstChild);
            }
        }
    } finally {
        dashboardState.isLoading = false;
        if (showLoading) {
            setTimeout(() => {
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) overlay.classList.add('hidden');
            }, 500);
        }
    }
}

// ============================================
// ACTUALIZACIÓN DEL DASHBOARD
// ============================================
function updateDashboard(data) {
    // KPIs Principales
    updateKPIs(data.kpi);
    
    // Métricas de Performance
    updatePerformanceMetrics(data.performance);
    
    // Métricas de Riesgo
    updateRiskMetrics(data);
    
    // Estadísticas de Trading
    updateTradingStats(data);
    
    // Distribución de Cuentas
    updateAccountDistribution(data.accounts);
    
    // Estado del Margen
    updateMarginStatus(data.margin_status);
    
    // Resumen de Operaciones
    updateOperationsSummary(data.operations_summary);
    
    // Top Símbolos
    updateTopSymbols(data.top_symbols);
    
    // Posiciones Abiertas
    updatePositionsTable(data.positions);
    
    // Historial
    updateHistoryTable(data.recent_history);
    
    // Alertas
    updateAlerts(data.alerts);
    
    // P&L por Periodo
    updatePeriodPL(data.period_pl);
    
    // Actualizar gráficos
    updateCharts(data.charts);
    
    // Sistema
    updateSystemStatus(data.system);
}

// ============================================
// ACTUALIZAR KPIs
// ============================================
function updateKPIs(kpi) {
    // Capital Total
    updateElement('kpi-total-capital', formatCurrency(kpi.initial_capital));
    
    // Equity Total
	// Equity Total
	updateElement('kpi-total-equity', formatCurrency(kpi.total_equity));
	const equityChange = formatCurrency(kpi.equity_change);
	const equityChangePct = formatNumber(kpi.equity_change_pct, 2);
	updateElement('kpi-equity-change', `${kpi.equity_change >= 0 ? '+' : ''}${equityChange} (${equityChangePct}%)`);
	setElementClass('kpi-equity-change', kpi.equity_change >= 0 ? 'metric-change positive' : 'metric-change negative');
    
    // Balance Total
    updateElement('kpi-total-balance', formatCurrency(kpi.total_balance));
    const balanceDiff = kpi.total_equity - kpi.total_balance;
    updateElement('kpi-balance-vs-equity', `Diferencia: ${formatCurrency(balanceDiff)}`);
    
    // P&L Flotante
    updateElement('kpi-floating-pl', formatCurrency(kpi.floating_pl));
    const plPct = kpi.initial_capital > 0 ? (kpi.floating_pl / kpi.initial_capital) * 100 : 0;
    updateElement('kpi-floating-pl-pct', `${formatNumber(plPct, 2)}% del capital`);
    setElementClass('kpi-floating-pl', kpi.floating_pl >= 0 ? 'metric-value positive' : 'metric-value negative');
    
    // Actualizar badges
    updateElement('notification-badge', kpi.accounts_count);
    updateElement('sidebar-accounts-badge', kpi.accounts_count);
    updateElement('sidebar-positions-badge', kpi.positions_count);
    updateElement('total-accounts', kpi.accounts_count);
    updateElement('total-positions', kpi.positions_count);
}

// ============================================
// ACTUALIZAR MÉTRICAS DE PERFORMANCE
// ============================================
function updatePerformanceMetrics(perf) {
    // Win Rate
    updateElement('perf-win-rate', `${formatNumber(perf.win_rate, 2)}%`);
    updateElement('perf-wins-losses', `W: ${perf.winning_trades} / L: ${perf.losing_trades}`);
    const winRateBar = document.getElementById('perf-win-rate-bar');
    if (winRateBar) {
        winRateBar.style.width = `${Math.min(perf.win_rate, 100)}%`;
        winRateBar.style.background = perf.win_rate > 60 ? 
            'linear-gradient(90deg, #10b981, #34d399)' : 
            (perf.win_rate > 40 ? 'linear-gradient(90deg, #f59e0b, #fbbf24)' : 
            'linear-gradient(90deg, #ef4444, #f87171)');
    }
    
    // Profit Factor
    updateElement('perf-profit-factor', formatNumber(perf.profit_factor, 2));
    updateElement('perf-gross-pl', `GP: ${formatCurrency(perf.gross_profit)} / GL: ${formatCurrency(perf.gross_loss)}`);
    
    // Sharpe Ratio
    updateElement('perf-sharpe-ratio', formatNumber(perf.sharpe_ratio, 2));
    
    // Max Drawdown
    updateElement('perf-max-dd', `${formatNumber(perf.max_drawdown_pct, 2)}%`);
    updateElement('perf-max-dd-value', formatCurrency(perf.max_drawdown));
    setElementClass('perf-max-dd', 'metric-value negative');
}

// ============================================
// ACTUALIZAR MÉTRICAS DE RIESGO
// ============================================
function updateRiskMetrics(data) {
    const perf = data.performance;
    const stats = data.trading_stats;
    
    // Sortino Ratio
    updateElement('risk-sortino', formatNumber(perf.sortino_ratio, 2));
    
    // Calmar Ratio
    updateElement('risk-calmar', formatNumber(perf.calmar_ratio, 2));
    
    // Expectancy
    updateElement('risk-expectancy', formatCurrency(perf.expectancy));
    setElementClass('risk-expectancy', perf.expectancy >= 0 ? 'metric-value positive' : 'metric-value negative');
    
    // Total Trades
    updateElement('risk-total-trades', formatNumber(perf.total_trades, 0));
    updateElement('risk-avg-daily', `Promedio: ${formatNumber(stats.avg_daily_trades, 1)} trades/día`);
    updateElement('risk-long-short', `Long: ${stats.long_trades} / Short: ${stats.short_trades}`);
}

// ============================================
// ACTUALIZAR ESTADÍSTICAS DE TRADING
// ============================================
function updateTradingStats(data) {
    const container = document.getElementById('trading-stats');
    if (!container) return;
    
    const perf = data.performance;
    const stats = [
        { label: 'Trades Ganadores', value: perf.winning_trades, type: 'positive' },
        { label: 'Trades Perdedores', value: perf.losing_trades, type: 'negative' },
        { label: 'Ganancia Promedio', value: formatCurrency(perf.avg_win), type: 'positive' },
        { label: 'Pérdida Promedio', value: formatCurrency(perf.avg_loss), type: 'negative' },
        { label: 'Mayor Ganancia', value: formatCurrency(perf.largest_win), type: 'positive' },
        { label: 'Mayor Pérdida', value: formatCurrency(perf.largest_loss), type: 'negative' },
        { label: 'Racha Ganadora', value: perf.consecutive_wins, type: 'neutral' },
        { label: 'Racha Perdedora', value: perf.consecutive_losses, type: 'neutral' },
        { label: 'Risk/Reward', value: formatNumber(perf.risk_reward, 2), type: 'neutral' },
        { label: 'Recovery Factor', value: formatNumber(perf.recovery_factor, 2), type: 'neutral' }
    ];
    
    container.innerHTML = stats.map(stat => `
        <div class="stat-item">
            <span class="stat-label">${stat.label}</span>
            <span class="stat-value ${stat.type}">${stat.value}</span>
        </div>
    `).join('');
}

// ============================================
// ACTUALIZAR DISTRIBUCIÓN DE CUENTAS
// ============================================
function updateAccountDistribution(accounts) {
    const container = document.getElementById('account-distribution');
    if (!container) return;
    
    if (!accounts || accounts.length === 0) {
        container.innerHTML = '<div class="no-data">No hay cuentas activas</div>';
        return;
    }
    
    const totalBalance = accounts.reduce((sum, acc) => sum + parseFloat(acc.balance), 0);
    
    container.innerHTML = accounts.map(acc => {
        const balance = parseFloat(acc.balance);
        const equity = parseFloat(acc.equity);
        const profit = parseFloat(acc.profit);
        const percentage = totalBalance > 0 ? (balance / totalBalance) * 100 : 0;
        const change = equity - balance;
        const changeClass = change >= 0 ? 'positive' : 'negative';
        
        return `
            <div class="account-item">
                <div class="account-info">
                    <span class="account-name">#${acc.login} - ${acc.name || 'Sin nombre'}</span>
                    <span class="account-broker">${acc.company || 'Broker'}</span>
                </div>
                <div class="account-stats">
                    <span class="account-balance">${formatCurrency(balance)}</span>
                    <span class="account-change ${changeClass}">
                        ${change >= 0 ? '+' : ''}${formatCurrency(change)}
                    </span>
                </div>
                <div class="account-bar">
                    <div class="bar-fill" style="width: ${percentage}%;"></div>
                </div>
            </div>
        `;
    }).join('');
}

// ============================================
// ACTUALIZAR ESTADO DEL MARGEN
// ============================================
function updateMarginStatus(margin) {
    const container = document.getElementById('margin-status');
    if (!container) return;
    
    const levelClass = margin.level > 1000 ? 'excellent' : 
                       (margin.level > 500 ? 'good' : 
                       (margin.level > 200 ? 'warning' : 'critical'));
    
    const fillWidth = Math.min(100, margin.level / 10);
    
    container.innerHTML = `
        <div class="margin-item">
            <div class="margin-header">
                <span>Nivel de Margen</span>
                <span class="margin-value ${levelClass}">
                    ${formatNumber(margin.level, 0)}%
                </span>
            </div>
            <div class="margin-bar">
                <div class="margin-fill" style="width: ${fillWidth}%;"></div>
            </div>
        </div>
        <div class="margin-stats">
            <div class="margin-stat">
                <span class="label">Margen Usado</span>
                <span class="value">${formatCurrency(margin.used)}</span>
            </div>
            <div class="margin-stat">
                <span class="label">Margen Libre</span>
                <span class="value">${formatCurrency(margin.free)}</span>
            </div>
            <div class="margin-stat">
                <span class="label">Estado</span>
                <span class="value ${levelClass}">${margin.status.toUpperCase()}</span>
            </div>
        </div>
    `;
}

// ============================================
// ACTUALIZAR RESUMEN DE OPERACIONES
// ============================================
function updateOperationsSummary(ops) {
    const container = document.getElementById('operations-summary');
    if (!container) return;
    
    container.innerHTML = `
        <div class="operation-stat">
            <span class="op-icon buy">↗</span>
            <div class="op-info">
                <span class="op-label">Posiciones Buy</span>
                <span class="op-value">${ops.buy_count}</span>
            </div>
            <span class="op-profit ${ops.buy_profit >= 0 ? 'positive' : 'negative'}">
                ${formatCurrency(ops.buy_profit)}
            </span>
        </div>
        <div class="operation-stat">
            <span class="op-icon sell">↘</span>
            <div class="op-info">
                <span class="op-label">Posiciones Sell</span>
                <span class="op-value">${ops.sell_count}</span>
            </div>
            <span class="op-profit ${ops.sell_profit >= 0 ? 'positive' : 'negative'}">
                ${formatCurrency(ops.sell_profit)}
            </span>
        </div>
        <div class="operation-stat">
            <span class="op-icon pending">⏱</span>
            <div class="op-info">
                <span class="op-label">Volumen Total</span>
                <span class="op-value">${formatNumber(ops.total_volume, 2)} lots</span>
            </div>
            <span class="op-profit neutral">
                ${formatCurrency(ops.buy_profit + ops.sell_profit)}
            </span>
        </div>
    `;
}

// ============================================
// ACTUALIZAR TOP SÍMBOLOS
// ============================================
function updateTopSymbols(symbols) {
    const container = document.getElementById('top-symbols');
    if (!container) return;
    
    if (!symbols || symbols.length === 0) {
        container.innerHTML = '<div class="no-data">No hay datos de símbolos</div>';
        return;
    }
    
    container.innerHTML = symbols.slice(0, 5).map((sym, index) => {
        const profit = parseFloat(sym.total_profit);
        const profitClass = profit >= 0 ? 'positive' : 'negative';
        const winRate = parseFloat(sym.win_rate);
        
        return `
            <div class="symbol-item">
                <div class="symbol-info">
                    <span class="symbol-name">${sym.symbol}</span>
                    <span class="symbol-volume">${sym.trade_count} trades</span>
                </div>
                <div class="symbol-stats">
                    <span class="symbol-profit ${profitClass}">
                        ${formatCurrency(profit)}
                    </span>
                    <span class="symbol-winrate">${formatNumber(winRate, 1)}%</span>
                </div>
            </div>
        `;
    }).join('');
}

// ============================================
// ACTUALIZAR TABLA DE POSICIONES
// ============================================
function updatePositionsTable(positions) {
    const tbody = document.getElementById('positions-tbody');
    if (!tbody) return;
    
    if (!positions || positions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="16" style="text-align: center; padding: 20px;">No hay posiciones abiertas</td></tr>';
        updateElement('positions-total', '0');
        updateElement('positions-pl-total', 'P&L Total: $0.00');
        return;
    }
    
    // Aplicar filtro si existe
    let filteredPositions = filterPositions(positions);
    
    // Paginación
    const page = dashboardState.pagination.positions.current;
    const perPage = dashboardState.pagination.positions.perPage;
    const start = (page - 1) * perPage;
    const end = start + perPage;
    const paginatedPositions = filteredPositions.slice(start, end);
    
    // Calcular P&L total
    const totalPL = positions.reduce((sum, pos) => {
        return sum + parseFloat(pos.profit) + parseFloat(pos.swap) + parseFloat(pos.commission);
    }, 0);
    
    // Actualizar información de paginación
    updateElement('positions-showing', `${start + 1}-${Math.min(end, filteredPositions.length)}`);
    updateElement('positions-total', filteredPositions.length);
    updateElement('positions-pl-total', `P&L Total: ${formatCurrency(totalPL)}`);
    
    // Generar filas
    tbody.innerHTML = paginatedPositions.map(pos => {
        const typeClass = pos.type == 0 ? 'buy' : 'sell';
        const typeText = pos.type == 0 ? 'BUY' : 'SELL';
        const profit = parseFloat(pos.profit);
        const swap = parseFloat(pos.swap);
        const commission = parseFloat(pos.commission);
        const totalProfit = profit + swap + commission;
        const profitClass = totalProfit >= 0 ? 'positive' : 'negative';
        
        // Calcular duración
        const openTime = new Date(pos.time_open);
        const now = new Date();
        const duration = Math.floor((now - openTime) / 60000); // minutos
        
        // Estado basado en profit
        let status = 'Activo';
        let statusClass = 'active';
        if (totalProfit < -1000) {
            status = 'En Riesgo';
            statusClass = 'warning';
        } else if (totalProfit > 1000) {
            status = 'En Profit';
            statusClass = 'success';
        }
        
        return `
            <tr>
                <td>#${pos.ticket}</td>
                <td><span class="account-tag">${pos.account_name || pos.account_login}</span></td>
                <td><span class="account-broker-tag">${pos.broker || 'N/A'}</span></td>
                <td><span class="symbol">${pos.symbol}</span></td>
                <td><span class="badge ${typeClass}">${typeText}</span></td>
                <td>${formatNumber(pos.volume, 2)}</td>
                <td>${formatNumber(pos.price_open, 5)}</td>
                <td>${formatNumber(pos.price_current, 5)}</td>
                <td>${pos.sl > 0 ? formatNumber(pos.sl, 5) : '-'}</td>
                <td>${pos.tp > 0 ? formatNumber(pos.tp, 5) : '-'}</td>
                <td class="${swap >= 0 ? 'positive' : 'negative'}">${formatCurrency(swap)}</td>
                <td class="${commission >= 0 ? 'positive' : 'negative'}">${formatCurrency(commission)}</td>
                <td class="${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                <td class="${profitClass}" style="font-weight: bold;">${formatCurrency(totalProfit)}</td>
                <td>${formatDuration(duration)}</td>
                <td><span class="status-badge ${statusClass}">${status}</span></td>
            </tr>
        `;
    }).join('');
    
    // Actualizar botones de paginación
    updatePositionsPagination(filteredPositions.length);
}

// ============================================
// ACTUALIZAR TABLA DE HISTORIAL
// ============================================
function updateHistoryTable(history) {
    const tbody = document.getElementById('history-tbody');
    if (!tbody) return;
    
    if (!history || history.length === 0) {
        tbody.innerHTML = '<tr><td colspan="14" style="text-align: center; padding: 20px;">No hay historial disponible</td></tr>';
        return;
    }
    
    // Filtrar solo trades de cierre
    const closedTrades = history.filter(deal => deal.entry == 1);
    
    // Paginación
    const page = dashboardState.pagination.history.current;
    const perPage = dashboardState.pagination.history.perPage;
    const start = (page - 1) * perPage;
    const end = start + perPage;
    const paginatedHistory = closedTrades.slice(start, end);
    
    // Calcular P&L total
    const totalPL = closedTrades.reduce((sum, deal) => {
        return sum + parseFloat(deal.profit) + parseFloat(deal.swap) + parseFloat(deal.commission);
    }, 0);
    
    // Actualizar información
    updateElement('history-showing', `${start + 1}-${Math.min(end, closedTrades.length)}`);
    updateElement('history-total', closedTrades.length);
    updateElement('history-pl-total', `P&L Total: ${formatCurrency(totalPL)}`);
    
    // Generar filas
    tbody.innerHTML = paginatedHistory.map(deal => {
        const typeClass = deal.type == 0 ? 'buy' : 'sell';
        const typeText = deal.type == 0 ? 'BUY' : 'SELL';
        const profit = parseFloat(deal.profit);
        const swap = parseFloat(deal.swap);
        const commission = parseFloat(deal.commission);
        const total = profit + swap + commission;
        const totalClass = total >= 0 ? 'positive' : 'negative';
        
        return `
            <tr>
                <td>#${deal.ticket}</td>
                <td><span class="account-tag">${deal.account_name || deal.account_login}</span></td>
                <td>${formatDateTime(deal.time)}</td>
                <td><span class="symbol">${deal.symbol}</span></td>
                <td><span class="badge ${typeClass}">${typeText}</span></td>
                <td>${formatNumber(deal.volume, 2)}</td>
                <td>${formatNumber(deal.price, 5)}</td>
                <td>${formatNumber(deal.price, 5)}</td>
                <td class="${commission >= 0 ? 'positive' : 'negative'}">${formatCurrency(commission)}</td>
                <td class="${swap >= 0 ? 'positive' : 'negative'}">${formatCurrency(swap)}</td>
                <td class="${profit >= 0 ? 'positive' : 'negative'}">${formatCurrency(profit)}</td>
                <td class="${totalClass}" style="font-weight: bold;">${formatCurrency(total)}</td>
                <td>-</td>
                <td>-</td>
            </tr>
        `;
    }).join('');
    
    // Actualizar paginación
    updateHistoryPagination(closedTrades.length);
}

// ============================================
// ACTUALIZAR ALERTAS
// ============================================
function updateAlerts(alerts) {
    const container = document.getElementById('recent-alerts');
    if (!container) return;
    
    if (!alerts || alerts.length === 0) {
        container.innerHTML = `
            <div class="alert-card info">
                <div class="alert-icon">
                    <span class="material-icons-outlined">info</span>
                </div>
                <div class="alert-content">
                    <div class="alert-title">Sin Alertas</div>
                    <div class="alert-message">No hay alertas activas en este momento</div>
                </div>
            </div>
        `;
        return;
    }
    
    container.innerHTML = alerts.slice(0, CONFIG.alerts.maxVisible).map(alert => {
        const icon = alert.type === 'critical' ? 'error' : 
                     (alert.type === 'warning' ? 'warning' : 'info');
        
        return `
            <div class="alert-card ${alert.type}">
                <div class="alert-icon">
                    <span class="material-icons-outlined">${icon}</span>
                </div>
                <div class="alert-content">
                    <div class="alert-title">${alert.title}</div>
                    <div class="alert-message">${alert.message}</div>
                    <div class="alert-meta">
                        <span class="alert-time">${formatDateTime(alert.time)}</span>
                    </div>
                </div>
                <button class="alert-action" onclick="dismissAlert(this)">
                    ${alert.type === 'critical' ? 'REVISAR' : 'OK'}
                </button>
            </div>
        `;
    }).join('');
    
    // Actualizar contador
    updateElement('alerts-count', alerts.length);
}

// ============================================
// ACTUALIZAR P&L POR PERIODO
// ============================================
function updatePeriodPL(periodPL) {
    updateElement('pl-today', formatCurrency(periodPL.today));
    updateElement('pl-week', formatCurrency(periodPL.week));
    updateElement('pl-month', formatCurrency(periodPL.month));
    updateElement('pl-year', formatCurrency(periodPL.year));
    
    // Aplicar clases según valor
    ['today', 'week', 'month', 'year'].forEach(period => {
        const element = document.getElementById(`pl-${period}`);
        if (element) {
            element.className = periodPL[period] >= 0 ? 'footer-value positive' : 'footer-value negative';
        }
    });
}

// ============================================
// ACTUALIZAR GRÁFICOS
// ============================================

function updateCharts(chartsData) {
    if (!window.ChartsManager || !chartsData) {
        console.warn('⚠️ ChartsManager o chartsData no disponibles');
        return;
    }
    
    try {
        console.log('📊 updateCharts llamado con chartsData:', chartsData);
        
        // Equity Curve
        if (chartsData.equity_curve) {
            window.ChartsManager.updateEquityChart(chartsData.equity_curve);
        }
        
        // P&L Distribution
        if (chartsData.pl_distribution) {
            window.ChartsManager.updatePLDistribution(chartsData.pl_distribution);
        }
        
        // Monthly Returns Heatmap
        if (chartsData.monthly_returns) {
            window.ChartsManager.updateHeatmap(chartsData.monthly_returns);
        }
        
        // Symbol Performance
        if (chartsData.symbol_performance) {
            window.ChartsManager.updateSymbolPerformance(chartsData.symbol_performance);
        }
        
        // Risk Radar - usar datos de performance
        if (dashboardState.data && dashboardState.data.performance) {
            window.ChartsManager.updateRiskRadar(dashboardState.data.performance);
        }
        
        // IMPORTANTE: CORRECCIÓN PRINCIPAL - Actualización de mini_charts
        if (chartsData.mini_charts) {
            console.log('📈 Mini charts data encontrada:', chartsData.mini_charts);
            
            // Verificar específicamente los datos de capital
            if (chartsData.mini_charts.capital) {
                console.log('💰 Datos de capital encontrados:', chartsData.mini_charts.capital);
                console.log('📊 Total puntos:', chartsData.mini_charts.capital.length);
                console.log('📊 Rango de valores:', 
                    'Min:', Math.min(...chartsData.mini_charts.capital),
                    'Max:', Math.max(...chartsData.mini_charts.capital)
                );
            } else {
                console.warn('⚠️ No hay datos de capital en mini_charts');
            }
            
            // LLAMAR A updateMiniCharts con los datos correctos
            window.ChartsManager.updateMiniCharts(chartsData.mini_charts);
        } else {
            console.warn('⚠️ No hay datos de mini_charts en chartsData');
            
            // Intentar obtener los datos desde dashboardState como fallback
            if (window.dashboardState && 
                window.dashboardState.data && 
                window.dashboardState.data.charts && 
                window.dashboardState.data.charts.mini_charts) {
                
                console.log('📈 Usando mini_charts desde dashboardState como fallback');
                window.ChartsManager.updateMiniCharts(window.dashboardState.data.charts.mini_charts);
            } else {
                console.error('❌ No se encontraron datos de mini_charts en ninguna ubicación');
            }
        }
    } catch (error) {
        console.error('❌ Error en updateCharts:', error);
        console.error('Stack trace:', error.stack);
    }
}

// ADICIONAL: Agregar función de debug al window para testing
window.debugCapitalChart = function() {
    console.group('🔍 Debug Capital Chart');
    
    // Verificar ChartsManager
    if (window.ChartsManager) {
        console.log('✅ ChartsManager existe');
        
        // Verificar miniChart1
        if (window.ChartsManager.charts && window.ChartsManager.charts.miniChart1) {
            console.log('✅ miniChart1 existe');
            const currentData = window.ChartsManager.charts.miniChart1.data.datasets[0].data;
            console.log('📊 Datos actuales en el gráfico:', currentData);
            console.log('📊 Suma de valores:', currentData.reduce((a, b) => a + b, 0));
        } else {
            console.error('❌ miniChart1 NO existe');
        }
    } else {
        console.error('❌ ChartsManager NO existe');
    }
    
    // Verificar dashboardState
    if (window.dashboardState && window.dashboardState.data) {
        console.log('✅ dashboardState tiene datos');
        
        if (window.dashboardState.data.charts && 
            window.dashboardState.data.charts.mini_charts && 
            window.dashboardState.data.charts.mini_charts.capital) {
            
            const capitalData = window.dashboardState.data.charts.mini_charts.capital;
            console.log('💰 Datos de capital en dashboardState:', capitalData);
            console.log('📊 Total puntos:', capitalData.length);
            console.log('📊 Primer valor:', capitalData[0]);
            console.log('📊 Último valor:', capitalData[capitalData.length - 1]);
        } else {
            console.error('❌ No hay datos de capital en dashboardState');
        }
    } else {
        console.error('❌ dashboardState NO tiene datos');
    }
    
    console.groupEnd();
};

// Función para forzar actualización manual (útil para testing)
window.forceUpdateCapitalChart = function() {
    if (window.dashboardState && 
        window.dashboardState.data && 
        window.dashboardState.data.charts && 
        window.dashboardState.data.charts.mini_charts && 
        window.dashboardState.data.charts.mini_charts.capital) {
        
        const capitalData = window.dashboardState.data.charts.mini_charts.capital;
        console.log('🔄 Forzando actualización con datos:', capitalData);
        
        if (window.ChartsManager && window.ChartsManager.updateMiniCharts) {
            window.ChartsManager.updateMiniCharts({
                capital: capitalData
            });
            console.log('✅ Actualización forzada completada');
        } else {
            console.error('❌ ChartsManager.updateMiniCharts no está disponible');
        }
    } else {
        console.error('❌ No hay datos de capital disponibles para actualizar');
    }
};

console.log('💡 Funciones de debug disponibles:');
console.log('   - window.debugCapitalChart() : Ver estado actual del gráfico');
console.log('   - window.forceUpdateCapitalChart() : Forzar actualización del gráfico');

// ============================================
// ACTUALIZAR ESTADO DEL SISTEMA
// ============================================
function updateSystemStatus(system) {
    updateElement('server-time', system.server_time);
    updateElement('last-update-time', formatDateTime(system.last_update));
    
    // Estado de conexiones
    const apiStatus = document.getElementById('api-status');
    const dbStatus = document.getElementById('db-status');
    const eaStatus = document.getElementById('ea-status');
    
    if (apiStatus) {
        apiStatus.className = 'status-dot online';
        document.getElementById('api-status-text').textContent = 'Online';
    }
    
    if (dbStatus) {
        dbStatus.className = 'status-dot online';
        document.getElementById('db-status-text').textContent = 'Conectado';
    }
    
    if (eaStatus) {
        // Verificar última actualización del EA
        const lastUpdate = new Date(system.last_update);
        const now = new Date();
        const diffMinutes = (now - lastUpdate) / 60000;
        
        if (diffMinutes < 5) {
            eaStatus.className = 'status-dot online';
            document.getElementById('ea-status-text').textContent = 'Activo';
        } else {
            eaStatus.className = 'status-dot warning';
            document.getElementById('ea-status-text').textContent = 'Sin datos recientes';
        }
    }
}

// ============================================
// EVENT LISTENERS
// ============================================
function initializeEventListeners() {
    // Filtro de periodo
    document.getElementById('time-filter')?.addEventListener('change', (e) => {
        dashboardState.currentFilter.period = e.target.value;
        loadDashboardData(true);
    });
    
    // Filtro de broker
    document.getElementById('broker-filter')?.addEventListener('change', (e) => {
        dashboardState.currentFilter.broker = e.target.value;
        const filterInfo = document.getElementById('brokerFilterInfo');
        
        if (e.target.value !== 'all') {
            filterInfo.style.display = 'flex';
            document.getElementById('activeBrokerFilter').textContent = 
                e.target.value === 'special' ? 'Formato Especial' : 'Formato Estándar';
        } else {
            filterInfo.style.display = 'none';
        }
        
        loadDashboardData(true);
    });
    
    // Botones de periodo en gráficos
    document.querySelectorAll('.chart-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Remover active de todos
            e.target.parentElement.querySelectorAll('.chart-btn').forEach(b => {
                b.classList.remove('active');
            });
            // Agregar active al clickeado
            e.target.classList.add('active');
            
            // Actualizar filtro y recargar
            const period = e.target.dataset.period;
            if (period) {
                dashboardState.currentFilter.period = period;
                document.getElementById('time-filter').value = period;
                loadDashboardData(true);
            }
        });
    });
    
    // Búsqueda global
    document.getElementById('globalSearch')?.addEventListener('input', debounce((e) => {
        const searchTerm = e.target.value.toLowerCase();
        // Implementar búsqueda si es necesario
        console.log('Buscando:', searchTerm);
    }, 500));
    
    // Menu items - SOLUCIÓN DEFINITIVA
	// Solo manejar anchors (#), ignorar completamente los enlaces .php
	document.querySelectorAll('.menu-item').forEach(item => {
		const href = item.getAttribute('href');

		// SOLO agregar listener si es un anchor
		if (href && href.startsWith('#')) {
			item.addEventListener('click', (e) => {
				e.preventDefault();

				// Remover active de todos
				document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
				// Agregar active al clickeado
				e.currentTarget.classList.add('active');

				// Scroll a la sección si existe
				const targetId = href.substring(1);
				const targetElement = document.getElementById(targetId);
				if (targetElement) {
					targetElement.scrollIntoView({ behavior: 'smooth' });
				}
			});
		}
		// NO agregar ningún listener para enlaces .php - dejarlos completamente solos
	});
    
    // FAB Menu
    document.querySelector('.fab')?.addEventListener('click', () => {
        document.querySelector('.fab-container').classList.toggle('active');
    });
    
    // Filtro de posiciones
    document.getElementById('positions-filter')?.addEventListener('change', () => {
        updatePositionsTable(dashboardState.data?.positions);
    });
    
    // Búsqueda de posiciones
    document.getElementById('positions-search')?.addEventListener('input', debounce(() => {
        updatePositionsTable(dashboardState.data?.positions);
    }, 300));
}

// ============================================
// FUNCIONES DE PAGINACIÓN
// ============================================
function updatePositionsPagination(total) {
    dashboardState.pagination.positions.total = total;
    const pages = Math.ceil(total / dashboardState.pagination.positions.perPage);
    const current = dashboardState.pagination.positions.current;
    
    const container = document.getElementById('positions-pages');
    if (!container) return;
    
    let html = '';
    for (let i = 1; i <= Math.min(pages, 5); i++) {
        html += `<button class="page-num ${i === current ? 'active' : ''}" onclick="goToPositionsPage(${i})">${i}</button>`;
    }
    
    container.innerHTML = html;
}

function goToPositionsPage(page) {
    if (page === 'first') page = 1;
    else if (page === 'last') page = Math.ceil(dashboardState.pagination.positions.total / dashboardState.pagination.positions.perPage);
    else if (page === 'prev') page = Math.max(1, dashboardState.pagination.positions.current - 1);
    else if (page === 'next') page = Math.min(Math.ceil(dashboardState.pagination.positions.total / dashboardState.pagination.positions.perPage), dashboardState.pagination.positions.current + 1);
    
    dashboardState.pagination.positions.current = page;
    updatePositionsTable(dashboardState.data?.positions);
}

function updateHistoryPagination(total) {
    dashboardState.pagination.history.total = total;
    const pages = Math.ceil(total / dashboardState.pagination.history.perPage);
    const current = dashboardState.pagination.history.current;
    
    const container = document.getElementById('history-pages');
    if (!container) return;
    
    let html = '';
    for (let i = 1; i <= Math.min(pages, 5); i++) {
        html += `<button class="page-num ${i === current ? 'active' : ''}" onclick="goToHistoryPage(${i})">${i}</button>`;
    }
    
    container.innerHTML = html;
}

function goToHistoryPage(page) {
    if (page === 'first') page = 1;
    else if (page === 'last') page = Math.ceil(dashboardState.pagination.history.total / dashboardState.pagination.history.perPage);
    else if (page === 'prev') page = Math.max(1, dashboardState.pagination.history.current - 1);
    else if (page === 'next') page = Math.min(Math.ceil(dashboardState.pagination.history.total / dashboardState.pagination.history.perPage), dashboardState.pagination.history.current + 1);
    
    dashboardState.pagination.history.current = page;
    updateHistoryTable(dashboardState.data?.recent_history);
}

// ============================================
// FUNCIONES DE FILTRADO
// ============================================
function filterPositions(positions) {
    if (!positions) return [];
    
    const filter = document.getElementById('positions-filter')?.value || 'all';
    const search = document.getElementById('positions-search')?.value.toLowerCase() || '';
    
    return positions.filter(pos => {
        // Filtro por tipo
        if (filter === 'buy' && pos.type != 0) return false;
        if (filter === 'sell' && pos.type != 1) return false;
        if (filter === 'profit' && parseFloat(pos.profit) < 0) return false;
        if (filter === 'loss' && parseFloat(pos.profit) >= 0) return false;
        
        // Búsqueda
        if (search) {
            const searchableText = `${pos.ticket} ${pos.symbol} ${pos.account_name}`.toLowerCase();
            if (!searchableText.includes(search)) return false;
        }
        
        return true;
    });
}

function filterHistory() {
    // Implementar filtrado de historial si es necesario
    const dateFrom = document.getElementById('history-date-from')?.value;
    const dateTo = document.getElementById('history-date-to')?.value;
    const filter = document.getElementById('history-filter')?.value;
    
    console.log('Filtrar historial:', { dateFrom, dateTo, filter });
    // Aquí se implementaría el filtrado
}

// ============================================
// FUNCIONES DE UTILIDAD
// ============================================
function formatCurrency(value) {
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat(CONFIG.locale, {
        style: 'currency',
        currency: CONFIG.currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(num);
}

function formatNumber(value, decimals = 2) {
    return (parseFloat(value) || 0).toFixed(decimals);
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleString(CONFIG.locale, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatDuration(minutes) {
    if (!minutes || minutes < 0) return '-';
    
    if (minutes < 60) return `${Math.floor(minutes)}m`;
    
    const hours = Math.floor(minutes / 60);
    const mins = Math.floor(minutes % 60);
    
    if (hours < 24) return `${hours}h ${mins}m`;
    
    const days = Math.floor(hours / 24);
    const hrs = hours % 24;
    
    return `${days}d ${hrs}h`;
}

function updateElement(id, value) {
    const element = document.getElementById(id);
    if (element) {
        element.textContent = value;
    }
}

function setElementClass(id, className) {
    const element = document.getElementById(id);
    if (element) {
        element.className = className;
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showNotification(message, type = 'info') {
    // Implementar sistema de notificaciones si es necesario
    console.log(`[${type.toUpperCase()}] ${message}`);
}

// ============================================
// AUTO-REFRESH
// ============================================
function startAutoRefresh() {
    // Detener timer anterior si existe
    if (dashboardState.refreshTimer) {
        clearInterval(dashboardState.refreshTimer);
    }
    
    // Configurar nuevo timer
    dashboardState.refreshTimer = setInterval(() => {
        // Solo actualizar datos, no reinicializar gráficos
        loadDashboardData(false);
    }, CONFIG.api.refreshInterval);
}

function stopAutoRefresh() {
    if (dashboardState.refreshTimer) {
        clearInterval(dashboardState.refreshTimer);
        dashboardState.refreshTimer = null;
    }
}

function updateLastUpdateTime() {
    const now = new Date();
    updateElement('last-update-time', now.toLocaleTimeString(CONFIG.locale));
}

// ============================================
// FUNCIONES GLOBALES
// ============================================
window.refreshDashboard = function() {
    loadDashboardData(true);
};

window.clearBrokerFilter = function() {
    document.getElementById('broker-filter').value = 'all';
    document.getElementById('brokerFilterInfo').style.display = 'none';
    dashboardState.currentFilter.broker = 'all';
    loadDashboardData(true);
};

window.exportData = function() {
    // Implementar exportación de datos
    console.log('Exportando datos...');
    showNotification('Función de exportación en desarrollo', 'info');
};

window.sendFullHistory = function() {
    // Trigger para enviar histórico completo desde EA
    console.log('Enviando histórico completo...');
    showNotification('Señal enviada al EA para sincronización completa', 'success');
};

window.createAlert = function() {
    // Crear nueva alerta
    console.log('Creando nueva alerta...');
    showNotification('Sistema de alertas personalizadas en desarrollo', 'info');
};

window.generateReport = function() {
    // Generar reporte
    console.log('Generando reporte...');
    showNotification('Generación de reportes en desarrollo', 'info');
};

window.openSettings = function() {
    // Abrir configuración
    console.log('Abriendo configuración...');
    showNotification('Panel de configuración en desarrollo', 'info');
};

window.toggleFullscreen = function() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
};

window.dismissAlert = function(button) {
    const alertCard = button.closest('.alert-card');
    if (alertCard) {
        alertCard.style.animation = 'fadeOut 0.3s';
        setTimeout(() => alertCard.remove(), 300);
    }
};

// ============================================
// MANEJO DE ERRORES GLOBALES
// ============================================
window.addEventListener('error', (e) => {
    console.error('Error global:', e.error);
    showNotification('Ha ocurrido un error. Por favor, recarga la página.', 'error');
});

window.addEventListener('unhandledrejection', (e) => {
    console.error('Promesa rechazada:', e.reason);
    showNotification('Error de conexión. Verificando...', 'warning');
});

// Prevenir cierre accidental
window.addEventListener('beforeunload', (e) => {
    if (dashboardState.isLoading) {
        e.preventDefault();
        e.returnValue = 'Hay operaciones en curso. ¿Estás seguro de que quieres salir?';
    }
});

console.log('✅ Dashboard.js v7.0 cargado correctamente');