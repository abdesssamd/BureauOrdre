<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_login();
include '../includes/header.php';

$file_id = (int)($_GET['file_id'] ?? 0);
$current_user_id = (int)($_SESSION['user_id'] ?? 0);

// Vérifier que l'utilisateur est propriétaire du fichier
$stmt = $pdo->prepare("SELECT * FROM files WHERE id = ? AND owner_id = ?");
$stmt->execute([$file_id, $current_user_id]);
$file = $stmt->fetch();

if (!$file) {
    echo "<p>Fichier introuvable ou non autorisé.</p>";
    include '../includes/footer.php';
    exit;
}

// Liste des utilisateurs et départements
$users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();

$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    $deptId = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;

    if (!$userId && !$deptId) {
        $message = "Veuillez choisir au moins un utilisateur ou un service.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO file_shares (file_id, shared_with_user_id, shared_with_department_id)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$file_id, $userId, $deptId]);

        log_action($pdo, $current_user_id, 'share', 'Fichier: '.$file['original_name']);
        $message = "Fichier partagé avec succès.";
    }
}
?>

<h2>Partager le fichier : <?= htmlspecialchars($file['original_name']) ?></h2>

<?php if ($message): ?>
    <p><?= htmlspecialchars($message) ?></p>
<?php endif; ?>

<form method="post">
    <label>Partager avec un utilisateur :
        <select name="user_id">
            <option value="">-- Aucun --</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>Partager avec un service :
        <select name="department_id">
            <option value="">-- Aucun --</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <button type="submit">Partager</button>
</form>

<?php include '../includes/footer.php'; ?>
