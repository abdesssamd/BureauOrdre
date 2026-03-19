<?php
require_once '../config/db.php';
require_once '../includes/lang_setup.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$step = isset($_GET['step']) ? $_GET['step'] : 'request';

// Étape 1 : Demande (email)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $message = "<div class='alert error'>Veuillez saisir votre email.</div>";
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            try {
                $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
                    ->execute([$user['id'], $token, $expires]);
                
                $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
                    . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;
                
                // En production : envoyer l'email
                // mail($email, "Réinitialisation mot de passe", "Cliquez : $reset_link");
                
                $message = "<div class='alert success'>Un lien de réinitialisation a été généré.<br><strong>Lien (pour test) :</strong><br><a href='reset_password.php?token=$token' style='word-break:break-all;'>$reset_link</a><br><small>En production, ce lien sera envoyé par email.</small></div>";
            } catch (Exception $e) {
                $message = "<div class='alert error'>Erreur. La table password_reset_tokens existe-t-elle ? Exécutez migration_documents_ux.sql</div>";
            }
        } else {
            $message = "<div class='alert error'>Aucun compte trouvé avec cet email.</div>";
        }
    }
}

// Lien vers la page de connexion
$login_url = 'index.php';
?>
<!DOCTYPE html>
<html lang="<?= $lang_code ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #f1f5f9 0%, #cbd5e1 100%); }
        .forgot-card { background: white; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); max-width: 420px; width: 100%; }
    </style>
</head>
<body>
    <div class="forgot-card">
        <div style="text-align:center; margin-bottom:1.5rem;">
            <i class="fa-solid fa-key fa-3x" style="color:var(--accent-color);"></i>
            <h2 style="margin:15px 0 5px;">Mot de passe oublié</h2>
            <p style="color:#64748b;">Saisissez votre email pour recevoir un lien de réinitialisation.</p>
        </div>
        
        <?= $message ?>
        
        <form method="POST">
            <input type="hidden" name="request_reset" value="1">
            <div class="form-group">
                <label>Email professionnel</label>
                <input type="email" name="email" class="form-control" required placeholder="votre@email.com">
            </div>
            <button type="submit" class="btn-primary" style="width:100%;">
                <i class="fa-solid fa-paper-plane"></i> Envoyer le lien
            </button>
        </form>
        
        <p style="text-align:center; margin-top:20px;">
            <a href="<?= $login_url ?>" style="color:var(--accent-color); text-decoration:none;">
                <i class="fa-solid fa-arrow-left"></i> Retour à la connexion
            </a>
        </p>
    </div>
</body>
</html>
