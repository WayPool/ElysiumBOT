<?php
//+------------------------------------------------------------------+
//| Copyright 2025, Elysium Media FZCO                              |
//| https://www.elysiumdubai.net                                    |
//| API para obtener histórico de operaciones de una cuenta         |
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
    
    // Validar parámetros
    $account_login = isset($_GET['login']) ? filter_var($_GET['login'], FILTER_SANITIZE_NUMBER_INT) : null;
    $limit = isset($_GET['limit']) ? min(1000, max(1, intval($_GET['limit']))) : 100;
    $offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
    $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
    $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
    $symbol = isset($_GET['symbol']) ? $_GET['symbol'] : null;
    $type_filter = isset($_GET['type']) ? $_GET['type'] : null;
    
    if (!$account_login) {
        throw new Exception('Account login is required');
    }
    
    // Construir query base
    $query = "
        SELECT 
            d.ticket,
            d.time,
            d.type,
            d.symbol,
            d.volume,
            d.price,
            d.profit,
            d.commission,
            d.swap,
            d.comment,
            d.magic,
            d.reason,
            d.position_id,
            d.entry,
            (d.profit + d.commission + d.swap) as total_pl,
            a.currency
        FROM deals d
        INNER JOIN accounts a ON d.account_login = a.login
        WHERE d.account_login = :login
    ";
    
    $params = ['login' => $account_login];
    
    // Agregar filtros opcionales
    if ($date_from) {
        $query .= " AND d.time >= :date_from";
        $params['date_from'] = $date_from;
    }
    
    if ($date_to) {
        $query .= " AND d.time <= :date_to";
        $params['date_to'] = $date_to;
    }
    
    if ($symbol) {
        $query .= " AND d.symbol = :symbol";
        $params['symbol'] = $symbol;
    }
    
    if ($type_filter !== null) {
        if ($type_filter === 'trades') {
            $query .= " AND d.type IN (0, 1)"; // Solo BUY/SELL
        } elseif ($type_filter === 'deposits') {
            $query .= " AND d.type = 2";
        } elseif ($type_filter === 'withdrawals') {
            $query .= " AND d.type = 3";
        }
    }
    
    // Ordenar y limitar
    $query .= " ORDER BY d.time DESC LIMIT :limit OFFSET :offset";
    
    // Preparar y ejecutar consulta
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $history = $stmt->fetchAll();
    
    // Obtener total de registros (sin límite)
    $count_query = "
        SELECT COUNT(*) as total
        FROM deals d
        WHERE d.account_login = :login
    ";
    
    if ($date_from) {
        $count_query .= " AND d.time >= :date_from";
    }
    if ($date_to) {
        $count_query .= " AND d.time <= :date_to";
    }
    if ($symbol) {
        $count_query .= " AND d.symbol = :symbol";
    }
    if ($type_filter === 'trades') {
        $count_query .= " AND d.type IN (0, 1)";
    } elseif ($type_filter === 'deposits') {
        $count_query .= " AND d.type = 2";
    } elseif ($type_filter === 'withdrawals') {
        $count_query .= " AND d.type = 3";
    }
    
    $count_stmt = $pdo->prepare($count_query);
    unset($params['limit']);
    unset($params['offset']);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue(':' . $key, $value);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetch()['total'];
    
    // Calcular estadísticas del histórico
    $total_trades = 0;
    $winning_trades = 0;
    $losing_trades = 0;
    $total_profit = 0;
    $total_loss = 0;
    $total_commission = 0;
    $total_swap = 0;
    $total_deposits = 0;
    $total_withdrawals = 0;
    $symbols_traded = [];
    $largest_win = 0;
    $largest_loss = 0;
    
    foreach ($history as &$deal) {
        // Convertir valores numéricos
        $deal['ticket'] = intval($deal['ticket']);
        $deal['type'] = intval($deal['type']);
        $deal['volume'] = floatval($deal['volume']);
        $deal['price'] = floatval($deal['price']);
        $deal['profit'] = floatval($deal['profit']);
        $deal['commission'] = floatval($deal['commission']);
        $deal['swap'] = floatval($deal['swap']);
        $deal['total_pl'] = floatval($deal['total_pl']);
        $deal['magic'] = intval($deal['magic']);
        $deal['position_id'] = intval($deal['position_id']);
        $deal['entry'] = intval($deal['entry']);
        
        // Agregar etiqueta de tipo
        $deal['type_label'] = getTypeLabel($deal['type']);
        
        // Acumular estadísticas
        if ($deal['type'] == 0 || $deal['type'] == 1) { // Trades
            $total_trades++;
            
            if ($deal['profit'] >= 0) {
                $winning_trades++;
                $total_profit += $deal['profit'];
                if ($deal['profit'] > $largest_win) {
                    $largest_win = $deal['profit'];
                }
            } else {
                $losing_trades++;
                $total_loss += abs($deal['profit']);
                if (abs($deal['profit']) > $largest_loss) {
                    $largest_loss = abs($deal['profit']);
                }
            }
            
            // Contar símbolos
            if (!in_array($deal['symbol'], $symbols_traded) && !empty($deal['symbol'])) {
                $symbols_traded[] = $deal['symbol'];
            }
        } elseif ($deal['type'] == 2) { // Deposits
            $total_deposits += abs($deal['profit']);
        } elseif ($deal['type'] == 3) { // Withdrawals
            $total_withdrawals += abs($deal['profit']);
        }
        
        $total_commission += $deal['commission'];
        $total_swap += $deal['swap'];
    }
    
    // Calcular métricas adicionales
    $win_rate = $total_trades > 0 ? round(($winning_trades / $total_trades) * 100, 2) : 0;
    $profit_factor = $total_loss > 0 ? round($total_profit / $total_loss, 2) : 0;
    $average_win = $winning_trades > 0 ? round($total_profit / $winning_trades, 2) : 0;
    $average_loss = $losing_trades > 0 ? round($total_loss / $losing_trades, 2) : 0;
    $expectancy = $total_trades > 0 ? round(($total_profit - $total_loss) / $total_trades, 2) : 0;
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'timestamp' => time(),
        'account_login' => $account_login,
        'history' => $history,
        'pagination' => [
            'total_records' => $total_records,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $total_records
        ],
        'statistics' => [
            'total_trades' => $total_trades,
            'winning_trades' => $winning_trades,
            'losing_trades' => $losing_trades,
            'win_rate' => $win_rate,
            'total_profit' => round($total_profit, 2),
            'total_loss' => round($total_loss, 2),
            'net_profit' => round($total_profit - $total_loss, 2),
            'profit_factor' => $profit_factor,
            'average_win' => $average_win,
            'average_loss' => $average_loss,
            'expectancy' => $expectancy,
            'largest_win' => round($largest_win, 2),
            'largest_loss' => round($largest_loss, 2),
            'total_commission' => round($total_commission, 2),
            'total_swap' => round($total_swap, 2),
            'total_deposits' => round($total_deposits, 2),
            'total_withdrawals' => round($total_withdrawals, 2),
            'symbols_traded' => count($symbols_traded)
        ]
    ];
    
} catch (PDOException $e) {
    $response = [
        'success' => false,
        'error' => 'Database error',
        'message' => 'Unable to retrieve history'
    ];
    error_log("[Elysium History API] DB Error: " . $e->getMessage());
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'Invalid request',
        'message' => $e->getMessage()
    ];
    error_log("[Elysium History API] Error: " . $e->getMessage());
}

/**
 * Obtiene la etiqueta del tipo de operación
 */
function getTypeLabel($type) {
    $labels = [
        0 => 'BUY',
        1 => 'SELL',
        2 => 'DEPOSIT',
        3 => 'WITHDRAWAL',
        4 => 'CREDIT',
        5 => 'CHARGE',
        6 => 'BONUS',
        7 => 'COMMISSION',
        8 => 'COMMISSION_DAILY',
        9 => 'COMMISSION_MONTHLY',
        10 => 'AGENT_COMMISSION',
        11 => 'INTEREST',
        12 => 'DIVIDEND'
    ];
    
    return isset($labels[$type]) ? $labels[$type] : 'OTHER';
}

// Enviar respuesta
echo json_encode($response, JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION);
?>