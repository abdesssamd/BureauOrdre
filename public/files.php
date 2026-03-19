<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php'; // NOUVEAU
require_once '../includes/share_acl.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// Access Control for Secretary
if (isset($_SESSION['role']) && $_SESSION['role'] === 'secretaire') {
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$dept_id = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : null;
share_acl_ensure_schema($pdo);
$message = "";

// --- RETRAIT D'UN PARTAGE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unshare_file'])) {
    $share_id = (int)($_POST['share_id'] ?? 0);
    $file_id = (int)($_POST['file_id'] ?? 0);
    $check = $pdo->prepare("SELECT fs.id FROM file_shares fs JOIN files f ON fs.file_id = f.id WHERE fs.id = ? AND fs.file_id = ? AND f.owner_id = ?");
    $check->execute([$share_id, $file_id, $user_id]);
    if ($check->rowCount() > 0) {
        $pdo->prepare("DELETE FROM file_shares WHERE id = ?")->execute([$share_id]);
        $message = "<div class='alert success'>AccÃ¨s retirÃ©.</div>";
    }
}

// --- TRAITEMENT DU PARTAGE (simple ou multiple) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_file'])) {
    $file_ids = isset($_POST['file_ids']) ? (array)$_POST['file_ids'] : (isset($_POST['file_id']) ? [$_POST['file_id']] : []);
    $file_ids = array_filter(array_map('intval', $file_ids));
    $target_type = $_POST['target_type'] ?? 'user';
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);
    $target_dept_id = (int)($_POST['target_department_id'] ?? 0);
    $target_group_id = (int)($_POST['target_group_id'] ?? 0);
    $can_view = isset($_POST['can_view']) ? 1 : 0;
    $can_download = isset($_POST['can_download']) ? 1 : 0;
    $can_share = isset($_POST['can_share']) ? 1 : 0;
    $can_edit = isset($_POST['can_edit']) ? 1 : 0;
    $expires_at = trim($_POST['expires_at'] ?? '');
    $expires_at = $expires_at !== '' ? $expires_at : null;

    $target_ok = ($target_type === 'user' && $target_user_id > 0)
        || ($target_type === 'department' && $target_dept_id > 0)
        || ($target_type === 'group' && $target_group_id > 0);

    if (!$target_ok || $can_view !== 1) {
        $message = "<div class='alert error'>Cible ou droits invalides.</div>";
    } else {
        $success = 0;
        $skipped = 0;

        foreach ($file_ids as $fid) {
            $perm = share_acl_get_file_permission($pdo, $fid, (int)$user_id, $dept_id);
            if (empty($perm['can_share'])) {
                continue;
            }

            $condSql = "file_id = ?";
            $condParams = [$fid];
            if ($target_type === 'user') {
                $condSql .= " AND shared_with_user_id = ?";
                $condParams[] = $target_user_id;
            } elseif ($target_type === 'department') {
                $condSql .= " AND shared_with_department_id = ?";
                $condParams[] = $target_dept_id;
            } else {
                $condSql .= " AND shared_with_group_id = ?";
                $condParams[] = $target_group_id;
            }

            $check = $pdo->prepare("SELECT id FROM file_shares WHERE {$condSql}");
            $check->execute($condParams);
            if ($check->rowCount() > 0) {
                $skipped++;
                continue;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO file_shares (
                    file_id, shared_with_user_id, shared_with_department_id, shared_with_group_id,
                    can_view, can_download, can_share, can_edit, expires_at, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ok = $stmt->execute([
                $fid,
                $target_type === 'user' ? $target_user_id : null,
                $target_type === 'department' ? $target_dept_id : null,
                $target_type === 'group' ? $target_group_id : null,
                $can_view,
                $can_download,
                $can_share,
                $can_edit,
                $expires_at,
                $user_id
            ]);

            if ($ok) {
                if ($target_type === 'user') {
                    notifyFileShared($pdo, $fid, $target_user_id, $user_id);
                }
                $pdo->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)")
                    ->execute([$user_id, "Partage fichier", "Fichier ID $fid vers cible $target_type"]);
                $success++;
            }
        }

        if ($success > 0) {
            $message = "<div class='alert success'>{$success} partage(s) créé(s)." . ($skipped ? " ({$skipped} déjà existants)" : "") . "</div>";
        } elseif ($skipped > 0) {
            $message = "<div class='alert error'>Partages déjà existants pour cette cible.</div>";
        } else {
            $message = "<div class='alert error'>Aucun partage autorisé.</div>";
        }
    }
}

