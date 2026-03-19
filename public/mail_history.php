<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
$mail_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$mail_id) { header('Location: registre.php'); exit; }

$mail = $pdo->prepare("SELECT m.*, c.name as contact_name, f.stored_name, f.original_name 
    FROM mails m 
    LEFT JOIN contacts c ON m.contact_id = c.id 
    LEFT JOIN files f ON m.file_id = f.id 
    WHERE m.id = ?");
$mail->execute([$mail_id]);
$mail = $mail->fetch();
if (!$mail) { header('Location: registre.php'); exit; }

$history = $pdo->prepare("SELECT ma.*, 
    u_by.full_name as assigned_by_name, u_to.full_name as assigned_to_name, d.name as assigned_to_dept_name
    FROM mail_assignments ma
    LEFT JOIN users u_by ON ma.assigned_by = u_by.id
    LEFT JOIN users u_to ON ma.assigned_to = u_to.id
    LEFT JOIN departments d ON ma.assigned_to_dept = d.id
    WHERE ma.mail_id = ?
    ORDER BY ma.created_at ASC");
$history->execute([$mail_id]);
$transmissions = $history->fetchAll();

include '../includes/header.php';
?>

<div class="content-area">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1><i class="fa-solid fa-route"></i> Historique : <?= htmlspecialchars($mail['reference_no']) ?></h1>
        <a href="registre.php" class="btn-sm" style="background:#64748b; color:white;"><i class="fa-solid fa-arrow-left"></i> Retour</a>
    </div>

    <div class="upload-card" style="margin-bottom:25px;">
        <h3 style="margin-top:0;">Courrier</h3>
        <p><strong>Objet :</strong> <?= htmlspecialchars($mail['object']) ?></p>
        <p><strong>Correspondant :</strong> <?= htmlspecialchars($mail['contact_name'] ?? '-') ?></p>
        <p><strong>Date :</strong> <?= date('d/m/Y', strtotime($mail['mail_date'])) ?></p>
    </div>

    <div class="upload-card">
        <h3 style="margin-top:0;"><i class="fa-solid fa-share-nodes"></i> Chaîne des transmissions</h3>
        <?php if (empty($transmissions)): ?>
            <p style="color:#64748b;">Aucune transmission pour ce courrier.</p>
        <?php else: ?>
            <div style="position:relative; padding-left:30px; border-left:2px solid #e2e8f0; margin-left:10px;">
                <?php foreach ($transmissions as $i => $t): ?>
                <div style="position:relative; margin-bottom:25px;">
                    <span style="position:absolute; left:-38px; background:<?= $t['status']=='traite' ? '#10b981' : '#3b82f6' ?>; color:white; width:24px; height:24px; border-radius:50%; text-align:center; line-height:24px; font-size:0.8rem;"><?= $i+1 ?></span>
                    <div style="background:#f8fafc; padding:15px; border-radius:8px;">
                        <strong><?= htmlspecialchars($t['assigned_by_name'] ?? '-') ?></strong>
                        <span style="color:#64748b;"> a transmis à </span>
                        <?php if ($t['assigned_to_name']): ?>
                            <strong><?= htmlspecialchars($t['assigned_to_name']) ?></strong>
                        <?php else: ?>
                            <strong>Service <?= htmlspecialchars($t['assigned_to_dept_name'] ?? '') ?></strong>
                        <?php endif; ?>
                        <div style="margin-top:8px; font-size:0.9rem;"><?= nl2br(htmlspecialchars($t['instruction'] ?? '')) ?></div>
                        <div style="margin-top:8px; font-size:0.8rem; color:#64748b;">
                            <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?>
                            <?php if ($t['deadline']): ?> • Échéance : <?= date('d/m/Y', strtotime($t['deadline'])) ?><?php endif; ?>
                            <?php if ($t['status'] == 'traite'): ?>
                                <span style="color:#10b981; margin-left:10px;">✓ Traité<?= $t['processed_at'] ? ' le ' . date('d/m/Y', strtotime($t['processed_at'])) : '' ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
