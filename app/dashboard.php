<?php
// dashboard.php - Panel mejorado para ver datos de múltiples cuentas desde la BD MySQL
// Incluye visualización de depósitos, retiros y todos los movimientos de balance

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'elysium_dashboard';
$user = 'elysium_dashboard';
$pass = '@01Mwdsz4';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Conexión BD fallida: ' . $e->getMessage());
}

// Obtener todas las cuentas
$stmt = $pdo->prepare("SELECT * FROM accounts ORDER BY last_update DESC");
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función helper para obtener icono según tipo de movimiento
function getMovementIcon($type, $profit) {
    switch($type) {
        case 2: // DEAL_TYPE_BALANCE
            return $profit > 0 ? '💰' : '💸';
        case 3: return '💳'; // CREDIT
        case 6: return '🎁'; // BONUS
        case 7: return '💵'; // COMMISSION
        case 10: return '📊'; // INTEREST
        default: return '📝';
    }
}

// Función para obtener color según profit
function getProfitColor($profit) {
    if ($profit > 0) return '#28a745';
    if ($profit < 0) return '#dc3545';
    return '#6c757d';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Cuentas MT5 - Dashboard Completo</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .account-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .account-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .account-header h2 {
            margin-bottom: 15px;
            font-size: 1.8em;
        }
        
        .account-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-item {
            background: rgba(255,255,255,0.1);
            padding: 10px;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .stat-label {
            font-size: 0.85em;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .account-body {
            padding: 20px;
        }
        
        .section-title {
            color: #333;
            margin: 20px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title span.count {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.85em;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        th {
            background: #f8f9fa;
            color: #495057;
            font-weight: 600;
            text-align: left;
            padding: 12px;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 10px 12px;
            border-top: 1px solid #dee2e6;
            font-size: 0.95em;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .profit-positive {
            color: #28a745;
            font-weight: 600;
        }
        
        .profit-negative {
            color: #dc3545;
            font-weight: 600;
        }
        
        .balance-movement {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            margin: 10px 0;
            padding: 15px;
            border-radius: 8px;
        }
        
        .movement-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .movement-type {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
        }
        
        .movement-amount {
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .movement-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .summary-card h4 {
            margin-bottom: 10px;
            opacity: 0.9;
            font-size: 0.95em;
        }
        
        .summary-card .value {
            font-size: 1.8em;
            font-weight: bold;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 600;
        }
        
        .badge-buy { background: #28a745; color: white; }
        .badge-sell { background: #dc3545; color: white; }
        .badge-balance { background: #17a2b8; color: white; }
        .badge-credit { background: #ffc107; color: #333; }
        .badge-bonus { background: #6f42c1; color: white; }
        
        .symbol-group {
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .symbol-header {
            background: #f8f9fa;
            padding: 10px 15px;
            font-weight: 600;
            border-bottom: 1px solid #dee2e6;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Panel de Cuentas MT5</h1>
        
        <?php if (empty($accounts)): ?>
            <div class="account-card">
                <div class="no-data">
                    <p>No hay cuentas registradas en el sistema.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($accounts as $account): ?>
                <?php
                // Calcular estadísticas adicionales
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(CASE WHEN type = 2 AND profit > 0 THEN 1 END) as deposits_count,
                        SUM(CASE WHEN type = 2 AND profit > 0 THEN profit ELSE 0 END) as total_deposits,
                        COUNT(CASE WHEN type = 2 AND profit < 0 THEN 1 END) as withdrawals_count,
                        SUM(CASE WHEN type = 2 AND profit < 0 THEN ABS(profit) ELSE 0 END) as total_withdrawals,
                        COUNT(CASE WHEN type IN (0,1) THEN 1 END) as trades_count,
                        SUM(CASE WHEN type IN (0,1) THEN profit ELSE 0 END) as trading_profit
                    FROM deals 
                    WHERE account_login = :login
                ");
                $stmt->execute(['login' => $account['login']]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                
                <div class="account-card">
                    <div class="account-header">
                        <h2>
                            Cuenta #<?= htmlspecialchars($account['login']) ?> 
                            - <?= htmlspecialchars($account['name'] ?: 'Sin nombre') ?>
                        </h2>
                        
                        <div class="account-stats">
                            <div class="stat-item">
                                <div class="stat-label">Balance</div>
                                <div class="stat-value"><?= number_format($account['balance'], 2) ?> <?= htmlspecialchars($account['currency']) ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Equity</div>
                                <div class="stat-value"><?= number_format($account['equity'], 2) ?> <?= htmlspecialchars($account['currency']) ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Profit Flotante</div>
                                <div class="stat-value" style="color: <?= $account['profit'] >= 0 ? '#4ade80' : '#f87171' ?>">
                                    <?= $account['profit'] >= 0 ? '+' : '' ?><?= number_format($account['profit'], 2) ?>
                                </div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Margen / Libre</div>
                                <div class="stat-value"><?= number_format($account['margin'], 2) ?> / <?= number_format($account['margin_free'], 2) ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Nivel de Margen</div>
                                <div class="stat-value"><?= $account['margin_level'] ? number_format($account['margin_level'], 2) . '%' : 'N/A' ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Apalancamiento</div>
                                <div class="stat-value">1:<?= $account['leverage'] ?></div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 15px; opacity: 0.9; font-size: 0.9em;">
                            <span>🏢 <?= htmlspecialchars($account['company']) ?></span> | 
                            <span>🖥️ <?= htmlspecialchars($account['server']) ?></span> | 
                            <span>🕐 Actualizado: <?= $account['last_update'] ?></span>
                        </div>
                    </div>
                    
                    <div class="account-body">
                        <!-- Resumen de la cuenta -->
                        <div class="summary-cards">
                            <div class="summary-card">
                                <h4>💰 Total Depositado</h4>
                                <div class="value"><?= number_format($stats['total_deposits'], 2) ?> <?= htmlspecialchars($account['currency']) ?></div>
                                <small><?= $stats['deposits_count'] ?> depósitos</small>
                            </div>
                            <div class="summary-card">
                                <h4>💸 Total Retirado</h4>
                                <div class="value"><?= number_format($stats['total_withdrawals'], 2) ?> <?= htmlspecialchars($account['currency']) ?></div>
                                <small><?= $stats['withdrawals_count'] ?> retiros</small>
                            </div>
                            <div class="summary-card">
                                <h4>📈 Profit Trading</h4>
                                <div class="value" style="color: <?= $stats['trading_profit'] >= 0 ? '#4ade80' : '#f87171' ?>">
                                    <?= $stats['trading_profit'] >= 0 ? '+' : '' ?><?= number_format($stats['trading_profit'], 2) ?>
                                </div>
                                <small><?= $stats['trades_count'] ?> operaciones</small>
                            </div>
                        </div>
                        
                        <!-- Movimientos de Balance (Depósitos, Retiros, etc.) -->
                        <h3 class="section-title">
                            💳 Movimientos de Balance
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT * FROM deals 
                                WHERE account_login = :login 
                                AND type IN (2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17)
                                ORDER BY time DESC
                            ");
                            $stmt->execute(['login' => $account['login']]);
                            $balanceMovements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <span class="count"><?= count($balanceMovements) ?></span>
                        </h3>
                        
                        <?php if (empty($balanceMovements)): ?>
                            <div class="empty-state">
                                <p>No hay movimientos de balance registrados.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($balanceMovements as $movement): ?>
                                <div class="balance-movement">
                                    <div class="movement-header">
                                        <div class="movement-type">
                                            <span><?= getMovementIcon($movement['type'], $movement['profit']) ?></span>
                                            <span>
                                                <?php
                                                if ($movement['type'] == 2 && $movement['profit'] > 0) {
                                                    echo "Depósito";
                                                } elseif ($movement['type'] == 2 && $movement['profit'] < 0) {
                                                    echo "Retiro";
                                                } else {
                                                    echo htmlspecialchars($movement['type_desc'] ?: 'Movimiento');
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="movement-amount" style="color: <?= getProfitColor($movement['profit']) ?>">
                                            <?= $movement['profit'] >= 0 ? '+' : '' ?><?= number_format($movement['profit'], 2) ?> <?= htmlspecialchars($account['currency']) ?>
                                        </div>
                                    </div>
                                    <div class="movement-details">
                                        <div><strong>Ticket:</strong> #<?= $movement['ticket'] ?></div>
                                        <div><strong>Fecha:</strong> <?= $movement['time'] ?></div>
                                        <?php if ($movement['comment']): ?>
                                            <div><strong>Comentario:</strong> <?= htmlspecialchars($movement['comment']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Posiciones Abiertas -->
                        <h3 class="section-title">
                            📊 Posiciones Abiertas
                            <?php
                            $stmt = $pdo->prepare("SELECT * FROM positions WHERE account_login = :login ORDER BY symbol, time_open");
                            $stmt->execute(['login' => $account['login']]);
                            $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <span class="count"><?= count($positions) ?></span>
                        </h3>
                        
                        <?php if (empty($positions)): ?>
                            <div class="empty-state">
                                <p>No hay posiciones abiertas actualmente.</p>
                            </div>
                        <?php else: ?>
                            <?php
                            // Agrupar por símbolo
                            $positionsBySymbol = [];
                            foreach ($positions as $pos) {
                                $symbol = $pos['symbol'] ?: 'Sin símbolo';
                                if (!isset($positionsBySymbol[$symbol])) {
                                    $positionsBySymbol[$symbol] = [];
                                }
                                $positionsBySymbol[$symbol][] = $pos;
                            }
                            ?>
                            
                            <?php foreach ($positionsBySymbol as $symbol => $symbolPositions): ?>
                                <div class="symbol-group">
                                    <div class="symbol-header">
                                        📈 <?= htmlspecialchars($symbol) ?> 
                                        <span style="font-weight: normal; color: #6c757d;">(<?= count($symbolPositions) ?> posiciones)</span>
                                    </div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Ticket</th>
                                                <th>Tipo</th>
                                                <th>Volumen</th>
                                                <th>Apertura</th>
                                                <th>Actual</th>
                                                <th>SL</th>
                                                <th>TP</th>
                                                <th>Swap</th>
                                                <th>Profit</th>
                                                <th>Tiempo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($symbolPositions as $pos): ?>
                                                <tr>
                                                    <td>#<?= $pos['ticket'] ?></td>
                                                    <td>
                                                        <span class="badge <?= $pos['type'] == 0 ? 'badge-buy' : 'badge-sell' ?>">
                                                            <?= $pos['type'] == 0 ? 'BUY' : 'SELL' ?>
                                                        </span>
                                                    </td>
                                                    <td><?= number_format($pos['volume'], 2) ?></td>
                                                    <td><?= number_format($pos['price_open'], 5) ?></td>
                                                    <td><?= number_format($pos['price_current'], 5) ?></td>
                                                    <td><?= $pos['sl'] > 0 ? number_format($pos['sl'], 5) : '-' ?></td>
                                                    <td><?= $pos['tp'] > 0 ? number_format($pos['tp'], 5) : '-' ?></td>
                                                    <td class="<?= $pos['swap'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                        <?= number_format($pos['swap'], 2) ?>
                                                    </td>
                                                    <td class="<?= $pos['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                        <?= $pos['profit'] >= 0 ? '+' : '' ?><?= number_format($pos['profit'], 2) ?>
                                                    </td>
                                                    <td><?= $pos['time_open'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <!-- Histórico de Trading -->
                        <h3 class="section-title">
                            📜 Histórico de Trading (Últimas 100)
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT * FROM deals 
                                WHERE account_login = :login 
                                AND type IN (0, 1)
                                ORDER BY time DESC 
                                LIMIT 100
                            ");
                            $stmt->execute(['login' => $account['login']]);
                            $tradingDeals = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <span class="count"><?= count($tradingDeals) ?></span>
                        </h3>
                        
                        <?php if (empty($tradingDeals)): ?>
                            <div class="empty-state">
                                <p>No hay operaciones de trading en el histórico.</p>
                            </div>
                        <?php else: ?>
                            <?php
                            // Agrupar por símbolo
                            $dealsBySymbol = [];
                            foreach ($tradingDeals as $deal) {
                                $symbol = $deal['symbol'] ?: 'Sin símbolo';
                                if (!isset($dealsBySymbol[$symbol])) {
                                    $dealsBySymbol[$symbol] = [];
                                }
                                $dealsBySymbol[$symbol][] = $deal;
                            }
                            ?>
                            
                            <?php foreach ($dealsBySymbol as $symbol => $symbolDeals): ?>
                                <div class="symbol-group">
                                    <div class="symbol-header">
                                        📈 <?= htmlspecialchars($symbol) ?>
                                        <span style="font-weight: normal; color: #6c757d;">(<?= count($symbolDeals) ?> operaciones)</span>
                                    </div>
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Ticket</th>
                                                <th>Fecha</th>
                                                <th>Tipo</th>
                                                <th>Entrada</th>
                                                <th>Volumen</th>
                                                <th>Precio</th>
                                                <th>Comisión</th>
                                                <th>Swap</th>
                                                <th>Profit</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($symbolDeals as $deal): ?>
                                                <?php
                                                $total = $deal['profit'] + $deal['swap'] + $deal['commission'];
                                                ?>
                                                <tr>
                                                    <td>#<?= $deal['ticket'] ?></td>
                                                    <td><?= $deal['time'] ?></td>
                                                    <td>
                                                        <span class="badge <?= $deal['type'] == 0 ? 'badge-buy' : 'badge-sell' ?>">
                                                            <?= $deal['type'] == 0 ? 'BUY' : 'SELL' ?>
                                                        </span>
                                                    </td>
                                                    <td><?= $deal['entry'] == 0 ? 'IN' : 'OUT' ?></td>
                                                    <td><?= number_format($deal['volume'], 2) ?></td>
                                                    <td><?= number_format($deal['price'], 5) ?></td>
                                                    <td class="<?= $deal['commission'] <= 0 ? 'profit-negative' : '' ?>">
                                                        <?= number_format($deal['commission'], 2) ?>
                                                    </td>
                                                    <td class="<?= $deal['swap'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                        <?= number_format($deal['swap'], 2) ?>
                                                    </td>
                                                    <td class="<?= $deal['profit'] >= 0 ? 'profit-positive' : 'profit-negative' ?>">
                                                        <?= $deal['profit'] >= 0 ? '+' : '' ?><?= number_format($deal['profit'], 2) ?>
                                                    </td>
                                                    <td class="<?= $total >= 0 ? 'profit-positive' : 'profit-negative' ?>" style="font-weight: bold;">
                                                        <?= $total >= 0 ? '+' : '' ?><?= number_format($total, 2) ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>