//+------------------------------------------------------------------+
//| bot-names.config.js                                             |
//| Configuración de mapeo MagicNumber -> Nombre de Bot             |
//| Copyright 2025, Elysium Media FZCO                              |
//| Sistema de Reportes de Trading v7.0                             |
//+------------------------------------------------------------------+

/**
 * Configuración de nombres de bots por MagicNumber
 * Basado en los Magic Numbers reales de la base de datos
 * 
 * @constant {Object} BOT_NAMES - Mapeo de MagicNumber a información del bot
 * @property {string} name - Nombre del bot
 * @property {string} version - Versión del bot
 * @property {string} category - Categoría del bot (scalping, trend, grid, etc.)
 * @property {string} description - Descripción breve del bot
 * @property {string} color - Color para visualizaciones (hex)
 * @property {boolean} active - Si el bot está actualmente activo
 */
const BOT_NAMES = {
    // Bot Principal - Alta frecuencia
    123456: {
        name: 'ELYSIUM-PRIME',
        version: '4.2.0',
        category: 'high-frequency',
        description: 'Bot principal de alta frecuencia con gestión avanzada',
        color: '#4F46E5',
        active: true
    },
    
    // Bot Scalper
    123400: {
        name: 'SCALPER-PRO',
        version: '2.1.0',
        category: 'scalping',
        description: 'Scalping de precisión en marcos temporales cortos',
        color: '#10B981',
        active: true
    },
    
    // Bot de Análisis Multi-Marco
    305424: {
        name: 'MULTI-FRAME',
        version: '3.5.2',
        category: 'multi-timeframe',
        description: 'Análisis multi-temporal con confirmación de tendencia',
        color: '#06B6D4',
        active: true
    },
    
    // Bot de Grid Trading
    355000: {
        name: 'GRID-EXPERT',
        version: '1.8.3',
        category: 'grid',
        description: 'Grid trading adaptativo con gestión de volatilidad',
        color: '#F59E0B',
        active: true
    },
    
    // Bot de Momentum
    552569: {
        name: 'MOMENTUM-HUNTER',
        version: '2.3.1',
        category: 'momentum',
        description: 'Captura movimientos de momentum en mercados volátiles',
        color: '#8B5CF6',
        active: true
    },
    
    // Bot de Reversión
    777888: {
        name: 'REVERSAL-MASTER',
        version: '3.1.0',
        category: 'mean-reversion',
        description: 'Trading de reversión a la media con filtros estadísticos',
        color: '#EC4899',
        active: true
    },
    
    // Bot de Breakout
    1000001: {
        name: 'BREAKOUT-SNIPER',
        version: '2.0.5',
        category: 'breakout',
        description: 'Identificación y trading de rupturas significativas',
        color: '#14B8A6',
        active: true
    },
    
    // Bot de Correlación
    3054250: {
        name: 'CORRELATION-TRADER',
        version: '1.5.0',
        category: 'correlation',
        description: 'Trading basado en correlaciones entre pares',
        color: '#F97316',
        active: true
    },
    
    // Bot de Arbitraje Estadístico
    20250122: {
        name: 'STAT-ARB',
        version: '3.2.0',
        category: 'arbitrage',
        description: 'Arbitraje estadístico con análisis de cointegración',
        color: '#0EA5E9',
        active: true
    },
    
    // Bot de Hedge Dinámico
    2025012200: {
        name: 'HEDGE-DYNAMIC',
        version: '2.1.0',
        category: 'hedging',
        description: 'Cobertura dinámica de posiciones con gestión de riesgo',
        color: '#84CC16',
        active: true
    },
    
    // Bot de Pattern Recognition
    2025012247: {
        name: 'PATTERN-SCOUT',
        version: '1.9.0',
        category: 'pattern',
        description: 'Reconocimiento de patrones técnicos con ML',
        color: '#6366F1',
        active: true
    },
    
    // Bot de News Trading
    2025012278: {
        name: 'NEWS-REACTOR',
        version: '1.4.2',
        category: 'news',
        description: 'Trading reactivo basado en eventos y noticias',
        color: '#A855F7',
        active: true
    },
    
    // Bot Experimental 305425000 (duplicado de 3054250, posible versión diferente)
    305425000: {
        name: 'CORRELATION-TRADER-V2',
        version: '2.0.0',
        category: 'correlation',
        description: 'Versión mejorada del trader de correlaciones',
        color: '#DC2626',
        active: true
    }
};

