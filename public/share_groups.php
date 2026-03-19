<?php
require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/share_acl.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (in_array($_SESSION['role'] ?? '', ['secretaire', 'secretaria'], true)) {
    header('Location: dashboard.php');
    exit;
}

share_acl_ensure_schema($pdo);

$user_id = (int)$_SESSION['user_id'];
$dept_id = isset($_SESSION['department_id']) ? (int)$_SESSION['department_id'] : null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($name === '') {
        $message = "<div class='alert error'>Nom de groupe requis.</div>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO share_groups (name, description, owner_id, department_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description ?: null, $user_id, $dept_id]);
        $group_id = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO share_group_members (group_id, user_id, role) VALUES (?, ?, 'manager')")->execute([$group_id, $user_id]);
        $message = "<div class='alert success'>Groupe créé.</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $group_id = (int)($_POST['group_id'] ?? 0);
    $member_id = (int)($_POST['member_id'] ?? 0);
    if ($group_id > 0 && $member_id > 0) {
        $check = $pdo->prepare(
            "SELECT 1 FROM share_groups g
             LEFT JOIN share_group_members gm ON gm.group_id = g.id AND gm.user_id = ?
             WHERE g.id = ? AND (g.owner_id = ? OR gm.role = 'manager')"
        );
        $check->execute([$user_id, $group_id, $user_id]);
        if ($check->fetchColumn()) {
            $pdo->prepare("INSERT IGNORE INTO share_group_members (group_id, user_id, role) VALUES (?, ?, 'member')")
                ->execute([$group_id, $member_id]);
            $message = "<div class='alert success'>Membre ajouté.</div>";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_member'])) {
    $group_id = (int)($_POST['group_id'] ?? 0);
    $member_id = (int)($_POST['member_id'] ?? 0);
    if ($group_id > 0 && $member_id > 0 && $member_id !== $user_id) {
        $check = $pdo->prepare(
            "SELECT 1 FROM share_groups g
             LEFT JOIN share_group_members gm ON gm.group_id = g.id AND gm.user_id = ?
             WHERE g.id = ? AND (g.owner_id = ? OR gm.role = 'manager')"
        );
        $check->execute([$user_id, $group_id, $user_id]);
        if ($check->fetchColumn()) {
            $pdo->prepare("DELETE FROM share_group_members WHERE group_id = ? AND user_id = ?")->execute([$group_id, $member_id]);
            $message = "<div class='alert success'>Membre retiré.</div>";
        }
    }
}

$users_stmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE is_active = 1 AND department_id = ? ORDER BY full_name");
$users_stmt->execute([$dept_id]);
$users = $users_stmt->fetchAll();

$groups_stmt = $pdo->prepare(
    "SELECT g.*, COUNT(gm.user_id) AS members_count
     FROM share_groups g
     LEFT JOIN share_group_members gm ON gm.group_id = g.id
     LEFT JOIN share_group_members me ON me.group_id = g.id AND me.user_id = ?
     WHERE g.department_id = ? OR g.owner_id = ? OR me.user_id IS NOT NULL
     GROUP BY g.id
     ORDER BY g.name"
);
$groups_stmt->execute([$user_id, $dept_id, $user_id]);
$groups = $groups_stmt->fetchAll();

$members_by_group = [];
foreach ($groups as $g) {
    $m = $pdo->prepare(
        "SELECT gm.user_id, gm.role, u.full_name, u.username
         FROM share_group_members gm
         JOIN users u ON u.id = gm.user_id
         WHERE gm.group_id = ?
         ORDER BY gm.role DESC, u.full_name"
    );
    $m->execute([(int)$g['id']]);
    $members_by_group[(int)$g['id']] = $m->fetchAll();
}

include '../includes/header.php';
?>

<div class="content-area">
    <h1><i class="fa-solid fa-people-group"></i> Groupes de partage</h1>
    <?= $message ?>

    <div class="recent-files" style="margin-bottom:20px;">
        <h3 style="margin-top:0;">Créer un groupe</h3>
        <form method="POST" style="display:grid; grid-template-columns:2fr 3fr auto; gap:10px; align-items:end;">
            <div>
                <label>Nom</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div>
                <label>Description</label>
                <input type="text" name="description" class="form-control">
            </div>
            <button type="submit" name="create_group" class="btn-primary">Créer</button>
        </form>
    </div>

    <?php foreach ($groups as $g): ?>
    <div class="recent-files" style="margin-bottom:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0;"><?= htmlspecialchars($g['name']) ?></h3>
            <span class="badge"><?= (int)$g['members_count'] ?> membre(s)</span>
        </div>
        <?php if (!empty($g['description'])): ?><p style="color:#64748b;"><?= htmlspecialchars($g['description']) ?></p><?php endif; ?>

        <table class="table">
            <thead><tr><th>Nom</th><th>Rôle</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach (($members_by_group[(int)$g['id']] ?? []) as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['full_name']) ?> <small style="color:#64748b;">(@<?= htmlspecialchars($m['username']) ?>)</small></td>
                    <td><span class="badge"><?= $m['role'] === 'manager' ? 'Gestionnaire' : 'Membre' ?></span></td>
                    <td>
                        <?php if ((int)$m['user_id'] !== $user_id): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
                            <input type="hidden" name="member_id" value="<?= (int)$m['user_id'] ?>">
                            <button type="submit" name="remove_member" class="btn-sm" style="background:#fee2e2; color:#b91c1c;">Retirer</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <form method="POST" style="display:flex; gap:8px; align-items:end; margin-top:12px;">
            <input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>">
            <div style="flex:1;">
                <label>Ajouter un collègue</label>
                <select name="member_id" class="form-control" required>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (@<?= htmlspecialchars($u['username']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" name="add_member" class="btn-primary">Ajouter</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<?php include '../includes/footer.php'; ?>

