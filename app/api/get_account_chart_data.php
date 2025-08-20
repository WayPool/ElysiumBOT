<?php
//+------------------------------------------------------------------+
//| Copyright 2025, Elysium Media FZCO                              |
//| https://www.elysiumdubai.net                                    |
//| API para obtener datos COMPLETOS de charts de cuentas           |
//| Versión 7.0 - Sistema de Reportes de Trading                    |
//+------------------------------------------------------------------+

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');

// Configuración de base de datos - NO HARDCODEAR
define('DB_HOST', 'localhost');
define('DB_NAME', 'elysium_dashboard');
define('DB_USER', 'elysium_dashboard');
define('DB_PASS', '@01Mwdsz4');

// Configuración de performance
ini_set('memory_limit', '256M');
set_time_limit(60);

try {
    // Conexión optimizada con opciones de performance
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]
    );
    
    // Inicializar respuesta
    $response = [
        'success' => true,
        'timestamp' => time(),
        'charts_data' => [],
        'metadata' => []
    ];
    
    // Obtener login específico o todos
    $account_login = isset($_GET['login']) ? filter_var($_GET['login'], FILTER_SANITIZE_NUMBER_INT) : null;
    
    // ====================================
    // FUNCIÓN PARA CONSTRUIR HISTORIAL COMPLETO
    // ====================================
    function buildCompleteHistory($pdo, $login) {
        // 1. Obtener información actual de la cuenta
        $stmt = $pdo->prepare("
            SELECT 
                login,
                equity,
                balance,
                profit,
                currency,
                UNIX_TIMESTAMP(last_update) as last_update_ts
            FROM accounts 
            WHERE login = :login 
            LIMIT 1
        ");
        $stmt->execute(['login' => $login]);
        $account = $stmt->fetch();
        
        if (!$account) {
            return null;
        }
        
        $current_equity = floatval($account['equity']);
        $current_balance = floatval($account['balance']);
        $currency = $account['currency'];
        
        // 2. Obtener TODO el historial de deals ordenado cronológicamente
        $stmt = $pdo->prepare("
            SELECT 
                DATE(time) as date,
                time,
                profit,
                commission,
                swap,
                type,
                type_desc,
                volume,
                symbol,
                (profit + IFNULL(commission, 0) + IFNULL(swap, 0)) as total_pl
            FROM deals 
            WHERE account_login = :login
            ORDER BY time ASC
        ");
        $stmt->execute(['login' => $login]);
        $deals = $stmt->fetchAll();
        
        // 3. Construir arrays de evolución histórica
        $equity_history = [];
        $balance_history = [];
        $dates = [];
        $volume_history = [];
        $trade_count = 0;
        $deposits_total = 0;
        $withdrawals_total = 0;
        
        if (count($deals) > 0) {
            // Encontrar el capital inicial (primer depósito o primera operación)
            $running_balance = 0;
            $running_equity = 0;
            $started = false;
            
            foreach ($deals as $deal) {
                $deal_date = substr($deal['time'], 0, 10);
                $deal_type = intval($deal['type']);
                $profit = floatval($deal['profit']);
                $total_pl = floatval($deal['total_pl']);
                
                // Detectar depósito inicial (type = 2 y profit > 0)
                if ($deal_type == 2 && $profit > 0 && !$started) {
                    $running_balance = $profit;
                    $running_equity = $profit;
                    $deposits_total += $profit;
                    $started = true;
                    
                    $dates[] = $deal_date;
                    $balance_history[] = $running_balance;
                    $equity_history[] = $running_equity;
                    $volume_history[] = 0;
                }
                // Otros depósitos
                else if ($deal_type == 2 && $profit > 0 && $started) {
                    $running_balance += $profit;
                    $running_equity += $profit;
                    $deposits_total += $profit;
                    
                    $dates[] = $deal_date;
                    $balance_history[] = $running_balance;
                    $equity_history[] = $running_equity;
                    $volume_history[] = floatval($deal['volume']);
                }
                // Retiros (type = 2 y profit < 0)
                else if ($deal_type == 2 && $profit < 0 && $started) {
                    $running_balance += $profit;
                    $running_equity += $profit;
                    $withdrawals_total += abs($profit);
                    
                    $dates[] = $deal_date;
                    $balance_history[] = $running_balance;
                    $equity_history[] = $running_equity;
                    $volume_history[] = floatval($deal['volume']);
                }
                // Operaciones de trading (type = 0 o 1)
                else if (($deal_type == 0 || $deal_type == 1) && $started) {
                    $running_balance += $total_pl;
                    $running_equity = $running_balance; // En histórico, equity = balance después de cerrar
                    $trade_count++;
                    
                    // Agregar punto solo si es un día nuevo o cambio significativo
                    if (empty($dates) || $dates[count($dates)-1] != $deal_date || abs($total_pl) > 10) {
                        $dates[] = $deal_date;
                        $balance_history[] = $running_balance;
                        $equity_history[] = $running_equity;
                        $volume_history[] = floatval($deal['volume']);
                    } else {
                        // Actualizar el último punto del mismo día
                        $balance_history[count($balance_history)-1] = $running_balance;
                        $equity_history[count($equity_history)-1] = $running_equity;
                        $volume_history[count($volume_history)-1] += floatval($deal['volume']);
                    }
                }
            }
            
            // Si no encontramos depósito inicial pero hay trades
            if (!$started && count($deals) > 0) {
                // Calcular hacia atrás desde el balance actual
                $running_balance = $current_balance;
                $running_equity = $current_balance;
                
                // Restar todas las ganancias/pérdidas para encontrar el balance inicial
                foreach (array_reverse($deals) as $deal) {
                    if ($deal['type'] == 0 || $deal['type'] == 1) {
                        $running_balance -= floatval($deal['total_pl']);
                    }
                }
                
                // Reconstruir hacia adelante
                $started = true;
                $balance_history = [$running_balance];
                $equity_history = [$running_equity];
                $dates = [substr($deals[0]['time'], 0, 10)];
                $volume_history = [0];
                
                foreach ($deals as $deal) {
                    $deal_date = substr($deal['time'], 0, 10);
                    if (($deal['type'] == 0 || $deal['type'] == 1)) {
                        $running_balance += floatval($deal['total_pl']);
                        $running_equity = $running_balance;
                        $trade_count++;
                        
                        if ($dates[count($dates)-1] != $deal_date) {
                            $dates[] = $deal_date;
                            $balance_history[] = $running_balance;
                            $equity_history[] = $running_equity;
                            $volume_history[] = floatval($deal['volume']);
                        } else {
                            $balance_history[count($balance_history)-1] = $running_balance;
                            $equity_history[count($equity_history)-1] = $running_equity;
                            $volume_history[count($volume_history)-1] += floatval($deal['volume']);
                        }
                    }
                }
            }
            
            // Agregar punto actual si es diferente al último
            if (!empty($dates)) {
                $today = date('Y-m-d');
                if ($dates[count($dates)-1] != $today) {
                    $dates[] = $today;
                    $balance_history[] = $current_balance;
                    $equity_history[] = $current_equity;
                    $volume_history[] = 0;
                } else {
                    // Actualizar último punto con valores actuales
                    $balance_history[count($balance_history)-1] = $current_balance;
                    $equity_history[count($equity_history)-1] = $current_equity;
                }
            }
            
        } else {
            // No hay deals, crear línea simple
            $dates = [date('Y-m-d', strtotime('-30 days')), date('Y-m-d')];
            $balance_history = [$current_balance, $current_balance];
            $equity_history = [$current_balance, $current_equity];
            $volume_history = [0, 0];
        }
        
        // 4. Calcular estadísticas adicionales
        $max_equity = !empty($equity_history) ? max($equity_history) : $current_equity;
        $min_equity = !empty($equity_history) ? min($equity_history) : $current_equity;
        $max_balance = !empty($balance_history) ? max($balance_history) : $current_balance;
        $min_balance = !empty($balance_history) ? min($balance_history) : $current_balance;
        
        // Calcular drawdown
        $max_drawdown = 0;
        $current_peak = 0;
        foreach ($equity_history as $equity) {
            if ($equity > $current_peak) {
                $current_peak = $equity;
            }
            if ($current_peak > 0) {
                $drawdown = (($current_peak - $equity) / $current_peak) * 100;
                if ($drawdown > $max_drawdown) {
                    $max_drawdown = $drawdown;
                }
            }
        }
        
        // 5. Preparar respuesta con todos los datos
        return [
            'dates' => $dates,
            'equity' => $equity_history,
            'balance' => $balance_history,
            'volume' => $volume_history,
            'points' => count($equity_history),
            'current_equity' => $current_equity,
            'current_balance' => $current_balance,
            'currency' => $currency,
            'statistics' => [
                'max_equity' => $max_equity,
                'min_equity' => $min_equity,
                'max_balance' => $max_balance,
                'min_balance' => $min_balance,
                'max_drawdown' => round($max_drawdown, 2),
                'total_trades' => $trade_count,
                'deposits_total' => $deposits_total,
                'withdrawals_total' => $withdrawals_total,
                'net_deposits' => $deposits_total - $withdrawals_total,
                'first_date' => !empty($dates) ? $dates[0] : null,
                'last_date' => !empty($dates) ? $dates[count($dates)-1] : null
            ]
        ];
    }
    
    // ====================================
    // PROCESAR SOLICITUD
    // ====================================
    
    if ($account_login) {
        // Datos para una cuenta específica
        $chart_data = buildCompleteHistory($pdo, $account_login);
        
        if ($chart_data) {
            $response['charts_data'][$account_login] = $chart_data;
            $response['metadata'] = [
                'account' => $account_login,
                'generated_at' => date('Y-m-d H:i:s'),
                'data_points' => $chart_data['points'],
                'date_range' => [
                    'from' => $chart_data['statistics']['first_date'],
                    'to' => $chart_data['statistics']['last_date']
                ]
            ];
        } else {
            $response['success'] = false;
            $response['error'] = 'Account not found';
        }
        
    } else {
        // Datos para todas las cuentas activas
        $stmt = $pdo->query("
            SELECT login 
            FROM accounts 
            WHERE is_historical = 0
            ORDER BY equity DESC
            LIMIT 50
        ");
        $accounts = $stmt->fetchAll();
        
        $total_processed = 0;
        $total_data_points = 0;
        
        foreach ($accounts as $account) {
            $login = $account['login'];
            $chart_data = buildCompleteHistory($pdo, $login);
            
            if ($chart_data) {
                // Para listados masivos, enviar versión simplificada
                // Reducir puntos si hay demasiados
                if ($chart_data['points'] > 100) {
                    $step = ceil($chart_data['points'] / 100);
                    $simplified_equity = [];
                    $simplified_balance = [];
                    $simplified_dates = [];
                    
                    for ($i = 0; $i < $chart_data['points']; $i += $step) {
                        $simplified_equity[] = $chart_data['equity'][$i];
                        $simplified_balance[] = $chart_data['balance'][$i];
                        $simplified_dates[] = $chart_data['dates'][$i];
                    }
                    
                    // Asegurar que el último punto esté incluido
                    if ($simplified_equity[count($simplified_equity)-1] != $chart_data['equity'][$chart_data['points']-1]) {
                        $simplified_equity[] = $chart_data['equity'][$chart_data['points']-1];
                        $simplified_balance[] = $chart_data['balance'][$chart_data['points']-1];
                        $simplified_dates[] = $chart_data['dates'][$chart_data['points']-1];
                    }
                    
                    $response['charts_data'][$login] = [
                        'dates' => $simplified_dates,
                        'equity' => $simplified_equity,
                        'balance' => $simplified_balance,
                        'points' => count($simplified_equity),
                        'current_equity' => $chart_data['current_equity'],
                        'current_balance' => $chart_data['current_balance'],
                        'currency' => $chart_data['currency'],
                        'simplified' => true,
                        'original_points' => $chart_data['points']
                    ];
                } else {
                    $response['charts_data'][$login] = $chart_data;
                }
                
                $total_processed++;
                $total_data_points += $chart_data['points'];
            }
        }
        
        $response['metadata'] = [
            'accounts_processed' => $total_processed,
            'total_data_points' => $total_data_points,
            'generated_at' => date('Y-m-d H:i:s'),
            'mode' => 'batch'
        ];
    }
    
    // Log de performance para monitoreo
    $execution_time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
    $response['metadata']['execution_time'] = round($execution_time, 3) . 's';
    $response['metadata']['memory_used'] = round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB';
    
} catch (PDOException $e) {
    $response = [
        'success' => false,
        'error' => 'Database error occurred',
        'message' => 'Unable to retrieve chart data',
        'timestamp' => time()
    ];
    error_log("[Elysium Charts API] DB Error: " . $e->getMessage());
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'System error occurred',
        'message' => 'Unable to process request',
        'timestamp' => time()
    ];
    error_log("[Elysium Charts API] Error: " . $e->getMessage());
}

// Enviar respuesta con compresión si es posible
if (!ob_get_level()) {
    ob_start('ob_gzhandler');
}

echo json_encode($response, JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION);

if (ob_get_level()) {
    ob_end_flush();
}
?>