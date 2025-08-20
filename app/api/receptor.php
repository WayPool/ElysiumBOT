<?php
// receptor.php - Script PHP para recibir JSON del EA y almacenar en MySQL

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'elysium_dashboard';
$user = 'elysium_dashboard';
$pass = '@01Mwdsz4';

// Token de seguridad
$secretToken = 'aB3dF6hJ9kL2mN4pQ7rS0tV5wX8yZ1cE';

// Configurar headers para UTF-8
header('Content-Type: application/json; charset=utf-8');

// Verificar token primero
if (!isset($_GET['token']) || $_GET['token'] !== $secretToken) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

// Recibir JSON una sola vez
$json = file_get_contents('php://input');
$json = trim($json); // Limpiar espacios y BOM

// Decodificar JSON
$data = json_decode($json, true);

// Verificar si el JSON es válido
if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'error' => 'JSON inválido',
        'details' => json_last_error_msg()
    ]);
    exit;
}

// Conectar a la base de datos
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Conexión BD fallida: ' . $e->getMessage()]);
    exit;
}

// Procesar datos
try {
    // 1. Actualizar cuenta
    $account = $data['account'];
    $stmt = $pdo->prepare("
        INSERT INTO accounts (login, name, server, currency, balance, credit, equity, profit, margin, margin_free, margin_level, leverage, company, last_update)
        VALUES (:login, :name, :server, :currency, :balance, :credit, :equity, :profit, :margin, :margin_free, :margin_level, :leverage, :company, NOW())
        ON DUPLICATE KEY UPDATE
        name=VALUES(name), server=VALUES(server), currency=VALUES(currency), 
        balance=VALUES(balance), credit=VALUES(credit), equity=VALUES(equity), 
        profit=VALUES(profit), margin=VALUES(margin), margin_free=VALUES(margin_free), 
        margin_level=VALUES(margin_level), leverage=VALUES(leverage), 
        company=VALUES(company), last_update=NOW()
    ");
    $stmt->execute($account);
    
    $accountLogin = $account['login'];
    
    // 2. Procesar histórico
    if (isset($data['history_updates'])) {
        $history = $data['history_updates'];
        $isFull = ($data['is_full_history'] === true || $data['is_full_history'] === 'true');
        
        if ($isFull) {
            // Si es histórico completo, eliminar anteriores
            $stmt = $pdo->prepare("DELETE FROM deals WHERE account_login = :login");
            $stmt->execute(['login' => $accountLogin]);
        }
        
        // Insertar nuevos deals
        foreach ($history as $deal) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO deals (ticket, account_login, time, type, type_desc, entry, volume, price, profit, commission, swap, symbol, comment, position_id, magic, reason, order_id)
                VALUES (:ticket, :account_login, FROM_UNIXTIME(:time), :type, :type_desc, :entry, :volume, :price, :profit, :commission, :swap, :symbol, :comment, :position_id, :magic, :reason, :order_id)
            ");
            
            $deal['account_login'] = $accountLogin;
            $deal['order_id'] = isset($deal['order']) ? $deal['order'] : 0;
            unset($deal['order']); // Eliminar campo 'order' del array
            
            $stmt->execute($deal);
        }
    }
    
    // 3. Procesar posiciones
    if (isset($data['positions'])) {
        // Eliminar posiciones antiguas
        $stmt = $pdo->prepare("DELETE FROM positions WHERE account_login = :login");
        $stmt->execute(['login' => $accountLogin]);
        
        // Insertar nuevas posiciones
        foreach ($data['positions'] as $pos) {
            $stmt = $pdo->prepare("
                INSERT INTO positions (ticket, account_login, symbol, type, volume, price_open, time_open, sl, tp, price_current, swap, profit, comment, magic, position_id, time_update)
                VALUES (:ticket, :account_login, :symbol, :type, :volume, :price_open, FROM_UNIXTIME(:time_open), :sl, :tp, :price_current, :swap, :profit, :comment, :magic, :position_id, FROM_UNIXTIME(:time_update))
            ");
            
            $pos['account_login'] = $accountLogin;
            $stmt->execute($pos);
        }
    }
    
    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Datos procesados correctamente',
        'account' => $accountLogin
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error procesando datos',
        'details' => $e->getMessage()
    ]);
}
?>