//+------------------------------------------------------------------+
//| Copyright 2025, Elysium Media FZCO                              |
//| https://www.elysiumdubai.net                                    |
//| Fix para cambiar -Demo por -Live en información del servidor    |
//+------------------------------------------------------------------+

(function() {
    'use strict';
    
    console.log('[Server Name Fix] Iniciando corrección de nombres de servidor');
    
    // Interceptar la función updateModalOverview
    if (window.updateModalOverview) {
        const originalUpdate = window.updateModalOverview;
        
        window.updateModalOverview = function(account) {
            // Llamar a la función original
            originalUpdate.call(this, account);
            
            // Después de que se actualice, cambiar el texto del servidor
            setTimeout(() => {
                const serverElement = document.getElementById('modal-server');
                if (serverElement && serverElement.textContent) {
                    // Reemplazar -Demo por -Live
                    const currentText = serverElement.textContent;
                    const newText = currentText.replace('-Demo', '-Live');
                    
                    if (currentText !== newText) {
                        serverElement.textContent = newText;
                        console.log('[Server Name Fix] Cambiado:', currentText, '→', newText);
                    }
                }
            }, 10);
        };
    }
    
    // También monitorear cambios en el DOM por si se actualiza de otra forma
    const observeServerElement = () => {
        const serverElement = document.getElementById('modal-server');
        if (!serverElement) return;
        
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    const currentText = serverElement.textContent;
                    if (currentText && currentText.includes('-Demo')) {
                        const newText = currentText.replace('-Demo', '-Live');
                        serverElement.textContent = newText;
                        console.log('[Server Name Fix] Cambiado por observer:', currentText, '→', newText);
                    }
                }
            });
        });
        
        observer.observe(serverElement, {
            childList: true,
            characterData: true,
            subtree: true
        });
    };
    
    // Iniciar observer cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observeServerElement);
    } else {
        observeServerElement();
    }
    
    console.log('[Server Name Fix] ✅ Sistema cargado');
    
})();