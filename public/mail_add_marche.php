<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/mail_helper.php';
require_once '../includes/notification_helper.php';

// 1. VÃ©rification SÃ©curitÃ©
if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// Restriction RÃ´le : Seul Admin ou SecrÃ©taire peut scanner
if (!in_array($_SESSION['role'], ['admin', 'secretaire'])) {
    die("<div class='alert error'>â›” AccÃ¨s refusÃ©. Seul le secrÃ©tariat peut enregistrer le courrier.</div>");
}

// --- 1-BIS. TRAITEMENT AJAX (AJOUT CONTACT RAPIDE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_contact'])) {
    ini_set('display_errors', '0');
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=UTF-8');
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'administration';
    
    if (empty($name)) { echo json_encode(['success' => false, 'message' => 'Le nom est obligatoire.']); exit; }

    try {
        $check = $pdo->prepare("SELECT id, name FROM contacts WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
        $check->execute([$name]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            echo json_encode([
                'success' => true,
                'id' => $existing['id'],
                'name' => htmlspecialchars($existing['name']),
                'already_exists' => true
            ]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO contacts (name, type) VALUES (?, ?)");
        $stmt->execute([$name, $type]);
        echo json_encode([
            'success' => true,
            'id' => $pdo->lastInsertId(),
            'name' => htmlspecialchars($name),
            'already_exists' => false
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur SQL: ' . $e->getMessage()]);
    }
    exit;
}

// 2. Chargement DonnÃ©es
$fiscal_year = getActiveFiscalYear($pdo);
if (!$fiscal_year) { die("<div style='padding:50px; text-align:center;'><h1>â›” Erreur</h1><p>Aucune annÃ©e budgÃ©taire ouverte.</p></div>"); }

$contacts = $pdo->query("SELECT id, name FROM contacts ORDER BY name")->fetchAll();
$arrivals = $pdo->query("SELECT id, reference_no, object FROM mails WHERE type='arrivee' ORDER BY id DESC LIMIT 50")->fetchAll();
$obj_history = $pdo->query("SELECT DISTINCT object FROM mails ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);

$message = "";

// 3. TRAITEMENT DU FORMULAIRE
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['ajax_add_contact'])) {
    
    if (empty($_FILES) || !isset($_FILES["document"])) {
         $message = "<div class='alert error'>â›” <strong>Erreur Critique :</strong> Le serveur n'a reÃ§u aucun fichier.</div>";
    } 
    elseif ($_FILES["document"]["error"] !== UPLOAD_ERR_OK) {
        $code = $_FILES["document"]["error"];
        $err_msg = "";
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE: $err_msg = "Le fichier dÃ©passe la limite upload_max_filesize."; break;
            case UPLOAD_ERR_FORM_SIZE: $err_msg = "Le fichier dÃ©passe la limite MAX_FILE_SIZE."; break;
            case UPLOAD_ERR_PARTIAL: $err_msg = "L'envoi a Ã©tÃ© interrompu."; break;
            case UPLOAD_ERR_NO_FILE: $err_msg = "Aucun fichier sÃ©lectionnÃ©."; break;
            default: $err_msg = "Erreur technique (Code: $code)";
        }
        $message = "<div class='alert error'>â›” <strong>Erreur Upload :</strong> $err_msg</div>";
    }
    else {
        $file = $_FILES["document"];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if(empty($ext)) { $ext = 'jpg'; }
        
        $unique_name = bin2hex(random_bytes(16)) . '.' . $ext;
        $target_dir = "../uploads/";
        
        if (move_uploaded_file($file['tmp_name'], $target_dir . $unique_name)) {
            try {
                $pdo->beginTransaction();

                $extracted_text = null;
                $ocr_helper = '../includes/ocr_helper.php';
                if (file_exists($ocr_helper)) {
                    require_once $ocr_helper;
                    $extracted_text = extractTextFromImage($target_dir . $unique_name);
                    if (empty($extracted_text) || strlen($extracted_text) < 5) { $extracted_text = null; }
                }

                $sql_file = "INSERT INTO files (original_name, stored_name, mime_type, size, owner_id, department_id, visibility, file_size, ocr_content) 
                             VALUES (?, ?, ?, ?, ?, ?, 'department', ?, ?)";
                $stmt = $pdo->prepare($sql_file);
                $stmt->execute([
                    $file['name'], $unique_name, $file['type'], $file['size'], 
                    $_SESSION['user_id'], $_SESSION['department_id'], $file['size'], $extracted_text
                ]);
                $file_id = $pdo->lastInsertId();

                $type = $_POST['type'];
                $ref_no = generateMailReference($pdo, $type);
                if($ref_no === "ERREUR_ANNEE") throw new Exception("AnnÃ©e close.");

                $sql_mail = "INSERT INTO mails (file_id, type, reference_no, external_ref, contact_id, object, mail_date, response_to_mail_id, fiscal_year_id, archive_box, archive_shelf) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $contact_id = !empty($_POST['contact_id']) ? $_POST['contact_id'] : null;
                $response_id = !empty($_POST['response_to']) ? $_POST['response_to'] : null;
                $archive_box = !empty($_POST['archive_box']) ? $_POST['archive_box'] : null;
                $archive_shelf = !empty($_POST['archive_shelf']) ? $_POST['archive_shelf'] : null;

                $stmt_mail = $pdo->prepare($sql_mail);
                $stmt_mail->execute([
                    $file_id, $type, $ref_no, $_POST['external_ref'], $contact_id, 
                    $_POST['object'], $_POST['mail_date'], $response_id, $fiscal_year['id'],
                    $archive_box, $archive_shelf
                ]);

                notifyDepartmentUpload($pdo, $file_id, $_SESSION['user_id'], $_SESSION['department_id']);
                $pdo->commit();
                $message = "<div class='alert success'>âœ… EnregistrÃ© avec succÃ¨s : <strong>$ref_no</strong></div>";

            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert error'>Erreur Base de DonnÃ©es : " . $e->getMessage() . "</div>";
            }
        } else {
            $message = "<div class='alert error'>â›” Erreur Permission : Impossible de dÃ©placer le fichier.</div>";
        }
    }
}

include '../includes/header.php'; 
?>

<script src="assets/js/scanner.js" type="text/javascript"></script>

<script>
    function scanToJpg() {
        if(typeof scanner === 'undefined') { 
            alert("âŒ scanner.js non chargÃ©"); 
            return; 
        }
        scanner.scan(displayImagesOnPage, { 
            "output_settings": [{ "type": "return-base64", "format": "jpg" }] 
        });
    }

    function scanToPdf() {
        if(typeof scanner === 'undefined') { 
            alert("âŒ scanner.js non chargÃ©"); 
            return; 
        }
        scanner.scan(displayImagesOnPage, { 
            "use_asprise_dialog": false, 
            "output_settings": [{ "type": "return-base64", "format": "pdf" }] 
        });
    }

    function displayImagesOnPage(successful, mesg, response) {
        console.log("=== SCAN RESPONSE ===");
        console.log("successful:", successful);
        console.log("mesg:", mesg);
        console.log("response:", response);
        
        if (!successful) { alert("âŒ " + mesg); return; }
        
        try {
            let scannedData = null;
            let format = 'jpg';
            
            // Parse JSON si string
            if (typeof response === 'string') {
                response = JSON.parse(response);
            }
            
            // Extraction via images[] (format Asprise standard)
            if (response?.images?.[0]?.image_data_base64) {
                scannedData = response.images[0].image_data_base64;
                format = response.images[0].image_format || 'jpg';
                console.log("âœ… Via images.image_data_base64");
            }
            // Via output[]
            else if (response?.output?.[0]?.result?.[0]) {
                scannedData = response.output[0].result[0];
                format = response.output[0].format || 'jpg';
                console.log("âœ… Via output.result");
            }
            // Via output_settings[]
            else if (response?.output_settings?.[0]?.value) {
                scannedData = response.output_settings[0].value;
                format = response.output_settings[0].format || 'jpg';
                console.log("âœ… Via output_settings");
            }
            
            if (!scannedData) {
                console.error("âŒ Pas de donnÃ©es");
                alert("âŒ Pas de donnÃ©es d'image trouvÃ©es");
                return;
            }
            
            // Nettoyage base64 (CRITIQUE)
            scannedData = scannedData.replace(/\s/g, '').replace(/[^A-Za-z0-9+/=]/g, '');
            console.log("Base64 nettoyÃ©:", scannedData.length, "chars");
            
            // Preview
            const previewDiv = document.getElementById('scanPreview');
            const fileNameDiv = document.getElementById('fileName');
            
            if(format === 'jpg' || format === 'jpeg' || format === 'png') {
                previewDiv.innerHTML = '<img src="data:image/' + format + ';base64,' + scannedData + '" style="max-height:150px; border:2px solid #10b981;">';
                fileNameDiv.innerHTML = '<i class="fa-solid fa-check" style="color:#10b981"></i> Image reÃ§ue !';
                fileNameDiv.style.color = '#10b981';
            } else {
                previewDiv.innerHTML = '<div class="alert success">PDF reÃ§u</div>';
                fileNameDiv.innerHTML = '<i class="fa-solid fa-check" style="color:#10b981"></i> PDF reÃ§u !';
                fileNameDiv.style.color = '#10b981';
            }

            // Conversion Blob
            const mime = format === 'pdf' ? 'application/pdf' : 'image/' + format;
            const blob = base64ToBlob(scannedData, mime);
            const file = new File([blob], "scan_" + Date.now() + "." + format, { type: mime });
            
            // Injection
            const container = new DataTransfer();
            container.items.add(file);
            document.getElementById('fileInput').files = container.files;
            
            console.log("âœ…âœ…âœ… SUCCÃˆS:", file.name, (blob.size/1024).toFixed(2) + "KB");
            
        } catch (error) {
            console.error("âŒ ERREUR:", error);
            alert("âŒ " + error.message);
        }
    }

    function base64ToBlob(base64, mime) {
        base64 = base64.replace(/\s/g, '');
        const byteChars = atob(base64);
        const byteNums = new Array(byteChars.length);
        for (let i = 0; i < byteChars.length; i++) { 
            byteNums[i] = byteChars.charCodeAt(i); 
        }
        return new Blob([new Uint8Array(byteNums)], {type: mime});
    }

    function toggleResponseField() {
        const type = document.getElementById('mailType').value;
        const field = document.getElementById('responseField');
        const label = document.getElementById('corrLabel');
        if (type === 'depart') {
            field.style.display = 'block';
            label.innerText = '<?= $t['dest_org'] ?>';
        } else {
            field.style.display = 'none';
            label.innerText = '<?= $t['sender_org'] ?>';
        }
    }
</script>

<h1><i class="fa-solid fa-stamp"></i> <?= $t['mail_registration'] ?> (<?= $fiscal_year['year'] ?>)</h1>
<?= $message ?>

<div class="upload-card" style="max-width: 900px; text-align: left; margin: 0 auto;">
    <form method="POST" enctype="multipart/form-data" id="mailForm">
        
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <div>
                <div class="form-group">
                    <label><?= $t['flow_type'] ?></label>
                    <select name="type" class="form-control" id="mailType" onchange="toggleResponseField()">
                        <option value="arrivee">ðŸ“¥ <?= $t['arrival'] ?></option>
                        <option value="depart">ðŸ“¤ <?= $t['departure'] ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= $t['mail_date'] ?></label>
                    <input type="date" name="mail_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label id="corrLabel"><?= $t['sender_org'] ?></label>
                    <div style="display:flex; gap:10px;">
                        <select name="contact_id" id="contactSelect" class="form-control" required>
                            <option value="">-- Choisir --</option>
                            <?php foreach($contacts as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="openContactModal()" class="btn-sm" style="background:#e0f2fe; color:#0284c7; width:40px;">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div>
                <div class="form-group">
                    <label><?= $t['object'] ?></label>
                    <input type="text" name="object" class="form-control" list="objets_precedents" required>
                    <datalist id="objets_precedents">
                        <?php foreach($obj_history as $obj): ?>
                            <option value="<?= htmlspecialchars($obj) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="form-group">
                    <label><?= $t['ext_ref'] ?></label>
                    <input type="text" name="external_ref" class="form-control">
                </div>
                <div class="form-group" id="responseField" style="display:none;">
                    <label><?= $t['reply_to'] ?></label>
                    <select name="response_to" class="form-control">
                        <option value="">-- Aucun --</option>
                        <?php foreach($arrivals as $arr): ?>
                            <option value="<?= $arr['id'] ?>"><?= $arr['reference_no'] ?> - <?= substr($arr['object'], 0, 20) ?>...</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-top: 30px; border-top: 2px dashed #cbd5e1; padding-top: 20px;">
            <label><?= $t['digitization'] ?></label>
            <div style="display:flex; gap:10px; margin-bottom:15px;">
                <button type="button" class="btn-sm" style="background:#0f172a; color:white;" onclick="scanToJpg()">
                    <i class="fa-solid fa-image"></i> <?= $t['quick_scan'] ?>
                </button>
                <button type="button" class="btn-sm" style="background:#3b82f6; color:white;" onclick="scanToPdf()">
                    <i class="fa-solid fa-copy"></i> <?= $t['multi_scan'] ?>
                </button>
            </div>

            <div id="scanPreview" style="margin-bottom:10px;"></div>

            <div class="drop-zone" onclick="document.getElementById('fileInput').click();" style="padding: 20px;">
                <i class="fa-solid fa-file-pdf fa-2x" style="color:var(--primary-color);"></i><br>
                <span id="fileName" style="font-weight:bold; margin-top:10px; display:block;">Cliquez pour choisir un fichier</span>
                <input type="file" id="fileInput" name="document" style="display:none;" onchange="document.getElementById('fileName').innerText = this.files[0].name; document.getElementById('fileName').style.color='green';">
            </div>
        </div>

        <div class="form-group" style="margin-top: 20px; background: #fff7ed; padding: 15px; border-radius: 8px;">
            <label style="color:#9a3412; font-weight:bold;"><i class="fa-solid fa-box-archive"></i> <?= $t['archive_loc'] ?></label>
            <div style="display:flex; gap:10px; margin-top:10px;">
                <div style="flex:1;">
                    <input type="text" name="archive_box" class="form-control" placeholder="<?= $t['box_num'] ?>">
                </div>
                <div style="flex:1;">
                    <input type="text" name="archive_shelf" class="form-control" placeholder="<?= $t['shelf_num'] ?>">
                </div>
            </div>
        </div>

        <div style="text-align:right; margin-top:20px;">
            <button type="submit" class="btn-primary" style="width:auto; padding:20px 40px;">
                <i class="fa-solid fa-save"></i> <?= $t['save'] ?>
            </button>
        </div>
    </form>
</div>

<div id="contactModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <span class="modal-title"><?= $t['new_contact'] ?></span>
            <span class="close-btn" onclick="closeContactModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="contactMsg"></div>
            <form id="contactForm" onsubmit="submitContact(event)">
                <input type="hidden" name="ajax_add_contact" value="1">
                <div class="form-group">
                    <label><?= $t['org_name'] ?> <span style="color:red">*</span></label>
                    <input type="text" name="name" id="newContactName" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><?= $t['type'] ?></label>
                    <select name="type" class="form-control">
                        <option value="administration">Administration</option>
                        <option value="entreprise">Entreprise</option>
                        <option value="personne">Particulier</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="width:100%;">
                    <i class="fa-solid fa-plus"></i> <?= $t['add'] ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    function openContactModal() {
        document.getElementById('contactModal').style.display = 'block';
        document.getElementById('contactMsg').innerHTML = '';
        document.getElementById('newContactName').focus();
    }
    function closeContactModal() {
        document.getElementById('contactModal').style.display = 'none';
        document.getElementById('contactMsg').innerHTML = '';
    }
    window.onclick = function(e) { 
        if (e.target == document.getElementById('contactModal')) closeContactModal(); 
    }

    function submitContact(e) {
        e.preventDefault();
        const form = document.getElementById('contactForm');
        const formData = new FormData(form);
        const msgDiv = document.getElementById('contactMsg');

        fetch(window.location.pathname, { method: 'POST', body: formData })
        .then(async (r) => {
            const raw = await r.text();
            const text = (raw || '').trim();
            try {
                return JSON.parse(text);
            } catch (parseError) {
                const start = text.indexOf('{');
                const end = text.lastIndexOf('}');
                if (start !== -1 && end !== -1 && end > start) {
                    return JSON.parse(text.slice(start, end + 1));
                }
                throw new Error((text || 'Réponse invalide du serveur.').slice(0, 220));
            }
        })
        .then(data => {
            if (data.success) {
                const select = document.getElementById('contactSelect');
                let opt = select.querySelector('option[value="' + String(data.id) + '"]');
                if (!opt) {
                    opt = document.createElement('option');
                    opt.value = data.id;
                    opt.text = data.name;
                    select.add(opt);
                }
                select.value = String(data.id);
                closeContactModal();
                form.reset();
            } else {
                msgDiv.innerHTML = '<div class="alert error">' + data.message + '</div>';
            }
        })
        .catch(err => { 
            console.error(err); 
            msgDiv.innerHTML = '<div class="alert error">Erreur réseau: serveur inaccessible ou réponse invalide.</div>'; 
        });
    }
</script>

<?php include '../includes/footer.php'; ?>
