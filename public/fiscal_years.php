<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'secretaire', 'secretaria'], true)) {
    header('Location: dashboard.php');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_year'])) {
        $year = (int) ($_POST['year'] ?? 0);

        if ($year < 2000 || $year > 2100) {
            $message = "<div class='alert error'>Année invalide (2000-2100).</div>";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO fiscal_years (year, is_active) VALUES (?, 0)");
                $stmt->execute([$year]);
                $message = "<div class='alert success'>Année budgétaire ajoutée.</div>";
            } catch (Throwable $e) {
                $message = "<div class='alert error'>Cette année existe déjà.</div>";
            }
        }
    }

    if (isset($_POST['activate_year']) && is_numeric($_POST['year_id'] ?? null)) {
        $yearId = (int) $_POST['year_id'];

        try {
            $pdo->beginTransaction();
            $pdo->exec("UPDATE fiscal_years SET is_active = 0");
            $stmt = $pdo->prepare("UPDATE fiscal_years SET is_active = 1 WHERE id = ?");
            $stmt->execute([$yearId]);
            $pdo->commit();
            $message = "<div class='alert success'>Exercice activé avec succès.</div>";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "<div class='alert error'>Erreur lors de l'activation.</div>";
        }
    }
}

$years = $pdo->query("SELECT id, year, is_active, created_at FROM fiscal_years ORDER BY year DESC")->fetchAll();
$currentYear = (int) date('Y');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Années Budgétaires</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <style>
        .panel {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            padding: 1.25rem;
            margin-bottom: 1.25rem;
        }
        .inline-form {
            display: flex;
            gap: 10px;
            align-items: end;
            flex-wrap: wrap;
        }
        .status-badge {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #fff;
        }
        .status-active { background: #16a34a; }
        .status-inactive { background: #64748b; }
    </style>
</head>
<body>
<div class="main-container">
    <?php include '../includes/header.php'; ?>

    <div class="content-area">
        <h1><i class="fa-solid fa-calendar-days"></i> Gestion des Années Budgétaires</h1>

        <?= $message ?>

        <div class="panel">
            <h3 style="margin-top:0;">Ajouter une année</h3>
            <form method="POST" class="inline-form">
                <div>
                    <label>Année</label>
                    <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?= $currentYear ?>" required>
                </div>
                <button type="submit" name="add_year" class="btn-primary">
                    <i class="fa-solid fa-plus"></i> Ajouter
                </button>
            </form>
        </div>

        <div class="recent-files">
            <table class="table">
                <thead>
                    <tr>
                        <th>Année</th>
                        <th>Statut</th>
                        <th>Créée le</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (count($years) === 0): ?>
                    <tr><td colspan="4" style="text-align:center; color:#64748b;">Aucune année budgétaire trouvée.</td></tr>
                <?php else: ?>
                    <?php foreach ($years as $y): ?>
                        <tr>
                            <td><strong><?= (int) $y['year'] ?></strong></td>
                            <td>
                                <?php if ((int) $y['is_active'] === 1): ?>
                                    <span class="status-badge status-active">Ouverte</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">Fermée</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string) $y['created_at']) ?></td>
                            <td>
                                <?php if ((int) $y['is_active'] !== 1): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="year_id" value="<?= (int) $y['id'] ?>">
                                        <button type="submit" name="activate_year" class="btn-sm" style="background:#dcfce7; color:#166534; border-color:#86efac;">
                                            <i class="fa-solid fa-circle-check"></i> Activer
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#16a34a; font-weight:700;">Exercice en cours</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
