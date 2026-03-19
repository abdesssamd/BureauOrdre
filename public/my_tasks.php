<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$my_dept = $_SESSION['department_id'];
$me = $_SESSION['user_id'];

// 1. GESTION DES ONGLETS
$tab = $_GET['tab'] ?? 'todo'; // 'todo', 'sent', 'done'

// 2. CONSTRUCTION DE LA REQUÃŠTE SELON L'ONGLET
$sql = "
    SELECT ma.id as assignment_id, ma.instruction, ma.deadline, ma.created_at, ma.status, ma.processed_at,
           m.reference_no, m.object, m.file_id,
           f.stored_name, f.original_name,
           u_sender.full_name as sender_name,
           u_dest.full_name as dest_user_name,
           d_dest.name as dest_dept_name
    FROM mail_assignments ma
    JOIN mails m ON ma.mail_id = m.id
    LEFT JOIN files f ON m.file_id = f.id
    LEFT JOIN users u_sender ON ma.assigned_by = u_sender.id
    LEFT JOIN users u_dest ON ma.assigned_to = u_dest.id
    LEFT JOIN departments d_dest ON ma.assigned_to_dept = d_dest.id
    WHERE 1=1 
";

$params = [];

if ($tab === 'todo') {
    // TÃ¢ches qui me sont assignÃ©es (ou Ã  mon service) ET qui sont "en cours"
    $sql .= " AND (ma.assigned_to = ? OR ma.assigned_to_dept = ?) AND ma.status = 'en_cours'";
    $params = [$me, $my_dept];

} elseif ($tab === 'sent') {
    // TÃ¢ches que J'AI crÃ©Ã©es (peu importe le statut)
    $sql .= " AND ma.assigned_by = ?";
    $params = [$me];

} elseif ($tab === 'done') {
    // TÃ¢ches que j'ai traitÃ©es (ou mon service) ET qui sont "traitÃ©es"
    $sql .= " AND (ma.assigned_to = ? OR ma.assigned_to_dept = ?) AND ma.status = 'traite'";
    $params = [$me, $my_dept];
}

