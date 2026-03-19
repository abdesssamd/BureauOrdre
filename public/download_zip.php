<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/share_acl.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
if (!class_exists('ZipArchive')) {
    header('Location: files.php?err=nozip');
    exit;
}

$ids = $_GET['ids'] ?? '';
if (empty($ids)) {
    header('Location: files.php');
    exit;
}

$ids_arr = array_map('intval', array_filter(explode(',', $ids)));
if (empty($ids_arr)) {
    header('Location: files.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$dept_id = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : null;
share_acl_ensure_schema($pdo);

$placeholders = implode(',', array_fill(0, count($ids_arr), '?'));
$stmt = $pdo->prepare("SELECT f.* FROM files f WHERE f.id IN ($placeholders)");
$stmt->execute($ids_arr);
$all_files = $stmt->fetchAll();

$files = [];
foreach ($all_files as $f) {
    $perm = share_acl_get_file_permission($pdo, (int)$f['id'], (int)$user_id, $dept_id);
    if (!empty($perm['can_download'])) {
        $files[] = $f;
    }
}

if (empty($files)) {
    header('Location: files.php?err=access');
    exit;
}

$zip = new ZipArchive();
$tmp_file = tempnam(sys_get_temp_dir(), 'zip_');

if ($zip->open($tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    header('Location: files.php?err=zip');
    exit;
}

$base = dirname(__DIR__) . '/uploads/';
foreach ($files as $f) {
    $path = $base . $f['stored_name'];
    if (file_exists($path)) {
        $zip->addFile($path, $f['original_name']);
    }
}

$zip->close();

$filename = 'documents_' . date('Y-m-d_H-i') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmp_file));
readfile($tmp_file);
unlink($tmp_file);
exit;
