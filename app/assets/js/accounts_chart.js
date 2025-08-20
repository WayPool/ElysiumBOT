// Script de corrección inmediata para charts en accounts.php
// Añadir al final de accounts.php antes de </body>

// Esperar a que todo esté cargado
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Iniciando corrección de charts...');
    
    // Función para inicializar charts con datos reales
    function initChartsWithRealData() {
        // Verificar que Chart.js esté disponible
        if (typeof Chart === 'undefined') {
            console.error('Chart.js no disponible, reintentando...');
            setTimeout(initChartsWithRealData, 500);
            return;
        }
        
        console.log('📊 Cargando datos de charts desde API...');
        
        // Cargar datos del API
        fetch('api/get_account_chart_data.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.charts_data) {
                    console.log('✅ Datos recibidos para', Object.keys(data.charts_data).length, 'cuentas');
                    
                    // Para cada cuenta con datos
                    Object.keys(data.charts_data).forEach(login => {
                        const chartData = data.charts_data[login];
                        const canvas = document.getElementById('miniChart-' + login);
                        
                        if (canvas) {
                            console.log('📈 Actualizando chart para cuenta', login);
                            
                            // Destruir chart existente si hay uno
                            if (window.AccountsState && 
                                window.AccountsState.charts && 
                                window.AccountsState.charts.miniCharts && 
                                window.AccountsState.charts.miniCharts[login]) {
                                try {
                                    window.AccountsState.charts.miniCharts[login].destroy();
                                } catch (e) {}
                            }
                            
                            // Crear nuevo chart con datos reales
                            const ctx = canvas.getContext('2d');
                            
                            // Crear gradiente
                            const gradient = ctx.createLinearGradient(0, 0, 0, 100);
                            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
                            gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
                            
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: Array(20).fill(''),
                                    datasets: [{
                                        data: chartData.equity,
                                        borderColor: '#6366f1',
                                        backgroundColor: gradient,
                                        borderWidth: 2,
                                        fill: true,
                                        tension: 0.4,
                                        pointRadius: 0,
                                        pointHoverRadius: 3,
                                        pointBackgroundColor: '#6366f1',
                                        pointBorderColor: '#fff',
                                        pointBorderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    animation: {
                                        duration: 500
                                    },
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
                                            padding: 8,
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
                                            display: false,
                                            grid: { display: false }
                                        },
                                        y: { 
                                            display: false,
                                            grid: { display: false },
                                            beginAtZero: false
                                        }
                                    },
                                    interaction: {
                                        mode: 'nearest',
                                        axis: 'x',
                                        intersect: false
                                    }
                                }
                            });
                            
                            console.log('✅ Chart actualizado para', login);
                        } else {
                            console.warn('Canvas no encontrado para', login);
                        }
                    });
                    
                    console.log('🎉 Todos los charts actualizados con datos reales');
                    
                    // Guardar datos para uso en modales
                    if (window.AccountsState) {
                        window.AccountsState.chartsData = data.charts_data;
                    }
                    
                } else {
                    console.error('❌ No se recibieron datos válidos del API');
                }
            })
            .catch(error => {
                console.error('❌ Error cargando datos:', error);
            });
    }
    
    // Función para actualizar charts del modal
    window.updateModalChart = function(login) {
        if (!window.AccountsState || !window.AccountsState.chartsData || !window.AccountsState.chartsData[login]) {
            console.warn('No hay datos para el modal de cuenta', login);
            return;
        }
        
        const chartData = window.AccountsState.chartsData[login];
        const canvas = document.getElementById('modalEquityChart');
        
        if (canvas) {
            // Destruir chart existente
            if (window.AccountsState.charts.modalCharts.equity) {
                try {
                    window.AccountsState.charts.modalCharts.equity.destroy();
                } catch (e) {}
            }
            
            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 300);
            gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
            gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
            
            window.AccountsState.charts.modalCharts.equity = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: Array(20).fill('').map((_, i) => i === 19 ? 'Actual' : ''),
                    datasets: [{
                        label: 'Equity',
                        data: chartData.equity,
                        borderColor: '#6366f1',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: '#a0aec0',
                                font: { size: 12 }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(22, 22, 43, 0.95)',
                            titleColor: '#fff',
                            bodyColor: '#a0aec0',
                            borderColor: 'rgba(255, 255, 255, 0.1)',
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': $' + context.parsed.y.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.03)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#718096',
                                font: { size: 11 }
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.03)',
                                drawBorder: false
                            },
                            ticks: {
                                color: '#718096',
                                font: { size: 11 },
                                callback: function(value) {
                                    return '$' + value.toLocaleString('en-US', {
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0
                                    });
                                }
                            },
                            beginAtZero: false
                        }
                    }
                }
            });
            
            console.log('✅ Modal chart actualizado para cuenta', login);
        }
    };
    
    // Interceptar la función openAccountModal para actualizar charts
    const originalOpenModal = window.openAccountModal;
    window.openAccountModal = function(login) {
        // Llamar a la función original
        if (originalOpenModal) {
            originalOpenModal(login);
        }
        
        // Actualizar chart del modal después de un pequeño delay
        setTimeout(() => {
            window.updateModalChart(login);
        }, 200);
    };
    
    // Iniciar después de un pequeño delay para asegurar que todo esté cargado
    setTimeout(initChartsWithRealData, 1000);
    
    // Reintentar cada 5 segundos si no hay charts visibles
    setInterval(() => {
        const canvases = document.querySelectorAll('[id^="miniChart-"]');
        let chartsEmpty = true;
        
        canvases.forEach(canvas => {
            if (canvas.chart || (canvas.getContext && canvas.getContext('2d').__chart)) {
                chartsEmpty = false;
            }
        });
        
        if (chartsEmpty && canvases.length > 0) {
            console.log('🔄 Reintentando cargar charts...');
            initChartsWithRealData();
        }
    }, 5000);
});

console.log('✅ Script de corrección de charts cargado');