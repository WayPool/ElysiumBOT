/**
 * system-status.js
 * Versión corregida que busca la última actualización en las cuentas
 * Copyright 2025, Elysium Media FZCO
 */

(function() {
    'use strict';
    
    // Variable para almacenar la última actualización conocida
    let lastKnownUpdate = null;
    
    // Función para actualizar el estado del sistema
    async function updateSystemStatus() {
        try {
            // Verificar API
            const apiStatus = await checkAPIStatus();
            updateStatusIndicator('api-status', apiStatus.online, apiStatus.text);
            
            // Verificar Base de Datos
            const dbStatus = await checkDatabaseStatus();
            updateStatusIndicator('db-status', dbStatus.online, dbStatus.text);
            
            // Verificar EA Reporter - CORREGIDO
            const eaStatus = await checkEAStatus();
            updateStatusIndicator('ea-status', eaStatus.online, eaStatus.text);
            
        } catch (error) {
            console.error('Error actualizando estado del sistema:', error);
            updateStatusIndicator('api-status', false, 'Error');
            updateStatusIndicator('db-status', false, 'Error');
            updateStatusIndicator('ea-status', false, 'Error');
        }
    }
    
    // Verificar estado del API
    async function checkAPIStatus() {
        try {
            const response = await fetch('api/get_dashboard_data.php?period=RT', {
                method: 'GET',
                cache: 'no-cache'
            });
            
            if (response.ok) {
                const data = await response.json();
                return {
                    online: data.success === true,
                    text: data.success ? 'Online' : 'Error'
                };
            }
            return { online: false, text: 'Offline' };
        } catch (error) {
            return { online: false, text: 'Sin conexión' };
        }
    }
    
    // Verificar estado de la base de datos
    async function checkDatabaseStatus() {
        try {
            const response = await fetch('api/get_dashboard_data.php?period=RT', {
                cache: 'no-cache'
            });
            const data = await response.json();
            
            if (data.success && data.data) {
                return { online: true, text: 'Conectado' };
            }
            return { online: false, text: 'Desconectado' };
        } catch (error) {
            return { online: false, text: 'Error' };
        }
    }
    
    // Verificar estado del EA Reporter - VERSIÓN CORREGIDA
    async function checkEAStatus() {
        try {
            const response = await fetch('api/get_dashboard_data.php?period=RT', {
                cache: 'no-cache'
            });
            const data = await response.json();
            
            if (data.success && data.data) {
                let mostRecentUpdate = null;
                let updateSource = '';
                
                // Buscar la última actualización en múltiples lugares
                
                // 1. Revisar en las cuentas
                if (data.data.accounts && Array.isArray(data.data.accounts)) {
                    data.data.accounts.forEach(account => {
                        // Buscar campos de tiempo en cada cuenta
                        const possibleFields = ['last_update', 'updated_at', 'timestamp', 'last_modified'];
                        
                        possibleFields.forEach(field => {
                            if (account[field]) {
                                const accountUpdate = new Date(account[field]);
                                if (!isNaN(accountUpdate.getTime())) {
                                    if (!mostRecentUpdate || accountUpdate > mostRecentUpdate) {
                                        mostRecentUpdate = accountUpdate;
                                        updateSource = `account.${field}`;
                                    }
                                }
                            }
                        });
                    });
                }
                
                // 2. Revisar en system (si existe)
                if (data.data.system) {
                    const systemFields = ['last_update', 'ea_timestamp', 'last_ea_update', 'updated_at'];
                    systemFields.forEach(field => {
                        if (data.data.system[field]) {
                            const systemUpdate = new Date(data.data.system[field]);
                            if (!isNaN(systemUpdate.getTime())) {
                                if (!mostRecentUpdate || systemUpdate > mostRecentUpdate) {
                                    mostRecentUpdate = systemUpdate;
                                    updateSource = `system.${field}`;
                                }
                            }
                        }
                    });
                }
                
                // 3. Revisar en las posiciones abiertas
                if (data.data.positions && Array.isArray(data.data.positions)) {
                    data.data.positions.forEach(position => {
                        if (position.open_time) {
                            const posTime = new Date(position.open_time);
                            if (!isNaN(posTime.getTime())) {
                                if (!mostRecentUpdate || posTime > mostRecentUpdate) {
                                    mostRecentUpdate = posTime;
                                    updateSource = 'position.open_time';
                                }
                            }
                        }
                    });
                }
                
                // Log para debug
                if (mostRecentUpdate) {
                    console.log(`EA última actualización encontrada en ${updateSource}: ${mostRecentUpdate.toLocaleString()}`);
                    lastKnownUpdate = mostRecentUpdate;
                }
                
                // Evaluar el estado basado en la última actualización
                if (mostRecentUpdate) {
                    const now = new Date();
                    const diffSeconds = (now - mostRecentUpdate) / 1000;
                    const diffMinutes = diffSeconds / 60;
                    
                    // Actualizar el texto del estado con más detalle
                    const statusText = document.getElementById('ea-status-text');
                    
                    if (diffSeconds < 60) {
                        if (statusText) {
                            statusText.textContent = `Activo (${Math.round(diffSeconds)}s)`;
                            statusText.title = `Última actualización: ${mostRecentUpdate.toLocaleString('es-ES')}`;
                        }
                        return { online: true, text: `Activo (${Math.round(diffSeconds)}s)` };
                    } else if (diffMinutes < 5) {
                        const mins = Math.round(diffMinutes);
                        if (statusText) {
                            statusText.textContent = `Activo (${mins}m)`;
                            statusText.title = `Última actualización: ${mostRecentUpdate.toLocaleString('es-ES')}`;
                        }
                        return { online: true, text: `Activo (${mins}m)` };
                    } else if (diffMinutes < 30) {
                        const mins = Math.round(diffMinutes);
                        if (statusText) {
                            statusText.textContent = `Inactivo (${mins}m)`;
                            statusText.title = `Última actualización: ${mostRecentUpdate.toLocaleString('es-ES')}`;
                        }
                        return { online: 'warning', text: `Inactivo (${mins}m)` };
                    } else {
                        const hours = Math.round(diffMinutes / 60);
                        if (statusText) {
                            statusText.textContent = hours > 24 ? `Sin datos (${Math.round(hours/24)}d)` : `Sin datos (${hours}h)`;
                            statusText.title = `Última actualización: ${mostRecentUpdate.toLocaleString('es-ES')}`;
                        }
                        return { online: false, text: hours > 24 ? `Sin datos (${Math.round(hours/24)}d)` : `Sin datos (${hours}h)` };
                    }
                }
            }
            
            // Si no se encontró ninguna actualización
            console.warn('No se encontró ninguna actualización del EA en los datos');
            return { online: false, text: 'Sin datos' };
            
        } catch (error) {
            console.error('Error verificando EA:', error);
            return { online: false, text: 'Error' };
        }
    }
    
    // Actualizar indicador visual
    function updateStatusIndicator(elementId, isOnline, statusText) {
        const dot = document.getElementById(elementId);
        const text = document.getElementById(elementId + '-text');
        
        if (dot) {
            // Remover todas las clases de estado
            dot.classList.remove('online', 'offline', 'warning');
            
            // Agregar la clase correcta
            if (isOnline === true) {
                dot.classList.add('online');
            } else if (isOnline === 'warning') {
                dot.classList.add('warning');
            } else {
                dot.classList.add('offline');
            }
        }
        
        if (text) {
            text.textContent = statusText;
            text.style.cursor = 'help';
        }
    }
    
    // Agregar estilos CSS si no existen
    function addStatusStyles() {
        if (!document.getElementById('system-status-styles')) {
            const style = document.createElement('style');
            style.id = 'system-status-styles';
            style.textContent = `
                .status-dot {
                    display: inline-block;
                    width: 8px;
                    height: 8px;
                    border-radius: 50%;
                    margin-right: 8px;
                    background: #6b7280;
                    transition: all 0.3s ease;
                }
                
                .status-dot.online {
                    background: #10b981;
                    box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
                    animation: pulse-green 2s infinite;
                }
                
                .status-dot.offline {
                    background: #ef4444;
                    box-shadow: 0 0 8px rgba(239, 68, 68, 0.5);
                }
                
                .status-dot.warning {
                    background: #f59e0b;
                    box-shadow: 0 0 8px rgba(245, 158, 11, 0.5);
                    animation: pulse-yellow 2s infinite;
                }
                
                @keyframes pulse-green {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.7; }
                }
                
                @keyframes pulse-yellow {
                    0%, 100% { opacity: 1; }
                    50% { opacity: 0.6; }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    // Inicialización
    function init() {
        console.log('Inicializando system-status-fixed.js...');
        
        // Agregar estilos
        addStatusStyles();
        
        // Actualizar estado inmediatamente
        updateSystemStatus();
        
        // Actualizar cada 10 segundos para reflejar cambios rápidos
        setInterval(updateSystemStatus, 10000);
    }
    
    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Exponer funciones para debug
    window.SystemStatus = {
        update: updateSystemStatus,
        checkEA: checkEAStatus,
        getLastUpdate: () => lastKnownUpdate
    };
    
    console.log('system-status-fixed.js cargado. Usa SystemStatus.checkEA() para debug.');
})();