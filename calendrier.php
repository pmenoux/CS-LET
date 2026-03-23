<?php
$pageTitle = 'Calendrier des nuisances';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// ─── French month names ──────────────────────────────────────────────────────
$moisFr = [
    1  => 'Janvier', 2  => 'Février',  3  => 'Mars',     4  => 'Avril',
    5  => 'Mai',     6  => 'Juin',     7  => 'Juillet',  8  => 'Août',
    9  => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
];
$joursFr = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

// ─── Parse ?mois=YYYY-MM, default to current month ──────────────────────────
$selectedMonth = '';
if (!empty($_GET['mois']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mois'])) {
    $selectedMonth = $_GET['mois'];
}
if (!$selectedMonth) {
    $selectedMonth = date('Y-m');
}

[$yearStr, $monthStr] = explode('-', $selectedMonth);
$year  = (int)$yearStr;
$month = (int)$monthStr;

// Clamp to sane range
if ($month < 1)  { $month = 1; }
if ($month > 12) { $month = 12; }

// Prev / next month strings
$prevTs    = mktime(0, 0, 0, $month - 1, 1, $year);
$nextTs    = mktime(0, 0, 0, $month + 1, 1, $year);
$prevMonth = date('Y-m', $prevTs);
$nextMonth = date('Y-m', $nextTs);

// First and last day of the month
$firstDay  = mktime(0, 0, 0, $month, 1, $year);
$lastDay   = (int)date('t', $firstDay);

// Day-of-week for first day: 1=Monday … 7=Sunday (ISO)
$startDow  = (int)date('N', $firstDay);  // 1=Mon, 7=Sun

// ─── Picto definitions ───────────────────────────────────────────────────────
// key = filename stored in DB / assets/img/
$pictoLib = [
    // In-use pictos (shown in legend + calendar)
    'PICTO_Camion_Faible.png'       => ['label' => 'Camion — Nuisance faible',             'inuse' => true],
    'PICTO_Camion_Faible_pous.png'  => ['label' => 'Camion — Nuisance faible + poussière', 'inuse' => true],
    'PICTO_Pelleteuse_Faible.png'   => ['label' => 'Pelleteuse — Nuisance faible',         'inuse' => true],
    'PICTO_Pieux_Moyen.png'         => ['label' => 'Pieux — Nuisance moyenne',             'inuse' => true],
    'PICTO_Pieux_Fort.png'          => ['label' => 'Pieux — Nuisance forte',               'inuse' => true],
    // Library pictos (future use)
    'PICTO_Camion_Fort.png'         => ['label' => 'Camion — Nuisance forte',              'inuse' => false],
    'PICTO_Camion_Moyen.png'        => ['label' => 'Camion — Nuisance moyenne',            'inuse' => false],
    'PICTO_Camion_Moyen_pous.png'   => ['label' => 'Camion — Nuisance moyenne + poussière','inuse' => false],
    'PICTO_Pelleteuse_Moyen.png'    => ['label' => 'Pelleteuse — Nuisance moyenne',        'inuse' => false],
    'PICTO_Pelleteuse_Fort.png'     => ['label' => 'Pelleteuse — Nuisance forte',          'inuse' => false],
    'PICTO_Camion_Fort_pous.png'    => ['label' => 'Camion — Nuisance forte + poussière',  'inuse' => false],
];

// ─── Query DB ────────────────────────────────────────────────────────────────
$nuisancesByDay = [];
try {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT DAY(date) AS jour, picto
         FROM nuisances
         WHERE YEAR(date) = :year AND MONTH(date) = :month
         ORDER BY date ASC"
    );
    $stmt->execute([':year' => $year, ':month' => $month]);
    foreach ($stmt->fetchAll() as $row) {
        $nuisancesByDay[(int)$row['jour']] = $row['picto'];
    }
} catch (PDOException $e) {
    error_log('[CS-LET] Calendar query failed: ' . $e->getMessage());
    // Show calendar without data rather than crashing
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section--page">
    <div class="container">

        <h1 class="page-title">Calendrier des nuisances</h1>

        <!-- ── Month navigation ──────────────────────────────────────────── -->
        <div class="cal-nav">
            <a href="calendrier.php?mois=<?= htmlspecialchars($prevMonth) ?>"
               class="cal-nav__arrow" aria-label="Mois précédent">&larr;</a>

            <h2 class="cal-nav__month">
                <?= htmlspecialchars($moisFr[$month]) ?> <?= $year ?>
            </h2>

            <a href="calendrier.php?mois=<?= htmlspecialchars($nextMonth) ?>"
               class="cal-nav__arrow" aria-label="Mois suivant">&rarr;</a>
        </div>

        <!-- ── Desktop grid calendar ─────────────────────────────────────── -->
        <div class="cal-grid" aria-label="Calendrier <?= htmlspecialchars($moisFr[$month]) ?> <?= $year ?>">

            <!-- Day-of-week headers -->
            <?php foreach ($joursFr as $j): ?>
            <div class="cal-grid__header"><?= $j ?></div>
            <?php endforeach; ?>

            <!-- Empty cells before first day -->
            <?php for ($e = 1; $e < $startDow; $e++): ?>
            <div class="cal-grid__cell cal-grid__cell--empty"></div>
            <?php endfor; ?>

            <!-- Day cells -->
            <?php for ($day = 1; $day <= $lastDay; $day++):
                $picto    = $nuisancesByDay[$day] ?? null;
                $dateStr  = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $isToday  = ($dateStr === date('Y-m-d'));
                $classes  = 'cal-grid__cell';
                if ($isToday)  $classes .= ' cal-grid__cell--today';
                if ($picto)    $classes .= ' cal-grid__cell--nuisance';
            ?>
            <div class="<?= $classes ?>"
                 data-date="<?= $dateStr ?>"
                 title="<?= $dateStr . ($picto ? ': ' . htmlspecialchars($pictoLib[$picto]['label'] ?? $picto) : '') ?>">
                <span class="cal-grid__day-num"><?= $day ?></span>
                <?php if ($picto && isset($pictoLib[$picto])): ?>
                <div class="cal-grid__picto">
                    <img src="assets/img/<?= htmlspecialchars($picto) ?>"
                         alt="<?= htmlspecialchars($pictoLib[$picto]['label']) ?>">
                </div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>

            <!-- Fill trailing cells to complete last week row -->
            <?php
            $totalCells = ($startDow - 1) + $lastDay;
            $trailing   = (7 - ($totalCells % 7)) % 7;
            for ($t = 0; $t < $trailing; $t++):
            ?>
            <div class="cal-grid__cell cal-grid__cell--empty"></div>
            <?php endfor; ?>

        </div><!-- /.cal-grid -->

        <!-- ── Mobile list view ───────────────────────────────────────────── -->
        <div class="cal-list" aria-label="Liste des nuisances <?= htmlspecialchars($moisFr[$month]) ?> <?= $year ?>">
            <?php if (empty($nuisancesByDay)): ?>
                <p class="cal-list__empty">Aucune nuisance enregistrée ce mois-ci.</p>
            <?php else: ?>
                <ul class="cal-list__ul">
                    <?php foreach ($nuisancesByDay as $day => $picto):
                        $dateTs  = mktime(0, 0, 0, $month, $day, $year);
                        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        // ISO day-of-week → French label
                        $dowIso  = (int)date('N', $dateTs);
                        $dowNames = [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',
                                     5=>'Vendredi',6=>'Samedi',7=>'Dimanche'];
                        $dayLabel = $dowNames[$dowIso] . ' ' . $day . ' ' . $moisFr[$month];
                    ?>
                    <li class="cal-list__item">
                        <span class="cal-list__date"><?= htmlspecialchars($dayLabel) ?></span>
                        <?php if (isset($pictoLib[$picto])): ?>
                        <span class="cal-list__picto-wrap">
                            <img src="assets/img/<?= htmlspecialchars($picto) ?>"
                                 alt="<?= htmlspecialchars($pictoLib[$picto]['label']) ?>"
                                 class="cal-list__picto-img">
                            <span class="cal-list__picto-label"><?= htmlspecialchars($pictoLib[$picto]['label']) ?></span>
                        </span>
                        <?php else: ?>
                        <span class="cal-list__picto-label"><?= htmlspecialchars($picto) ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div><!-- /.cal-list -->

        <!-- ── Legend ────────────────────────────────────────────────────── -->
        <div class="cal-legend">
            <h3 class="cal-legend__title">Légende</h3>

            <div class="cal-legend__section">
                <h4 class="cal-legend__subtitle">Pictos utilisés</h4>
                <ul class="cal-legend__list">
                    <?php foreach ($pictoLib as $file => $info):
                        if (!$info['inuse']) continue;
                        $imgPath = BASE_PATH . '/assets/img/' . $file;
                    ?>
                    <li class="cal-legend__item">
                        <?php if (file_exists($imgPath)): ?>
                        <img src="assets/img/<?= htmlspecialchars($file) ?>"
                             alt="<?= htmlspecialchars($info['label']) ?>"
                             class="cal-legend__img">
                        <?php else: ?>
                        <span class="cal-legend__img-missing">[image manquante]</span>
                        <?php endif; ?>
                        <span class="cal-legend__label"><?= htmlspecialchars($info['label']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="cal-legend__section cal-legend__section--future">
                <h4 class="cal-legend__subtitle">Bibliothèque complète (usage futur)</h4>
                <ul class="cal-legend__list">
                    <?php foreach ($pictoLib as $file => $info):
                        if ($info['inuse']) continue;
                        $imgPath = BASE_PATH . '/assets/img/' . $file;
                    ?>
                    <li class="cal-legend__item cal-legend__item--future">
                        <?php if (file_exists($imgPath)): ?>
                        <img src="assets/img/<?= htmlspecialchars($file) ?>"
                             alt="<?= htmlspecialchars($info['label']) ?>"
                             class="cal-legend__img">
                        <?php else: ?>
                        <span class="cal-legend__img-missing"><?= htmlspecialchars($file) ?></span>
                        <?php endif; ?>
                        <span class="cal-legend__label"><?= htmlspecialchars($info['label']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

        </div><!-- /.cal-legend -->

    </div><!-- /.container -->
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
