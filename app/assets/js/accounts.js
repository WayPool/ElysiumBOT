//+------------------------------------------------------------------+
//| Copyright 2025, Elysium Media FZCO                              |
//| https://www.elysiumdubai.net                                    |
//| Gestión de cuentas con charts históricos completos              |
//| Versión 7.0 - Sistema de Reportes de Trading                    |
//+------------------------------------------------------------------+

// ============================================
// ESTADO GLOBAL DE CUENTAS
// ============================================
const AccountsState = {
    accounts: [],
    filteredAccounts: [],
    currentPage: 1,
    accountsPerPage: 9, // 3x3 grid
    sortBy: 'equity',
    sortOrder: 'desc',
    filterBy: 'all',
    searchTerm: '',
    currentAccount: null,
    updateInterval: null,
    isLoading: false,
    chartsData: {},
    charts: {
        miniCharts: {},
        modalCharts: {
            equity: null,
            pl: null,
            volume: null
        }
    }
};

// ============================================
// INICIALIZACIÓN
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Iniciando módulo de cuentas v7.0');
    
    // Ocultar loading inicial si existe
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
    }
    
    // Cargar datos iniciales
    loadAccountsData();
    
    // Configurar actualizaciones automáticas
    AccountsState.updateInterval = setInterval(() => {
        loadAccountsData(false);
    }, 30000); // Actualizar cada 30 segundos
    
    // Event listeners
    setupEventListeners();
});

// ============================================
// CARGA DE DATOS PRINCIPAL
// ============================================
async function loadAccountsData(showLoading = true) {
    if (AccountsState.isLoading) return;
    
    try {
        AccountsState.isLoading = true;
        
        if (showLoading) {
            showLoadingState();
        }
        
        // Cargar datos de cuentas
        const response = await fetch('api/get_accounts_data.php');
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Guardar datos
            AccountsState.accounts = data.accounts || [];
            AccountsState.filteredAccounts = [...AccountsState.accounts];
            
            console.log('✅ Datos de cuentas cargados:', AccountsState.accounts.length, 'cuentas');
            
            // Actualizar estadísticas
            if (data.statistics) {
                updateStatistics(data.statistics);
            }
            
            // Actualizar filtro de brokers
            if (data.brokers) {
                updateBrokerFilter(data.brokers);
            }
            
            // Aplicar filtros y ordenamiento
            applyFiltersAndSort();
            
            // IMPORTANTE: Renderizar las cuentas inmediatamente
            renderAccountsGrid();
            
            // Cargar datos de charts en segundo plano (sin await)
            loadChartsData();
            
        } else {
            throw new Error(data.error || 'Error al cargar datos');
        }
        
    } catch (error) {
        console.error('❌ Error cargando cuentas:', error);
        showErrorMessage('Error al cargar datos de cuentas: ' + error.message);
    } finally {
        AccountsState.isLoading = false;
    }
}

