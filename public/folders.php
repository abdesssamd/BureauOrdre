<?php
require '../config/db.php';
require '../includes/auth.php';
require '../includes/share_acl.php';
require_login();

if (in_array($_SESSION['role'] ?? '', ['secretaire', 'secretaria'], true)) {
    header('Location: dashboard.php');
    exit;
}

share_acl_ensure_schema($pdo);
$user_id = (int)$_SESSION['user_id'];
$dept_id = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
        $stmt = $pdo->prepare("INSERT INTO folders (name, owner_id, department_id) VALUES (?, ?, ?)");
        $stmt->execute([$name, $user_id, $dept_id]);
        $message = "<div class='alert success'>Dossier créé.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_folder'])) {
    $folder_id = (int)($_POST['folder_id'] ?? 0);
    $target_type = $_POST['target_type'] ?? 'user';
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);
    $target_dept_id = (int)($_POST['target_department_id'] ?? 0);
    $target_group_id = (int)($_POST['target_group_id'] ?? 0);
    $can_view = isset($_POST['can_view']) ? 1 : 0;
    $can_download = isset($_POST['can_download']) ? 1 : 0;
    $can_share = isset($_POST['can_share']) ? 1 : 0;
    $can_edit = isset($_POST['can_edit']) ? 1 : 0;
    $expires_at = trim($_POST['expires_at'] ?? '') ?: null;

    $perm = share_acl_get_folder_permission($pdo, $folder_id, $user_id, $dept_id);
    if (empty($perm['can_share'])) {
        $message = "<div class='alert error'>Vous n'avez pas le droit de partager ce dossier.</div>";
    } elseif ($can_view !== 1) {
        $message = "<div class='alert error'>Le droit Voir est obligatoire.</div>";
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO folder_shares
                (folder_id, shared_with_user_id, shared_with_department_id, shared_with_group_id,
                 can_view, can_download, can_share, can_edit, expires_at, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $folder_id,
            $target_type === 'user' ? $target_user_id : null,
            $target_type === 'department' ? $target_dept_id : null,
            $target_type === 'group' ? $target_group_id : null,
            $can_view, $can_download, $can_share, $can_edit, $expires_at, $user_id
        ]);
        $message = "<div class='alert success'>Dossier partagé.</div>";
    }
}

$users_stmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE id != ? AND is_active = 1 ORDER BY full_name");
$users_stmt->execute([$user_id]);
$users = $users_stmt->fetchAll();
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$groups_stmt = $pdo->prepare(
    "SELECT g.id, g.name
     FROM share_groups g
     LEFT JOIN share_group_members gm ON gm.group_id = g.id
     WHERE g.owner_id = ? OR g.department_id = ? OR gm.user_id = ?
     GROUP BY g.id, g.name
     ORDER BY g.name"
);
$groups_stmt->execute([$user_id, $dept_id, $user_id]);
$share_groups = $groups_stmt->fetchAll();

$own_stmt = $pdo->prepare("SELECT * FROM folders WHERE owner_id = ? ORDER BY created_at DESC");
$own_stmt->execute([$user_id]);
$my_folders = $own_stmt->fetchAll();

$shared_stmt = $pdo->prepare(
    "SELECT DISTINCT f.*, u.full_name AS owner_name
     FROM folders f
     JOIN users u ON u.id = f.owner_id
     JOIN folder_shares fs ON fs.folder_id = f.id
     LEFT JOIN share_group_members gm ON gm.group_id = fs.shared_with_group_id AND gm.user_id = ?
     WHERE f.owner_id != ?
       AND fs.can_view = 1
       AND (fs.expires_at IS NULL OR fs.expires_at >= CURDATE())
       AND (fs.shared_with_user_id = ? OR fs.shared_with_department_id = ? OR gm.user_id IS NOT NULL)
     ORDER BY f.created_at DESC"
);
$shared_stmt->execute([$user_id, $user_id, $user_id, $dept_id]);
$shared_folders = $shared_stmt->fetchAll();

include '../includes/header.php';
?>

