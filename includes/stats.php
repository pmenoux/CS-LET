<?php
/**
 * stats.php — Compteur de visites sans cookie, conforme RGPD
 * Inclure dans header.php pour tracker automatiquement chaque page.
 * Stocke : page, date, heure, referrer (domaine seul), device type.
 * Ne stocke PAS : IP, user-agent complet, identifiants.
 */
require_once __DIR__ . '/db.php';

function track_visit(): void
{
    // Ne pas tracker les bots connus
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (preg_match('/bot|crawl|spider|slurp|curl|wget/i', $ua)) return;

    // Ne pas tracker les pages admin ou les assets
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (str_contains($uri, 'cslet-admin') || str_contains($uri, 'setup_db') || str_contains($uri, 'import_')) return;

    // Page visitée (nettoyée)
    $page = parse_url($uri, PHP_URL_PATH) ?: '/';
    $page = substr($page, 0, 255);

    // Referrer (domaine seul, pas l'URL complète — respect vie privée)
    $referrer = '';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $refHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        // Ignorer le self-referral
        $ownHost = $_SERVER['HTTP_HOST'] ?? '';
        if ($refHost && $refHost !== $ownHost) {
            $referrer = substr($refHost, 0, 255);
        }
    }

    // Type de device (basique, sans stocker le UA complet)
    $device = 'desktop';
    if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
        $device = preg_match('/iPad|Tablet/i', $ua) ? 'tablet' : 'mobile';
    }

    try {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO page_views (page, visited_at, referrer_domain, device)
             VALUES (?, NOW(), ?, ?)'
        );
        $stmt->execute([$page, $referrer, $device]);
    } catch (\PDOException $e) {
        // Silencieux — ne jamais casser le site pour des stats
        error_log('[CS-LET stats] ' . $e->getMessage());
    }
}

track_visit();
