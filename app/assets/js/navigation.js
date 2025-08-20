/**
 * navigation.js - Manejo de navegación del menú lateral
 * Elysium Trading Dashboard v7.0
 * Copyright 2025, Elysium Media FZCO
 */

document.addEventListener('DOMContentLoaded', function() {
    // Obtener todos los enlaces del menú
    const menuLinks = document.querySelectorAll('.sidebar .menu-item');
    
    menuLinks.forEach(link => {
        // Obtener el href
        const href = link.getAttribute('href');
        
        // Si es un enlace a archivo .php, asegurarse de que funcione normal
        if (href && (href.endsWith('.php') || href.startsWith('http'))) {
            // Remover cualquier event listener existente clonando el elemento
            const newLink = link.cloneNode(true);
            link.parentNode.replaceChild(newLink, link);
            
            // No agregar ningún preventDefault
            newLink.addEventListener('click', function(e) {
                // Permitir navegación normal
                console.log('Navegando a:', href);
            });
        } 
        // Solo para anchors internos (#)
        else if (href && href.startsWith('#')) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Actualizar clases active
                menuLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                // Opcional: scroll a la sección
                const targetId = href.substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                }
            });
        }
    });
    
    // Fix específico para accounts.php
    const accountsLink = document.querySelector('a[href="accounts.php"]');
    if (accountsLink) {
        accountsLink.style.cursor = 'pointer';
        accountsLink.onclick = function() {
            window.location.href = 'accounts.php';
            return true;
        };
    }
    
    // Fix específico para index.php
    const dashboardLink = document.querySelector('a[href="index.php"]');
    if (dashboardLink) {
        dashboardLink.style.cursor = 'pointer';
        dashboardLink.onclick = function() {
            window.location.href = 'index.php';
            return true;
        };
    }
});