/**
 * Charts System - Elysium v7.0
 * Sistema Profesional de Graficos para Trading
 * Copyright 2025, Elysium Media FZCO
 */

// ============================================
// CONFIGURACION DE COLORES Y TEMAS
// ============================================
const ChartTheme = {
    colors: {
        primary: '#6366f1',
        success: '#10b981',
        warning: '#f59e0b',
        danger: '#ef4444',
        info: '#3b82f6',
        purple: '#8b5cf6',
        pink: '#ec4899',
        cyan: '#06b6d4',
        
        // Gradientes
        gradientPrimary: ['#6366f1', '#818cf8'],
        gradientSuccess: ['#10b981', '#34d399'],
        gradientWarning: ['#f59e0b', '#fbbf24'],
        gradientDanger: ['#ef4444', '#f87171'],
        
        // Colores de fondo
        background: '#0f0f1a',
        cardBg: '#16162b',
        borderColor: 'rgba(255, 255, 255, 0.05)',
        gridColor: 'rgba(255, 255, 255, 0.03)',
        textColor: '#a0aec0',
        textMuted: '#718096'
    },
    
    font: {
        family: "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
        size: 12,
        weight: 400
    }
};

// ============================================
// ESTADO GLOBAL DE GRAFICOS
// ============================================
let charts = {
    equityChart: null,
    plDistribution: null,
    riskRadar: null,
    symbolPerformance: null,
    heatmap: null,
    miniChart1: null,
    miniChart2: null,
    miniChart3: null,
    miniChart4: null
};

// ============================================
// INICIALIZACION DE GRAFICOS
// ============================================
function initializeCharts() {
    console.log('Inicializando sistema de graficos...');
    
    // Destruir graficos existentes si existen
    destroyAllCharts();
    
    // Configuracion global de Chart.js
    if (window.Chart) {
        Chart.defaults.color = ChartTheme.colors.textColor;
        Chart.defaults.font.family = ChartTheme.font.family;
        Chart.defaults.font.size = ChartTheme.font.size;
        Chart.defaults.borderColor = ChartTheme.colors.borderColor;
        
        // Inicializar graficos
        initEquityChart();
        initPLDistribution();
        initRiskRadar();
        initSymbolPerformance();
        initMiniCharts();
    }
    
    // Inicializar heatmap con ApexCharts
    if (window.ApexCharts) {
        initHeatmap();
    }
    
    console.log('Graficos inicializados correctamente');
}

// ============================================
// DESTRUIR GRAFICOS
// ============================================
function destroyAllCharts() {
    Object.keys(charts).forEach(key => {
        if (charts[key]) {
            if (charts[key].destroy) {
                charts[key].destroy();
            } else if (charts[key].remove) {
                charts[key].remove();
            }
            charts[key] = null;
        }
    });
}