// --- SUPPRESSION ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $file_id = $_GET['delete'];
    $check = $pdo->prepare("SELECT stored_name FROM files WHERE id = ? AND owner_id = ?");
    $check->execute([$file_id, $user_id]);
    $file_to_delete = $check->fetch();

    if ($file_to_delete) {
        $path = "../uploads/" . $file_to_delete['stored_name'];
        if (file_exists($path)) { unlink($path); }
        $pdo->prepare("DELETE FROM file_shares WHERE file_id = ?")->execute([$file_id]);
        $pdo->prepare("DELETE FROM files WHERE id = ?")->execute([$file_id]);
        header("Location: files.php");
        exit;
    }
}

// --- RÃ‰CUPÃ‰RATION DES DONNÃ‰ES ---
$users_list = $pdo->prepare("SELECT id, full_name, username FROM users WHERE id != ? AND is_active = 1 ORDER BY full_name");
$users_list->execute([$user_id]);
$users = $users_list->fetchAll();
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

$current_folder_id = isset($_GET['folder']) && is_numeric($_GET['folder']) ? $_GET['folder'] : null;
$folder_name = "Racine";
$folder_is_owned = true;
$folder_perm = ['can_view' => true, 'can_share' => true, 'can_edit' => true];
if ($current_folder_id) {
    $f_stmt = $pdo->prepare("SELECT id, name, owner_id FROM folders WHERE id = ?");
    $f_stmt->execute([$current_folder_id]);
    $res = $f_stmt->fetch();
    if (!$res) {
        header('Location: files.php?err=access');
        exit;
    }
    $folder_name = $res['name'];
    $folder_is_owned = ((int)$res['owner_id'] === (int)$user_id);
    if (!$folder_is_owned) {
        $folder_perm = share_acl_get_folder_permission($pdo, (int)$current_folder_id, (int)$user_id, $dept_id);
        if (empty($folder_perm['can_view'])) {
            header('Location: files.php?err=access');
            exit;
        }
    }
}

$stmt_folders = $pdo->prepare("SELECT * FROM folders WHERE owner_id = ?");
$stmt_folders->execute([$user_id]);
$folders = $stmt_folders->fetchAll();

$sql_files = "";
$params = [];
if ($current_folder_id && !$folder_is_owned) {
    $sql_files = "SELECT * FROM files WHERE folder_id = ? ORDER BY uploaded_at DESC";
    $params = [$current_folder_id];
} else {
    $sql_files = "SELECT * FROM files WHERE owner_id = ? " . ($current_folder_id ? " AND folder_id = ?" : " AND folder_id IS NULL") . " ORDER BY uploaded_at DESC";
    $params = $current_folder_id ? [$user_id, $current_folder_id] : [$user_id];
}
$stmt = $pdo->prepare($sql_files);
$stmt->execute($params);
$my_files = $stmt->fetchAll();

$sql_shared = "
    SELECT files.*, users.full_name as owner_name,
           MAX(file_shares.can_download) AS can_download_eff
    FROM files
    JOIN file_shares ON files.id = file_shares.file_id
    JOIN users ON files.owner_id = users.id
    LEFT JOIN share_group_members gm ON gm.group_id = file_shares.shared_with_group_id AND gm.user_id = ?
    WHERE file_shares.can_view = 1
      AND (file_shares.expires_at IS NULL OR file_shares.expires_at >= CURDATE())
      AND (
            file_shares.shared_with_user_id = ?
            OR file_shares.shared_with_department_id = ?
            OR gm.user_id IS NOT NULL
          )
    GROUP BY files.id
    ORDER BY MAX(file_shares.created_at) DESC";
$stmt_shared = $pdo->prepare($sql_shared);
$stmt_shared->execute([$user_id, $user_id, $dept_id]);
$shared_files = $stmt_shared->fetchAll();

