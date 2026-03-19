<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

// Sécurité : Seuls Admin et Secrétaire peuvent modifier
if (!in_array($_SESSION['role'], ['admin', 'secretaire'])) {
    die("<div class='alert error'>⛔ Accès refusé.</div>");
}

$id = $_GET['id'] ?? 0;
$message = "";

// 1. Récupération des données actuelles
$stmt = $pdo->prepare("SELECT * FROM mails WHERE id = ?");
$stmt->execute([$id]);
$mail = $stmt->fetch();

if (!$mail) die("Courrier introuvable.");

// 2. Traitement de la Modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = "UPDATE mails SET 
                mail_date = ?, 
                contact_id = ?, 
                object = ?, 
                external_ref = ?, 
                archive_box = ?, 
                archive_shelf = ? 
                WHERE id = ?";
        
        $stmt_upd = $pdo->prepare($sql);
        $stmt_upd->execute([
            $_POST['mail_date'],
            $_POST['contact_id'],
            $_POST['object'],
            $_POST['external_ref'],
            $_POST['archive_box'],
            $_POST['archive_shelf'],
            $id
        ]);

        $message = "<div class='alert success'>Modification enregistrée avec succès ! <a href='registre.php'>Retour au registre</a></div>";
        
        // Rafraîchir les données
        $stmt->execute([$id]);
        $mail = $stmt->fetch();

    } catch (Exception $e) {
        $message = "<div class='alert error'>Erreur : " . $e->getMessage() . "</div>";
    }
}

// Listes pour le formulaire
$contacts = $pdo->query("SELECT id, name FROM contacts ORDER BY name")->fetchAll();

include '../includes/header.php';
?>

<div class="content-area">
    <h1><i class="fa-solid fa-pen-to-square"></i> Modifier le courrier (Réf: <?= htmlspecialchars($mail['reference_no']) ?>)</h1>
    
    <?= $message ?>

    <div class="upload-card" style="max-width: 800px; text-align: left; margin: 0 auto;">
        <form method="POST">
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <div class="form-group">
                        <label>Date du courrier</label>
                        <input type="date" name="mail_date" class="form-control" value="<?= $mail['mail_date'] ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Correspondant (Expéditeur/Destinataire)</label>
                        <select name="contact_id" class="form-control" required>
                            <?php foreach($contacts as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($c['id'] == $mail['contact_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="form-group">
                        <label>Objet</label>
                        <textarea name="object" class="form-control" rows="4" required><?= htmlspecialchars($mail['object']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Réf. Externe (Origine)</label>
                        <input type="text" name="external_ref" class="form-control" value="<?= htmlspecialchars($mail['external_ref']) ?>">
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top: 20px; background: #fff7ed; padding: 15px; border-radius: 8px; border: 1px solid #ffedd5;">
                <label style="color:#9a3412; font-weight:bold;"><i class="fa-solid fa-box-archive"></i> Localisation Physique</label>
                <div style="display:flex; gap:10px; margin-top:10px;">
                    <div style="flex:1;">
                        <label style="font-size:0.8rem;">Boîte</label>
                        <input type="text" name="archive_box" class="form-control" value="<?= htmlspecialchars($mail['archive_box']) ?>">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:0.8rem;">Rayon/Armoire</label>
                        <input type="text" name="archive_shelf" class="form-control" value="<?= htmlspecialchars($mail['archive_shelf']) ?>">
                    </div>
                </div>
            </div>

            <div style="text-align:right; margin-top:20px;">
                <a href="registre.php" class="btn-sm" style="background:#64748b; color:white; text-decoration:none; margin-right:10px;">Annuler</a>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-save"></i> Enregistrer les modifications</button>
            </div>

        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>