<?php
//+------------------------------------------------------------------+
//| Copyright 2025, Elysium Media FZCO                              |
//| https://www.elysiumdubai.net                                    |
//| API para obtener posiciones abiertas de una cuenta              |
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
    
    if (!$account_login) {
        throw new Exception('Account login is required');
    }
    
    // Obtener posiciones abiertas
    $stmt = $pdo->prepare("
        SELECT 
            p.ticket,
            p.time,
            p.type,
            p.symbol,
            p.volume,
            p.price_open,
            p.sl,
            p.tp,
            p.price_current,
            p.swap,
            p.profit,
            p.comment,
            p.magic,
            TIMESTAMPDIFF(MINUTE, p.time, NOW()) as duration_minutes,
            a.currency
        FROM positions p
        INNER JOIN accounts a ON p.account_login = a.login
        WHERE p.account_login = :login
        ORDER BY p.time DESC
    ");
    
    $stmt->execute(['login' => $account_login]);
    $positions = $stmt->fetchAll();
    
    // Calcular estadísticas
    $total_positions = count($positions);
    $total_volume = 0;
    $total_profit = 0;
    $total_swap = 0;
    $buy_positions = 0;
    $sell_positions = 0;
    $profitable_positions = 0;
    $losing_positions = 0;
    $symbols = [];
    
    foreach ($positions as &$position) {
        // Convertir valores numéricos
        $position['ticket'] = intval($position['ticket']);
        $position['type'] = intval($position['type']);
        $position['volume'] = floatval($position['volume']);
        $position['price_open'] = floatval($position['price_open']);
        $position['price_current'] = floatval($position['price_current']);
        $position['sl'] = floatval($position['sl']);
        $position['tp'] = floatval($position['tp']);
        $position['swap'] = floatval($position['swap']);
        $position['profit'] = floatval($position['profit']);
        $position['magic'] = intval($position['magic']);
        $position['duration_minutes'] = intval($position['duration_minutes']);
        
        // Calcular duración legible
        $duration_hours = floor($position['duration_minutes'] / 60);
        $duration_days = floor($duration_hours / 24);
        
        if ($duration_days > 0) {
            $position['duration_text'] = $duration_days . 'd ' . ($duration_hours % 24) . 'h';
        } elseif ($duration_hours > 0) {
            $position['duration_text'] = $duration_hours . 'h ' . ($position['duration_minutes'] % 60) . 'm';
        } else {
            $position['duration_text'] = $position['duration_minutes'] . 'm';
        }
        
        // Calcular pips (simplificado)
        $pips = 0;
        if ($position['type'] == 0) { // BUY
            $pips = ($position['price_current'] - $position['price_open']) * 10000;
        } else { // SELL
            $pips = ($position['price_open'] - $position['price_current']) * 10000;
        }
        $position['pips'] = round($pips, 1);
        
        // Acumular estadísticas
        $total_volume += $position['volume'];
        $total_profit += $position['profit'];
        $total_swap += $position['swap'];
        
        if ($position['type'] == 0) {
            $buy_positions++;
        } else {
            $sell_positions++;
        }
        
        if ($position['profit'] >= 0) {
            $profitable_positions++;
        } else {
            $losing_positions++;
        }
        
        // Contar por símbolo
        if (!isset($symbols[$position['symbol']])) {
            $symbols[$position['symbol']] = [
                'count' => 0,
                'volume' => 0,
                'profit' => 0
            ];
        }
        $symbols[$position['symbol']]['count']++;
        $symbols[$position['symbol']]['volume'] += $position['volume'];
        $symbols[$position['symbol']]['profit'] += $position['profit'];
    }
    
    // Ordenar símbolos por volumen
    arsort($symbols);
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'timestamp' => time(),
        'account_login' => $account_login,
        'positions' => $positions,
        'summary' => [
            'total_positions' => $total_positions,
            'buy_positions' => $buy_positions,
            'sell_positions' => $sell_positions,
            'total_volume' => round($total_volume, 2),
            'total_profit' => round($total_profit, 2),
            'total_swap' => round($total_swap, 2),
            'net_profit' => round($total_profit + $total_swap, 2),
            'profitable_positions' => $profitable_positions,
            'losing_positions' => $losing_positions,
            'win_rate' => $total_positions > 0 ? round(($profitable_positions / $total_positions) * 100, 2) : 0
        ],
        'symbols' => $symbols
    ];
    
} catch (PDOException $e) {
    $response = [
        'success' => false,
        'error' => 'Database error',
        'message' => 'Unable to retrieve positions'
    ];
    error_log("[Elysium Positions API] DB Error: " . $e->getMessage());
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'Invalid request',
        'message' => $e->getMessage()
    ];
    error_log("[Elysium Positions API] Error: " . $e->getMessage());
}

// Enviar respuesta
echo json_encode($response, JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION);
?>