/**
 * Categorías de bots con sus configuraciones
 */
const BOT_CATEGORIES = {
    'high-frequency': {
        name: 'Alta Frecuencia',
        icon: '⚡',
        description: 'Trading de alta frecuencia con múltiples operaciones diarias'
    },
    scalping: {
        name: 'Scalping',
        icon: '🎯',
        description: 'Trading de corto plazo con objetivos pequeños'
    },
    'multi-timeframe': {
        name: 'Multi-Temporal',
        icon: '📊',
        description: 'Análisis en múltiples marcos temporales'
    },
    grid: {
        name: 'Grid Trading',
        icon: '🔲',
        description: 'Trading con rejilla de órdenes'
    },
    momentum: {
        name: 'Momentum',
        icon: '🚀',
        description: 'Aprovecha movimientos fuertes del mercado'
    },
    'mean-reversion': {
        name: 'Reversión a la Media',
        icon: '↩️',
        description: 'Trading basado en reversión estadística'
    },
    breakout: {
        name: 'Ruptura',
        icon: '💥',
        description: 'Trading de rupturas de niveles clave'
    },
    correlation: {
        name: 'Correlación',
        icon: '🔗',
        description: 'Trading basado en correlaciones entre activos'
    },
    arbitrage: {
        name: 'Arbitraje',
        icon: '🔄',
        description: 'Aprovecha ineficiencias del mercado'
    },
    hedging: {
        name: 'Cobertura',
        icon: '🛡️',
        description: 'Protección y gestión de riesgo'
    },
    pattern: {
        name: 'Patrones',
        icon: '📈',
        description: 'Reconocimiento de patrones técnicos'
    },
    news: {
        name: 'Noticias',
        icon: '📰',
        description: 'Trading basado en eventos y noticias'
    }
};

/**
 * Estadísticas de operaciones por Magic Number (basado en datos reales)
 * Actualizado: Agosto 2025
 */
const BOT_STATISTICS = {
    123456: { operations: 78, lastActive: '2025-08-20', totalPnL: 867.58 },
    123400: { operations: 25, lastActive: '2025-08-13', totalPnL: -636.90 },
    305424: { operations: 59, lastActive: '2025-08-20', totalPnL: -1028000.00 },
    355000: { operations: 11, lastActive: '2025-08-12', totalPnL: -2865.00 },
    552569: { operations: 3, lastActive: '2025-07-30', totalPnL: 21800.00 },
    777888: { operations: 131, lastActive: '2025-08-11', totalPnL: 34500.00 },
    1000001: { operations: 5, lastActive: '2025-07-31', totalPnL: -22000.00 },
    3054250: { operations: 1, lastActive: '2025-08-01', totalPnL: 4730.63 },
    20250122: { operations: 16, lastActive: '2025-08-19', totalPnL: -45000.00 },
    2025012200: { operations: 3, lastActive: '2025-08-01', totalPnL: -961.86 },
    2025012247: { operations: 2, lastActive: '2025-07-29', totalPnL: -15342.59 },
    2025012278: { operations: 6, lastActive: '2025-08-01', totalPnL: -75000.00 },
    305425000: { operations: 1, lastActive: '2025-08-01', totalPnL: 4730.63 }
};

/**
 * Obtiene el nombre del bot por MagicNumber
 * @param {number} magicNumber - MagicNumber del bot
 * @returns {string} Nombre del bot o MagicNumber si no existe
 */
function getBotName(magicNumber) {
    const bot = BOT_NAMES[magicNumber];
    return bot ? bot.name : `BOT-${magicNumber}`;
}

/**
 * Obtiene la información completa del bot
 * @param {number} magicNumber - MagicNumber del bot
 * @returns {Object} Información del bot o objeto por defecto
 */
