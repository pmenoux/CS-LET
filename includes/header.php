<?php
require_once __DIR__ . '/config.php';

// Detect active page for nav highlighting
$currentFile = basename($_SERVER['PHP_SELF']);

// Compute root-relative path prefix (works from / or /admin/)
$depth      = substr_count(trim(dirname($_SERVER['PHP_SELF']), '/'), '/');
$rootPrefix = str_repeat('../', $depth);

$navPages = [
    'index.php'        => 'Accueil',
    'a-propos.php'     => 'À propos',
    'calendrier.php'   => 'Calendrier des nuisances',
    'informations.php' => 'Informations',
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars(SITE_NAME) ?> — Informations chantier FMEL">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?><?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="<?= $rootPrefix ?>assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <a href="<?= $rootPrefix ?>index.php" class="logo-link">
            <img src="<?= $rootPrefix ?>assets/img/logo_fmel.png"
                 alt="Logo FMEL"
                 height="80"
                 class="logo">
        </a>
        <div class="site-title-block">
            <span class="site-tagline"><?= htmlspecialchars(SITE_NAME) ?></span>
        </div>
        <button class="nav-toggle" aria-label="Menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<nav class="site-nav" id="site-nav">
    <div class="nav-inner">
        <ul class="nav-list">
            <?php foreach ($navPages as $file => $label): ?>
            <li class="nav-item">
                <a href="<?= $rootPrefix ?><?= $file ?>"
                   class="nav-link<?= ($currentFile === $file) ? ' nav-link--active' : '' ?>">
                    <?= htmlspecialchars($label) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>

<main class="site-main">