$share_history = [];
foreach ($my_files as $f) {
    $sh = $pdo->prepare(
        "SELECT
            fs.id as share_id,
            fs.shared_with_user_id,
            fs.shared_with_department_id,
            fs.shared_with_group_id,
            fs.can_view,
            fs.can_download,
            fs.can_share,
            fs.can_edit,
            fs.expires_at,
            fs.created_at,
            CASE
                WHEN fs.shared_with_user_id IS NOT NULL THEN COALESCE(u.full_name, CONCAT('User#', fs.shared_with_user_id))
                WHEN fs.shared_with_department_id IS NOT NULL THEN CONCAT('Service: ', d.name)
                WHEN fs.shared_with_group_id IS NOT NULL THEN CONCAT('Groupe: ', g.name)
                ELSE 'Cible inconnue'
            END AS target_name
         FROM file_shares fs
         LEFT JOIN users u ON fs.shared_with_user_id = u.id
         LEFT JOIN departments d ON fs.shared_with_department_id = d.id
         LEFT JOIN share_groups g ON fs.shared_with_group_id = g.id
         WHERE fs.file_id = ?"
    );
    $sh->execute([$f['id']]);
    $rows = $sh->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) $r['created_at_fmt'] = date('d/m/Y H:i', strtotime($r['created_at']));
    $share_history[$f['id']] = $rows;
}

$file_perm_map = [];
$all_for_perm = array_merge($my_files, $shared_files);
foreach ($all_for_perm as $ff) {
    $fid = (int)$ff['id'];
    if (!isset($file_perm_map[$fid])) {
        $file_perm_map[$fid] = share_acl_get_file_permission($pdo, $fid, (int)$user_id, $dept_id);
    }
}

$file_tags_map = [];
try {
    $all_ids = array_merge(array_column($my_files, 'id'), array_column($shared_files, 'id'));
    if (!empty($all_ids)) {
        $ph = implode(',', array_fill(0, count($all_ids), '?'));
        $tags_stmt = $pdo->prepare("SELECT ftl.file_id, ft.id, ft.name, ft.color FROM file_tag_links ftl JOIN file_tags ft ON ftl.tag_id = ft.id WHERE ftl.file_id IN ($ph)");
        $tags_stmt->execute($all_ids);
        while ($r = $tags_stmt->fetch()) {
            if (!isset($file_tags_map[$r['file_id']])) $file_tags_map[$r['file_id']] = [];
            $file_tags_map[$r['file_id']][] = $r;
        }
    }
} catch (Exception $e) {}