$sql .= " ORDER BY ma.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Documents partagÃ©s avec moi (onglet todo) â€” pour que les transmissions apparaissent dans Mes TÃ¢ches
$file_tasks = [];
if ($tab === 'todo') {
    $fs = $pdo->prepare("
        SELECT fs.id as share_id, fs.file_id, fs.created_at, f.original_name, f.stored_name,
               u.full_name as sender_name
        FROM file_shares fs
        JOIN files f ON fs.file_id = f.id
        LEFT JOIN users u ON f.owner_id = u.id
        WHERE fs.shared_with_user_id = ? AND fs.shared_with_user_id IS NOT NULL
        ORDER BY fs.created_at DESC
    ");
    $fs->execute([$me]);
    $file_tasks = $fs->fetchAll(PDO::FETCH_ASSOC);
}

// Compter les tÃ¢ches en retard (onglet todo uniquement)
$overdue_count = 0;
if ($tab === 'todo') {
    $today = date('Y-m-d');
    foreach ($tasks as $task) {
        if (!empty($task['deadline']) && $task['deadline'] < $today) $overdue_count++;
    }
}

include '../includes/header.php';
?>

<div class="content-area">
    <?php if ($overdue_count > 0): ?>
    <div class="alert" style="background:#fef2f2; border-left:4px solid #ef4444; color:#991b1b; margin-bottom:20px;">
        <i class="fa-solid fa-exclamation-triangle"></i> <strong><?= $overdue_count ?> tÃ¢che(s) en retard</strong> â€” Traitez-les en prioritÃ©.
    </div>
    <?php endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1><i class="fa-solid fa-list-check"></i> <?= $t['my_tasks'] ?></h1>
        
        <?php if($tab == 'todo'): ?>
            <span class="badge" style="background:var(--accent-color); color:white; font-size:1rem; padding:8px 12px;">
                <?= count($tasks) + count($file_tasks) ?> <?= $t['waiting'] ?>
            </span>
        <?php endif; ?>
    </div>

    <div class="tabs">
        <a href="?tab=todo" class="tab <?= $tab=='todo'?'active':'' ?>">
            <i class="fa-solid fa-inbox"></i> <?= $t['tab_todo'] ?>
        </a>
        <a href="?tab=sent" class="tab <?= $tab=='sent'?'active':'' ?>">
            <i class="fa-solid fa-paper-plane"></i> <?= $t['tab_sent'] ?>
        </a>
        <a href="?tab=done" class="tab <?= $tab=='done'?'active':'' ?>">
            <i class="fa-solid fa-check-circle"></i> <?= $t['tab_done'] ?>
        </a>
    </div>

    <div class="recent-files" style="border-top-left-radius:0;">
        <?php if(count($tasks) == 0 && count($file_tasks) == 0): ?>
            <div style="text-align:center; padding:40px; color:#94a3b8;">
                <i class="fa-solid fa-box-open fa-3x" style="margin-bottom:15px;"></i><br>
                <?= $t['nothing_to_report'] ?>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th><?= $t['ref'] ?></th>
                        <th width="30%"><?= $t['object'] ?></th>
                        <th><?= $t['instruction'] ?></th>
                        
                        <?php if($tab == 'sent'): ?>
                            <th><?= $t['sent_to'] ?></th>
                            <th><?= $t['status'] ?></th>
                        <?php else: ?>
                            <th><?= $t['uploaded_by'] ?></th> <?php endif; ?>
                        <?php if($tab == 'todo'): ?><th><?= $t['deadline'] ?></th><?php endif; ?>
                        <th><?= $t['date'] ?></th>
                        <th><?= $t['action'] ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tasks as $t_row): ?>
                    <tr>
                        <td>
                            <span class="badge" style="background:#e0f2fe; color:#0284c7;">
                                <?= htmlspecialchars($t_row['reference_no']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($t_row['object']) ?></td>
                        
                        <td style="color:#b45309; font-weight:bold;">
                            <?= htmlspecialchars($t_row['instruction']) ?>
                        </td>

                        <?php if($tab == 'sent'): ?>
                            <td>
                                <?php if($t_row['dest_user_name']): ?>
                                    <i class="fa-solid fa-user"></i> <?= htmlspecialchars($t_row['dest_user_name']) ?>
                                <?php else: ?>
                                    <i class="fa-solid fa-building"></i> <?= htmlspecialchars($t_row['dest_dept_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($t_row['status'] == 'en_cours'): ?>
                                    <span class="badge" style="background:#fef3c7; color:#b45309;"><?= $t['status_pending'] ?></span>
                                <?php else: ?>
                                    <span class="badge" style="background:#dcfce7; color:#166534;"><?= $t['status_done'] ?></span>
                                <?php endif; ?>
                            </td>
                        <?php else: ?>
                            <td><?= htmlspecialchars($t_row['sender_name']) ?></td>
                        <?php endif; ?>
                        <?php if($tab == 'todo'): ?>
                        <td>
                            <?php if ($t_row['deadline']): 
                                $overdue = ($t_row['deadline'] < date('Y-m-d'));
                            ?>
                                <span class="badge" style="background:<?= $overdue ? '#ef4444' : '#10b981' ?>; color:white;">
                                    <?= date('d/m/Y', strtotime($t_row['deadline'])) ?>
                                    <?= $overdue ? ' âš ' : '' ?>
                                </span>
                            <?php else: ?>
                                <span style="color:#94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <?= date('d/m H:i', strtotime($t_row['created_at'])) ?>
                            <?php if($tab == 'done' && $t_row['processed_at']): ?>
                                <br><small style="color:#10b981;">Fini : <?= date('d/m', strtotime($t_row['processed_at'])) ?></small>
                            <?php endif; ?>
                        </td>
                        
                        <td>
                            <?php if ($t_row['file_id'] && $t_row['stored_name']): 
                                $ext = strtolower(pathinfo($t_row['original_name'] ?? '', PATHINFO_EXTENSION));
                                $preview_url = '../uploads/' . $t_row['stored_name'];
                            ?>
                            <button type="button" class="btn-sm" style="background:#1b74e4; color:white; border:none; cursor:pointer;" 
                                onclick="openDocPreview('<?= addslashes($preview_url) ?>', '<?= $ext ?>', '<?= addslashes($t_row['reference_no']) ?>')" title="AperÃ§u rapide">
                                <i class="fa-solid fa-file-lines"></i>
                            </button>
                            <?php endif; ?>
                            <a href="task_details.php?id=<?= $t_row['assignment_id'] ?>" class="btn-sm" style="background:var(--accent-color); color:white; text-decoration:none;">
                                <i class="fa-solid fa-eye"></i> <?= $t['view'] ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach($file_tasks as $ft): 
                        $ext = strtolower(pathinfo($ft['original_name'] ?? '', PATHINFO_EXTENSION));
                        $preview_url = '../uploads/' . $ft['stored_name'];
                    ?>
                    <tr style="background:#f8fafc;">
                        <td><span class="badge" style="background:#1b74e4; color:white;"><i class="fa-solid fa-file"></i> DOC</span></td>
                        <td><?= htmlspecialchars($ft['original_name']) ?></td>
                        <td style="color:#6d28d9; font-weight:bold;"><?= $t['for_consultation'] ?? 'Pour consultation' ?></td>
                        <td><?= htmlspecialchars($ft['sender_name'] ?? '-') ?></td>
                        <?php if($tab == 'todo'): ?><td><span style="color:#94a3b8;">-</span></td><?php endif; ?>
                        <td><?= date('d/m H:i', strtotime($ft['created_at'])) ?></td>
                        <td>
                            <button type="button" class="btn-sm" style="background:#1b74e4; color:white; border:none; cursor:pointer;" 
                                onclick="openDocPreview('<?= addslashes($preview_url) ?>', '<?= $ext ?>', <?= json_encode($ft['original_name']) ?>)" title="AperÃ§u">
                                <i class="fa-solid fa-file-lines"></i>
                            </button>
                            <a href="preview.php?id=<?= $ft['file_id'] ?>" target="_blank" class="btn-sm" style="background:var(--accent-color); color:white; text-decoration:none;">
                                <i class="fa-solid fa-eye"></i> <?= $t['view'] ?>
                            </a>
                            <a href="files.php" class="btn-sm" style="background:#64748b; color:white; text-decoration:none;" title="Documents"><i class="fa-solid fa-folder-open"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div id="docPreviewModal" class="doc-preview-modal" style="display:none;">
    <div class="doc-preview-content">
        <div class="doc-preview-header">
            <span id="docPreviewTitle"></span>
            <button onclick="closeDocPreview()" class="doc-preview-close">&times;</button>
        </div>
        <div class="doc-preview-body" id="docPreviewBody"></div>
        <div id="docPreviewImgControls" class="doc-preview-img-controls" style="display:none;">
            <button onclick="docZoom()"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
            <button onclick="docRotate()"><i class="fa-solid fa-rotate-right"></i></button>
        </div>
    </div>
</div>

<style>
    .doc-preview-modal { position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:2000; display:flex; align-items:center; justify-content:center; }
    .doc-preview-content { background:#fff; border-radius:12px; width:95%; max-width:1200px; height:90vh; display:flex; flex-direction:column; box-shadow:0 25px 50px rgba(0,0,0,0.3); }
    .doc-preview-header { padding:15px 20px; background:#0f172a; color:white; border-radius:12px 12px 0 0; display:flex; justify-content:space-between; align-items:center; }
    .doc-preview-close { background:none; border:none; color:white; font-size:28px; cursor:pointer; padding:0 10px; }
    .doc-preview-body { flex:1; background:#1e293b; overflow:auto; display:flex; align-items:center; justify-content:center; padding:20px; }
    .doc-preview-body iframe { width:100%; height:100%; border:none; min-height:500px; }
    .doc-preview-body img { max-width:100%; max-height:100%; object-fit:contain; }
    .doc-preview-img-controls { position:absolute; bottom:20px; left:50%; transform:translateX(-50%); background:rgba(0,0,0,0.7); padding:10px 20px; border-radius:30px; gap:10px; display:flex; }
    .doc-preview-img-controls button { background:rgba(255,255,255,0.2); border:none; color:white; width:40px; height:40px; border-radius:50%; cursor:pointer; }
    /* Style minimal pour les onglets (dÃ©jÃ  prÃ©sent dans registre mais utile ici aussi) */
    .tabs { display: flex; gap: 5px; border-bottom: 2px solid #e2e8f0; margin-bottom: 0; }
    .tab { padding: 12px 20px; text-decoration: none; color: #64748b; font-weight: bold; background: #f1f5f9; border-radius: 8px 8px 0 0; transition:0.2s; }
    .tab:hover { background: #e2e8f0; }
    .tab.active { background: white; color: var(--primary-color); border: 2px solid #e2e8f0; border-bottom: 2px solid white; margin-bottom: -2px; }
</style>

<script>
var docPreviewRotation = 0;
function openDocPreview(url, ext, ref) {
    document.getElementById('docPreviewTitle').textContent = ref;
    var body = document.getElementById('docPreviewBody');
    var ctrls = document.getElementById('docPreviewImgControls');
    body.innerHTML = ''; ctrls.style.display = 'none'; docPreviewRotation = 0;
    var fullUrl = url + (url.indexOf('?') > -1 ? '&' : '?') + 't=' + Date.now();
    if (ext === 'pdf') {
        body.innerHTML = '<iframe src="' + fullUrl + '"></iframe>';
    } else if (['jpg','jpeg','png','gif','bmp'].includes(ext)) {
        var img = document.createElement('img');
        img.id = 'docPreviewImg';
        img.src = fullUrl;
        body.appendChild(img);
        ctrls.style.display = 'flex';
    } else {
        body.innerHTML = '<div style="color:white;padding:40px;text-align:center;"><p>Format non affichable.</p><a href="' + url + '" target="_blank" class="btn-primary">TÃ©lÃ©charger</a></div>';
    }
    document.getElementById('docPreviewModal').style.display = 'flex';
}
function closeDocPreview() { document.getElementById('docPreviewModal').style.display = 'none'; }
function docZoom() {
    var img = document.getElementById('docPreviewImg');
    if (img) img.style.transform = (img.style.transform === 'scale(1.5)' ? 'scale(1)' : 'scale(1.5)');
}
function docRotate() {
    docPreviewRotation += 90;
    var img = document.getElementById('docPreviewImg');
    if (img) img.style.transform = 'rotate(' + docPreviewRotation + 'deg)';
}
document.getElementById('docPreviewModal').onclick = function(e) { if (e.target === this) closeDocPreview(); };
</script>

<?php include '../includes/footer.php'; ?>
