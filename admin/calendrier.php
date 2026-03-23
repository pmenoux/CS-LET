<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

check_auth();
$user = get_user();

// ── Paramètres mois/an ────────────────────────────────────────────────────
$year  = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y');
$month = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('m');

// Clamp valeurs
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
$firstWeekday = (int) date('N', mktime(0, 0, 0, $month, 1, $year));

$monthNames = [
    1 => 'Janvier', 2 => 'Fevrier', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Aout',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Decembre',
];
$monthLabel = $monthNames[$month] . ' ' . $year;

// Navigation prev/next
$prevM = $month - 1; $prevY = $year;
if ($prevM < 1) { $prevM = 12; $prevY--; }
$nextM = $month + 1; $nextY = $year;
if ($nextM > 12) { $nextM = 1; $nextY++; }

// ── Entrées du mois depuis la DB ──────────────────────────────────────────
$byDate = [];
try {
    $db = getDB();
    $monthStart = sprintf('%04d-%02d-01', $year, $month);
    $monthEnd   = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
    $stmt = $db->prepare('SELECT date, picto FROM nuisances WHERE date BETWEEN ? AND ? ORDER BY date');
    $stmt->execute([$monthStart, $monthEnd]);
    foreach ($stmt->fetchAll() as $row) {
        $byDate[$row['date']] = $row['picto'];
    }
} catch (PDOException $e) {
    error_log('[CS-LET admin] Calendrier query error: ' . $e->getMessage());
}

// ── Liste des pictos disponibles ──────────────────────────────────────────
$pictos = [
    'PICTO_Camion_Faible.png',
    'PICTO_Camion_Faible_pous.png',
    'PICTO_Camion_Moyen.png',
    'PICTO_Camion_Moyen_pous.png',
    'PICTO_Camion_Fort.png',
    'PICTO_Camion_Fort_pous.png',
    'PICTO_Pelleteuse_Faible.png',
    'PICTO_Pelleteuse_Moyen.png',
    'PICTO_Pelleteuse_Fort.png',
    'PICTO_Pieux_Moyen.png',
    'PICTO_Pieux_Fort.png',
];

// Labels lisibles pour les pictos
$pictoLabels = [
    'PICTO_Camion_Faible.png'       => 'Camion Faible',
    'PICTO_Camion_Faible_pous.png'  => 'Camion Faible + Poussiere',
    'PICTO_Camion_Moyen.png'        => 'Camion Moyen',
    'PICTO_Camion_Moyen_pous.png'   => 'Camion Moyen + Poussiere',
    'PICTO_Camion_Fort.png'         => 'Camion Fort',
    'PICTO_Camion_Fort_pous.png'    => 'Camion Fort + Poussiere',
    'PICTO_Pelleteuse_Faible.png'   => 'Pelleteuse Faible',
    'PICTO_Pelleteuse_Moyen.png'    => 'Pelleteuse Moyen',
    'PICTO_Pelleteuse_Fort.png'     => 'Pelleteuse Fort',
    'PICTO_Pieux_Moyen.png'         => 'Pieux Moyen',
    'PICTO_Pieux_Fort.png'          => 'Pieux Fort',
];

$csrfToken  = csrf_token();
$pictoBase  = '../assets/img/';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendrier — Admin CS-LET</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">

<!-- ── Navigation ────────────────────────────────────────────────────────── -->
<nav class="admin-nav">
    <div class="admin-nav-brand">
        <span class="badge-fmel">FMEL</span>
        <span class="nav-title">CS-LET Admin</span>
    </div>
    <ul class="admin-nav-links">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="calendrier.php" class="active">Calendrier</a></li>
        <li><a href="planning.php">Plannings</a></li>
        <li><a href="rapport.php">Rapport</a></li>
    </ul>
    <div class="admin-nav-user">
        <span class="nav-username"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></span>
        <a href="logout.php" class="btn-logout">Deconnexion</a>
    </div>
</nav>

