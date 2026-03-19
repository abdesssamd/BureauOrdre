<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

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
    }
    // On recharge pour éviter le re-post du formulaire
    header("Location: task_details.php?id=$assignment_id"); 
    exit;
}

// 2. TRAITEMENT : CLÔTURE TÂCHE
if (isset($_POST['close_task'])) {
    $note = $_POST['final_note'] ?? 'Traité';
    $pdo->prepare("UPDATE mail_assignments SET status='traite', processed_at=NOW(), response_note=? WHERE id=?")->execute([$note, $assignment_id]);
    
    // Note système automatique
    $pdo->prepare("INSERT INTO mail_comments (mail_id, user_id, comment) VALUES (?, ?, ?)")
        ->execute([$_POST['mail_id'], $_SESSION['user_id'], "✅ Tâche clôturée : " . $note]);

    header('Location: my_tasks.php'); exit;
}

// 3. RECUPERATION INFOS
$sql = "SELECT ma.*, m.id as mail_ref_id, m.reference_no, m.object, m.correspondent, m.mail_date, 
               f.stored_name, f.original_name, f.mime_type,
               u_sender.full_name as sender_name, u_sender.role as sender_role
        FROM mail_assignments ma
        JOIN mails m ON ma.mail_id = m.id
        JOIN files f ON m.file_id = f.id
        JOIN users u_sender ON ma.assigned_by = u_sender.id
        WHERE ma.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$assignment_id]);
$task = $stmt->fetch();

if (!$task) die("Tâche introuvable.");

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
        /* Styles Chat Améliorés */
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

        /* Style Pièce jointe dans le chat */
        .chat-attachment {
            display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.5); 
            padding: 8px; border-radius: 6px; margin-top: 5px; border: 1px dashed #94a3b8; text-decoration: none; color: inherit;
            transition: background 0.2s;
        }
        .chat-attachment:hover { background: rgba(255,255,255,0.8); }

        /* Input File Caché */
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
                <p><strong>Expéditeur :</strong> <?= htmlspecialchars($task['correspondent']) ?></p>
                <p><strong>Date :</strong> <?= date('d/m/Y', strtotime($task['mail_date'])) ?></p>
                
                <a href="../uploads/<?= $task['stored_name'] ?>" target="_blank" class="btn-primary" style="display:block; text-align:center; margin-top:15px;">
                    <i class="fa-solid fa-file-pdf"></i> Ouvrir le document
                </a>

                <div style="margin-top:20px; background:#fff7ed; border-left:4px solid #f97316; padding:10px;">
                    <strong>Instruction :</strong><br>
                    <?= htmlspecialchars($task['instruction']) ?>
                    <div style="font-size:0.85rem; color:#9a3412; margin-top:5px;">Par: <?= $task['sender_name'] ?></div>
                </div>

                <?php if($task['status'] == 'en_cours'): ?>
                <hr style="margin:20px 0; border:0; border-top:1px solid #e2e8f0;">
                <form method="POST">
                    <input type="hidden" name="mail_id" value="<?= $task['mail_ref_id'] ?>">
                    <label style="font-weight:bold; color:#10b981;">✅ Terminer le traitement</label>
                    <div style="display:flex; gap:5px; margin-top:5px;">
                        <input type="text" name="final_note" class="form-control" placeholder="Note de fin (Ex: Répondu)" required>
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
                        Début du fil de discussion
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
                                <?= htmlspecialchars($msg['full_name']) ?> • <?= date('d/m H:i', strtotime($msg['created_at'])) ?>
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

                    <input type="text" name="comment" class="form-control" placeholder="Écrivez une note ou un message..." style="margin:0;" autocomplete="off">
                    
                    <button type="submit" name="add_comment" class="btn-sm" style="background:var(--primary-color); color:white; border:none; height:40px; width:40px; border-radius:50%;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
                <div id="file-preview"></div>
            </form>
        </div>
    </div>

    <script>
        // 1. Scroll automatique vers le bas
        var chatBox = document.getElementById("chatBox");
        chatBox.scrollTop = chatBox.scrollHeight;

        // 2. Afficher nom du fichier sélectionné
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