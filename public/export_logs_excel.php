<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_admin();

require('../vendor/SimpleXLSXGen.php');

$rows = [["Date", "Utilisateur", "Action", "Details"]];

$stmt = $pdo->query("
    SELECT l.*, u.full_name
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
");

while ($log = $stmt->fetch()) {
    $rows[] = [
        $log['created_at'],
        $log['full_name'] ?? 'Systeme',
        $log['action'],
        $log['details']
    ];
}

$xlsx = SimpleXLSXGen::fromArray($rows);
$xlsx->downloadAs('logs_export.xlsx');
