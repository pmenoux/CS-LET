<?php
// ─── Database credentials ────────────────────────────────────────────────────
define('DB_HOST',   'gfeu.myd.infomaniak.com');
define('DB_NAME',   'gfeu_cslet');
define('DB_USER',   'gfeu_claude');
define('DB_PASS',   'Vive Neostar 26!');
define('DB_CHARSET','utf8mb4');

// ─── Site constants ──────────────────────────────────────────────────────────
define('SITE_NAME', 'LET - Campus Santé - Dorigny');
define('SITE_URL',  'https://cslet.ldsnet.ch');

// ─── Paths ───────────────────────────────────────────────────────────────────
define('BASE_PATH',    __DIR__ . '/..');
define('UPLOADS_PATH', BASE_PATH . '/uploads/planning');
define('ASSETS_PATH',  BASE_PATH . '/assets');

// ─── Timezone ────────────────────────────────────────────────────────────────
date_default_timezone_set('Europe/Zurich');
