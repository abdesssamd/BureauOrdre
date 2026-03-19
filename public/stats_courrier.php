<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
if (!in_array($_SESSION['role'], ['admin', 'secretaire', 'secretaria', 'directeur', 'chef_service'])) {
    header('Location: dashboard.php'); exit;
}

$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Stats par mois (arrivÃ©es + dÃ©parts)
$by_month = $pdo->prepare("
    SELECT MONTH(mail_date) as m, type, COUNT(*) as cnt
    FROM mails
    WHERE YEAR(mail_date) = ?
    GROUP BY MONTH(mail_date), type
    ORDER BY m
");
$by_month->execute([$year]);
$monthly = [];
while ($r = $by_month->fetch()) {
    if (!isset($monthly[$r['m']])) $monthly[$r['m']] = ['arrivee' => 0, 'depart' => 0];
    $monthly[$r['m']][$r['type']] = (int)$r['cnt'];
}

// Top correspondants
$top_contacts = $pdo->prepare("
    SELECT c.name, COUNT(*) as cnt
    FROM mails m
    JOIN contacts c ON m.contact_id = c.id
    WHERE YEAR(m.mail_date) = ?
    GROUP BY m.contact_id
    ORDER BY cnt DESC
    LIMIT 10
");
$top_contacts->execute([$year]);
$top_contacts = $top_contacts->fetchAll();

// Totaux
$tot_arr = $pdo->prepare("SELECT COUNT(*) FROM mails WHERE type='arrivee' AND YEAR(mail_date)=?");
$tot_arr->execute([$year]);
$tot_arr = $tot_arr->fetchColumn();

$tot_dep = $pdo->prepare("SELECT COUNT(*) FROM mails WHERE type='depart' AND YEAR(mail_date)=?");
$tot_dep->execute([$year]);
$tot_dep = $tot_dep->fetchColumn();

$months_fr = ['', 'Jan', 'FÃ©v', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'AoÃ»t', 'Sep', 'Oct', 'Nov', 'DÃ©c'];

include '../includes/header.php';
?>

<div class="content-area">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
        <h1><i class="fa-solid fa-chart-column"></i> Statistiques Courrier</h1>
        <form method="GET" style="display:flex; gap:10px; align-items:center;">
            <label>AnnÃ©e :</label>
            <select name="year" class="form-control" onchange="this.form.submit()">
                <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <div class="stats-grid" style="margin-bottom:30px;">
        <div class="stat-card">
            <div class="stat-info">
                <h3>ArrivÃ©es <?= $year ?></h3>
                <p style="font-size:2rem;"><?= $tot_arr ?></p>
            </div>
            <div class="stat-icon icon-blue"><i class="fa-solid fa-arrow-down"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3>DÃ©parts <?= $year ?></h3>
                <p style="font-size:2rem;"><?= $tot_dep ?></p>
            </div>
            <div class="stat-icon icon-purple"><i class="fa-solid fa-arrow-up"></i></div>
        </div>
        <div class="stat-card">
            <div class="stat-info">
                <h3>Total</h3>
                <p style="font-size:2rem;"><?= $tot_arr + $tot_dep ?></p>
            </div>
            <div class="stat-icon icon-green"><i class="fa-solid fa-envelope"></i></div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 1.5fr 1fr; gap: 25px;">
        <div class="upload-card">
            <h3 style="margin-top:0;">Courrier par mois</h3>
            <?php 
            $max_val = 1;
            for ($m = 1; $m <= 12; $m++) {
                $t = ($monthly[$m]['arrivee'] ?? 0) + ($monthly[$m]['depart'] ?? 0);
                if ($t > $max_val) $max_val = $t;
            }
            ?>
            <div style="height:280px; display:flex; align-items:flex-end; gap:6px; padding:20px 0;">
                <?php for ($m = 1; $m <= 12; $m++): 
                    $arr = $monthly[$m]['arrivee'] ?? 0;
                    $dep = $monthly[$m]['depart'] ?? 0;
                    $total_m = $arr + $dep;
                    $h_pct = $max_val > 0 ? ($total_m / $max_val) * 100 : 0;
                    $arr_pct = $total_m > 0 ? ($arr / $total_m) * 100 : 0;
                    $dep_pct = $total_m > 0 ? ($dep / $total_m) * 100 : 0;
                ?>
                <div style="flex:1; display:flex; flex-direction:column; align-items:center;">
                    <div style="width:100%; height:220px; display:flex; align-items:flex-end;">
                        <div style="width:100%; height:<?= max($h_pct, 2) ?>%; display:flex; flex-direction:column;">
                            <?php if ($dep > 0): ?>
                            <div style="height:<?= $dep_pct ?>%; background:#1b74e4; border-radius:2px 2px 0 0;" title="DÃ©parts: <?= $dep ?>"></div>
                            <?php endif; ?>
                            <?php if ($arr > 0): ?>
                            <div style="height:<?= $arr_pct ?>%; background:#3b82f6; border-radius:2px 2px 0 0;" title="ArrivÃ©es: <?= $arr ?>"></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span style="font-size:0.7rem; color:#64748b; margin-top:5px;"><?= $months_fr[$m] ?></span>
                    <span style="font-size:0.75rem; font-weight:bold;"><?= $total_m ?></span>
                </div>
                <?php endfor; ?>
            </div>
            <div style="display:flex; gap:20px; margin-top:15px; padding-top:15px; border-top:1px solid #e2e8f0;">
                <span><span style="display:inline-block; width:12px; height:12px; background:#3b82f6; border-radius:2px;"></span> ArrivÃ©es</span>
                <span><span style="display:inline-block; width:12px; height:12px; background:#1b74e4; border-radius:2px;"></span> DÃ©parts</span>
            </div>
        </div>

        <div class="upload-card">
            <h3 style="margin-top:0;">Top 10 correspondants</h3>
            <?php if (empty($top_contacts)): ?>
                <p style="color:#64748b;">Aucune donnÃ©e.</p>
            <?php else: ?>
                <div style="max-height:280px; overflow-y:auto;">
                    <?php 
                    $max_cnt = max(array_column($top_contacts, 'cnt'));
                    foreach ($top_contacts as $i => $c): 
                        $pct = $max_cnt > 0 ? ($c['cnt'] / $max_cnt) * 100 : 0;
                    ?>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:4px; font-size:0.9rem;">
                            <span><?= htmlspecialchars($c['name']) ?></span>
                            <strong><?= $c['cnt'] ?></strong>
                        </div>
                        <div style="height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                            <div style="height:100%; width:<?= $pct ?>%; background:linear-gradient(90deg,#10b981,#34d399); border-radius:4px;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

