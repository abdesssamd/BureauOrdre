<?php
// 1. Démarrage Session
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Vérification connexion
if (empty($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'index.php') {
    header('Location: index.php');
    exit;
}

// Helper Notification
$helper_path = __DIR__ . '/notification_helper.php';
if (file_exists($helper_path)) { require_once $helper_path; }

// Récupération Notifications
$unread_count = 0;
if (isset($_SESSION['user_id']) && isset($pdo) && function_exists('getUnreadNotificationsCount')) {
    $unread_count = getUnreadNotificationsCount($pdo, $_SESSION['user_id']);
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Administration Publique</title>
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
</head>
<body>

    <button type="button" class="mobile-menu-btn" onclick="toggleMenu()" style="z-index: 9999;">
        <i class="fa-solid fa-bars fa-lg"></i>
    </button>

    <div class="sidebar" id="mySidebar">
        <div class="brand">
            <i class="fa-solid fa-building-columns"></i>
            <span>AdminShare</span>
        </div>

        <div class="user-profile-section" style="padding:15px; background:rgba(255,255,255,0.1); border-radius:12px; margin-bottom:20px;">
            <div style="font-weight:bold;"><?= htmlspecialchars($_SESSION['username'] ?? 'Utilisateur') ?></div>
            <div style="font-size:0.8rem; opacity:0.8;"><?= htmlspecialchars($_SESSION['department_name'] ?? '') ?></div>
        </div>

        <ul class="nav-links">
            <li><a href="dashboard.php" class="<?= $current_page=='dashboard.php'?'active':'' ?>"><i class="fa-solid fa-gauge"></i> Tableau de bord</a></li>
			<li style="margin-top:10px; font-size:0.7rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; padding-left:10px;">Bureau d'Ordre</li>

			<li>
				<a href="mail_add.php" class="<?= $current_page=='mail_add.php'?'active':'' ?>">
					<i class="fa-solid fa-pen-to-square"></i> Enregistrer
				</a>
			</li>
			
			<li>
				<a href="registre.php" class="<?= $current_page=='registre.php'?'active':'' ?>">
					<i class="fa-solid fa-book"></i> Registres
				</a>
			</li>
			
			<li>
				<a href="my_tasks.php" class="<?= $current_page=='my_tasks.php'?'active':'' ?>">
					<i class="fa-solid fa-inbox"></i> À Traiter
				</a>
			</li>
<li style="margin-top:15px; font-size:0.7rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px; padding-left:10px;">Gestion Documentaire</li>
<li><a href="mail_add.php"><i class="fa-solid fa-envelope-open-text"></i> Bureau d'Ordre</a></li>            
<li><a href="files.php" class="<?= $current_page=='files.php'?'active':'' ?>"><i class="fa-regular fa-folder-open"></i> Mes Fichiers</a></li>
            <li><a href="department_files.php" class="<?= $current_page=='department_files.php'?'active':'' ?>"><i class="fa-solid fa-sitemap"></i> Service</a></li>
            <li><a href="upload.php" class="<?= $current_page=='upload.php'?'active':'' ?>"><i class="fa-solid fa-cloud-arrow-up"></i> Dépôt</a></li>
            <li>
                <a href="notifications.php" class="<?= $current_page=='notifications.php'?'active':'' ?>">
                    <i class="fa-solid fa-bell"></i> Notifications
                    <?php if($unread_count > 0): ?>
                        <span style="background:#ef4444; color:white; padding:2px 6px; border-radius:10px; font-size:0.7rem; margin-left:auto;"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <?php if(($_SESSION['role'] ?? '') === 'admin'): ?>
                <li style="margin-top:15px; font-size:0.7rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px;">Administration</li>
                <li><a href="users.php" class="<?= $current_page=='users.php'?'active':'' ?>"><i class="fa-solid fa-users-gear"></i> Utilisateurs</a></li>
                <li><a href="logs.php" class="<?= $current_page=='logs.php'?'active':'' ?>"><i class="fa-solid fa-list-check"></i> Logs</a></li>
            <?php endif; ?>

            <li style="margin-top:auto; padding-top:20px;">
                <a href="logout.php" style="color:#fca5a5;"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
            </li>
        </ul>
    </div>

    <div class="content-area">

    <script>
        function toggleMenu() {
            // On récupère le menu par son ID
            var sidebar = document.getElementById("mySidebar");
            
            // On ajoute ou enlève la classe 'active'
            sidebar.classList.toggle("active");
            
            // Debug : Affiche un message dans la console (F12) pour vérifier
            console.log("Menu cliqué ! Etat classes :", sidebar.className);
        }
    </script>