// ============================================
// RENDERIZAR GRID DE CUENTAS
// ============================================
function renderAccountsGrid() {
    const grid = document.getElementById('accounts-grid');
    if (!grid) {
        console.error('❌ No se encontró elemento accounts-grid');
        return;
    }
    
    // Calcular paginación
    const start = (AccountsState.currentPage - 1) * AccountsState.accountsPerPage;
    const end = start + AccountsState.accountsPerPage;
    const pageAccounts = AccountsState.filteredAccounts.slice(start, end);
    
    console.log('📄 Renderizando página', AccountsState.currentPage, 'con', pageAccounts.length, 'cuentas');
    
    // Si no hay cuentas
    if (pageAccounts.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <span class="material-icons-outlined">inbox</span>
                <h3>No se encontraron cuentas</h3>
                <p>Intenta ajustar los filtros o actualizar la página</p>
                <button onclick="refreshAccounts()" class="btn btn-primary">
                    <span class="material-icons-outlined">refresh</span>
                    Actualizar
                </button>
            </div>
        `;
        updatePagination();
        return;
    }
    
    // Renderizar cards de cuentas
    let html = '';
    pageAccounts.forEach(account => {
        html += createAccountCard(account);
    });
    
    grid.innerHTML = html;
    
    // Actualizar mini charts después de renderizar
    setTimeout(() => {
        updateVisibleMiniCharts();
    }, 100);
    
    // Actualizar paginación
    updatePagination();
    
    // Actualizar info de paginación
    updatePaginationInfo();
    
    console.log('✅ Grid renderizado correctamente');
}

// ============================================
// CREAR CARD DE CUENTA
// ============================================
function createAccountCard(account) {
    const hoursSinceUpdate = (Date.now() - new Date(account.last_update)) / 3600000;
    const isActive = hoursSinceUpdate < 24;
    const statusClass = isActive ? 'active' : 'inactive';
    const statusText = isActive ? 'Activa' : 'Inactiva';
    
    const marginLevel = parseFloat(account.margin_level) || 0;
    const marginClass = marginLevel < 200 ? 'danger' : 
                       marginLevel < 500 ? 'warning' : 
                       'success';
    
    // P&L Flotante (posiciones abiertas)
    const floatingProfit = parseFloat(account.floating_profit) || 0;
    const floatingClass = floatingProfit >= 0 ? 'positive' : 'negative';
    const floatingDisplay = floatingProfit >= 0 ? 
        `+${formatCurrency(floatingProfit)}` : 
        formatCurrency(floatingProfit);
    
    // P&L Total (rendimiento sobre capital depositado)
    const totalPL = parseFloat(account.profit) || 0; // Este es el P&L real calculado
    const totalPLPercentage = parseFloat(account.profit_percentage) || 0;
    const totalPLClass = totalPL >= 0 ? 'positive' : 'negative';
    const totalPLDisplay = totalPL >= 0 ? 
        `+${formatCurrency(totalPL)}` : 
        formatCurrency(totalPL);
    const totalPLPercentDisplay = totalPLPercentage >= 0 ?
        `+${formatNumber(totalPLPercentage, 2)}%` :
        `${formatNumber(totalPLPercentage, 2)}%`;
    
    // Información de depósitos para tooltip
    const totalDeposits = parseFloat(account.total_deposits) || 0;
    const totalWithdrawals = parseFloat(account.total_withdrawals) || 0;
    const netDeposits = parseFloat(account.net_deposits) || 0;
    const depositCount = account.deposit_count || 0;
    const withdrawalCount = account.withdrawal_count || 0;
    
    const depositsTooltip = `Capital Neto: ${formatCurrency(netDeposits)}
Depósitos (${depositCount}): ${formatCurrency(totalDeposits)}
Retiros (${withdrawalCount}): ${formatCurrency(totalWithdrawals)}`;
    
    return `
        <div class="account-card" onclick="openAccountModal('${account.login}')">
            <div class="account-card-header">
                <div class="account-info">
                    <h3>${account.login}</h3>
                    <span class="account-name">${account.name || 'Sin nombre'}</span>
                </div>
                <span class="status-badge ${statusClass}">${statusText}</span>
            </div>
            
            <div class="account-card-body">
                <div class="account-metrics">
                    <div class="metric">
                        <span class="metric-label">Balance</span>
                        <span class="metric-value">${formatCurrency(account.balance)}</span>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Equity</span>
                        <span class="metric-value">${formatCurrency(account.equity)}</span>
                    </div>
                    <div class="metric metric-double">
                        <div class="metric-row">
                            <span class="metric-label">P&L Flotante</span>
                            <span class="metric-value ${floatingClass}">${floatingDisplay}</span>
                        </div>
                        <div class="metric-row">
                            <span class="metric-label">P&L Total</span>
                            <span class="metric-value ${totalPLClass}">
                                ${totalPLDisplay}
                                <span style="font-size: 0.75em; opacity: 0.9; margin-left: 4px;">
                                    (${totalPLPercentDisplay})
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="metric">
                        <span class="metric-label">Margen</span>
                        <span class="metric-value ${marginClass}">${formatNumber(marginLevel)}%</span>
                    </div>
                </div>
                
                <div class="mini-chart-container">
                    <canvas id="miniChart-${account.login}"></canvas>
                </div>
                
                <div class="account-card-footer">
                    <span class="broker">${account.company || 'N/A'}</span>
                    <span class="positions">${account.open_positions || 0} posiciones</span>
                    ${netDeposits ? 
                        `<span class="deposits-info" title="${depositsTooltip}">
                            <span class="material-icons-outlined" style="font-size: 14px;">account_balance</span>
                            ${formatCurrency(netDeposits)}
                        </span>` : ''
                    }
                </div>
            </div>
        </div>
    `;
}

// ============================================
// CARGA DE DATOS DE CHARTS
// ============================================
async function loadChartsData() {
    try {
        console.log('📊 Cargando datos históricos de charts...');
        
        const response = await fetch('api/get_account_chart_data.php');
        const data = await response.json();
        
        if (data.success && data.charts_data) {
            AccountsState.chartsData = data.charts_data;
            
            console.log('✅ Datos de charts cargados:', Object.keys(data.charts_data).length, 'cuentas');
            if (data.metadata) {
                console.log('📈 Total de puntos de datos:', data.metadata.total_data_points);
            }
            
            // Actualizar mini charts de las cuentas visibles
            updateVisibleMiniCharts();
            
            // Si hay un modal abierto, actualizar sus charts
            if (AccountsState.currentAccount) {
                updateModalCharts(AccountsState.currentAccount.login);
            }
        }
    } catch (error) {
        console.error('❌ Error cargando datos de charts:', error);
        // No es crítico, continuar sin charts
    }
}

// ============================================
// ACTUALIZAR MINI CHARTS VISIBLES
// ============================================
function updateVisibleMiniCharts() {
    const start = (AccountsState.currentPage - 1) * AccountsState.accountsPerPage;
    const end = start + AccountsState.accountsPerPage;
    const pageAccounts = AccountsState.filteredAccounts.slice(start, end);
    
    pageAccounts.forEach(account => {
        const login = String(account.login);
        if (AccountsState.chartsData[login]) {
            updateMiniChart(login, AccountsState.chartsData[login]);
        } else {
            // Si no hay datos del API, crear chart simple
            createSimpleMiniChart(login, account);
        }
    });
}

// ============================================
// ACTUALIZAR MINI CHART INDIVIDUAL
// ============================================
function updateMiniChart(login, chartData) {
    const canvas = document.getElementById(`miniChart-${login}`);
    if (!canvas) return;
    
    // Destruir chart existente
    if (AccountsState.charts.miniCharts[login]) {
        try {
            AccountsState.charts.miniCharts[login].destroy();
        } catch (e) {}
        delete AccountsState.charts.miniCharts[login];
    }
    
    const ctx = canvas.getContext('2d');
    
    // Preparar datos
    const equityData = chartData.equity || [];
    const dates = chartData.dates || [];
    
    // Simplificar si hay muchos puntos
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
    
    // Determinar color basado en tendencia
    const isPositive = displayData.length > 1 && 
                      displayData[displayData.length-1] >= displayData[0];
    
    // Crear gradiente
    const gradient = ctx.createLinearGradient(0, 0, 0, 60);
    if (isPositive) {
        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
        gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
    } else {
        gradient.addColorStop(0, 'rgba(239, 68, 68, 0.3)');
        gradient.addColorStop(1, 'rgba(239, 68, 68, 0)');
    }
    
    // Crear chart
    AccountsState.charts.miniCharts[login] = new Chart(ctx, {
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
                pointHoverRadius: 3,
                pointBackgroundColor: isPositive ? '#10b981' : '#ef4444',
                pointBorderColor: '#fff',
                pointBorderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 750
            },
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
                        title: function(context) {
                            return context[0].label || '';
                        },
                        label: function(context) {
                            return formatCurrency(context.parsed.y);
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
                    grid: { display: false }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            layout: {
                padding: 0
            }
        }
    });
}

// ============================================
// CREAR MINI CHART SIMPLE (FALLBACK)
// ============================================
function createSimpleMiniChart(login, account) {
    const canvas = document.getElementById(`miniChart-${login}`);
    if (!canvas) return;
    
    const equity = parseFloat(account.equity) || 0;
    const balance = parseFloat(account.balance) || 0;
    
    // Generar datos simples
    const points = 20;
    const data = [];
    
    for (let i = 0; i < points; i++) {
        const progress = i / (points - 1);
        const value = balance + ((equity - balance) * progress);
        // Añadir variación aleatoria pequeña
        const variation = Math.sin(i * 0.5) * Math.abs(equity - balance) * 0.02;
        data.push(value + variation);
    }
    data[points - 1] = equity; // Asegurar último punto
    
    updateMiniChart(login, {
        equity: data,
        dates: Array(points).fill('')
    });
}

// ============================================
// FUNCIONES DE UTILIDAD
// ============================================
function formatCurrency(value, decimals = 2) {
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

function formatNumber(value, decimals = 2) {
    const num = parseFloat(value) || 0;
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(num);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// ============================================
// ESTADOS DE CARGA
// ============================================
function showLoadingState() {
    const grid = document.getElementById('accounts-grid');
    if (grid) {
        grid.innerHTML = `
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <span>Cargando datos de cuentas...</span>
            </div>
        `;
    }
}

function showErrorMessage(message) {
    const grid = document.getElementById('accounts-grid');
    if (grid) {
        grid.innerHTML = `
            <div class="error-state">
                <span class="material-icons-outlined">error</span>
                <h3>Error al cargar datos</h3>
                <p>${message}</p>
                <button onclick="refreshAccounts()" class="btn btn-primary">
                    <span class="material-icons-outlined">refresh</span>
                    Reintentar
                </button>
            </div>
        `;
    }
}

// ============================================
// ACTUALIZAR ESTADÍSTICAS
// ============================================
function updateStatistics(stats) {
    if (!stats) return;
    
    const updateElement = (id, value) => {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    };
    
    // Actualizar contadores principales
    updateElement('total-accounts', stats.total_accounts || 0);
    updateElement('total-equity', formatCurrency(stats.total_equity || 0));
    updateElement('total-balance', formatCurrency(stats.total_balance || 0));
    
    // Actualizar badges de estado
    updateElement('active-count', stats.active_accounts || 0);
    updateElement('warning-count', stats.warning_accounts || 0);
    updateElement('critical-count', stats.critical_accounts || 0);
    
    // Actualizar paginación
    updateElement('accounts-total', stats.total_accounts || 0);
}

// ============================================
// ACTUALIZAR FILTRO DE BROKERS
// ============================================
function updateBrokerFilter(brokers) {
    const select = document.getElementById('filter-broker');
    if (!select) return;
    
    const currentValue = select.value;
    select.innerHTML = '<option value="all">Todos</option>';
    
    brokers.forEach(broker => {
        if (broker) {
            const option = document.createElement('option');
            option.value = broker;
            option.textContent = broker;
            select.appendChild(option);
        }
    });
    
    // Restaurar valor si existe
    if (currentValue && Array.from(select.options).some(opt => opt.value === currentValue)) {
        select.value = currentValue;
    }
}

// ============================================
// PAGINACIÓN
// ============================================
function updatePagination() {
    const totalPages = Math.ceil(AccountsState.filteredAccounts.length / AccountsState.accountsPerPage) || 1;
    
    // Actualizar números de página
    const pageNumbers = document.getElementById('page-numbers');
    if (pageNumbers) {
        let html = '';
        
        // Mostrar máximo 5 páginas
        let startPage = Math.max(1, AccountsState.currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="page-number ${i === AccountsState.currentPage ? 'active' : ''}" 
                     onclick="changePage(${i})">${i}</button>`;
        }
        
        pageNumbers.innerHTML = html;
    }
    
    // Actualizar botones de navegación
    const updateButton = (selector, disabled) => {
        const btn = document.querySelector(selector);
        if (btn) {
            btn.disabled = disabled;
            if (disabled) {
                btn.classList.add('disabled');
            } else {
                btn.classList.remove('disabled');
            }
        }
    };
    
    updateButton('[onclick="goToPage(\'first\')"]', AccountsState.currentPage === 1);
    updateButton('[onclick="goToPage(\'prev\')"]', AccountsState.currentPage === 1);
    updateButton('[onclick="goToPage(\'next\')"]', AccountsState.currentPage >= totalPages);
    updateButton('[onclick="goToPage(\'last\')"]', AccountsState.currentPage >= totalPages);
}

