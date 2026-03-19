<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sync_session_identity() {
    if (!empty($_SESSION['user']) && is_array($_SESSION['user']) && empty($_SESSION['user_id'])) {
        $_SESSION['user_id'] = $_SESSION['user']['id'] ?? null;
        $_SESSION['role'] = $_SESSION['user']['role'] ?? ($_SESSION['role'] ?? null);
        $_SESSION['department_id'] = $_SESSION['user']['department_id'] ?? ($_SESSION['department_id'] ?? null);
        $_SESSION['username'] = $_SESSION['user']['username'] ?? ($_SESSION['username'] ?? null);
    }

    if (empty($_SESSION['user']) && !empty($_SESSION['user_id'])) {
        $_SESSION['user'] = [
            'id' => $_SESSION['user_id'],
            'role' => $_SESSION['role'] ?? null,
            'department_id' => $_SESSION['department_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
        ];
    }
}

sync_session_identity();

function require_login() {
    if (empty($_SESSION['user_id']) && empty($_SESSION['user'])) {
        header('Location: index.php');
        exit;
    }

    sync_session_identity();
}

function require_admin() {
    require_login();
    $role = $_SESSION['role'] ?? ($_SESSION['user']['role'] ?? '');
    if ($role !== 'admin') {
        http_response_code(403);
        die('Acces refuse (admin seulement).');
    }
}

// Chef de service ou admin
function require_manager() {
    require_login();
    $role = $_SESSION['role'] ?? ($_SESSION['user']['role'] ?? '');
    if (!in_array($role, ['admin', 'chef_service'], true)) {
        http_response_code(403);
        die('Acces refuse (chef de service ou admin).');
    }
}

// Secretaire, secretaria ou admin - pour acces au bureau d'ordre
function require_secretary_or_admin() {
    require_login();
    $role = $_SESSION['role'] ?? ($_SESSION['user']['role'] ?? '');
    if (!in_array($role, ['admin', 'secretaire', 'secretaria'], true)) {
        http_response_code(403);
        die('Acces refuse (secretaire ou admin seulement).');
    }
}

function log_action(PDO $pdo, $user_id, $action, $details = null) {
    $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $action, $details]);
}
