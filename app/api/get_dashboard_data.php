<?php
//+------------------------------------------------------------------+
//| get_dashboard_data.php                                          |
//| API Principal para Dashboard Elysium v7.0                       |
//| Copyright 2025, Elysium Media FZCO                              |
//| Gestión Profesional de Fondos de Trading                        |
//+------------------------------------------------------------------+

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Cache-Control: no-cache, must-revalidate');

// Configuración de base de datos
$host = 'localhost';
$dbname = 'elysium_dashboard';
$user = 'elysium_dashboard';
$pass = '@01Mwdsz4';

// Respuesta estructurada
$response = [
    'success' => false,
    'timestamp' => time(),
    'data' => []
];

try {
    // Conexión a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Obtener parámetros de filtro
    $period = $_GET['period'] ?? '30D';
    $broker_filter = $_GET['broker'] ?? 'all';
    $account_filter = $_GET['account'] ?? 'all';
    
    // Calcular fecha inicial según periodo
    $date_from = null;
    switch($period) {
        case 'RT':
        case '1D':
            $date_from = date('Y-m-d 00:00:00');
            break;
        case '7D':
            $date_from = date('Y-m-d H:i:s', strtotime('-7 days'));
            break;
        case '30D':
            $date_from = date('Y-m-d H:i:s', strtotime('-30 days'));
            break;
        case '90D':
            $date_from = date('Y-m-d H:i:s', strtotime('-90 days'));
            break;
        case 'YTD':
            $date_from = date('Y-01-01 00:00:00');
            break;
        case 'ALL':
        default:
            $date_from = '2020-01-01 00:00:00';
    }
    
    // ============================
    // SECCIÓN NUEVA: ANÁLISIS DE BOTS POR MAGIC NUMBER
    // ============================
    
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
    
    // Query para obtener estadísticas de bots
    $bots_query = "
        SELECT 
            d.magic as magic_number,
            COUNT(CASE WHEN d.entry = 1 THEN 1 END) as total_trades,
            SUM(CASE WHEN d.entry = 1 THEN d.profit + d.commission + d.swap ELSE 0 END) as pnl,
            SUM(CASE WHEN d.entry = 1 AND d.profit > 0 THEN 1 ELSE 0 END) as winning_trades,
            SUM(CASE WHEN d.entry = 1 AND d.profit < 0 THEN 1 ELSE 0 END) as losing_trades,
            MAX(d.time) as last_trade,
            MIN(d.time) as first_trade
        FROM deals d
        WHERE d.type IN (0, 1)
        AND d.magic IS NOT NULL
        AND d.magic != 0
        AND d.time >= :date_from
        GROUP BY d.magic
        ORDER BY pnl DESC
    ";
    
    $stmt = $pdo->prepare($bots_query);
    $stmt->execute(['date_from' => $date_from]);
    $bots_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar datos de bots
    $bots_performance = [];
    $total_bots_pnl = 0;
    $active_bots_count = 0;
    
    foreach ($bots_data as $bot) {
        $magicNumber = intval($bot['magic_number']);
        $botInfo = $botNames[$magicNumber] ?? [
            'name' => 'BOT-' . $magicNumber,
            'category' => 'unknown'
        ];
        
        $winRate = $bot['total_trades'] > 0 ? 
            ($bot['winning_trades'] / $bot['total_trades']) * 100 : 0;
        
        $isActive = (strtotime($bot['last_trade']) > strtotime('-7 days'));
        if ($isActive) $active_bots_count++;
        
        $total_bots_pnl += floatval($bot['pnl']);
        
        $bots_performance[] = [
            'magic_number' => $magicNumber,
            'name' => $botInfo['name'],
            'category' => $botInfo['category'],
            'pnl' => round($bot['pnl'], 2),
            'total_trades' => intval($bot['total_trades']),
            'win_rate' => round($winRate, 2),
            'is_active' => $isActive,
            'last_trade' => $bot['last_trade']
        ];
    }
    
    // ============================
    // 1. OBTENER DATOS DE CUENTAS (CÓDIGO ORIGINAL)
    // ============================
    $accounts_query = "SELECT * FROM accounts WHERE 1=1";
    $params = [];
    
    // Aplicar filtro de broker si es necesario
    if ($broker_filter !== 'all') {
        if ($broker_filter === 'special') {
            // Broker con formato especial de datos
            $accounts_query .= " AND company LIKE :broker";
            $params['broker'] = '%Special%';
        } else {
            // Formato estándar
            $accounts_query .= " AND company NOT LIKE :broker";
            $params['broker'] = '%Special%';
        }
    }
    
    $accounts_query .= " ORDER BY equity DESC";
    $stmt = $pdo->prepare($accounts_query);
    $stmt->execute($params);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================
    // 2. CALCULAR KPIs PRINCIPALES (CÓDIGO ORIGINAL)
    // ============================
    $total_balance = 0;
    $total_equity = 0;
    $total_margin = 0;
    $total_margin_free = 0;
    $total_profit = 0;
    $total_credit = 0;
    $accounts_count = count($accounts);
    
    foreach ($accounts as $account) {
        $total_balance += floatval($account['balance']);
        $total_equity += floatval($account['equity']);
        $total_margin += floatval($account['margin']);
        $total_margin_free += floatval($account['margin_free']);
        $total_profit += floatval($account['profit']);
        $total_credit += floatval($account['credit']);
    }
    
    // Calcular capital inicial (suma de TODOS los depósitos)
    $stmt = $pdo->prepare("
        SELECT SUM(profit) as initial_capital
        FROM deals
        WHERE type = 2 AND profit > 0
    ");
    $stmt->execute();
    $initial_capital_result = $stmt->fetch();
    $initial_capital = floatval($initial_capital_result['initial_capital'] ?? 0);
    
    // Si no hay depósito inicial registrado, usar el balance actual como referencia
    if ($initial_capital == 0) {
        $initial_capital = $total_balance;
    }
    
    // ============================
    // NUEVO: OBTENER EVOLUCIÓN HISTÓRICA DEL CAPITAL DEPOSITADO (CÓDIGO ORIGINAL)
    // ============================
    
    // Debug: Verificar que hay depósitos
    $check_deposits = $pdo->prepare("SELECT COUNT(*) as count FROM deals WHERE type = 2 AND profit > 0");
    $check_deposits->execute();
    $deposit_count = $check_deposits->fetch()['count'];
    error_log("Total depósitos encontrados en BD: " . $deposit_count);
    
    // Obtener TODOS los depósitos históricos (type = 2 es Balance/Deposit)
    $all_deposits_query = "
        SELECT 
            time,
            profit as amount,
            account_login,
            comment
        FROM deals
        WHERE type = 2 
        AND profit > 0
        ORDER BY time ASC
    ";
    
    $stmt = $pdo->prepare($all_deposits_query);
    $stmt->execute();
    $all_deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log todos los depósitos
    error_log("Depósitos encontrados para evolución: " . count($all_deposits));
    
    // Construir evolución acumulativa del capital
    $capital_evolution = [];
    $capital_timeline = [];
    $running_capital = 0;
    
    // Procesar todos los depósitos cronológicamente
    foreach ($all_deposits as $deposit) {
        $amount = floatval($deposit['amount']);
        $running_capital += $amount;
        $deposit_date = date('Y-m-d H:i:s', strtotime($deposit['time']));
        $capital_timeline[] = $running_capital;
        
        // Debug: Log cada depósito
        error_log("Depósito: " . $deposit_date . " - Monto: " . $amount . " - Acumulado: " . $running_capital);
    }
    
    // Si hay datos en el timeline
    if (!empty($capital_timeline)) {
        $total_points = count($capital_timeline);
        error_log("Total puntos en timeline: " . $total_points);
        
        if ($total_points <= 20) {
            // Si hay 20 o menos puntos, usar todos
            $capital_evolution = $capital_timeline;
        } else {
            // Si hay más de 20 puntos, tomar muestras distribuidas
            $step = ($total_points - 1) / 19; // 20 puntos = 19 intervalos
            for ($i = 0; $i < 20; $i++) {
                $index = min(round($i * $step), $total_points - 1);
                $capital_evolution[] = $capital_timeline[$index];
            }
        }
    } else {
        // Si no hay depósitos, usar el capital inicial calculado
        error_log("No hay depósitos en timeline, usando capital inicial: " . $initial_capital);
        if ($initial_capital > 0) {
            // Crear una línea ascendente desde 0 hasta el capital inicial
            for ($i = 0; $i < 20; $i++) {
                $capital_evolution[] = ($initial_capital / 20) * ($i + 1);
            }
        } else {
            // Sin datos, usar el balance total
            error_log("Sin capital inicial, usando balance total: " . $total_balance);
            if ($total_balance > 0) {
                for ($i = 0; $i < 20; $i++) {
                    $capital_evolution[] = ($total_balance / 20) * ($i + 1);
                }
            } else {
                // Absolutamente sin datos
                $capital_evolution = array_fill(0, 20, 0);
            }
        }
    }
    
    // Si tenemos menos de 20 puntos, rellenar
    while (count($capital_evolution) < 20) {
        $last_value = !empty($capital_evolution) ? end($capital_evolution) : 0;
        $capital_evolution[] = $last_value;
    }
    
    // Asegurar que todos los valores sean numéricos y limitar a 20
    $capital_evolution = array_slice(array_map('floatval', $capital_evolution), 0, 20);
    
    // Debug: Log final
    error_log("Capital Evolution Final (20 puntos): " . json_encode($capital_evolution));
    error_log("Primer valor: " . ($capital_evolution[0] ?? 0));
    error_log("Último valor: " . ($capital_evolution[19] ?? 0));
    
    $floating_pl = $total_equity - $total_balance;
    $equity_change = $total_equity - $initial_capital;
    $equity_change_pct = $initial_capital > 0 ? ($equity_change / $initial_capital) * 100 : 0;
    
    // ============================
    // 3. OBTENER POSICIONES ABIERTAS (CÓDIGO ORIGINAL)
    // ============================
    $positions_query = "
        SELECT 
            p.*,
            a.name as account_name,
            a.company as broker,
            a.currency
        FROM positions p
        JOIN accounts a ON p.account_login = a.login
        ORDER BY p.profit DESC
    ";
    $stmt = $pdo->prepare($positions_query);
    $stmt->execute();
    $open_positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $positions_count = count($open_positions);
    
    // Análisis de posiciones
    $buy_positions = 0;
    $sell_positions = 0;
    $buy_volume = 0;
    $sell_volume = 0;
    $buy_profit = 0;
    $sell_profit = 0;
    $positions_by_symbol = [];
    
    foreach ($open_positions as $pos) {
        if ($pos['type'] == 0) { // BUY
            $buy_positions++;
            $buy_volume += floatval($pos['volume']);
            $buy_profit += floatval($pos['profit']);
        } else { // SELL
            $sell_positions++;
            $sell_volume += floatval($pos['volume']);
            $sell_profit += floatval($pos['profit']);
        }
        
        // Agrupar por símbolo
        $symbol = $pos['symbol'];
        if (!isset($positions_by_symbol[$symbol])) {
            $positions_by_symbol[$symbol] = [
                'count' => 0,
                'volume' => 0,
                'profit' => 0
            ];
        }
        $positions_by_symbol[$symbol]['count']++;
        $positions_by_symbol[$symbol]['volume'] += floatval($pos['volume']);
        $positions_by_symbol[$symbol]['profit'] += floatval($pos['profit']);
    }
    
    // ============================
    // 4. ANÁLISIS DE HISTORIAL (CÓDIGO ORIGINAL)
    // ============================
    $history_query = "
        SELECT 
            d.*,
            a.name as account_name,
            a.company as broker
        FROM deals d
        JOIN accounts a ON d.account_login = a.login
        WHERE d.type IN (0, 1)
        AND d.time >= :date_from
        ORDER BY d.time DESC
    ";
    $stmt = $pdo->prepare($history_query);
    $stmt->execute(['date_from' => $date_from]);
    $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular métricas de trading
    $total_trades = 0;
    $winning_trades = 0;
    $losing_trades = 0;
    $gross_profit = 0;
    $gross_loss = 0;
    $largest_win = 0;
    $largest_loss = 0;
    $consecutive_wins = 0;
    $consecutive_losses = 0;
    $max_consecutive_wins = 0;
    $max_consecutive_losses = 0;
    $last_trade_result = null;
    $long_trades = 0;
    $short_trades = 0;
    $trade_profits = [];
    $daily_returns = [];
    $monthly_returns = [];
    
    foreach ($deals as $deal) {
        if ($deal['entry'] == 1) { // Solo contar trades de salida
            $total_trades++;
            $profit = floatval($deal['profit']) + floatval($deal['swap']) + floatval($deal['commission']);
            $trade_profits[] = $profit;
            
            // Clasificar por tipo
            if ($deal['type'] == 0) {
                $long_trades++;
            } else {
                $short_trades++;
            }
            
            // Clasificar ganancias/pérdidas
            if ($profit > 0) {
                $winning_trades++;
                $gross_profit += $profit;
                $largest_win = max($largest_win, $profit);
                
                if ($last_trade_result === 'win') {
                    $consecutive_wins++;
                } else {
                    $consecutive_wins = 1;
                }
                $max_consecutive_wins = max($max_consecutive_wins, $consecutive_wins);
                $last_trade_result = 'win';
                
            } elseif ($profit < 0) {
                $losing_trades++;
                $gross_loss += abs($profit);
                $largest_loss = min($largest_loss, $profit);
                
                if ($last_trade_result === 'loss') {
                    $consecutive_losses++;
                } else {
                    $consecutive_losses = 1;
                }
                $max_consecutive_losses = max($max_consecutive_losses, $consecutive_losses);
                $last_trade_result = 'loss';
            }
            
            // Agrupar por día para análisis temporal
            $day = date('Y-m-d', strtotime($deal['time']));
            if (!isset($daily_returns[$day])) {
                $daily_returns[$day] = 0;
            }
            $daily_returns[$day] += $profit;
            
            // Agrupar por mes
            $month = date('Y-m', strtotime($deal['time']));
            if (!isset($monthly_returns[$month])) {
                $monthly_returns[$month] = 0;
            }
            $monthly_returns[$month] += $profit;
        }
    }
    
    // ============================
    // 5-12. RESTO DEL CÓDIGO ORIGINAL (sin cambios)
    // ============================
    
    // Win Rate
    $win_rate = $total_trades > 0 ? ($winning_trades / $total_trades) * 100 : 0;
    
    // Profit Factor
    $profit_factor = $gross_loss > 0 ? $gross_profit / $gross_loss : ($gross_profit > 0 ? 999.99 : 0);
    
    // Average Win/Loss
    $avg_win = $winning_trades > 0 ? $gross_profit / $winning_trades : 0;
    $avg_loss = $losing_trades > 0 ? $gross_loss / $losing_trades : 0;
    
    // Expectancy
    $expectancy = $total_trades > 0 ? 
        (($win_rate/100 * $avg_win) - ((100-$win_rate)/100 * $avg_loss)) : 0;
    
    // Risk/Reward Ratio
    $risk_reward = $avg_loss > 0 ? $avg_win / $avg_loss : 0;
    
    // Recovery Factor (profit / max drawdown)
    $recovery_factor = 0; // Se calculará con el drawdown
    
    // Construir curva de equity
    $equity_curve = [];
    $running_equity = $initial_capital;
    $peak_equity = $initial_capital;
    $max_drawdown = 0;
    $max_drawdown_pct = 0;
    $current_drawdown = 0;
    $drawdown_start = null;
    $drawdown_end = null;
    
    // Construir curva de equity día por día
    foreach ($daily_returns as $day => $profit) {
        $running_equity += $profit;
        $equity_curve[$day] = $running_equity;
        
        // Actualizar peak
        if ($running_equity > $peak_equity) {
            $peak_equity = $running_equity;
        }
        
        // Calcular drawdown
        $current_dd = $peak_equity - $running_equity;
        $current_dd_pct = $peak_equity > 0 ? ($current_dd / $peak_equity) * 100 : 0;
        
        if ($current_dd > $max_drawdown) {
            $max_drawdown = $current_dd;
            $max_drawdown_pct = $current_dd_pct;
            $drawdown_end = $day;
        }
    }
    
    // Recovery Factor
    $total_net_profit = $gross_profit - $gross_loss;
    $recovery_factor = $max_drawdown > 0 ? $total_net_profit / $max_drawdown : 0;
    
    // Sharpe Ratio
    $returns = [];
    $prev_equity = $initial_capital;
    foreach ($equity_curve as $day => $equity) {
        $daily_return = $prev_equity > 0 ? (($equity - $prev_equity) / $prev_equity) : 0;
        $returns[] = $daily_return;
        $prev_equity = $equity;
    }
    
    $avg_return = count($returns) > 0 ? array_sum($returns) / count($returns) : 0;
    $return_variance = 0;
    
    if (count($returns) > 1) {
        foreach ($returns as $return) {
            $return_variance += pow($return - $avg_return, 2);
        }
        $return_variance /= (count($returns) - 1);
        $return_std = sqrt($return_variance);
        $sharpe_ratio = $return_std > 0 ? ($avg_return / $return_std) * sqrt(252) : 0;
    } else {
        $sharpe_ratio = 0;
    }
    
    // Sortino Ratio
    $negative_returns = array_filter($returns, function($r) { return $r < 0; });
    $downside_variance = 0;
    
    if (count($negative_returns) > 0) {
        foreach ($negative_returns as $return) {
            $downside_variance += pow($return, 2);
        }
        $downside_variance /= count($negative_returns);
        $downside_std = sqrt($downside_variance);
        $sortino_ratio = $downside_std > 0 ? ($avg_return / $downside_std) * sqrt(252) : 0;
    } else {
        $sortino_ratio = $sharpe_ratio * 1.5;
    }
    
    // Calmar Ratio
    $days_trading = count($equity_curve);
    $years_trading = max($days_trading / 252, 0.01);
    $annual_return = $years_trading > 0 ? ($total_net_profit / $initial_capital) / $years_trading : 0;
    $calmar_ratio = $max_drawdown_pct > 0 ? ($annual_return * 100) / $max_drawdown_pct : 0;
    
    // Análisis por símbolo
    $symbol_stats_query = "
        SELECT 
            symbol,
            COUNT(CASE WHEN entry = 1 THEN 1 END) as trade_count,
            SUM(CASE WHEN entry = 1 THEN profit + swap + commission ELSE 0 END) as total_profit,
            COUNT(CASE WHEN entry = 1 AND profit > 0 THEN 1 END) as wins,
            COUNT(CASE WHEN entry = 1 AND profit < 0 THEN 1 END) as losses,
            SUM(volume) as total_volume
        FROM deals
        WHERE type IN (0, 1)
        AND time >= :date_from
        AND symbol != ''
        GROUP BY symbol
        ORDER BY total_profit DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($symbol_stats_query);
    $stmt->execute(['date_from' => $date_from]);
    $top_symbols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($top_symbols as &$symbol) {
        $symbol['win_rate'] = $symbol['trade_count'] > 0 ? 
            ($symbol['wins'] / $symbol['trade_count']) * 100 : 0;
    }
    
    // Estado del margen
    $margin_level = $total_margin > 0 ? ($total_equity / $total_margin) * 100 : 999999;
    $margin_status = [
        'used' => $total_margin,
        'free' => $total_margin_free,
        'level' => $margin_level,
        'status' => $margin_level > 500 ? 'excellent' : 
                   ($margin_level > 200 ? 'good' : 
                   ($margin_level > 150 ? 'warning' : 'critical'))
    ];
    
    // P&L por periodos
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT SUM(profit + swap + commission) as pl_today
        FROM deals
        WHERE type IN (0, 1)
        AND entry = 1
        AND DATE(time) = :today
    ");
    $stmt->execute(['today' => $today]);
    $pl_today = floatval($stmt->fetch()['pl_today'] ?? 0);
    
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $stmt = $pdo->prepare("
        SELECT SUM(profit + swap + commission) as pl_week
        FROM deals
        WHERE type IN (0, 1)
        AND entry = 1
        AND DATE(time) >= :week_start
    ");
    $stmt->execute(['week_start' => $week_start]);
    $pl_week = floatval($stmt->fetch()['pl_week'] ?? 0);
    
    $month_start = date('Y-m-01');
    $stmt = $pdo->prepare("
        SELECT SUM(profit + swap + commission) as pl_month
        FROM deals
        WHERE type IN (0, 1)
        AND entry = 1
        AND DATE(time) >= :month_start
    ");
    $stmt->execute(['month_start' => $month_start]);
    $pl_month = floatval($stmt->fetch()['pl_month'] ?? 0);
    
    $year_start = date('Y-01-01');
    $stmt = $pdo->prepare("
        SELECT SUM(profit + swap + commission) as pl_year
        FROM deals
        WHERE type IN (0, 1)
        AND entry = 1
        AND DATE(time) >= :year_start
    ");
    $stmt->execute(['year_start' => $year_start]);
    $pl_year = floatval($stmt->fetch()['pl_year'] ?? 0);
    
    // Preparar datos para gráficos
    $equity_chart_data = [
        'labels' => array_keys($equity_curve),
        'datasets' => [
            [
                'label' => 'Equity',
                'data' => array_values($equity_curve),
                'borderColor' => '#6366f1',
                'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                'tension' => 0.1
            ]
        ]
    ];
    $equity_chart_data['labels'] = array_reverse($equity_chart_data['labels']);
    $equity_chart_data['datasets'][0]['data'] = array_reverse($equity_chart_data['datasets'][0]['data']);
    
    // P&L Distribution
    $pl_ranges = [
        '-5000+' => 0,
        '-2500' => 0,
        '-1000' => 0,
        '-500' => 0,
        '-250' => 0,
        '0' => 0,
        '250' => 0,
        '500' => 0,
        '1000' => 0,
        '2500' => 0,
        '5000+' => 0
    ];
    
    foreach ($trade_profits as $profit) {
        if ($profit <= -5000) $pl_ranges['-5000+']++;
        elseif ($profit <= -2500) $pl_ranges['-2500']++;
        elseif ($profit <= -1000) $pl_ranges['-1000']++;
        elseif ($profit <= -500) $pl_ranges['-500']++;
        elseif ($profit <= -250) $pl_ranges['-250']++;
        elseif ($profit <= 0) $pl_ranges['0']++;
        elseif ($profit <= 250) $pl_ranges['250']++;
        elseif ($profit <= 500) $pl_ranges['500']++;
        elseif ($profit <= 1000) $pl_ranges['1000']++;
        elseif ($profit <= 2500) $pl_ranges['2500']++;
        else $pl_ranges['5000+']++;
    }
    
    // Monthly Returns para Heatmap
    $monthly_heatmap = [];
    foreach ($monthly_returns as $month => $profit) {
        $year = substr($month, 0, 4);
        $month_num = intval(substr($month, 5, 2));
        $month_names = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 
                       'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        
        if (!isset($monthly_heatmap[$year])) {
            $monthly_heatmap[$year] = [];
        }
        
        $monthly_return_pct = $initial_capital > 0 ? 
            ($profit / $initial_capital) * 100 : 0;
        
        $monthly_heatmap[$year][] = [
            'x' => $month_names[$month_num],
            'y' => round($monthly_return_pct, 2)
        ];
    }
    
    // Generar alertas
    $alerts = [];
    
    if ($margin_level < 200) {
        $alerts[] = [
            'type' => 'critical',
            'title' => 'Nivel de Margen Crítico',
            'message' => "El nivel de margen está en {$margin_level}%. Considere cerrar posiciones.",
            'time' => date('Y-m-d H:i:s')
        ];
    }
    
    if ($max_drawdown_pct > 20) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Drawdown Elevado',
            'message' => "El drawdown actual es de " . number_format($max_drawdown_pct, 2) . "%",
            'time' => date('Y-m-d H:i:s')
        ];
    }
    
    if ($max_consecutive_losses > 5) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Racha de Pérdidas',
            'message' => "Se han registrado {$max_consecutive_losses} pérdidas consecutivas",
            'time' => date('Y-m-d H:i:s')
        ];
    }
    
    if ($pl_today < -1000) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Pérdida Diaria Significativa',
            'message' => "Pérdida de hoy: $" . number_format(abs($pl_today), 2),
            'time' => date('Y-m-d H:i:s')
        ];
    }
    
    // ============================
    // 13. PREPARAR RESPUESTA FINAL (AÑADIENDO DATOS DE BOTS)
    // ============================
    
    $response['success'] = true;
    $response['data'] = [
        // KPIs Principales
        'kpi' => [
            'initial_capital' => $initial_capital,
            'total_equity' => $total_equity,
            'total_balance' => $total_balance,
            'floating_pl' => $floating_pl,
            'equity_change' => $equity_change,
            'equity_change_pct' => $equity_change_pct,
            'total_margin' => $total_margin,
            'total_margin_free' => $total_margin_free,
            'accounts_count' => $accounts_count,
            'positions_count' => $positions_count,
            'total_credit' => $total_credit,
            // NUEVO: KPIs de bots
            'total_bots' => count($bots_performance),
            'active_bots' => $active_bots_count,
            'bots_total_pnl' => $total_bots_pnl
        ],
        
        // NUEVO: Performance de Bots
        'bots_performance' => $bots_performance,
        
        // Métricas de Performance (sin cambios)
        'performance' => [
            'win_rate' => $win_rate,
            'profit_factor' => min($profit_factor, 999.99),
            'sharpe_ratio' => $sharpe_ratio,
            'sortino_ratio' => $sortino_ratio,
            'calmar_ratio' => $calmar_ratio,
            'max_drawdown' => $max_drawdown,
            'max_drawdown_pct' => $max_drawdown_pct,
            'recovery_factor' => $recovery_factor,
            'winning_trades' => $winning_trades,
            'losing_trades' => $losing_trades,
            'total_trades' => $total_trades,
            'gross_profit' => $gross_profit,
            'gross_loss' => $gross_loss,
            'net_profit' => $total_net_profit,
            'largest_win' => $largest_win,
            'largest_loss' => $largest_loss,
            'avg_win' => $avg_win,
            'avg_loss' => $avg_loss,
            'expectancy' => $expectancy,
            'risk_reward' => $risk_reward,
            'consecutive_wins' => $max_consecutive_wins,
            'consecutive_losses' => $max_consecutive_losses
        ],
        
        // Estadísticas de Trading (sin cambios)
        'trading_stats' => [
            'long_trades' => $long_trades,
            'short_trades' => $short_trades,
            'avg_daily_trades' => $days_trading > 0 ? $total_trades / $days_trading : 0,
            'trades_per_month' => $total_trades > 0 && $years_trading > 0 ? 
                $total_trades / ($years_trading * 12) : 0,
            'avg_trade_duration' => 0,
            'commission_total' => array_sum(array_column($deals, 'commission')),
            'swap_total' => array_sum(array_column($deals, 'swap'))
        ],
        
        // Estado del Margen
        'margin_status' => $margin_status,
        
        // P&L por Periodos
        'period_pl' => [
            'today' => $pl_today,
            'week' => $pl_week,
            'month' => $pl_month,
            'year' => $pl_year
        ],
        
        // Cuentas
        'accounts' => $accounts,
        
        // Posiciones Abiertas
        'positions' => $open_positions,
        
        // Resumen de Operaciones
        'operations_summary' => [
            'buy_count' => $buy_positions,
            'sell_count' => $sell_positions,
            'buy_volume' => $buy_volume,
            'sell_volume' => $sell_volume,
            'buy_profit' => $buy_profit,
            'sell_profit' => $sell_profit,
            'total_volume' => $buy_volume + $sell_volume
        ],
        
        // Top Símbolos
        'top_symbols' => $top_symbols,
        
        // Historial
        'recent_history' => array_slice($deals, 0, 100),
        
        // Datos para Gráficos
        'charts' => [
            'equity_curve' => $equity_chart_data,
            'pl_distribution' => [
                'labels' => array_keys($pl_ranges),
                'data' => array_values($pl_ranges)
            ],
            'monthly_returns' => $monthly_heatmap,
            'daily_returns' => $daily_returns,
            'symbol_performance' => [
                'labels' => array_column($top_symbols, 'symbol'),
                'profit' => array_column($top_symbols, 'total_profit'),
                'trades' => array_column($top_symbols, 'trade_count')
            ],
            // IMPORTANTE: Datos para mini charts
            'mini_charts' => [
                'capital' => $capital_evolution,
                'equity' => !empty($equity_curve) ? array_slice(array_values($equity_curve), -20) : array_fill(0, 20, $total_equity),
                'balance' => array_fill(0, 20, $total_balance),
                'pl' => !empty($trade_profits) ? array_slice($trade_profits, -20) : array_fill(0, 20, 0)
            ]
        ],
        
        // Alertas
        'alerts' => $alerts,
        
        // Información del Sistema
        'system' => [
            'last_update' => date('Y-m-d H:i:s'),
            'data_period' => $period,
            'broker_filter' => $broker_filter,
            'server_time' => date('H:i:s')
        ]
    ];
    
} catch (PDOException $e) {
    $response['success'] = false;
    $response['error'] = [
        'code' => 'DB_ERROR',
        'message' => 'Error de base de datos',
        'details' => $e->getMessage()
    ];
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = [
        'code' => 'GENERAL_ERROR',
        'message' => 'Error general del sistema',
        'details' => $e->getMessage()
    ];
}

// Enviar respuesta JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// Registrar en log si es necesario
if (!$response['success']) {
    error_log("[Elysium Dashboard API] Error: " . json_encode($response['error']));
}
?>