function updatePaginationInfo() {
    const start = (AccountsState.currentPage - 1) * AccountsState.accountsPerPage + 1;
    const end = Math.min(start + AccountsState.accountsPerPage - 1, AccountsState.filteredAccounts.length);
    
    const showingElement = document.getElementById('accounts-showing');
    if (showingElement) {
        showingElement.textContent = AccountsState.filteredAccounts.length > 0 ? 
            `${start}-${end}` : '0-0';
    }
    
    const totalElement = document.getElementById('accounts-total');
    if (totalElement) {
        totalElement.textContent = AccountsState.filteredAccounts.length;
    }
}

// ============================================
// FILTROS Y ORDENAMIENTO
// ============================================
function applyFilters() {
    const status = document.getElementById('filter-status')?.value || 'all';
    const broker = document.getElementById('filter-broker')?.value || 'all';
    const currency = document.getElementById('filter-currency')?.value || 'all';
    const search = document.getElementById('accountSearch')?.value.toLowerCase() || '';
    
    AccountsState.filteredAccounts = AccountsState.accounts.filter(account => {
        // Filtro por estado
        if (status !== 'all') {
            const hoursSinceUpdate = (Date.now() - new Date(account.last_update)) / 3600000;
            if (status === 'active' && hoursSinceUpdate > 24) return false;
            if (status === 'inactive' && hoursSinceUpdate <= 24) return false;
            if (status === 'warning') {
                const margin = parseFloat(account.margin_level) || 0;
                if (margin >= 500 || margin < 200) return false;
            }
        }
        
        // Filtro por broker
        if (broker !== 'all' && account.company !== broker) return false;
        
        // Filtro por moneda
        if (currency !== 'all' && account.currency !== currency) return false;
        
        // Filtro por búsqueda
        if (search) {
            const searchableText = `${account.login} ${account.name || ''} ${account.company || ''}`.toLowerCase();
            if (!searchableText.includes(search)) return false;
        }
        
        return true;
    });
    
    AccountsState.currentPage = 1;
    renderAccountsGrid();
}

