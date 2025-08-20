/**
 * API Module - Elysium v7.0
 * Gestión de comunicación con el servidor
 * Copyright 2025, Elysium Media FZCO
 */

// ============================================
// CONFIGURACIÓN DE API
// ============================================
const API = {
    baseURL: window.location.origin + window.location.pathname.replace('index.php', ''),
    endpoints: {
        dashboard: 'api/get_dashboard_data.php',
        positions: 'api/get_positions.php',
        history: 'api/get_history.php',
        accounts: 'api/get_accounts.php',
        sendHistory: 'api/send_full_history.php'
    },
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Cache-Control': 'no-cache'
    },
    timeout: 10000
};

// ============================================
// FUNCIONES DE API
// ============================================

/**
 * Realizar petición GET
 */
async function apiGet(endpoint, params = {}) {
    try {
        const url = new URL(API.baseURL + endpoint);
        Object.keys(params).forEach(key => url.searchParams.append(key, params[key]));
        
        const response = await fetch(url, {
            method: 'GET',
            headers: API.headers,
            signal: AbortSignal.timeout(API.timeout)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        return data;
        
    } catch (error) {
        console.error('API GET Error:', error);
        throw error;
    }
}

/**
 * Realizar petición POST
 */
async function apiPost(endpoint, data = {}) {
    try {
        const url = API.baseURL + endpoint;
        
        const response = await fetch(url, {
            method: 'POST',
            headers: API.headers,
            body: JSON.stringify(data),
            signal: AbortSignal.timeout(API.timeout)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const responseData = await response.json();
        return responseData;
        
    } catch (error) {
        console.error('API POST Error:', error);
        throw error;
    }
}

/**
 * Obtener datos del dashboard
 */
async function getDashboardData(filters = {}) {
    return apiGet(API.endpoints.dashboard, filters);
}

/**
 * Obtener posiciones
 */
async function getPositions(filters = {}) {
    return apiGet(API.endpoints.positions, filters);
}

/**
 * Obtener historial
 */
async function getHistory(filters = {}) {
    return apiGet(API.endpoints.history, filters);
}

/**
 * Obtener cuentas
 */
async function getAccounts() {
    return apiGet(API.endpoints.accounts);
}

/**
 * Enviar señal para histórico completo
 */
async function triggerFullHistory() {
    return apiPost(API.endpoints.sendHistory, {
        action: 'full_sync',
        timestamp: Date.now()
    });
}

// ============================================
// FUNCIONES DE UTILIDAD
// ============================================

/**
 * Verificar conexión con el servidor
 */
async function checkConnection() {
    try {
        const response = await fetch(API.baseURL + API.endpoints.dashboard + '?check=1', {
            method: 'HEAD',
            signal: AbortSignal.timeout(3000)
        });
        return response.ok;
    } catch {
        return false;
    }
}

/**
 * Reintentar petición con backoff exponencial
 */
async function retryWithBackoff(fn, maxRetries = 3, delay = 1000) {
    for (let i = 0; i < maxRetries; i++) {
        try {
            return await fn();
        } catch (error) {
            if (i === maxRetries - 1) throw error;
            await new Promise(resolve => setTimeout(resolve, delay * Math.pow(2, i)));
        }
    }
}

// ============================================
// EXPORTAR API
// ============================================
window.ElysiumAPI = {
    get: apiGet,
    post: apiPost,
    getDashboardData,
    getPositions,
    getHistory,
    getAccounts,
    triggerFullHistory,
    checkConnection,
    retryWithBackoff
};

console.log('✅ API Module v7.0 cargado');