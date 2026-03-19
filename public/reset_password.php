<?php
require_once '../config/db.php';
require_once '../includes/lang_setup.php';
require_once '../includes/password_helper.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$token = $_GET['token'] ?? '';
$message = '';
$success = false;

if (empty($token)) {
    header('Location: forgot_password.php');
    exit;
}

// Vérifier le token
$stmt = $pdo->prepare("
    SELECT prt.*, u.email 
    FROM password_reset_tokens prt 
    JOIN users u ON prt.user_id = u.id 
    WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()
");
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    $message = "<div class='alert error'>Lien invalide ou expiré. <a href='forgot_password.php'>Redemander un lien</a>.</div>";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (strlen($password) < 4) {
        $message = "<div class='alert error'>Le mot de passe doit contenir au moins 4 caractères.</div>";
    } elseif ($password !== $confirm) {
        $message = "<div class='alert error'>Les mots de passe ne correspondent pas.</div>";
    } else {
        $hash = hash_password_secure($password);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $row['user_id']]);
        $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?")->execute([$row['id']]);
        $message = "<div class='alert success'>Mot de passe modifié ! <a href='index.php'>Se connecter</a>.</div>";
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang_code ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #f1f5f9 0%, #cbd5e1 100%); }
        .reset-card { background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); max-width: 420px; width: 100%; }
    </style>
</head>
<body>
    <div class="reset-card">
        <div style="text-align:center; margin-bottom:1.5rem;">
            <i class="fa-solid fa-lock fa-3x" style="color:#10b981;"></i>
            <h2 style="margin:15px 0 5px;">Nouveau mot de passe</h2>
            <p style="color:#64748b;">Choisissez un nouveau mot de passe.</p>
        </div>
        
        <?= $message ?>
        
        <?php if (!$success && $row): ?>
        <form method="POST">
            <div class="form-group">
                <label>Nouveau mot de passe</label>
                <input type="password" name="password" class="form-control" required minlength="4">
            </div>
            <div class="form-group">
                <label>Confirmer</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn-primary" style="width:100%; background:#10b981;">
                <i class="fa-solid fa-check"></i> Enregistrer
            </button>
        </form>
        <?php endif; ?>
        
        <p style="text-align:center; margin-top:20px;">
            <a href="index.php" style="color:var(--accent-color); text-decoration:none;">
                <i class="fa-solid fa-arrow-left"></i> Retour à la connexion
            </a>
        </p>
    </div>
</body>
</html>
