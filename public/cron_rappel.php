<?php
// Ce script est destiné à être lancé automatiquement (Tâche Planifiée / Cron Job)
// Exécuter quotidiennement : php cron_rappel.php ou via C:\xampp\php\php.exe cron_rappel.php

require_once '../config/db.php';
require_once '../includes/notification_helper.php';

$cli = (php_sapi_name() === 'cli');
function out($msg) {
    global $cli;
    echo $cli ? $msg . "\n" : nl2br(htmlspecialchars($msg));
}

if (!$cli) {
    echo "<h1>🤖 Robot de Rappel - Délais</h1><hr>";
}

$today = date('Y-m-d');

// Tâches assignées à un utilisateur spécifique
$sql_user = "
    SELECT ma.id, ma.assigned_to, ma.deadline, m.reference_no, m.object, u.email, u.full_name
    FROM mail_assignments ma
    JOIN mails m ON ma.mail_id = m.id
    JOIN users u ON ma.assigned_to = u.id
    WHERE ma.status = 'en_cours' 
    AND ma.deadline IS NOT NULL 
    AND ma.deadline <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
";
$tasks_user = $pdo->query($sql_user)->fetchAll();

// Tâches assignées à un service (tous les users du service)
$sql_dept = "
    SELECT ma.id, ma.assigned_to_dept, ma.deadline, m.reference_no, m.object
    FROM mail_assignments ma
    JOIN mails m ON ma.mail_id = m.id
    WHERE ma.status = 'en_cours' 
    AND ma.assigned_to_dept IS NOT NULL
    AND ma.deadline IS NOT NULL 
    AND ma.deadline <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
";
$tasks_dept = $pdo->query($sql_dept)->fetchAll();

$count_notif = 0;

foreach ($tasks_user as $t) {
    $is_overdue = ($t['deadline'] < $today);
    notifyDeadlineReminder($pdo, $t['assigned_to'], $t['id'], $t['reference_no'], $t['object'], $t['deadline'], $is_overdue);
    $count_notif++;
    out("Notification créée pour " . $t['full_name'] . " - " . $t['reference_no']);
}

foreach ($tasks_dept as $t) {
    $users = $pdo->prepare("SELECT id FROM users WHERE department_id = ? AND is_active = 1");
    $users->execute([$t['assigned_to_dept']]);
    foreach ($users->fetchAll() as $u) {
        $is_overdue = ($t['deadline'] < $today);
        notifyDeadlineReminder($pdo, $u['id'], $t['id'], $t['reference_no'], $t['object'], $t['deadline'], $is_overdue);
        $count_notif++;
    }
    out("Notifications créées pour le service - " . $t['reference_no']);
}

if ($count_notif == 0 && empty($tasks_user) && empty($tasks_dept)) {
    out("✅ Aucune tâche urgente. Rien à faire.");
} else {
    out("✅ $count_notif notification(s) de rappel créée(s).");
}
?>