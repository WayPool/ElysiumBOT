<?php
//+------------------------------------------------------------------+
//| process_csv_import.php                                          |
//| Procesador de Importación de Datos Históricos CSV               |
//| Copyright 2025, Elysium Media FZCO                              |
//| Sistema Profesional de Importación de Trading                   |
//| UBICACIÓN: /upload/api/process_csv_import.php                   |
//+------------------------------------------------------------------+

// Configuración de errores para producción
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/csv_import_errors.log');

// Headers JSON obligatorios - IMPORTANTE: Antes de cualquier output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Función para enviar respuesta JSON y terminar
function sendJsonResponse($response) {
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// Manejar OPTIONS request para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuración de base de datos
$host = 'localhost';
$dbname = 'elysium_dashboard';
$user = 'elysium_dashboard';
$pass = '@01Mwdsz4';

// Configuración de límites
ini_set('max_execution_time', 300); // 5 minutos
ini_set('memory_limit', '256M');

// Respuesta estructurada por defecto
$response = [
    'success' => false,
    'message' => '',
    'details' => [
        'processed' => 0,
        'imported' => 0,
        'skipped' => 0,
        'errors' => 0
    ],
    'errors' => [],
    'warnings' => []
];

// Log de auditoría
$auditLog = [];

try {
    // Validar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Método no permitido. Use POST.';
        sendJsonResponse($response);
    }
    
    // Validar archivo
    if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = isset($_FILES['csvFile']['error']) ? $_FILES['csvFile']['error'] : 'Unknown';
        $response['message'] = 'Error al cargar el archivo CSV. Código: ' . $uploadError;
        sendJsonResponse($response);
    }
    
    // Validar campos requeridos
    $requiredFields = ['accountName', 'accountLogin', 'brokerName'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $response['message'] = "El campo '$field' es requerido.";
            sendJsonResponse($response);
        }
    }
    
    // Obtener datos del formulario
    $accountData = [
        'login' => intval($_POST['accountLogin']),
        'name' => trim($_POST['accountName']),
        'company' => trim($_POST['brokerName']),
        'server' => !empty($_POST['serverName']) ? trim($_POST['serverName']) : 'Historical Import',
        'currency' => $_POST['accountCurrency'] ?? 'USD',
        'leverage' => intval($_POST['accountLeverage'] ?? 100),
        'balance' => 0, // Se calculará
        'credit' => 0,
        'equity' => 0, // Se calculará
        'profit' => 0,
        'margin' => 0,
        'margin_free' => 0,
        'margin_level' => 0
    ];
    
    // Validar que el login sea válido
    if ($accountData['login'] <= 0) {
        $response['message'] = 'El número de cuenta debe ser mayor que 0.';
        sendJsonResponse($response);
    }
    
    // Opciones de validación
    $options = [
        'validateDuplicates' => filter_var($_POST['validateDuplicates'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'validateDates' => filter_var($_POST['validateDates'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'validateNumbers' => filter_var($_POST['validateNumbers'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'calculateBalance' => filter_var($_POST['calculateBalance'] ?? true, FILTER_VALIDATE_BOOLEAN)
    ];
    
    // Conectar a la base de datos
    try {
        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4", 
            $user, 
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );
    } catch (PDOException $e) {
        $response['message'] = 'Error de conexión a la base de datos.';
        error_log('[CSV Import DB Connection Error] ' . $e->getMessage());
        sendJsonResponse($response);
    }
    
    // Verificar si la cuenta ya existe ANTES de iniciar transacción
    $stmt = $pdo->prepare("SELECT login FROM accounts WHERE login = :login");
    $stmt->execute(['login' => $accountData['login']]);
    if ($stmt->fetch()) {
        $response['message'] = "La cuenta #{$accountData['login']} ya existe en el sistema.";
        sendJsonResponse($response);
    }
    
    // Procesar archivo CSV ANTES de iniciar transacción
    $csvFile = $_FILES['csvFile']['tmp_name'];
    $csvData = processCSVFile($csvFile, $options);
    
    if (empty($csvData['deals'])) {
        $response['message'] = 'No se encontraron datos válidos en el archivo CSV.';
        sendJsonResponse($response);
    }
    
    // AHORA SÍ iniciar transacción
    $pdo->beginTransaction();
    
    // Insertar cuenta
    $stmt = $pdo->prepare("
        INSERT INTO accounts (login, name, server, currency, balance, credit, equity, 
                            profit, margin, margin_free, margin_level, leverage, company, 
                            last_update, is_historical, import_date, data_source)
        VALUES (:login, :name, :server, :currency, :balance, :credit, :equity, 
                :profit, :margin, :margin_free, :margin_level, :leverage, :company, 
                NOW(), 1, NOW(), 'csv')
    ");
    
    // Calcular balance y equity si está habilitado
    if ($options['calculateBalance']) {
        $balanceCalc = calculateBalanceFromDeals($csvData['deals']);
        $accountData['balance'] = $balanceCalc['final_balance'];
        $accountData['equity'] = $balanceCalc['final_balance'];
    }
    
    $stmt->execute($accountData);
    $auditLog[] = "Cuenta creada: #{$accountData['login']} - {$accountData['name']}";
    
    // Preparar inserción de deals
    $stmt = $pdo->prepare("
        INSERT INTO deals (ticket, account_login, time, type, type_desc, entry, volume, 
                          price, profit, commission, swap, symbol, comment, position_id, 
                          magic, reason, order_id, sl, tp)
        VALUES (:ticket, :account_login, :time, :type, :type_desc, :entry, :volume, 
                :price, :profit, :commission, :swap, :symbol, :comment, :position_id, 
                :magic, :reason, :order_id, :sl, :tp)
    ");
    
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($csvData['deals'] as $index => $deal) {
        try {
            // Asignar login de cuenta
            $deal['account_login'] = $accountData['login'];
            
            // Agregar campos sl y tp si no existen
            if (!isset($deal['sl'])) $deal['sl'] = 0;
            if (!isset($deal['tp'])) $deal['tp'] = 0;
            
            // Validar duplicados si está habilitado
            if ($options['validateDuplicates']) {
                $checkStmt = $pdo->prepare("SELECT ticket FROM deals WHERE ticket = :ticket AND account_login = :login");
                $checkStmt->execute(['ticket' => $deal['ticket'], 'login' => $accountData['login']]);
                if ($checkStmt->fetch()) {
                    $response['warnings'][] = "Ticket #{$deal['ticket']} ya existe, omitido.";
                    $skipped++;
                    continue;
                }
            }
            
            // Determinar descripción del tipo
            $deal['type_desc'] = getTypeDescription($deal['type']);
            
            // Ejecutar inserción
            $stmt->execute($deal);
            $imported++;
            
            // Log para operaciones importantes
            if ($deal['type'] == 2 && $deal['profit'] != 0) {
                $moveType = $deal['profit'] > 0 ? 'Depósito' : 'Retiro';
                $auditLog[] = "$moveType: {$deal['profit']} {$accountData['currency']} - {$deal['time']}";
            }
            
        } catch (PDOException $e) {
            $errors++;
            $response['errors'][] = "Error en línea " . ($index + 2) . ": " . $e->getMessage();
            
            if ($errors > 100) {
                throw new Exception('Demasiados errores. Importación cancelada.');
            }
        }
    }
    
    // Actualizar estadísticas de respuesta
    $response['details']['processed'] = count($csvData['deals']);
    $response['details']['imported'] = $imported;
    $response['details']['skipped'] = $skipped;
    $response['details']['errors'] = $errors;
    
    // Guardar log de auditoría
    saveAuditLog($pdo, $accountData['login'], $auditLog, $response['details']);
    
    // Confirmar transacción
    $pdo->commit();
    
    // Preparar respuesta exitosa
    $response['success'] = true;
    $response['message'] = sprintf(
        'Importación completada: %d registros importados de %d procesados.',
        $imported,
        $response['details']['processed']
    );
    
    // Generar resumen
    $summary = generateImportSummary($pdo, $accountData['login']);
    $response['summary'] = $summary;
    
} catch (Exception $e) {
    // Revertir transacción SOLO si existe Y está activa
    try {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    } catch (PDOException $rollbackException) {
        // Ignorar errores de rollback
        error_log('[CSV Import Rollback Error] ' . $rollbackException->getMessage());
    }
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    $response['errors'][] = $e->getMessage();
    
    // Log de error con más detalles
    error_log('[CSV Import Error] ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine());
}

// Enviar respuesta JSON y terminar
sendJsonResponse($response);

// ============================================
// FUNCIONES AUXILIARES
// ============================================

/**
 * Procesar archivo CSV y validar datos
 */
function processCSVFile($filepath, $options) {
    $result = [
        'deals' => [],
        'positions' => []
    ];
    
    if (!file_exists($filepath)) {
        throw new Exception('El archivo CSV no existe.');
    }
    
    $file = fopen($filepath, 'r');
    if (!$file) {
        throw new Exception('No se puede abrir el archivo CSV.');
    }
    
    // Leer encabezados
    $headers = fgetcsv($file);
    if (!$headers) {
        fclose($file);
        throw new Exception('El archivo CSV está vacío o mal formateado.');
    }
    
    // Normalizar encabezados
    $headers = array_map(function($h) {
        return strtolower(trim($h));
    }, $headers);
    
    // Validar columnas requeridas
    $requiredColumns = ['ticket', 'time', 'type', 'symbol', 'volume', 'price', 'profit'];
    $missingColumns = array_diff($requiredColumns, $headers);
    if (!empty($missingColumns)) {
        fclose($file);
        throw new Exception('Columnas faltantes en CSV: ' . implode(', ', $missingColumns));
    }
    
    // Procesar filas
    $lineNumber = 2;
    while (($row = fgetcsv($file)) !== FALSE) {
        if (count($row) !== count($headers)) {
            continue; // Saltar filas mal formateadas
        }
        
        $data = array_combine($headers, $row);
        
        // Limpiar y validar datos
        $deal = [
            'ticket' => intval($data['ticket']),
            'time' => validateDateTime($data['time'], $options['validateDates']),
            'type' => intval($data['type']),
            'entry' => intval($data['entry'] ?? 0),
            'symbol' => trim($data['symbol'] ?? ''),
            'volume' => floatval($data['volume'] ?? 0),
            'price' => floatval($data['price'] ?? 0),
            'sl' => floatval($data['sl'] ?? 0),
            'tp' => floatval($data['tp'] ?? 0),
            'profit' => floatval($data['profit'] ?? 0),
            'commission' => floatval($data['commission'] ?? 0),
            'swap' => floatval($data['swap'] ?? 0),
            'comment' => substr(trim($data['comment'] ?? ''), 0, 255),
            'position_id' => intval($data['position_id'] ?? 0),
            'magic' => intval($data['magic'] ?? 0),
            'reason' => intval($data['reason'] ?? 0),
            'order_id' => intval($data['order_id'] ?? 0)
        ];
        
        // Validar campos numéricos si está habilitado
        if ($options['validateNumbers']) {
            if ($deal['ticket'] <= 0) {
                throw new Exception("Ticket inválido en línea $lineNumber");
            }
            if ($deal['type'] < 0 || $deal['type'] > 17) {
                throw new Exception("Tipo de operación inválido en línea $lineNumber");
            }
        }
        
        $result['deals'][] = $deal;
        $lineNumber++;
    }
    
    fclose($file);
    
    // Ordenar por fecha
    usort($result['deals'], function($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });
    
    return $result;
}

/**
 * Validar y formatear fecha/hora
 */
function validateDateTime($datetime, $validate = true) {
    if (!$validate) {
        return $datetime;
    }
    
    // Limpiar espacios y caracteres invisibles
    $datetime = trim($datetime);
    
    // Intentar varios formatos
    $formats = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y/m/d H:i:s',
        'd/m/Y H:i:s',
        'm/d/Y H:i:s',
        'd.m.Y H:i:s',
        'Y-m-d\TH:i:s'
    ];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $datetime);
        if ($date !== false) {
            return $date->format('Y-m-d H:i:s');
        }
    }
    
    // Intentar con strtotime como último recurso
    $timestamp = strtotime($datetime);
    if ($timestamp !== false && $timestamp > 0) {
        return date('Y-m-d H:i:s', $timestamp);
    }
    
    throw new Exception("Formato de fecha inválido: $datetime");
}

/**
 * Obtener descripción del tipo de operación
 */
function getTypeDescription($type) {
    $types = [
        0 => 'Buy',
        1 => 'Sell',
        2 => 'Balance',
        3 => 'Credit',
        4 => 'Charge',
        5 => 'Correction',
        6 => 'Bonus',
        7 => 'Commission',
        8 => 'Commission Daily',
        9 => 'Commission Monthly',
        10 => 'Interest',
        11 => 'Buy Canceled',
        12 => 'Sell Canceled',
        13 => 'Dividend',
        14 => 'Franked Dividend',
        15 => 'Tax'
    ];
    
    return $types[$type] ?? 'Unknown';
}

/**
 * Calcular balance desde deals
 */
function calculateBalanceFromDeals($deals) {
    $balance = 0;
    $deposits = 0;
    $withdrawals = 0;
    $trading_profit = 0;
    $commissions = 0;
    $swaps = 0;
    
    foreach ($deals as $deal) {
        // Sumar profit + commission + swap
        $total = $deal['profit'] + $deal['commission'] + $deal['swap'];
        $balance += $total;
        
        // Clasificar por tipo
        if ($deal['type'] == 2) { // Balance
            if ($deal['profit'] > 0) {
                $deposits += $deal['profit'];
            } else {
                $withdrawals += abs($deal['profit']);
            }
        } elseif ($deal['type'] == 0 || $deal['type'] == 1) { // Trading
            $trading_profit += $deal['profit'];
            $commissions += $deal['commission'];
            $swaps += $deal['swap'];
        }
    }
    
    return [
        'final_balance' => $balance,
        'total_deposits' => $deposits,
        'total_withdrawals' => $withdrawals,
        'trading_profit' => $trading_profit,
        'total_commissions' => $commissions,
        'total_swaps' => $swaps
    ];
}

/**
 * Guardar log de auditoría
 */
function saveAuditLog($pdo, $accountLogin, $auditLog, $details) {
    try {
        // Crear tabla de logs si no existe
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
        
        // Insertar log
        $stmt = $pdo->prepare("
            INSERT INTO import_logs (account_login, import_type, processed, 
                                    imported, skipped, errors, 
                                    audit_log, import_user, ip_address)
            VALUES (:account_login, 'CSV', :processed, :imported, :skipped, :errors, 
                    :audit_log, :user, :ip)
        ");
        
        $stmt->execute([
            'account_login' => $accountLogin,
            'processed' => $details['processed'],
            'imported' => $details['imported'],
            'skipped' => $details['skipped'],
            'errors' => $details['errors'],
            'audit_log' => json_encode($auditLog, JSON_UNESCAPED_UNICODE),
            'user' => $_SESSION['username'] ?? 'admin',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
    } catch (PDOException $e) {
        // Log silencioso, no interrumpir el proceso principal
        error_log('[Audit Log Error] ' . $e->getMessage());
    }
}

/**
 * Generar resumen de importación
 */
function generateImportSummary($pdo, $accountLogin) {
    $summary = [];
    
    try {
        // Obtener estadísticas de la cuenta importada
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(CASE WHEN type IN (0,1) THEN 1 END) as total_trades,
                COUNT(CASE WHEN type = 2 AND profit > 0 THEN 1 END) as total_deposits,
                COUNT(CASE WHEN type = 2 AND profit < 0 THEN 1 END) as total_withdrawals,
                SUM(CASE WHEN type IN (0,1) THEN profit + commission + swap ELSE 0 END) as trading_pl,
                SUM(CASE WHEN type = 2 AND profit > 0 THEN profit ELSE 0 END) as sum_deposits,
                SUM(CASE WHEN type = 2 AND profit < 0 THEN ABS(profit) ELSE 0 END) as sum_withdrawals,
                MIN(time) as first_operation,
                MAX(time) as last_operation,
                COUNT(DISTINCT symbol) as symbols_traded,
                COUNT(CASE WHEN type IN (0,1) AND profit > 0 THEN 1 END) as winning_trades,
                COUNT(CASE WHEN type IN (0,1) AND profit < 0 THEN 1 END) as losing_trades
            FROM deals 
            WHERE account_login = :login
        ");
        $stmt->execute(['login' => $accountLogin]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calcular métricas adicionales
        $winRate = 0;
        if ($stats['total_trades'] > 0) {
            $winRate = ($stats['winning_trades'] / $stats['total_trades']) * 100;
        }
        
        $netDeposits = $stats['sum_deposits'] - $stats['sum_withdrawals'];
        $finalBalance = $netDeposits + $stats['trading_pl'];
        
        $summary = [
            'account_login' => $accountLogin,
            'total_operations' => $stats['total_trades'] + $stats['total_deposits'] + $stats['total_withdrawals'],
            'total_trades' => $stats['total_trades'],
            'winning_trades' => $stats['winning_trades'],
            'losing_trades' => $stats['losing_trades'],
            'win_rate' => round($winRate, 2),
            'total_deposits' => $stats['total_deposits'],
            'total_withdrawals' => $stats['total_withdrawals'],
            'sum_deposits' => round($stats['sum_deposits'], 2),
            'sum_withdrawals' => round($stats['sum_withdrawals'], 2),
            'net_deposits' => round($netDeposits, 2),
            'trading_pl' => round($stats['trading_pl'], 2),
            'final_balance' => round($finalBalance, 2),
            'symbols_traded' => $stats['symbols_traded'],
            'date_range' => [
                'from' => $stats['first_operation'],
                'to' => $stats['last_operation']
            ]
        ];
        
        // Actualizar balance en la cuenta
        $updateStmt = $pdo->prepare("
            UPDATE accounts 
            SET balance = :balance, 
                equity = :equity,
                last_update = NOW()
            WHERE login = :login
        ");
        $updateStmt->execute([
            'balance' => $finalBalance,
            'equity' => $finalBalance,
            'login' => $accountLogin
        ]);
        
    } catch (PDOException $e) {
        error_log('[Summary Error] ' . $e->getMessage());
    }
    
    return $summary;
}
?>