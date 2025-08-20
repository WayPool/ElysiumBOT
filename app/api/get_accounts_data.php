<?php
//+------------------------------------------------------------------+
//| Copyright 2025, Elysium Media FZCO                              |
//| https://www.elysiumdubai.net                                    |
//| API mejorada con cálculo de P&L real                            |
//| Versión 7.0 - Sistema de Reportes de Trading                    |
//+------------------------------------------------------------------+

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'elysium_dashboard');
define('DB_USER', 'elysium_dashboard');
define('DB_PASS', '@01Mwdsz4');

// Inicializar respuesta
$response = [
    'success' => false,
    'error' => null,
    'accounts' => [],
    'statistics' => [],
    'brokers' => []
];

try {
    // Conexión a base de datos
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // ====================================
    // FUNCIÓN PARA CALCULAR P&L REAL
    // ====================================
    function calculateRealPL($pdo, $account_login, $current_balance) {
        // Calcular TODOS los depósitos y retiros a lo largo del tiempo
        $stmt = $pdo->prepare("
            SELECT 
                type,
                profit,
                time,
                comment
            FROM deals 
            WHERE account_login = :login
            AND type = 2
            ORDER BY time ASC
        ");
        $stmt->execute(['login' => $account_login]);
        $transactions = $stmt->fetchAll();
        
        $total_deposits = 0;
        $total_withdrawals = 0;
        $deposit_history = [];
        
        // Procesar cada transacción de depósito/retiro
        foreach ($transactions as $trans) {
            $amount = floatval($trans['profit']);
            
            if ($amount > 0) {
                // Es un depósito
                $total_deposits += $amount;
                $deposit_history[] = [
                    'date' => $trans['time'],
                    'amount' => $amount,
                    'type' => 'deposit',
                    'comment' => $trans['comment']
                ];
            } else {
                // Es un retiro (valor negativo)
                $total_withdrawals += abs($amount);
                $deposit_history[] = [
                    'date' => $trans['time'],
                    'amount' => $amount,
                    'type' => 'withdrawal',
                    'comment' => $trans['comment']
                ];
            }
        }
        
        // Capital Neto = Total de Depósitos - Total de Retiros
        $net_deposits = $total_deposits - $total_withdrawals;
        
        // Si no hay depósitos registrados, intentar calcular desde el historial de trades
        if ($net_deposits <= 0) {
            // Buscar el balance más antiguo conocido
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(profit + IFNULL(commission, 0) + IFNULL(swap, 0)) as total_pl
                FROM deals 
                WHERE account_login = :login 
                AND type IN (0, 1)
            ");
            $stmt->execute(['login' => $account_login]);
            $result = $stmt->fetch();
            
            $total_trading_pl = floatval($result['total_pl'] ?? 0);
            
            // Estimar el capital inicial: Balance actual - P&L de trading
            if ($current_balance > 0 && $total_trading_pl != 0) {
                $estimated_initial = $current_balance - $total_trading_pl;
                if ($estimated_initial > 0) {
                    $net_deposits = $estimated_initial;
                    $total_deposits = $estimated_initial;
                }
            } else {
                // Último recurso: usar el balance actual como referencia
                $net_deposits = $current_balance;
                $total_deposits = $current_balance;
            }
        }
        
        // P&L Real = Balance Actual - Capital Neto Depositado
        $real_pl = $current_balance - $net_deposits;
        
        // Calcular porcentaje de rendimiento
        $real_pl_percentage = $net_deposits > 0 ? ($real_pl / $net_deposits * 100) : 0;
        
        return [
            'net_deposits' => $net_deposits,
            'total_deposits' => $total_deposits,
            'total_withdrawals' => $total_withdrawals,
            'real_pl' => $real_pl,
            'real_pl_percentage' => $real_pl_percentage,
            'deposit_count' => count(array_filter($deposit_history, fn($d) => $d['type'] === 'deposit')),
            'withdrawal_count' => count(array_filter($deposit_history, fn($d) => $d['type'] === 'withdrawal')),
            'first_deposit_date' => !empty($deposit_history) ? $deposit_history[0]['date'] : null,
            'last_transaction_date' => !empty($deposit_history) ? end($deposit_history)['date'] : null
        ];
    }
    
    // ====================================
    // OBTENER TODAS LAS CUENTAS
    // ====================================
    $stmt = $pdo->query("
        SELECT 
            a.*,
            COUNT(DISTINCT p.ticket) as open_positions
        FROM accounts a
        LEFT JOIN positions p ON a.login = p.account_login
        WHERE a.is_historical = 0
        GROUP BY a.login
        ORDER BY a.equity DESC
    ");
    
    $accounts = $stmt->fetchAll();
    
    // ====================================
    // ENRIQUECER CADA CUENTA CON P&L REAL
    // ====================================
    foreach ($accounts as &$account) {
        $login = $account['login'];
        $current_balance = floatval($account['balance']);
        $current_equity = floatval($account['equity']);
        
        // Calcular P&L real basado en depósitos
        $pl_data = calculateRealPL($pdo, $login, $current_balance);
        
        // IMPORTANTE: Mantener ambos P&L
        // 1. P&L Flotante (posiciones abiertas) - profit original del EA
        $floating_profit = $current_equity - $current_balance;
        
        // 2. P&L Total (rendimiento sobre capital depositado)
        $total_pl = $pl_data['real_pl'];
        
        // Asignar valores a la cuenta
        $account['floating_profit'] = $floating_profit; // P&L de posiciones abiertas
        $account['profit'] = $total_pl; // P&L total sobre capital
        $account['profit_percentage'] = $pl_data['real_pl_percentage'];
        $account['net_deposits'] = $pl_data['net_deposits'];
        $account['total_deposits'] = $pl_data['total_deposits'];
        $account['total_withdrawals'] = $pl_data['total_withdrawals'];
        
        // Obtener estadísticas de trades
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_trades,
                COUNT(CASE WHEN profit > 0 THEN 1 END) as wins,
                COUNT(CASE WHEN profit < 0 THEN 1 END) as losses,
                MIN(time) as first_trade,
                MAX(time) as last_trade
            FROM deals 
            WHERE account_login = :login 
            AND type IN (0,1) 
            AND entry = 1
        ");
        $stmt->execute(['login' => $login]);
        $stats = $stmt->fetch();
        
        $account['total_trades'] = intval($stats['total_trades']);
        $account['winning_trades'] = intval($stats['wins']);
        $account['losing_trades'] = intval($stats['losses']);
        $account['first_trade_date'] = $stats['first_trade'];
        $account['last_trade_date'] = $stats['last_trade'];
        
        $totalTrades = $account['winning_trades'] + $account['losing_trades'];
        $account['win_rate'] = $totalTrades > 0 ? ($account['winning_trades'] / $totalTrades) * 100 : 0;
        
        // Convertir tipos de datos
        $account['login'] = strval($account['login']);
        $account['balance'] = floatval($account['balance']);
        $account['equity'] = floatval($account['equity']);
        $account['margin'] = floatval($account['margin']);
        $account['margin_free'] = floatval($account['margin_free']);
        $account['margin_level'] = floatval($account['margin_level']);
        $account['credit'] = floatval($account['credit']);
        $account['leverage'] = intval($account['leverage']);
        $account['open_positions'] = intval($account['open_positions']);
        
        // Formato de fecha
        if ($account['last_update']) {
            $account['last_update'] = date('Y-m-d H:i:s', strtotime($account['last_update']));
        }
    }
    
    // ====================================
    // BROKERS ÚNICOS
    // ====================================
    $brokers = array_values(array_unique(array_filter(array_column($accounts, 'company'))));
    
    // ====================================
    // ESTADÍSTICAS GLOBALES
    // ====================================
    $total_accounts = count($accounts);
    $total_equity = 0;
    $total_balance = 0;
    $total_margin = 0;
    $total_realized_pl = 0;
    $total_floating_pl = 0;
    $total_net_deposits = 0;
    $active_accounts = 0;
    $warning_accounts = 0;
    $critical_accounts = 0;
    $profitable_accounts = 0;
    $losing_accounts = 0;
    
    foreach ($accounts as $acc) {
        $total_equity += $acc['equity'];
        $total_balance += $acc['balance'];
        $total_margin += $acc['margin'];
        $total_realized_pl += $acc['profit']; // P&L real calculado
        $total_floating_pl += $acc['floating_profit'];
        $total_net_deposits += $acc['net_deposits'];
        
        // Determinar estado de la cuenta
        $marginLevel = $acc['margin_level'];
        $lastUpdate = strtotime($acc['last_update']);
        $hoursSinceUpdate = (time() - $lastUpdate) / 3600;
        
        // Cuenta activa si se actualizó en las últimas 24 horas
        if ($hoursSinceUpdate < 24) {
            $active_accounts++;
        }
        
        // Estados de margen (solo si hay margen usado)
        if ($acc['margin'] > 0) {
            if ($marginLevel > 0 && $marginLevel < 200) {
                $critical_accounts++;
            } elseif ($marginLevel >= 200 && $marginLevel < 500) {
                $warning_accounts++;
            }
        }
        
        // Contar cuentas rentables
        if ($acc['profit'] > 0) {
            $profitable_accounts++;
        } elseif ($acc['profit'] < 0) {
            $losing_accounts++;
        }
    }
    
    // Calcular porcentaje global de P&L
    $global_pl_percentage = $total_net_deposits > 0 ? 
        ($total_realized_pl / $total_net_deposits * 100) : 0;
    
    // ====================================
    // RESPUESTA FINAL
    // ====================================
    $response = [
        'success' => true,
        'timestamp' => time(),
        'accounts' => array_values($accounts),
        'statistics' => [
            'total_accounts' => $total_accounts,
            'total_equity' => round($total_equity, 2),
            'total_balance' => round($total_balance, 2),
            'total_margin' => round($total_margin, 2),
            'total_realized_pl' => round($total_realized_pl, 2),
            'total_floating_pl' => round($total_floating_pl, 2),
            'total_net_deposits' => round($total_net_deposits, 2),
            'global_pl_percentage' => round($global_pl_percentage, 2),
            'active_accounts' => $active_accounts,
            'warning_accounts' => $warning_accounts,
            'critical_accounts' => $critical_accounts,
            'profitable_accounts' => $profitable_accounts,
            'losing_accounts' => $losing_accounts,
            'average_equity' => $total_accounts > 0 ? round($total_equity / $total_accounts, 2) : 0,
            'average_balance' => $total_accounts > 0 ? round($total_balance / $total_accounts, 2) : 0,
            'average_pl' => $total_accounts > 0 ? round($total_realized_pl / $total_accounts, 2) : 0
        ],
        'brokers' => $brokers
    ];
    
} catch (PDOException $e) {
    $response['error'] = 'Database error: ' . $e->getMessage();
    error_log("[Elysium API] DB Error: " . $e->getMessage());
} catch (Exception $e) {
    $response['error'] = 'General error: ' . $e->getMessage();
    error_log("[Elysium API] Error: " . $e->getMessage());
}

// Enviar respuesta
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>