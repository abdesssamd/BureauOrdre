<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/db.php';

$installedNow = !empty($db_was_installed_now);

if (!$installedNow) {
    if (!empty($_SESSION['user_id'])) {
        header('Location: dashboard.php');
        exit;
    }
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation terminee</title>
    <style>
        :root {
            --bg-start: #f8fafc;
            --bg-end: #e2e8f0;
            --card-bg: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --ok: #166534;
            --ok-bg: #dcfce7;
            --btn: #0f172a;
            --btn-hover: #1e293b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 16px;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background: linear-gradient(140deg, var(--bg-start) 0%, var(--bg-end) 100%);
        }
        .card {
            width: 100%;
            max-width: 520px;
            background: var(--card-bg);
            border-radius: 14px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.15);
            padding: 28px;
        }
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--ok-bg);
            color: var(--ok);
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        h1 {
            margin: 14px 0 8px;
            font-size: 24px;
            line-height: 1.2;
        }
        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.5;
        }
        .actions {
            margin-top: 24px;
        }
        .btn {
            display: inline-block;
            text-decoration: none;
            background: var(--btn);
            color: #fff;
            border: 0;
            border-radius: 10px;
            padding: 12px 18px;
            font-weight: 600;
        }
        .btn:hover {
            background: var(--btn-hover);
        }
    </style>
</head>
<body>
    <main class="card">
        <span class="badge">Installation reussie</span>
        <h1>Base de donnees initialisee</h1>
        <p>La structure SQL a ete installee avec succes pour la premiere utilisation de l'application.</p>
        <div class="actions">
            <a class="btn" href="index.php">Continuer vers la connexion</a>
        </div>
    </main>
</body>
</html>
