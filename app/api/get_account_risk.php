<?php
//+------------------------------------------------------------------+
//| get_account_risk.php                                            |
//| API para análisis de riesgo de cuenta - Elysium v7.0           |
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
    'risk_analysis' => []
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
    // ANÁLISIS DE RIESGO ACTUAL
    // ============================
    $risk_analysis = [];
    $alerts = [];
    $recommendations = [];
    $risk_score = 100; // Empezamos con puntuación perfecta
    
    // 1. Análisis del Nivel de Margen
    $margin_level = floatval($account['margin_level']);
    if ($margin_level < 200) {
        $risk_score -= 40;
        $alerts[] = [
            'severity' => 'critical',
            'title' => 'Nivel de Margen Crítico',
            'message' => 'El nivel de margen está en ' . number_format($margin_level, 2) . '%. Riesgo extremo de margin call.'
        ];
        $recommendations[] = 'Cerrar posiciones perdedoras inmediatamente';
        $recommendations[] = 'No abrir nuevas posiciones hasta estabilizar el margen';
    } elseif ($margin_level < 500) {
        $risk_score -= 20;
        $alerts[] = [
            'severity' => 'warning',
            'title' => 'Nivel de Margen Bajo',
            'message' => 'El nivel de margen está en ' . number_format($margin_level, 2) . '%. Precaución requerida.'
        ];
        $recommendations[] = 'Reducir el tamaño de las posiciones';
        $recommendations[] = 'Considerar cerrar algunas posiciones';
    }
    
    // 2. Análisis de Exposición
    $equity = floatval($account['equity']);
    $margin_used = floatval($account['margin']);
    $exposure_ratio = $equity > 0 ? ($margin_used / $equity) * 100 : 0;
    
    if ($exposure_ratio > 50) {
        $risk_score -= 25;
        $alerts[] = [
            'severity' => 'warning',
            'title' => 'Alta Exposición al Mercado',
            'message' => 'Usando ' . number_format($exposure_ratio, 2) . '% del equity en margen.'
        ];
        $recommendations[] = 'Reducir la exposición total al mercado';
    }
    
    // 3. Análisis de Drawdown Histórico
    $stmt = $pdo->prepare("
        SELECT 
            MAX(profit) as max_profit,
            MIN(profit) as max_loss,
            AVG(ABS(profit)) as avg_trade_size,
            STDDEV(profit) as profit_stddev
        FROM deals
        WHERE account_login = :login
        AND type IN (0,1)
        AND entry = 1
    ");
    $stmt->execute(['login' => $login]);
    $risk_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calcular el drawdown máximo
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
    
    $running_balance = floatval($account['balance']);
    $peak = $running_balance;
    $max_drawdown = 0;
    $max_drawdown_pct = 0;
    $current_drawdown = 0;
    $current_drawdown_pct = 0;
    
    foreach ($trades as $trade) {
        $running_balance += floatval($trade['pl']);
        
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
    
    // Drawdown actual
    $current_drawdown = $peak - floatval($account['equity']);
    $current_drawdown_pct = $peak > 0 ? ($current_drawdown / $peak) * 100 : 0;
    
    if ($max_drawdown_pct > 20) {
        $risk_score -= 15;
        $alerts[] = [
            'severity' => 'warning',
            'title' => 'Drawdown Histórico Alto',
            'message' => 'El drawdown máximo alcanzó ' . number_format($max_drawdown_pct, 2) . '%'
        ];
        $recommendations[] = 'Implementar stops más ajustados';
        $recommendations[] = 'Revisar la estrategia de gestión de riesgo';
    }
    
    // 4. Value at Risk (VaR) al 95%
    $var_95 = 0;
    if ($risk_stats['profit_stddev'] && $equity > 0) {
        // VaR simplificado usando desviación estándar
        $var_95 = (1.645 * floatval($risk_stats['profit_stddev']) / $equity) * 100;
        
        if ($var_95 > 5) {
            $risk_score -= 10;
            $alerts[] = [
                'severity' => 'info',
                'title' => 'VaR Elevado',
                'message' => 'El VaR al 95% es ' . number_format($var_95, 2) . '% del equity'
            ];
            $recommendations[] = 'Diversificar las operaciones';
        }
    }
    
    // 5. Análisis de Posiciones Abiertas
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as open_positions,
            SUM(volume) as total_volume,
            SUM(profit) as unrealized_pl,
            COUNT(DISTINCT symbol) as symbols_traded
        FROM positions
        WHERE account_login = :login
    ");
    $stmt->execute(['login' => $login]);
    $positions_risk = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($positions_risk['open_positions'] > 10) {
        $risk_score -= 5;
        $alerts[] = [
            'severity' => 'info',
            'title' => 'Muchas Posiciones Abiertas',
            'message' => 'Hay ' . $positions_risk['open_positions'] . ' posiciones abiertas simultáneamente'
        ];
        $recommendations[] = 'Considerar reducir el número de posiciones abiertas';
    }
    
    // Concentración en un solo símbolo
    if ($positions_risk['symbols_traded'] == 1 && $positions_risk['open_positions'] > 3) {
        $risk_score -= 10;
        $alerts[] = [
            'severity' => 'warning',
            'title' => 'Concentración en un Solo Instrumento',
            'message' => 'Todas las posiciones están en el mismo símbolo'
        ];
        $recommendations[] = 'Diversificar entre diferentes instrumentos';
    }
    
    // 6. Análisis de Pérdidas No Realizadas
    $unrealized_loss_pct = $equity > 0 ? 
        (floatval($positions_risk['unrealized_pl']) / $equity) * 100 : 0;
    
    if ($unrealized_loss_pct < -10) {
        $risk_score -= 15;
        $alerts[] = [
            'severity' => 'warning',
            'title' => 'Pérdidas No Realizadas Significativas',
            'message' => 'Las pérdidas no realizadas son ' . number_format(abs($unrealized_loss_pct), 2) . '% del equity'
        ];
        $recommendations[] = 'Evaluar cerrar posiciones perdedoras';
        $recommendations[] = 'Implementar stop loss en todas las posiciones';
    }
    
    // 7. Análisis de Actividad Reciente
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as recent_trades,
            SUM(CASE WHEN profit < 0 THEN 1 ELSE 0 END) as recent_losses
        FROM deals
        WHERE account_login = :login
        AND type IN (0,1)
        AND entry = 1
        AND time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute(['login' => $login]);
    $recent_activity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($recent_activity['recent_trades'] > 0) {
        $recent_loss_rate = ($recent_activity['recent_losses'] / $recent_activity['recent_trades']) * 100;
        if ($recent_loss_rate > 70) {
            $risk_score -= 10;
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'Alta Tasa de Pérdidas Recientes',
                'message' => number_format($recent_loss_rate, 1) . '% de pérdidas en los últimos 7 días'
            ];
            $recommendations[] = 'Revisar y ajustar la estrategia de trading';
            $recommendations[] = 'Considerar una pausa para re-evaluar el mercado';
        }
    }
    
    // Determinar nivel de riesgo general
    $risk_level = 'low';
    if ($risk_score < 50) {
        $risk_level = 'critical';
    } elseif ($risk_score < 70) {
        $risk_level = 'high';
    } elseif ($risk_score < 85) {
        $risk_level = 'medium';
    }
    
    // Si no hay alertas, agregar mensaje positivo
    if (empty($alerts)) {
        $alerts[] = [
            'severity' => 'success',
            'title' => 'Sin Alertas de Riesgo',
            'message' => 'La cuenta opera dentro de parámetros seguros'
        ];
    }
    
    // Si no hay recomendaciones, agregar consejo general
    if (empty($recommendations)) {
        $recommendations[] = 'Mantener la disciplina actual de trading';
        $recommendations[] = 'Continuar monitoreando el rendimiento';
    }
    
    // Preparar respuesta
    $risk_analysis = [
        'risk_score' => max(0, $risk_score),
        'risk_level' => strtoupper($risk_level),
        'margin_level' => $margin_level,
        'exposure_ratio' => $exposure_ratio,
        'max_drawdown' => $max_drawdown,
        'max_drawdown_pct' => $max_drawdown_pct,
        'current_drawdown' => $current_drawdown,
        'current_drawdown_pct' => $current_drawdown_pct,
        'var_95' => $var_95,
        'open_positions' => $positions_risk['open_positions'],
        'unrealized_pl' => $positions_risk['unrealized_pl'],
        'unrealized_pl_pct' => $unrealized_loss_pct,
        'alerts' => $alerts,
        'recommendations' => $recommendations
    ];
    
    $response['success'] = true;
    $response['risk_analysis'] = $risk_analysis;
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
    error_log("[Elysium Risk API] Error: " . $e->getMessage());
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>