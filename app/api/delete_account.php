<?php
//+------------------------------------------------------------------+
//| delete_account.php                                              |
//| API para eliminar cuenta y todos sus datos - Elysium v7.0      |
//| Copyright 2025, Elysium Media FZCO                              |
//| Gestión Profesional de Fondos de Trading                        |
//+------------------------------------------------------------------+

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Configuración de base de datos
$host = 'localhost';
$dbname = 'elysium_dashboard';
$user = 'elysium_dashboard';
$pass = '@01Mwdsz4';

// CONFIGURACIÓN DE CONTRASEÑA DE ELIMINACIÓN
// Cambia esta contraseña por una segura
define('DELETE_PASSWORD', 'Elysium2025Delete!');
// Alternativamente, puedes usar hash para mayor seguridad:
// define('DELETE_PASSWORD_HASH', password_hash('TuContraseñaSegura', PASSWORD_DEFAULT));

// Respuesta estructurada
$response = [
    'success' => false,
    'timestamp' => time(),
    'message' => '',
    'deleted' => []
];

try {
    // Obtener datos del request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['login']) || empty($input['login'])) {
        throw new Exception('Login de cuenta no proporcionado');
    }
    
    if (!isset($input['password']) || empty($input['password'])) {
        throw new Exception('Contraseña no proporcionada');
    }
    
    $login = intval($input['login']);
    $password = $input['password'];
    
    // Validación de contraseña
    // Opción 1: Comparación directa (más simple)
    if ($password !== DELETE_PASSWORD) {
        throw new Exception('Contraseña incorrecta');
    }
    
    // Opción 2: Usando hash (más seguro, descomenta si usas hash)
    // if (!password_verify($password, DELETE_PASSWORD_HASH)) {
    //     throw new Exception('Contraseña incorrecta');
    // }
    
    // Validación adicional del login
    if ($login <= 0) {
        throw new Exception('Login de cuenta inválido');
    }
    
    // Conexión a la base de datos
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Iniciar transacción para asegurar integridad
    $pdo->beginTransaction();
    
    try {
        // ============================
        // 1. VERIFICAR QUE LA CUENTA EXISTE
        // ============================
        $stmt = $pdo->prepare("SELECT login, name FROM accounts WHERE login = :login");
        $stmt->execute(['login' => $login]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            throw new Exception("La cuenta #$login no existe");
        }
        
        $accountName = $account['name'] ?? 'Sin nombre';
        
        // ============================
        // 2. CONTAR REGISTROS A ELIMINAR (para auditoría)
        // ============================
        $counts = [];
        
        // Contar posiciones
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM positions WHERE account_login = :login");
        $stmt->execute(['login' => $login]);
        $counts['positions'] = $stmt->fetchColumn();
        
        // Contar deals
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM deals WHERE account_login = :login");
        $stmt->execute(['login' => $login]);
        $counts['deals'] = $stmt->fetchColumn();
        
        // Contar import logs (si existen)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM import_logs WHERE account_login = :login");
        $stmt->execute(['login' => $login]);
        $counts['import_logs'] = $stmt->fetchColumn();
        
        // ============================
        // 3. ELIMINAR TODOS LOS DATOS RELACIONADOS
        // ============================
        
        // Eliminar posiciones
        $stmt = $pdo->prepare("DELETE FROM positions WHERE account_login = :login");
        $stmt->execute(['login' => $login]);
        $deletedPositions = $stmt->rowCount();
        
        // Eliminar deals (historial)
        $stmt = $pdo->prepare("DELETE FROM deals WHERE account_login = :login");
        $stmt->execute(['login' => $login]);
        $deletedDeals = $stmt->rowCount();
        
        // Eliminar import logs si existen
        $stmt = $pdo->prepare("DELETE FROM import_logs WHERE account_login = :login");
        $stmt->execute(['login' => $login]);
        $deletedLogs = $stmt->rowCount();
        
        // Eliminar import validations relacionadas (si existen)
        // Primero obtener los IDs de import_logs que se eliminaron
        $stmt = $pdo->prepare("
            DELETE iv FROM import_validations iv
            INNER JOIN import_logs il ON iv.import_log_id = il.id
            WHERE il.account_login = :login
        ");
        $stmt->execute(['login' => $login]);
        $deletedValidations = $stmt->rowCount();
        
        // Finalmente, eliminar la cuenta principal
        $stmt = $pdo->prepare("DELETE FROM accounts WHERE login = :login");
        $stmt->execute(['login' => $login]);
        $deletedAccount = $stmt->rowCount();
        
        // ============================
        // 4. VERIFICAR QUE SE ELIMINÓ TODO
        // ============================
        if ($deletedAccount !== 1) {
            throw new Exception("Error al eliminar la cuenta principal");
        }
        
        // Confirmar transacción
        $pdo->commit();
        
        // ============================
        // 5. REGISTRAR EN LOG DE AUDITORÍA
        // ============================
        $logMessage = sprintf(
            "[DELETE] Usuario autenticado eliminó cuenta #%d (%s). Positions: %d, Deals: %d, Logs: %d. IP: %s",
            $login,
            $accountName,
            $deletedPositions,
            $deletedDeals,
            $deletedLogs,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        );
        error_log($logMessage);
        
        // Opcional: Guardar en tabla de auditoría
        try {
            $stmt = $pdo->prepare("
                INSERT INTO audit_log (action, entity_type, entity_id, details, user_ip, created_at)
                VALUES ('DELETE', 'ACCOUNT', :login, :details, :ip, NOW())
            ");
            $stmt->execute([
                'login' => $login,
                'details' => json_encode([
                    'account_name' => $accountName,
                    'deleted_positions' => $deletedPositions,
                    'deleted_deals' => $deletedDeals,
                    'deleted_logs' => $deletedLogs
                ]),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
            ]);
        } catch (Exception $e) {
            // Si no existe la tabla de auditoría, continuar sin error
        }
        
        // Preparar respuesta exitosa
        $response['success'] = true;
        $response['message'] = "Cuenta #$login ($accountName) eliminada exitosamente";
        $response['deleted'] = [
            'account' => $login,
            'account_name' => $accountName,
            'positions' => $deletedPositions,
            'deals' => $deletedDeals,
            'import_logs' => $deletedLogs,
            'validations' => $deletedValidations,
            'total_records' => $deletedPositions + $deletedDeals + $deletedLogs + $deletedValidations + 1
        ];
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    $response['success'] = false;
    $response['error'] = 'Error de base de datos';
    $response['details'] = $e->getMessage();
    error_log("[Elysium Delete Account] DB Error: " . $e->getMessage());
    http_response_code(500);
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
    
    // Log solo si no es error de contraseña (para no llenar el log)
    if ($e->getMessage() !== 'Contraseña incorrecta') {
        error_log("[Elysium Delete Account] Error: " . $e->getMessage());
    }
    
    // Usar código 403 para contraseña incorrecta
    if ($e->getMessage() === 'Contraseña incorrecta') {
        http_response_code(403);
    } else {
        http_response_code(400);
    }
}

// Enviar respuesta JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>