<?php
//+------------------------------------------------------------------+
//| get_account_statistics.php                                      |
//| API para obtener estadísticas de cuenta - Elysium v7.0         |
//| Copyright 2025, Elysium Media FZCO                              |
//| Gestión Profesional de Fondos de Trading                        |
//+------------------------------------------------------------------+

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: no-cache, must-revalidate');

// Configuración de base de datos
$host = 'localhost';
$dbname = 'elysium_dashboard';
$user = 'elysium_dashboard';
$pass = '@01Mwdsz4';

$response = [
    'success' => false,
    'statistics' => []
];

try {
    // Validar parámetro login
    if (!isset($_GET['login']) || empty($_GET['login'])) {
        throw new Exception('Login de cuenta requerido');
    }
    
    $login = intval($_GET['login']);
    
    // Conexión a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener información de la cuenta
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE login = :login");
    $stmt->execute(['login' => $login]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        throw new Exception("Cuenta no encontrada");
    }
    
    // ============================
    // ESTADÍSTICAS BÁSICAS
    // ============================
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN entry = 1 THEN 1 END) as total_trades,
            COUNT(CASE WHEN entry = 1 AND profit > 0 THEN 1 END) as winning_trades,
            COUNT(CASE WHEN entry = 1 AND profit < 0 THEN 1 END) as losing_trades,
            SUM(CASE WHEN entry = 1 AND profit > 0 THEN profit ELSE 0 END) as gross_profit,
            SUM(CASE WHEN entry = 1 AND profit < 0 THEN ABS(profit) ELSE 0 END) as gross_loss,
            SUM(CASE WHEN entry = 1 THEN profit + swap + commission ELSE 0 END) as net_profit,
            MAX(CASE WHEN entry = 1 THEN profit ELSE NULL END) as largest_win,
            MIN(CASE WHEN entry = 1 THEN profit ELSE NULL END) as largest_loss,
            AVG(CASE WHEN entry = 1 AND profit > 0 THEN profit ELSE NULL END) as avg_win,
            AVG(CASE WHEN entry = 1 AND profit < 0 THEN ABS(profit) ELSE NULL END) as avg_loss,
            SUM(CASE WHEN entry = 1 THEN swap ELSE 0 END) as total_swap,
            SUM(CASE WHEN entry = 1 THEN commission ELSE 0 END) as total_commission,
            COUNT(CASE WHEN type = 0 AND entry = 1 THEN 1 END) as long_trades,
            COUNT(CASE WHEN type = 1 AND entry = 1 THEN 1 END) as short_trades
        FROM deals
        WHERE account_login = :login
        AND type IN (0,1)
    ");
    $stmt->execute(['login' => $login]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ============================
    // CALCULAR MÉTRICAS AVANZADAS
    // ============================
    
    // Win Rate
    $stats['win_rate'] = $stats['total_trades'] > 0 ? 
        ($stats['winning_trades'] / $stats['total_trades']) * 100 : 0;
    
    // Profit Factor
    $stats['profit_factor'] = $stats['gross_loss'] > 0 ? 
        $stats['gross_profit'] / $stats['gross_loss'] : 
        ($stats['gross_profit'] > 0 ? 999.99 : 0);
    
    // Expectancy
    $stats['expectancy'] = $stats['total_trades'] > 0 ? 
        $stats['net_profit'] / $stats['total_trades'] : 0;
    
    // Risk/Reward Ratio
    $stats['risk_reward'] = ($stats['avg_loss'] > 0 && $stats['avg_win'] > 0) ? 
        $stats['avg_win'] / $stats['avg_loss'] : 0;
    
    // ============================
    // CALCULAR DRAWDOWN
    // ============================
    $stmt = $pdo->prepare("
        SELECT time, profit + swap + commission as pl
        FROM deals
        WHERE account_login = :login
        AND type IN (0,1)
        AND entry = 1
        ORDER BY time ASC
    ");
    $stmt->execute(['login' => $login]);
    $trades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $equity_curve = [];
    $running_balance = floatval($account['balance']) - floatval($stats['net_profit']);
    $peak = $running_balance;
    $max_drawdown = 0;
    $max_drawdown_pct = 0;
    
    foreach ($trades as $trade) {
        $running_balance += floatval($trade['pl']);
        $equity_curve[] = $running_balance;
        
        if ($running_balance > $peak) {
            $peak = $running_balance;
        }
        
        $drawdown = $peak - $running_balance;
        $drawdown_pct = $peak > 0 ? ($drawdown / $peak) * 100 : 0;
        
        if ($drawdown > $max_drawdown) {
            $max_drawdown = $drawdown;
            $max_drawdown_pct = $drawdown_pct;
        }
    }
    
    $stats['max_drawdown'] = $max_drawdown;
    $stats['max_drawdown_pct'] = $max_drawdown_pct;
    
    // Recovery Factor
    $stats['recovery_factor'] = $max_drawdown > 0 ? 
        floatval($stats['net_profit']) / $max_drawdown : 0;
    
    // ============================
    // CALCULAR RATIOS DE SHARPE Y SORTINO
    // ============================
    if (count($trades) > 1) {
        // Calcular retornos diarios
        $daily_returns = [];
        $prev_balance = floatval($account['balance']) - floatval($stats['net_profit']);
        
        foreach ($trades as $trade) {
            $new_balance = $prev_balance + floatval($trade['pl']);
            $daily_return = $prev_balance > 0 ? 
                (($new_balance - $prev_balance) / $prev_balance) : 0;
            $daily_returns[] = $daily_return;
            $prev_balance = $new_balance;
        }
        
        // Sharpe Ratio
        $avg_return = array_sum($daily_returns) / count($daily_returns);
        $variance = 0;
        foreach ($daily_returns as $return) {
            $variance += pow($return - $avg_return, 2);
        }
        $std_dev = sqrt($variance / (count($daily_returns) - 1));
        $stats['sharpe_ratio'] = $std_dev > 0 ? 
            ($avg_return / $std_dev) * sqrt(252) : 0; // Anualizado
        
        // Sortino Ratio
        $negative_returns = array_filter($daily_returns, function($r) { return $r < 0; });
        if (count($negative_returns) > 0) {
            $downside_variance = 0;
            foreach ($negative_returns as $return) {
                $downside_variance += pow($return, 2);
            }
            $downside_std = sqrt($downside_variance / count($negative_returns));
            $stats['sortino_ratio'] = $downside_std > 0 ? 
                ($avg_return / $downside_std) * sqrt(252) : 0;
        } else {
            $stats['sortino_ratio'] = $stats['sharpe_ratio'] * 1.5;
        }
        
        // Calmar Ratio
        $days_trading = count($trades);
        $years_trading = max($days_trading / 252, 0.01);
        $annual_return = $years_trading > 0 ? 
            (floatval($stats['net_profit']) / $running_balance) / $years_trading : 0;
        $stats['calmar_ratio'] = $max_drawdown_pct > 0 ? 
            ($annual_return * 100) / $max_drawdown_pct : 0;
    } else {
        $stats['sharpe_ratio'] = 0;
        $stats['sortino_ratio'] = 0;
        $stats['calmar_ratio'] = 0;
    }
    
    // ============================
    // ESTADÍSTICAS POR SÍMBOLO
    // ============================
    $stmt = $pdo->prepare("
        SELECT 
            symbol,
            COUNT(CASE WHEN entry = 1 THEN 1 END) as trades,
            SUM(CASE WHEN entry = 1 THEN profit + swap + commission ELSE 0 END) as total_pl,
            COUNT(CASE WHEN entry = 1 AND profit > 0 THEN 1 END) as wins,
            COUNT(CASE WHEN entry = 1 AND profit < 0 THEN 1 END) as losses
        FROM deals
        WHERE account_login = :login
        AND type IN (0,1)
        AND symbol != ''
        GROUP BY symbol
        ORDER BY total_pl DESC
        LIMIT 10
    ");
    $stmt->execute(['login' => $login]);
    $symbol_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================
    // ESTADÍSTICAS TEMPORALES
    // ============================
    $stmt = $pdo->prepare("
        SELECT 
            MIN(time) as first_trade,
            MAX(time) as last_trade,
            COUNT(DISTINCT DATE(time)) as trading_days
        FROM deals
        WHERE account_login = :login
        AND type IN (0,1)
        AND entry = 1
    ");
    $stmt->execute(['login' => $login]);
    $temporal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['first_trade'] = $temporal['first_trade'];
    $stats['last_trade'] = $temporal['last_trade'];
    $stats['trading_days'] = $temporal['trading_days'];
    $stats['avg_trades_per_day'] = $temporal['trading_days'] > 0 ? 
        $stats['total_trades'] / $temporal['trading_days'] : 0;
    
    // ============================
    // RACHAS
    // ============================
    $stmt = $pdo->prepare("
        SELECT profit
        FROM deals
        WHERE account_login = :login
        AND type IN (0,1)
        AND entry = 1
        ORDER BY time ASC
    ");
    $stmt->execute(['login' => $login]);
    $profit_sequence = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $consecutive_wins = 0;
    $consecutive_losses = 0;
    $max_consecutive_wins = 0;
    $max_consecutive_losses = 0;
    $last_result = null;
    
    foreach ($profit_sequence as $profit) {
        if ($profit > 0) {
            if ($last_result === 'win') {
                $consecutive_wins++;
            } else {
                $consecutive_wins = 1;
            }
            $max_consecutive_wins = max($max_consecutive_wins, $consecutive_wins);
            $last_result = 'win';
        } elseif ($profit < 0) {
            if ($last_result === 'loss') {
                $consecutive_losses++;
            } else {
                $consecutive_losses = 1;
            }
            $max_consecutive_losses = max($max_consecutive_losses, $consecutive_losses);
            $last_result = 'loss';
        }
    }
    
    $stats['max_consecutive_wins'] = $max_consecutive_wins;
    $stats['max_consecutive_losses'] = $max_consecutive_losses;
    
    // Preparar respuesta
    $response['success'] = true;
    $response['statistics'] = $stats;
    $response['symbol_performance'] = $symbol_stats;
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
    error_log("[Elysium Statistics API] Error: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>