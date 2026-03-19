<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// Access Control for Secretary
if (isset($_SESSION['role']) && $_SESSION['role'] === 'secretaire') {
    header('Location: dashboard.php');
    exit;
}

$dept_id = $_SESSION['department_id'];
$dept_name = $_SESSION['department_name'] ?? 'Service';
$user_id = $_SESSION['user_id'];
$can_dispatch = in_array($_SESSION['role'] ?? '', ['admin', 'directeur', 'chef_service']);

$message = '';

// Dispatch de fichier par directeur/chef de service
if ($can_dispatch && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispatch_file'])) {
    $file_id = (int)($_POST['file_id'] ?? 0);
    $target = $_POST['target_type'] ?? 'user';
    $assigned_to = ($target === 'user') ? (int)($_POST['assigned_to'] ?? 0) : null;
    $assigned_to_dept = ($target === 'dept') ? (int)($_POST['assigned_to_dept'] ?? 0) : null;
    $instruction = trim($_POST['instruction'] ?? 'Pour traitement');

    if ($file_id && ($assigned_to || $assigned_to_dept)) {
        $check = $pdo->prepare("SELECT f.id FROM files f WHERE f.id = ? AND (f.department_id = ? OR ? = 'admin')");
        $check->execute([$file_id, $dept_id, $_SESSION['role'] ?? '']);
        if ($check->rowCount() > 0) {
            if ($assigned_to) {
                $exists = $pdo->prepare("SELECT id FROM file_shares WHERE file_id = ? AND shared_with_user_id = ?");
                $exists->execute([$file_id, $assigned_to]);
                if ($exists->rowCount() == 0) {
                    $pdo->prepare("INSERT INTO file_shares (file_id, shared_with_user_id) VALUES (?, ?)")->execute([$file_id, $assigned_to]);
                    notifyFileShared($pdo, $file_id, $assigned_to, $user_id);
                }
            }
            if ($assigned_to_dept) {
                $users_dept = $pdo->prepare("SELECT id FROM users WHERE department_id = ? AND is_active = 1");
                $users_dept->execute([$assigned_to_dept]);
                foreach ($users_dept->fetchAll() as $u) {
                    $exists = $pdo->prepare("SELECT id FROM file_shares WHERE file_id = ? AND shared_with_user_id = ?");
                    $exists->execute([$file_id, $u['id']]);
                    if ($exists->rowCount() == 0) {
                        $pdo->prepare("INSERT INTO file_shares (file_id, shared_with_user_id) VALUES (?, ?)")->execute([$file_id, $u['id']]);
                        notifyFileShared($pdo, $file_id, $u['id'], $user_id);
                    }
                }
            }
            $message = "<div class='alert success'>Fichier transmis avec succÃ¨s.</div>";
        }
    }
}

$users_list = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
$depts_list = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("SELECT files.*, users.username as uploader_name FROM files LEFT JOIN users ON files.owner_id = users.id WHERE files.department_id = ? AND (files.visibility = 'department' OR files.visibility = 'public') ORDER BY files.uploaded_at DESC");
$stmt->execute([$dept_id]);
$dept_files = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="content-area">
    <?= $message ?>
    <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #e2e8f0; padding-bottom:1rem; margin-bottom:2rem;">
        <h1 style="border:none; margin:0;"><i class="fa-solid fa-building"></i> <?= $t['service_files'] ?> : <?= htmlspecialchars($dept_name) ?></h1>
        <span class="badge" style="font-size: 1rem; padding: 0.5rem 1rem; background: var(--primary-color); color: white;">
            <?= count($dept_files) ?> <?= $t['shared_docs'] ?>
        </span>
    </div>

    <div class="recent-files">
        <?php if (count($dept_files) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= $t['name'] ?></th>
                    <th><?= $t['uploaded_by'] ?></th>
                    <th><?= $t['date'] ?></th>
                    <th><?= $t['actions'] ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($dept_files as $file): ?>
                <tr>
                    <td>
                        <i class="fa-regular fa-file" style="margin: 0 8px; color:#64748b;"></i>
                        <strong><?= htmlspecialchars($file['original_name']) ?></strong>
                    </td>
                    <td>
                        <div style="display:flex; align-items:center;">
                            <div style="width:30px; height:30px; background:#e0f2fe; color:#0284c7; border-radius:50%; display:flex; align-items:center; justify-content:center; margin: 0 10px; font-weight:bold;">
                                <?= strtoupper(substr($file['uploader_name'], 0, 1)) ?>
                            </div>
                            <?= htmlspecialchars($file['uploader_name']) ?>
                        </div>
                    </td>
                    <td dir="ltr"><?= date('d/m/Y', strtotime($file['uploaded_at'])) ?></td>
                    <td>
                        <a href="preview.php?id=<?= $file['id'] ?>" target="_blank" class="btn-sm" style="background:var(--accent-color); color:white;">
                            <i class="fa-solid fa-eye"></i> AperÃ§u
                        </a>
                        <a href="file_download.php?id=<?= (int)$file['id'] ?>" class="btn-sm">
                            <i class="fa-solid fa-download"></i> <?= $t['download'] ?>
                        </a>
                        <?php if ($can_dispatch): ?>
                        <button type="button" class="btn-sm" style="background:#1b74e4; color:white; border:none; cursor:pointer;" onclick="openDispatchModal(<?= $file['id'] ?>, '<?= addslashes($file['original_name']) ?>')" title="Transmettre">
                            <i class="fa-solid fa-share"></i> Transmettre
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div style="text-align:center; padding: 3rem;">
                <i class="fa-solid fa-folder-open fa-3x" style="color:#cbd5e1; margin-bottom:1rem;"></i>
                <p style="color:#64748b; font-size:1.1rem;"><?= $t['no_service_docs'] ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($can_dispatch): ?>
<div id="dispatchModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <span class="modal-title">Transmettre : <span id="dispatchFileName"></span></span>
            <span class="close-btn" onclick="closeDispatchModal()">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="dispatch_file" value="1">
            <input type="hidden" name="file_id" id="dispatchFileId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Transmettre Ã  :</label>
                    <div style="margin-bottom:10px;">
                        <input type="radio" name="target_type" value="user" checked onclick="toggleDispatchTarget('user')"> Une personne
                        <input type="radio" name="target_type" value="dept" style="margin-left:15px;" onclick="toggleDispatchTarget('dept')"> Un service
                    </div>
                    <select name="assigned_to" id="dispatchSelectUser" class="form-control">
                        <?php foreach($users_list as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="assigned_to_dept" id="dispatchSelectDept" class="form-control" style="display:none;">
                        <?php foreach($depts_list as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Instruction</label>
                    <select name="instruction" class="form-control">
                        <option value="Pour traitement">Pour traitement</option>
                        <option value="Pour information">Pour information</option>
                        <option value="Pour avis">Pour avis</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
            </div>
        </form>
    </div>
</div>
<script>
function openDispatchModal(fileId, fileName) {
    document.getElementById('dispatchFileId').value = fileId;
    document.getElementById('dispatchFileName').textContent = fileName;
    document.getElementById('dispatchModal').style.display = 'block';
}
function closeDispatchModal() {
    document.getElementById('dispatchModal').style.display = 'none';
}
function toggleDispatchTarget(type) {
    document.getElementById('dispatchSelectUser').style.display = type === 'user' ? 'block' : 'none';
    document.getElementById('dispatchSelectDept').style.display = type === 'dept' ? 'block' : 'none';
}
window.onclick = function(e) { if (e.target.id === 'dispatchModal') closeDispatchModal(); };
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

