//+------------------------------------------------------------------+
//| Copyright 2025, Elysium Media FZCO                              |
//| https://www.elysiumdubai.net                                    |
//| Modal Charts - COPIA EXACTA de updateMiniChart                  |
//| Versión 7.0 - Sistema de Reportes de Trading                    |
//+------------------------------------------------------------------+

/**
 * SOLUCIÓN DEFINITIVA: Copia EXACTA de la función updateMiniChart
 * pero adaptada para el modal
 */

(function() {
    'use strict';
    
    console.log('[Modal Charts] Inicializando - COPIA EXACTA de updateMiniChart');
    
    // ============================================
    // FUNCIÓN PRINCIPAL - COPIA EXACTA DE updateMiniChart
    // ============================================
    window.updateModalChart = function(login, chartData) {
        console.log(`[Modal Charts] updateModalChart llamado para ${login}`);
        
        // Si no hay chartData, intentar obtenerlo del estado global
        if (!chartData && window.AccountsState && window.AccountsState.chartsData) {
            chartData = window.AccountsState.chartsData[login];
        }
        
        if (!chartData) {
            console.error(`[Modal Charts] No hay datos para ${login}`);
            return;
        }
        
        const canvas = document.getElementById('modalEquityChart');
        if (!canvas) {
            console.error('[Modal Charts] Canvas modalEquityChart no encontrado');
            return;
        }
        
        // Destruir chart existente - EXACTO como en updateMiniChart
        if (window.AccountsState.charts.modalCharts.equity) {
            try {
                window.AccountsState.charts.modalCharts.equity.destroy();
            } catch (e) {}
            delete window.AccountsState.charts.modalCharts.equity;
        }
        
        const ctx = canvas.getContext('2d');
        
        // Preparar datos - EXACTO como en updateMiniChart
        const equityData = chartData.equity || [];
        const dates = chartData.dates || [];
        
        // Simplificar si hay muchos puntos - EXACTO como en updateMiniChart
        let displayData = equityData;
        let displayDates = dates;
        
        if (equityData.length > 50) {
            const step = Math.ceil(equityData.length / 50);
            displayData = [];
            displayDates = [];
            for (let i = 0; i < equityData.length; i += step) {
                displayData.push(equityData[i]);
                displayDates.push(dates[i] || '');
            }
            // Asegurar último punto
            if (displayData[displayData.length-1] !== equityData[equityData.length-1]) {
                displayData.push(equityData[equityData.length-1]);
                displayDates.push(dates[dates.length-1] || '');
            }
        }
        
        // Determinar color basado en tendencia - EXACTO como en updateMiniChart
        const isPositive = displayData.length > 1 && 
                          displayData[displayData.length-1] >= displayData[0];
        
        // Crear gradiente - EXACTO como en updateMiniChart pero con altura del modal
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        if (isPositive) {
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
        } else {
            gradient.addColorStop(0, 'rgba(239, 68, 68, 0.3)');
            gradient.addColorStop(1, 'rgba(239, 68, 68, 0)');
        }
        
        // Crear chart - CONFIGURACIÓN EXACTA de updateMiniChart
        window.AccountsState.charts.modalCharts.equity = new Chart(ctx, {
            type: 'line',
            data: {
                labels: displayDates,
                datasets: [{
                    data: displayData,
                    borderColor: isPositive ? '#10b981' : '#ef4444',
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: isPositive ? '#10b981' : '#ef4444'
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
                        enabled: true,
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(22, 22, 43, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#a0aec0',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return '$' + context.parsed.y.toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    }
                },
                scales: {
                    x: { 
                        display: false 
                    },
                    y: { 
                        display: false,
                        beginAtZero: false 
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false
                }
            }
        });
        
        console.log(`[Modal Charts] Chart creado con ${displayData.length} puntos (de ${equityData.length} totales)`);
        
        // Agregar chart de balance si hay datos
        if (chartData.balance && chartData.balance.length > 0) {
            addBalanceLineToChart(chartData.balance, dates);
        }
    };
    
    // ============================================
    // AGREGAR LÍNEA DE BALANCE AL CHART
    // ============================================
    function addBalanceLineToChart(balanceData, dates) {
        if (!window.AccountsState.charts.modalCharts.equity) return;
        
        const chart = window.AccountsState.charts.modalCharts.equity;
        
        // Simplificar datos de balance igual que equity
        let displayBalance = balanceData;
        let displayDates = dates;
        
        if (balanceData.length > 50) {
            const step = Math.ceil(balanceData.length / 50);
            displayBalance = [];
            for (let i = 0; i < balanceData.length; i += step) {
                displayBalance.push(balanceData[i]);
            }
            if (displayBalance[displayBalance.length-1] !== balanceData[balanceData.length-1]) {
                displayBalance.push(balanceData[balanceData.length-1]);
            }
        }
        
        // Agregar dataset de balance
        chart.data.datasets.push({
            label: 'Balance',
            data: displayBalance,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99, 102, 241, 0.1)',
            borderWidth: 1.5,
            fill: false,
            tension: 0.4,
            pointRadius: 0,
            pointHoverRadius: 3,
            borderDash: [5, 5]
        });
        
        chart.update('none');
    }
    
    // ============================================
    // CARGAR DATOS DESDE API SI NO EXISTEN
    // ============================================
    async function loadChartData(login) {
        try {
            console.log(`[Modal Charts] Cargando datos para ${login}`);
            const response = await fetch(`api/get_account_chart_data.php?login=${login}`);
            const data = await response.json();
            
            if (data.success && data.charts_data && data.charts_data[login]) {
                // Guardar en estado global
                if (!window.AccountsState.chartsData) {
                    window.AccountsState.chartsData = {};
                }
                window.AccountsState.chartsData[login] = data.charts_data[login];
                console.log(`[Modal Charts] Datos cargados: ${data.charts_data[login].points} puntos`);
                return data.charts_data[login];
            }
        } catch (error) {
            console.error('[Modal Charts] Error cargando datos:', error);
        }
        return null;
    }
    
    // ============================================
    // INTERCEPTAR APERTURA DEL MODAL
    // ============================================
    const originalOpenModal = window.openAccountModal;
    window.openAccountModal = async function(login) {
        console.log(`[Modal Charts] Abriendo modal para ${login}`);
        
        // Llamar función original
        if (originalOpenModal) {
            await originalOpenModal(login);
        }
        
        // Esperar a que el modal esté visible
        setTimeout(async () => {
            // Obtener datos de charts
            let chartData = null;
            
            // Primero intentar del estado global
            if (window.AccountsState && window.AccountsState.chartsData) {
                chartData = window.AccountsState.chartsData[login];
            }
            
            // Si no hay datos, cargar desde API
            if (!chartData) {
                chartData = await loadChartData(login);
            }
            
            // Actualizar chart con los datos
            if (chartData) {
                window.updateModalChart(login, chartData);
            } else {
                console.error(`[Modal Charts] No se pudieron obtener datos para ${login}`);
            }
            
            // Cargar posiciones y histórico
            loadModalTabs(login);
        }, 100);
    };
    
    // ============================================
    // CARGAR TABS DE POSICIONES E HISTÓRICO
    // ============================================
    async function loadModalTabs(login) {
        // Posiciones abiertas
        const posContainer = document.getElementById('tab-positions');
        if (posContainer) {
            try {
                const response = await fetch(`api/get_account_positions.php?login=${login}`);
                const data = await response.json();
                
                if (data.success && data.positions && data.positions.length > 0) {
                    renderPositionsTable(data.positions, posContainer);
                } else {
                    posContainer.innerHTML = '<div class="no-data">No hay posiciones abiertas</div>';
                }
            } catch (error) {
                console.error('[Modal Charts] Error cargando posiciones:', error);
                posContainer.innerHTML = '<div class="error-message">Error cargando posiciones</div>';
            }
        }
        
        // Histórico
        const histContainer = document.getElementById('tab-history');
        if (histContainer) {
            try {
                const response = await fetch(`api/get_account_history.php?login=${login}&limit=100`);
                const data = await response.json();
                
                if (data.success && data.history && data.history.length > 0) {
                    renderHistoryTable(data.history, histContainer);
                } else {
                    histContainer.innerHTML = '<div class="no-data">No hay historial disponible</div>';
                }
            } catch (error) {
                console.error('[Modal Charts] Error cargando historial:', error);
                histContainer.innerHTML = '<div class="error-message">Error cargando historial</div>';
            }
        }
    }
    
    // ============================================
    // RENDERIZAR TABLA DE POSICIONES
    // ============================================
    function renderPositionsTable(positions, container) {
        let html = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Símbolo</th>
                        <th>Tipo</th>
                        <th>Volumen</th>
                        <th>Precio</th>
                        <th>S/L</th>
                        <th>T/P</th>
                        <th>Profit</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        positions.forEach(pos => {
            const profitClass = pos.profit >= 0 ? 'positive' : 'negative';
            const typeLabel = pos.type == 0 ? 'BUY' : 'SELL';
            const typeClass = pos.type == 0 ? 'buy' : 'sell';
            
            html += `
                <tr>
                    <td>${pos.ticket}</td>
                    <td>${pos.symbol}</td>
                    <td><span class="badge ${typeClass}">${typeLabel}</span></td>
                    <td>${parseFloat(pos.volume).toFixed(2)}</td>
                    <td>${parseFloat(pos.price_open).toFixed(5)}</td>
                    <td>${pos.sl > 0 ? parseFloat(pos.sl).toFixed(5) : '-'}</td>
                    <td>${pos.tp > 0 ? parseFloat(pos.tp).toFixed(5) : '-'}</td>
                    <td class="${profitClass}">$${parseFloat(pos.profit).toFixed(2)}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    
    // ============================================
    // RENDERIZAR TABLA DE HISTÓRICO
    // ============================================
    function renderHistoryTable(history, container) {
        let html = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Ticket</th>
                        <th>Símbolo</th>
                        <th>Tipo</th>
                        <th>Volumen</th>
                        <th>Precio</th>
                        <th>Profit</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        history.forEach(deal => {
            const profitClass = deal.profit >= 0 ? 'positive' : 'negative';
            
            html += `
                <tr>
                    <td>${new Date(deal.time).toLocaleDateString()}</td>
                    <td>${deal.ticket}</td>
                    <td>${deal.symbol}</td>
                    <td>${deal.type_label || deal.type}</td>
                    <td>${parseFloat(deal.volume).toFixed(2)}</td>
                    <td>${parseFloat(deal.price).toFixed(5)}</td>
                    <td class="${profitClass}">$${parseFloat(deal.profit).toFixed(2)}</td>
                </tr>
            `;
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    
    // ============================================
    // LIMPIEZA AL CERRAR MODAL
    // ============================================
    const originalCloseModal = window.closeAccountModal;
    window.closeAccountModal = function() {
        // Destruir charts
        if (window.AccountsState && window.AccountsState.charts && window.AccountsState.charts.modalCharts) {
            if (window.AccountsState.charts.modalCharts.equity) {
                try {
                    window.AccountsState.charts.modalCharts.equity.destroy();
                } catch (e) {}
                delete window.AccountsState.charts.modalCharts.equity;
            }
        }
        
        // Llamar función original
        if (originalCloseModal) {
            originalCloseModal();
        }
    };
    
    console.log('[Modal Charts] ✅ Sistema inicializado - COPIA EXACTA de updateMiniChart');
    
})();
<!-- 2. JAVASCRIPT PARA ARREGLAR EL CHART -->
// SOLUCIÓN DEFINITIVA PARA EL CHART DEL MODAL
(function() {
    'use strict';
    
    // Función para actualizar el chart del modal CON DATOS REALES
    window.forceModalChartWithRealData = function(login) {
        console.log(`[Force Modal Chart] Forzando chart para cuenta ${login}`);
        
        // Obtener el canvas
        const canvas = document.getElementById('modalEquityChart');
        if (!canvas) {
            console.error('[Force Modal Chart] Canvas no encontrado');
            return;
        }
        
        // Destruir chart existente
        const existingChart = Chart.getChart(canvas);
        if (existingChart) {
            existingChart.destroy();
        }
        
        // Obtener datos reales de la API
        fetch(`api/get_account_chart_data.php?login=${login}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.charts_data && data.charts_data[login]) {
                    const chartData = data.charts_data[login];
                    console.log(`[Force Modal Chart] Datos recibidos: ${chartData.points} puntos`);
                    
                    // Crear el chart con datos reales
                    createModalChart(canvas, chartData);
                } else {
                    console.error('[Force Modal Chart] No se recibieron datos válidos');
                }
            })
            .catch(error => {
                console.error('[Force Modal Chart] Error:', error);
            });
    };
    
    // Función para crear el chart
    function createModalChart(canvas, chartData) {
        const ctx = canvas.getContext('2d');
        
        // Preparar datos
        let equityData = chartData.equity || [];
        let dates = chartData.dates || [];
        
        // Si hay muchos puntos, tomar una muestra
        if (equityData.length > 100) {
            const step = Math.floor(equityData.length / 100);
            const sampledEquity = [];
            const sampledDates = [];
            
            for (let i = 0; i < equityData.length; i += step) {
                sampledEquity.push(equityData[i]);
                sampledDates.push(dates[i]);
            }
            
            // Asegurar el último punto
            sampledEquity.push(equityData[equityData.length - 1]);
            sampledDates.push(dates[dates.length - 1]);
            
            equityData = sampledEquity;
            dates = sampledDates;
        }
        
        console.log(`[Force Modal Chart] Creando chart con ${equityData.length} puntos`);
        console.log(`[Force Modal Chart] Rango: ${Math.min(...equityData)} a ${Math.max(...equityData)}`);
        
        // Determinar color según tendencia
        const isPositive = equityData[equityData.length - 1] >= equityData[0];
        const color = isPositive ? '#10b981' : '#ef4444';
        
        // Crear gradiente
        const gradient = ctx.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, `${color}33`);
        gradient.addColorStop(1, `${color}00`);
        
        // Crear el chart
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Equity',
                    data: equityData,
                    borderColor: color,
                    backgroundColor: gradient,
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    pointBackgroundColor: color
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: '#a0aec0'
                        }
                    },
                    tooltip: {
                        enabled: true,
                        backgroundColor: 'rgba(22, 22, 43, 0.95)',
                        callbacks: {
                            label: function(context) {
                                return 'Equity: $' + context.parsed.y.toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#718096',
                            maxTicksLimit: 10
                        }
                    },
                    y: {
                        display: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        },
                        ticks: {
                            color: '#718096',
                            callback: function(value) {
                                return '$' + value.toLocaleString('en-US');
                            }
                        }
                    }
                }
            }
        });
        
        console.log('[Force Modal Chart] ✅ Chart creado exitosamente');
    }
    
    // Interceptar la apertura del modal
    const originalOpen = window.openAccountModal;
    window.openAccountModal = function(login) {
        console.log(`[Force Modal Chart] Modal abierto para cuenta ${login}`);
        
        // Llamar función original
        if (originalOpen) {
            originalOpen(login);
        }
        
        // Forzar actualización del chart después de un delay
        setTimeout(() => {
            window.forceModalChartWithRealData(login);
        }, 500);
    };
    
    // Si el modal ya está abierto, actualizar ahora
    const modal = document.querySelector('.modal-overlay.show');
    if (modal) {
        const accountTitle = document.getElementById('modal-account-title');
        if (accountTitle) {
            const login = accountTitle.textContent.replace('Cuenta #', '').trim();
            if (login) {
                setTimeout(() => {
                    window.forceModalChartWithRealData(login);
                }, 100);
            }
        }
    }
    
    console.log('[Force Modal Chart] Sistema inicializado');
})();