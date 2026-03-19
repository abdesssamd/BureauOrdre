<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/mail_helper.php';
require_once '../includes/notification_helper.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
if (!in_array($_SESSION['role'], ['admin', 'secretaire', 'secretaria'])) {
    die("<div class='alert error'>⛔ Accès refusé. Seul le secrétariat peut enregistrer le courrier.</div>");
}

$fiscal_year = getActiveFiscalYear($pdo);
if (!$fiscal_year) { die("<div style='padding:50px; text-align:center;'><h1>⛔ Erreur</h1><p>Aucune année budgétaire ouverte.</p></div>"); }

$contacts = $pdo->query("SELECT id, name FROM contacts ORDER BY name")->fetchAll();
$arrivals = $pdo->query("SELECT id, reference_no, object FROM mails WHERE type='arrivee' ORDER BY id DESC LIMIT 50")->fetchAll();

// Charger modèles d'objets si la table existe
$obj_templates = [];
try {
    $obj_templates = $pdo->query("SELECT label, type FROM mail_object_templates ORDER BY sort_order, label")->fetchAll();
} catch (Exception $e) {}

$message = "";

// TRAITEMENT IMPORT EN MASSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_submit'])) {
    $type = $_POST['type'];
    $mail_date = $_POST['mail_date'];
    $contact_id = !empty($_POST['contact_id']) ? $_POST['contact_id'] : null;
    $archive_box = !empty($_POST['archive_box']) ? $_POST['archive_box'] : null;
    $archive_shelf = !empty($_POST['archive_shelf']) ? $_POST['archive_shelf'] : null;
    
    if (empty($_FILES['documents']['name'][0])) {
        $message = "<div class='alert error'>⛔ Aucun fichier sélectionné.</div>";
    } else {
        $success_count = 0;
        $errors = [];
        
        foreach ($_FILES['documents']['tmp_name'] as $i => $tmp) {
            if ($_FILES['documents']['error'][$i] !== UPLOAD_ERR_OK) continue;
            
            $object = trim($_POST['objects'][$i] ?? $_POST['default_object'] ?? '');
            $external_ref = trim($_POST['external_refs'][$i] ?? $_POST['default_external_ref'] ?? '');
            $resp_contact = !empty($_POST['contacts'][$i]) ? $_POST['contacts'][$i] : $contact_id;
            
            if (empty($object)) {
                $errors[] = "Fichier " . ($i+1) . " : Objet obligatoire.";
                continue;
            }
            
            $ext = strtolower(pathinfo($_FILES['documents']['name'][$i], PATHINFO_EXTENSION));
            if (empty($ext)) $ext = 'jpg';
            $unique_name = bin2hex(random_bytes(16)) . '.' . $ext;
            $target_dir = "../uploads/";
            
            if (!move_uploaded_file($tmp, $target_dir . $unique_name)) {
                $errors[] = "Fichier " . ($i+1) . " : Erreur d'upload.";
                continue;
            }
            
            try {
                $pdo->beginTransaction();
                
                $file_size = $_FILES['documents']['size'][$i];
                $mime = $_FILES['documents']['type'][$i];
                
                $sql_file = "INSERT INTO files (original_name, stored_name, mime_type, size, owner_id, department_id, visibility, file_size, ocr_content, ocr_status) 
                             VALUES (?, ?, ?, ?, ?, ?, 'department', ?, NULL, 'pending')";
                $stmt_f = $pdo->prepare($sql_file);
                $stmt_f->execute([
                    $_FILES['documents']['name'][$i], $unique_name, $mime, $file_size,
                    $_SESSION['user_id'], $_SESSION['department_id'], $file_size
                ]);
                $file_id = $pdo->lastInsertId();
                
                $ref_no = generateMailReference($pdo, $type);
                $response_id = !empty($_POST['response_to']) ? $_POST['response_to'] : null;
                
                $sql_mail = "INSERT INTO mails (file_id, type, reference_no, external_ref, contact_id, object, mail_date, response_to_mail_id, fiscal_year_id, archive_box, archive_shelf) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_m = $pdo->prepare($sql_mail);
                $stmt_m->execute([
                    $file_id, $type, $ref_no, $external_ref, $resp_contact,
                    $object, $mail_date, $response_id, $fiscal_year['id'],
                    $archive_box, $archive_shelf
                ]);
                
                notifyDepartmentUpload($pdo, $file_id, $_SESSION['user_id'], $_SESSION['department_id']);
                $pdo->commit();
                $success_count++;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Fichier " . ($i+1) . " : " . $e->getMessage();
            }
        }
        
        if ($success_count > 0) {
            $msg_text = "✅ $success_count courrier(s) enregistré(s) avec succès.";
            if (!empty($errors)) $msg_text .= "<br><small>" . implode("<br>", $errors) . "</small>";
            $message = "<div class='alert success'>$msg_text</div>";
            $message .= "<script>setTimeout(function(){ window.location.href = 'registre.php?tab=$type'; }, 2500);</script>";
        } else {
            $message = "<div class='alert error'>" . implode("<br>", $errors) . "</div>";
        }
    }
}

include '../includes/header.php';
include '../includes/lang_setup.php';
$t = isset($t) ? $t : (include '../lang/fr.php');
?>