<div class="content-area">
    <h1><i class="fa-solid fa-folder-tree"></i> Dossiers</h1>
    <?= $message ?>

    <div class="recent-files" style="margin-bottom:16px;">
        <h3 style="margin-top:0;">Nouveau dossier</h3>
        <form method="post" style="display:flex; gap:10px; align-items:end;">
            <div style="flex:1;">
                <label>Nom du dossier</label>
                <input class="form-control" type="text" name="name" required>
            </div>
            <button class="btn-primary" name="create_folder" value="1">Créer</button>
        </form>
    </div>

    <div class="recent-files">
        <h3 style="margin-top:0;">Mes dossiers</h3>
        <table class="table">
            <thead><tr><th>Dossier</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($my_folders as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['name']) ?></td>
                    <td style="display:flex; gap:6px;">
                        <a class="btn-sm" href="files.php?folder=<?= (int)$d['id'] ?>">Voir fichiers</a>
                        <button type="button" class="btn-sm" style="background:#1b74e4; color:#fff;" onclick="openFolderShare(<?= (int)$d['id'] ?>, <?= json_encode($d['name']) ?>)">Partager</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="recent-files" style="margin-top:16px;">
        <h3 style="margin-top:0;">Dossiers partagés avec moi</h3>
        <?php if (!count($shared_folders)): ?>
            <p style="color:#64748b;">Aucun dossier partagé.</p>
        <?php else: ?>
        <table class="table">
            <thead><tr><th>Dossier</th><th>Propriétaire</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($shared_folders as $d): ?>
                <tr>
                    <td><?= htmlspecialchars($d['name']) ?></td>
                    <td><?= htmlspecialchars($d['owner_name']) ?></td>
                    <td><a class="btn-sm" href="files.php?folder=<?= (int)$d['id'] ?>">Voir fichiers</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div id="folderShareModal" class="modal" style="display:none;">
    <div class="modal-content" style="height:auto; max-width:520px; padding:20px;">
        <div class="modal-header">
            <span class="modal-title">Partager dossier: <span id="folderShareName"></span></span>
            <span class="close-btn" onclick="document.getElementById('folderShareModal').style.display='none'">&times;</span>
        </div>
        <div class="modal-body" style="display:block;">
            <form method="POST">
                <input type="hidden" name="share_folder" value="1">
                <input type="hidden" name="folder_id" id="folderShareId">

                <div style="display:flex; gap:10px; margin-bottom:10px;">
                    <label><input type="radio" name="target_type" value="user" checked onchange="toggleFolderTarget()"> Utilisateur</label>
                    <label><input type="radio" name="target_type" value="department" onchange="toggleFolderTarget()"> Service</label>
                    <label><input type="radio" name="target_type" value="group" onchange="toggleFolderTarget()"> Groupe</label>
                </div>

                <select id="folderTargetUser" name="target_user_id" class="form-control" style="margin-bottom:10px;">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (@<?= htmlspecialchars($u['username']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select id="folderTargetDept" name="target_department_id" class="form-control" style="margin-bottom:10px; display:none;">
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="folderTargetGroup" name="target_group_id" class="form-control" style="margin-bottom:12px; display:none;">
                    <?php foreach ($share_groups as $g): ?>
                        <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:10px;">
                    <label><input type="checkbox" name="can_view" checked> Voir</label>
                    <label><input type="checkbox" name="can_download" checked> Télécharger</label>
                    <label><input type="checkbox" name="can_share"> Re-partager</label>
                    <label><input type="checkbox" name="can_edit"> Modifier</label>
                </div>

                <label>Expiration (optionnel)</label>
                <input type="date" name="expires_at" class="form-control" style="margin-bottom:12px;">
                <button type="submit" class="btn-primary" style="width:100%;">Partager</button>
            </form>
        </div>
    </div>
</div>

<script>
function openFolderShare(id, name) {
    document.getElementById('folderShareId').value = id;
    document.getElementById('folderShareName').innerText = '"' + name + '"';
    document.getElementById('folderShareModal').style.display = 'block';
}
function toggleFolderTarget() {
    const v = (document.querySelector('input[name="target_type"]:checked') || {}).value || 'user';
    document.getElementById('folderTargetUser').style.display = v === 'user' ? 'block' : 'none';
    document.getElementById('folderTargetDept').style.display = v === 'department' ? 'block' : 'none';
    document.getElementById('folderTargetGroup').style.display = v === 'group' ? 'block' : 'none';
}
window.onclick = function(e) {
    if (e.target.id === 'folderShareModal') document.getElementById('folderShareModal').style.display = 'none';
};
toggleFolderTarget();
</script>

<?php include '../includes/footer.php'; ?>

