<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Marquer une notification comme lue
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notif_id = $_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    header('Location: notifications.php');
    exit;
}

// Marquer toutes comme lues
if (isset($_GET['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
    header('Location: notifications.php');
    exit;
}

// Supprimer une notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([$_GET['delete'], $user_id]);
    header('Location: notifications.php');
    exit;
}

// Récupérer les notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$unread_count = 0;
foreach($notifications as $n) {
    if (!$n['is_read']) $unread_count++;
}
include '../includes/header.php'; 
?>
        <div class="content-area">
            <div class="notif-header">
                <div>
                    <h1 style="margin: 0;"><i class="fa-solid fa-bell"></i> Notifications</h1>
                    <?php if($unread_count > 0): ?>
                        <span class="badge-count" style="margin-left: 10px;">
                            <?= $unread_count ?> non lue<?= $unread_count > 1 ? 's' : '' ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if(count($notifications) > 0): ?>
                    <a href="notifications.php?mark_all_read" class="btn-sm" style="background: var(--primary-color); color: white;">
                        <i class="fa-solid fa-check-double"></i> Tout marquer comme lu
                    </a>
                <?php endif; ?>
            </div>

            <?php if(count($notifications) > 0): ?>
                <?php foreach($notifications as $notif): ?>
                    <div class="notif-item <?= !$notif['is_read'] ? 'unread' : '' ?>">
                        <div class="notif-icon <?= $notif['type'] ?>">
                            <?php
                                $icon = 'fa-bell';
                                if($notif['type'] == 'share') $icon = 'fa-share-nodes';
                                elseif($notif['type'] == 'upload') $icon = 'fa-cloud-arrow-up';
                                elseif($notif['type'] == 'system') $icon = 'fa-gear';
                            ?>
                            <i class="fa-solid <?= $icon ?>"></i>
                        </div>
                        
                        <div class="notif-content">
                            <div class="notif-title"><?= htmlspecialchars($notif['title']) ?></div>
                            <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                            <div class="notif-time">
                                <i class="fa-regular fa-clock"></i> 
                                <?= timeAgo($notif['created_at']) ?>
                            </div>
                        </div>

                        <div class="notif-actions">
                            <?php if(!$notif['is_read']): ?>
                                <a href="notifications.php?mark_read=<?= $notif['id'] ?>" 
                                   class="btn-sm" 
                                   style="background: #dbeafe; color: #1e40af;"
                                   title="Marquer comme lu">
                                    <i class="fa-solid fa-check"></i>
                                </a>
                            <?php endif; ?>
                            <a href="notifications.php?delete=<?= $notif['id'] ?>" 
                               class="btn-sm" 
                               style="background: #fee2e2; color: #991b1b;"
                               onclick="return confirm('Supprimer cette notification ?')"
                               title="Supprimer">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 12px;">
                    <i class="fa-regular fa-bell-slash fa-3x" style="color: #cbd5e1; margin-bottom: 20px;"></i>
                    <h3>Aucune notification</h3>
                    <p style="color: #64748b;">Vous êtes à jour !</p>
                </div>
            <?php endif; ?>
        </div>
<?php 
// 2. On inclut le FOOTER (il ferme les balises)
include '../includes/footer.php'; 
?>
<?php
// Fonction helper pour afficher le temps écoulé
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return "À l'instant";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return "Il y a " . $mins . " minute" . ($mins > 1 ? 's' : '');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "Il y a " . $hours . " heure" . ($hours > 1 ? 's' : '');
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "Il y a " . $days . " jour" . ($days > 1 ? 's' : '');
    } else {
        return date('d/m/Y à H:i', $timestamp);
    }
}
?>