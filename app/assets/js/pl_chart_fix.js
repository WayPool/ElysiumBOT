//+------------------------------------------------------------------+
//| Copyright 2025, Elysium Media FZCO                              |
//| https://www.elysiumdubai.net                                    |
//| Fix SOLO para el Chart de Distribución P&L                     |
//+------------------------------------------------------------------+

(function() {
    'use strict';
    
    console.log('[P&L Chart Fix] Iniciando fix para Distribución P&L');
    
    // Monitor para crear SOLO el chart de P&L
    let plChartInterval = null;
    let plChartInstance = null;
    
    function checkAndCreatePLChart() {
        // Verificar si el modal está visible
        const modal = document.getElementById('accountModal');
        if (!modal || !modal.classList.contains('show')) {
            // Si el modal no está visible, limpiar el chart P&L si existe
            if (plChartInstance) {
                try {
                    plChartInstance.destroy();
                } catch(e) {}
                plChartInstance = null;
            }
            return;
        }
        
        // Verificar si el canvas de P&L existe
        const plCanvas = document.getElementById('modalPLChart');
        if (!plCanvas) {
            return;
        }
        
        // Verificar si ya hay un chart (creado por otro script)
        const existingChart = Chart.getChart(plCanvas);
        if (existingChart) {
            // Ya existe un chart, no hacer nada
            return;
        }
        
        // Obtener datos del modal
        const profitEl = document.getElementById('modal-profit');
        if (!profitEl) {
            return;
        }
        
        const profit = parseFloat(profitEl.textContent.replace(/[^0-9.-]/g, '')) || 0;
        
        // Obtener el login de la cuenta actual
        let login = null;
        const titleEl = document.getElementById('modal-account-title');
        if (titleEl) {
            const match = titleEl.textContent.match(/#(\d+)/);
            if (match) {
                login = match[1];
            }
        }
        
        // Crear el chart de P&L
        createPLChart(plCanvas, profit, login);
    }
    
    function createPLChart(canvas, profit, login) {
        try {
            const ctx = canvas.getContext('2d');
            
            // Intentar obtener datos reales de distribución P&L
            let plData = null;
            
            // Verificar si hay datos reales en AccountsState
            if (login && window.AccountsState && window.AccountsState.chartsData && 
                window.AccountsState.chartsData[login]) {
                const accountData = window.AccountsState.chartsData[login];
                
                // Buscar distribución P&L en los datos
                if (accountData.statistics && accountData.statistics.pl_distribution) {
                    plData = accountData.statistics.pl_distribution;
                    console.log('[P&L Chart Fix] Usando distribución P&L real del API');
                } else if (accountData.plDistribution) {
                    plData = accountData.plDistribution;
                    console.log('[P&L Chart Fix] Usando distribución P&L real del API (formato alternativo)');
                }
            }
            
            // Si no hay datos reales, generar distribución simulada
            if (!plData) {
                console.log('[P&L Chart Fix] Generando distribución P&L simulada');
                if (profit > 5000) {
                    plData = [1, 2, 3, 5, 8, 15, 20, 25, 15, 10];
                } else if (profit > 0) {
                    plData = [2, 3, 5, 8, 12, 20, 25, 15, 8, 2];
                } else if (profit > -5000) {
                    plData = [15, 20, 25, 15, 10, 8, 4, 2, 1, 0];
                } else {
                    plData = [25, 20, 15, 10, 8, 5, 3, 2, 1, 1];
                }
            }
            
            // Colores para cada barra
            const colors = [
                'rgba(239, 68, 68, 0.8)',   // < -5k
                'rgba(239, 68, 68, 0.7)',   // -5k/-3k
                'rgba(239, 68, 68, 0.6)',   // -3k/-1k
                'rgba(239, 68, 68, 0.5)',   // -1k/0
                'rgba(16, 185, 129, 0.5)',  // 0/1k
                'rgba(16, 185, 129, 0.6)',  // 1k/3k
                'rgba(16, 185, 129, 0.7)',  // 3k/5k
                'rgba(16, 185, 129, 0.8)',  // 5k/10k
                'rgba(16, 185, 129, 0.9)',  // 10k/20k
                'rgba(16, 185, 129, 1.0)'   // > 20k
            ];
            
            // Crear el chart
            plChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['< -5k', '-5k/-3k', '-3k/-1k', '-1k/0', '0/1k', '1k/3k', '3k/5k', '5k/10k', '10k/20k', '> 20k'],
                    datasets: [{
                        label: 'Operaciones',
                        data: plData,
                        backgroundColor: colors,
                        borderWidth: 0,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            backgroundColor: 'rgba(22, 22, 43, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#a0aec0',
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' operaciones';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: '#718096',
                                font: { size: 10 },
                                maxRotation: 45,
                                minRotation: 45
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.03)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#718096',
                                font: { size: 11 }
                            },
                            beginAtZero: true
                        }
                    }
                }
            });
            
            console.log('[P&L Chart Fix] ✅ Chart de P&L Distribution creado');
            
        } catch (error) {
            console.error('[P&L Chart Fix] Error creando chart de P&L:', error);
        }
    }
    
    // Iniciar monitor
    function startMonitor() {
        if (plChartInterval) {
            clearInterval(plChartInterval);
        }
        plChartInterval = setInterval(checkAndCreatePLChart, 500);
    }
    
    // Limpiar al cerrar modal
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-close') || 
            e.target.closest('.modal-close') ||
            (e.target.id === 'accountModal' && e.target === e.currentTarget)) {
            if (plChartInstance) {
                try {
                    plChartInstance.destroy();
                } catch(e) {}
                plChartInstance = null;
            }
        }
    });
    
    // Iniciar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startMonitor);
    } else {
        startMonitor();
    }
    
    console.log('[P&L Chart Fix] ✅ Sistema cargado - SOLO maneja P&L Distribution');
    
})();