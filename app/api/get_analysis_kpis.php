<?php
//+------------------------------------------------------------------+
//| get_analysis_kpis.php                                           |
//| API para obtener KPIs principales del análisis                  |
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
    'kpis' => [],
    'message' => ''
];

try {
    // Conectar a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener parámetros de filtro
    $period = $_GET['period'] ?? 'today';
    $magicNumber = isset($_GET['magic_number']) ? intval($_GET['magic_number']) : null;
    $accountLogin = isset($_GET['account_login']) ? intval($_GET['account_login']) : null;
    $symbol = $_GET['symbol'] ?? null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    // Construir condiciones WHERE
    $conditions = ["d.type IN (0, 1)"]; // Solo trades (Buy/Sell)
    $params = [];
    
    // Filtro por periodo
    if ($period !== 'custom') {
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
    } else {
        if ($startDate) {
            $conditions[] = "d.time >= :start_date";
            $params[':start_date'] = $startDate;
        }
        if ($endDate) {
            $conditions[] = "d.time <= :end_date";
            $params[':end_date'] = $endDate;
        }
    }
    
    // Filtros adicionales
    if ($magicNumber) {
        $conditions[] = "d.magic = :magic";
        $params[':magic'] = $magicNumber;
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
    
    // Query principal para KPIs
    $query = "
    SELECT 
        -- Métricas básicas
        COUNT(*) as total_trades,
        SUM(CASE WHEN d.profit > 0 THEN 1 ELSE 0 END) as winning_trades,
        SUM(CASE WHEN d.profit < 0 THEN 1 ELSE 0 END) as losing_trades,
        SUM(d.profit + d.commission + d.swap) as total_return,
        AVG(d.profit + d.commission + d.swap) as avg_return,
        
        -- P&L
        SUM(CASE WHEN d.profit > 0 THEN d.profit ELSE 0 END) as gross_profit,
        SUM(CASE WHEN d.profit < 0 THEN ABS(d.profit) ELSE 0 END) as gross_loss,
        
        -- Estadísticas
        STD(d.profit) as profit_std,
        MAX(d.profit) as max_profit,
        MIN(d.profit) as max_loss,
        
        -- Fechas para cálculos
        MIN(d.time) as first_trade,
        MAX(d.time) as last_trade,
        DATEDIFF(MAX(d.time), MIN(d.time)) as trading_days
        
    FROM deals d
    WHERE $whereClause
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcular KPIs derivados
    $kpis = [];
    
    // Rentabilidad absoluta y porcentual
    $kpis['total_return'] = round($stats['total_return'] ?? 0, 2);
    
    // Obtener capital inicial para calcular rentabilidad %
    $capitalQuery = "
    SELECT 
        COALESCE(AVG(a.balance), 100000) as avg_capital
    FROM accounts a
    " . ($accountLogin ? "WHERE a.login = :account" : "");
    
    $capitalStmt = $pdo->prepare($capitalQuery);
    if ($accountLogin) {
        $capitalStmt->execute([':account' => $accountLogin]);
    } else {
        $capitalStmt->execute();
    }
    $capitalData = $capitalStmt->fetch(PDO::FETCH_ASSOC);
    $avgCapital = $capitalData['avg_capital'] ?? 100000;
    
    $kpis['return_pct'] = $avgCapital > 0 ? round(($kpis['total_return'] / $avgCapital) * 100, 2) : 0;
    
    // APR (Annual Percentage Rate)
    $tradingDays = max($stats['trading_days'] ?? 1, 1);
    $annualizationFactor = 365 / $tradingDays;
    $kpis['apr'] = round($kpis['return_pct'] * $annualizationFactor, 2);
    
    // APY (con interés compuesto)
    $dailyReturn = $kpis['return_pct'] / 100 / $tradingDays;
    $kpis['apy'] = round((pow(1 + $dailyReturn, 365) - 1) * 100, 2);
    
    // Win Rate
    $totalTrades = $stats['total_trades'] ?? 0;
    $kpis['win_rate'] = $totalTrades > 0 ? 
        round(($stats['winning_trades'] / $totalTrades) * 100, 2) : 0;
    
    // Profit Factor
    $grossProfit = $stats['gross_profit'] ?? 0;
    $grossLoss = $stats['gross_loss'] ?? 1;
    $kpis['profit_factor'] = $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : $grossProfit;
    
    // Expectancy (ganancia esperada por trade)
    $kpis['expectancy'] = $totalTrades > 0 ? round($kpis['total_return'] / $totalTrades, 2) : 0;
    
    // Volatilidad anualizada
    $dailyStd = $stats['profit_std'] ?? 0;
    $kpis['volatility_annual'] = round($dailyStd * sqrt(252), 2); // 252 días trading al año
    
    // Sharpe Ratio (asumiendo tasa libre de riesgo del 2%)
    $riskFreeRate = 0.02;
    $excessReturn = ($kpis['apr'] / 100) - $riskFreeRate;
    $annualVol = $kpis['volatility_annual'] / 100;
    $kpis['sharpe_ratio'] = $annualVol > 0 ? round($excessReturn / $annualVol, 2) : 0;
    
    // Sortino Ratio (solo volatilidad negativa)
    $downsideQuery = "
    SELECT 
        STD(CASE WHEN d.profit < 0 THEN d.profit ELSE NULL END) as downside_std
    FROM deals d
    WHERE $whereClause
    ";
    
    $downsideStmt = $pdo->prepare($downsideQuery);
    $downsideStmt->execute($params);
    $downsideData = $downsideStmt->fetch(PDO::FETCH_ASSOC);
    $downsideStd = $downsideData['downside_std'] ?? 0;
    $downsideVolAnnual = $downsideStd * sqrt(252);
    
    $kpis['sortino_ratio'] = $downsideVolAnnual > 0 ? 
        round($excessReturn / ($downsideVolAnnual / 100), 2) : 0;
    
    // Max Drawdown
    $equityQuery = "
    SELECT 
        d.time,
        SUM(d.profit + d.commission + d.swap) OVER (ORDER BY d.time) as cumulative_pnl
    FROM deals d
    WHERE $whereClause
    ORDER BY d.time
    ";
    
    $equityStmt = $pdo->prepare($equityQuery);
    $equityStmt->execute($params);
    $equityData = $equityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $maxDrawdown = 0;
    $peak = 0;
    
    foreach ($equityData as $point) {
        $equity = $point['cumulative_pnl'];
        if ($equity > $peak) {
            $peak = $equity;
        }
        $drawdown = $peak > 0 ? (($peak - $equity) / $peak) * 100 : 0;
        if ($drawdown > $maxDrawdown) {
            $maxDrawdown = $drawdown;
        }
    }
    
    $kpis['max_drawdown'] = round($maxDrawdown, 2);
    
    // Comparación con periodo anterior
    $prevPeriodConditions = $conditions;
    $prevPeriodParams = $params;
    
    // Ajustar periodo anterior según el periodo actual
    switch ($period) {
        case 'today':
            $prevPeriodConditions[0] = "DATE(d.time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'ytd':
            $prevPeriodConditions[0] = "YEAR(d.time) = YEAR(CURDATE()) - 1";
            break;
        case 'mtd':
            $prevPeriodConditions[0] = "YEAR(d.time) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                                        AND MONTH(d.time) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
            break;
        default:
            // Para periodos de días, tomar el periodo anterior del mismo tamaño
            if (is_numeric($period)) {
                $days = intval($period);
                $prevPeriodConditions[0] = "d.time BETWEEN DATE_SUB(NOW(), INTERVAL " . ($days * 2) . " DAY) 
                                            AND DATE_SUB(NOW(), INTERVAL $days DAY)";
            }
    }
    
    $prevWhereClause = implode(' AND ', $prevPeriodConditions);
    $prevQuery = "
    SELECT SUM(d.profit + d.commission + d.swap) as prev_return
    FROM deals d
    WHERE $prevWhereClause
    ";
    
    $prevStmt = $pdo->prepare($prevQuery);
    $prevStmt->execute($prevPeriodParams);
    $prevData = $prevStmt->fetch(PDO::FETCH_ASSOC);
    $prevReturn = $prevData['prev_return'] ?? 0;
    
    $kpis['return_change'] = $prevReturn != 0 ? 
        round((($kpis['total_return'] - $prevReturn) / abs($prevReturn)) * 100, 2) : 0;
    
    // Métricas adicionales
    $kpis['total_trades'] = $totalTrades;
    $kpis['winning_trades'] = $stats['winning_trades'] ?? 0;
    $kpis['losing_trades'] = $stats['losing_trades'] ?? 0;
    $kpis['avg_win'] = $kpis['winning_trades'] > 0 ? 
        round($grossProfit / $kpis['winning_trades'], 2) : 0;
    $kpis['avg_loss'] = $kpis['losing_trades'] > 0 ? 
        round($grossLoss / $kpis['losing_trades'], 2) : 0;
    $kpis['win_loss_ratio'] = $kpis['avg_loss'] > 0 ? 
        round($kpis['avg_win'] / $kpis['avg_loss'], 2) : 0;
    
    // Tiempo en mercado
    $kpis['time_in_market'] = $tradingDays . ' días';
    
    // Slippage y comisiones promedio
    $slippageQuery = "
    SELECT 
        AVG(ABS(d.commission)) as avg_commission,
        AVG(ABS(d.swap)) as avg_swap
    FROM deals d
    WHERE $whereClause
    ";
    
    $slippageStmt = $pdo->prepare($slippageQuery);
    $slippageStmt->execute($params);
    $slippageData = $slippageStmt->fetch(PDO::FETCH_ASSOC);
    
    $kpis['avg_commission'] = round($slippageData['avg_commission'] ?? 0, 2);
    $kpis['avg_swap'] = round($slippageData['avg_swap'] ?? 0, 2);
    $kpis['total_costs'] = round(
        ($slippageData['avg_commission'] ?? 0) * $totalTrades + 
        ($slippageData['avg_swap'] ?? 0) * $totalTrades, 
        2
    );
    
    // Respuesta exitosa
    $response['success'] = true;
    $response['kpis'] = $kpis;
    $response['period'] = $period;
    $response['filters'] = [
        'magic_number' => $magicNumber,
        'account_login' => $accountLogin,
        'symbol' => $symbol
    ];
    
} catch (PDOException $e) {
    $response['success'] = false;
    $response['error'] = 'Database error';
    $response['message'] = 'Unable to retrieve KPIs';
    error_log("[Elysium Analysis KPIs API] DB Error: " . $e->getMessage());
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = 'System error';
    $response['message'] = 'Unable to process request';
    error_log("[Elysium Analysis KPIs API] Error: " . $e->getMessage());
}

// Enviar respuesta
echo json_encode($response, JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION);
?>