function applySorting() {
    const sortBy = document.getElementById('sort-by')?.value || 'equity_desc';
    
    AccountsState.filteredAccounts.sort((a, b) => {
        const aEquity = parseFloat(a.equity) || 0;
        const bEquity = parseFloat(b.equity) || 0;
        const aBalance = parseFloat(a.balance) || 0;
        const bBalance = parseFloat(b.balance) || 0;
        const aProfit = parseFloat(a.profit) || 0;
        const bProfit = parseFloat(b.profit) || 0;
        
        switch(sortBy) {
            case 'equity_desc':
                return bEquity - aEquity;
            case 'equity_asc':
                return aEquity - bEquity;
            case 'profit_desc':
                return bProfit - aProfit;
            case 'profit_asc':
                return aProfit - bProfit;
            case 'recent':
                return new Date(b.last_update) - new Date(a.last_update);
            default:
                return bEquity - aEquity;
        }
    });
    
    renderAccountsGrid();
}

function applyFiltersAndSort() {
    // Aplicar ordenamiento por defecto
    const sortBy = document.getElementById('sort-by')?.value || 'equity_desc';
    
    AccountsState.filteredAccounts.sort((a, b) => {
        const aEquity = parseFloat(a.equity) || 0;
        const bEquity = parseFloat(b.equity) || 0;
        const aProfit = parseFloat(a.profit) || 0;
        const bProfit = parseFloat(b.profit) || 0;
        
        switch(sortBy) {
            case 'equity_desc':
                return bEquity - aEquity;
            case 'equity_asc':
                return aEquity - bEquity;
            case 'profit_desc':
                return bProfit - aProfit;
            case 'profit_asc':
                return aProfit - bProfit;
            case 'recent':
                return new Date(b.last_update) - new Date(a.last_update);
            default:
                return bEquity - aEquity;
        }
    });
}

