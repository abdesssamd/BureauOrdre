<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/share_acl.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Non autorisÃ©');
}

$file_id = $_GET['id'] ?? 0;
share_acl_ensure_schema($pdo);

// RÃ©cupÃ©rer les infos du fichier
$stmt = $pdo->prepare("
    SELECT files.*, users.username 
    FROM files 
    LEFT JOIN users ON files.owner_id = users.id 
    WHERE files.id = ?
");
$stmt->execute([$file_id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    exit('Fichier introuvable');
}

$user_id = (int)$_SESSION["user_id"];
$dept_id = isset($_SESSION["department_id"]) ? (int)$_SESSION["department_id"] : null;
$perm = share_acl_get_file_permission($pdo, (int)$file_id, $user_id, $dept_id);
$has_access = (bool)$perm["can_view"];

// Fallback metier: fichier lie a un courrier assigne
if (!$has_access) {
    $check_task = $pdo->prepare("
        SELECT 1 FROM mails m
        JOIN mail_assignments ma ON m.id = ma.mail_id
        WHERE m.file_id = ? AND (ma.assigned_to = ? OR ma.assigned_to_dept = ?)
    ");
    $check_task->execute([(int)$file_id, $user_id, $dept_id]);
    if ($check_task->rowCount() > 0) {
        $has_access = true;
    }
}

if (!$has_access) {
    http_response_code(403);
    exit('AccÃ¨s refusÃ©');
}

$file_path = "file_download.php?id=" . (int)$file_id . "&inline=1";
$download_path = "file_download.php?id=" . (int)$file_id;
$ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
$can_download = (bool)$perm['can_download'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>AperÃ§u - <?= htmlspecialchars($file['original_name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #0f172a; font-family: system-ui, -apple-system, sans-serif; min-height: 100vh; color: #e2e8f0; }
        .preview-container { max-width: 1600px; margin: 0 auto; padding: 20px; display: flex; flex-direction: column; min-height: 100vh; }
        .preview-header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            padding: 18px 24px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255,255,255,0.08);
        }
        .file-info { display: flex; align-items: center; gap: 16px; }
        .file-icon {
            width: 52px; height: 52px;
            background: rgba(59, 130, 246, 0.2);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px; color: #60a5fa;
        }
        .preview-header h2 { margin: 0; font-size: 1.2rem; color: #f8fafc; }
        .preview-header p { margin: 6px 0 0; color: #94a3b8; font-size: 0.9rem; }
        .preview-actions { display: flex; gap: 10px; align-items: center; }
        .preview-actions .btn-primary { background: #3b82f6; }
        .preview-actions .btn-primary:hover { background: #2563eb; }
        .preview-content {
            flex: 1; background: #1e293b; padding: 24px; border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2); min-height: 70vh;
            display: flex; align-items: center; justify-content: center;
            position: relative; border: 1px solid rgba(255,255,255,0.06);
        }
        .preview-content img { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px; transition: transform 0.3s; }
        .preview-frame { width: 100%; height: 75vh; border: none; border-radius: 8px; background: white; }
        .img-toolbar {
            position: absolute; bottom: 24px; left: 50%; transform: translateX(-50%);
            background: rgba(0,0,0,0.75); backdrop-filter: blur(10px); padding: 12px 24px;
            border-radius: 40px; display: flex; gap: 8px; align-items: center;
        }
        .img-toolbar button {
            width: 44px; height: 44px; border-radius: 50%; border: none; background: rgba(255,255,255,0.15);
            color: white; cursor: pointer; transition: all 0.2s;
        }
        .img-toolbar button:hover { background: rgba(255,255,255,0.25); transform: scale(1.05); }
        .loading { text-align: center; padding: 60px; color: #94a3b8; }
        .spinner {
            border: 4px solid #334155; border-top: 4px solid #3b82f6; border-radius: 50%;
            width: 50px; height: 50px; animation: spin 0.8s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .excel-preview { overflow-x: auto; background: #fff; padding: 20px; border-radius: 8px; color: #1e293b; }
        .excel-preview table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .excel-preview th { background: #f1f5f9; padding: 10px 12px; border: 1px solid #e2e8f0; font-weight: 600; text-align: left; }
        .excel-preview td { padding: 8px 12px; border: 1px solid #e2e8f0; }
        .excel-preview tr:hover { background: #f8fafc; }
        .doc-preview-inner { padding: 24px; max-width: 800px; margin: 0 auto; background: #fff; border-radius: 8px; color: #1e293b; }
        .txt-preview { background: #1e293b; padding: 24px; border-radius: 8px; color: #e2e8f0; font-family: 'Consolas', monospace; white-space: pre-wrap; max-height: 70vh; overflow: auto; }
    </style>
</head>
<body>
    <div class="preview-container">
        <div class="preview-header">
            <div class="file-info">
                <div class="file-icon">
                    <?php 
                        $icon = "fa-file";
                        if($ext == 'pdf') $icon = "fa-file-pdf";
                        elseif(in_array($ext, ['doc', 'docx'])) $icon = "fa-file-word";
                        elseif(in_array($ext, ['xls', 'xlsx', 'csv'])) $icon = "fa-file-excel";
                        elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $icon = "fa-file-image";
                        elseif(in_array($ext, ['txt'])) $icon = "fa-file-lines";
                    ?>
                    <i class="fa-solid <?= $icon ?>"></i>
                </div>
                <div>
                    <h2><?= htmlspecialchars($file['original_name']) ?></h2>
                    <p>DÃ©posÃ© par <?= htmlspecialchars($file['username']) ?> â€¢ <?= round($file['size']/1024, 1) ?> Ko</p>
                </div>
            </div>
            <div class="preview-actions">
                <button type="button" onclick="toggleFullscreen()" class="btn-sm" style="background:rgba(255,255,255,0.1); color:white; border:1px solid rgba(255,255,255,0.2);">
                    <i class="fa-solid fa-expand"></i> Plein Ã©cran
                </button>
                <?php if ($can_download): ?>
                <a href="<?= $download_path ?>" class="btn-primary" download="<?= $file['original_name'] ?>">
                    <i class="fa-solid fa-download"></i> TÃ©lÃ©charger
                </a>
                <?php endif; ?>
                <button onclick="window.close()" class="btn-sm" style="background:rgba(239,68,68,0.2); color:#fca5a5; border:none;">
                    <i class="fa-solid fa-xmark"></i> Fermer
                </button>
            </div>
        </div>

        <div class="preview-content" id="previewContent">
            <div class="loading">
                <div class="spinner"></div>
                <p>Chargement de l'aperÃ§u...</p>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js"></script>

    <script>
        const fileExt = '<?= $ext ?>';
        const filePath = '<?= $file_path ?>';
        const downloadPath = '<?= $download_path ?>';
        const fileName = '<?= addslashes($file['original_name']) ?>';
        const canDownload = <?= $can_download ? 'true' : 'false' ?>;
        const contentDiv = document.getElementById('previewContent');

        let imgZoom = 1, imgRotate = 0;
        function updateImgTransform() {
            const img = document.getElementById('previewImg');
            if (img) img.style.transform = `scale(${imgZoom}) rotate(${imgRotate}deg)`;
        }
        function toggleFullscreen() {
            const el = document.querySelector('.preview-content');
            if (!document.fullscreenElement) el.requestFullscreen?.();
            else document.exitFullscreen?.();
        }

        function renderPreview() {
            // Images
            if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(fileExt)) {
                contentDiv.innerHTML = `
                    <img id="previewImg" src="${filePath}" alt="${fileName}">
                    <div class="img-toolbar">
                        <button onclick="imgZoom=Math.min(2,imgZoom+0.25);updateImgTransform()" title="Zoom +"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                        <button onclick="imgZoom=Math.max(0.5,imgZoom-0.25);updateImgTransform()" title="Zoom -"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                        <button onclick="imgRotate+=90;updateImgTransform()" title="Rotation"><i class="fa-solid fa-rotate-right"></i></button>
                        <button onclick="imgZoom=1;imgRotate=0;updateImgTransform()" title="RÃ©initialiser"><i class="fa-solid fa-rotate-left"></i></button>
                        <button onclick="toggleFullscreen()" title="Plein Ã©cran"><i class="fa-solid fa-expand"></i></button>
                    </div>`;
            }
            // PDF
            else if (fileExt === 'pdf') {
                contentDiv.innerHTML = `<iframe src="${filePath}" class="preview-frame" id="previewFrame"></iframe>`;
            }
            // Fichiers texte
            else if (fileExt === 'txt') {
                fetch(filePath)
                    .then(r => r.text())
                    .then(text => {
                        contentDiv.innerHTML = `<pre class="txt-preview">${escapeHtml(text)}</pre>`;
                    })
                    .catch(err => showError('Erreur de chargement du fichier texte'));
            }
            // Excel / CSV
            else if (['xlsx', 'xls', 'csv'].includes(fileExt)) {
                loadExcelPreview();
            }
            // Word (DOCX)
            else if (fileExt === 'docx') {
                loadWordPreview();
            }
            // Autres formats
            else {
                contentDiv.innerHTML = `
                    <div style="text-align: center; padding: 50px;">
                        <i class="fa-solid fa-eye-slash fa-3x" style="color: #cbd5e1; margin-bottom: 20px;"></i>
                        <h3>AperÃ§u non disponible pour ce format</h3>
                        <p style="color: #64748b;">Extension actuelle: <strong>.${fileExt || 'inconnue'}</strong></p>
                        <p style="color: #64748b;">TÃ©lÃ©chargez le fichier pour le consulter.</p>
                        ${canDownload ? `<a href="${downloadPath}" class="btn-primary" download="${fileName}" style="margin-top: 20px; display: inline-block;">
                            <i class="fa-solid fa-download"></i> TÃ©lÃ©charger
                        </a>` : `<span class="btn-sm" style="margin-top: 20px; display: inline-block; background:#e2e8f0; color:#64748b;"><i class="fa-solid fa-lock"></i> Téléchargement interdit</span>`}
                    </div>
                `;
            }
        }

        // PrÃ©visualisation Excel
        function loadExcelPreview() {
            fetch(filePath)
                .then(response => response.arrayBuffer())
                .then(data => {
                    const workbook = XLSX.read(data, {type: 'array'});
                    const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                    const html = XLSX.utils.sheet_to_html(firstSheet, {header: 1});
                    
                    contentDiv.innerHTML = `
                        <div class="excel-preview">
                            <div style="margin-bottom: 15px; padding: 10px; background: #f1f5f9; border-radius: 8px;">
                                <strong>Feuille :</strong> ${workbook.SheetNames[0]}
                                ${workbook.SheetNames.length > 1 ? ` (${workbook.SheetNames.length} feuilles au total)` : ''}
                            </div>
                            ${html}
                        </div>
                    `;
                })
                .catch(err => showError('Erreur lors de la lecture du fichier Excel'));
        }

        // PrÃ©visualisation Word (DOCX)
        function loadWordPreview() {
            fetch(filePath)
                .then(response => response.arrayBuffer())
                .then(arrayBuffer => {
                    mammoth.convertToHtml({arrayBuffer: arrayBuffer})
                        .then(result => {
                            contentDiv.innerHTML = `<div class="doc-preview-inner">${result.value}</div>`;
                        })
                        .catch(err => showError('Erreur de conversion du document Word'));
                })
                .catch(err => showError('Erreur de chargement du fichier Word'));
        }

        function showError(message) {
            contentDiv.innerHTML = `
                <div style="text-align: center; padding: 50px; color: #ef4444;">
                    <i class="fa-solid fa-circle-exclamation fa-3x" style="margin-bottom: 20px;"></i>
                    <h3>${message}</h3>
                </div>
            `;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Lancer la prÃ©visualisation
        renderPreview();
    </script>
</body>
</html>
