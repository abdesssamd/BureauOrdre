<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
if (!isset($_GET['id'])) { die("ID manquant"); }
$assignment_id = $_GET['id'];

// 1. TRAITEMENT : AJOUT COMMENTAIRE + FICHIER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $comment = trim($_POST['comment']);
    $mail_id = $_POST['mail_id'];
    
    // Gestion du fichier joint (Optionnel)
    $att_name = null;
    $att_stored = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $att_name = $_FILES['attachment']['name'];
        $att_stored = 'chat_' . bin2hex(random_bytes(8)) . '.' . $ext;
        move_uploaded_file($_FILES['attachment']['tmp_name'], '../uploads/comments/' . $att_stored);
    }

    if (!empty($comment) || $att_name) {
        $stmt = $pdo->prepare("INSERT INTO mail_comments (mail_id, user_id, comment, attachment_name, attachment_stored) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$mail_id, $_SESSION['user_id'], $comment, $att_name, $att_stored]);

        // Notification changement workflow au createur de l'assignation
        $info = $pdo->prepare("
            SELECT ma.assigned_by, m.reference_no, m.object, m.file_id
            FROM mail_assignments ma
            JOIN mails m ON m.id = ma.mail_id
            WHERE ma.id = ?
            LIMIT 1
        ");
        $info->execute([$assignment_id]);
        $ctx = $info->fetch(PDO::FETCH_ASSOC);
        if ($ctx && (int)$ctx['assigned_by'] !== (int)$_SESSION['user_id']) {
            $actor = $_SESSION['full_name'] ?? 'Un utilisateur';
            $title = "Mise a jour workflow";
            $msg = $actor . " a ajoute un commentaire sur " . $ctx['reference_no'] . " (" . $ctx['object'] . ").";
            createSystemNotification($pdo, (int)$ctx['assigned_by'], $title, $msg, $ctx['file_id'] ?? null);
        }
    }
    // On recharge pour Ã©viter le re-post du formulaire
    header("Location: task_details.php?id=$assignment_id"); 
    exit;
}

// 2. TRAITEMENT : CLÃ”TURE TÃ‚CHE
if (isset($_POST['close_task'])) {
    $note = $_POST['final_note'] ?? 'TraitÃ©';
    $pdo->prepare("UPDATE mail_assignments SET status='traite', processed_at=NOW(), response_note=? WHERE id=?")->execute([$note, $assignment_id]);
    
    // Note systÃ¨me automatique
    $pdo->prepare("INSERT INTO mail_comments (mail_id, user_id, comment) VALUES (?, ?, ?)")
        ->execute([$_POST['mail_id'], $_SESSION['user_id'], "âœ… TÃ¢che clÃ´turÃ©e : " . $note]);

    // Notification changement workflow au createur de l'assignation
    $info = $pdo->prepare("
        SELECT ma.assigned_by, m.reference_no, m.object, m.file_id
        FROM mail_assignments ma
        JOIN mails m ON m.id = ma.mail_id
        WHERE ma.id = ?
        LIMIT 1
    ");
    $info->execute([$assignment_id]);
    $ctx = $info->fetch(PDO::FETCH_ASSOC);
    if ($ctx && (int)$ctx['assigned_by'] !== (int)$_SESSION['user_id']) {
        $actor = $_SESSION['full_name'] ?? 'Un utilisateur';
        $title = "Ordre traite";
        $msg = $actor . " a marque " . $ctx['reference_no'] . " (" . $ctx['object'] . ") comme traite. Note: " . $note;
        createSystemNotification($pdo, (int)$ctx['assigned_by'], $title, $msg, $ctx['file_id'] ?? null);
    }

    header('Location: my_tasks.php'); exit;
}

// 3. RECUPERATION INFOS
$sql = "SELECT ma.*, m.id as mail_ref_id, m.file_id, m.reference_no, m.object, COALESCE(c.name, m.correspondent) as correspondent, m.mail_date, 
               f.stored_name, f.original_name, f.mime_type,
               u_sender.full_name as sender_name, u_sender.role as sender_role
        FROM mail_assignments ma
        JOIN mails m ON ma.mail_id = m.id
        LEFT JOIN contacts c ON m.contact_id = c.id
        LEFT JOIN files f ON m.file_id = f.id
        JOIN users u_sender ON ma.assigned_by = u_sender.id
        WHERE ma.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$assignment_id]);
