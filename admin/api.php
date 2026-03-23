<?php
/**
 * api.php — Endpoint AJAX pour la gestion des nuisances
 * Toutes les réponses sont en JSON.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Forcer JSON pour toutes les réponses
header('Content-Type: application/json; charset=utf-8');
// Empêcher la mise en cache
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// ── Vérification d'authentification ──────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifie.']);
    exit;
}

// ── Lecture de l'action ───────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = '';

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
} elseif ($method === 'POST') {
    // Support JSON body ou form data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body   = file_get_contents('php://input');
        $data   = json_decode($body, true) ?? [];
        $action = $data['action'] ?? '';
    } else {
        $data   = $_POST;
        $action = $data['action'] ?? '';
    }

    // Vérification CSRF pour toutes les requêtes POST
    $csrfIn = $data['csrf_token']
           ?? $_SERVER['HTTP_X_CSRF_TOKEN']
           ?? '';
    if (!hash_equals(csrf_token(), $csrfIn)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Token CSRF invalide.']);
        exit;
    }
}

// Liste des pictos autorisés (whitelist)
$allowedPictos = [
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

/**
 * Valide un format de date YYYY-MM-DD.
 */
function validateDate(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
    [$y, $m, $d] = explode('-', $date);
    return checkdate((int)$m, (int)$d, (int)$y);
}

// ── Routeur ────────────────────────────────────────────────────────────────

try {
    $db = getDB();

    switch ($action) {

        // ── GET get_month ────────────────────────────────────────────────
        case 'get_month':
            $monthParam = $_GET['month'] ?? '';
            if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Parametre month invalide (YYYY-MM attendu).']);
                exit;
            }
            [$y, $m] = explode('-', $monthParam);
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$m, (int)$y);
            $start = $monthParam . '-01';
            $end   = $monthParam . '-' . str_pad($daysInMonth, 2, '0', STR_PAD_LEFT);

            $stmt = $db->prepare('SELECT date, picto FROM nuisances WHERE date BETWEEN ? AND ? ORDER BY date');
            $stmt->execute([$start, $end]);
            $rows = $stmt->fetchAll();

            // Formater en objet keyed par date
            $result = [];
            foreach ($rows as $row) {
                $result[$row['date']] = $row['picto'];
            }
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        // ── POST set_nuisance ─────────────────────────────────────────────
        case 'set_nuisance':
            $date  = $data['date']  ?? '';
            $picto = $data['picto'] ?? '';

            if (!validateDate($date)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Date invalide.']);
                exit;
            }
            if (!in_array($picto, $allowedPictos, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Picto non autorise.']);
                exit;
            }

            $stmt = $db->prepare(
                'INSERT INTO nuisances (date, picto, created_at, updated_at)
                 VALUES (?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE picto = VALUES(picto), updated_at = NOW()'
            );
            $stmt->execute([$date, $picto]);

            echo json_encode([
                'success' => true,
                'message' => 'Nuisance enregistree.',
                'date'    => $date,
                'picto'   => $picto,
            ]);
            break;

        // ── POST delete_nuisance ──────────────────────────────────────────
        case 'delete_nuisance':
            $date = $data['date'] ?? '';

            if (!validateDate($date)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Date invalide.']);
                exit;
            }

            $stmt = $db->prepare('DELETE FROM nuisances WHERE date = ?');
            $stmt->execute([$date]);

            echo json_encode([
                'success'       => true,
                'message'       => 'Entree supprimee.',
                'date'          => $date,
                'rows_affected' => $stmt->rowCount(),
            ]);
            break;

        // ── POST bulk_nuisance ────────────────────────────────────────────
        case 'bulk_nuisance':
            $startDate = $data['start_date'] ?? '';
            $endDate   = $data['end_date']   ?? '';
            $picto     = $data['picto']      ?? '';

            if (!validateDate($startDate) || !validateDate($endDate)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Dates invalides.']);
                exit;
            }
            if ($endDate < $startDate) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'La date de fin doit etre superieure ou egale a la date de debut.']);
                exit;
            }
            if (!in_array($picto, $allowedPictos, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Picto non autorise.']);
                exit;
            }

            // Limiter la plage à 31 jours pour éviter les abus
            $start  = new DateTime($startDate);
            $end    = new DateTime($endDate);
            $diffDays = (int)$start->diff($end)->days;
            if ($diffDays > 31) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'La plage ne peut pas depasser 31 jours.']);
                exit;
            }

            $stmt = $db->prepare(
                'INSERT INTO nuisances (date, picto, created_at, updated_at)
                 VALUES (?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE picto = VALUES(picto), updated_at = NOW()'
            );

            $db->beginTransaction();
            $count   = 0;
            $current = clone $start;
            while ($current <= $end) {
                $stmt->execute([$current->format('Y-m-d'), $picto]);
                $count++;
                $current->modify('+1 day');
            }
            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => $count . ' entrees enregistrees.',
                'count'   => $count,
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action inconnue ou manquante.']);
            break;
    }

} catch (PDOException $e) {
    error_log('[CS-LET api] PDO error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur. Veuillez reessayer.']);
} catch (Throwable $e) {
    error_log('[CS-LET api] Unexpected error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur inattendue.']);
}
