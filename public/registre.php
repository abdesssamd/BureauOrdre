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
            $msg_update = "<div class='alert success'>Modification enregistrÃ©e !</div>";
        } catch (Exception $e) {
            $msg_update = "<div class='alert error'>Erreur : " . $e->getMessage() . "</div>";
        }
    }
}

// --- 2. FILTRES ---
$current_tab = $_GET['tab'] ?? 'arrivee'; 
$contacts_list = $pdo->query("SELECT id, name FROM contacts ORDER BY name")->fetchAll();

// --- PAGINATION ---
$items_per_page = 20; 
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Compte total
$sql_count = "SELECT COUNT(*) as total FROM mails m 
              LEFT JOIN files f ON m.file_id = f.id 
              WHERE m.type = ? ";
$params_count = [$current_tab];

if (!empty($_GET['keyword'])) {
    $sql_count .= " AND (UPPER(m.reference_no) LIKE ? OR UPPER(m.object) LIKE ? OR UPPER(m.external_ref) LIKE ? OR UPPER(f.ocr_content) LIKE ?)";
    $term = "%" . mb_strtoupper($_GET['keyword'], 'UTF-8') . "%";
    array_push($params_count, $term, $term, $term, $term);
}
if (!empty($_GET['contact_id'])) { $sql_count .= " AND m.contact_id = ?"; $params_count[] = $_GET['contact_id']; }
if (!empty($_GET['date_start'])) { $sql_count .= " AND m.mail_date >= ?"; $params_count[] = $_GET['date_start']; }
if (!empty($_GET['date_end'])) { $sql_count .= " AND m.mail_date <= ?"; $params_count[] = $_GET['date_end']; }

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params_count);
$total_items = $stmt_count->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);

// RequÃªte principale
$sql = "SELECT m.*, c.name as contact_name, f.stored_name, f.original_name, f.id as fid
        FROM mails m
        LEFT JOIN contacts c ON m.contact_id = c.id
        LEFT JOIN files f ON m.file_id = f.id
        WHERE m.type = ? ";
$params = [$current_tab];

if (!empty($_GET['keyword'])) {
    $sql .= " AND (UPPER(m.reference_no) LIKE ? OR UPPER(m.object) LIKE ? OR UPPER(m.external_ref) LIKE ? OR UPPER(f.ocr_content) LIKE ?)";
    $term = "%" . mb_strtoupper($_GET['keyword'], 'UTF-8') . "%";
    array_push($params, $term, $term, $term, $term);
}
if (!empty($_GET['contact_id'])) { $sql .= " AND m.contact_id = ?"; $params[] = $_GET['contact_id']; }
if (!empty($_GET['date_start'])) { $sql .= " AND m.mail_date >= ?"; $params[] = $_GET['date_start']; }
if (!empty($_GET['date_end'])) { $sql .= " AND m.mail_date <= ?"; $params[] = $_GET['date_end']; }

$sql .= " ORDER BY m.created_at DESC LIMIT " . intval($items_per_page) . " OFFSET " . intval($offset);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mails = $stmt->fetchAll();

include '../includes/header.php'; 
?>

