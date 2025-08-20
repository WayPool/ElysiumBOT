/**
 * sidebar-counters.js
 * Actualiza los contadores del sidebar en todas las páginas
 * Copyright 2025, Elysium Media FZCO
 */

(function() {
    'use strict';
    
    // Función para actualizar contadores del sidebar
    async function updateSidebarCounters() {
        try {
            // Hacer petición al API
            const response = await fetch('api/get_dashboard_data.php?period=RT');
            const data = await response.json();
            
            if (data.success && data.data) {
                // Actualizar contador de cuentas
                const accountsBadge = document.getElementById('sidebar-accounts-badge');
                if (accountsBadge && data.data.accounts) {
                    const activeAccounts = data.data.accounts.filter(acc => acc.status === 'active').length;
                    accountsBadge.textContent = activeAccounts || data.data.accounts.length;
                    accountsBadge.style.display = activeAccounts > 0 ? 'inline-block' : 'none';
                }
                
                // Actualizar contador de posiciones
                const positionsBadge = document.getElementById('sidebar-positions-badge');
                if (positionsBadge && data.data.positions) {
                    const openPositions = data.data.positions.length;
                    positionsBadge.textContent = openPositions;
                    positionsBadge.style.display = openPositions > 0 ? 'inline-block' : 'none';
                    
                    // Agregar clase 'live' si hay posiciones abiertas
                    if (openPositions > 0) {
                        positionsBadge.classList.add('live');
                    } else {
                        positionsBadge.classList.remove('live');
                    }
                }
                
                // Actualizar contador de alertas
                const alertsCount = document.getElementById('alerts-count');
                if (alertsCount && data.data.alerts) {
                    const activeAlerts = data.data.alerts.filter(alert => alert.status === 'active').length;
                    alertsCount.textContent = activeAlerts;
                    alertsCount.style.display = activeAlerts > 0 ? 'inline-block' : 'none';
                }
                
                console.log('✅ Contadores del sidebar actualizados');
            }
        } catch (error) {
            console.error('Error actualizando contadores del sidebar:', error);
        }
    }
    
    // Ejecutar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', updateSidebarCounters);
    } else {
        updateSidebarCounters();
    }
    
    // Actualizar cada 30 segundos
    setInterval(updateSidebarCounters, 30000);
    
    // Exponer función globalmente por si se necesita
    window.updateSidebarCounters = updateSidebarCounters;
})();