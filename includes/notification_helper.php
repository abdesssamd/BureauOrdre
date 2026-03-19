<?php
/**
 * Fonctions helper pour les notifications
 * CORRIGÉ : Remplacement de 'real_name' par 'original_name' pour correspondre à la BDD
 */

/**
 * Créer une notification de partage
 * @param int $sharer_id ID de la personne qui partage (ou null = utiliser le propriétaire du fichier)
 */
function notifyFileShared($pdo, $file_id, $shared_with_user_id, $sharer_id = null) {

    $stmt = $pdo->prepare("SELECT files.original_name FROM files WHERE files.id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch();
    if (!$file) return;

    $sharer_name = 'Un utilisateur';
    if ($sharer_id) {
        $u = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $u->execute([$sharer_id]);
        $sharer_name = $u->fetchColumn() ?: $sharer_name;
    } else {
        $u = $pdo->prepare("SELECT u.full_name FROM files f JOIN users u ON f.owner_id = u.id WHERE f.id = ?");
        $u->execute([$file_id]);
        $sharer_name = $u->fetchColumn() ?: $sharer_name;
    }

    $title = "Nouveau fichier transmis";
    $message = "$sharer_name a transmis le fichier \"{$file['original_name']}\" avec vous.";

    $insert = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_file_id) VALUES (?, 'share', ?, ?, ?)");
    $insert->execute([$shared_with_user_id, $title, $message, $file_id]);
}


/**
 * Créer une notification pour un upload dans le département
 */
function notifyDepartmentUpload($pdo, $file_id, $uploader_id, $department_id) {
    // 1. Récupérer le nom du fichier (original_name)
    $stmt = $pdo->prepare("
        SELECT files.original_name, users.full_name AS uploader_name
        FROM files
        JOIN users ON files.owner_id = users.id
        WHERE files.id = ?
    ");
    $stmt->execute([$file_id]);
    $data = $stmt->fetch();

    if ($data) {
        $title = "Nouveau document";
        $message = "{$data['uploader_name']} a ajouté : \"{$data['original_name']}\"";

        // 2. Sélectionner les collègues (Sauf moi)
        // ATTENTION : On vérifie department_id ET que ce n'est pas moi (id != uploader_id)
        $users_sql = "SELECT id FROM users WHERE department_id = ? AND id != ? AND is_active = 1";
        $users_stmt = $pdo->prepare($users_sql);
        $users_stmt->execute([$department_id, $uploader_id]);
        $colleagues = $users_stmt->fetchAll();

        // Si aucun collègue trouvé, on arrête
        if (!$colleagues) return;

        // 3. Insérer les notifications
        $insert = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_file_id)
            VALUES (?, 'upload', ?, ?, ?)
        ");

        foreach ($colleagues as $user) {
            $insert->execute([$user['id'], $title, $message, $file_id]);
        }
    }
}


/**
 * Créer une notification système
 */
function createSystemNotification($pdo, $user_id, $title, $message, $file_id = null) {

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, related_file_id)
        VALUES (?, 'system', ?, ?, ?)
    ");
    $stmt->execute([$user_id, $title, $message, $file_id]);
}


/**
 * Créer une notification de rappel de délai pour une tâche
 */
function notifyDeadlineReminder($pdo, $user_id, $assignment_id, $reference_no, $object, $deadline, $is_overdue = false) {
    $status = $is_overdue ? 'EN RETARD' : 'Échéance proche';
    $title = $is_overdue ? "⚠️ Tâche en retard" : "⏰ Rappel d'échéance";
    $message = "Courrier $reference_no ($object) - Date limite : " . date('d/m/Y', strtotime($deadline)) . ". Allez dans Mes Tâches pour traiter.";
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, message, related_file_id)
        VALUES (?, 'reminder', ?, ?, NULL)
    ");
    $stmt->execute([$user_id, $title, $message]);
}


/**
 * Nombre de notifications non lues
 */
function getUnreadNotificationsCount($pdo, $user_id) {

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();

    return (int)$result['count'];
}


/**
 * Dernières notifications (compatible PHP 7)
 */
function getRecentNotifications($pdo, $user_id, $limit = 5) {

    $stmt = $pdo->prepare("
        SELECT * FROM notifications
        WHERE user_id = :uid
        ORDER BY created_at DESC
        LIMIT :limit
    ");
    
    $stmt->bindValue(':uid', (int)$user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>