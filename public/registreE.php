<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// --- 1. TRAITEMENT MODIFICATION ---
$msg_update = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_mail_id'])) {
    if (in_array($_SESSION['role'], ['admin', 'secretaire', 'secretaria'])) {
        try {
            $sql = "UPDATE mails SET 
                    mail_date = ?, contact_id = ?, object = ?, external_ref = ?, 
                    archive_box = ?, archive_shelf = ? 
                    WHERE id = ?";
            $stmt_upd = $pdo->prepare($sql);
            $stmt_upd->execute([
                $_POST['mail_date'], $_POST['contact_id'], $_POST['object'], 
                $_POST['external_ref'], $_POST['archive_box'], $_POST['archive_shelf'], 
                $_POST['edit_mail_id']
            ]);
            $msg_update = "<div class='alert success'>Modification enregistrée !</div>";
        } catch (Exception $e) {
            $msg_update = "<div class='alert error'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

// --- 2. FILTRES ---
$current_tab = $_GET['tab'] ?? 'arrivee'; 
$contacts_list = $pdo->query("SELECT id, name FROM contacts ORDER BY name")->fetchAll();

$sql = "SELECT m.*, c.name as contact_name, f.stored_name, f.original_name, f.id as fid
        FROM mails m
        LEFT JOIN contacts c ON m.contact_id = c.id
        LEFT JOIN files f ON m.file_id = f.id
        WHERE m.type = ? ";

$params = [$current_tab];

if (!empty($_GET['keyword'])) {
    $sql .= " AND (UPPER(m.reference_no) LIKE ? OR UPPER(m.object) LIKE ? OR UPPER(m.external_ref) LIKE ? OR UPPER(f.ocr_content) LIKE ?)";
    $term = "%" . mb_strtoupper($_GET['keyword'], 'UTF-8') . "%";
    $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
}
if (!empty($_GET['contact_id'])) { $sql .= " AND m.contact_id = ?"; $params[] = $_GET['contact_id']; }
if (!empty($_GET['date_start'])) { $sql .= " AND m.mail_date >= ?"; $params[] = $_GET['date_start']; }
if (!empty($_GET['date_end'])) { $sql .= " AND m.mail_date <= ?"; $params[] = $_GET['date_end']; }

$sql .= " ORDER BY m.created_at DESC LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mails = $stmt->fetchAll();

include '../includes/header.php'; 
?>

<style>
    .viewer-modal {
        display: none; position: fixed; z-index: 2000; 
        left: 0; top: 0; width: 100%; height: 100%; 
        background-color: rgba(0,0,0,0.85);
    }
    .viewer-content {
        background-color: #fff; margin: 2vh auto; padding: 0;
        width: 80%; height: 94vh; border-radius: 8px;
        display: flex; flex-direction: column;
    }
    .viewer-header {
        padding: 10px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
        display: flex; justify-content: space-between; align-items: center;
        border-radius: 8px 8px 0 0;
    }
    .viewer-body {
        flex: 1; background: #e2e8f0; overflow: hidden; position: relative; text-align: center;
    }
    .viewer-body iframe, .viewer-body img {
        width: 100%; height: 100%; object-fit: contain; border: none;
    }
    .btn-print-viewer {
        background: #475569; color: white; border: none; padding: 5px 15px;
        border-radius: 4px; cursor: pointer; margin-right: 15px;
    }
    .btn-print-viewer:hover { background: #334155; }
    .close-viewer { font-size: 28px; color: #ef4444; cursor: pointer; }
    .close-viewer:hover { color: #b91c1c; }
</style>

<div class="content-area">
    <?= $msg_update ?>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="margin:0;"><i class="fa-solid fa-book"></i> Registre du Courrier</h1>
        <?php if(in_array($_SESSION['role'], ['admin', 'secretaire', 'secretaria'])): ?>
            <a href="mail_add.php" class="btn-primary"><i class="fa-solid fa-plus"></i> Nouveau</a>
        <?php endif; ?>
    </div>

    <div class="tabs" style="margin-bottom:0; border-bottom:2px solid #e2e8f0;">
        <a href="?tab=arrivee" class="tab-link <?= $current_tab == 'arrivee' ? 'active' : '' ?>" style="padding:10px 20px;display:inline-block;text-decoration:none;font-weight:bold;border-bottom:3px solid <?= $current_tab == 'arrivee'?'#0f172a':'transparent' ?>;color:<?= $current_tab == 'arrivee'?'#0f172a':'#64748b' ?>;">
            <i class="fa-solid fa-arrow-down"></i> ARRIVÉES
        </a>
        <a href="?tab=depart" class="tab-link <?= $current_tab == 'depart' ? 'active' : '' ?>" style="padding:10px 20px;display:inline-block;text-decoration:none;font-weight:bold;border-bottom:3px solid <?= $current_tab == 'depart'?'#0f172a':'transparent' ?>;color:<?= $current_tab == 'depart'?'#0f172a':'#64748b' ?>;">
            <i class="fa-solid fa-arrow-up"></i> DÉPARTS
        </a>
    </div>

    <div class="upload-card" style="margin-top:0; border-top-left-radius:0; border-top-right-radius:0; padding:15px; background:white; border:1px solid #e2e8f0; border-top:none;">
        <form method="GET" style="display:grid; grid-template-columns: 2fr 1.5fr 1fr 1fr auto; gap:10px; align-items:end;">
            <input type="hidden" name="tab" value="<?= $current_tab ?>">
            <div class="form-group" style="margin:0;">
                <label>Mots-clés</label>
                <input type="text" name="keyword" class="form-control" placeholder="Recherche..." value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
            </div>
            <div class="form-group" style="margin:0;">
                <label>Organisme</label>
                <select name="contact_id" class="form-control"><option value="">-- Tous --</option><?php foreach($contacts_list as $c): ?><option value="<?= $c['id'] ?>" <?= (isset($_GET['contact_id']) && $_GET['contact_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-group" style="margin:0;"><label>Début</label><input type="date" name="date_start" class="form-control" value="<?= $_GET['date_start'] ?? '' ?>"></div>
            <div class="form-group" style="margin:0;"><label>Fin</label><input type="date" name="date_end" class="form-control" value="<?= $_GET['date_end'] ?? '' ?>"></div>
            <button type="submit" class="btn-primary" style="height:42px; padding:0 25px; background:#0f172a;"><i class="fa-solid fa-filter"></i></button>
        </form>
    </div>

    <div class="recent-files" style="margin-top:20px;">
        <table class="table">
            <thead>
                <tr>
                    <th>Réf</th><th>Date</th><th>Correspondant</th><th width="40%">Objet</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($mails as $m): 
                    $ext = strtolower(pathinfo($m['stored_name'], PATHINFO_EXTENSION));
                ?>
                <tr>
                    <td><span class="badge" style="background:#e0f2fe; color:#0369a1;"><?= htmlspecialchars($m['reference_no']) ?></span></td>
                    <td><?= date('d/m/Y', strtotime($m['mail_date'])) ?></td>
                    <td><strong><?= htmlspecialchars($m['contact_name']) ?></strong></td>
                    <td><?= htmlspecialchars($m['object']) ?><?php if($m['external_ref']): ?><br><small style="color:#64748b;">(Ext: <?= htmlspecialchars($m['external_ref']) ?>)</small><?php endif; ?></td>
                    <td>
                        <button type="button" class="btn-sm" title="Voir Scan"
                                onclick="openViewer('../uploads/<?= $m['stored_name'] ?>', '<?= $ext ?>', '<?= addslashes($m['reference_no']) ?>')">
                            <i class="fa-solid fa-eye"></i>
                        </button>

                        <?php if(in_array($_SESSION['role'], ['admin', 'secretaire', 'secretaria'])): ?>
                            <button type="button" class="btn-sm" style="background:#f59e0b; color:white; border:none; cursor:pointer;" 
                                    title="Modifier"
                                    onclick="openEditModal('<?= $m['id'] ?>','<?= addslashes($m['reference_no']) ?>','<?= $m['mail_date'] ?>','<?= $m['contact_id'] ?>','<?= addslashes(str_replace(array("\r", "\n"), ' ', $m['object'])) ?>','<?= addslashes($m['external_ref']) ?>','<?= addslashes($m['archive_box']) ?>','<?= addslashes($m['archive_shelf']) ?>')">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        <?php endif; ?>

                        <button type="button" class="btn-sm" style="background:#64748b; color:white; border:none; cursor:pointer;" title="Décharge"
                                onclick="openViewer('print_receipt.php?id=<?= $m['id'] ?>', 'pdf', 'Décharge : <?= addslashes($m['reference_no']) ?>')">
                            <i class="fa-solid fa-print"></i>
                        </button>
                        
                        <button type="button" class="btn-sm" style="background:#334155; color:white; border:none; cursor:pointer;" title="Étiquette"
                                onclick="openViewer('print_label.php?id=<?= $m['id'] ?>', 'pdf', 'Étiquette : <?= addslashes($m['reference_no']) ?>')">
                            <i class="fa-solid fa-tag"></i>
                        </button>

                        <?php if(in_array($_SESSION['role'], ['admin', 'directeur']) && $m['status'] == 'nouveau'): ?>
                            <a href="mail_assign.php?id=<?= $m['id'] ?>" class="btn-sm" style="background:#8b5cf6; color:white;" title="Transmettre"><i class="fa-solid fa-share"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="viewerModal" class="viewer-modal">
    <div class="viewer-content">
        <div class="viewer-header">
            <div>
                <span class="modal-title" style="font-weight:bold; font-size:1.1rem;">
                    <i class="fa-solid fa-file-lines"></i> <span id="viewerTitle" style="color:#2563eb;"></span>
                </span>
            </div>
            <div>
                <button onclick="printViewerContent()" class="btn-print-viewer"><i class="fa-solid fa-print"></i> Imprimer</button>
                <span class="close-viewer" onclick="closeViewer()">&times;</span>
            </div>
        </div>
        <div class="viewer-body" id="viewerBody"></div>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header"><span class="modal-title">Modifier : <span id="editRefDisplay"></span></span><span class="close-btn" onclick="closeEditModal()">&times;</span></div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="edit_mail_id" id="editMailId">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group"><label>Date</label><input type="date" name="mail_date" id="editDate" class="form-control" required></div>
                    <div class="form-group"><label>Correspondant</label><select name="contact_id" id="editContact" class="form-control" required><?php foreach($contacts_list as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-group"><label>Objet</label><textarea name="object" id="editObject" class="form-control" rows="3" required></textarea></div>
                <div class="form-group"><label>Réf. Externe</label><input type="text" name="external_ref" id="editExtRef" class="form-control"></div>
                <div class="form-group" style="background:#fff7ed; padding:10px;"><label style="color:#9a3412;">Archivage</label><div style="display:flex; gap:10px;"><input type="text" name="archive_box" id="editBox" class="form-control" placeholder="Boîte"><input type="text" name="archive_shelf" id="editShelf" class="form-control" placeholder="Rayon"></div></div>
                <div style="text-align:right; margin-top:20px;"><button type="button" onclick="closeEditModal()" class="btn-sm" style="background:#ccc; margin-right:5px;">Annuler</button><button type="submit" class="btn-primary">Enregistrer</button></div>
            </form>
        </div>
    </div>
</div>

<script>
    // --- VISUALISATEUR ---
    function openViewer(url, ext, ref) {
        var modal = document.getElementById('viewerModal');
        var body = document.getElementById('viewerBody');
        document.getElementById('viewerTitle').innerText = ref;
        body.innerHTML = "";

        if (ext === 'pdf') {
            body.innerHTML = '<iframe id="viewerFrame" src="' + url + '" allowfullscreen></iframe>';
        } else if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(ext)) {
            body.innerHTML = '<img id="viewerImage" src="' + url + '" alt="Document">';
        } else {
            body.innerHTML = '<div style="padding:50px; font-size:1.2rem;">Format non affichable.<br><a href="' + url + '" class="btn-primary" target="_blank">Télécharger</a></div>';
        }
        modal.style.display = 'block';
    }

    function closeViewer() {
        document.getElementById('viewerModal').style.display = 'none';
        document.getElementById('viewerBody').innerHTML = "";
    }

    // Fonction pour imprimer le contenu du visualisateur
    function printViewerContent() {
        var frame = document.getElementById('viewerFrame');
        if (frame) {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        } else {
            var img = document.getElementById('viewerImage');
            if (img) {
                var printWindow = window.open('', '_blank');
                printWindow.document.write('<html><head><title>Impression</title></head><body>');
                printWindow.document.write('<img src="' + img.src + '" style="max-width:100%;">');
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.print();
            }
        }
    }

    // --- EDITION ---
    function openEditModal(id, ref, date, contactId, object, extRef, box, shelf) {
        document.getElementById('editMailId').value = id;
        document.getElementById('editRefDisplay').innerText = ref;
        document.getElementById('editDate').value = date;
        document.getElementById('editContact').value = contactId;
        document.getElementById('editObject').value = object;
        document.getElementById('editExtRef').value = extRef;
        document.getElementById('editBox').value = box;
        document.getElementById('editShelf').value = shelf;
        document.getElementById('editModal').style.display = 'block';
    }
    function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }

    window.onclick = function(e) {
        if (e.target == document.getElementById('viewerModal')) closeViewer();
        if (e.target == document.getElementById('editModal')) closeEditModal();
    }
</script>

<?php include '../includes/footer.php'; ?>