<style>
    /* Modal Viewer */
    .viewer-modal {
        display: none; position: fixed; z-index: 2000; 
        left: 0; top: 0; width: 100%; height: 100%; 
        background-color: rgba(0,0,0,0.92);
        backdrop-filter: blur(4px);
        animation: fadeIn 0.2s ease;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    
    .viewer-content {
        background-color: #fff; margin: 1vh auto; padding: 0;
        width: 95%; max-width: 1400px; height: 98vh; border-radius: 12px;
        display: flex; flex-direction: column;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    }
    
    .viewer-header {
        padding: 15px 25px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        color: white; border-bottom: 1px solid rgba(255,255,255,0.1);
        display: flex; justify-content: space-between; align-items: center;
        border-radius: 12px 12px 0 0;
    }
    .viewer-header .modal-title { font-weight: 600; font-size: 1.1rem; display: flex; align-items: center; gap: 10px; }
    
    .viewer-controls { display: flex; gap: 10px; align-items: center; }
    .viewer-btn {
        background: rgba(255,255,255,0.15); color: white; border: none;
        padding: 8px 16px; border-radius: 6px; cursor: pointer;
        font-size: 0.9rem; transition: all 0.2s; display: flex; align-items: center; gap: 6px;
    }
    .viewer-btn:hover { background: rgba(255,255,255,0.25); transform: translateY(-1px); }
    
    .close-viewer {
        width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;
        background: rgba(239,68,68,0.2); color: #ef4444; border-radius: 50%;
        cursor: pointer; transition: all 0.2s; font-size: 20px;
    }
    .close-viewer:hover { background: #ef4444; color: white; transform: rotate(90deg); }
    
    .viewer-body {
        flex: 1; background: #1e293b; overflow: hidden; position: relative;
        display: flex; align-items: center; justify-content: center;
    }
    
    /* Conteneur interne pour l'image/PDF (Nouveau pour corriger le bug) */
    #viewerContainer { width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; }
    
    #viewerContainer iframe { width: 100%; height: 100%; border: none; background: white; }
    #viewerContainer img {
        max-width: 100%; max-height: 100%; object-fit: contain;
        border-radius: 4px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        transition: transform 0.3s ease;
    }
    #viewerContainer img.zoomed { transform: scale(1.5); cursor: zoom-out; }
    #viewerContainer img:not(.zoomed) { cursor: zoom-in; }
    
    /* ContrÃ´les flottants */
    .image-controls {
        position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%);
        background: rgba(0,0,0,0.7); backdrop-filter: blur(10px);
        padding: 10px 20px; border-radius: 30px;
        display: none; gap: 10px; align-items: center; z-index: 10;
    }
    .image-controls button {
        background: rgba(255,255,255,0.2); color: white; border: none;
        width: 40px; height: 40px; border-radius: 50%;
        cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center;
    }
    .image-controls button:hover { background: rgba(255,255,255,0.3); transform: scale(1.1); }
    
    /* Pagination */
    .pagination { display: flex; justify-content: center; align-items: center; gap: 8px; margin: 25px 0; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
    .pagination-info { color: #64748b; font-size: 0.9rem; margin-right: 15px; }
    .pagination-btn { padding: 8px 16px; border: 1px solid #e2e8f0; background: white; color: #475569; border-radius: 6px; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; }
    .pagination-btn:hover:not(:disabled) { background: #0f172a; color: white; border-color: #0f172a; }
    .pagination-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .pagination-number { padding: 8px 12px; border: 1px solid #e2e8f0; background: white; color: #475569; border-radius: 6px; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; min-width: 40px; text-align: center; }
    .pagination-number:hover { background: #f1f5f9; border-color: #cbd5e1; }
    .pagination-number.active { background: #0f172a; color: white; border-color: #0f172a; font-weight: 600; }
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
            <i class="fa-solid fa-arrow-down"></i> ARRIVÃ‰ES
        </a>
        <a href="?tab=depart" class="tab-link <?= $current_tab == 'depart' ? 'active' : '' ?>" style="padding:10px 20px;display:inline-block;text-decoration:none;font-weight:bold;border-bottom:3px solid <?= $current_tab == 'depart'?'#0f172a':'transparent' ?>;color:<?= $current_tab == 'depart'?'#0f172a':'#64748b' ?>;">
            <i class="fa-solid fa-arrow-up"></i> DÃ‰PARTS
        </a>
    </div>

    <div class="upload-card" style="margin-top:0; border-top-left-radius:0; border-top-right-radius:0; padding:15px; background:white; border:1px solid #e2e8f0; border-top:none;">
        <form method="GET" style="display:grid; grid-template-columns: 2fr 1.5fr 1fr 1fr auto; gap:10px; align-items:end;">
            <input type="hidden" name="tab" value="<?= $current_tab ?>">
            <input type="hidden" name="page" value="1">
            <div class="form-group" style="margin:0;"><label>Mots-clÃ©s</label><input type="text" name="keyword" class="form-control" placeholder="Recherche..." value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>"></div>
            <div class="form-group" style="margin:0;"><label>Organisme</label><select name="contact_id" class="form-control"><option value="">-- Tous --</option><?php foreach($contacts_list as $c): ?><option value="<?= $c['id'] ?>" <?= (isset($_GET['contact_id']) && $_GET['contact_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group" style="margin:0;"><label>DÃ©but</label><input type="date" name="date_start" class="form-control" value="<?= $_GET['date_start'] ?? '' ?>"></div>
            <div class="form-group" style="margin:0;"><label>Fin</label><input type="date" name="date_end" class="form-control" value="<?= $_GET['date_end'] ?? '' ?>"></div>
            <button type="submit" class="btn-primary" style="height:42px; padding:0 25px; background:#0f172a;"><i class="fa-solid fa-filter"></i></button>
        </form>
    </div>

    <div class="recent-files" style="margin-top:20px;">
        <table class="table">
            <thead><tr><th>RÃ©f</th><th>Date</th><th>Correspondant</th><th width="40%">Objet</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach($mails as $m): $ext = strtolower(pathinfo($m['stored_name'], PATHINFO_EXTENSION)); ?>
                <tr>
                    <td><span class="badge" style="background:#e0f2fe; color:#0369a1;"><?= htmlspecialchars($m['reference_no']) ?></span></td>
                    <td><?= date('d/m/Y', strtotime($m['mail_date'])) ?></td>
                    <td><strong><?= htmlspecialchars($m['contact_name']) ?></strong></td>
                    <td><?= htmlspecialchars($m['object']) ?><?php if($m['external_ref']): ?><br><small style="color:#64748b;">(Ext: <?= htmlspecialchars($m['external_ref']) ?>)</small><?php endif; ?></td>
                    <td>
                        <?php if (!empty($m['stored_name'])): ?>
                        <button type="button" class="btn-sm" title="Voir Scan" onclick="openViewer('../uploads/<?= $m['stored_name'] ?>', '<?= $ext ?>', '<?= addslashes($m['reference_no']) ?>')"><i class="fa-solid fa-eye"></i></button>
                        <?php else: ?>
                        <span class="badge" style="background:#e2e8f0; color:#475569;" title="Ordre sans document">Sans doc</span>
                        <?php endif; ?>
                        <?php if(in_array($_SESSION['role'], ['admin', 'secretaire', 'secretaria'])): ?>
                            <button type="button" class="btn-sm" style="background:#f59e0b; color:white; border:none; cursor:pointer;" title="Modifier" onclick="openEditModal('<?= $m['id'] ?>','<?= addslashes($m['reference_no']) ?>','<?= $m['mail_date'] ?>','<?= $m['contact_id'] ?>','<?= addslashes(str_replace(array("\r", "\n"), ' ', $m['object'])) ?>','<?= addslashes($m['external_ref']) ?>','<?= addslashes($m['archive_box']) ?>','<?= addslashes($m['archive_shelf']) ?>')"><i class="fa-solid fa-pen"></i></button>
                        <?php endif; ?>
                        <button type="button" class="btn-sm" style="background:#64748b; color:white; border:none; cursor:pointer;" title="DÃ©charge" onclick="openViewer('print_receipt.php?id=<?= $m['id'] ?>', 'pdf', 'DÃ©charge : <?= addslashes($m['reference_no']) ?>')"><i class="fa-solid fa-print"></i></button>
                        <button type="button" class="btn-sm" style="background:#334155; color:white; border:none; cursor:pointer;" title="Ã‰tiquette" onclick="openViewer('print_label.php?id=<?= $m['id'] ?>', 'pdf', 'Ã‰tiquette : <?= addslashes($m['reference_no']) ?>')"><i class="fa-solid fa-tag"></i></button>
                        <a href="mail_history.php?id=<?= $m['id'] ?>" class="btn-sm" style="background:#1b74e4; color:white;" title="Historique transmissions"><i class="fa-solid fa-route"></i></a>
                        <?php if(in_array($_SESSION['role'], ['admin', 'directeur', 'chef_service']) && ($m['status'] ?? 'nouveau') == 'nouveau'): ?>
                            <a href="mail_assign.php?id=<?= $m['id'] ?>" class="btn-sm" style="background:#1b74e4; color:white;" title="Transmettre"><i class="fa-solid fa-share"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <span class="pagination-info">Affichage <?= $offset + 1 ?>-<?= min($offset + $items_per_page, $total_items) ?> sur <?= $total_items ?></span>
            <button class="pagination-btn" onclick="goToPage(1)" <?= $current_page == 1 ? 'disabled' : '' ?>><i class="fa-solid fa-angles-left"></i></button>
            <button class="pagination-btn" onclick="goToPage(<?= $current_page - 1 ?>)" <?= $current_page == 1 ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-left"></i></button>
            <?php 
            $start = max(1, $current_page - 2); 
            $end = min($total_pages, $current_page + 2);
            for ($i = $start; $i <= $end; $i++): ?>
                <span class="pagination-number <?= $i == $current_page ? 'active' : '' ?>" onclick="goToPage(<?= $i ?>)"><?= $i ?></span>
            <?php endfor; ?>
            <button class="pagination-btn" onclick="goToPage(<?= $current_page + 1 ?>)" <?= $current_page >= $total_pages ? 'disabled' : '' ?>><i class="fa-solid fa-chevron-right"></i></button>
            <button class="pagination-btn" onclick="goToPage(<?= $total_pages ?>)" <?= $current_page >= $total_pages ? 'disabled' : '' ?>><i class="fa-solid fa-angles-right"></i></button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="viewerModal" class="viewer-modal">
    <div class="viewer-content">
        <div class="viewer-header">
            <div><span class="modal-title"><i class="fa-solid fa-file-lines"></i> <span id="viewerTitle"></span></span></div>
            <div class="viewer-controls">
                <button onclick="printViewerContent()" class="viewer-btn"><i class="fa-solid fa-print"></i> Imprimer</button>
                <button onclick="downloadViewerContent()" class="viewer-btn" id="downloadBtn" style="display:none;"><i class="fa-solid fa-download"></i> TÃ©lÃ©charger</button>
                <span class="close-viewer" onclick="closeViewer()" title="Fermer">&times;</span>
            </div>
        </div>
        <div class="viewer-body">
            <div id="viewerContainer"></div>
            
            <div id="imageControls" class="image-controls" style="display:none;">
                <button onclick="zoomImage()" title="Zoom"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                <button onclick="rotateImage()" title="Rotation"><i class="fa-solid fa-rotate-right"></i></button>
                <button onclick="resetImage()" title="RÃ©initialiser"><i class="fa-solid fa-rotate-left"></i></button>
                <button onclick="fullscreenImage()" title="Plein Ã©cran"><i class="fa-solid fa-expand"></i></button>
            </div>
        </div>
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
                <div class="form-group"><label>RÃ©f. Externe</label><input type="text" name="external_ref" id="editExtRef" class="form-control"></div>
                <div class="form-group" style="background:#fff7ed; padding:10px;"><label style="color:#9a3412;">Archivage</label><div style="display:flex; gap:10px;"><input type="text" name="archive_box" id="editBox" class="form-control" placeholder="BoÃ®te"><input type="text" name="archive_shelf" id="editShelf" class="form-control" placeholder="Rayon"></div></div>
                <div style="text-align:right; margin-top:20px;"><button type="button" onclick="closeEditModal()" class="btn-sm" style="background:#ccc; margin-right:5px;">Annuler</button><button type="submit" class="btn-primary">Enregistrer</button></div>
            </form>
        </div>
    </div>
</div>

<script>
    let currentViewerUrl = '';
    let imageRotation = 0;
    let isImageZoomed = false;
    
    // --- VISUALISATEUR CORRIGÃ‰ ---
    function openViewer(url, ext, ref) {
        var modal = document.getElementById('viewerModal');
        var container = document.getElementById('viewerContainer'); // On cible le conteneur, pas le body entier
        var imageControls = document.getElementById('imageControls');
        var downloadBtn = document.getElementById('downloadBtn');
        
        document.getElementById('viewerTitle').innerText = ref;
        currentViewerUrl = url;
        imageRotation = 0;
        isImageZoomed = false;
        
        // Vider seulement le conteneur de contenu
        container.innerHTML = "";
        
        // RÃ©initialiser l'affichage des contrÃ´les
        imageControls.style.display = 'none';
        downloadBtn.style.display = 'none';

        if (ext === 'pdf') {
            // Iframe pour PDF
            var iframe = document.createElement('iframe');
            iframe.id = 'viewerFrame';
            iframe.src = url + '?t=' + Date.now();
            iframe.setAttribute('allowfullscreen', 'true');
            container.appendChild(iframe);
            downloadBtn.style.display = 'inline-flex';
        } else if (['jpg', 'jpeg', 'png', 'gif', 'bmp'].includes(ext)) {
            // Image
            var img = document.createElement('img');
            img.id = 'viewerImage';
            img.src = url + '?t=' + Date.now();
            img.alt = 'Document';
            img.onload = function() { resetImageTransform(); };
            img.onerror = function() { container.innerHTML = '<div style="color:white;text-align:center;padding:50px;">Erreur de chargement.</div>'; };
            container.appendChild(img);
            imageControls.style.display = 'flex'; // On affiche les boutons qui existent toujours
            downloadBtn.style.display = 'inline-flex';
        } else {
            // Autres
            container.innerHTML = '<div style="padding:50px;font-size:1.2rem;color:white;text-align:center;">Format non affichable.<br><a href="' + url + '" class="btn-primary" target="_blank" style="margin-top:20px;">TÃ©lÃ©charger</a></div>';
            downloadBtn.style.display = 'inline-flex';
        }
        
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeViewer() {
        var modal = document.getElementById('viewerModal');
        var container = document.getElementById('viewerContainer');
        
        if (modal) modal.style.display = 'none';
        document.body.style.overflow = '';
        if (container) container.innerHTML = ""; // Vider le conteneur pour arrÃªter PDF/Video
    }

    // ContrÃ´les Image
    function zoomImage() {
        var img = document.getElementById('viewerImage');
        if (!img) return;
        if (isImageZoomed) { img.classList.remove('zoomed'); isImageZoomed = false; }
        else { img.classList.add('zoomed'); isImageZoomed = true; }
    }
    function rotateImage() {
        var img = document.getElementById('viewerImage');
        if (!img) return;
        imageRotation += 90; if (imageRotation >= 360) imageRotation = 0;
        img.style.transform = 'rotate(' + imageRotation + 'deg)';
    }
    function resetImage() {
        imageRotation = 0; isImageZoomed = false; resetImageTransform();
    }
    function resetImageTransform() {
        var img = document.getElementById('viewerImage');
        if (img) { img.style.transform = 'rotate(0deg)'; img.classList.remove('zoomed'); }
    }
    function fullscreenImage() {
        var img = document.getElementById('viewerImage');
        if (img && img.requestFullscreen) img.requestFullscreen();
    }

    function downloadViewerContent() {
        if (currentViewerUrl) {
            var a = document.createElement('a'); a.href = currentViewerUrl; a.download = ''; a.target = '_blank';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
        }
    }

    function printViewerContent() {
        var frame = document.getElementById('viewerFrame');
        if (frame) {
            frame.contentWindow.focus(); frame.contentWindow.print();
        } else {
            var img = document.getElementById('viewerImage');
            if (img) {
                var w = window.open('', '_blank');
                w.document.write('<html><head><title>Print</title><style>body{text-align:center;}img{max-width:100%;}</style></head><body><img src="' + img.src + '"></body></html>');
                w.document.close(); w.print();
            }
        }
    }

    function goToPage(page) {
        var url = new URL(window.location.href); url.searchParams.set('page', page); window.location.href = url.toString();
    }

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
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeViewer(); });
</script>

<?php include '../includes/footer.php'; ?>