$task = $stmt->fetch();

if (!$task) die("TÃ¢che introuvable.");
$has_document = !empty($task['file_id']) && !empty($task['stored_name']);

// 4. RECUPERATION DISCUSSION
$comments = $pdo->prepare("
    SELECT c.*, u.full_name, u.role
    FROM mail_comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.mail_id = ? 
    ORDER BY c.created_at ASC
");
$comments->execute([$task['mail_ref_id']]);
$discussion = $comments->fetchAll();

// 5. HISTORIQUE DES TRANSMISSIONS (chaÃ®ne complÃ¨te)
$history_sql = "SELECT ma.*, 
        u_by.full_name as assigned_by_name, u_to.full_name as assigned_to_name, d.name as assigned_to_dept_name
    FROM mail_assignments ma
    LEFT JOIN users u_by ON ma.assigned_by = u_by.id
    LEFT JOIN users u_to ON ma.assigned_to = u_to.id
    LEFT JOIN departments d ON ma.assigned_to_dept = d.id
    WHERE ma.mail_id = ?
    ORDER BY ma.created_at ASC";
$history_stmt = $pdo->prepare($history_sql);
$history_stmt->execute([$task['mail_ref_id']]);
$transmission_history = $history_stmt->fetchAll();

// Helper pour les initiales (Ex: Karim Benz -> KB)
function getInitials($name) {
    $parts = explode(" ", $name);
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $initials .= strtoupper(substr($parts[1], 0, 1));
    return $initials;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Suivi : <?= htmlspecialchars($task['reference_no']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <style>
        /* Styles Chat AmÃ©liorÃ©s */
        .chat-container { display: flex; flex-direction: column; height: 500px; background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .chat-messages { flex: 1; overflow-y: auto; padding: 20px; background: #f8fafc; }
        .chat-input-area { padding: 15px; border-top: 1px solid #e2e8f0; background: white; border-radius: 0 0 12px 12px; }
        
        .message { display: flex; margin-bottom: 20px; gap: 10px; }
        .message.me { flex-direction: row-reverse; }
        
        .avatar { 
            width: 35px; height: 35px; border-radius: 50%; background: #cbd5e1; color: white; 
            display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.8rem;
        }
        .message.me .avatar { background: var(--primary-color); }
        .message.other .avatar { background: #64748b; }
        
        .bubble-content { max-width: 80%; }
        .meta { font-size: 0.75rem; color: #94a3b8; margin-bottom: 2px; }
        .message.me .meta { text-align: right; }
        
        .bubble { padding: 12px 16px; border-radius: 12px; font-size: 0.95rem; line-height: 1.5; box-shadow: 0 1px 2px rgba(0,0,0,0.05); position: relative; }
        .message.me .bubble { background: #dbeafe; color: #1e3a8a; border-top-right-radius: 2px; }
        .message.other .bubble { background: white; border: 1px solid #e2e8f0; border-top-left-radius: 2px; }

        /* Style PiÃ¨ce jointe dans le chat */
        .chat-attachment {
            display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.5); 
            padding: 8px; border-radius: 6px; margin-top: 5px; border: 1px dashed #94a3b8; text-decoration: none; color: inherit;
            transition: background 0.2s;
        }
        .chat-attachment:hover { background: rgba(255,255,255,0.8); }

        /* Input File CachÃ© */
        .file-label { cursor: pointer; padding: 10px 15px; color: #64748b; transition: color 0.2s; }
        .file-label:hover { color: var(--primary-color); }
        #file-preview { font-size: 0.8rem; color: #10b981; margin-left: 10px; display: none; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1><i class="fa-solid fa-folder-open"></i> Suivi : <span style="color:var(--primary-color)"><?= $task['reference_no'] ?></span></h1>
        <a href="my_tasks.php" class="btn-sm" style="background:#64748b; color:white;"><i class="fa-solid fa-arrow-left"></i> Retour</a>
    </div>

    <div style="display:grid; grid-template-columns: 1fr 1.5fr; gap: 20px;">
        
        <div>
            <div class="upload-card" style="text-align:left; margin:0;">
                <h3 style="margin-top:0; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">Le Courrier</h3>
                <p><strong>Objet :</strong> <?= htmlspecialchars($task['object']) ?></p>
                <p><strong>ExpÃ©diteur :</strong> <?= htmlspecialchars($task['correspondent']) ?></p>
                <p><strong>Date :</strong> <?= date('d/m/Y', strtotime($task['mail_date'])) ?></p>
                
                <?php if ($has_document): ?>
                <div style="display:flex; gap:10px; margin-top:15px; flex-wrap:wrap;">
                    <button type="button" class="btn-primary" onclick="openTaskDocPreview()" style="flex:1; min-width:140px;">
                        <i class="fa-solid fa-eye"></i> AperÃ§u document
                    </button>
                    <a href="../uploads/<?= $task['stored_name'] ?>" target="_blank" class="btn-sm" style="background:#64748b; color:white; text-decoration:none; padding:10px 15px;">
                        <i class="fa-solid fa-download"></i> TÃ©lÃ©charger
                    </a>
                    <a href="preview.php?id=<?= $task['file_id'] ?? '' ?>" target="_blank" class="btn-sm" style="background:#1b74e4; color:white; text-decoration:none; padding:10px 15px;">
                        <i class="fa-solid fa-up-right-from-square"></i> Nouvel onglet
                    </a>
                </div>
                <?php else: ?>
                <div style="margin-top:15px; background:#f8fafc; border-left:4px solid #94a3b8; color:#475569; padding:10px;">
                    Aucun document joint pour cette instruction.
                </div>
                <?php endif; ?>

                <div style="margin-top:20px; background:#fff7ed; border-left:4px solid #f97316; padding:10px;">
                    <strong>Instruction :</strong><br>
                    <?= htmlspecialchars($task['instruction']) ?>
                    <div style="font-size:0.85rem; color:#9a3412; margin-top:5px;">Par: <?= $task['sender_name'] ?></div>
                </div>

                <?php if (count($transmission_history) > 1): ?>
                <div style="margin-top:20px; padding:12px; background:#f0fdf4; border-radius:8px; border-left:4px solid #10b981;">
                    <strong><i class="fa-solid fa-route"></i> Historique des transmissions</strong>
                    <div style="margin-top:10px; font-size:0.9rem;">
                        <?php foreach ($transmission_history as $i => $th): ?>
                        <div style="display:flex; gap:8px; margin-bottom:8px; align-items:flex-start;">
                            <span style="background:#10b981; color:white; width:22px; height:22px; border-radius:50%; text-align:center; line-height:22px; font-size:0.75rem; flex-shrink:0;"><?= $i+1 ?></span>
                            <div>
                                <strong><?= htmlspecialchars($th['assigned_by_name'] ?? 'SystÃ¨me') ?></strong>
                                <span style="color:#64748b;"> â†’ </span>
                                <?php if ($th['assigned_to_name']): ?>
                                    <strong><?= htmlspecialchars($th['assigned_to_name']) ?></strong>
                                <?php else: ?>
                                    <strong>Service <?= htmlspecialchars($th['assigned_to_dept_name'] ?? '') ?></strong>
                                <?php endif; ?>
                                <br>
                                <small style="color:#64748b;"><?= date('d/m/Y H:i', strtotime($th['created_at'])) ?>
                                    <?php if ($th['deadline']): ?> â€¢ Ã‰chÃ©ance: <?= date('d/m/Y', strtotime($th['deadline'])) ?><?php endif; ?>
                                    <?php if ($th['status'] == 'traite'): ?> â€¢ <span style="color:#10b981;">âœ“ TraitÃ©</span><?php endif; ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if($task['status'] == 'en_cours'): ?>
                <hr style="margin:20px 0; border:0; border-top:1px solid #e2e8f0;">
                <form method="POST">
                    <input type="hidden" name="mail_id" value="<?= $task['mail_ref_id'] ?>">
                    <label style="font-weight:bold; color:#10b981;">âœ… Terminer le traitement</label>
                    <div style="display:flex; gap:5px; margin-top:5px;">
                        <input type="text" name="final_note" class="form-control" placeholder="Note de fin (Ex: RÃ©pondu)" required>
                        <button type="submit" name="close_task" class="btn-sm" style="background:#10b981; color:white; border:none;">Valider</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-container">
            <div class="chat-messages" id="chatBox">
                <?php if(count($discussion) == 0): ?>
                    <div style="text-align:center; color:#94a3b8; margin-top:50px;">
                        <i class="fa-regular fa-comments fa-3x"></i><br>
                        DÃ©but du fil de discussion
                    </div>
                <?php endif; ?>

                <?php foreach($discussion as $msg): 
                    $is_me = ($msg['user_id'] == $_SESSION['user_id']);
                ?>
                    <div class="message <?= $is_me ? 'me' : 'other' ?>">
                        <div class="avatar" title="<?= htmlspecialchars($msg['full_name']) ?>">
                            <?= getInitials($msg['full_name']) ?>
                        </div>
                        <div class="bubble-content">
                            <div class="meta">
                                <?= htmlspecialchars($msg['full_name']) ?> â€¢ <?= date('d/m H:i', strtotime($msg['created_at'])) ?>
                            </div>
                            <div class="bubble">
                                <?= nl2br(htmlspecialchars($msg['comment'])) ?>
                                
                                <?php if($msg['attachment_stored']): ?>
                                    <a href="../uploads/comments/<?= $msg['attachment_stored'] ?>" target="_blank" class="chat-attachment">
                                        <i class="fa-solid fa-paperclip"></i>
                                        <span><?= htmlspecialchars($msg['attachment_name']) ?></span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="POST" enctype="multipart/form-data" class="chat-input-area">
                <input type="hidden" name="mail_id" value="<?= $task['mail_ref_id'] ?>">
                
                <div style="display:flex; align-items:center; gap:10px;">
                    <label class="file-label" title="Joindre un fichier">
                        <i class="fa-solid fa-paperclip fa-lg"></i>
                        <input type="file" name="attachment" style="display:none;" onchange="showFileName(this)">
                    </label>

                    <input type="text" name="comment" class="form-control" placeholder="Ã‰crivez une note ou un message..." style="margin:0;" autocomplete="off">
                    
                    <button type="submit" name="add_comment" class="btn-sm" style="background:var(--primary-color); color:white; border:none; height:40px; width:40px; border-radius:50%;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
                <div id="file-preview"></div>
            </form>
        </div>
    </div>

    <!-- Modal AperÃ§u Document -->
    <?php if ($has_document): ?>
    <div id="taskDocModal" class="doc-preview-modal" style="display:none;">
        <div class="doc-preview-content">
            <div class="doc-preview-header">
                <span><i class="fa-solid fa-file-lines"></i> <?= htmlspecialchars($task['reference_no']) ?> - Document</span>
                <div>
                    <button onclick="printTaskDoc()" class="btn-sm" style="background:rgba(255,255,255,0.2); color:white; border:none; margin-right:10px;"><i class="fa-solid fa-print"></i></button>
                    <a href="../uploads/<?= $task['stored_name'] ?>" download class="btn-sm" style="background:rgba(255,255,255,0.2); color:white; text-decoration:none;"><i class="fa-solid fa-download"></i></a>
                    <button onclick="closeTaskDocModal()" class="doc-preview-close" style="margin-left:10px;">&times;</button>
                </div>
            </div>
            <div class="doc-preview-body" id="taskDocBody" style="position:relative;">
                <?php 
                $doc_ext = strtolower(pathinfo($task['original_name'] ?? '', PATHINFO_EXTENSION));
                $doc_url = '../uploads/' . $task['stored_name'];
                ?>
                <?php if ($doc_ext === 'pdf'): ?>
                    <iframe src="<?= htmlspecialchars($doc_url) ?>?t=<?= time() ?>" style="width:100%;height:100%;min-height:500px;border:none;"></iframe>
                <?php elseif (in_array($doc_ext, ['jpg','jpeg','png','gif','bmp'])): ?>
                    <img id="taskDocImg" src="<?= htmlspecialchars($doc_url) ?>?t=<?= time() ?>" style="max-width:100%;max-height:100%;object-fit:contain;" alt="Document">
                    <div class="doc-preview-img-controls" style="position:absolute;bottom:20px;left:50%;transform:translateX(-50%);">
                        <button onclick="taskDocZoom()"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                        <button onclick="taskDocRotate()"><i class="fa-solid fa-rotate-right"></i></button>
                    </div>
                <?php else: ?>
                    <div style="color:white;padding:40px;text-align:center;">
                        <p>AperÃ§u non disponible. <a href="../uploads/<?= $task['stored_name'] ?>" download class="btn-primary">TÃ©lÃ©charger</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <style>
        .doc-preview-modal { position:fixed; inset:0; background:rgba(0,0,0,0.92); z-index:2000; display:flex; align-items:center; justify-content:center; }
        .doc-preview-content { background:#fff; border-radius:12px; width:95%; max-width:1200px; height:90vh; display:flex; flex-direction:column; box-shadow:0 25px 50px rgba(0,0,0,0.3); overflow:hidden; }
        .doc-preview-header { padding:15px 20px; background:linear-gradient(135deg,#0f172a,#1e293b); color:white; display:flex; justify-content:space-between; align-items:center; }
        .doc-preview-close { background:none; border:none; color:white; font-size:28px; cursor:pointer; padding:0 10px; line-height:1; }
        .doc-preview-body { flex:1; background:#1e293b; overflow:auto; display:flex; align-items:center; justify-content:center; padding:20px; }
        .doc-preview-img-controls { background:rgba(0,0,0,0.7); padding:10px 20px; border-radius:30px; gap:10px; display:flex; }
        .doc-preview-img-controls button { background:rgba(255,255,255,0.2); border:none; color:white; width:40px; height:40px; border-radius:50%; cursor:pointer; }
    </style>

    <script>
        function openTaskDocPreview() {
            var modal = document.getElementById('taskDocModal');
            if (modal) modal.style.display = 'flex';
        }
        function closeTaskDocModal() {
            var modal = document.getElementById('taskDocModal');
            if (modal) modal.style.display = 'none';
        }
        var taskDocModal = document.getElementById('taskDocModal');
        if (taskDocModal) {
            taskDocModal.onclick = function(e) { if (e.target === this) closeTaskDocModal(); };
        }
        function printTaskDoc() {
            var ifr = document.querySelector('#taskDocBody iframe');
            if (ifr) ifr.contentWindow.print();
            else {
                var img = document.getElementById('taskDocImg');
                if (img) { var w = window.open(''); w.document.write('<img src="'+img.src+'">'); w.print(); }
            }
        }
        var taskDocRot = 0;
        function taskDocZoom() {
            var img = document.getElementById('taskDocImg');
            if (img) img.style.transform = (img.style.transform === 'scale(1.5)' ? 'scale(1)' : 'scale(1.5)');
        }
        function taskDocRotate() {
            taskDocRot += 90;
            var img = document.getElementById('taskDocImg');
            if (img) img.style.transform = 'rotate(' + taskDocRot + 'deg)';
        }

        // 1. Scroll automatique vers le bas
        var chatBox = document.getElementById("chatBox");
        chatBox.scrollTop = chatBox.scrollHeight;

        // 2. Afficher nom du fichier sÃ©lectionnÃ©
        function showFileName(input) {
            var preview = document.getElementById('file-preview');
            if (input.files && input.files[0]) {
                preview.style.display = 'block';
                preview.innerHTML = '<i class="fa-solid fa-file"></i> ' + input.files[0].name;
            } else {
                preview.style.display = 'none';
            }
        }
    </script>

    <?php include '../includes/footer.php'; ?>
