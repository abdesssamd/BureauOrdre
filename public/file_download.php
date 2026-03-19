<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/share_acl.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Non autorisé');
}

share_acl_ensure_schema($pdo);

$file_id = (int)($_GET['id'] ?? 0);
$inline = isset($_GET['inline']) && (int)$_GET['inline'] === 1;
$user_id = (int)$_SESSION['user_id'];
$dept_id = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : null;

$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$file) {
    http_response_code(404);
    exit('Fichier introuvable');
}

$perm = share_acl_get_file_permission($pdo, $file_id, $user_id, $dept_id);
$allowed = $inline ? !empty($perm['can_view']) : !empty($perm['can_download']);
if (!$allowed) {
    http_response_code(403);
    exit('Accès refusé');
}

$path = dirname(__DIR__) . '/uploads/' . $file['stored_name'];
if (!is_file($path)) {
    http_response_code(404);
    exit('Fichier absent');
}

$mime = $file['mime_type'] ?: 'application/octet-stream';
$name = $file['original_name'] ?: basename($path);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . rawurlencode($name) . '"');
readfile($path);
exit;

