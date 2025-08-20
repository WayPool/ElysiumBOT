<?php
//+------------------------------------------------------------------+
//| get_equity_curve.php                                            |
//| API para obtener equity curve y distribución de retornos        |
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
    'equity_data' => [],
    'returns_data' => [],
    'message' => ''
];

try {
    // Conectar a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener parámetros
    $period = $_GET['period'] ?? '30';
    $magicNumber = isset($_GET['magic_number']) ? intval($_GET['magic_number']) : null;
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
    
    // Query para obtener equity curve
    $equityQuery = "
    SELECT 
        DATE(d.time) as date,
        SUM(d.profit + d.commission + d.swap) as daily_pnl,
        COUNT(*) as trades_count,
        SUM(CASE WHEN d.profit > 0 THEN 1 ELSE 0 END) as winning_trades,
        SUM(CASE WHEN d.profit < 0 THEN 1 ELSE 0 END) as losing_trades
    FROM deals d
    WHERE $whereClause
    GROUP BY DATE(d.time)
    ORDER BY date ASC
    ";
    
    $stmt = $pdo->prepare($equityQuery);
    $stmt->execute($params);
    $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular equity acumulada y drawdown
    $equity = 0;
    $peak = 0;
    $dates = [];
    $equityValues = [];
    $drawdownValues = [];
    $dailyReturns = [];
    
    foreach ($dailyData as $day) {
        $equity += $day['daily_pnl'];
        $dates[] = $day['date'];
        $equityValues[] = round($equity, 2);
        
        // Calcular drawdown
        if ($equity > $peak) {
            $peak = $equity;
        }
        $drawdown = $peak > 0 ? (($peak - $equity) / $peak) * 100 : 0;
        $drawdownValues[] = round($drawdown * -1, 2); // Negativo para visualización
        
        // Guardar retorno diario para distribución
        $dailyReturns[] = round($day['daily_pnl'], 2);
    }
    
    // Preparar datos de equity
    $response['equity_data'] = [
        'dates' => $dates,
        'equity' => $equityValues,
        'drawdown' => $drawdownValues,
        'peak_equity' => round($peak, 2),
        'final_equity' => end($equityValues),
        'max_drawdown' => round(min($drawdownValues), 2)
    ];
    
    // Calcular distribución de retornos
    if (count($dailyReturns) > 0) {
        // Crear histograma de retornos
        $minReturn = min($dailyReturns);
        $maxReturn = max($dailyReturns);
        $range = $maxReturn - $minReturn;
        
        // Crear 20 bins para el histograma
        $numBins = min(20, count($dailyReturns));
        $binSize = $range / $numBins;
        
        $bins = [];
        $frequencies = [];
        
        for ($i = 0; $i < $numBins; $i++) {
            $binStart = $minReturn + ($i * $binSize);
            $binEnd = $binStart + $binSize;
            $binCenter = round(($binStart + $binEnd) / 2, 2);
            
            $bins[] = $binCenter;
            
            // Contar frecuencia en este bin
            $count = 0;
            foreach ($dailyReturns as $return) {
                if ($return >= $binStart && $return < $binEnd) {
                    $count++;
                }
            }
            $frequencies[] = $count;
        }
        
        // Estadísticas de retornos
        $avgReturn = array_sum($dailyReturns) / count($dailyReturns);
        $stdDev = 0;
        foreach ($dailyReturns as $return) {
            $stdDev += pow($return - $avgReturn, 2);
        }
        $stdDev = sqrt($stdDev / count($dailyReturns));
        
        $response['returns_data'] = [
            'bins' => $bins,
            'frequencies' => $frequencies,
            'daily_returns' => $dailyReturns,
            'statistics' => [
                'mean' => round($avgReturn, 2),
                'std_dev' => round($stdDev, 2),
                'min' => round($minReturn, 2),
                'max' => round($maxReturn, 2),
                'total_days' => count($dailyReturns),
                'positive_days' => count(array_filter($dailyReturns, function($r) { return $r > 0; })),
                'negative_days' => count(array_filter($dailyReturns, function($r) { return $r < 0; }))
            ]
        ];
    }
    
    // Obtener información adicional
    $summaryQuery = "
    SELECT 
        COUNT(*) as total_trades,
        SUM(d.profit + d.commission + d.swap) as total_pnl,
        SUM(CASE WHEN d.profit > 0 THEN 1 ELSE 0 END) as total_winners,
        SUM(CASE WHEN d.profit < 0 THEN 1 ELSE 0 END) as total_losers,
        AVG(d.profit) as avg_trade,
        MAX(d.profit) as best_trade,
        MIN(d.profit) as worst_trade,
        MIN(d.time) as first_trade_date,
        MAX(d.time) as last_trade_date
    FROM deals d
    WHERE $whereClause
    ";
    
    $summaryStmt = $pdo->prepare($summaryQuery);
    $summaryStmt->execute($params);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    $response['summary'] = [
        'total_trades' => intval($summary['total_trades']),
        'total_pnl' => round($summary['total_pnl'], 2),
        'win_rate' => $summary['total_trades'] > 0 ? 
            round(($summary['total_winners'] / $summary['total_trades']) * 100, 2) : 0,
        'avg_trade' => round($summary['avg_trade'], 2),
        'best_trade' => round($summary['best_trade'], 2),
        'worst_trade' => round($summary['worst_trade'], 2),
        'first_trade' => $summary['first_trade_date'],
        'last_trade' => $summary['last_trade_date']
    ];
    
    $response['success'] = true;
    $response['period'] = $period;
    
} catch (PDOException $e) {
    $response['success'] = false;
    $response['error'] = 'Database error';
    $response['message'] = 'Unable to retrieve equity curve data';
    error_log("[Elysium Equity Curve API] DB Error: " . $e->getMessage());
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = 'System error';
    $response['message'] = 'Unable to process request';
    error_log("[Elysium Equity Curve API] Error: " . $e->getMessage());
}

// Enviar respuesta
echo json_encode($response, JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION);
?>