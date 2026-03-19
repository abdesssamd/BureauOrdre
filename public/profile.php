<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/password_helper.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }

$user_id = $_SESSION['user_id'];
$message = "";

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) { header('Location: dashboard.php'); exit; }

// Changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    $needsRehash = false;
    if (!verify_password_with_legacy($current, $user['password_hash'], $needsRehash)) {
        $message = "<div class='alert error'>Mot de passe actuel incorrect.</div>";
    } elseif (strlen($new) < 4) {
        $message = "<div class='alert error'>Le nouveau mot de passe doit contenir au moins 4 caractères.</div>";
    } elseif ($new !== $confirm) {
        $message = "<div class='alert error'>Les mots de passe ne correspondent pas.</div>";
    } else {
        $hash = hash_password_secure($new);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user_id]);
        $message = "<div class='alert success'>Mot de passe modifié avec succès.</div>";
        $user['password_hash'] = $hash;
    }
}

// Mise à jour des infos (nom, email si autorisé)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    if (empty($full_name)) {
        $message = "<div class='alert error'>Le nom complet est obligatoire.</div>";
    } else {
        $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?")->execute([$full_name, $user_id]);
        $_SESSION['username'] = $user['username']; // Peut contenir le nom affiché
        $message = "<div class='alert success'>Profil mis à jour.</div>";
        $user['full_name'] = $full_name;
    }
}

include '../includes/header.php';
?>

<div class="content-area">
    <h1><i class="fa-solid fa-user"></i> Mon Profil</h1>
    
    <?= $message ?>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 25px; max-width: 900px;">
        <div class="upload-card">
            <h3 style="margin-top:0;">Informations personnelles</h3>
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-group">
                    <label>Nom complet</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Identifiant</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    <small style="color:#64748b;">Contactez l'administrateur pour modifier l'email.</small>
                </div>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-save"></i> Enregistrer</button>
            </form>
        </div>

        <div class="upload-card">
            <h3 style="margin-top:0;">Changer le mot de passe</h3>
            <form method="POST">
                <input type="hidden" name="change_password" value="1">
                <div class="form-group">
                    <label>Mot de passe actuel</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="new_password" class="form-control" required minlength="4">
                </div>
                <div class="form-group">
                    <label>Confirmer le mot de passe</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn-primary" style="background:#10b981;"><i class="fa-solid fa-key"></i> Modifier le mot de passe</button>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