// ============================================
// EVENT LISTENERS
// ============================================
function setupEventListeners() {
    // Filtros
    document.getElementById('filter-status')?.addEventListener('change', applyFilters);
    document.getElementById('filter-broker')?.addEventListener('change', applyFilters);
    document.getElementById('filter-currency')?.addEventListener('change', applyFilters);
    document.getElementById('sort-by')?.addEventListener('change', applySorting);
    
    // Búsqueda
    document.getElementById('accountSearch')?.addEventListener('input', debounce(applyFilters, 300));
    
    // Cerrar modal
    document.querySelector('.modal-close')?.addEventListener('click', closeAccountModal);
    
    // Click fuera del modal
    document.getElementById('accountModal')?.addEventListener('click', (e) => {
        if (e.target.id === 'accountModal') {
            closeAccountModal();
        }
    });
}

// ============================================
// FUNCIONES GLOBALES
// ============================================
window.openAccountModal = openAccountModal;
window.closeAccountModal = closeAccountModal;
window.refreshAccounts = () => loadAccountsData(true);
window.changePage = changePage;
window.goToPage = goToPage;

function changePage(page) {
    const totalPages = Math.ceil(AccountsState.filteredAccounts.length / AccountsState.accountsPerPage) || 1;
    
    if (page < 1 || page > totalPages) return;
    
    AccountsState.currentPage = page;
    renderAccountsGrid();
}

