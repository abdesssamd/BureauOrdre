<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
if (empty($_SESSION['user_id'])) { header('HTTP/1.1 403 Forbidden'); exit; }
$msg_id = (int)($_GET['id'] ?? 0);
if ($msg_id <= 0) { header('HTTP/1.1 400 Bad Request'); exit; }

$stmt = $pdo->prepare("
    SELECT m.attachment_stored, m.attachment_original, m.attachment_mime
    FROM msg_messages m
    JOIN msg_participants p ON p.conversation_id = m.conversation_id AND p.user_id = ?
    WHERE m.id = ? AND m.attachment_stored IS NOT NULL
");
$stmt->execute([$_SESSION['user_id'], $msg_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { header('HTTP/1.1 404 Not Found'); exit; }

$path = dirname(__DIR__) . '/uploads/messenger/' . basename($row['attachment_stored']);
if (!is_file($path)) { header('HTTP/1.1 404 Not Found'); exit; }

$name = $row['attachment_original'] ?: 'fichier';
$inline = isset($_GET['inline']) && strpos($row['attachment_mime'] ?? '', 'image/') === 0;
header('Content-Type: ' . ($row['attachment_mime'] ?: 'application/octet-stream'));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($name) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
