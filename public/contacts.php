<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$message = "";

// 1. Traitement AJOUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_contact'])) {
    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? 'administration';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? ''; 

    if (!empty($name)) {
        $sql = "INSERT INTO contacts (name, type, email, phone, address) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$name, $type, $email, $phone, $address])) {
            $message = "<div class='alert success'>Contact ajouté.</div>";
        } else {
            $message = "<div class='alert error'>Erreur lors de l'ajout.</div>";
        }
    } else {
        $message = "<div class='alert error'>Le nom est obligatoire.</div>";
    }
}

// 2. Traitement SUPPRESSION
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM contacts WHERE id = ?")->execute([$id]);
    header('Location: contacts.php'); exit;
}

// 3. Récupération LISTE
$contacts = $pdo->query("SELECT * FROM contacts ORDER BY name")->fetchAll();

// --- AFFICHAGE ---
// Le header ouvre déjà la <div class="content-area">
include '../includes/header.php'; 
?>

<h1><i class="fa-solid fa-address-book"></i> Carnet d'Adresses</h1>
    
    <?= $message ?>

    <div style="display:flex; gap:20px; flex-wrap:wrap;">
        
        <div class="upload-card" style="flex:1; text-align:left; height:fit-content; min-width: 300px;">
            <h3 style="margin-top:0;">Nouveau Contact</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Nom de l'organisme <span style="color:red">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="Ex: Wilaya de Tlemcen">
                </div>
                
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="administration">Administration Publique</option>
                        <option value="entreprise">Entreprise Privée</option>
                        <option value="personne">Particulier</option>
                    </select>
                </div>

                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Téléphone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label>Adresse Postale</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>

                <button type="submit" name="add_contact" class="btn-primary">
                    <i class="fa-solid fa-plus"></i> Ajouter
                </button>
            </form>
        </div>

        <div class="recent-files" style="flex:2; min-width: 300px;">
            <table class="table">
                <thead>
                    <tr><th>Nom</th><th>Coordonnées</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach($contacts as $c): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($c['name']) ?></strong><br>
                            <span class="badge" style="background:#f1f5f9; color:#64748b; font-size:0.75rem;">
                                <?= ucfirst($c['type']) ?>
                            </span>
                        </td>
                        <td style="font-size:0.9rem; color:#475569;">
                            <?php if($c['email']): ?>
                                <div><i class="fa-solid fa-envelope" style="width:20px;"></i> <?= htmlspecialchars($c['email']) ?></div>
                            <?php endif; ?>
                            <?php if($c['phone']): ?>
                                <div><i class="fa-solid fa-phone" style="width:20px;"></i> <?= htmlspecialchars($c['phone']) ?></div>
                            <?php endif; ?>
                            <?php if($c['address']): ?>
                                <div style="font-size:0.8rem; margin-top:2px;"><i class="fa-solid fa-location-dot" style="width:20px;"></i> <?= htmlspecialchars($c['address']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="contacts.php?delete=<?= $c['id'] ?>" class="btn-sm" style="color:#ef4444; background:#fee2e2; border:none;" onclick="return confirm('Supprimer ?')">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

<?php include '../includes/footer.php'; ?>