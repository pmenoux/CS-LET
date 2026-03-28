<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cslet_cslet');
define('DB_USER', 'cslet_cslet');
define('DB_PASS', 'dq24S9cuJl5T6GZj6x');
define('SITE_NAME', 'LET - Campus Sante - Dorigny');
define('SITE_URL', 'https://cs-let.ch');
define('BASE_PATH', '/');
define('ASSETS_PATH', '/assets');
define('DB_CHARSET', 'utf8mb4');
date_default_timezone_set('Europe/Zurich');
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) { die("Erreur BDD"); }