function getBotInfo(magicNumber) {
    return BOT_NAMES[magicNumber] || {
        name: `BOT-${magicNumber}`,
        version: 'Unknown',
        category: 'unknown',
        description: 'Bot no registrado en configuración',
        color: '#374151',
        active: false
    };
}

/**
 * Obtiene todos los bots activos
 * @returns {Array} Array de bots activos con su MagicNumber
 */
function getActiveBots() {
    return Object.entries(BOT_NAMES)
        .filter(([_, bot]) => bot.active)
        .map(([magicNumber, bot]) => ({
            magicNumber: parseInt(magicNumber),
            ...bot,
            statistics: BOT_STATISTICS[magicNumber] || null
        }));
}

/**
 * Obtiene bots por categoría
 * @param {string} category - Categoría de los bots
 * @returns {Array} Array de bots de la categoría especificada
 */
function getBotsByCategory(category) {
    return Object.entries(BOT_NAMES)
        .filter(([_, bot]) => bot.category === category)
        .map(([magicNumber, bot]) => ({
            magicNumber: parseInt(magicNumber),
            ...bot,
            statistics: BOT_STATISTICS[magicNumber] || null
        }));
}

/**
 * Obtiene el color del bot para visualizaciones
 * @param {number} magicNumber - MagicNumber del bot
 * @returns {string} Color en formato hex
 */
function getBotColor(magicNumber) {
    const bot = BOT_NAMES[magicNumber];
    return bot ? bot.color : '#374151';
}

/**
 * Verifica si un bot está activo
 * @param {number} magicNumber - MagicNumber del bot
 * @returns {boolean} True si el bot está activo
 */
function isBotActive(magicNumber) {
    const bot = BOT_NAMES[magicNumber];
    return bot ? bot.active : false;
}

/**
 * Obtiene estadísticas de los bots
 * @returns {Object} Estadísticas generales de los bots
 */
function getBotsStatistics() {
    const total = Object.keys(BOT_NAMES).length;
    const active = Object.values(BOT_NAMES).filter(bot => bot.active).length;
    const byCategory = {};
    
    Object.values(BOT_NAMES).forEach(bot => {
        if (!byCategory[bot.category]) {
            byCategory[bot.category] = { total: 0, active: 0 };
        }
        byCategory[bot.category].total++;
        if (bot.active) {
            byCategory[bot.category].active++;
        }
    });
    
    // Calcular totales desde BOT_STATISTICS
    let totalOperations = 0;
    let totalPnL = 0;
    let profitable = 0;
    
    Object.entries(BOT_STATISTICS).forEach(([magic, stats]) => {
        totalOperations += stats.operations;
        totalPnL += stats.totalPnL;
        if (stats.totalPnL > 0) profitable++;
    });
    
    return {
        total,
        active,
        inactive: total - active,
        byCategory,
        totalOperations,
        totalPnL: totalPnL.toFixed(2),
        profitableBots: profitable,
        unprofitableBots: total - profitable
    };
}

/**
 * Obtiene el rendimiento de un bot específico
 * @param {number} magicNumber - MagicNumber del bot
 * @returns {Object} Estadísticas del bot o null
 */
function getBotPerformance(magicNumber) {
    const bot = BOT_NAMES[magicNumber];
    const stats = BOT_STATISTICS[magicNumber];
    
    if (!bot || !stats) return null;
    
    return {
        ...bot,
        ...stats,
        avgPnLPerOperation: (stats.totalPnL / stats.operations).toFixed(2),
        isActive: bot.active,
        daysSinceLastTrade: Math.floor(
            (new Date() - new Date(stats.lastActive)) / (1000 * 60 * 60 * 24)
        )
    };
}

// Export para uso en otros módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        BOT_NAMES,
        BOT_CATEGORIES,
        BOT_STATISTICS,
        getBotName,
        getBotInfo,
        getActiveBots,
        getBotsByCategory,
        getBotColor,
        isBotActive,
        getBotsStatistics,
        getBotPerformance
    };
}