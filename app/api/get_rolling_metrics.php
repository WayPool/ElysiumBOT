<?php
//+------------------------------------------------------------------+
//| get_rolling_metrics.php                                         |
//| API para obtener métricas rolling (Sharpe, Vol, MaxDD)          |
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
    'rolling_sharpe' => [],
    'rolling_volatility' => [],
    'rolling_maxdd' => [],
    'message' => ''
];

try {
    // Conectar a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener parámetros
    $window = intval($_GET['window'] ?? 30); // Ventana de cálculo en días
    $period = $_GET['period'] ?? '365';
    $magicNumber = isset($_GET['magic_number']) ? intval($_GET['magic_number']) : null;
    $accountLogin = isset($_GET['account_login']) ? intval($_GET['account_login']) : null;
    $symbol = $_GET['symbol'] ?? null;
    
    // Construir condiciones WHERE
    $conditions = ["d.type IN (0, 1)"]; // Solo trades
    $params = [];
    
    // Filtro por periodo para el rango total de datos
    switch ($period) {
        case 'ytd':
            $conditions[] = "YEAR(d.time) = YEAR(CURDATE())";
            break;
        case 'mtd':
            $conditions[] = "d.time >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
        case '30':
            $conditions[] = "d.time >= DATE_SUB(NOW(), INTERVAL 60 DAY)"; // Extra para rolling
            break;
        case '90':
            $conditions[] = "d.time >= DATE_SUB(NOW(), INTERVAL 180 DAY)";
            break;
        case '365':
        default:
            $conditions[] = "d.time >= DATE_SUB(NOW(), INTERVAL 500 DAY)";
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
    
    // Obtener datos diarios
    $dailyQuery = "
    SELECT 
        DATE(d.time) as date,
        SUM(d.profit + d.commission + d.swap) as daily_pnl,
        COUNT(*) as trades_count
    FROM deals d
    WHERE $whereClause
    GROUP BY DATE(d.time)
    ORDER BY date ASC
    ";
    
    $stmt = $pdo->prepare($dailyQuery);
    $stmt->execute($params);
    $dailyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular métricas rolling
    $dates = [];
    $sharpValues = [];
    $volValues = [];
    $maxDDValues = [];
    
    // Necesitamos al menos 'window' días de datos
    if (count($dailyData) >= $window) {
        for ($i = $window - 1; $i < count($dailyData); $i++) {
            // Obtener ventana de datos
            $windowData = array_slice($dailyData, $i - $window + 1, $window);
            
            // Extraer PnL de la ventana
            $windowPnL = array_column($windowData, 'daily_pnl');
            
            // Fecha del último día de la ventana
            $dates[] = $dailyData[$i]['date'];
            
            // Calcular Sharpe Ratio rolling
            $avgReturn = array_sum($windowPnL) / count($windowPnL);
            $variance = 0;
            foreach ($windowPnL as $pnl) {
                $variance += pow($pnl - $avgReturn, 2);
            }
            $stdDev = sqrt($variance / count($windowPnL));
            
            // Sharpe anualizado (asumiendo 252 días de trading)
            $sharpe = $stdDev > 0 ? ($avgReturn / $stdDev) * sqrt(252) : 0;
            $sharpValues[] = round($sharpe, 4);
            
            // Volatilidad anualizada
            $volatility = $stdDev * sqrt(252);
            $volValues[] = round($volatility, 2);
            
            // Max Drawdown en la ventana
            $cumSum = 0;
            $peak = 0;
            $maxDD = 0;
            
            foreach ($windowPnL as $pnl) {
                $cumSum += $pnl;
                if ($cumSum > $peak) {
                    $peak = $cumSum;
                }
                $dd = $peak > 0 ? (($peak - $cumSum) / $peak) * 100 : 0;
                if ($dd > $maxDD) {
                    $maxDD = $dd;
                }
            }
            
            $maxDDValues[] = round($maxDD, 2);
        }
    }
    
    // Preparar respuesta
    $response['rolling_sharpe'] = [
        'dates' => $dates,
        'values' => $sharpValues,
        'window' => $window,
        'current' => end($sharpValues) ?: 0,
        'avg' => count($sharpValues) > 0 ? round(array_sum($sharpValues) / count($sharpValues), 4) : 0,
        'max' => count($sharpValues) > 0 ? max($sharpValues) : 0,
        'min' => count($sharpValues) > 0 ? min($sharpValues) : 0
    ];
    
    $response['rolling_volatility'] = [
        'dates' => $dates,
        'values' => $volValues,
        'window' => $window,
        'current' => end($volValues) ?: 0,
        'avg' => count($volValues) > 0 ? round(array_sum($volValues) / count($volValues), 2) : 0,
        'max' => count($volValues) > 0 ? max($volValues) : 0,
        'min' => count($volValues) > 0 ? min($volValues) : 0
    ];
    
    $response['rolling_maxdd'] = [
        'dates' => $dates,
        'values' => $maxDDValues,
        'window' => $window,
        'current' => end($maxDDValues) ?: 0,
        'avg' => count($maxDDValues) > 0 ? round(array_sum($maxDDValues) / count($maxDDValues), 2) : 0,
        'max' => count($maxDDValues) > 0 ? max($maxDDValues) : 0,
        'min' => count($maxDDValues) > 0 ? min($maxDDValues) : 0
    ];
    
    $response['success'] = true;
    $response['period'] = $period;
    $response['window_days'] = $window;
    
} catch (PDOException $e) {
    $response['success'] = false;
    $response['error'] = 'Database error';
    $response['message'] = 'Unable to retrieve rolling metrics';
    error_log("[Elysium Rolling Metrics API] DB Error: " . $e->getMessage());
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = 'System error';
    $response['message'] = 'Unable to process request';
    error_log("[Elysium Rolling Metrics API] Error: " . $e->getMessage());
}

// Enviar respuesta
echo json_encode($response, JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION);
?>