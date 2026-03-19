<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/notification_helper.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
// Admin, Directeur ou Chef de service peuvent dispatcher
if (!in_array($_SESSION['role'], ['admin', 'directeur', 'chef_service'])) {
    die("<div class='alert error'>⛔ Accès refusé. Seuls l'admin, le directeur et le chef de service peuvent transmettre le courrier.</div>");
}
$mail_id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM mails WHERE id = ?");
$stmt->execute([$mail_id]);
$mail = $stmt->fetch();

if (!$mail) die("Courrier introuvable");

// Listes pour le formulaire
$users = $pdo->query("SELECT id, full_name, role FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
$depts = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

// Traitement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $instruction = $_POST['instruction'];
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    $target_type = $_POST['target_type']; // 'user' ou 'dept'
    
    $assigned_to = ($target_type === 'user') ? $_POST['assigned_to'] : null;
    $assigned_to_dept = ($target_type === 'dept') ? $_POST['assigned_to_dept'] : null;

    // Insertion
    $sql = "INSERT INTO mail_assignments (mail_id, assigned_by, assigned_to, assigned_to_dept, instruction, deadline) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$mail_id, $_SESSION['user_id'], $assigned_to, $assigned_to_dept, $instruction, $deadline]);
    
    // Mise à jour statut courrier
    $pdo->prepare("UPDATE mails SET status = 'en_cours' WHERE id = ?")->execute([$mail_id]);

    $sender_name = $_SESSION['full_name'] ?? 'Direction';
    $deadline_txt = $deadline ? (' - Echeance: ' . date('d/m/Y', strtotime($deadline))) : '';
    $title = "Nouvel ordre de workflow";
    $message = $sender_name . " vous a transmis " . $mail['reference_no'] . " (" . $mail['object'] . ") - Instruction: " . $instruction . $deadline_txt;

    if (!empty($assigned_to) && (int)$assigned_to !== (int)$_SESSION['user_id']) {
        createSystemNotification($pdo, (int)$assigned_to, $title, $message, $mail['file_id'] ?? null);
    } elseif (!empty($assigned_to_dept)) {
        $users_stmt = $pdo->prepare("SELECT id FROM users WHERE department_id = ? AND is_active = 1 AND id != ?");
        $users_stmt->execute([(int)$assigned_to_dept, (int)$_SESSION['user_id']]);
        $dept_users = $users_stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($dept_users as $uid) {
            createSystemNotification($pdo, (int)$uid, $title, $message, $mail['file_id'] ?? null);
        }
    }

    header('Location: registre.php?msg=assigned');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Transmettre</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/all.min.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="content-area">
        <h1><i class="fa-solid fa-share-from-square"></i> Transmettre le courrier</h1>

        <div class="upload-card" style="max-width:600px; text-align:left; margin:0 auto;">
            <div style="background:#f1f5f9; padding:15px; border-radius:8px; margin-bottom:20px;">
                <strong>Concerne :</strong> <?= htmlspecialchars($mail['object']) ?> (Réf: <?= $mail['reference_no'] ?>)
            </div>

            <form method="POST">
                
                <div class="form-group">
                    <label>Transmettre à :</label>
                    <div style="margin-bottom:10px;">
                        <input type="radio" name="target_type" value="user" checked onclick="toggleTarget('user')"> Une Personne
                        <input type="radio" name="target_type" value="dept" style="margin-left:20px;" onclick="toggleTarget('dept')"> Un Service/Département
                    </div>

                    <select name="assigned_to" id="selectUser" class="form-control">
                        <?php foreach($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="assigned_to_dept" id="selectDept" class="form-control" style="display:none;">
                        <?php foreach($depts as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Instruction</label>
                    <select name="instruction" class="form-control">
                        <option value="Traitement">Pour traitement</option>
                        <option value="Information">Pour information</option>
                        <option value="Avis">Pour avis</option>
                        <option value="Signature">Pour signature</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Date limite</label>
                    <input type="date" name="deadline" class="form-control">
                </div>

                <button type="submit" class="btn-primary">Envoyer</button>
            </form>
        </div>
    </div>

    <script>
        function toggleTarget(type) {
            if(type === 'user') {
                document.getElementById('selectUser').style.display = 'block';
                document.getElementById('selectDept').style.display = 'none';
            } else {
                document.getElementById('selectUser').style.display = 'none';
                document.getElementById('selectDept').style.display = 'block';
            }
        }
    </script>
    <?php include '../includes/footer.php'; ?>
