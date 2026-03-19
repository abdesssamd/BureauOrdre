<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$dept_id = $_SESSION['department_id'];

// Récupération des paramètres de recherche
$search_query = $_GET['q'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_owner = $_GET['owner'] ?? '';
$filter_dept = $_GET['department'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_visibility = $_GET['visibility'] ?? '';

$results = [];

if (!empty($search_query) || !empty($filter_type) || !empty($filter_owner) || !empty($filter_dept) || !empty($filter_date_from) || !empty($filter_visibility)) {
    
    // Construction de la requête SQL
    $sql = "SELECT DISTINCT files.*, users.username, users.full_name, departments.name as dept_name 
            FROM files 
            LEFT JOIN users ON files.owner_id = users.id 
            LEFT JOIN departments ON files.department_id = departments.id
            LEFT JOIN file_shares ON files.id = file_shares.file_id
            WHERE (
                files.owner_id = :user_id 
                OR files.visibility = 'public'
                OR (files.visibility = 'department' AND files.department_id = :dept_id)
                OR file_shares.shared_with_user_id = :user_id2
            )";
    
    $params = [
        'user_id' => $user_id,
        'dept_id' => $dept_id,
        'user_id2' => $user_id
    ];
    
    // Filtre par recherche textuelle (nom, type, contenu OCR si disponible)
    if (!empty($search_query)) {
        $has_ocr = false;
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM files LIKE 'ocr_content'")->fetch();
            $has_ocr = (bool)$cols;
        } catch (Exception $e) {}
        $sql .= " AND (files.original_name LIKE :search OR files.mime_type LIKE :search2" . ($has_ocr ? " OR files.ocr_content LIKE :search3" : "") . ")";
        $params['search'] = "%$search_query%";
        $params['search2'] = "%$search_query%";
        if ($has_ocr) $params['search3'] = "%$search_query%";
    }
    
    // Filtre par type de fichier
    if (!empty($filter_type)) {
        $sql .= " AND files.mime_type LIKE :type";
        $params['type'] = "%$filter_type%";
    }
    
    // Filtre par propriétaire
    if (!empty($filter_owner)) {
        $sql .= " AND files.owner_id = :owner";
        $params['owner'] = $filter_owner;
    }
    
    // Filtre par département
    if (!empty($filter_dept)) {
        $sql .= " AND files.department_id = :filter_dept";
        $params['filter_dept'] = $filter_dept;
    }
    
    // Filtre par visibilité
    if (!empty($filter_visibility)) {
        $sql .= " AND files.visibility = :visibility";
        $params['visibility'] = $filter_visibility;
    }
    
    // Filtre par date (de)
    if (!empty($filter_date_from)) {
        $sql .= " AND DATE(files.uploaded_at) >= :date_from";
        $params['date_from'] = $filter_date_from;
    }
    
    // Filtre par date (à)
    if (!empty($filter_date_to)) {
        $sql .= " AND DATE(files.uploaded_at) <= :date_to";
        $params['date_to'] = $filter_date_to;
    }
    
    $sql .= " ORDER BY files.uploaded_at DESC LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}

// Récupérer les utilisateurs pour le filtre
$users = $pdo->query("SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();

// Récupérer les départements
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Recherche Avancée - Admin</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="assets/css/all.min.css" rel="stylesheet">
    <style>
        .search-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }
        .search-main {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .search-main input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
        }
        .search-main input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .filters-toggle {
            background: #f1f5f9;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            transition: background 0.2s;
        }
        .filters-toggle:hover {
            background: #e2e8f0;
        }
        .filters-panel {
            display: none;
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .filters-panel.active {
            display: block;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .filter-item label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
            color: #334155;
        }
        .filter-item select,
        .filter-item input {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }
        .search-stats {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .result-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            transition: background 0.2s;
        }
        .result-item:hover {
            background: #f8fafc;
        }
        .result-icon {
            width: 50px;
            height: 50px;
            background: #e0f2fe;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #0284c7;
            margin-right: 15px;
        }
        .result-info {
            flex: 1;
        }
        .result-actions {
            display: flex;
            gap: 8px;
        }
        .highlight {
            background: #fef08a;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include '../includes/header.php'; ?>

        <div class="content-area">
            <h1><i class="fa-solid fa-magnifying-glass"></i> Recherche Avancée</h1>

            <div class="search-container">
                <form method="GET" action="search.php">
                    <div class="search-main">
                        <input type="text" 
                               name="q" 
                               placeholder="Rechercher un fichier par nom..." 
                               value="<?= htmlspecialchars($search_query) ?>"
                               autofocus>
                        <button type="submit" class="btn-primary">
                            <i class="fa-solid fa-search"></i> Rechercher
                        </button>
                    </div>

                    <div class="filters-toggle" onclick="toggleFilters()">
                        <i class="fa-solid fa-sliders"></i>
                        <span>Filtres avancés</span>
                        <i class="fa-solid fa-chevron-down" id="toggleIcon"></i>
                    </div>

                    <div class="filters-panel" id="filtersPanel">
                        <div class="filter-grid">
                            <div class="filter-item">
                                <label><i class="fa-solid fa-file-lines"></i> Type de fichier</label>
                                <select name="type">
                                    <option value="">Tous les types</option>
                                    <option value="pdf" <?= $filter_type == 'pdf' ? 'selected' : '' ?>>PDF</option>
                                    <option value="word" <?= $filter_type == 'word' ? 'selected' : '' ?>>Word</option>
                                    <option value="excel" <?= $filter_type == 'excel' ? 'selected' : '' ?>>Excel</option>
                                    <option value="image" <?= $filter_type == 'image' ? 'selected' : '' ?>>Images</option>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label><i class="fa-solid fa-user"></i> Déposé par</label>
                                <select name="owner">
                                    <option value="">Tous les utilisateurs</option>
                                    <?php foreach($users as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= $filter_owner == $u['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label><i class="fa-solid fa-building"></i> Département</label>
                                <select name="department">
                                    <option value="">Tous les départements</option>
                                    <?php foreach($departments as $d): ?>
                                        <option value="<?= $d['id'] ?>" <?= $filter_dept == $d['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($d['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label><i class="fa-solid fa-eye"></i> Visibilité</label>
                                <select name="visibility">
                                    <option value="">Toutes</option>
                                    <option value="public" <?= $filter_visibility == 'public' ? 'selected' : '' ?>>Public</option>
                                    <option value="department" <?= $filter_visibility == 'department' ? 'selected' : '' ?>>Département</option>
                                    <option value="private" <?= $filter_visibility == 'private' ? 'selected' : '' ?>>Privé</option>
                                </select>
                            </div>

                            <div class="filter-item">
                                <label><i class="fa-solid fa-calendar"></i> Date de début</label>
                                <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
                            </div>

                            <div class="filter-item">
                                <label><i class="fa-solid fa-calendar"></i> Date de fin</label>
                                <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
                            </div>
                        </div>

                        <div style="margin-top: 20px; display: flex; gap: 10px;">
                            <button type="submit" class="btn-primary">
                                <i class="fa-solid fa-filter"></i> Appliquer les filtres
                            </button>
                            <a href="search.php" class="btn-sm" style="background: #64748b; color: white;">
                                <i class="fa-solid fa-rotate-left"></i> Réinitialiser
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <?php if (!empty($results)): ?>
                <div class="search-stats">
                    <div>
                        <strong><?= count($results) ?></strong> résultat<?= count($results) > 1 ? 's' : '' ?> trouvé<?= count($results) > 1 ? 's' : '' ?>
                    </div>
                    <div style="color: #64748b; font-size: 14px;">
                        <?php if (!empty($search_query)): ?>
                            Recherche : <strong>"<?= htmlspecialchars($search_query) ?>"</strong>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="recent-files">
                    <?php foreach($results as $file): 
                        $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                        $icon = "fa-file";
                        if($ext == 'pdf') $icon = "fa-file-pdf";
                        elseif(in_array($ext, ['doc', 'docx'])) $icon = "fa-file-word";
                        elseif(in_array($ext, ['xls', 'xlsx'])) $icon = "fa-file-excel";
                        elseif(in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $icon = "fa-file-image";
                    ?>
                        <div class="result-item">
                            <div class="result-icon">
                                <i class="fa-solid <?= $icon ?>"></i>
                            </div>
                            <div class="result-info">
                                <h3 style="margin: 0 0 5px 0; font-size: 16px;">
                                    <?php
                                        $name = htmlspecialchars($file['original_name']);
                                        if (!empty($search_query)) {
                                            $name = str_ireplace($search_query, '<span class="highlight">' . $search_query . '</span>', $name);
                                        }
                                        echo $name;
                                    ?>
                                </h3>
                                <div style="font-size: 14px; color: #64748b;">
                                    <i class="fa-solid fa-user"></i> <?= htmlspecialchars($file['full_name']) ?>
                                    <span style="margin: 0 8px;">•</span>
                                    <i class="fa-solid fa-building"></i> <?= htmlspecialchars($file['dept_name'] ?? 'Aucun') ?>
                                    <span style="margin: 0 8px;">•</span>
                                    <i class="fa-solid fa-calendar"></i> <?= date('d/m/Y', strtotime($file['uploaded_at'])) ?>
                                    <span style="margin: 0 8px;">•</span>
                                    <?= round($file['size']/1024, 1) ?> Ko
                                </div>
                                <div style="margin-top: 5px;">
                                    <?php
                                        $visColor = [
                                            'public' => '#10b981',
                                            'department' => '#f59e0b',
                                            'private' => '#64748b'
                                        ];
                                    ?>
                                    <span class="badge" style="background: <?= $visColor[$file['visibility']] ?? '#64748b' ?>; color: white; font-size: 12px;">
                                        <?= ucfirst($file['visibility']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="result-actions">
                                <button class="btn-sm" style="background:var(--accent-color); color:white;" 
                                        onclick="window.open('preview.php?id=<?= $file['id'] ?>', '_blank')">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <a href="../uploads/<?= $file['stored_name'] ?>" class="btn-sm" download="<?= $file['original_name'] ?>">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif(isset($_GET['q']) || isset($_GET['type'])): ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 12px;">
                    <i class="fa-solid fa-magnifying-glass fa-3x" style="color: #cbd5e1; margin-bottom: 20px;"></i>
                    <h3>Aucun résultat trouvé</h3>
                    <p style="color: #64748b;">Essayez avec d'autres mots-clés ou ajustez vos filtres.</p>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 12px;">
                    <i class="fa-solid fa-search fa-3x" style="color: #cbd5e1; margin-bottom: 20px;"></i>
                    <h3>Lancez une recherche</h3>
                    <p style="color: #64748b;">Utilisez la barre de recherche et les filtres pour trouver vos fichiers.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleFilters() {
            const panel = document.getElementById('filtersPanel');
            const icon = document.getElementById('toggleIcon');
            panel.classList.toggle('active');
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        }

        // Ouvrir automatiquement les filtres si des filtres sont appliqués
        <?php if(!empty($filter_type) || !empty($filter_owner) || !empty($filter_dept) || !empty($filter_date_from) || !empty($filter_visibility)): ?>
            toggleFilters();
        <?php endif; ?>
    </script>
</body>
</html>