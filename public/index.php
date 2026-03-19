<?php
require_once '../config/db.php';
require_once '../includes/password_helper.php';
// 1. Initialiser la langue MANUELLEMENT car pas de header.php ici
require_once '../includes/lang_setup.php'; 

// Si déjà connecté
if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (Votre logique de connexion existante ne change pas) ...
    // ... Juste s'assurer de garder votre code de vérif password ...
    $email = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT users.*, departments.name as dept_name FROM users LEFT JOIN departments ON users.department_id = departments.id WHERE username = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $needsRehash = false;
    if ($user && verify_password_with_legacy($password, $user['password_hash'], $needsRehash)) {
        if ($needsRehash) {
            $newHash = hash_password_secure($password);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$newHash, $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role']; 
        $_SESSION['department_id'] = $user['department_id'];
        $_SESSION['department_name'] = $user['dept_name'] ?? 'Aucun Service';
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'department_id' => $user['department_id'],
        ];
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Email ou mot de passe incorrect.";
    }
}
?>
<!doctype html>
<html lang="<?= $lang_code ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $t['login_title'] ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <style>
        body { display: flex; align-items: center; justify-content: center; height: 100vh; background: linear-gradient(135deg, #f1f5f9 0%, #cbd5e1 100%); }
        .login-card { background: white; padding: 3rem; border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); width: 100%; max-width: 400px; text-align: center; }
        .input-group { margin-bottom: 1.5rem; text-align: <?= $dir == 'rtl' ? 'right' : 'left' ?>; } /* Alignement dynamique */
        .input-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        /* Sélecteur de langue sur la page de login */
        .lang-switch { position: absolute; top: 20px; right: 20px; font-weight: bold; }
        .lang-switch a { text-decoration: none; color: #0f172a; margin: 0 5px; }
    </style>
</head>
<body>
    
    <div class="lang-switch">
        <a href="?lang=fr">FR</a> | <a href="?lang=ar">عربي</a>
    </div>

    <div class="login-card">
        <div class="login-header">
            <i class="fa-solid fa-building-columns fa-3x" style="color:#0f172a; margin-bottom:1rem;"></i>
            <h2><?= $t['login_title'] ?></h2>
            <p style="color: #64748b;"><?= $t['login_subtitle'] ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="input-group">
                <label><?= $t['email'] ?></label>
                <input type="text" name="username" class="form-control" placeholder="admin@admin.com" required>
            </div>
            
            <div class="input-group">
                <label><?= $t['password'] ?></label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn-primary">
                <?= $t['connect_btn'] ?> <i class="fa-solid <?= $dir=='rtl'?'fa-arrow-left':'fa-arrow-right' ?>"></i>
            </button>
            <p style="margin-top:15px; text-align:center;">
                <a href="forgot_password.php" style="font-size:0.9rem; color:var(--accent-color);">Mot de passe oublié ?</a>
            </p>
        </form>
    </div>
</body>
</html>
