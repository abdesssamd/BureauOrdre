<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// Access Control for Secretary
if (isset($_SESSION['role']) && $_SESSION['role'] === 'secretaire') {
    header('Location: dashboard.php');
    exit;
}

// RÃ©cupÃ©ration des listes pour les filtres
$contacts = $pdo->query("SELECT id, name FROM contacts ORDER BY name")->fetchAll();

// Initialisation des variables
$results = [];
$search_performed = false;

// TRAITEMENT DE LA RECHERCHE
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $search_performed = true;
    
    // On construit la requÃªte dynamiquement
    // On joint la table 'files' (alias f) pour accÃ©der au contenu OCR
    $sql = "SELECT m.*, c.name as contact_name, f.stored_name 
            FROM mails m 
            LEFT JOIN contacts c ON m.contact_id = c.id
            JOIN files f ON m.file_id = f.id 
            WHERE 1=1"; 
    
    $params = [];

    // 1. Filtre Mots-clÃ©s (RÃ©f, Objet, RÃ©f Externe, ET CONTENU OCR)
    if (!empty($_GET['keyword'])) {
        // AJOUT DE : OR f.ocr_content LIKE ?
        $sql .= " AND (m.reference_no LIKE ? OR m.object LIKE ? OR m.external_ref LIKE ? OR f.ocr_content LIKE ?)";
        
        $keyword = "%" . $_GET['keyword'] . "%";
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword; // On ajoute le paramÃ¨tre une 4Ã¨me fois pour l'OCR
    }

    // 2. Filtre Type (ArrivÃ©e / DÃ©part)
    if (!empty($_GET['type'])) {
        $sql .= " AND m.type = ?";
        $params[] = $_GET['type'];
    }

    // 3. Filtre Contact (Organisme)
    if (!empty($_GET['contact_id'])) {
        $sql .= " AND m.contact_id = ?";
        $params[] = $_GET['contact_id'];
    }

    // 4. Filtre Date DÃ©but
    if (!empty($_GET['date_start'])) {
        $sql .= " AND m.mail_date >= ?";
        $params[] = $_GET['date_start'];
    }

    // 5. Filtre Date Fin
    if (!empty($_GET['date_end'])) {
        $sql .= " AND m.mail_date <= ?";
        $params[] = $_GET['date_end'];
    }

    // Ordre : Plus rÃ©cent en premier
    $sql .= " ORDER BY m.mail_date DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Recherche AvancÃ©e</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="content-area">
        <h1><i class="fa-solid fa-magnifying-glass"></i> Recherche AvancÃ©e</h1>

        <div class="upload-card" style="max-width: 100%; text-align: left; padding: 20px;">
            <form method="GET">
                <input type="hidden" name="search" value="1">
                
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Mots-clÃ©s (Objet, RÃ©f, Contenu)</label>
                        <input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>" placeholder="Ex: Budget, 001...">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label>Type de flux</label>
                        <select name="type" class="form-control">
                            <option value="">-- Tous --</option>
                            <option value="arrivee" <?= (isset($_GET['type']) && $_GET['type']=='arrivee') ? 'selected' : '' ?>>ArrivÃ©e</option>
                            <option value="depart" <?= (isset($_GET['type']) && $_GET['type']=='depart') ? 'selected' : '' ?>>DÃ©part</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label>Organisme / Contact</label>
                        <select name="contact_id" class="form-control">
                            <option value="">-- Tous --</option>
                            <?php foreach($contacts as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= (isset($_GET['contact_id']) && $_GET['contact_id'] == $c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label>Du (Date dÃ©but)</label>
                        <input type="date" name="date_start" class="form-control" value="<?= $_GET['date_start'] ?? '' ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Au (Date fin)</label>
                        <input type="date" name="date_end" class="form-control" value="<?= $_GET['date_end'] ?? '' ?>">
                    </div>

                    <div class="form-group" style="margin-bottom:0; display:flex; align-items:end;">
                        <button type="submit" class="btn-primary" style="height:42px;">
                            <i class="fa-solid fa-search"></i> Rechercher
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if($search_performed): ?>
            <div class="recent-files" style="margin-top:20px;">
                <h3><i class="fa-solid fa-list"></i> RÃ©sultats (<?= count($results) ?>)</h3>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>RÃ©f. Interne</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Correspondant</th>
                            <th width="40%">Objet</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($results as $r): ?>
                        <tr>
                            <td>
                                <span class="badge" style="background:#e0f2fe; color:#0369a1;">
                                    <?= htmlspecialchars($r['reference_no']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($r['mail_date'])) ?></td>
                            <td>
                                <?php if($r['type'] == 'arrivee'): ?>
                                    <span style="color:#166534;"><i class="fa-solid fa-arrow-down"></i> ArrivÃ©e</span>
                                <?php else: ?>
                                    <span style="color:#b45309;"><i class="fa-solid fa-arrow-up"></i> DÃ©part</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= htmlspecialchars($r['contact_name']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($r['object']) ?>
                                <?php if($r['external_ref']): ?>
                                    <br><small style="color:#64748b;">RÃ©f Ext: <?= htmlspecialchars($r['external_ref']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                              <?php if (!empty($r['stored_name'])): ?>
    <a href="../uploads/<?= $r['stored_name'] ?>" target="_blank" class="btn-sm" title="Voir">
        <i class="fa-solid fa-eye"></i>
    </a>
<?php else: ?>
    <span class="btn-sm" style="background:#ccc; cursor:not-allowed;" title="Fichier introuvable">
        <i class="fa-solid fa-eye-slash"></i>
    </span>
<?php endif; ?>
                                <a href="mail_assign.php?id=<?= $r['id'] ?>" class="btn-sm" style="background:#1b74e4; color:white; border:none;" title="Transmettre"><i class="fa-solid fa-share"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if(count($results) == 0): ?>
                            <tr><td colspan="6" style="text-align:center; padding:30px;">Aucun rÃ©sultat ne correspond Ã  vos critÃ¨res.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
    <?php include '../includes/footer.php'; ?>

