<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

check_auth();
$user = get_user();

$db = getDB();

// Période
$period = $_GET['p'] ?? '30';
$validPeriods = ['7' => '7 jours', '30' => '30 jours', '90' => '3 mois', '365' => '12 mois', 'all' => 'Tout'];
if (!isset($validPeriods[$period])) $period = '30';

$dateCondition = $period === 'all' ? '1=1' : "visited_at >= DATE_SUB(NOW(), INTERVAL $period DAY)";

// Total visites
$total = (int) $db->query("SELECT COUNT(*) FROM page_views WHERE $dateCondition")->fetchColumn();

// Visites aujourd'hui
$today = (int) $db->query("SELECT COUNT(*) FROM page_views WHERE DATE(visited_at) = CURDATE()")->fetchColumn();

// Pages les plus vues
$topPages = $db->query(
    "SELECT page, COUNT(*) as vues FROM page_views WHERE $dateCondition GROUP BY page ORDER BY vues DESC LIMIT 10"
)->fetchAll();

// Visites par jour (graphe)
$dailyData = $db->query(
    "SELECT DATE(visited_at) as jour, COUNT(*) as vues FROM page_views
     WHERE $dateCondition GROUP BY jour ORDER BY jour ASC"
)->fetchAll();

// Referrers
$topReferrers = $db->query(
    "SELECT referrer_domain, COUNT(*) as vues FROM page_views
     WHERE $dateCondition AND referrer_domain != '' GROUP BY referrer_domain ORDER BY vues DESC LIMIT 10"
)->fetchAll();

// Devices
$devices = $db->query(
    "SELECT device, COUNT(*) as vues FROM page_views WHERE $dateCondition GROUP BY device ORDER BY vues DESC"
)->fetchAll();

// Visites par heure
$hourly = $db->query(
    "SELECT HOUR(visited_at) as h, COUNT(*) as vues FROM page_views
     WHERE $dateCondition GROUP BY h ORDER BY h"
)->fetchAll();

