<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_login();

include '../includes/header.php';

// Nombre de fichiers de l'utilisateur
$stmt = $pdo->prepare("SELECT COUNT(*) AS nb FROM files WHERE owner_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$myFiles = $stmt->fetch()['nb'] ?? 0;

// Nombre de fichiers partagés avec son département
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT f.id) AS nb
    FROM files f
    JOIN file_shares s ON s.file_id = f.id
    WHERE s.shared_with_department_id = ?
");
$stmt->execute([$_SESSION['department_id']]);
$deptFiles = $stmt->fetch()['nb'] ?? 0;
?>

<h2>Tableau de bord</h2>
<ul>
    <li>Mes fichiers : <?= (int)$myFiles ?></li>
    <li>Fichiers partagés avec mon service : <?= (int)$deptFiles ?></li>
</ul>

<?php include '../includes/footer.php'; ?>