function goToPage(direction) {
    const totalPages = Math.ceil(AccountsState.filteredAccounts.length / AccountsState.accountsPerPage) || 1;
    
    switch(direction) {
        case 'first':
            AccountsState.currentPage = 1;
            break;
        case 'prev':
            AccountsState.currentPage = Math.max(1, AccountsState.currentPage - 1);
            break;
        case 'next':
            AccountsState.currentPage = Math.min(totalPages, AccountsState.currentPage + 1);
            break;
        case 'last':
            AccountsState.currentPage = totalPages;
            break;
    }
    
    renderAccountsGrid();
}

// ============================================
// MODAL DE CUENTA
// ============================================
async function openAccountModal(login) {
    console.log('Abriendo modal para cuenta:', login);
    
    const account = AccountsState.accounts.find(a => a.login == login);
    if (!account) {
        console.error('Cuenta no encontrada:', login);
        return;
    }
    
    AccountsState.currentAccount = account;
    
    // Obtener el modal y mostrarlo
    const modal = document.getElementById('accountModal');
    if (modal) {
        modal.classList.add('show');
        console.log('Modal mostrado');
    } else {
        console.error('Modal no encontrado en el DOM');
        return;
    }
    
    // Actualizar información básica del modal
    updateModalOverview(account);
    
    // Cargar datos históricos si no están disponibles
    if (!AccountsState.chartsData[login]) {
        console.log('📊 Cargando datos históricos para cuenta', login);
        try {
            const response = await fetch(`api/get_account_chart_data.php?login=${login}`);
            const data = await response.json();
            
            if (data.success && data.charts_data[login]) {
                AccountsState.chartsData[login] = data.charts_data[login];
                console.log('✅ Datos históricos cargados:', data.charts_data[login].points, 'puntos');
            }
        } catch (error) {
            console.error('❌ Error cargando datos históricos:', error);
        }
    }
    
    // Inicializar charts del modal
    setTimeout(() => {
        initializeModalCharts(account);
    }, 100);
}

