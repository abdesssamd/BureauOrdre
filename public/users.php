<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/password_helper.php';

// 1. Sécurité : Seul un ADMIN peut accéder à cette page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Vérification stricte du rôle
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$message = "";

// 2. Traitement du formulaire d'AJOUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $dept_id = $_POST['department_id'];

    // Vérification email unique
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    
    if ($check->rowCount() > 0) {
        $message = "<div class='alert error'>Cet email est déjà utilisé.</div>";
    } else {
        // Hachage SHA256 (Compatible avec votre système actuel)
        $hashed_pwd = hash_password_secure($password);

        $sql = "INSERT INTO users (username, full_name, email, password_hash, role, department_id, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, 1)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$username, $fullname, $email, $hashed_pwd, $role, $dept_id])) {
            $message = "<div class='alert success'>Utilisateur créé avec succès !</div>";
        } else {
            $message = "<div class='alert error'>Erreur lors de la création.</div>";
        }
    }
}

// 3. Traitement de la SUPPRESSION
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if ($_GET['delete'] != $_SESSION['user_id']) {
        $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $del->execute([$_GET['delete']]);
        $message = "<div class='alert success'>Utilisateur supprimé.</div>";
    } else {
        $message = "<div class='alert error'>Impossible de supprimer votre propre compte.</div>";
    }
}

// 4. Récupération des données pour l'affichage
$depts = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$sql_users = "SELECT users.*, departments.name as dept_name 
              FROM users 
              LEFT JOIN departments ON users.department_id = departments.id 
              ORDER BY users.created_at DESC";
$users_list = $pdo->query($sql_users)->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Utilisateurs - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <style>
        .user-form {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .form-row { display: flex; gap: 15px; margin-bottom: 15px; }
        .form-col { flex: 1; }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../includes/header.php'; ?>

        <div class="content-area">
            <h1><i class="fa-solid fa-users-gear"></i> Gestion des Utilisateurs</h1>
            
            <?= $message ?>

            <div class="user-form">
                <h3 style="margin-top:0; color:var(--primary-color);">Ajouter un collaborateur</h3>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-col">
                            <label>Identifiant</label>
                            <input type="text" name="username" class="form-control" required placeholder="ex: j.dupont">
                        </div>
                        <div class="form-col">
                            <label>Nom Complet</label>
                            <input type="text" name="full_name" class="form-control" required placeholder="ex: Jean Dupont">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-col">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-col">
                            <label>Mot de passe</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <label>Rôle</label>
                            <select name="role" class="form-control">
                                <option value="employe">Employé</option>
                                <option value="chef_service">Chef de Service</option>
                                <option value="secretaire">Secrétaire</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        <div class="form-col">
                            <label>Département</label>
                            <select name="department_id" class="form-control">
                                <?php foreach($depts as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="create_user" class="btn-primary">Créer l'utilisateur</button>
                </form>
            </div>

            <div class="recent-files">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Département</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users_list as $u): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($u['full_name']) ?></strong><br>
                                <span style="font-size:0.8rem; color:#64748b;">@<?= htmlspecialchars($u['username']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php 
                                    $badgeColor = '#64748b'; // Gris par défaut
                                    if($u['role'] == 'admin') $badgeColor = '#ef4444'; // Rouge
                                    if($u['role'] == 'chef_service') $badgeColor = '#f59e0b'; // Orange
                                    if($u['role'] == 'secretaire') $badgeColor = '#3b82f6'; // Bleu
                                   
                                ?>
                                <span class="badge" style="background-color:<?= $badgeColor ?>; color:white;">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($u['dept_name'] ?? 'Aucun') ?></td>
                            <td>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <a href="users.php?delete=<?= $u['id'] ?>" class="btn-sm" style="background:#fee2e2; color:#991b1b; border-color:#fca5a5;" onclick="return confirm('Supprimer cet utilisateur ?');">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