include '../includes/header.php'; 
?>
            <?= $message ?>
            <?php if (isset($_GET['err'])): ?>
                <div class="alert error">
                    <?php
                    if ($_GET['err'] == 'nozip') echo 'L\'extension ZIP n\'est pas disponible sur le serveur.';
                    elseif ($_GET['err'] == 'access') echo 'AccÃ¨s refusÃ© aux fichiers sÃ©lectionnÃ©s.';
                    else echo 'Erreur lors du tÃ©lÃ©chargement.';
                    ?>
                </div>
            <?php endif; ?>
            
            <h1><i class="fa-regular fa-folder-open"></i> Mes Documents</h1>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; flex-wrap:wrap; gap:10px;">
                <div style="font-size: 1.1rem; color: #64748b;">
                    <a href="files.php" style="text-decoration:none; color:var(--primary-color);">Racine</a> 
                    <?php if($current_folder_id): ?> / <strong><?= htmlspecialchars($folder_name) ?></strong><?php endif; ?>
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="share_groups.php" class="btn-sm" style="background:#0f5fcc; color:white;">
                        <i class="fa-solid fa-people-group"></i> Groupes
                    </a>
                    <button type="button" id="btnBulkShare" class="btn-sm" style="background:#1b74e4; color:white; display:none;" onclick="openBulkShareModal()">
                        <i class="fa-solid fa-share-nodes"></i> Partager la sÃ©lection
                    </button>
                    <button type="button" id="btnBulkDownload" class="btn-sm" style="background:#10b981; color:white; display:none;" onclick="bulkDownload()">
                        <i class="fa-solid fa-file-zipper"></i> TÃ©lÃ©charger la sÃ©lection
                    </button>
                </div>
            </div>

            <div class="recent-files">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="checkAll" onclick="toggleAll(this)" title="Tout sÃ©lectionner"></th>
                            <th width="45%">Nom</th>
                            <th>Type</th>
                            <th>Taille</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($folders as $folder): ?>
                        <tr>
                            <td></td>
                            <td>
                                <a href="files.php?folder=<?= $folder['id'] ?>" style="color:inherit; font-weight:bold; text-decoration:none;">
                                    <i class="fa-solid fa-folder" style="color:#fbbf24; margin-right:8px;"></i> <?= htmlspecialchars($folder['name']) ?>
                                </a>
                            </td>
                            <td><span class="badge">Dossier</span></td>
                            <td>-</td>
                            <td><a href="files.php?folder=<?= $folder['id'] ?>" class="btn-sm"><i class="fa-solid fa-arrow-right"></i></a></td>
                        </tr>
                        <?php endforeach; ?>

                        <?php foreach($my_files as $file): 
                            $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                        ?>
                        <tr>
                            <td><input type="checkbox" class="file-check my-file-check" value="<?= $file['id'] ?>"></td>
                            <td>
                                <i class="fa-regular fa-file" style="color:#64748b; margin-right:8px;"></i> <?= htmlspecialchars($file['original_name']) ?>
                                <?php if (!empty($file_tags_map[$file['id']])): ?>
                                    <div style="margin-top:4px;">
                                        <?php foreach ($file_tags_map[$file['id']] as $tag): ?>
                                            <span class="badge" style="background:<?= htmlspecialchars($tag['color']) ?>20; color:<?= htmlspecialchars($tag['color']) ?>; font-size:0.75rem;"><?= htmlspecialchars($tag['name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge" style="background:#e2e8f0;"><?= strtoupper($ext) ?></span></td>
                            <td><?= round($file['size']/1024, 1) ?> Ko</td>
                            <td>
                                <?php $p = $file_perm_map[(int)$file['id']] ?? ['can_view'=>false,'can_share'=>false,'can_download'=>false,'can_edit'=>false]; ?>
                                <button type="button" class="btn-sm" style="background:var(--accent-color); color:white; border:none; cursor:pointer;" 
                                        onclick="window.open('preview.php?id=<?= $file['id'] ?>', '_blank')">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <?php if (!empty($p['can_share'])): ?>
                                <button type="button" class="btn-sm" style="background:#1b74e4; color:white; border:none; cursor:pointer;" 
                                        onclick="openShareModal(<?= $file['id'] ?>, <?= json_encode($file['original_name']) ?>)" title="Partager">
                                    <i class="fa-solid fa-share-nodes"></i>
                                </button>
                                <?php endif; ?>
                                <button type="button" class="btn-sm" style="background:#64748b; color:white; border:none; cursor:pointer;" 
                                        onclick="openHistoryModal(<?= $file['id'] ?>, <?= json_encode($file['original_name']) ?>)" title="Voir qui a accÃ¨s">
                                    <i class="fa-solid fa-users"></i>
                                </button>
                                <?php if (!empty($p['can_download'])): ?>
                                <a href="file_download.php?id=<?= (int)$file['id'] ?>" class="btn-sm"><i class="fa-solid fa-download"></i></a>
                                <?php endif; ?>
                                <?php if ((int)$file['owner_id'] === (int)$user_id): ?>
                                <a href="files.php?delete=<?= $file['id'] ?>" class="btn-sm" style="background:#fee2e2; color:#ef4444;" onclick="return confirm('Supprimer ?')"><i class="fa-solid fa-trash"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h2 style="margin-top: 40px; color: var(--primary-color);"><i class="fa-solid fa-inbox"></i> PartagÃ©s avec moi</h2>
            <div class="recent-files">
                <?php if(count($shared_files) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="checkAllShared" onclick="toggleAllShared(this)"></th>
                            <th>Fichier</th>
                            <th>PartagÃ© par</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($shared_files as $shared): 
                             $ext = strtolower(pathinfo($shared['original_name'], PATHINFO_EXTENSION));
                        ?>
                        <tr>
                            <td><input type="checkbox" class="file-check shared-file-check" value="<?= $shared['id'] ?>"></td>
                            <td>
                                <strong><?= htmlspecialchars($shared['original_name']) ?></strong>
                                <?php if (!empty($file_tags_map[$shared['id']])): ?>
                                    <div style="margin-top:4px;">
                                        <?php foreach ($file_tags_map[$shared['id']] as $tag): ?>
                                            <span class="badge" style="background:<?= htmlspecialchars($tag['color']) ?>20; color:<?= htmlspecialchars($tag['color']) ?>; font-size:0.75rem;"><?= htmlspecialchars($tag['name']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background:#dbeafe; color:#1e40af;">
                                    <i class="fa-solid fa-user"></i> <?= htmlspecialchars($shared['owner_name']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($shared['uploaded_at'])) ?></td>
                            <td>
                                <button type="button" class="btn-sm" style="background:var(--accent-color); color:white; border:none; cursor:pointer;" 
                                        onclick="window.open('preview.php?id=<?= $shared['id'] ?>', '_blank')">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <?php if ((int)($shared['can_download_eff'] ?? 0) === 1): ?>
                                <a href="file_download.php?id=<?= (int)$shared['id'] ?>" class="btn-sm"><i class="fa-solid fa-download"></i></a>
                                <?php else: ?>
                                <span class="btn-sm" style="background:#e2e8f0; color:#64748b;" title="Téléchargement non autorisé"><i class="fa-solid fa-lock"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="color:#64748b; padding:1rem;">Aucun fichier n'a Ã©tÃ© partagÃ© avec vous pour le moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="shareModal" class="modal">
        <div class="modal-content" style="height: auto; max-width: 500px; padding: 20px;">
            <div class="modal-header">
                <span class="modal-title">Partager <span id="shareFileName"></span></span>
                <span class="close-btn" onclick="document.getElementById('shareModal').style.display='none'">&times;</span>
            </div>
            <div class="modal-body" style="background: white; padding: 20px; display:block;">
                <form method="POST">
                    <div id="shareFileIdsContainer"></div>
                    <label style="display:block; margin-bottom:8px; font-weight:bold;">Cible :</label>
                    <div style="display:flex; gap:12px; margin-bottom:12px;">
                        <label><input type="radio" name="target_type" value="user" checked onchange="toggleShareTarget()"> Utilisateur</label>
                        <label><input type="radio" name="target_type" value="department" onchange="toggleShareTarget()"> Service</label>
                        <label><input type="radio" name="target_type" value="group" onchange="toggleShareTarget()"> Groupe</label>
                    </div>

                    <select id="targetUserSelect" name="target_user_id" class="form-control" style="width:100%; margin-bottom:10px;">
                        <?php foreach($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <select id="targetDeptSelect" name="target_department_id" class="form-control" style="width:100%; margin-bottom:10px; display:none;">
                        <?php foreach($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="targetGroupSelect" name="target_group_id" class="form-control" style="width:100%; margin-bottom:14px; display:none;">
                        <?php foreach($share_groups as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label style="display:block; margin-bottom:8px; font-weight:bold;">Droits :</label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:12px;">
                        <label><input type="checkbox" name="can_view" checked> Voir</label>
                        <label><input type="checkbox" name="can_download" checked> Télécharger</label>
                        <label><input type="checkbox" name="can_share"> Re-partager</label>
                        <label><input type="checkbox" name="can_edit"> Modifier</label>
                    </div>

                    <label style="display:block; margin-bottom:6px;">Expiration (optionnel)</label>
                    <input type="date" name="expires_at" class="form-control" style="width:100%; margin-bottom:16px;">
                    <button type="submit" name="share_file" class="btn-primary" style="width:100%;">Confirmer le partage</button>
                </form>
            </div>
        </div>
    </div>

    <div id="historyModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <span class="modal-title">AccÃ¨s au fichier : <span id="historyFileName"></span></span>
                <span class="close-btn" onclick="document.getElementById('historyModal').style.display='none'">&times;</span>
            </div>
            <div class="modal-body" id="historyModalBody">
                <p style="color:#64748b;">Personnes ayant accÃ¨s :</p>
                <div id="historyList"></div>
            </div>
        </div>
    </div>

    <script>
    const shareHistoryData = <?= json_encode($share_history, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}' ?>;
        function openShareModal(id, name, ids) {
            const container = document.getElementById('shareFileIdsContainer');
            container.innerHTML = '';
            const fileIds = ids && ids.length ? ids : [id];
            fileIds.forEach(fid => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'file_ids[]'; inp.value = fid;
                container.appendChild(inp);
            });
            document.getElementById('shareFileName').innerText = ids && ids.length > 1 ? '(' + ids.length + ' fichiers)' : '"' + name + '"';
            toggleShareTarget();
            document.getElementById('shareModal').style.display = 'block';
        }
        function openBulkShareModal() {
            const checked = Array.from(document.querySelectorAll('.my-file-check:checked')).map(c => c.value);
            if (checked.length === 0) return;
            openShareModal(checked[0], '', checked);
        }
        function openHistoryModal(fileId, fileName) {
            document.getElementById('historyFileName').innerText = '"' + fileName + '"';
            const list = document.getElementById('historyList');
            const data = shareHistoryData[fileId] || [];
            if (data.length === 0) {
                list.innerHTML = '<p style="color:#64748b; padding:10px;">Aucun partage actif.</p>';
            } else {
                list.innerHTML = data.map(s => `
                    <div style="display:flex; align-items:center; justify-content:space-between; padding:10px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:8px;">
                        <span>
                            <i class="fa-solid fa-user-shield"></i> ${escapeHtml(s.target_name || 'Cible')}
                            <small style="display:block; color:#64748b;">
                                droits: ${s.can_view==1?'V':''}${s.can_download==1?' D':''}${s.can_share==1?' S':''}${s.can_edit==1?' M':''}
                                ${s.expires_at ? ' • exp: '+s.expires_at : ''}
                            </small>
                        </span>
                        <span style="font-size:0.85rem; color:#64748b;">depuis ${s.created_at_fmt || s.created_at}</span>
                        <form method="POST" style="margin:0; display:inline;">
                            <input type="hidden" name="unshare_file" value="1">
                            <input type="hidden" name="file_id" value="${fileId}">
                            <input type="hidden" name="share_id" value="${s.share_id}">
                            <button type="submit" class="btn-sm" style="background:#fee2e2; color:#ef4444; border:none; cursor:pointer;" onclick="return confirm('Retirer l\'accÃ¨s ?')" title="Retirer"><i class="fa-solid fa-user-minus"></i></button>
                        </form>
                    </div>
                `).join('');
            }
            document.getElementById('historyModal').style.display = 'block';
        }
        function escapeHtml(s) { const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
        function toggleShareTarget() {
            const v = (document.querySelector('input[name="target_type"]:checked') || {}).value || 'user';
            document.getElementById('targetUserSelect').style.display = (v === 'user') ? 'block' : 'none';
            document.getElementById('targetDeptSelect').style.display = (v === 'department') ? 'block' : 'none';
            document.getElementById('targetGroupSelect').style.display = (v === 'group') ? 'block' : 'none';
        }

        function toggleAll(cb) {
            document.querySelectorAll('.file-check').forEach(c => c.checked = cb.checked);
            const cb2 = document.getElementById('checkAllShared');
            if (cb2) cb2.checked = cb.checked;
            updateBulkBtn();
        }
        function toggleAllShared(cb) {
            document.querySelectorAll('.shared-file-check').forEach(c => c.checked = cb.checked);
            updateBulkBtn();
        }
        function updateBulkBtn() {
            const n = document.querySelectorAll('.file-check:checked').length;
            const nMy = document.querySelectorAll('.my-file-check:checked').length;
            document.getElementById('btnBulkDownload').style.display = n > 0 ? 'inline-flex' : 'none';
            document.getElementById('btnBulkShare').style.display = nMy > 0 ? 'inline-flex' : 'none';
        }
        document.querySelectorAll('.file-check').forEach(c => c.addEventListener('change', updateBulkBtn));
        function bulkDownload() {
            const ids = Array.from(document.querySelectorAll('.file-check:checked')).map(c => c.value).join(',');
            if (ids) window.location.href = 'download_zip.php?ids=' + ids;
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }
        toggleShareTarget();
    </script>
<?php 
// 2. On inclut le FOOTER (il ferme les balises)
include '../includes/footer.php'; 
?>

