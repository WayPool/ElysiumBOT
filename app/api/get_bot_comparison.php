<?php
//+------------------------------------------------------------------+
//| get_bot_comparison.php                                          |
//| API para obtener comparación de bots con datos reales           |
//| Copyright 2025, Elysium Media FZCO                              |
//| Sistema de Reportes de Trading v7.0                             |
//+------------------------------------------------------------------+

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Configuración de base de datos
$host = 'localhost';
$dbname = 'elysium_dashboard';
$user = 'elysium_dashboard';
$pass = '@01Mwdsz4';

// Deshabilitar display de errores en producción
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Respuesta estructurada
$response = [
    'success' => false,
    'bots' => [],
    'message' => ''
];

try {
    // Conectar a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener parámetros
    $sortBy = $_GET['sort_by'] ?? 'pnl';
    $period = $_GET['period'] ?? 'all';
    $accountLogin = isset($_GET['account_login']) ? intval($_GET['account_login']) : null;
    $symbol = $_GET['symbol'] ?? null;
    
    // Construir condiciones WHERE
    $conditions = ["d.type IN (0, 1)"]; // Solo trades
    $params = [];
    
    // Filtro por periodo
    switch ($period) {
        case 'today':
            $conditions[] = "DATE(d.time) = CURDATE()";
            break;
        case 'ytd':
            $conditions[] = "YEAR(d.time) = YEAR(CURDATE())";
            break;
        case 'mtd':
            $conditions[] = "YEAR(d.time) = YEAR(CURDATE()) AND MONTH(d.time) = MONTH(CURDATE())";
            break;
        case '30':
            $conditions[] = "d.time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
        case '90':
            $conditions[] = "d.time >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            break;
        case '365':
            $conditions[] = "d.time >= DATE_SUB(NOW(), INTERVAL 365 DAY)";
            break;
    }
    
    if ($accountLogin) {
        $conditions[] = "d.account_login = :account";
        $params[':account'] = $accountLogin;
    }
    
    if ($symbol) {
        $conditions[] = "d.symbol = :symbol";
        $params[':symbol'] = $symbol;
    }
    
    $whereClause = implode(' AND ', $conditions);
    
    // Query principal para obtener estadísticas por bot
    $query = "
    SELECT 
        d.magic as magic_number,
        COUNT(*) as total_trades,
        COUNT(DISTINCT d.account_login) as accounts_using,
        SUM(d.profit + d.commission + d.swap) as pnl,
        SUM(CASE WHEN d.profit > 0 THEN 1 ELSE 0 END) as winning_trades,
        SUM(CASE WHEN d.profit < 0 THEN 1 ELSE 0 END) as losing_trades,
        SUM(CASE WHEN d.profit > 0 THEN d.profit ELSE 0 END) as gross_profit,
        SUM(CASE WHEN d.profit < 0 THEN ABS(d.profit) ELSE 0 END) as gross_loss,
        AVG(d.profit) as avg_profit,
        STD(d.profit) as profit_std,
        MAX(d.profit) as max_profit,
        MIN(d.profit) as max_loss,
        MIN(d.time) as first_trade,
        MAX(d.time) as last_trade,
        GROUP_CONCAT(DISTINCT d.symbol ORDER BY d.symbol SEPARATOR ', ') as symbols_traded
    FROM deals d
    WHERE $whereClause
        AND d.magic IS NOT NULL 
        AND d.magic != 0
    GROUP BY d.magic
    ";
    
    // Añadir ordenamiento
    switch ($sortBy) {
        case 'return':
            $query .= " ORDER BY pnl / NULLIF(ABS(SUM(CASE WHEN d.time = (SELECT MIN(time) FROM deals WHERE magic = d.magic) THEN d.price * d.volume ELSE 0 END)), 0) DESC";
            break;
        case 'sharpe':
            $query .= " ORDER BY (AVG(d.profit) / NULLIF(STD(d.profit), 0)) DESC";
            break;
        case 'maxdd':
            $query .= " ORDER BY max_loss ASC";
            break;
        case 'pf':
            $query .= " ORDER BY (gross_profit / NULLIF(gross_loss, 0)) DESC";
            break;
        case 'pnl':
        default:
            $query .= " ORDER BY pnl DESC";
            break;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $botsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Mapeo de Magic Numbers a nombres de bots
    $botNames = [
        123456 => ['name' => 'ELYSIUM-PRIME', 'category' => 'high-frequency'],
        123400 => ['name' => 'SCALPER-PRO', 'category' => 'scalping'],
        305424 => ['name' => 'MULTI-FRAME', 'category' => 'multi-timeframe'],
        355000 => ['name' => 'GRID-EXPERT', 'category' => 'grid'],
        552569 => ['name' => 'MOMENTUM-HUNTER', 'category' => 'momentum'],
        777888 => ['name' => 'REVERSAL-MASTER', 'category' => 'mean-reversion'],
        1000001 => ['name' => 'BREAKOUT-SNIPER', 'category' => 'breakout'],
        3054250 => ['name' => 'CORRELATION-TRADER', 'category' => 'correlation'],
        20250122 => ['name' => 'STAT-ARB', 'category' => 'arbitrage'],
        2025012200 => ['name' => 'HEDGE-DYNAMIC', 'category' => 'hedging'],
        2025012247 => ['name' => 'PATTERN-SCOUT', 'category' => 'pattern'],
        2025012278 => ['name' => 'NEWS-REACTOR', 'category' => 'news'],
        305425000 => ['name' => 'CORRELATION-TRADER-V2', 'category' => 'correlation']
    ];
    
    // Procesar cada bot y calcular métricas adicionales
    $bots = [];
    foreach ($botsData as $bot) {
        $magicNumber = intval($bot['magic_number']);
        
        // Obtener nombre y categoría
        $botInfo = $botNames[$magicNumber] ?? [
            'name' => 'BOT-' . $magicNumber,
            'category' => 'unknown'
        ];
        
        // Calcular métricas derivadas
        $winRate = $bot['total_trades'] > 0 ? 
            round(($bot['winning_trades'] / $bot['total_trades']) * 100, 2) : 0;
        
        $profitFactor = $bot['gross_loss'] > 0 ? 
            round($bot['gross_profit'] / $bot['gross_loss'], 2) : 
            ($bot['gross_profit'] > 0 ? 999.99 : 0);
        
        // Calcular Sharpe Ratio simplificado
        $sharpeRatio = $bot['profit_std'] > 0 ? 
            round($bot['avg_profit'] / $bot['profit_std'] * sqrt(252), 2) : 0;
        
        // Calcular Max Drawdown (simplificado, usando el peor trade como proxy)
        $maxDrawdown = abs($bot['max_loss'] ?? 0);
        
        // Calcular rentabilidad porcentual (estimada)
        $returnPct = 0;
        if ($bot['pnl'] != 0) {
            // Estimar capital inicial basado en el tamaño promedio de trades
            $estimatedCapital = abs($bot['avg_profit'] * 100);
            $returnPct = $estimatedCapital > 0 ? 
                round(($bot['pnl'] / $estimatedCapital) * 100, 2) : 0;
        }
        
        // Determinar si está activo (operaciones en los últimos 7 días)
        $isActive = (strtotime($bot['last_trade']) > strtotime('-7 days'));
        
        $bots[] = [
            'magic_number' => $magicNumber,
            'bot_name' => $botInfo['name'],
            'category' => $botInfo['category'],
            'pnl' => round($bot['pnl'], 2),
            'return_pct' => $returnPct,
            'sharpe_ratio' => $sharpeRatio,
            'max_drawdown' => round($maxDrawdown, 2),
            'profit_factor' => $profitFactor,
            'win_rate' => $winRate,
            'total_trades' => intval($bot['total_trades']),
            'winning_trades' => intval($bot['winning_trades']),
            'losing_trades' => intval($bot['losing_trades']),
            'avg_profit' => round($bot['avg_profit'], 2),
            'max_profit' => round($bot['max_profit'], 2),
            'max_loss' => round($bot['max_loss'], 2),
            'first_trade' => $bot['first_trade'],
            'last_trade' => $bot['last_trade'],
            'is_active' => $isActive,
            'accounts_using' => intval($bot['accounts_using']),
            'symbols_traded' => $bot['symbols_traded']
        ];
    }
    
    // Ordenar según el criterio seleccionado
    usort($bots, function($a, $b) use ($sortBy) {
        switch ($sortBy) {
            case 'return':
                return $b['return_pct'] <=> $a['return_pct'];
            case 'sharpe':
                return $b['sharpe_ratio'] <=> $a['sharpe_ratio'];
            case 'maxdd':
                return $a['max_drawdown'] <=> $b['max_drawdown'];
            case 'pf':
                return $b['profit_factor'] <=> $a['profit_factor'];
            case 'pnl':
            default:
                return $b['pnl'] <=> $a['pnl'];
        }
    });
    
    $response['success'] = true;
    $response['bots'] = $bots;
    $response['total_bots'] = count($bots);
    $response['sort_by'] = $sortBy;
    $response['period'] = $period;
    
} catch (PDOException $e) {
    $response['success'] = false;
    $response['error'] = 'Database error';
    $response['message'] = 'Unable to retrieve bot comparison data';
    error_log("[Elysium Bot Comparison API] DB Error: " . $e->getMessage());
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = 'System error';
    $response['message'] = 'Unable to process request';
    error_log("[Elysium Bot Comparison API] Error: " . $e->getMessage());
}

// Enviar respuesta
echo json_encode($response, JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION);
?>