// ============================================
// GRAFICO DE EQUITY
// ============================================
function initEquityChart() {
    const canvas = document.getElementById('equityChart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    
    // Crear gradiente
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(99, 102, 241, 0.2)');
    gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
    
    charts.equityChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Equity',
                data: [],
                borderColor: ChartTheme.colors.primary,
                backgroundColor: gradient,
                borderWidth: 3,
                fill: true,
                tension: 0.1,
                pointRadius: 0,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: ChartTheme.colors.primary,
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2
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
                        usePointStyle: true,
                        padding: 15,
                        font: {
                            size: 12,
                            weight: 500
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(22, 22, 43, 0.95)',
                    titleColor: '#fff',
                    bodyColor: ChartTheme.colors.textColor,
                    borderColor: ChartTheme.colors.borderColor,
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            const value = context.parsed.y || 0;
                            label += '$' + value.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: ChartTheme.colors.gridColor,
                        drawBorder: false
                    },
                    ticks: {
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8,
                        font: {
                            size: 11
                        }
                    }
                },
                y: {
                    position: 'right',
                    grid: {
                        color: ChartTheme.colors.gridColor,
                        drawBorder: false
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + (value / 1000).toFixed(0) + 'K';
                        },
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
}

// ============================================
// DISTRIBUCION DE P&L
// ============================================
function initPLDistribution() {
    const canvas = document.getElementById('plDistribution');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    
    charts.plDistribution = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Frecuencia',
                data: [],
                backgroundColor: [],
                borderColor: [],
                borderWidth: 1,
                borderRadius: 4
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
                    callbacks: {
                        label: function(context) {
                            return 'Trades: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 10
                        }
                    }
                },
                y: {
                    grid: {
                        color: ChartTheme.colors.gridColor
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
}

// ============================================
// RADAR DE METRICAS DE RIESGO
// ============================================
function initRiskRadar() {
    const canvas = document.getElementById('riskRadar');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    
    charts.riskRadar = new Chart(ctx, {
        type: 'radar',
        data: {
            labels: [
                'Win Rate',
                'Profit Factor',
                'Sharpe Ratio',
                'Sortino Ratio',
                'Calmar Ratio',
                'Recovery'
            ],
            datasets: [{
                label: 'Actual',
                data: [0, 0, 0, 0, 0, 0],
                borderColor: ChartTheme.colors.primary,
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                borderWidth: 2,
                pointBackgroundColor: ChartTheme.colors.primary,
                pointBorderColor: '#fff',
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: ChartTheme.colors.primary
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        font: {
                            size: 11
                        }
                    }
                }
            },
            scales: {
                r: {
                    angleLines: {
                        color: ChartTheme.colors.gridColor
                    },
                    grid: {
                        color: ChartTheme.colors.gridColor
                    },
                    pointLabels: {
                        color: ChartTheme.colors.textColor,
                        font: {
                            size: 11,
                            weight: 500
                        }
                    },
                    ticks: {
                        backdropColor: 'transparent',
                        color: ChartTheme.colors.textMuted,
                        font: {
                            size: 10
                        }
                    },
                    suggestedMin: 0,
                    suggestedMax: 100
                }
            }
        }
    });
}

// ============================================
// PERFORMANCE POR SIMBOLO
// ============================================
function initSymbolPerformance() {
    const canvas = document.getElementById('symbolPerformance');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    
    charts.symbolPerformance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Profit',
                data: [],
                backgroundColor: [],
                borderColor: [],
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Profit: $' + context.parsed.x.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: ChartTheme.colors.gridColor
                    },
                    ticks: {
                        callback: function(value) {
                            return '$' + (value / 1000).toFixed(0) + 'K';
                        },
                        font: {
                            size: 10
                        }
                    }
                },
                y: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
}

