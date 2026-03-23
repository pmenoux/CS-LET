<?php
/**
 * rapport.php — Rapport des nuisances CS-LET
 * Génère un récapitulatif des nuisances sur une période donnée.
 * Export CSV ou affichage HTML pour impression / preuve juridique.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

check_auth();
$user = get_user();

// ── Paramètres de période ────────────────────────────────────────────────
$startDate = $_GET['start'] ?? '';
$endDate   = $_GET['end']   ?? '';
$format    = $_GET['format'] ?? 'html'; // html ou csv

// Defaults : mois en cours
if (!$startDate) $startDate = date('Y-m-01');
if (!$endDate)   $endDate   = date('Y-m-t');

// Validation
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $startDate = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))   $endDate   = date('Y-m-t');

// ── Requête DB ───────────────────────────────────────────────────────────
$entries = [];
$stats   = [];
try {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT date, picto, created_at, updated_at
         FROM nuisances
         WHERE date BETWEEN ? AND ?
         ORDER BY date ASC'
    );
    $stmt->execute([$startDate, $endDate]);
    $entries = $stmt->fetchAll();

    // Statistiques par type de picto
    $stmtStats = $db->prepare(
        'SELECT picto, COUNT(*) as nb
         FROM nuisances
         WHERE date BETWEEN ? AND ?
         GROUP BY picto
         ORDER BY nb DESC'
    );
    $stmtStats->execute([$startDate, $endDate]);
    $stats = $stmtStats->fetchAll();
} catch (PDOException $e) {
    error_log('[CS-LET admin] Rapport query error: ' . $e->getMessage());
}

// Labels lisibles
$pictoLabels = [
    'PICTO_Camion_Faible.webp'       => 'Camion — Nuisance faible',
    'PICTO_Camion_Faible_pous.webp'  => 'Camion — Nuisance faible + poussière',
    'PICTO_Camion_Moyen.webp'        => 'Camion — Nuisance moyenne',
    'PICTO_Camion_Moyen_pous.webp'   => 'Camion — Nuisance moyenne + poussière',
    'PICTO_Camion_Fort.webp'         => 'Camion — Nuisance forte',
    'PICTO_Camion_Fort_pous.webp'    => 'Camion — Nuisance forte + poussière',
    'PICTO_Pelleteuse_Faible.webp'   => 'Pelleteuse — Nuisance faible',
    'PICTO_Pelleteuse_Moyen.webp'    => 'Pelleteuse — Nuisance moyenne',
    'PICTO_Pelleteuse_Fort.webp'     => 'Pelleteuse — Nuisance forte',
    'PICTO_Pieux_Moyen.webp'         => 'Pieux — Nuisance moyenne',
    'PICTO_Pieux_Fort.webp'          => 'Pieux — Nuisance forte',
];

// Niveaux de nuisance pour le scoring
$pictoNiveaux = [
    'PICTO_Camion_Faible.webp'       => ['niveau' => 'Faible',  'score' => 1],
    'PICTO_Camion_Faible_pous.webp'  => ['niveau' => 'Faible',  'score' => 1],
    'PICTO_Camion_Moyen.webp'        => ['niveau' => 'Moyen',   'score' => 2],
    'PICTO_Camion_Moyen_pous.webp'   => ['niveau' => 'Moyen',   'score' => 2],
    'PICTO_Camion_Fort.webp'         => ['niveau' => 'Fort',    'score' => 3],
    'PICTO_Camion_Fort_pous.webp'    => ['niveau' => 'Fort',    'score' => 3],
    'PICTO_Pelleteuse_Faible.webp'   => ['niveau' => 'Faible',  'score' => 1],
    'PICTO_Pelleteuse_Moyen.webp'    => ['niveau' => 'Moyen',   'score' => 2],
    'PICTO_Pelleteuse_Fort.webp'     => ['niveau' => 'Fort',    'score' => 3],
    'PICTO_Pieux_Moyen.webp'         => ['niveau' => 'Moyen',   'score' => 2],
    'PICTO_Pieux_Fort.webp'          => ['niveau' => 'Fort',    'score' => 3],
];

// Jours FR
$joursFr = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche'];
$moisFr  = [1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',
            7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre'];

// Calculs
$totalJours       = count($entries);
$joursFaibles     = 0;
$joursMoyens      = 0;
$joursForts       = 0;
$joursPoussiere   = 0;
$scoreTotal       = 0;

foreach ($entries as $e) {
    $info = $pictoNiveaux[$e['picto']] ?? ['niveau' => '?', 'score' => 0];
    $scoreTotal += $info['score'];
    switch ($info['niveau']) {
        case 'Faible': $joursFaibles++; break;
        case 'Moyen':  $joursMoyens++;  break;
        case 'Fort':   $joursForts++;   break;
    }
    if (str_contains($e['picto'], 'pous')) $joursPoussiere++;
}

// Nombre de jours calendaires dans la période
$nbJoursCalendaires = (int)((strtotime($endDate) - strtotime($startDate)) / 86400) + 1;

// ── EXPORT CSV ───────────────────────────────────────────────────────────
if ($format === 'csv') {
    $filename = 'CS-LET_Rapport_Nuisances_' . $startDate . '_' . $endDate . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    // BOM UTF-8 pour Excel
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    fputcsv($out, ['RAPPORT DES NUISANCES — CHANTIER LET CAMPUS SANTE DORIGNY'], ';');
    fputcsv($out, ['Periode : ' . $startDate . ' au ' . $endDate], ';');
    fputcsv($out, ['Genere le : ' . date('d.m.Y H:i')], ';');
    fputcsv($out, [''], ';');
    fputcsv($out, ['SYNTHESE'], ';');
    fputcsv($out, ['Jours avec nuisances', $totalJours . ' / ' . $nbJoursCalendaires . ' jours'], ';');
    fputcsv($out, ['Jours nuisance faible', $joursFaibles], ';');
    fputcsv($out, ['Jours nuisance moyenne', $joursMoyens], ';');
    fputcsv($out, ['Jours nuisance forte', $joursForts], ';');
    fputcsv($out, ['Jours avec poussiere', $joursPoussiere], ';');
    fputcsv($out, ['Score cumule', $scoreTotal . ' (Faible=1, Moyen=2, Fort=3)'], ';');
    fputcsv($out, [''], ';');
    fputcsv($out, ['DETAIL PAR JOUR'], ';');
    fputcsv($out, ['Date', 'Jour', 'Type de nuisance', 'Niveau', 'Score'], ';');

    foreach ($entries as $e) {
        $ts    = strtotime($e['date']);
        $dow   = $joursFr[(int)date('N', $ts)] ?? '';
        $label = $pictoLabels[$e['picto']] ?? $e['picto'];
        $info  = $pictoNiveaux[$e['picto']] ?? ['niveau' => '?', 'score' => 0];
        fputcsv($out, [
            date('d.m.Y', $ts),
            $dow,
            $label,
            $info['niveau'],
            $info['score'],
        ], ';');
    }

    fputcsv($out, [''], ';');
    fputcsv($out, ['Ce rapport est genere automatiquement par le systeme CS-LET.'], ';');
    fputcsv($out, ['Les informations sont a titre informatif et n\'ont pas de portee legale.'], ';');
    fclose($out);
    exit;
}

// ── Formatage dates pour l'affichage ─────────────────────────────────────
function formatDateFR(string $date): string {
    global $joursFr, $moisFr;
    $ts = strtotime($date);
    $dow   = $joursFr[(int)date('N', $ts)] ?? '';
    $day   = (int)date('j', $ts);
    $month = $moisFr[(int)date('n', $ts)] ?? '';
    $year  = date('Y', $ts);
    return "$dow $day $month $year";
}

// Raccourcis de période
$periodes = [
    'Ce mois'        => ['start' => date('Y-m-01'), 'end' => date('Y-m-t')],
    'Mois dernier'   => ['start' => date('Y-m-01', strtotime('first day of last month')),
                         'end'   => date('Y-m-t',  strtotime('last day of last month'))],
    'Trimestre'      => ['start' => date('Y-m-01', strtotime('-2 months')), 'end' => date('Y-m-t')],
    'Semestre'       => ['start' => date('Y-m-01', strtotime('-5 months')), 'end' => date('Y-m-t')],
    'Annee en cours' => ['start' => date('Y-01-01'), 'end' => date('Y-m-t')],
    'Tout'           => ['start' => '2023-11-01', 'end' => date('Y-m-t')],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport des nuisances — Admin CS-LET</title>
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .rapport-header {
            background: #1a2d4a;
            color: #fff;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .rapport-header h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        .rapport-header .rapport-sub { color: #a0b4c8; font-size: 0.9rem; }
        .rapport-header .rapport-periode { font-size: 1.1rem; margin-top: 0.5rem; }

        .periode-form { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .periode-form .field { display: flex; flex-direction: column; gap: 0.3rem; }
        .periode-form label { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #4a5568; }
        .periode-form input[type="date"] {
            padding: 0.5rem 0.75rem; border: 1px solid #cbd5e0; border-radius: 5px; font-size: 0.9rem;
        }
        .periode-shortcuts { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .periode-shortcuts a {
            padding: 0.35rem 0.75rem; border: 1px solid #cbd5e0; border-radius: 4px;
            font-size: 0.8rem; color: #4a5568; text-decoration: none; transition: all 0.15s;
        }
        .periode-shortcuts a:hover, .periode-shortcuts a.active {
            background: #2c5282; color: #fff; border-color: #2c5282;
        }

        .stats-rapport { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-r { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; text-align: center; }
        .stat-r .val { font-size: 1.8rem; font-weight: 700; color: #1a2d4a; }
        .stat-r .lbl { font-size: 0.75rem; color: #718096; text-transform: uppercase; margin-top: 0.3rem; }
        .stat-r.faible .val { color: #38a169; }
        .stat-r.moyen .val  { color: #d69e2e; }
        .stat-r.fort .val   { color: #e53e3e; }

        .export-bar { display: flex; gap: 1rem; margin-bottom: 2rem; }
        .export-bar a {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.6rem 1.2rem; border-radius: 5px; font-size: 0.9rem;
            font-weight: 600; text-decoration: none; transition: background 0.15s;
        }
        .btn-csv { background: #38a169; color: #fff; }
        .btn-csv:hover { background: #2f855a; }
        .btn-print { background: #5a7a8a; color: #fff; }
        .btn-print:hover { background: #4a6a7a; }

        .rapport-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .rapport-table th {
            background: #edf2f7; padding: 0.6rem 0.75rem; text-align: left;
            font-size: 0.7rem; text-transform: uppercase; color: #4a5568; border-bottom: 2px solid #cbd5e0;
        }
        .rapport-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #e2e8f0; }
        .rapport-table tr:hover { background: #f7fafc; }
        .rapport-table .picto-cell img { height: 28px; vertical-align: middle; margin-right: 0.5rem; }
        .niveau-faible { color: #38a169; font-weight: 600; }
        .niveau-moyen  { color: #d69e2e; font-weight: 600; }
        .niveau-fort   { color: #e53e3e; font-weight: 600; }

        .rapport-footer {
            margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;
            font-size: 0.75rem; color: #a0aec0; text-align: center;
        }

        @media print {
            .admin-nav, .admin-footer, .periode-form, .periode-shortcuts, .export-bar, .btn-logout { display: none !important; }
            .rapport-header { background: #fff !important; color: #000 !important; border: 2px solid #000; }
            .rapport-header .rapport-sub { color: #333 !important; }
            .stat-r { border: 1px solid #000; }
            body { font-size: 11pt; }
        }
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
        <li><a href="rapport.php" class="active">Rapport</a></li>
        <li><a href="guide.php">Guide</a></li>
    </ul>
    <div class="admin-nav-user">
        <span class="nav-username"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></span>
        <a href="logout.php" class="btn-logout">Deconnexion</a>
    </div>
</nav>

<main class="admin-main">

    <!-- En-tête rapport -->
    <div class="rapport-header">
        <h1>Rapport des nuisances — Chantier LET</h1>
        <div class="rapport-sub">Campus Sante Dorigny — FMEL</div>
        <div class="rapport-periode">
            Periode : <?= htmlspecialchars(formatDateFR($startDate)) ?> au <?= htmlspecialchars(formatDateFR($endDate)) ?>
        </div>
        <div class="rapport-sub">Genere le <?= date('d.m.Y') ?> a <?= date('H:i') ?></div>
    </div>

    <!-- Sélecteur de période -->
    <form class="periode-form" method="get" action="rapport.php">
        <div class="field">
            <label for="start">Du</label>
            <input type="date" id="start" name="start" value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div class="field">
            <label for="end">Au</label>
            <input type="date" id="end" name="end" value="<?= htmlspecialchars($endDate) ?>">
        </div>
        <button type="submit" class="btn-primary" style="height:38px;">Generer</button>
    </form>

    <div class="periode-shortcuts">
        <?php foreach ($periodes as $label => $p): ?>
        <a href="rapport.php?start=<?= $p['start'] ?>&end=<?= $p['end'] ?>"
           class="<?= ($startDate === $p['start'] && $endDate === $p['end']) ? 'active' : '' ?>">
            <?= htmlspecialchars($label) ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Statistiques -->
    <div class="stats-rapport">
        <div class="stat-r">
            <div class="val"><?= $totalJours ?></div>
            <div class="lbl">Jours avec nuisances</div>
        </div>
        <div class="stat-r">
            <div class="val"><?= $nbJoursCalendaires ?></div>
            <div class="lbl">Jours dans la periode</div>
        </div>
        <div class="stat-r faible">
            <div class="val"><?= $joursFaibles ?></div>
            <div class="lbl">Jours faible</div>
        </div>
        <div class="stat-r moyen">
            <div class="val"><?= $joursMoyens ?></div>
            <div class="lbl">Jours moyen</div>
        </div>
        <div class="stat-r fort">
            <div class="val"><?= $joursForts ?></div>
            <div class="lbl">Jours fort</div>
        </div>
        <div class="stat-r">
            <div class="val"><?= $joursPoussiere ?></div>
            <div class="lbl">Jours poussiere</div>
        </div>
        <div class="stat-r">
            <div class="val"><?= $scoreTotal ?></div>
            <div class="lbl">Score cumule</div>
        </div>
    </div>

    <!-- Export -->
    <div class="export-bar">
        <a href="rapport.php?start=<?= htmlspecialchars($startDate) ?>&end=<?= htmlspecialchars($endDate) ?>&format=csv"
           class="btn-csv">
            Exporter CSV (Excel)
        </a>
        <a href="javascript:window.print()" class="btn-print">
            Imprimer / PDF
        </a>
    </div>

    <!-- Tableau détaillé -->
    <?php if (empty($entries)): ?>
        <p style="text-align:center; color:#718096; padding:2rem;">Aucune nuisance enregistree sur cette periode.</p>
    <?php else: ?>
    <table class="rapport-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Jour</th>
                <th>Type de nuisance</th>
                <th>Niveau</th>
                <th>Score</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entries as $e):
                $ts    = strtotime($e['date']);
                $dow   = $joursFr[(int)date('N', $ts)] ?? '';
                $label = $pictoLabels[$e['picto']] ?? $e['picto'];
                $info  = $pictoNiveaux[$e['picto']] ?? ['niveau' => '?', 'score' => 0];
                $nClass = 'niveau-' . strtolower($info['niveau']);
            ?>
            <tr>
                <td><?= date('d.m.Y', $ts) ?></td>
                <td><?= htmlspecialchars($dow) ?></td>
                <td class="picto-cell">
                    <img src="../assets/img/<?= htmlspecialchars($e['picto']) ?>"
                         alt="<?= htmlspecialchars($label) ?>">
                    <?= htmlspecialchars($label) ?>
                </td>
                <td class="<?= $nClass ?>"><?= htmlspecialchars($info['niveau']) ?></td>
                <td><?= $info['score'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:700; background:#edf2f7;">
                <td colspan="4">Total</td>
                <td><?= $scoreTotal ?></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>

    <!-- Cadre juridique — Droit du bail suisse -->
    <section class="rapport-juridique" style="margin-top:2.5rem; padding:2rem; background:#f7fafc; border:1px solid #e2e8f0; border-radius:8px;">
        <h2 style="font-size:1.1rem; color:#1a2d4a; margin-bottom:1rem;">Cadre juridique — Droit du bail suisse</h2>

        <div style="font-size:0.85rem; color:#4a5568; line-height:1.7;">
            <p>Le present rapport documente les nuisances liees au chantier LET (Campus Sante Dorigny)
            subies par les locataires voisins. Il sert de base factuelle pour l'evaluation d'eventuelles
            pretentions en reduction de loyer au sens du droit du bail suisse.</p>

            <h3 style="font-size:0.95rem; margin-top:1.25rem; color:#2c5282;">Base legale</h3>
            <ul style="margin:0.5rem 0 1rem 1.5rem;">
                <li><strong>Art. 256 CO</strong> — Le bailleur est tenu de delivrer la chose dans un etat
                    approprie a l'usage pour lequel elle a ete louee, et de l'entretenir dans cet etat.</li>
                <li><strong>Art. 259a CO</strong> — Lorsque apparaissent des defauts de la chose qui ne sont
                    pas imputables au locataire, celui-ci peut exiger du bailleur la remise en etat, une
                    reduction proportionnelle du loyer, des dommages-interets, ou la prise en charge du
                    proces contre un tiers.</li>
                <li><strong>Art. 259d CO</strong> — Le locataire peut exiger une <strong>reduction
                    proportionnelle du loyer</strong> lorsque le defaut restreint l'usage de la chose.</li>
                <li><strong>Art. 259e CO</strong> — Le locataire qui a droit a des dommages-interets peut
                    les compenser avec des loyers echus.</li>
            </ul>

            <h3 style="font-size:0.95rem; margin-top:1.25rem; color:#2c5282;">Jurisprudence applicable</h3>
            <ul style="margin:0.5rem 0 1rem 1.5rem;">
                <li><strong>ATF 136 III 186</strong> — Les immissions de bruit provenant de travaux de
                    construction a proximite de l'immeuble loue constituent un <strong>defaut de la chose
                    louee</strong>, meme si le bailleur n'en est pas responsable (defaut relatif).</li>
                <li><strong>ATF 135 III 345</strong> — Le locataire peut pretendre a une reduction de loyer
                    meme lorsque les nuisances proviennent de tiers (chantier voisin), car le bailleur
                    garantit l'usage convenu de la chose.</li>
                <li>La reduction de loyer se calcule proportionnellement a la <strong>diminution de
                    jouissance</strong> subie par le locataire.</li>
            </ul>

            <h3 style="font-size:0.95rem; margin-top:1.25rem; color:#2c5282;">Bareme indicatif de reduction</h3>
            <p>Les tribunaux suisses admettent generalement les fourchettes suivantes pour les nuisances
            de chantier :</p>
            <table style="width:100%; border-collapse:collapse; margin:0.75rem 0; font-size:0.85rem;">
                <thead>
                    <tr style="background:#edf2f7;">
                        <th style="padding:0.5rem; text-align:left; border-bottom:2px solid #cbd5e0;">Niveau de nuisance</th>
                        <th style="padding:0.5rem; text-align:left; border-bottom:2px solid #cbd5e0;">Score</th>
                        <th style="padding:0.5rem; text-align:left; border-bottom:2px solid #cbd5e0;">Reduction indicative</th>
                        <th style="padding:0.5rem; text-align:left; border-bottom:2px solid #cbd5e0;">Exemples</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0; color:#38a169; font-weight:600;">Faible</td>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0;">1</td>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0;">5 – 10 %</td>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0;">Camions, vibrations legeres</td>
                    </tr>
                    <tr>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0; color:#d69e2e; font-weight:600;">Moyen</td>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0;">2</td>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0;">10 – 20 %</td>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0;">Pieux, pelleteuses, poussiere significative</td>
                    </tr>
                    <tr>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0; color:#e53e3e; font-weight:600;">Fort</td>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0;">3</td>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0;">20 – 40 %</td>
                        <td style="padding:0.5rem; border-bottom:1px solid #e2e8f0;">Battage de pieux, travaux lourds prolonges</td>
                    </tr>
                </tbody>
            </table>

            <h3 style="font-size:0.95rem; margin-top:1.25rem; color:#2c5282;">Synthese pour cette periode</h3>
            <p>Sur la periode du <strong><?= htmlspecialchars(formatDateFR($startDate)) ?></strong>
            au <strong><?= htmlspecialchars(formatDateFR($endDate)) ?></strong> :</p>
            <ul style="margin:0.5rem 0 1rem 1.5rem;">
                <li><strong><?= $totalJours ?></strong> jours avec nuisances documentees sur <?= $nbJoursCalendaires ?> jours calendaires
                    (<?= $nbJoursCalendaires > 0 ? round($totalJours / $nbJoursCalendaires * 100) : 0 ?> %)</li>
                <li><strong><?= $joursForts ?></strong> jours de nuisance forte (score 3)
                    <?php if ($joursForts > 0): ?>— potentiellement eligibles a une reduction de 20-40 %<?php endif; ?></li>
                <li><strong><?= $joursMoyens ?></strong> jours de nuisance moyenne (score 2)
                    <?php if ($joursMoyens > 0): ?>— potentiellement eligibles a une reduction de 10-20 %<?php endif; ?></li>
                <li><strong><?= $joursFaibles ?></strong> jours de nuisance faible (score 1)
                    <?php if ($joursFaibles > 0): ?>— potentiellement eligibles a une reduction de 5-10 %<?php endif; ?></li>
                <li>Score cumule de nuisance : <strong><?= $scoreTotal ?></strong>
                    (echelle : Faible=1, Moyen=2, Fort=3 par jour)</li>
            </ul>

            <p style="font-style:italic; color:#718096; margin-top:1rem;">
                Note : les pourcentages de reduction ci-dessus sont indicatifs et bases sur la jurisprudence
                cantonale vaudoise et federale. Chaque cas est evalue individuellement par les autorites
                de conciliation et les tribunaux compitents. Ce rapport constitue un element de preuve
                factuel ; il ne constitue pas un avis juridique.
            </p>
        </div>
    </section>

    <div class="rapport-footer">
        <p>FMEL — Chantier LET Campus Sante Dorigny</p>
        <p>Les informations publiees sur ce site le sont a titre informatif et n'ont pas de portee legale.</p>
        <p>Rapport genere automatiquement le <?= date('d.m.Y') ?> a <?= date('H:i') ?></p>
    </div>

</main>

<footer class="admin-footer">
    &copy; <?= date('Y') ?> FMEL &mdash; Interface d'administration CS-LET
</footer>

</body>
</html>