// Max pour les barres
$maxDaily = 1;
foreach ($dailyData as $d) { if ($d['vues'] > $maxDaily) $maxDaily = $d['vues']; }
$maxHourly = 1;
foreach ($hourly as $h) { if ($h['vues'] > $maxHourly) $maxHourly = $h['vues']; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques — Admin CS-LET</title>
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .stats-section { margin-bottom: 2rem; }
        .stats-section h2 { font-size: 1.1rem; color: #1a2d4a; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0; }
        .period-bar { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .period-bar a {
            padding: 0.4rem 0.8rem; border: 1px solid #cbd5e0; border-radius: 4px;
            font-size: 0.8rem; color: #4a5568; text-decoration: none;
        }
        .period-bar a.active, .period-bar a:hover { background: #2c5282; color: #fff; border-color: #2c5282; }

        .kpi-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .kpi { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; text-align: center; }
        .kpi .val { font-size: 2rem; font-weight: 700; color: #1a2d4a; }
        .kpi .lbl { font-size: 0.75rem; color: #718096; text-transform: uppercase; margin-top: 0.25rem; }

        .bar-chart { width: 100%; }
        .bar-row { display: flex; align-items: center; margin-bottom: 0.35rem; }
        .bar-label { width: 200px; font-size: 0.8rem; color: #4a5568; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bar-track { flex: 1; background: #e2e8f0; border-radius: 3px; height: 20px; margin: 0 0.75rem; position: relative; }
        .bar-fill { background: #2c5282; border-radius: 3px; height: 100%; min-width: 2px; }
        .bar-value { font-size: 0.8rem; font-weight: 600; color: #2c5282; width: 50px; text-align: right; }

        .mini-graph { display: flex; align-items: flex-end; gap: 2px; height: 100px; margin: 1rem 0; }
        .mini-graph .col { flex: 1; background: #2c5282; border-radius: 2px 2px 0 0; min-width: 3px; position: relative; }
        .mini-graph .col:hover { background: #1a365d; }
        .mini-graph .col:hover::after {
            content: attr(data-tip); position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%);
            background: #1a202c; color: #fff; font-size: 0.7rem; padding: 2px 6px; border-radius: 3px; white-space: nowrap;
        }

        .device-grid { display: flex; gap: 1.5rem; flex-wrap: wrap; }
        .device-card { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.5rem; text-align: center; min-width: 120px; }
        .device-card .val { font-size: 1.5rem; font-weight: 700; color: #2c5282; }
        .device-card .lbl { font-size: 0.8rem; color: #718096; text-transform: capitalize; }

        .no-data { text-align: center; color: #a0aec0; padding: 2rem; font-size: 0.9rem; }
    </style>
</head>
<body class="admin-body">

<nav class="admin-nav">
    <div class="admin-nav-brand">
        <span class="badge-fmel">FMEL</span>
        <span class="nav-title">CS-LET Admin</span>
    </div>
    <ul class="admin-nav-links">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="calendrier.php">Calendrier</a></li>
        <li><a href="planning.php">Plannings</a></li>
        <li><a href="rapport.php">Rapport</a></li>
        <li><a href="guide.php">Guide</a></li>
        <li><a href="stats.php" class="active">Stats</a></li>
    </ul>
    <div class="admin-nav-user">
        <span class="nav-username"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></span>
        <a href="logout.php" class="btn-logout">Deconnexion</a>
    </div>
</nav>

<main class="admin-main">
    <div class="page-header">
        <h1 class="page-title">Statistiques</h1>
        <p class="page-subtitle">Visites du site public — sans cookie, conforme RGPD</p>
    </div>

    <div class="period-bar">
        <?php foreach ($validPeriods as $k => $label): ?>
        <a href="stats.php?p=<?= $k ?>" class="<?= $period === $k ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <!-- KPIs -->
    <div class="kpi-row">
        <div class="kpi">
            <div class="val"><?= $total ?></div>
            <div class="lbl">Visites (<?= $validPeriods[$period] ?>)</div>
        </div>
        <div class="kpi">
            <div class="val"><?= $today ?></div>
            <div class="lbl">Aujourd'hui</div>
        </div>
        <div class="kpi">
            <div class="val"><?= count($dailyData) > 0 ? round($total / count($dailyData), 1) : 0 ?></div>
            <div class="lbl">Moyenne / jour</div>
        </div>
        <div class="kpi">
            <div class="val"><?= count($topReferrers) ?></div>
            <div class="lbl">Sources externes</div>
        </div>
    </div>

    <!-- Graphe journalier -->
    <div class="stats-section">
        <h2>Visites par jour</h2>
        <?php if (empty($dailyData)): ?>
            <p class="no-data">Pas encore de donnees sur cette periode.</p>
        <?php else: ?>
        <div class="mini-graph">
            <?php foreach ($dailyData as $d):
                $pct = round(($d['vues'] / $maxDaily) * 100);
            ?>
            <div class="col" style="height:<?= max($pct, 3) ?>%;" data-tip="<?= date('d.m', strtotime($d['jour'])) ?> : <?= $d['vues'] ?> visite<?= $d['vues'] > 1 ? 's' : '' ?>"></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Pages les plus vues -->
    <div class="stats-section">
        <h2>Pages les plus vues</h2>
        <?php if (empty($topPages)): ?>
            <p class="no-data">Pas encore de donnees.</p>
        <?php else: ?>
        <div class="bar-chart">
            <?php
            $maxPage = $topPages[0]['vues'] ?? 1;
            foreach ($topPages as $p):
                $pct = round(($p['vues'] / $maxPage) * 100);
                $label = $p['page'] === '/' || $p['page'] === '/index.php' ? 'Accueil' : $p['page'];
            ?>
            <div class="bar-row">
                <span class="bar-label" title="<?= htmlspecialchars($p['page']) ?>"><?= htmlspecialchars($label) ?></span>
                <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
                <span class="bar-value"><?= $p['vues'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Referrers -->
    <div class="stats-section">
        <h2>Sources de trafic</h2>
        <?php if (empty($topReferrers)): ?>
            <p class="no-data">Aucun referrer externe enregistre.</p>
        <?php else: ?>
        <div class="bar-chart">
            <?php
            $maxRef = $topReferrers[0]['vues'] ?? 1;
            foreach ($topReferrers as $r):
                $pct = round(($r['vues'] / $maxRef) * 100);
            ?>
            <div class="bar-row">
                <span class="bar-label"><?= htmlspecialchars($r['referrer_domain']) ?></span>
                <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
                <span class="bar-value"><?= $r['vues'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Devices -->
    <div class="stats-section">
        <h2>Appareils</h2>
        <?php if (empty($devices)): ?>
            <p class="no-data">Pas encore de donnees.</p>
        <?php else: ?>
        <div class="device-grid">
            <?php
            $deviceLabels = ['desktop' => 'Ordinateur', 'mobile' => 'Mobile', 'tablet' => 'Tablette'];
            foreach ($devices as $d):
            ?>
            <div class="device-card">
                <div class="val"><?= $d['vues'] ?></div>
                <div class="lbl"><?= $deviceLabels[$d['device']] ?? $d['device'] ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Heures -->
    <div class="stats-section">
        <h2>Visites par heure</h2>
        <?php if (empty($hourly)): ?>
            <p class="no-data">Pas encore de donnees.</p>
        <?php else: ?>
        <div class="mini-graph" style="height:80px;">
            <?php
            // Remplir les 24h
            $byHour = array_fill(0, 24, 0);
            foreach ($hourly as $h) $byHour[(int)$h['h']] = (int)$h['vues'];
            foreach ($byHour as $h => $v):
                $pct = $maxHourly > 0 ? round(($v / $maxHourly) * 100) : 0;
            ?>
            <div class="col" style="height:<?= max($pct, 3) ?>%;" data-tip="<?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>h : <?= $v ?>"></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</main>

<footer class="admin-footer">
    &copy; <?= date('Y') ?> FMEL &mdash; Interface d'administration CS-LET
</footer>

</body>
</html>