// ============================================
// MINI CHARTS - MODIFICADO PARA ELIMINAR PUNTOS
// ============================================
function initMiniCharts() {
    // Mini Chart 1 - CAPITAL TOTAL (SIN PUNTOS)
    const canvas1 = document.getElementById('miniChart1');
    if (canvas1) {
        const ctx1 = canvas1.getContext('2d');
        if (ctx1 && !charts.miniChart1) {
            // Crear gradiente para el área bajo la línea
            const gradient = ctx1.createLinearGradient(0, 0, 0, 50);
            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
            gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
            
            charts.miniChart1 = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: Array(20).fill(''),
                    datasets: [{
                        data: Array(20).fill(0),
                        borderColor: '#6366f1',
                        backgroundColor: gradient,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        // CONFIGURACIÓN PARA ELIMINAR PUNTOS COMPLETAMENTE
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointBackgroundColor: 'transparent',
                        pointBorderColor: 'transparent',
                        pointBorderWidth: 0,
                        pointHoverBackgroundColor: 'transparent',
                        pointHoverBorderColor: 'transparent',
                        pointHoverBorderWidth: 0,
                        hitRadius: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false, // Desactivar animaciones
                    plugins: {
                        legend: { display: false },
                        tooltip: { 
                            enabled: true,
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(22, 22, 43, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#a0aec0',
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            padding: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed.y || 0;
                                    return 'Capital: $' + value.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        x: { display: false },
                        y: { 
                            display: false,
                            beginAtZero: false
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    elements: {
                        point: {
                            radius: 0,
                            hoverRadius: 0,
                            hitRadius: 0
                        }
                    }
                }
            });
        }
    }
    
    // Mini Chart 2 - EQUITY (SIN PUNTOS)
    const canvas2 = document.getElementById('miniChart2');
    if (canvas2) {
        const ctx2 = canvas2.getContext('2d');
        if (ctx2 && !charts.miniChart2) {
            const gradient = ctx2.createLinearGradient(0, 0, 0, 50);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
            
            charts.miniChart2 = new Chart(ctx2, {
                type: 'line',
                data: {
                    labels: Array(20).fill(''),
                    datasets: [{
                        data: Array(20).fill(0),
                        borderColor: '#10b981',
                        backgroundColor: gradient,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        // CONFIGURACIÓN PARA ELIMINAR PUNTOS
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointBackgroundColor: 'transparent',
                        pointBorderColor: 'transparent',
                        pointBorderWidth: 0,
                        pointHoverBackgroundColor: 'transparent',
                        pointHoverBorderColor: 'transparent',
                        pointHoverBorderWidth: 0,
                        hitRadius: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    elements: {
                        point: {
                            radius: 0,
                            hoverRadius: 0,
                            hitRadius: 0
                        }
                    }
                }
            });
        }
    }
    
    // Mini Chart 3 - BALANCE (SIN PUNTOS)
    const canvas3 = document.getElementById('miniChart3');
    if (canvas3) {
        const ctx3 = canvas3.getContext('2d');
        if (ctx3 && !charts.miniChart3) {
            const gradient = ctx3.createLinearGradient(0, 0, 0, 50);
            gradient.addColorStop(0, 'rgba(251, 191, 36, 0.3)');
            gradient.addColorStop(1, 'rgba(251, 191, 36, 0)');
            
            charts.miniChart3 = new Chart(ctx3, {
                type: 'line',
                data: {
                    labels: Array(20).fill(''),
                    datasets: [{
                        data: Array(20).fill(0),
                        borderColor: '#fbbf24',
                        backgroundColor: gradient,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        // CONFIGURACIÓN PARA ELIMINAR PUNTOS
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointBackgroundColor: 'transparent',
                        pointBorderColor: 'transparent',
                        pointBorderWidth: 0,
                        pointHoverBackgroundColor: 'transparent',
                        pointHoverBorderColor: 'transparent',
                        pointHoverBorderWidth: 0,
                        hitRadius: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    elements: {
                        point: {
                            radius: 0,
                            hoverRadius: 0,
                            hitRadius: 0
                        }
                    }
                }
            });
        }
    }
    
    // Mini Chart 4 - P&L (Tipo barra, no necesita cambios de puntos)
    const canvas4 = document.getElementById('miniChart4');
    if (canvas4) {
        const ctx4 = canvas4.getContext('2d');
        if (ctx4 && !charts.miniChart4) {
            charts.miniChart4 = new Chart(ctx4, {
                type: 'bar',
                data: {
                    labels: Array(20).fill(''),
                    datasets: [{
                        data: Array(20).fill(0),
                        backgroundColor: 'rgba(16, 185, 129, 0.5)',
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    }
                }
            });
        }
    }
}

// ============================================
// HEATMAP DE RETORNOS MENSUALES
// ============================================
function initHeatmap() {
    const element = document.getElementById('heatmapChart');
    if (!element) return;
    
    // Destruir heatmap existente si existe
    if (charts.heatmap) {
        charts.heatmap.destroy();
        charts.heatmap = null;
    }
    
    const options = {
        series: [],
        chart: {
            height: 350,
            type: 'heatmap',
            toolbar: {
                show: false
            },
            background: 'transparent'
        },
        dataLabels: {
            enabled: true,
            style: {
                colors: ['#fff'],
                fontSize: '11px',
                fontFamily: ChartTheme.font.family,
                fontWeight: 600
            },
            formatter: function(val) {
                return val ? val + '%' : '0%';
            }
        },
        plotOptions: {
            heatmap: {
                shadeIntensity: 0.5,
                radius: 0,
                useFillColorAsStroke: false,
                colorScale: {
                    ranges: [{
                        from: -10,
                        to: -5,
                        name: 'Perdida Alta',
                        color: '#ef4444'
                    }, {
                        from: -5,
                        to: 0,
                        name: 'Perdida',
                        color: '#f59e0b'
                    }, {
                        from: 0,
                        to: 5,
                        name: 'Ganancia',
                        color: '#10b981'
                    }, {
                        from: 5,
                        to: 15,
                        name: 'Ganancia Alta',
                        color: '#059669'
                    }]
                }
            }
        },
        xaxis: {
            type: 'category',
            categories: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
            labels: {
                style: {
                    colors: ChartTheme.colors.textColor,
                    fontSize: '11px',
                    fontFamily: ChartTheme.font.family
                }
            }
        },
        yaxis: {
            labels: {
                style: {
                    colors: ChartTheme.colors.textColor,
                    fontSize: '11px',
                    fontFamily: ChartTheme.font.family
                }
            }
        },
        grid: {
            borderColor: ChartTheme.colors.borderColor
        },
        tooltip: {
            theme: 'dark',
            y: {
                formatter: function(val) {
                    return val ? val + '%' : '0%';
                }
            }
        }
    };
    
    charts.heatmap = new ApexCharts(element, options);
    charts.heatmap.render();
}

// ============================================
// FUNCIONES DE ACTUALIZACION - CORREGIDAS
// ============================================
window.ChartsManager = {
    initialized: false,
    charts: charts, // Exponer charts para debugging
    
    init: function() {
        if (!this.initialized) {
            initializeCharts();
            this.initialized = true;
        }
    },
    
    updateEquityChart: function(data) {
        if (!charts.equityChart || !data) return;
        
        try {
            charts.equityChart.data.labels = data.labels || [];
            if (data.datasets && data.datasets[0]) {
                charts.equityChart.data.datasets[0].data = data.datasets[0].data || [];
                charts.equityChart.data.datasets[0].label = data.datasets[0].label || 'Equity';
            }
            charts.equityChart.update('none');
        } catch (error) {
            console.error('Error actualizando equity chart:', error);
        }
    },
    
    updatePLDistribution: function(data) {
        if (!charts.plDistribution || !data) return;
        
        try {
            charts.plDistribution.data.labels = data.labels || [];
            charts.plDistribution.data.datasets[0].data = data.data || [];
            
            // Colorear barras segun valor
            const colors = (data.labels || []).map(label => {
                const labelStr = String(label);
                if (labelStr.includes('-') || labelStr.startsWith('-')) {
                    return 'rgba(239, 68, 68, 0.6)';
                }
                if (labelStr === '0' || labelStr === '0+') {
                    return 'rgba(107, 114, 128, 0.6)';
                }
                return 'rgba(16, 185, 129, 0.6)';
            });
            
            charts.plDistribution.data.datasets[0].backgroundColor = colors;
            charts.plDistribution.update('none');
        } catch (error) {
            console.error('Error actualizando P&L distribution:', error);
        }
    },
    
    updateRiskRadar: function(data) {
        if (!charts.riskRadar || !data) return;
        
        try {
            // Normalizar valores para el radar (0-100)
            const metrics = [
                data.win_rate || 0,
                Math.min((data.profit_factor || 0) * 20, 100),
                Math.min((data.sharpe_ratio || 0) * 33, 100),
                Math.min((data.sortino_ratio || 0) * 25, 100),
                Math.min((data.calmar_ratio || 0) * 20, 100),
                Math.min((data.recovery_factor || 0) * 10, 100)
            ];
            
            charts.riskRadar.data.datasets[0].data = metrics;
            charts.riskRadar.update('none');
        } catch (error) {
            console.error('Error actualizando risk radar:', error);
        }
    },
    
    updateSymbolPerformance: function(data) {
        if (!charts.symbolPerformance || !data) return;
        
        try {
            charts.symbolPerformance.data.labels = data.labels || [];
            charts.symbolPerformance.data.datasets[0].data = data.profit || [];
            
            // Colorear segun profit
            const colors = (data.profit || []).map(p => 
                p >= 0 ? 'rgba(16, 185, 129, 0.6)' : 'rgba(239, 68, 68, 0.6)'
            );
            
            charts.symbolPerformance.data.datasets[0].backgroundColor = colors;
            charts.symbolPerformance.update('none');
        } catch (error) {
            console.error('Error actualizando symbol performance:', error);
        }
    },
    
    updateHeatmap: function(data) {
        if (!charts.heatmap || !data) return;
        
        try {
            const series = Object.keys(data).map(year => ({
                name: year,
                data: data[year] || []
            }));
            
            charts.heatmap.updateSeries(series);
        } catch (error) {
            console.error('Error actualizando heatmap:', error);
        }
    },
    
    updateMiniCharts: function(data) {
        // IMPORTANTE: Actualizar mini chart 1 con datos de evolución de capital
        try {
            console.log('🔍 updateMiniCharts llamado con data:', data);
            
            // Verificar y actualizar miniChart1 (CAPITAL)
            if (charts.miniChart1) {
                let capitalData = null;
                
                // Buscar datos de capital en diferentes ubicaciones posibles
                if (data && data.capital) {
                    capitalData = data.capital;
                    console.log('📊 Datos de capital encontrados en data.capital');
                } else if (data && Array.isArray(data)) {
                    capitalData = data;
                    console.log('📊 Datos de capital recibidos como array directo');
                }
                
                if (capitalData && capitalData.length > 0) {
                    console.log('📈 Actualizando gráfico de capital con:', capitalData);
                    console.log('📊 Primer valor:', capitalData[0], 'Último valor:', capitalData[capitalData.length - 1]);
                    
                    // Asegurarse de que los datos son números válidos
                    const processedData = capitalData.map((val, index) => {
                        const num = parseFloat(val);
                        if (isNaN(num)) {
                            console.warn(`⚠️ Valor inválido en posición ${index}:`, val);
                            return 0;
                        }
                        return num;
                    });
                    
                    // Verificar que hay datos válidos
                    const hasValidData = processedData.some(val => val > 0);
                    if (!hasValidData) {
                        console.error('❌ Todos los valores de capital son 0 o inválidos');
                        return;
                    }
                    
                    // Si todos los valores son iguales, agregar pequeña variación para visualización
                    const allEqual = processedData.every(val => val === processedData[0]);
                    if (allEqual && processedData[0] > 0) {
                        console.log('📊 Agregando variación visual para mejor visualización');
                        for (let i = 1; i < processedData.length; i++) {
                            processedData[i] = processedData[i-1] * 1.0001; // Micro incremento
                        }
                    }
                    
                    // ACTUALIZAR EL GRÁFICO
                    charts.miniChart1.data.datasets[0].data = processedData;
                    
                    // FORZAR ELIMINACIÓN DE PUNTOS
                    charts.miniChart1.data.datasets[0].pointRadius = 0;
                    charts.miniChart1.data.datasets[0].pointHoverRadius = 0;
                    charts.miniChart1.data.datasets[0].pointBackgroundColor = 'transparent';
                    charts.miniChart1.data.datasets[0].pointBorderColor = 'transparent';
                    
                    // Actualizar etiquetas
                    charts.miniChart1.data.labels = processedData.map((_, index) => `${index + 1}`);
                    
                    // Configurar escalas dinámicamente basadas en los datos
                    const minVal = Math.min(...processedData) * 0.98;
                    const maxVal = Math.max(...processedData) * 1.02;
                    
                    if (charts.miniChart1.options && charts.miniChart1.options.scales && charts.miniChart1.options.scales.y) {
                        charts.miniChart1.options.scales.y.min = minVal;
                        charts.miniChart1.options.scales.y.max = maxVal;
                        charts.miniChart1.options.scales.y.beginAtZero = false;
                    }
                    
                    // CAMBIO CRÍTICO: Usar 'none' en lugar de 'active'
                    charts.miniChart1.update('none');
                    
                    console.log('✅ Gráfico de capital actualizado exitosamente');
                    
                    // Actualizar el valor del KPI si existe
                    const lastValue = processedData[processedData.length - 1];
                    const kpiElement = document.getElementById('kpi-total-capital');
                    if (kpiElement && lastValue > 0) {
                        console.log('💰 Último valor de capital:', lastValue.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                    }
                } else {
                    console.error('❌ No se encontraron datos de capital válidos');
                    console.log('📍 Estructura recibida:', data);
                }
            } else {
                console.error('❌ miniChart1 no está inicializado');
            }
            
            // Actualizar miniChart2 (EQUITY) - SIN PUNTOS
            if (charts.miniChart2 && data && data.equity) {
                const equityData = data.equity.map(val => parseFloat(val) || 0);
                charts.miniChart2.data.datasets[0].data = equityData;
                charts.miniChart2.data.datasets[0].pointRadius = 0;
                charts.miniChart2.data.datasets[0].pointHoverRadius = 0;
                charts.miniChart2.update('none');
                console.log('✅ Gráfico de equity actualizado');
            }
            
            // Actualizar miniChart3 (BALANCE) - SIN PUNTOS
            if (charts.miniChart3 && data && data.balance) {
                const balanceData = data.balance.map(val => parseFloat(val) || 0);
                charts.miniChart3.data.datasets[0].data = balanceData;
                charts.miniChart3.data.datasets[0].pointRadius = 0;
                charts.miniChart3.data.datasets[0].pointHoverRadius = 0;
                charts.miniChart3.update('none');
                console.log('✅ Gráfico de balance actualizado');
            }
            
            // Actualizar miniChart4 (P&L)
            if (charts.miniChart4 && data && data.pl) {
                const plData = data.pl.map(val => parseFloat(val) || 0);
                charts.miniChart4.data.datasets[0].data = plData;
                const colors = plData.map(v => 
                    v >= 0 ? 'rgba(16, 185, 129, 0.5)' : 'rgba(239, 68, 68, 0.5)'
                );
                charts.miniChart4.data.datasets[0].backgroundColor = colors;
                charts.miniChart4.update('none');
                console.log('✅ Gráfico de P&L actualizado');
            }
        } catch (error) {
            console.error('❌ Error crítico en updateMiniCharts:', error);
            console.error('Stack trace:', error.stack);
        }
    },
    
    destroy: function() {
        destroyAllCharts();
        this.initialized = false;
    }
};

// ============================================
// INICIALIZACION CUANDO DOM ESTA LISTO
// ============================================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        window.ChartsManager.init();
    });
} else {
    // DOM ya esta cargado
    setTimeout(function() {
        window.ChartsManager.init();
    }, 100);
}

console.log('Charts Module v7.0 cargado correctamente');