<!-- ── Contenu ────────────────────────────────────────────────────────────── -->
<main class="admin-main">
    <div class="page-header">
        <h1 class="page-title">Calendrier des nuisances</h1>
        <p class="page-subtitle">Cliquez sur un jour pour assigner ou modifier un picto</p>
    </div>

    <!-- Notification globale -->
    <div id="notif-bar" class="notif-bar" aria-live="polite" style="display:none;"></div>

    <!-- Navigation mois + mode bulk -->
    <div class="cal-toolbar">
        <div class="cal-nav">
            <a href="calendrier.php?y=<?= $prevY ?>&m=<?= $prevM ?>" class="btn-nav" aria-label="Mois precedent">&#8592;</a>
            <span class="cal-month-label"><?= htmlspecialchars($monthLabel) ?></span>
            <a href="calendrier.php?y=<?= $nextY ?>&m=<?= $nextM ?>" class="btn-nav" aria-label="Mois suivant">&#8594;</a>
        </div>
        <button id="bulk-toggle" class="btn-outline" type="button">Mode selection multiple</button>
    </div>

    <!-- Interface mode bulk (masquée par défaut) -->
    <div id="bulk-panel" class="bulk-panel" style="display:none;">
        <div class="bulk-panel-inner">
            <h3>Assigner un picto en masse</h3>
            <div class="bulk-fields">
                <div class="bulk-field">
                    <label for="bulk-start">Du</label>
                    <input type="date" id="bulk-start" name="bulk_start"
                           min="<?= sprintf('%04d-%02d-01', $year, $month) ?>"
                           max="<?= sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth) ?>">
                </div>
                <div class="bulk-field">
                    <label for="bulk-end">Au</label>
                    <input type="date" id="bulk-end" name="bulk_end"
                           min="<?= sprintf('%04d-%02d-01', $year, $month) ?>"
                           max="<?= sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth) ?>">
                </div>
            </div>
            <div class="bulk-picto-selector">
                <p class="bulk-picto-label">Picto a assigner :</p>
                <div class="picto-grid" id="bulk-picto-grid">
                    <?php foreach ($pictos as $p): ?>
                    <button
                        type="button"
                        class="picto-btn"
                        data-picto="<?= htmlspecialchars($p, ENT_QUOTES) ?>"
                        title="<?= htmlspecialchars($pictoLabels[$p] ?? $p, ENT_QUOTES) ?>"
                    >
                        <img src="<?= htmlspecialchars($pictoBase . $p, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($pictoLabels[$p] ?? $p, ENT_QUOTES) ?>">
                        <span><?= htmlspecialchars($pictoLabels[$p] ?? $p, ENT_QUOTES) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" id="bulk-selected-picto" value="">
            </div>
            <div class="bulk-actions">
                <button id="bulk-apply" class="btn-primary" type="button" disabled>
                    Appliquer a la plage
                </button>
                <button id="bulk-cancel" class="btn-outline" type="button">Annuler</button>
            </div>
        </div>
    </div>

    <!-- Calendrier principal ───────────────────────────────────────────────── -->
    <div class="cal-editor-wrap">
        <div class="cal-weekdays">
            <div>Lun</div><div>Mar</div><div>Mer</div>
            <div>Jeu</div><div>Ven</div><div>Sam</div><div>Dim</div>
        </div>
        <div class="cal-grid" id="cal-grid">
            <?php
            for ($pad = 1; $pad < $firstWeekday; $pad++):
            ?>
                <div class="cal-cell cal-cell-empty"></div>
            <?php
            endfor;
            for ($day = 1; $day <= $daysInMonth; $day++):
                $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $picto    = $byDate[$dateStr] ?? null;
                $isToday  = ($dateStr === date('Y-m-d'));
                $isPast   = ($dateStr < date('Y-m-d'));
                $weekday  = (int) date('N', mktime(0, 0, 0, $month, $day, $year));
                $isWeekend = $weekday >= 6;
            ?>
                <div
                    class="cal-cell cal-cell-day <?= $picto ? 'has-entry' : '' ?> <?= $isToday ? 'is-today' : '' ?> <?= $isWeekend ? 'is-weekend' : '' ?>"
                    data-date="<?= $dateStr ?>"
                    data-picto="<?= htmlspecialchars($picto ?? '', ENT_QUOTES) ?>"
                    tabindex="0"
                    role="button"
                    aria-label="<?= $day . ' ' . $monthLabel . ($picto ? ' — ' . ($pictoLabels[$picto] ?? $picto) : '') ?>"
                >
                    <span class="cal-day-num"><?= $day ?></span>
                    <?php if ($picto): ?>
                        <img
                            src="<?= htmlspecialchars($pictoBase . $picto, ENT_QUOTES) ?>"
                            alt="<?= htmlspecialchars($pictoLabels[$picto] ?? $picto, ENT_QUOTES) ?>"
                            class="cal-picto-thumb"
                            loading="lazy"
                        >
                        <span class="cal-picto-label"><?= htmlspecialchars($pictoLabels[$picto] ?? $picto, ENT_QUOTES) ?></span>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</main>

<!-- ── Modal édition jour ─────────────────────────────────────────────────── -->
<div id="modal-overlay" class="modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-box">
        <div class="modal-header">
            <h2 class="modal-title" id="modal-title">Modifier le picto</h2>
            <button class="modal-close" id="modal-close" aria-label="Fermer">&times;</button>
        </div>
        <div class="modal-body">
            <p class="modal-date-label">Date selectionnee : <strong id="modal-date-display"></strong></p>
            <input type="hidden" id="modal-date-value">

            <p class="picto-selector-label">Choisir un picto :</p>
            <div class="picto-grid" id="modal-picto-grid">
                <?php foreach ($pictos as $p): ?>
                <button
                    type="button"
                    class="picto-btn"
                    data-picto="<?= htmlspecialchars($p, ENT_QUOTES) ?>"
                    title="<?= htmlspecialchars($pictoLabels[$p] ?? $p, ENT_QUOTES) ?>"
                >
                    <img src="<?= htmlspecialchars($pictoBase . $p, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($pictoLabels[$p] ?? $p, ENT_QUOTES) ?>">
                    <span><?= htmlspecialchars($pictoLabels[$p] ?? $p, ENT_QUOTES) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button id="modal-save" class="btn-primary" type="button" disabled>Enregistrer</button>
            <button id="modal-delete" class="btn-danger" type="button">Supprimer l'entree</button>
            <button id="modal-cancel" class="btn-outline" type="button">Annuler</button>
        </div>
    </div>
</div>

<!-- Données PHP passées au JS -->
<script>
    const CSRF_TOKEN    = <?= json_encode($csrfToken) ?>;
    const API_URL       = 'api.php';
    const CURRENT_YEAR  = <?= json_encode($year) ?>;
    const CURRENT_MONTH = <?= json_encode(str_pad($month, 2, '0', STR_PAD_LEFT)) ?>;
</script>
<script src="assets/admin.js"></script>
</body>
</html>
