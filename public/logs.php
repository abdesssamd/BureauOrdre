<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// Sécurité : Admin uniquement
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Récupération des logs avec jointure pour avoir le nom de l'utilisateur
// On utilise 'details' et 'action' qui sont dans votre BDD
$sql = "SELECT logs.*, users.username, users.full_name 
        FROM logs 
        LEFT JOIN users ON logs.user_id = users.id 
        ORDER BY logs.created_at DESC 
        LIMIT 100";
$stmt = $pdo->query($sql);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Logs Système - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="assets/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="main-container">
        <?php include '../includes/header.php'; ?>

        <div class="content-area">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
                <h1><i class="fa-solid fa-list-check"></i> Traçabilité (Logs)</h1>
                
                <div>
                    <a href="export_logs_excel.php" class="btn-sm" style="background:#10b981; color:white; border:none; margin-right:5px;">
                        <i class="fa-solid fa-file-excel"></i> Excel
                    </a>
                    <a href="export_logs_pdf.php" class="btn-sm" style="background:#ef4444; color:white; border:none;">
                        <i class="fa-solid fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>

            <div class="recent-files">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date & Heure</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                        <tr>
                            <td style="color:#64748b; font-size:0.9rem;">
                                <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($log['username'] ?? 'Inconnu') ?></strong>
                            </td>
                            <td>
                                <?php
                                    // Style conditionnel selon l'action
                                    $style = "color: #334155;";
                                    if(stripos($log['action'], 'delete') !== false || stripos($log['action'], 'suppression') !== false) {
                                        $style = "color: #ef4444; font-weight:bold;";
                                    } elseif(stripos($log['action'], 'upload') !== false || stripos($log['action'], 'ajout') !== false) {
                                        $style = "color: #10b981; font-weight:bold;";
                                    }
                                ?>
                                <span style="<?= $style ?>"><?= htmlspecialchars($log['action']) ?></span>
                            </td>
                            <td style="color:#475569;">
                                <?= htmlspecialchars($log['details'] ?? '-') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if(count($logs) === 0): ?>
                    <p style="text-align:center; padding:2rem; color:#94a3b8;">Aucun historique disponible.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>