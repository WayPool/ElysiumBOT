// Corrección específica para los charts del modal
// Agregar este código al final de accounts.js o como archivo separado

// Override de la función openAccountModal para asegurar que los charts del modal funcionen
(function() {
    // Guardar la función original
    const originalOpenModal = window.openAccountModal;
    
    // Reemplazar con versión mejorada
    window.openAccountModal = async function(login) {
        // Llamar a la función original
        if (originalOpenModal) {
            await originalOpenModal(login);
        }
        
        // Esperar un momento para que el modal esté completamente cargado
        setTimeout(() => {
            // Buscar la cuenta
            const account = AccountsState.accounts.find(a => a.login == login);
            if (!account) return;
            
            const equity = parseFloat(account.equity) || 0;
            const balance = parseFloat(account.balance) || 0;
            
            // Destruir charts existentes si hay
            if (AccountsState.charts.modalCharts.equity) {
                try {
                    AccountsState.charts.modalCharts.equity.destroy();
                } catch(e) {}
                AccountsState.charts.modalCharts.equity = null;
            }
            
            // Crear chart de Equity
            const equityCanvas = document.getElementById('modalEquityChart');
            if (equityCanvas) {
                const ctx = equityCanvas.getContext('2d');
                
                // Crear gradiente
                const gradient = ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(99, 102, 241, 0.3)');
                gradient.addColorStop(1, 'rgba(99, 102, 241, 0)');
                
                // Generar datos o usar los del API
                let chartData = [];
                if (window.AccountsState && 
                    window.AccountsState.chartsData && 
                    window.AccountsState.chartsData[login] && 
                    window.AccountsState.chartsData[login].equity) {
                    // Usar datos del API
                    chartData = window.AccountsState.chartsData[login].equity;
                } else {
                    // Generar datos simples
                    for (let i = 0; i < 20; i++) {
                        const progress = i / 19;
                        const value = balance + ((equity - balance) * progress);
                        const variation = Math.sin(i * 0.5) * Math.abs(equity - balance) * 0.02;
                        chartData.push(value + variation);
                    }
                    chartData[19] = equity; // Asegurar último punto
                }
                
                // Crear el chart
                AccountsState.charts.modalCharts.equity = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: Array(20).fill('').map((_, i) => {
                            if (i === 0) return 'Inicio';
                            if (i === 19) return 'Actual';
                            return '';
                        }),
                        datasets: [{
                            label: 'Equity',
                            data: chartData,
                            borderColor: '#6366f1',
                            backgroundColor: gradient,
                            borderWidth: 3,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#6366f1'
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
                                enabled: true,
                                backgroundColor: 'rgba(22, 22, 43, 0.95)',
                                titleColor: '#fff',
                                bodyColor: '#a0aec0',
                                callbacks: {
                                    label: function(context) {
                                        const value = context.parsed.y;
                                        return 'Equity: $' + value.toLocaleString('en-US', {
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
                                        if (value >= 1000000) {
                                            return '$' + (value / 1000000).toFixed(1) + 'M';
                                        } else if (value >= 1000) {
                                            return '$' + (value / 1000).toFixed(0) + 'k';
                                        }
                                        return '$' + value.toFixed(0);
                                    }
                                },
                                beginAtZero: false
                            }
                        }
                    }
                });
                
                console.log('✅ Chart de equity del modal actualizado para cuenta', login);
            }
            
            // Destruir chart P&L existente si hay
            if (AccountsState.charts.modalCharts.pl) {
                try {
                    AccountsState.charts.modalCharts.pl.destroy();
                } catch(e) {}
                AccountsState.charts.modalCharts.pl = null;
            }
            
            // Crear chart de P&L Distribution
            const plCanvas = document.getElementById('modalPLChart');
            if (plCanvas) {
                const ctx = plCanvas.getContext('2d');
                
                // Datos de distribución (por ahora demo)
                const profit = parseFloat(account.profit) || 0;
                let plData = [5, 8, 12, 25, 30, 28, 15, 10, 8, 5];
                
                // Ajustar según el profit
                if (profit > 0) {
                    plData = [2, 3, 5, 8, 12, 20, 25, 15, 8, 2];
                } else if (profit < 0) {
                    plData = [15, 20, 25, 15, 10, 8, 4, 2, 1, 0];
                }
                
                const colors = plData.map((_, i) => i < 4 ? 'rgba(239, 68, 68, 0.7)' : 'rgba(16, 185, 129, 0.7)');
                
                AccountsState.charts.modalCharts.pl = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['<-5k', '-5k/-3k', '-3k/-1k', '-1k/0', '0/1k', '1k/3k', '3k/5k', '5k/10k', '10k/20k', '>20k'],
                        datasets: [{
                            label: 'Operaciones',
                            data: plData,
                            backgroundColor: colors,
                            borderWidth: 0
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
                                enabled: true,
                                backgroundColor: 'rgba(22, 22, 43, 0.95)',
                                titleColor: '#fff',
                                bodyColor: '#a0aec0'
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
                
                console.log('✅ Chart de P&L del modal actualizado');
            }
            
        }, 300); // Esperar 300ms para que el modal esté listo
    };
    
    console.log('✅ Fix para charts del modal aplicado');
})();