function closeAccountModal() {
    console.log('Cerrando modal');
    const modal = document.getElementById('accountModal');
    if (modal) {
        modal.classList.remove('show');
    }
    
    // Limpiar charts si existen
    if (AccountsState.charts.modalCharts.equity) {
        try {
            AccountsState.charts.modalCharts.equity.destroy();
        } catch(e) {}
        AccountsState.charts.modalCharts.equity = null;
    }
    if (AccountsState.charts.modalCharts.pl) {
        try {
            AccountsState.charts.modalCharts.pl.destroy();
        } catch(e) {}
        AccountsState.charts.modalCharts.pl = null;
    }
    
    AccountsState.currentAccount = null;
}

// Función para cambiar tabs en el modal
window.switchTab = function(tabName) {
    // Ocultar todos los tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Remover active de todos los tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Mostrar el tab seleccionado
    const selectedContent = document.getElementById(`tab-${tabName}`);
    if (selectedContent) {
        selectedContent.classList.add('active');
    }
    
    // Marcar el tab como activo
    event.target.closest('.tab').classList.add('active');
}

function updateModalOverview(account) {
    console.log('Actualizando modal con datos de cuenta:', account);
    
    // Función helper para actualizar elementos
    const updateElement = (id, value, isHTML = false) => {
        const element = document.getElementById(id);
        if (element) {
            if (isHTML) {
                element.innerHTML = value;
            } else {
                element.textContent = value;
            }
        } else {
            console.warn(`Elemento no encontrado: ${id}`);
        }
    };
    
    // Actualizar título del modal
    updateElement('modal-account-title', `Cuenta #${account.login}`);
    
    // Actualizar estado
    const statusEl = document.getElementById('modal-account-status');
    if (statusEl) {
        const hoursSinceUpdate = (Date.now() - new Date(account.last_update)) / 3600000;
        const isActive = hoursSinceUpdate < 24;
        statusEl.textContent = isActive ? 'ACTIVA' : 'INACTIVA';
        statusEl.className = isActive ? 'account-status active' : 'account-status inactive';
    }
    
    // Summary Cards - Tab Overview
    updateElement('modal-equity', formatCurrency(account.equity));
    updateElement('modal-balance', formatCurrency(account.balance));
    
    // P&L con ambos valores
    const floatingProfit = parseFloat(account.floating_profit) || 0;
    const totalPL = parseFloat(account.profit) || 0;
    const plElement = document.getElementById('modal-profit');
    if (plElement) {
        plElement.innerHTML = `
            <div style="font-size: 0.8em; color: #718096; margin-bottom: 3px;">Flotante: ${formatCurrency(floatingProfit)}</div>
            <div>${formatCurrency(totalPL)}</div>
        `;
    }
    
    updateElement('modal-margin-level', formatNumber(account.margin_level) + '%');
    
    // Información de Cuenta
    updateElement('modal-login', account.login);
    updateElement('modal-name', account.name || 'Sin nombre');
    updateElement('modal-server', account.server || '-');
    updateElement('modal-company', account.company || '-');
    updateElement('modal-currency', account.currency || 'USD');
    updateElement('modal-leverage', '1:' + (account.leverage || 100));
    
    // Estado Financiero
    updateElement('modal-balance-detail', formatCurrency(account.balance));
    updateElement('modal-equity-detail', formatCurrency(account.equity));
    updateElement('modal-margin', formatCurrency(account.margin));
    updateElement('modal-margin-free', formatCurrency(account.margin_free));
    updateElement('modal-credit', formatCurrency(account.credit));
    
    // P&L detallado
    const profitDetailEl = document.getElementById('modal-profit-detail');
    if (profitDetailEl) {
        const profitPercentage = parseFloat(account.profit_percentage) || 0;
        profitDetailEl.innerHTML = `
            ${formatCurrency(totalPL)} 
            <span style="font-size: 0.85em; color: ${totalPL >= 0 ? '#10b981' : '#ef4444'};">
                (${profitPercentage >= 0 ? '+' : ''}${formatNumber(profitPercentage, 2)}%)
            </span>
        `;
    }
    
    // Información Temporal
	updateElement('modal-last-update', formatDate(account.last_update));
	updateElement('modal-first-trade', formatDate(account.first_trade_date || account.first_operation_date));
	updateElement('modal-last-trade', formatDate(account.last_trade_date || account.last_operation_date));
    
    // Calcular días activo
    if (account.first_trade_date) {
        const firstDate = new Date(account.first_trade_date);
        const today = new Date();
        const daysActive = Math.floor((today - firstDate) / (1000 * 60 * 60 * 24));
        updateElement('modal-days-active', daysActive);
    } else {
        updateElement('modal-days-active', '0');
    }
    
    // Estado EA
    const eaStatusEl = document.getElementById('modal-ea-status');
    if (eaStatusEl) {
        const lastEAUpdate = account.last_ea_timestamp ? 
            new Date(account.last_ea_timestamp * 1000) : null;
        const eaActive = lastEAUpdate && 
            ((Date.now() - lastEAUpdate) / 3600000) < 1;
        
        eaStatusEl.textContent = eaActive ? 'Activo' : 'Inactivo';
        eaStatusEl.style.color = eaActive ? '#10b981' : '#ef4444';
    }
    
    updateElement('modal-ea-timestamp', account.last_ea_timestamp ? 
        new Date(account.last_ea_timestamp * 1000).toLocaleString() : '-');
    
    // Información adicional si existe
    if (account.total_trades) {
        const winRate = parseFloat(account.win_rate) || 0;
        const statsContainer = document.querySelector('.stats-grid');
        if (statsContainer) {
            statsContainer.innerHTML = `
                <div class="stat-card">
                    <div class="stat-label">Total Trades</div>
                    <div class="stat-value">${account.total_trades}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Win Rate</div>
                    <div class="stat-value">${formatNumber(winRate, 2)}%</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Ganadas</div>
                    <div class="stat-value" style="color: #10b981;">${account.winning_trades}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Perdidas</div>
                    <div class="stat-value" style="color: #ef4444;">${account.losing_trades}</div>
                </div>
            `;
        }
    }
    
    console.log('✅ Modal actualizado correctamente');
}

function initializeModalCharts(account) {
    // Implementar charts del modal si es necesario
    console.log('Inicializando charts del modal para', account.login);
}

function updateModalCharts(login) {
    // Actualizar charts del modal si es necesario
    console.log('Actualizando charts del modal para', login);
}

// ============================================
// UTILIDAD: DEBOUNCE
// ============================================
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

// ============================================
// CLEANUP
// ============================================
window.addEventListener('beforeunload', () => {
    if (AccountsState.updateInterval) {
        clearInterval(AccountsState.updateInterval);
    }
    
    // Destruir todos los charts
    Object.values(AccountsState.charts.miniCharts).forEach(chart => {
        try { chart.destroy(); } catch(e) {}
    });
    
    if (AccountsState.charts.modalCharts.equity) {
        try { AccountsState.charts.modalCharts.equity.destroy(); } catch(e) {}
    }
    if (AccountsState.charts.modalCharts.pl) {
        try { AccountsState.charts.modalCharts.pl.destroy(); } catch(e) {}
    }
});

console.log('✅ Módulo de cuentas v7.0 cargado correctamente');