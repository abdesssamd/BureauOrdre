<?php
// 1. Configuration Langue (DOIT ÊTRE EN PREMIER)
require_once 'lang_setup.php';

// 2. Vérification Session
if (empty($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) != 'index.php') {
    header('Location: index.php'); exit;
}

$helper_path = __DIR__ . '/notification_helper.php';
if (file_exists($helper_path)) { require_once $helper_path; }

// --- RECUPERATION DONNÉES ---
$user_id = $_SESSION['user_id'];

// Compteur non lus
$unread_count = 0;
if (isset($pdo) && function_exists('getUnreadNotificationsCount')) {
    $unread_count = getUnreadNotificationsCount($pdo, $user_id);
}

// Les 5 dernières notifications
$last_notifs = [];
if (isset($pdo)) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM notifications
        WHERE user_id = ?
        ORDER BY
            is_read ASC,
            CASE
                WHEN type = 'system' THEN 0
                WHEN type = 'share' THEN 1
                WHEN type = 'upload' THEN 2
                ELSE 3
            END ASC,
            created_at DESC
        LIMIT 8
    ");
    $stmt->execute([$user_id]);
    $last_notifs = $stmt->fetchAll();
}

// Badge taches en attente (visible sans ouvrir Mes Taches)
$pending_tasks_count = 0;
if (isset($pdo)) {
    $dept_id = (int)($_SESSION['department_id'] ?? 0);
    $me = (int)($_SESSION['user_id'] ?? 0);
    if ($me > 0) {
        $stmt_tasks = $pdo->prepare("
            SELECT COUNT(*)
            FROM mail_assignments ma
            WHERE ma.status = 'en_cours'
              AND (ma.assigned_to = ? OR (? > 0 AND ma.assigned_to_dept = ?))
        ");
        $stmt_tasks->execute([$me, $dept_id, $dept_id]);
        $pending_tasks_count = (int)$stmt_tasks->fetchColumn();
    }
}

// Infos User
$page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? 'employe';
$username = $_SESSION['username'] ?? 'Utilisateur';
$dept_name = $_SESSION['department_name'] ?? 'Service';

$initials = "U";
if (!empty($username)) {
    $parts = explode(" ", $username);
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $initials .= strtoupper(substr($parts[1], 0, 1));
}
?>
<!DOCTYPE html>
<html lang="<?= $lang_code ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Administration</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
</head>
<body>

<nav class="navbar">
    
    <div class="nav-left">
        <a href="dashboard.php" class="brand">
            <i class="fa-solid fa-layer-group"></i> GCourier
        </a>

      <div class="nav-links">
            <?php if ($role !== 'secretaire'): ?>
            <a href="dashboard.php" class="nav-item <?= $page=='dashboard.php'?'active':'' ?>">
                <i class="fa-solid fa-chart-pie"></i> <span><?= $t['home'] ?></span>
            </a>
            <?php endif; ?>
            
            <div class="nav-item has-dropdown <?= in_array($page, ['registre.php', 'mail_add.php', 'mail_add_bulk.php', 'my_tasks.php', 'stats_courrier.php'])?'active':'' ?>">
                <i class="fa-solid fa-book-journal-whills"></i> <span><?= $t['bureau_ordre'] ?></span> <i class="fa-solid fa-caret-down" style="font-size:0.7em; margin-left:5px;"></i>
                
                <div class="dropdown-menu">
                    <?php if (in_array($role, ['admin', 'secretaire', 'secretaria'])): ?>
                    <a href="mail_add.php" class="dropdown-item">
                        <i class="fa-solid fa-pen-to-square"></i> <?= $t['save_mail'] ?>
                    </a>
                    <a href="mail_add_bulk.php" class="dropdown-item">
                        <i class="fa-solid fa-file-import"></i> Import en masse
                    </a>
                    <?php endif; ?>

                    <?php if (in_array($role, ['admin', 'secretaire', 'secretaria', 'directeur', 'chef_service'])): ?>
                    <a href="registre.php" class="dropdown-item">
                        <i class="fa-solid fa-book"></i> <?= $t['registers'] ?>
                    </a>
                    <?php endif; ?>
                    
                    <?php if (in_array($role, ['admin', 'directeur', 'chef_service'])): ?>
                    <a href="recherche.php" class="dropdown-item">
                        <i class="fa-solid fa-magnifying-glass"></i> <?= $t['search'] ?>
                    </a>
                    <a href="stats_courrier.php" class="dropdown-item">
                        <i class="fa-solid fa-chart-column"></i> Statistiques
                    </a>
                    <?php endif; ?>

                    <?php if (in_array($role, ['admin', 'directeur', 'chef_service', 'employe'])): ?>
                    <a href="my_tasks.php" class="dropdown-item">
                        <i class="fa-solid fa-list-check"></i> <?= $t['my_tasks'] ?>
                        <?php if ($pending_tasks_count > 0): ?>
                            <span class="badge-count" style="margin-left:8px; position:static; transform:none; vertical-align:middle;">
                                <?= $pending_tasks_count > 99 ? '99+' : $pending_tasks_count ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($role !== 'secretaire'): ?>
            <div class="nav-item has-dropdown <?= in_array($page, ['files.php', 'department_files.php', 'upload.php', 'folders.php'])?'active':'' ?>">
                <i class="fa-regular fa-folder-open"></i> <span><?= $t['documents'] ?></span> <i class="fa-solid fa-caret-down" style="font-size:0.7em; margin-left:5px;"></i>
                
                <div class="dropdown-menu">
                    <a href="files.php" class="dropdown-item"><i class="fa-solid fa-user-tag"></i> <?= $t['my_files'] ?></a>
                    
                    <a href="department_files.php" class="dropdown-item"><i class="fa-solid fa-sitemap"></i> <?= $t['dept_files'] ?></a>
                    
                    <a href="upload.php" class="dropdown-item"><i class="fa-solid fa-cloud-arrow-up"></i> <?= $t['upload_simple'] ?></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                <a href="users.php" class="nav-item <?= $page=='users.php'?'active':'' ?>" style="color:#fca5a5;">
                    <i class="fa-solid fa-users-gear"></i> <span><?= $t['admin'] ?></span>
                </a>
            <?php endif; ?>

            <?php if (in_array($role, ['admin', 'secretaire', 'secretaria'], true)): ?>
                <a href="fiscal_years.php" class="nav-item <?= $page=='fiscal_years.php'?'active':'' ?>">
                    <i class="fa-solid fa-calendar-days"></i> <span>Années budgétaires</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="nav-right">
        <button onclick="toggleTheme()" class="notif-btn" style="background:none; border:none; margin-right:15px;" title="Mode Sombre/Clair">
			<i id="themeIcon" class="fa-solid fa-moon"></i>
		</button>
        <div class="nav-item">
            <?php if($lang_code == 'fr'): ?>
                <a href="?lang=ar" title="العربية" style="color:white; text-decoration:none; font-weight:bold; font-family:'Tajawal', sans-serif;">
                    عربي
                </a>
            <?php else: ?>
                <a href="?lang=fr" title="Français" style="color:white; text-decoration:none; font-weight:bold;">
                    FR
                </a>
            <?php endif; ?>
        </div>

        <div class="has-dropdown">
            <a href="notifications.php" class="notif-btn" style="display:flex; align-items:center; height:100%;">
                <i class="fa-regular fa-bell"></i>
                <?php if($unread_count > 0): ?>
                    <span class="badge-count"><?= $unread_count ?></span>
                <?php endif; ?>
            </a>

            <div class="dropdown-menu notif-dropdown">
                <div class="notif-title"><?= $t['notifications'] ?></div>
                
                <?php if(count($last_notifs) > 0): ?>
                    <?php foreach($last_notifs as $n): ?>
                        <a href="notifications.php" class="notif-row <?= !$n['is_read'] ? 'unread' : '' ?>">
                            <p><?= htmlspecialchars($n['title']) ?></p>
                            <?php if (!empty($n['message'])): ?>
                                <small style="display:block; color:#64748b; margin-top:2px;">
                                    <?php
                                        $msg_preview = $n['message'];
                                        if (function_exists('mb_strimwidth')) {
                                            $msg_preview = mb_strimwidth($msg_preview, 0, 90, '...');
                                        } else {
                                            $msg_preview = strlen($msg_preview) > 90 ? substr($msg_preview, 0, 87) . '...' : $msg_preview;
                                        }
                                    ?>
                                    <?= htmlspecialchars($msg_preview) ?>
                                </small>
                            <?php endif; ?>
                            <small><i class="fa-regular fa-clock"></i> <?= date('d/m H:i', strtotime($n['created_at'])) ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding:15px; text-align:center; color:#94a3b8;"><?= $t['no_notif'] ?></div>
                <?php endif; ?>

                <div class="notif-footer">
                    <a href="notifications.php" style="color:var(--accent-color); text-decoration:none; font-weight:bold;"><?= $t['view_all'] ?></a>
                </div>
            </div>
        </div>

        <div class="user-menu has-dropdown">
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($username) ?></span>
                <span class="user-role"><?= htmlspecialchars($dept_name) ?></span>
            </div>
            <div class="user-avatar"><?= $initials ?></div>

            <div class="dropdown-menu">
                <div style="padding: 10px 15px; font-weight:bold; border-bottom:1px solid #f1f5f9; color:var(--accent-color);">
                    <?= $t['my_account'] ?>
                </div>
                <a href="profile.php" class="dropdown-item"><i class="fa-solid fa-user"></i> <?= $t['profile'] ?></a>
                <a href="contacts.php" class="dropdown-item"><i class="fa-solid fa-address-book"></i> <?= $t['contacts'] ?></a>
                <a href="messages.php" class="dropdown-item" id="navMessages"><i class="fa-solid fa-comments"></i> Messagerie <span id="msgUnreadBadge" style="display:none;" class="badge-count">0</span></a>
                <div style="border-top:1px solid #f1f5f9; margin:5px 0;"></div>
                <a href="logout.php" class="dropdown-item danger"><i class="fa-solid fa-right-from-bracket"></i> <?= $t['logout'] ?></a>
            </div>
        </div>
    </div>
</nav>
<script>
    // Appliquer le thème au chargement
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        const icon = document.getElementById('themeIcon');
        if(icon) icon.classList.replace('fa-moon', 'fa-sun');
    }

    function toggleTheme() {
        document.body.classList.toggle('dark-mode');
        const icon = document.getElementById('themeIcon');
        
        if (document.body.classList.contains('dark-mode')) {
            localStorage.setItem('theme', 'dark');
            if(icon) icon.classList.replace('fa-moon', 'fa-sun');
        } else {
            localStorage.setItem('theme', 'light');
            if(icon) icon.classList.replace('fa-sun', 'fa-moon');
        }
    }

    // Badge messages non lus (si tables msg_* existent)
    fetch('api_messages.php?action=unread_count').then(r=>r.json()).then(d=>{
        if(d.count > 0) {
            var a = document.querySelector('a[href="messages.php"]');
            if(a && !a.querySelector('.msg-badge')) {
                var s = document.createElement('span'); s.className='msg-badge'; s.style.cssText='background:#ef4444;color:white;font-size:0.7rem;padding:2px 6px;border-radius:10px;margin-left:6px;';
                s.textContent = d.count > 99 ? '99+' : d.count;
                a.appendChild(s);
            }
        }
    }).catch(()=>{});

    // Raccourci Ctrl+K : recherche rapide
    document.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            window.location.href = window.location.pathname.includes('public') ? 'search.php' : 'public/search.php';
        }
    });
</script>
<div class="content-area">
