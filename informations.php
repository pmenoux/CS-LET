<?php
$pageTitle = 'Informations';
require_once __DIR__ . '/includes/config.php';

// ─── Find latest uploaded planning files ────────────────────────────────────
$uploadsDir   = BASE_PATH . '/uploads/planning/';
$latestImage  = null;
$latestPdf    = null;

if (is_dir($uploadsDir)) {
    $files = scandir($uploadsDir);
    $images = [];
    $pdfs   = [];

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $fullPath = $uploadsDir . $file;
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $images[$fullPath] = filemtime($fullPath);
        } elseif ($ext === 'pdf') {
            $pdfs[$fullPath] = filemtime($fullPath);
        }
    }

    if (!empty($images)) {
        arsort($images);
        $latestImagePath = array_key_first($images);
        $latestImage     = 'uploads/planning/' . basename($latestImagePath);
    }
    if (!empty($pdfs)) {
        arsort($pdfs);
        $latestPdfPath = array_key_first($pdfs);
        $latestPdf     = 'uploads/planning/' . basename($latestPdfPath);
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="section section--page">
    <div class="container">

        <h1 class="page-title">Planning des nuisances</h1>
        <p class="page-subtitle">Planning des principales étapes des travaux</p>

        <!-- ── Latest planning image ───────────────────────────────────────── -->
        <div class="planning-block">
            <?php if ($latestImage): ?>
                <figure class="planning-figure">
                    <img src="<?= htmlspecialchars($latestImage) ?>"
                         alt="Planning des nuisances — dernière mise à jour"
                         class="planning-image">
                </figure>
            <?php else: ?>
                <div class="planning-placeholder">
                    <p>Le planning n'a pas encore été mis en ligne.<br>Revenez prochainement.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── PDF download ────────────────────────────────────────────────── -->
        <?php if ($latestPdf): ?>
        <div class="download-block">
            <a href="<?= htmlspecialchars($latestPdf) ?>"
               class="btn btn--primary"
               download>
                Télécharger le planning (PDF)
            </a>
        </div>
        <?php endif; ?>

        <!-- ── Schema palplanches ──────────────────────────────────────────── -->
        <div class="schema-block">
            <h2 class="content-title">Schéma des palplanches</h2>
            <?php
            $schemaPath = 'assets/img/schema_palplanches.png';
            if (file_exists(BASE_PATH . '/' . $schemaPath)):
            ?>
                <figure class="planning-figure">
                    <img src="<?= htmlspecialchars($schemaPath) ?>"
                         alt="Schéma des palplanches"
                         class="planning-image">
                </figure>
            <?php else: ?>
                <p class="placeholder-text">Schéma non disponible pour le moment.</p>
            <?php endif; ?>
        </div>

    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
