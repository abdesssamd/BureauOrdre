<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Access control for secretary
if (isset($_SESSION['role']) && $_SESSION['role'] === 'secretaire') {
    header('Location: dashboard.php');
    exit;
}

$target_dir = "../uploads/";
$allowed_types = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg',
    'image/png',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
    'text/csv',
    'application/zip',
    'application/x-zip-compressed',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation'
];
$allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx', 'txt', 'csv', 'zip', 'rar', '7z', 'ppt', 'pptx'];
$mime_by_extension = [
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
    'txt' => ['text/plain', 'application/octet-stream', 'inode/x-empty'],
    'csv' => ['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/octet-stream'],
    'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
    'rar' => ['application/x-rar', 'application/vnd.rar', 'application/octet-stream'],
    '7z' => ['application/x-7z-compressed', 'application/octet-stream'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream']
];
$max_size = 10 * 1024 * 1024;

$message = "";
$tags_list = [];
try {
    $tags_list = $pdo->query("SELECT id, name, color FROM file_tags ORDER BY name")->fetchAll();
} catch (Exception $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $visibility = $_POST['visibility'] ?? 'department';
    $tag_id = !empty($_POST['tag_id']) ? intval($_POST['tag_id']) : null;

    $raw_files = $_FILES['document'];
    $files = [];

    if (is_array($raw_files['name'])) {
        $total_files = count($raw_files['name']);
        for ($i = 0; $i < $total_files; $i++) {
            $files[] = [
                'name' => $raw_files['name'][$i] ?? '',
                'type' => $raw_files['type'][$i] ?? '',
                'tmp_name' => $raw_files['tmp_name'][$i] ?? '',
                'error' => $raw_files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $raw_files['size'][$i] ?? 0
            ];
        }
    } else {
        $files[] = $raw_files;
    }

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    $success_count = 0;
    $errors = [];
    $valid_tag_ids = array_column($tags_list, 'id');
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;

    foreach ($files as $file) {
        $original_name = trim((string)($file['name'] ?? ''));
        if ($original_name === '' || (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
            continue;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = htmlspecialchars($original_name) . ' : erreur de transfert (code ' . intval($file['error']) . ').';
            continue;
        }

        if (($file['size'] ?? 0) > $max_size) {
            $errors[] = htmlspecialchars($original_name) . ' : fichier trop volumineux (max 10 Mo).';
            continue;
        }

        $ext = strtolower((string)pathinfo($original_name, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowed_extensions, true)) {
            $shown_ext = $ext !== '' ? ('.' . $ext) : '(sans extension)';
            $errors[] = htmlspecialchars($original_name) . ' : extension non autorisee ' . htmlspecialchars($shown_ext) . '.';
            continue;
        }

        $detected_mime = '';
        if ($finfo) {
            $detected_mime = (string)finfo_file($finfo, $file['tmp_name']);
        }
        $client_mime = (string)($file['type'] ?? '');
        $allowed_for_ext = $mime_by_extension[$ext] ?? $allowed_types;
        $mime_is_valid = in_array($detected_mime, $allowed_for_ext, true) || in_array($client_mime, $allowed_for_ext, true);

        if (!$mime_is_valid) {
            $errors[] = htmlspecialchars($original_name) . ' : format non autorise (MIME detecte: ' . htmlspecialchars($detected_mime ?: 'inconnu') . ').';
            continue;
        }

        $unique_name = bin2hex(random_bytes(16)) . '.' . $ext;
        $target_path = $target_dir . $unique_name;

        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            $errors[] = htmlspecialchars($original_name) . ' : erreur serveur lors de l enregistrement.';
            continue;
        }

        $stored_mime = $detected_mime !== '' ? $detected_mime : $client_mime;

        try {
            $sql = 'INSERT INTO files (original_name, stored_name, mime_type, size, owner_id, department_id, visibility, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $original_name,
                $unique_name,
                $stored_mime,
                $file['size'],
                $_SESSION['user_id'],
                $_SESSION['department_id'],
                $visibility,
                $file['size']
            ]);

            $file_id = $pdo->lastInsertId();

            if ($tag_id && in_array($tag_id, $valid_tag_ids, true)) {
                try {
                    $pdo->prepare('INSERT INTO file_tag_links (file_id, tag_id) VALUES (?, ?)')->execute([$file_id, $tag_id]);
                } catch (Exception $e) {
                }
            }

            $log_details = 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '');
            $log = $pdo->prepare('INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)');
            $log->execute([$_SESSION['user_id'], 'Upload fichier: ' . $original_name, $log_details]);

            if ($visibility === 'department' || $visibility === 'public') {
                notifyDepartmentUpload($pdo, $file_id, $_SESSION['user_id'], $_SESSION['department_id']);
            }

            $success_count++;
        } catch (PDOException $e) {
            if (file_exists($target_path)) {
                unlink($target_path);
            }
            $errors[] = htmlspecialchars($original_name) . ' : erreur base de donnees.';
        }
    }

    if ($finfo) {
        finfo_close($finfo);
    }

    $message_parts = [];
    if ($success_count > 0) {
        $extra = '';
        if ($visibility === 'department' || $visibility === 'public') {
            $extra = '<br><small>Les membres concernes ont ete notifies.</small>';
        }
        $share_hint = '<br><small>Vous pouvez aussi partager un fichier deja dans <a href=\"files.php\" style=\"color:inherit; text-decoration:underline; font-weight:600;\">Mes Documents</a> via le bouton <strong>Partager</strong>.</small>';
        $message_parts[] = "<div class='alert success'><i class='fa-solid fa-circle-check'></i> {$success_count} fichier(s) archive(s) avec succes.{$extra}{$share_hint}</div>";
    }
    if (!empty($errors)) {
        $message_parts[] = "<div class='alert error'><strong>Erreurs :</strong><ul><li>" . implode('</li><li>', $errors) . '</li></ul></div>';
    }
    if (empty($message_parts)) {
        $message_parts[] = "<div class='alert error'>Aucun fichier selectionne.</div>";
    }

    $message = implode('', $message_parts);
}

