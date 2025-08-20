//+------------------------------------------------------------------+
//| analysis.page.js                                                |
//| JavaScript específico para la página de Análisis y Rendimiento  |
//| Copyright 2025, Elysium Media FZCO                              |
//| Sistema de Reportes de Trading v7.0                             |
//+------------------------------------------------------------------+

// IIFE para encapsular y evitar contaminación del scope global
(function() {
    'use strict';

    // ============================================
    // CONFIGURACIÓN Y ESTADO GLOBAL DE LA PÁGINA
    // ============================================
    const AnalysisState = {
        // Periodo actual seleccionado
        currentPeriod: 'today',
        customDateRange: null,
        
        // Filtros activos
        filters: {
            bot: null,
            account: null,
            symbol: null
        },
        
        // Cache de datos
        cache: {
            kpis: null,
            botsComparison: null,
            equityData: null,
            correlationMatrix: null,
            operations: null
        },
        
        // Estado de paginación
        pagination: {
            currentPage: 1,
            totalPages: 1,
            rowsPerPage: 50
        },
        
        // Charts instances
        charts: {
            equity: null,
            returnsDistribution: null,
            rollingSharpe: null,
            rollingVolatility: null,
            rollingMaxDD: null,
            hourlyPerformance: null,
            botEquity: null
        },
        
        // ECharts instances
        echartsInstances: {
            treemap: null,
            sankey: null,
            correlationMatrix: null,
            calendarHeatmap: null
        },
        
        // Estado de carga
        isLoading: false,
        loadingTasks: new Set()
    };

    // ============================================
    // UTILIDADES Y HELPERS
    // ============================================
    
    /**
     * Formatear números con separadores de miles
     */
    function formatNumber(num, decimals = 2) {
        if (num === null || num === undefined) return '--';
        return new Intl.NumberFormat('es-ES', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(num);
    }

    /**
     * Formatear porcentaje
     */
    function formatPercent(num, decimals = 2) {
        if (num === null || num === undefined) return '--';
        return formatNumber(num, decimals) + '%';
    }

    /**
     * Formatear moneda
     */
    function formatCurrency(num, currency = 'USD') {
        if (num === null || num === undefined) return '--';
        return new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2
        }).format(num);
    }

    /**
     * Formatear fecha
     */
    function formatDate(dateStr) {
        if (!dateStr) return '--';
        const date = new Date(dateStr);
        return new Intl.DateTimeFormat('es-ES', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    }

    /**
     * Calcular diferencia de tiempo
     */
    function formatDuration(start, end) {
        const diff = new Date(end) - new Date(start);
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        
        if (hours > 24) {
            const days = Math.floor(hours / 24);
            return `${days}d ${hours % 24}h`;
        }
        return `${hours}h ${minutes}m`;
    }

    /**
     * Debounce para optimizar llamadas
     */
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

    /**
     * Mostrar/ocultar loading
     */
    function setLoading(taskName, isLoading) {
        if (isLoading) {
            AnalysisState.loadingTasks.add(taskName);
        } else {
            AnalysisState.loadingTasks.delete(taskName);
        }
        
        const shouldShowLoading = AnalysisState.loadingTasks.size > 0;
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.style.display = shouldShowLoading ? 'flex' : 'none';
        }
    }

    /**
     * Obtener parámetros de filtros para API
     */
    function getFilterParams() {
        const params = new URLSearchParams();
        
        // Periodo
        if (AnalysisState.currentPeriod === 'custom' && AnalysisState.customDateRange) {
            params.append('start_date', AnalysisState.customDateRange.start);
            params.append('end_date', AnalysisState.customDateRange.end);
        } else {
            params.append('period', AnalysisState.currentPeriod);
        }
        
        // Filtros
        if (AnalysisState.filters.bot) {
            params.append('magic_number', AnalysisState.filters.bot);
        }
        if (AnalysisState.filters.account) {
            params.append('account_login', AnalysisState.filters.account);
        }
        if (AnalysisState.filters.symbol) {
            params.append('symbol', AnalysisState.filters.symbol);
        }
        
        return params;
    }

    // ============================================
    // FUNCIONES DE CARGA DE DATOS
    // ============================================

    /**
     * Cargar KPIs principales
     */
    async function loadKPIs() {
        setLoading('kpis', true);
        
        try {
            const response = await fetch('api/get_analysis_kpis.php?' + getFilterParams());
            const data = await response.json();
            
            if (data.success) {
                AnalysisState.cache.kpis = data.kpis;
                updateKPIsDisplay(data.kpis);
            } else {
                console.error('Error loading KPIs:', data.error);
            }
        } catch (error) {
            console.error('Error fetching KPIs:', error);
        } finally {
            setLoading('kpis', false);
        }
    }

    /**
     * Actualizar display de KPIs
     */
    function updateKPIsDisplay(kpis) {
        // Rentabilidad
        const returnEl = document.getElementById('kpiReturn');
        if (returnEl) {
            returnEl.textContent = formatCurrency(kpis.total_return);
            const changeEl = returnEl.parentElement.querySelector('.kpi-change');
            if (changeEl) {
                const change = kpis.return_change;
                changeEl.textContent = (change >= 0 ? '↑ +' : '↓ ') + formatPercent(Math.abs(change));
                changeEl.className = 'kpi-change ' + (change >= 0 ? 'positive' : 'negative');
            }
        }
        
        // APR
        const aprEl = document.getElementById('kpiAPR');
        if (aprEl) {
            aprEl.textContent = formatPercent(kpis.apr);
        }
        
        // Sharpe Ratio
        const sharpeEl = document.getElementById('kpiSharpe');
        if (sharpeEl) {
            sharpeEl.textContent = formatNumber(kpis.sharpe_ratio);
        }
        
        // Sortino Ratio
        const sortinoEl = document.getElementById('kpiSortino');
        if (sortinoEl) {
            sortinoEl.textContent = formatNumber(kpis.sortino_ratio);
        }
        
        // Max Drawdown
        const maxDDEl = document.getElementById('kpiMaxDD');
        if (maxDDEl) {
            maxDDEl.textContent = formatPercent(kpis.max_drawdown);
        }
        
        // Profit Factor
        const pfEl = document.getElementById('kpiPF');
        if (pfEl) {
            pfEl.textContent = formatNumber(kpis.profit_factor);
        }
        
        // Win Rate
        const winRateEl = document.getElementById('kpiWinRate');
        if (winRateEl) {
            winRateEl.textContent = formatPercent(kpis.win_rate);
        }
        
        // Volatilidad
        const volEl = document.getElementById('kpiVolatility');
        if (volEl) {
            volEl.textContent = formatPercent(kpis.volatility_annual);
        }
    }

    /**
     * Cargar comparación de bots
     */
    async function loadBotsComparison() {
        setLoading('bots', true);
        
        try {
            const sortBy = document.getElementById('botRankingSort')?.value || 'pnl';
            const params = getFilterParams();
            params.append('sort_by', sortBy);
            
            const response = await fetch('api/get_bot_comparison.php?' + params);
            const data = await response.json();
            
            if (data.success) {
                AnalysisState.cache.botsComparison = data.bots;
                renderBotsTable(data.bots);
            }
        } catch (error) {
            console.error('Error loading bots comparison:', error);
        } finally {
            setLoading('bots', false);
        }
    }

    /**
     * Renderizar tabla de bots
     */
    function renderBotsTable(bots) {
        const tbody = document.getElementById('botsTableBody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        bots.forEach(bot => {
            const botInfo = getBotInfo(bot.magic_number);
            const row = document.createElement('tr');
            
            row.innerHTML = `
                <td>
                    <div class="analysis-bot-name">
                        <span class="analysis-bot-icon" style="color: ${botInfo.color}">🤖</span>
                        <div>
                            <div class="analysis-bot-title">${botInfo.name}</div>
                            <div class="analysis-bot-version">v${botInfo.version}</div>
                        </div>
                    </div>
                </td>
                <td><span class="analysis-category-badge">${botInfo.category}</span></td>
                <td class="${bot.pnl >= 0 ? 'positive' : 'negative'}">${formatCurrency(bot.pnl)}</td>
                <td class="${bot.return_pct >= 0 ? 'positive' : 'negative'}">${formatPercent(bot.return_pct)}</td>
                <td>${formatNumber(bot.sharpe_ratio)}</td>
                <td class="negative">${formatPercent(bot.max_drawdown)}</td>
                <td>${formatNumber(bot.profit_factor)}</td>
                <td>${formatPercent(bot.win_rate)}</td>
                <td>${bot.total_trades}</td>
                <td>
                    <span class="analysis-status-badge ${botInfo.active ? 'active' : 'inactive'}">
                        ${botInfo.active ? 'Activo' : 'Inactivo'}
                    </span>
                </td>
                <td>
                    <button class="analysis-btn-action" onclick="showBotDetail(${bot.magic_number})">
                        Ver Detalle
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }

    /**
     * Cargar equity curve
     */
    async function loadEquityCurve() {
        setLoading('equity', true);
        
        try {
            const response = await fetch('api/get_equity_curve.php?' + getFilterParams());
            const data = await response.json();
            
            if (data.success) {
                AnalysisState.cache.equityData = data.equity_data;
                renderEquityChart(data.equity_data);
                renderReturnsDistribution(data.returns_data);
            }
        } catch (error) {
            console.error('Error loading equity curve:', error);
        } finally {
            setLoading('equity', false);
        }
    }

    /**
     * Renderizar gráfico de equity
     */
    function renderEquityChart(data) {
        const ctx = document.getElementById('equityChart');
        if (!ctx) return;
        
        // Destruir chart anterior si existe
        if (AnalysisState.charts.equity) {
            AnalysisState.charts.equity.destroy();
        }
        
        AnalysisState.charts.equity = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.dates,
                datasets: [{
                    label: 'Equity',
                    data: data.equity,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }, {
                    label: 'Drawdown',
                    data: data.drawdown,
                    borderColor: '#EF4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 1,
                    fill: true,
                    yAxisID: 'y1',
                    tension: 0.1,
                    pointRadius: 0,
                    pointHoverRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            color: '#9CA3AF',
                            usePointStyle: true,
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                        titleColor: '#F3F4F6',
                        bodyColor: '#D1D5DB',
                        borderColor: 'rgba(99, 102, 241, 0.3)',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Equity: ' + formatCurrency(context.parsed.y);
                                } else {
                                    return 'Drawdown: ' + formatPercent(context.parsed.y);
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#9CA3AF',
                            maxRotation: 0
                        }
                    },
                    y: {
                        position: 'left',
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#9CA3AF',
                            callback: function(value) {
                                return formatCurrency(value);
                            }
                        }
                    },
                    y1: {
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                            drawBorder: false
                        },
                        ticks: {
                            color: '#9CA3AF',
                            callback: function(value) {
                                return formatPercent(value);
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Renderizar distribución de retornos
     */
    function renderReturnsDistribution(data) {
        const ctx = document.getElementById('returnsDistribution');
        if (!ctx) return;
        
        if (AnalysisState.charts.returnsDistribution) {
            AnalysisState.charts.returnsDistribution.destroy();
        }
        
        AnalysisState.charts.returnsDistribution = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.bins,
                datasets: [{
                    label: 'Frecuencia',
                    data: data.frequencies,
                    backgroundColor: data.frequencies.map(v => 
                        v >= 0 ? 'rgba(16, 185, 129, 0.6)' : 'rgba(239, 68, 68, 0.6)'
                    ),
                    borderColor: data.frequencies.map(v => 
                        v >= 0 ? '#10B981' : '#EF4444'
                    ),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                        callbacks: {
                            title: function(tooltipItems) {
                                return 'Retorno: ' + tooltipItems[0].label + '%';
                            },
                            label: function(context) {
                                return 'Frecuencia: ' + context.parsed.y + ' días';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#9CA3AF',
                            callback: function(value, index) {
                                return data.bins[index] + '%';
                            }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    }
                }
            }
        });
    }

    /**
     * Cargar métricas rolling
     */
    async function loadRollingMetrics() {
        setLoading('rolling', true);
        
        try {
            const window = document.querySelector('.rolling-window')?.value || '30';
            const params = getFilterParams();
            params.append('window', window);
            
            const response = await fetch('api/get_rolling_metrics.php?' + params);
            const data = await response.json();
            
            if (data.success) {
                renderRollingSharpe(data.rolling_sharpe);
                renderRollingVolatility(data.rolling_volatility);
                renderRollingMaxDD(data.rolling_maxdd);
            }
        } catch (error) {
            console.error('Error loading rolling metrics:', error);
        } finally {
            setLoading('rolling', false);
        }
    }

    /**
     * Renderizar Rolling Sharpe
     */
    function renderRollingSharpe(data) {
        const ctx = document.getElementById('rollingSharpe');
        if (!ctx) return;
        
        if (AnalysisState.charts.rollingSharpe) {
            AnalysisState.charts.rollingSharpe.destroy();
        }
        
        AnalysisState.charts.rollingSharpe = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.dates,
                datasets: [{
                    label: 'Rolling Sharpe',
                    data: data.values,
                    borderColor: '#6366F1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.2,
                    pointRadius: 0
                }]
            },
            options: getChartOptions('Sharpe Ratio')
        });
    }

    /**
     * Renderizar Rolling Volatility
     */
    function renderRollingVolatility(data) {
        const ctx = document.getElementById('rollingVolatility');
        if (!ctx) return;
        
        if (AnalysisState.charts.rollingVolatility) {
            AnalysisState.charts.rollingVolatility.destroy();
        }
        
        AnalysisState.charts.rollingVolatility = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.dates,
                datasets: [{
                    label: 'Rolling Volatilidad',
                    data: data.values,
                    borderColor: '#F59E0B',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.2,
                    pointRadius: 0
                }]
            },
            options: getChartOptions('Volatilidad %')
        });
    }

    /**
     * Renderizar Rolling Max DD
     */
    function renderRollingMaxDD(data) {
        const ctx = document.getElementById('rollingMaxDD');
        if (!ctx) return;
        
        if (AnalysisState.charts.rollingMaxDD) {
            AnalysisState.charts.rollingMaxDD.destroy();
        }
        
        AnalysisState.charts.rollingMaxDD = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.dates,
                datasets: [{
                    label: 'Rolling Max Drawdown',
                    data: data.values,
                    borderColor: '#EF4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.2,
                    pointRadius: 0
                }]
            },
            options: getChartOptions('Max DD %')
        });
    }

    /**
     * Opciones comunes para gráficos
     */
    function getChartOptions(yLabel) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(17, 24, 39, 0.95)',
                    titleColor: '#F3F4F6',
                    bodyColor: '#D1D5DB',
                    borderColor: 'rgba(99, 102, 241, 0.3)',
                    borderWidth: 1
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#9CA3AF',
                        maxRotation: 0
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#9CA3AF'
                    },
                    title: {
                        display: true,
                        text: yLabel,
                        color: '#9CA3AF'
                    }
                }
            }
        };
    }

    /**
     * Cargar diversificación
     */
    async function loadDiversification() {
        setLoading('diversification', true);
        
        try {
            const response = await fetch('api/get_portfolio_diversification.php?' + getFilterParams());
            const data = await response.json();
            
            if (data.success) {
                renderTreemap(data.treemap_data);
                renderSankey(data.sankey_data);
            }
        } catch (error) {
            console.error('Error loading diversification:', error);
        } finally {
            setLoading('diversification', false);
        }
    }

    /**
     * Renderizar Treemap
     */
    function renderTreemap(data) {
        const container = document.getElementById('treemapChart');
        if (!container) return;
        
        if (AnalysisState.echartsInstances.treemap) {
            AnalysisState.echartsInstances.treemap.dispose();
        }
        
        AnalysisState.echartsInstances.treemap = echarts.init(container);
        
        const option = {
            title: {
                text: '',
                left: 'center',
                textStyle: {
                    color: '#F3F4F6'
                }
            },
            tooltip: {
                formatter: function(info) {
                    const value = info.value;
                    const treePathInfo = info.treePathInfo;
                    const treePath = [];
                    
                    for (let i = 1; i < treePathInfo.length; i++) {
                        treePath.push(treePathInfo[i].name);
                    }
                    
                    return [
                        '<div class="analysis-tooltip">',
                        'Path: ' + treePath.join(' → '),
                        'Capital: ' + formatCurrency(value[0]),
                        'Rentabilidad: ' + formatPercent(value[1]),
                        '</div>'
                    ].join('<br>');
                }
            },
            series: [{
                type: 'treemap',
                data: data,
                leafDepth: 2,
                levels: [{
                    itemStyle: {
                        borderColor: '#1a1a2e',
                        borderWidth: 2,
                        gapWidth: 2
                    }
                }, {
                    itemStyle: {
                        borderColor: '#2d2d42',
                        borderWidth: 1,
                        gapWidth: 1
                    }
                }],
                label: {
                    show: true,
                    formatter: '{b}',
                    color: '#F3F4F6'
                },
                itemStyle: {
                    borderColor: '#1a1a2e'
                }
            }]
        };
        
        AnalysisState.echartsInstances.treemap.setOption(option);
    }

    /**
     * Renderizar Sankey
     */
    function renderSankey(data) {
        const container = document.getElementById('sankeyChart');
        if (!container) return;
        
        if (AnalysisState.echartsInstances.sankey) {
            AnalysisState.echartsInstances.sankey.dispose();
        }
        
        AnalysisState.echartsInstances.sankey = echarts.init(container);
        
        const option = {
            tooltip: {
                trigger: 'item',
                triggerOn: 'mousemove',
                formatter: function(params) {
                    if (params.dataType === 'edge') {
                        return params.data.source + ' → ' + params.data.target + ': ' + formatCurrency(params.value);
                    }
                    return params.name + ': ' + formatCurrency(params.value);
                }
            },
            series: [{
                type: 'sankey',
                data: data.nodes,
                links: data.links,
                emphasis: {
                    focus: 'adjacency'
                },
                lineStyle: {
                    color: 'gradient',
                    curveness: 0.5
                },
                itemStyle: {
                    color: '#6366F1',
                    borderColor: '#1a1a2e'
                },
                label: {
                    color: '#F3F4F6',
                    fontSize: 11
                }
            }]
        };
        
        AnalysisState.echartsInstances.sankey.setOption(option);
    }

    /**
     * Cargar matriz de correlación
     */
    async function loadCorrelationMatrix() {
        setLoading('correlation', true);
        
        try {
            const type = document.getElementById('correlationType')?.value || 'pearson';
            const params = getFilterParams();
            params.append('correlation_type', type);
            
            const response = await fetch('api/get_correlation_matrix.php?' + params);
            const data = await response.json();
            
            if (data.success) {
                renderCorrelationMatrix(data.matrix_data);
            }
        } catch (error) {
            console.error('Error loading correlation matrix:', error);
        } finally {
            setLoading('correlation', false);
        }
    }

    /**
     * Renderizar matriz de correlación
     */
    function renderCorrelationMatrix(data) {
        const container = document.getElementById('correlationMatrix');
        if (!container) return;
        
        if (AnalysisState.echartsInstances.correlationMatrix) {
            AnalysisState.echartsInstances.correlationMatrix.dispose();
        }
        
        AnalysisState.echartsInstances.correlationMatrix = echarts.init(container);
        
        const option = {
            tooltip: {
                position: 'top',
                formatter: function(params) {
                    return data.labels[params.data[0]] + ' vs ' + 
                           data.labels[params.data[1]] + ': ' + 
                           formatNumber(params.data[2], 3);
                }
            },
            grid: {
                left: '15%',
                right: '5%',
                bottom: '15%',
                top: '5%',
                containLabel: true
            },
            xAxis: {
                type: 'category',
                data: data.labels,
                splitArea: {
                    show: true
                },
                axisLabel: {
                    rotate: 45,
                    color: '#9CA3AF'
                }
            },
            yAxis: {
                type: 'category',
                data: data.labels,
                splitArea: {
                    show: true
                },
                axisLabel: {
                    color: '#9CA3AF'
                }
            },
            visualMap: {
                min: -1,
                max: 1,
                calculable: true,
                orient: 'horizontal',
                left: 'center',
                bottom: '0%',
                inRange: {
                    color: ['#EF4444', '#FFFFFF', '#10B981']
                },
                textStyle: {
                    color: '#9CA3AF'
                }
            },
            series: [{
                name: 'Correlación',
                type: 'heatmap',
                data: data.values,
                label: {
                    show: true,
                    formatter: function(params) {
                        return formatNumber(params.value[2], 2);
                    },
                    color: '#1a1a2e'
                },
                emphasis: {
                    itemStyle: {
                        shadowBlur: 10,
                        shadowColor: 'rgba(99, 102, 241, 0.5)'
                    }
                }
            }]
        };
        
        AnalysisState.echartsInstances.correlationMatrix.setOption(option);
    }

    /**
     * Cargar calendario de retornos
     */
    async function loadCalendarHeatmap() {
        setLoading('calendar', true);
        
        try {
            const response = await fetch('api/get_risk_metrics.php?' + getFilterParams());
            const data = await response.json();
            
            if (data.success) {
                renderCalendarHeatmap(data.calendar_data);
                renderHourlyPerformance(data.hourly_data);
            }
        } catch (error) {
            console.error('Error loading calendar heatmap:', error);
        } finally {
            setLoading('calendar', false);
        }
    }

    /**
     * Renderizar calendario heatmap
     */
    function renderCalendarHeatmap(data) {
        const container = document.getElementById('calendarHeatmap');
        if (!container) return;
        
        if (AnalysisState.echartsInstances.calendarHeatmap) {
            AnalysisState.echartsInstances.calendarHeatmap.dispose();
        }
        
        AnalysisState.echartsInstances.calendarHeatmap = echarts.init(container);
        
        const option = {
            tooltip: {
                formatter: function(params) {
                    return params.value[0] + ': ' + formatPercent(params.value[1]);
                }
            },
            visualMap: {
                min: data.min,
                max: data.max,
                calculable: true,
                orient: 'horizontal',
                left: 'center',
                top: '0',
                inRange: {
                    color: ['#EF4444', '#F59E0B', '#FFFFFF', '#10B981', '#059669']
                },
                textStyle: {
                    color: '#9CA3AF'
                }
            },
            calendar: {
                top: 60,
                left: 30,
                right: 30,
                cellSize: ['auto', 20],
                range: data.range,
                itemStyle: {
                    borderWidth: 0.5,
                    borderColor: '#1a1a2e'
                },
                yearLabel: { show: false },
                dayLabel: {
                    nameMap: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
                    color: '#9CA3AF'
                },
                monthLabel: {
                    color: '#9CA3AF'
                }
            },
            series: [{
                type: 'heatmap',
                coordinateSystem: 'calendar',
                data: data.values
            }]
        };
        
        AnalysisState.echartsInstances.calendarHeatmap.setOption(option);
    }

    /**
     * Renderizar rendimiento por hora
     */
    function renderHourlyPerformance(data) {
        const ctx = document.getElementById('hourlyPerformance');
        if (!ctx) return;
        
        if (AnalysisState.charts.hourlyPerformance) {
            AnalysisState.charts.hourlyPerformance.destroy();
        }
        
        AnalysisState.charts.hourlyPerformance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.hours,
                datasets: [{
                    label: 'Rentabilidad promedio',
                    data: data.returns,
                    backgroundColor: data.returns.map(v => 
                        v >= 0 ? 'rgba(16, 185, 129, 0.6)' : 'rgba(239, 68, 68, 0.6)'
                    )
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#9CA3AF'
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#9CA3AF',
                            callback: function(value) {
                                return formatPercent(value);
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Cargar tabla de operaciones
     */
    async function loadOperationsTable(page = 1) {
        setLoading('operations', true);
        
        try {
            const params = getFilterParams();
            params.append('page', page);
            params.append('per_page', AnalysisState.pagination.rowsPerPage);
            
            const searchTerm = document.querySelector('.search-input')?.value;
            if (searchTerm) {
                params.append('search', searchTerm);
            }
            
            const filter = document.getElementById('operationsFilter')?.value;
            if (filter && filter !== 'all') {
                params.append('status', filter);
            }
            
            const response = await fetch('api/get_operations_table.php?' + params);
            const data = await response.json();
            
            if (data.success) {
                AnalysisState.cache.operations = data.operations;
                AnalysisState.pagination.currentPage = data.current_page;
                AnalysisState.pagination.totalPages = data.total_pages;
                renderOperationsTable(data.operations);
                updatePagination();
            }
        } catch (error) {
            console.error('Error loading operations:', error);
        } finally {
            setLoading('operations', false);
        }
    }

    /**
     * Renderizar tabla de operaciones
     */
    function renderOperationsTable(operations) {
        const tbody = document.getElementById('operationsTableBody');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        operations.forEach(op => {
            const botInfo = getBotInfo(op.magic);
            const row = document.createElement('tr');
            
            row.innerHTML = `
                <td>${op.ticket}</td>
                <td>${formatDate(op.time_open)}</td>
                <td>
                    <span class="analysis-bot-badge" style="background: ${botInfo.color}20; color: ${botInfo.color}">
                        ${botInfo.name}
                    </span>
                </td>
                <td>${op.symbol}</td>
                <td>${op.type === 0 ? 'BUY' : 'SELL'}</td>
                <td>${formatNumber(op.volume)}</td>
                <td>${formatNumber(op.price_open, 5)}</td>
                <td>${op.price_close ? formatNumber(op.price_close, 5) : '--'}</td>
                <td class="${op.profit >= 0 ? 'positive' : 'negative'}">
                    ${formatCurrency(op.profit)}
                </td>
                <td>${op.time_close ? formatDuration(op.time_open, op.time_close) : '--'}</td>
                <td>
                    <span class="analysis-status-badge ${op.time_close ? 'closed' : 'open'}">
                        ${op.time_close ? 'Cerrada' : 'Abierta'}
                    </span>
                </td>
            `;
            
            tbody.appendChild(row);
        });
    }

    /**
     * Actualizar paginación
     */
    function updatePagination() {
        const pageInfo = document.getElementById('pageInfo');
        if (pageInfo) {
            pageInfo.textContent = `Página ${AnalysisState.pagination.currentPage} de ${AnalysisState.pagination.totalPages}`;
        }
        
        const prevBtn = document.getElementById('prevPage');
        const nextBtn = document.getElementById('nextPage');
        
        if (prevBtn) {
            prevBtn.disabled = AnalysisState.pagination.currentPage <= 1;
        }
        
        if (nextBtn) {
            nextBtn.disabled = AnalysisState.pagination.currentPage >= AnalysisState.pagination.totalPages;
        }
    }

    /**
     * Mostrar detalle de bot
     */
    window.showBotDetail = async function(magicNumber) {
        const modal = document.getElementById('botDetailModal');
        if (!modal) return;
        
        modal.style.display = 'block';
        
        const botInfo = getBotInfo(magicNumber);
        document.getElementById('botDetailTitle').textContent = `Detalle de ${botInfo.name}`;
        
        try {
            const params = new URLSearchParams();
            params.append('magic_number', magicNumber);
            
            const response = await fetch('api/get_bot_detail.php?' + params);
            const data = await response.json();
            
            if (data.success) {
                renderBotDetail(data.bot_data);
            }
        } catch (error) {
            console.error('Error loading bot detail:', error);
        }
    };

    /**
     * Renderizar detalle de bot
     */
    function renderBotDetail(data) {
        const content = document.querySelector('.bot-detail-stats');
        if (!content) return;
        
        content.innerHTML = `
            <div class="analysis-bot-kpis">
                <div class="analysis-kpi-mini">
                    <label>P&L Total</label>
                    <value class="${data.total_pnl >= 0 ? 'positive' : 'negative'}">
                        ${formatCurrency(data.total_pnl)}
                    </value>
                </div>
                <div class="analysis-kpi-mini">
                    <label>Rentabilidad</label>
                    <value class="${data.return_pct >= 0 ? 'positive' : 'negative'}">
                        ${formatPercent(data.return_pct)}
                    </value>
                </div>
                <div class="analysis-kpi-mini">
                    <label>Sharpe Ratio</label>
                    <value>${formatNumber(data.sharpe_ratio)}</value>
                </div>
                <div class="analysis-kpi-mini">
                    <label>Win Rate</label>
                    <value>${formatPercent(data.win_rate)}</value>
                </div>
                <div class="analysis-kpi-mini">
                    <label>Profit Factor</label>
                    <value>${formatNumber(data.profit_factor)}</value>
                </div>
                <div class="analysis-kpi-mini">
                    <label>Total Trades</label>
                    <value>${data.total_trades}</value>
                </div>
            </div>
        `;
        
        // Renderizar equity del bot
        const ctx = document.getElementById('botEquityChart');
        if (ctx && data.equity_data) {
            if (AnalysisState.charts.botEquity) {
                AnalysisState.charts.botEquity.destroy();
            }
            
            AnalysisState.charts.botEquity = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.equity_data.dates,
                    datasets: [{
                        label: 'Equity',
                        data: data.equity_data.values,
                        borderColor: '#6366F1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.2
                    }]
                },
                options: getChartOptions('Equity')
            });
        }
    }

    /**
     * Exportar datos
     */
    async function exportData(type) {
        setLoading('export', true);
        
        try {
            const params = getFilterParams();
            params.append('export_type', type);
            
            const response = await fetch('api/export_analytics.php?' + params);
            const blob = await response.blob();
            
            // Crear link de descarga
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `analysis_${type}_${new Date().toISOString().split('T')[0]}.${type === 'csv' ? 'csv' : 'xlsx'}`;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        } catch (error) {
            console.error('Error exporting data:', error);
        } finally {
            setLoading('export', false);
        }
    }

    // ============================================
    // EVENT LISTENERS
    // ============================================

    /**
     * Inicializar event listeners
     */
    function initEventListeners() {
        // Selector de periodo
        document.querySelectorAll('.period-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                AnalysisState.currentPeriod = this.dataset.period;
                
                if (AnalysisState.currentPeriod === 'custom') {
                    // Mostrar selector de fechas personalizado
                    showCustomDatePicker();
                } else {
                    loadAllData();
                }
            });
        });
        
        // Filtros
        document.getElementById('filterBot')?.addEventListener('change', function() {
            AnalysisState.filters.bot = this.value || null;
            loadAllData();
        });
        
        document.getElementById('filterAccount')?.addEventListener('change', function() {
            AnalysisState.filters.account = this.value || null;
            loadAllData();
        });
        
        document.getElementById('filterSymbol')?.addEventListener('change', function() {
            AnalysisState.filters.symbol = this.value || null;
            loadAllData();
        });
        
        // Botón reiniciar filtros
        document.querySelector('.btn-reset-filters')?.addEventListener('click', function() {
            AnalysisState.filters = { bot: null, account: null, symbol: null };
            document.getElementById('filterBot').value = '';
            document.getElementById('filterAccount').value = '';
            document.getElementById('filterSymbol').value = '';
            loadAllData();
        });
        
        // Ordenamiento de bots
        document.getElementById('botRankingSort')?.addEventListener('change', function() {
            loadBotsComparison();
        });
        
        // Tipo de treemap
        document.getElementById('treemapType')?.addEventListener('change', function() {
            loadDiversification();
        });
        
        // Tipo de correlación
        document.getElementById('correlationType')?.addEventListener('change', function() {
            loadCorrelationMatrix();
        });
        
        // Ventana de rolling metrics
        document.querySelector('.rolling-window')?.addEventListener('change', function() {
            loadRollingMetrics();
        });
        
        // Búsqueda en operaciones
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(function() {
                loadOperationsTable(1);
            }, 500));
        }
        
        // Filtro de operaciones
        document.getElementById('operationsFilter')?.addEventListener('change', function() {
            loadOperationsTable(1);
        });
        
        // Paginación
        document.getElementById('prevPage')?.addEventListener('click', function() {
            if (AnalysisState.pagination.currentPage > 1) {
                loadOperationsTable(AnalysisState.pagination.currentPage - 1);
            }
        });
        
        document.getElementById('nextPage')?.addEventListener('click', function() {
            if (AnalysisState.pagination.currentPage < AnalysisState.pagination.totalPages) {
                loadOperationsTable(AnalysisState.pagination.currentPage + 1);
            }
        });
        
        // Botones de exportación
        document.querySelectorAll('.btn-export').forEach(btn => {
            btn.addEventListener('click', function() {
                const table = this.dataset.table;
                exportData(table === 'bots' ? 'bots_csv' : 'operations_csv');
            });
        });
        
        // Modal close
        document.querySelector('.modal-close')?.addEventListener('click', function() {
            document.getElementById('botDetailModal').style.display = 'none';
        });
        
        // Glosario toggle
        document.querySelector('.glossary-toggle')?.addEventListener('click', function() {
            const panel = document.getElementById('glossaryPanel');
            panel.classList.toggle('open');
        });
        
        // Chart type toggles
        document.querySelectorAll('.chart-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const chart = this.dataset.chart;
                const type = this.dataset.type;
                
                this.parentElement.querySelectorAll('.chart-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                if (chart === 'equity' && type) {
                    // Cambiar escala del gráfico
                    if (AnalysisState.charts.equity) {
                        AnalysisState.charts.equity.options.scales.y.type = type === 'log' ? 'logarithmic' : 'linear';
                        AnalysisState.charts.equity.update();
                    }
                }
            });
        });
        
        // Resize handlers para charts
        window.addEventListener('resize', debounce(function() {
            // Resize Chart.js
            Object.values(AnalysisState.charts).forEach(chart => {
                if (chart) chart.resize();
            });
            
            // Resize ECharts
            Object.values(AnalysisState.echartsInstances).forEach(chart => {
                if (chart) chart.resize();
            });
        }, 250));
    }

    /**
     * Cargar filtros disponibles
     */
    async function loadFilters() {
        try {
            // Cargar bots
            const botsSelect = document.getElementById('filterBot');
            if (botsSelect) {
                const activeBots = getActiveBots();
                activeBots.forEach(bot => {
                    const option = document.createElement('option');
                    option.value = bot.magicNumber;
                    option.textContent = bot.name;
                    botsSelect.appendChild(option);
                });
            }
            
            // Cargar cuentas desde API
            const accountsResponse = await fetch('api/get_accounts_data.php');
            const accountsData = await accountsResponse.json();
            
            if (accountsData.success) {
                const accountsSelect = document.getElementById('filterAccount');
                if (accountsSelect) {
                    accountsData.accounts.forEach(account => {
                        const option = document.createElement('option');
                        option.value = account.login;
                        option.textContent = `${account.login} - ${account.name}`;
                        accountsSelect.appendChild(option);
                    });
                }
            }
            
            // Cargar símbolos desde API
            const symbolsResponse = await fetch('api/get_account_statistics.php');
            const symbolsData = await symbolsResponse.json();
            
            if (symbolsData.success && symbolsData.symbols) {
                const symbolsSelect = document.getElementById('filterSymbol');
                if (symbolsSelect) {
                    symbolsData.symbols.forEach(symbol => {
                        const option = document.createElement('option');
                        option.value = symbol;
                        option.textContent = symbol;
                        symbolsSelect.appendChild(option);
                    });
                }
            }
        } catch (error) {
            console.error('Error loading filters:', error);
        }
    }

    /**
     * Cargar todos los datos
     */
    function loadAllData() {
        loadKPIs();
        loadBotsComparison();
        loadEquityCurve();
        loadRollingMetrics();
        loadDiversification();
        loadCorrelationMatrix();
        loadCalendarHeatmap();
        loadOperationsTable(1);
    }

    /**
     * Mostrar selector de fechas personalizado
     */
    function showCustomDatePicker() {
        // Aquí podrías implementar un date picker personalizado
        // Por ahora usamos prompt simple
        const startDate = prompt('Fecha inicio (YYYY-MM-DD):');
        const endDate = prompt('Fecha fin (YYYY-MM-DD):');
        
        if (startDate && endDate) {
            AnalysisState.customDateRange = {
                start: startDate,
                end: endDate
            };
            loadAllData();
        }
    }

    // ============================================
    // INICIALIZACIÓN
    // ============================================

    /**
     * Inicializar la página
     */
    async function init() {
        console.log('📊 Inicializando página de Análisis y Rendimiento...');
        
        // Cargar configuración de bots
        if (typeof BOT_NAMES === 'undefined') {
            console.error('❌ Configuración de bots no cargada');
            return;
        }
        
        // Inicializar event listeners
        initEventListeners();
        
        // Cargar filtros
        await loadFilters();
        
        // Cargar datos iniciales
        loadAllData();
        
        // Ocultar loading inicial
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            setTimeout(() => {
                loadingOverlay.style.display = 'none';
            }, 500);
        }
        
        console.log('✅ Página de Análisis inicializada correctamente');
    }

    // Esperar a que el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();