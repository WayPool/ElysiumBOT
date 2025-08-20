//+------------------------------------------------------------------+
//| Copyright 2025, Elysium Media FZCO                              |
//| https://www.elysiumdubai.net                                    |
//| Sistema de Eliminación de Cuentas con Contraseña v7.0           |
//+------------------------------------------------------------------+

(function() {
    'use strict';
    
    // ============================================
    // ESTADO GLOBAL
    // ============================================
    window.DeleteAccountState = {
        currentLogin: null,
        currentName: null,
        isDeleting: false,
        lastOpenedAccount: null  // Agregar esta línea
    };
    
    // ============================================
    // FUNCIÓN: ABRIR MODAL DE CONFIRMACIÓN
    // ============================================
    window.confirmDeleteAccount = function() {
        console.log('[Delete Account] Iniciando confirmación de eliminación...');
        
        // Debug: Ver estado actual
        console.log('[Delete Account] Estado global:', {
            hasAccountsState: !!window.AccountsState,
            hasCurrentAccount: !!(window.AccountsState && window.AccountsState.currentAccount),
            currentAccount: window.AccountsState ? window.AccountsState.currentAccount : null,
            accountsList: window.AccountsState ? window.AccountsState.accounts : null
        });
        
        // Primero intentar obtener la cuenta del estado global
        let account = null;
        
        // Opción 1: Desde AccountsState.currentAccount
        if (window.AccountsState && window.AccountsState.currentAccount) {
            account = window.AccountsState.currentAccount;
            console.log('[Delete Account] Cuenta obtenida de AccountsState.currentAccount:', account);
        }
        
        // Opción 2: Si no hay cuenta en el estado, intentar obtenerla del modal visible
        if (!account) {
            console.log('[Delete Account] No hay currentAccount, intentando obtener del DOM...');
            
            // Obtener el login del título del modal
            const titleEl = document.getElementById('modal-account-title');
            console.log('[Delete Account] Elemento título:', titleEl, 'Texto:', titleEl ? titleEl.textContent : 'N/A');
            
            if (titleEl) {
                const titleText = titleEl.textContent || '';
                const match = titleText.match(/#(\d+)/);
                console.log('[Delete Account] Regex match:', match);
                
                if (match && match[1]) {
                    const login = parseInt(match[1]);
                    console.log('[Delete Account] Login extraído:', login);
                    
                    // Buscar en la lista de cuentas
                    if (window.AccountsState && window.AccountsState.accounts) {
                        account = window.AccountsState.accounts.find(a => a.login == login);
                        console.log('[Delete Account] Cuenta encontrada en lista:', account);
                    } else {
                        console.log('[Delete Account] No hay lista de cuentas disponible');
                    }
                }
            }
        }
        
        // Opción 3: Obtener datos directamente del modal si están visibles
        if (!account) {
            console.log('[Delete Account] Intentando obtener datos directamente del modal...');
            
            const equityEl = document.getElementById('modal-equity');
            const balanceEl = document.getElementById('modal-balance');
            const titleEl = document.getElementById('modal-account-title');
            
            if (titleEl && equityEl && balanceEl) {
                const titleText = titleEl.textContent || '';
                const match = titleText.match(/#(\d+)/);
                
                if (match && match[1]) {
                    // Crear objeto cuenta mínimo con los datos disponibles
                    account = {
                        login: parseInt(match[1]),
                        name: 'Cuenta',
                        equity: equityEl.textContent,
                        balance: balanceEl.textContent,
                        server: ''
                    };
                    console.log('[Delete Account] Cuenta creada desde datos del modal:', account);
                }
            }
        }
        
        // Opción 4: Usar la última cuenta abierta guardada
        if (!account && window.DeleteAccountState.lastOpenedAccount) {
            account = window.DeleteAccountState.lastOpenedAccount;
            console.log('[Delete Account] Usando última cuenta abierta:', account);
        }
        
        // Si aún no hay cuenta, error
        if (!account) {
            console.error('[Delete Account] ERROR: No se pudo identificar la cuenta');
            console.error('[Delete Account] Debug info:', {
                hasModal: !!document.getElementById('accountModal'),
                modalVisible: document.getElementById('accountModal')?.classList.contains('show'),
                titleElement: document.getElementById('modal-account-title')?.textContent,
                accountsState: window.AccountsState
            });
            showNotification('Error: No se ha seleccionado ninguna cuenta', 'error');
            return;
        }
        
        // Guardar datos de la cuenta a eliminar ANTES de cerrar el modal
        window.DeleteAccountState.currentLogin = account.login;
        window.DeleteAccountState.currentName = account.name || 'Sin nombre';
        
        console.log('[Delete Account] Confirmando eliminación de cuenta:', account.login);
        
        // Actualizar información en el modal de confirmación
        const deleteNumberEl = document.getElementById('delete-account-number');
        const deleteNameEl = document.getElementById('delete-account-name');
        
        if (deleteNumberEl) {
            deleteNumberEl.textContent = account.login;
        }
        
        if (deleteNameEl) {
            deleteNameEl.textContent = `${account.name || 'Sin nombre'} - ${account.server || ''}`;
        }
        
        // Limpiar campo de contraseña y mensajes de error
        const passwordField = document.getElementById('delete-password');
        if (passwordField) {
            passwordField.value = '';
        }
        
        const errorMessage = document.getElementById('delete-error-message');
        if (errorMessage) {
            errorMessage.style.display = 'none';
        }
        
        // Cerrar modal de cuenta (opcional - puedes comentar esta línea para mantenerlo abierto)
        // closeAccountModal();
        
        // Abrir modal de confirmación
        openDeleteModal();
        
        // Focus en el campo de contraseña después de abrir el modal
        setTimeout(() => {
            if (passwordField) {
                passwordField.focus();
            }
        }, 200);
    };
    
    // ============================================
    // FUNCIÓN: ABRIR MODAL DE ELIMINACIÓN
    // ============================================
    window.openDeleteModal = function() {
        const modal = document.getElementById('deleteModal');
        if (modal) {
            modal.classList.add('show');
            
            // Focus en el campo de contraseña
            setTimeout(() => {
                const passwordField = document.getElementById('delete-password');
                if (passwordField) {
                    passwordField.focus();
                }
            }, 100);
            
            console.log('[Delete Account] Modal de confirmación abierto');
        } else {
            console.error('[Delete Account] Modal de eliminación no encontrado');
        }
    };
    
    // ============================================
    // FUNCIÓN: CERRAR MODAL DE ELIMINACIÓN
    // ============================================
    window.closeDeleteModal = function() {
        const modal = document.getElementById('deleteModal');
        if (modal) {
            modal.classList.remove('show');
        }
        
        // Limpiar estado
        window.DeleteAccountState.currentLogin = null;
        window.DeleteAccountState.currentName = null;
        window.DeleteAccountState.isDeleting = false;
        
        // Limpiar campo de contraseña
        const passwordField = document.getElementById('delete-password');
        if (passwordField) {
            passwordField.value = '';
        }
        
        // Ocultar mensaje de error
        const errorMessage = document.getElementById('delete-error-message');
        if (errorMessage) {
            errorMessage.style.display = 'none';
        }
        
        console.log('[Delete Account] Modal de confirmación cerrado');
    };
    
    // ============================================
    // FUNCIÓN: ELIMINAR CUENTA
    // ============================================
    window.deleteAccount = async function() {
        // Verificar que no se esté procesando ya
        if (window.DeleteAccountState.isDeleting) {
            console.warn('[Delete Account] Ya se está procesando una eliminación');
            return;
        }
        
        // Verificar que hay una cuenta seleccionada
        if (!window.DeleteAccountState.currentLogin) {
            showNotification('Error: No se ha seleccionado ninguna cuenta', 'error');
            return;
        }
        
        // Obtener contraseña
        const passwordField = document.getElementById('delete-password');
        if (!passwordField || !passwordField.value) {
            showDeleteError('Por favor ingrese la contraseña de autorización');
            passwordField?.focus();
            return;
        }
        
        const password = passwordField.value;
        const login = window.DeleteAccountState.currentLogin;
        const name = window.DeleteAccountState.currentName;
        
        console.log('[Delete Account] Procesando eliminación de cuenta:', login);
        
        // Marcar como procesando
        window.DeleteAccountState.isDeleting = true;
        
        // Deshabilitar botón y mostrar loading
        const confirmBtn = document.getElementById('btn-confirm-delete');
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = '<span class="material-icons-outlined rotating">sync</span> Eliminando...';
        }
        
        try {
            // Llamar al API de eliminación
            const response = await fetch('api/delete_account.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    login: login,
                    password: password
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                console.log('[Delete Account] ✅ Cuenta eliminada exitosamente:', data);
                
                // Mostrar notificación de éxito
                showNotification(
                    `✅ Cuenta #${login} (${name}) eliminada exitosamente. ` +
                    `Se eliminaron ${data.deleted.total_records} registros.`,
                    'success'
                );
                
                // Cerrar modal de eliminación
                closeDeleteModal();
                
                // También cerrar el modal de cuenta si está abierto
                if (typeof closeAccountModal === 'function') {
                    closeAccountModal();
                }
                
                // Actualizar la lista de cuentas
                if (typeof loadAccounts === 'function') {
                    setTimeout(() => {
                        loadAccounts();
                    }, 500);
                } else if (typeof loadAccountsData === 'function') {
                    // Alternativa si la función tiene otro nombre
                    setTimeout(() => {
                        loadAccountsData(true);
                    }, 500);
                }
                
                // Remover la cuenta del estado si existe
                if (window.AccountsState && window.AccountsState.accounts) {
                    window.AccountsState.accounts = window.AccountsState.accounts.filter(
                        acc => acc.login != login
                    );
                    
                    // Actualizar grid si existe la función
                    if (typeof renderAccountsGrid === 'function') {
                        renderAccountsGrid();
                    }
                }
                
                // Log en consola para debugging
                console.log('[Delete Account] Registros eliminados:', {
                    positions: data.deleted.positions,
                    deals: data.deleted.deals,
                    logs: data.deleted.import_logs,
                    total: data.deleted.total_records
                });
                
            } else {
                // Error del servidor
                console.error('[Delete Account] Error del servidor:', data.error);
                
                if (data.error === 'Contraseña incorrecta') {
                    showDeleteError('Contraseña incorrecta. Por favor verifique e intente nuevamente.');
                    passwordField.value = '';
                    passwordField.focus();
                } else {
                    showDeleteError(data.error || 'Error al eliminar la cuenta');
                }
            }
            
        } catch (error) {
            console.error('[Delete Account] Error de red:', error);
            showDeleteError('Error de conexión. Por favor intente nuevamente.');
            
        } finally {
            // Rehabilitar botón
            window.DeleteAccountState.isDeleting = false;
            
            if (confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<span class="material-icons-outlined">delete_forever</span> Eliminar Permanentemente';
            }
        }
    };
    
    // ============================================
    // FUNCIÓN: MOSTRAR ERROR EN MODAL
    // ============================================
    function showDeleteError(message) {
        const errorDiv = document.getElementById('delete-error-message');
        const errorText = document.getElementById('delete-error-text');
        
        if (errorDiv && errorText) {
            errorText.textContent = message;
            errorDiv.style.display = 'block';
            
            // Animar el error
            errorDiv.style.animation = 'shake 0.5s';
            setTimeout(() => {
                errorDiv.style.animation = '';
            }, 500);
        }
    }
    
    // ============================================
    // FUNCIÓN: MOSTRAR NOTIFICACIÓN
    // ============================================
    function showNotification(message, type = 'info') {
        // Intentar usar el sistema de notificaciones existente
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type);
            return;
        }
        
        // Fallback: crear notificación simple
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#6366f1'};
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 100000;
            animation: slideIn 0.3s ease;
            max-width: 400px;
        `;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Remover después de 5 segundos
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    }
    
    // ============================================
    // EVENT LISTENERS
    // ============================================
    
    // Cerrar modal al hacer clic fuera
    document.addEventListener('click', function(e) {
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal && e.target === deleteModal) {
            closeDeleteModal();
        }
    });
    
    // Manejar tecla ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal && deleteModal.classList.contains('show')) {
                closeDeleteModal();
            }
        }
    });
    
    // ============================================
    // ESTILOS CSS PARA ANIMACIONES
    // ============================================
    if (!document.getElementById('delete-account-styles')) {
        const style = document.createElement('style');
        style.id = 'delete-account-styles';
        style.textContent = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                20%, 40%, 60%, 80% { transform: translateX(5px); }
            }
            
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            
            .rotating {
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            .btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
        `;
        document.head.appendChild(style);
    }
    
    // ============================================
    // INTERCEPTAR APERTURA DEL MODAL DE CUENTA
    // ============================================
    // Guardar referencia a la cuenta cuando se abre el modal
    if (window.openAccountModal) {
        const originalOpenModal = window.openAccountModal;
        window.openAccountModal = function(login) {
            console.log('[Delete Account] Interceptando apertura de modal para cuenta:', login);
            
            // Llamar función original
            const result = originalOpenModal.call(this, login);
            
            // Guardar referencia temporal de la cuenta
            if (window.AccountsState && window.AccountsState.accounts) {
                const account = window.AccountsState.accounts.find(a => a.login == login);
                if (account) {
                    window.DeleteAccountState.lastOpenedAccount = account;
                    console.log('[Delete Account] Cuenta guardada temporalmente:', account);
                }
            }
            
            return result;
        };
    }
    
    console.log('[Delete Account System] ✅ Sistema de eliminación con contraseña cargado v7.0');
    
})();