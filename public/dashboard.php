<?php
// 1. Connexion BDD
require_once '../config/db.php'; 

// 2. Inclusion du HEADER (C'est lui qui démarre la session et ouvre le HTML <body>)
include '../includes/header.php'; 

// 3. Logique PHP (Calculs)
$total_files = $pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();
$total_size = round($pdo->query("SELECT SUM(size) FROM files")->fetchColumn() / 1048576, 2);

// Requête Fichiers Récents
$dept_id = $_SESSION['department_id'] ?? 0;
$sql_recent = "
    SELECT files.*, users.username, departments.name as dept_name 
    FROM files 
    LEFT JOIN users ON files.owner_id = users.id 
    LEFT JOIN departments ON files.department_id = departments.id
    WHERE files.visibility = 'public' OR files.department_id = ?
    ORDER BY files.uploaded_at DESC 
    LIMIT 5";
$stmt = $pdo->prepare($sql_recent);
$stmt->execute([$dept_id]);
$recent_files = $stmt->fetchAll();

// Requête Logs Récents
$sql_actions = "
    SELECT logs.*, users.username 
    FROM logs 
    JOIN users ON logs.user_id = users.id 
    ORDER BY logs.created_at DESC 
    LIMIT 5";
$last_actions = $pdo->query($sql_actions)->fetchAll();


$year = date('Y');
// 1. Total Arrivées cette année
$stats_arr = $pdo->query("SELECT COUNT(*) FROM mails WHERE type='arrivee' AND YEAR(created_at) = $year")->fetchColumn();
// 2. Total Départs cette année
$stats_dep = $pdo->query("SELECT COUNT(*) FROM mails WHERE type='depart' AND YEAR(created_at) = $year")->fetchColumn();
// 3. Courriers Aujourd'hui
$today = date('Y-m-d');
$stats_today = $pdo->query("SELECT COUNT(*) FROM mails WHERE DATE(created_at) = '$today'")->fetchColumn();
?>

<h1>Tableau de Bord</h1>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3>Total Fichiers</h3>
            <p><?= $total_files ?></p>
        </div>
        <div class="stat-icon icon-blue">
            <i class="fa-solid fa-file"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <h3>Volume (Mo)</h3>
            <p><?= $total_size ?></p>
        </div>
        <div class="stat-icon icon-purple">
            <i class="fa-solid fa-server"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <h3>Service</h3>
            <p><?= htmlspecialchars($_SESSION['department_name'] ?? '-') ?></p>
        </div>
        <div class="stat-icon icon-green">
            <i class="fa-solid fa-building"></i>
        </div>
    </div>
</div>

<div style="display:flex; gap:20px; flex-wrap:wrap; margin-top:2rem;">
    
    <div class="recent-files" style="flex: 2; min-width: 300px;">
        <h2><i class="fa-solid fa-clock"></i> Derniers Documents</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Fichier</th>
                    <th>Ajouté par</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recent_files as $f): ?>
                <tr>
                    <td>
                        <i class="fa-regular fa-file" style="color:#64748b; margin-right:5px;"></i> 
                        <?= htmlspecialchars($f['original_name']) ?>
                    </td>
                    <td><?= htmlspecialchars($f['username']) ?></td>
                    <td><?= date('d/m H:i', strtotime($f['uploaded_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="recent-files" style="flex: 1; min-width: 300px;">
        <h2><i class="fa-solid fa-bolt"></i> Activité Récente</h2>
        <ul style="list-style:none; padding:0; margin:0;">
            <?php foreach($last_actions as $log): ?>
            <li style="border-bottom:1px solid #e2e8f0; padding:12px 0;">
                <div style="font-weight:bold; font-size:0.9rem; display:flex; align-items:center;">
                    <i class="fa-solid fa-circle-user" style="color:#64748b; margin-right:8px;"></i> 
                    <?= htmlspecialchars($log['username']) ?>
                </div>
                <div style="color:var(--primary-color); font-size:0.85rem; margin:4px 0;">
                    <?= htmlspecialchars($log['action']) ?>
                </div>
                <div style="font-size:0.75rem; color:#94a3b8;">
                    <?= date('d/m/Y à H:i', strtotime($log['created_at'])) ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
	<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-info">
            <h3>Arrivées (<?= $year ?>)</h3>
            <p><?= $stats_arr ?></p>
        </div>
        <div class="stat-icon" style="background:#dcfce7; color:#166534;">
            <i class="fa-solid fa-inbox"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <h3>Départs (<?= $year ?>)</h3>
            <p><?= $stats_dep ?></p>
        </div>
        <div class="stat-icon" style="background:#fef3c7; color:#b45309;">
            <i class="fa-solid fa-paper-plane"></i>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-info">
            <h3>Aujourd'hui</h3>
            <p><?= $stats_today ?></p>
        </div>
        <div class="stat-icon icon-blue">
            <i class="fa-solid fa-calendar-day"></i>
        </div>
    </div>
	</div>

</div>

<?php 
// 5. Inclusion du FOOTER (Ferme les balises)
include '../includes/footer.php'; 
?>