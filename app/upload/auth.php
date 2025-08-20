<?php
//+------------------------------------------------------------------+
//| auth.php - Protección simple con sesiones                       |
//| Copyright 2025, Elysium Media FZCO                              |
//| COLOCAR EN: /upload/auth.php                                    |
//+------------------------------------------------------------------+

session_start();

// Usuarios permitidos (usuario => contraseña)
$valid_users = [
    'admin' => '@01Mwdsz4',
    'elysium' => 'elysium2025',
    'upload' => 'upload2025'
];

// Verificar si está autenticado
$is_authenticated = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Si no está autenticado y no está en login.php, redirigir
if (!$is_authenticated && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header('Location: login.php');
    exit;
}

// Función para verificar login
function checkLogin($username, $password) {
    global $valid_users;
    return isset($valid_users[$username]) && $valid_users[$username] === $password;
}

// Función para hacer logout
function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}