<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

check_auth();
$user = get_user();

// ── Stats rapides ──────────────────────────────────────────────────────────
try {
    $db = getDB();

    $totalEntries = (int) $db->query('SELECT COUNT(*) FROM nuisances')->fetchColumn();

    $lastUpdate = $db->query(
        'SELECT MAX(updated_at) FROM nuisances'
    )->fetchColumn();

    $totalPlannings = (int) $db->query('SELECT COUNT(*) FROM planning_files')->fetchColumn();

    // Entrées du mois courant
    $monthStart = date('Y-m-01');
    $monthEnd   = date('Y-m-t');
    $stmt = $db->prepare(
        'SELECT date, picto FROM nuisances WHERE date BETWEEN ? AND ? ORDER BY date'
    );
    $stmt->execute([$monthStart, $monthEnd]);
    $monthEntries = $stmt->fetchAll();
    // Indexer par date pour l'affichage du calendrier
    $byDate = [];
    foreach ($monthEntries as $row) {
        $byDate[$row['date']] = $row['picto'];
    }
} catch (PDOException $e) {
    error_log('[CS-LET admin] Dashboard query error: ' . $e->getMessage());
    $totalEntries   = 0;
    $lastUpdate     = null;
    $totalPlannings = 0;
    $byDate         = [];
}

// ── Calendrier du mois courant ─────────────────────────────────────────────
$year  = (int) date('Y');
$month = (int) date('m');
$daysInMonth = (int) date('t');
$firstWeekday = (int) date('N', mktime(0, 0, 0, $month, 1, $year)); // 1=lun … 7=dim
$monthNames = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
];
$monthLabel = $monthNames[$month] . ' ' . $year;

// Chemin relatif vers les pictos depuis admin/
$pictoBase = '../assets/img/';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Admin CS-LET</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">

<!-- ── Navigation ─────────────────────────────────────────────────────── -->
<nav class="admin-nav">
    <div class="admin-nav-brand">
        <span class="badge-fmel">FMEL</span>
        <span class="nav-title">CS-LET Admin</span>
    </div>
    <ul class="admin-nav-links">
        <li><a href="index.php" class="active">Dashboard</a></li>
        <li><a href="calendrier.php">Calendrier</a></li>
        <li><a href="planning.php">Plannings</a></li>
        <li><a href="rapport.php">Rapport</a></li>
        <li><a href="guide.php">Guide</a></li>
    </ul>
    <div class="admin-nav-user">
        <span class="nav-username"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php if (is_admin()): ?>
            <span class="badge-role">Admin</span>
        <?php else: ?>
            <span class="badge-role badge-travaux">Travaux</span>
        <?php endif; ?>
        <a href="logout.php" class="btn-logout">Deconnexion</a>
    </div>
</nav>

<!-- ── Contenu ────────────────────────────────────────────────────────── -->
<main class="admin-main">
    <div class="page-header">
        <h1 class="page-title">Tableau de bord</h1>
        <p class="page-subtitle">Chantier Campus Sante Dorigny — LET</p>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $totalEntries ?></div>
            <div class="stat-label">Entrees nuisances</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($byDate) ?></div>
            <div class="stat-label">Entrees ce mois</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalPlannings ?></div>
            <div class="stat-label">Plannings uploades</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $lastUpdate ? date('d.m.Y', strtotime($lastUpdate)) : '—' ?></div>
            <div class="stat-label">Derniere mise a jour</div>
        </div>
    </div>

    <!-- Actions rapides -->
    <div class="quick-actions">
        <a href="calendrier.php" class="action-btn action-btn-primary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Gerer le calendrier
        </a>
        <a href="planning.php" class="action-btn action-btn-secondary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Uploader un planning
        </a>
    </div>

    <!-- Mini-calendrier du mois en cours -->
    <section class="dashboard-section">
        <div class="section-header">
            <h2 class="section-title">Apercu — <?= htmlspecialchars($monthLabel) ?></h2>
            <a href="calendrier.php" class="section-link">Modifier &rarr;</a>
        </div>
        <div class="mini-calendar">
            <div class="cal-weekdays">
                <div>Lun</div><div>Mar</div><div>Mer</div>
                <div>Jeu</div><div>Ven</div><div>Sam</div><div>Dim</div>
            </div>
            <div class="cal-grid">
                <?php
                // Cellules vides avant le premier jour
                for ($pad = 1; $pad < $firstWeekday; $pad++): ?>
                    <div class="cal-cell cal-cell-empty"></div>
                <?php endfor;
                // Jours du mois
                for ($day = 1; $day <= $daysInMonth; $day++):
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $picto   = $byDate[$dateStr] ?? null;
                    $isToday = ($dateStr === date('Y-m-d'));
                ?>
                    <div class="cal-cell <?= $picto ? 'has-entry' : '' ?> <?= $isToday ? 'is-today' : '' ?>">
                        <span class="cal-day-num"><?= $day ?></span>
                        <?php if ($picto): ?>
                            <img
                                src="<?= htmlspecialchars($pictoBase . $picto, ENT_QUOTES) ?>"
                                alt="<?= htmlspecialchars($picto, ENT_QUOTES) ?>"
                                class="cal-picto-thumb"
                            >
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </section>
</main>

<footer class="admin-footer">
    &copy; <?= date('Y') ?> FMEL &mdash; Interface d'administration CS-LET
</footer>

</body>
</html>
