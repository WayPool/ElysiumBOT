<?php
//+------------------------------------------------------------------+
//| get_import_history.php                                          |
//| API para obtener historial de importaciones CSV                 |
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
    'data' => [],
    'message' => ''
];

try {
    // Conectar a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si la tabla existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'import_logs'");
    if ($stmt->rowCount() == 0) {
        // Crear tabla si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS import_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_login BIGINT,
                import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                import_type VARCHAR(50) DEFAULT 'CSV',
                processed INT DEFAULT 0,
                imported INT DEFAULT 0,
                skipped INT DEFAULT 0,
                errors INT DEFAULT 0,
                audit_log TEXT,
                import_user VARCHAR(100),
                ip_address VARCHAR(45),
                INDEX idx_account (account_login),
                INDEX idx_date (import_date)
            )
        ");
    }
    
    // Obtener historial de importaciones
    $query = "
        SELECT 
            il.account_login,
            il.import_date,
            il.processed as records,
            il.imported,
            il.skipped,
            il.errors,
            a.name as account_name,
            a.company as broker,
            a.currency
        FROM import_logs il
        LEFT JOIN accounts a ON il.account_login = a.login
        ORDER BY il.import_date DESC
        LIMIT 20
    ";
    
    $stmt = $pdo->query($query);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear datos
    $formattedHistory = [];
    foreach ($history as $item) {
        $formattedHistory[] = [
            'account_login' => $item['account_login'],
            'account_name' => $item['account_name'] ?? 'Cuenta Histórica',
            'broker' => $item['broker'] ?? 'N/A',
            'currency' => $item['currency'] ?? 'USD',
            'records' => $item['records'],
            'imported' => $item['imported'],
            'skipped' => $item['skipped'],
            'errors' => $item['errors'],
            'import_date' => date('d/m/Y H:i', strtotime($item['import_date'])),
            'success_rate' => $item['records'] > 0 ? 
                round(($item['imported'] / $item['records']) * 100, 1) : 0
        ];
    }
    
    $response['success'] = true;
    $response['data'] = $formattedHistory;
    $response['message'] = count($formattedHistory) . ' registros encontrados';
    
} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Error de base de datos';
    error_log('[Import History API Error] ' . $e->getMessage());
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error del sistema';
    error_log('[Import History API Error] ' . $e->getMessage());
}

// Enviar respuesta JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>