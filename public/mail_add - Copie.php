<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/mail_helper.php';
require_once '../includes/notification_helper.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
// Restriction Rôle : Seul Admin ou Secrétaire peut scanner
if (!in_array($_SESSION['role'], ['admin', 'secretaire'])) {
    die("<div class='alert error'>⛔ Accès refusé. Seul le secrétariat peut enregistrer le courrier.</div>");
}
// 1. Vérifications et Données
$fiscal_year = getActiveFiscalYear($pdo);
if (!$fiscal_year) {
    die("<div style='padding:50px; text-align:center;'><h1>⛔ Erreur</h1><p>Aucune année budgétaire ouverte.</p></div>");
}

$contacts = $pdo->query("SELECT id, name FROM contacts ORDER BY name")->fetchAll();
$arrivals = $pdo->query("SELECT id, reference_no, object FROM mails WHERE type='arrivee' ORDER BY id DESC LIMIT 50")->fetchAll();

// Récupérer les 100 derniers objets uniques pour l'autocomplétion
$obj_history = $pdo->query("SELECT DISTINCT object FROM mails ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
$message = "";

// 2. Traitement du Formulaire
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["document"])) {
    $file = $_FILES["document"];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if(empty($ext)) { $ext = 'jpg'; } // Extension par défaut pour le scan
    
    $unique_name = bin2hex(random_bytes(16)) . '.' . $ext;
    $target_dir = "../uploads/";
    
    if (move_uploaded_file($file['tmp_name'], $target_dir . $unique_name)) {
        try {
            $pdo->beginTransaction();

            $sql_file = "INSERT INTO files (original_name, stored_name, mime_type, size, owner_id, department_id, visibility, file_size) VALUES (?, ?, ?, ?, ?, ?, 'department', ?)";
            $stmt = $pdo->prepare($sql_file);
            $stmt->execute([$file['name'], $unique_name, $file['type'], $file['size'], $_SESSION['user_id'], $_SESSION['department_id'], $file['size']]);
            $file_id = $pdo->lastInsertId();

            $type = $_POST['type'];
            $ref_no = generateMailReference($pdo, $type);
            if($ref_no === "ERREUR_ANNEE") throw new Exception("Année close.");
$sql_mail = "INSERT INTO mails (file_id, type, reference_no, external_ref, contact_id, object, mail_date, response_to_mail_id, fiscal_year_id, archive_box, archive_shelf) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $contact_id = !empty($_POST['contact_id']) ? $_POST['contact_id'] : null;
            $response_id = !empty($_POST['response_to']) ? $_POST['response_to'] : null;
            
            // On récupère les valeurs (ou NULL si vide)
            $archive_box = !empty($_POST['archive_box']) ? $_POST['archive_box'] : null;
            $archive_shelf = !empty($_POST['archive_shelf']) ? $_POST['archive_shelf'] : null;

            $stmt_mail = $pdo->prepare($sql_mail);
            $stmt_mail->execute([
                $file_id, 
                $type, 
                $ref_no, 
                $_POST['external_ref'], 
                $contact_id, 
                $_POST['object'], 
                $_POST['mail_date'], 
                $response_id, 
                $fiscal_year['id'],
                $archive_box,   // Nouveau champ
                $archive_shelf  // Nouveau champ
            ]);
            notifyDepartmentUpload($pdo, $file_id, $_SESSION['user_id'], $_SESSION['department_id']);
            $pdo->commit();
            $message = "<div class='alert success'>Enregistré : <strong>$ref_no</strong></div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert error'>Erreur : " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert error'>Erreur upload ou aucun fichier scanné.</div>";
    }
}

// 3. Affichage
include '../includes/header.php'; 
?>

<script src="https://cdn.asprise.com/scannerjs/scanner.js" type="text/javascript"></script>

<script>
    // --- FONCTIONS DE SCAN ---
    
    function scanToJpg() {
        console.log("Lancement du scan JPG...");
        scanner.scan(displayImagesOnPage, {
            "output_settings": [ { "type": "return-base64", "format": "jpg" } ]
        });
    }

    function scanToPdf() {
        console.log("Lancement du scan PDF...");
        scanner.scan(displayImagesOnPage, {
            "use_asprise_dialog": false,
            "output_settings": [ { "type": "return-base64", "format": "pdf" } ]
        });
    }

    // Cette fonction reçoit le résultat du scan
    function displayImagesOnPage(successful, mesg, response) {
        if (!successful) { 
            console.error('Erreur scan:', mesg); 
            alert("Erreur: " + mesg);
            return; 
        }

        if (successful && response && response.output_settings) {
            var scannedData = response.output_settings[0].value;
            var format = response.output_settings[0].format;
            var previewDiv = document.getElementById('scanPreview');
            
            // 1. Afficher l'aperçu
            if(format === 'jpg') {
                previewDiv.innerHTML = '<img src="data:image/jpeg;base64,' + scannedData + '" style="max-height:150px; border:2px solid #10b981;">';
                document.getElementById('fileName').innerHTML = '<i class="fa-solid fa-check"></i> Image reçue !';
            } else {
                previewDiv.innerHTML = '<div class="alert success">PDF Scanné reçu !</div>';
                document.getElementById('fileName').innerHTML = '<i class="fa-solid fa-check"></i> PDF reçu !';
            }

            // 2. Convertir en fichier pour le formulaire PHP
            // C'est l'étape magique qui permet au bouton "Enregistrer" de marcher
            const mime = (format === 'pdf' ? 'application/pdf' : 'image/jpeg');
            const blob = base64ToBlob(scannedData, mime);
            const file = new File([blob], "scan_" + new Date().getTime() + "." + format, { type: mime });
            
            // Injecter dans l'input file caché
            const container = new DataTransfer();
            container.items.add(file);
            document.getElementById('fileInput').files = container.files;
            
            console.log("Fichier injecté dans le formulaire !");
        }
    }

    // Utilitaire technique
    function base64ToBlob(base64, mime) {
        const byteCharacters = atob(base64);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        return new Blob([byteArray], {type: mime});
    }

    // UI : Basculer les champs
    function toggleResponseField() {
        const type = document.getElementById('mailType').value;
        const field = document.getElementById('responseField');
        const label = document.getElementById('corrLabel');
        if (type === 'depart') {
            field.style.display = 'block';
            label.innerText = 'Destinataire (Organisme)';
        } else {
            field.style.display = 'none';
            label.innerText = 'Expéditeur (Organisme)';
        }
    }
</script>

    <h1><i class="fa-solid fa-stamp"></i> Enregistrement Courrier (<?= $fiscal_year['year'] ?>)</h1>
    <?= $message ?>

    <div class="upload-card" style="max-width: 900px; text-align: left; margin: 0 auto;">
        <form method="POST" enctype="multipart/form-data" id="mailForm">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div>
                    <div class="form-group">
                        <label>Type de flux</label>
                        <select name="type" class="form-control" id="mailType" onchange="toggleResponseField()">
                            <option value="arrivee">📥 Arrivée (Reçu)</option>
                            <option value="depart">📤 Départ (Envoyé)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date du courrier</label>
                        <input type="date" name="mail_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label id="corrLabel">Expéditeur (Organisme)</label>
                        <div style="display:flex; gap:10px;">
                            <select name="contact_id" class="form-control" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach($contacts as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <a href="contacts.php" target="_blank" class="btn-sm" style="background:#e0f2fe; color:#0284c7; width:40px; display:flex; align-items:center; justify-content:center;">
                                <i class="fa-solid fa-plus"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div>
                   <div class="form-group">
						<label><?= $t['object'] ?></label>
						<input type="text" name="object" class="form-control" list="objets_precedents" required autocomplete="off">
						
						<datalist id="objets_precedents">
							<?php foreach($obj_history as $obj): ?>
								<option value="<?= htmlspecialchars($obj) ?>">
							<?php endforeach; ?>
						</datalist>
					</div>
                    <div class="form-group">
                        <label>Réf. Externe</label>
                        <input type="text" name="external_ref" class="form-control" placeholder="N° origine...">
                    </div>
                    <div class="form-group" id="responseField" style="display:none;">
                        <label>Réponse au courrier N° :</label>
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
                <label>Numérisation</label>
                
                <div style="display:flex; gap:10px; margin-bottom:15px;">
                    <button type="button" class="btn-sm" style="background:#0f172a; color:white; border:none; cursor:pointer;" onclick="scanToJpg()">
                        <i class="fa-solid fa-image"></i> Scan Rapide
                    </button>
                    <button type="button" class="btn-sm" style="background:#3b82f6; color:white; border:none; cursor:pointer;" onclick="scanToPdf()">
                        <i class="fa-solid fa-copy"></i> Scan Multiple
                    </button>
                </div>

                <div id="scanPreview" style="margin-bottom:10px;"></div>

                <div class="drop-zone" onclick="document.getElementById('fileInput').click();" style="padding: 20px;">
                    <i class="fa-solid fa-file-pdf fa-2x" style="color:var(--primary-color);"></i><br>
                    <span id="fileName" style="font-weight:bold; margin-top:10px; display:block;">Ou cliquez pour choisir un fichier</span>
                    <input type="file" id="fileInput" name="document" style="display:none;" onchange="document.getElementById('fileName').innerText = this.files[0].name; document.getElementById('fileName').style.color='green';">
                </div>
            </div>
				<div class="form-group" style="margin-top: 20px; background: #fff7ed; padding: 15px; border-radius: 8px; border: 1px solid #ffedd5;">
						<label style="color:#9a3412; font-weight:bold;"><i class="fa-solid fa-box-archive"></i> <?= $t['archive_loc'] ?></label>
						<div style="display:flex; gap:10px; margin-top:10px;">
							<div style="flex:1;">
								<input type="text" name="archive_box" class="form-control" placeholder="<?= $t['box_num'] ?> (Ex: B-2025-01)">
							</div>
							<div style="flex:1;">
								<input type="text" name="archive_shelf" class="form-control" placeholder="<?= $t['shelf_num'] ?> (Ex: Armoire 3)">
							</div>
					</div>
				</div>
            <div style="text-align:right; margin-top:20px;">
                <button type="submit" class="btn-primary" style="width:auto; padding-left:40px; padding-right:40px;">
                    <i class="fa-solid fa-save"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>

<?php include '../includes/footer.php'; ?>