<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=UTF-8');

$db = getDB();

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS page_views (
            id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            page            VARCHAR(255)    NOT NULL,
            visited_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            referrer_domain VARCHAR(255)    NOT NULL DEFAULT '',
            device          ENUM('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
            PRIMARY KEY (id),
            KEY idx_visited_at (visited_at),
            KEY idx_page (page)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "OK — Table page_views creee.\n";
    echo "Supprimez ce fichier setup_stats.php.\n";
} catch (PDOException $e) {
    echo "ERREUR : " . $e->getMessage() . "\n";
}