include '../includes/header.php';
?>
            <h1><i class="fa-solid fa-cloud-arrow-up"></i> Deposer un document officiel</h1>

            <?= $message ?>

            <div class="upload-card">
                <form action="upload.php" method="POST" enctype="multipart/form-data">
                    <div class="drop-zone" onclick="document.getElementById('fileInput').click();">
                        <i class="fa-solid fa-file-import fa-3x"></i>
                        <p>Cliquez ici pour selectionner un ou plusieurs fichiers</p>
                        <input type="file" id="fileInput" name="document[]" required multiple class="file-input" style="display:none;" onchange="updateFileName(this)">
                    </div>

                    <?php if (!empty($tags_list)): ?>
                    <div class="form-group">
                        <label>Categorie / Tag</label>
                        <select name="tag_id" class="form-control">
                            <option value="">-- Aucun --</option>
                            <?php foreach ($tags_list as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Visibilite du document</label>
                        <select name="visibility" class="form-control" id="visibilitySelect" onchange="updateNotifInfo()">
                            <option value="department">Interne (Mon service uniquement)</option>
                            <option value="public">Public (Toute l administration)</option>
                            <option value="private">Prive (Moi uniquement)</option>
                        </select>
                        <small id="notifInfo" style="color: #64748b; display: block; margin-top: 8px;">
                            <i class="fa-solid fa-bell"></i> Les membres de votre service seront notifies
                        </small>
                    </div>

                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Archiver le(s) document(s)
                    </button>
                </form>
            </div>

</div>
    <script>
        function updateFileName(input) {
            const dropZone = document.querySelector('.drop-zone p');
            if (input.files && input.files.length > 0) {
                if (input.files.length === 1) {
                    dropZone.innerHTML = '<i class="fa-solid fa-file-check"></i> ' + input.files[0].name;
                } else {
                    dropZone.innerHTML = '<i class="fa-solid fa-file-check"></i> ' + input.files.length + ' fichiers selectionnes';
                }
                dropZone.style.color = 'var(--primary-color)';
            }
        }

        function updateNotifInfo() {
            const visibility = document.getElementById('visibilitySelect').value;
            const notifInfo = document.getElementById('notifInfo');

            if (visibility === 'private') {
                notifInfo.innerHTML = '<i class="fa-solid fa-lock"></i> Aucune notification ne sera envoyee (fichier prive)';
                notifInfo.style.color = '#94a3b8';
            } else if (visibility === 'department') {
                notifInfo.innerHTML = '<i class="fa-solid fa-bell"></i> Les membres de votre service seront notifies';
                notifInfo.style.color = '#64748b';
            } else {
                notifInfo.innerHTML = '<i class="fa-solid fa-bell"></i> Tous les membres seront notifies';
                notifInfo.style.color = '#64748b';
            }
        }
    </script>
<?php
include '../includes/footer.php';
?>