<style>
.bulk-drop { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 40px; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.2s; }
.bulk-drop:hover, .bulk-drop.dragover { border-color: var(--accent-color); background: #eff6ff; }
.bulk-file-list { margin-top: 15px; }
.bulk-file-item { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: white; border-radius: 8px; margin-bottom: 5px; border: 1px solid #e2e8f0; }
.bulk-file-item input { flex: 1; }
.bulk-file-item .obj-input { min-width: 200px; }
.step-badge { display: inline-block; width: 28px; height: 28px; line-height: 26px; text-align: center; border-radius: 50%; background: var(--primary-color); color: white; font-weight: bold; margin-right: 10px; }
</style>

<div class="content-area">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1><i class="fa-solid fa-file-import"></i> Import Courrier en Masse</h1>
        <a href="mail_add.php" class="btn-sm" style="background:#64748b; color:white;"><i class="fa-solid fa-arrow-left"></i> Enregistrement simple</a>
    </div>
    
    <?= $message ?>

    <div class="upload-card" style="max-width: 1100px;">
        <form method="POST" enctype="multipart/form-data" id="bulkForm">
            <input type="hidden" name="bulk_submit" value="1">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
                <div>
                    <label class="step-badge">1</label><strong>Paramètres communs</strong>
                    <div class="form-group" style="margin-top:15px;">
                        <label>Type de flux</label>
                        <select name="type" class="form-control" required>
                            <option value="arrivee">📥 Arrivée</option>
                            <option value="depart">📤 Départ</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date du courrier</label>
                        <input type="date" name="mail_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Correspondant par défaut</label>
                        <select name="contact_id" class="form-control">
                            <option value="">-- Choisir --</option>
                            <?php foreach($contacts as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Objet par défaut (si vide dans la liste)</label>
                        <input type="text" name="default_object" class="form-control" list="objTemplates" placeholder="Ex: Demande de renseignements">
                        <datalist id="objTemplates">
                            <?php foreach($obj_templates as $ot): ?>
                                <option value="<?= htmlspecialchars($ot['label']) ?>">
                            <?php endforeach; ?>
                            <?php 
                            $hist = $pdo->query("SELECT DISTINCT object FROM mails ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_COLUMN);
                            foreach($hist as $h): if(empty($h)) continue; ?>
                                <option value="<?= htmlspecialchars($h) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group" style="background:#fff7ed; padding:12px; border-radius:8px;">
                        <label style="color:#9a3412;"><i class="fa-solid fa-box-archive"></i> Archivage</label>
                        <div style="display:flex; gap:10px; margin-top:8px;">
                            <input type="text" name="archive_box" class="form-control" placeholder="Boîte">
                            <input type="text" name="archive_shelf" class="form-control" placeholder="Rayon">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="step-badge">2</label><strong>Sélection des fichiers</strong>
                    <div class="bulk-drop" id="dropZone" onclick="document.getElementById('fileInput').click();">
                        <i class="fa-solid fa-cloud-arrow-up fa-3x" style="color:var(--accent-color);"></i>
                        <p style="margin:15px 0 5px; font-weight:bold;">Glissez vos fichiers ici ou cliquez</p>
                        <p style="font-size:0.9rem; color:#64748b;">PDF, JPG, PNG (plusieurs fichiers autorisés)</p>
                        <input type="file" id="fileInput" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" style="display:none;">
                    </div>
                    <p id="fileCount" style="margin-top:10px; color:#64748b; font-size:0.9rem;"></p>
                </div>
            </div>

            <div id="fileListSection" style="display:none;">
                <hr style="margin:25px 0;">
                <label class="step-badge">3</label><strong>Objet et correspondant par fichier</strong>
                <p style="font-size:0.9rem; color:#64748b;">Renseignez l'objet pour chaque document. Le correspondant par défaut s'applique si vide.</p>
                <div class="bulk-file-list" id="fileList"></div>
            </div>

            <div style="text-align:right; margin-top:25px;">
                <button type="submit" id="submitBtn" class="btn-primary" disabled style="padding:15px 40px;">
                    <i class="fa-solid fa-check-double"></i> Enregistrer tout
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileList = document.getElementById('fileList');
const fileListSection = document.getElementById('fileListSection');
const fileCount = document.getElementById('fileCount');
const submitBtn = document.getElementById('submitBtn');
const contacts = <?= json_encode(array_map(function($c){ return ['id'=>$c['id'],'name'=>$c['name']]; }, $contacts)) ?>;

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        updateFileList();
    }
});
fileInput.addEventListener('change', updateFileList);

function updateFileList() {
    const files = fileInput.files;
    fileList.innerHTML = '';
    
    if (files.length === 0) {
        fileListSection.style.display = 'none';
        submitBtn.disabled = true;
        fileCount.textContent = '';
        return;
    }
    
    fileListSection.style.display = 'block';
    submitBtn.disabled = false;
    fileCount.textContent = files.length + ' fichier(s) sélectionné(s)';
    
    for (let i = 0; i < files.length; i++) {
        const div = document.createElement('div');
        div.className = 'bulk-file-item';
        div.innerHTML = `
            <span style="min-width:30px; color:#64748b;">${i+1}.</span>
            <span style="min-width:180px; overflow:hidden; text-overflow:ellipsis;" title="${files[i].name}">${files[i].name}</span>
            <input type="text" name="objects[]" class="form-control obj-input" placeholder="Objet *" required list="objTemplates">
            <select name="contacts[]" class="form-control" style="min-width:150px;">
                <option value="">-- Défaut --</option>
                ${contacts.map(c => '<option value="'+c.id+'">'+c.name+'</option>').join('')}
            </select>
        `;
        fileList.appendChild(div);
    }
}
</script>

<?php include '../includes/footer.php'; ?>
