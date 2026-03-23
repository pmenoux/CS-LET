<?php
/**
 * auth.php — Session-based authentication helpers for CS-LET admin
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ]);
}

/**
 * Vérifie que l'utilisateur est connecté.
 * Redirige vers login.php si la session est absente ou expirée.
 */
function check_auth(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . admin_url('login.php'));
        exit;
    }

    // Régénération de session toutes les 30 minutes (protection fixation de session)
    if (isset($_SESSION['last_regen']) && time() - $_SESSION['last_regen'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

/**
 * Retourne les informations de l'utilisateur connecté.
 *
 * @return array{id: int, username: string, role: string}|null
 */
function get_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'       => (int) $_SESSION['user_id'],
        'username' => (string) $_SESSION['username'],
        'role'     => (string) $_SESSION['role'],
    ];
}

/**
 * Retourne vrai si l'utilisateur connecté a le rôle admin.
 */
function is_admin(): bool
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Génère (ou récupère) le token CSRF de la session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie le token CSRF passé dans $_POST ou dans le header HTTP.
 * Arrête l'exécution et renvoie une erreur 403 si invalide.
 */
function verify_csrf(): void
{
    $token = $_POST['csrf_token']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? '';

    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Token CSRF invalide.']);
        exit;
    }
}

/**
 * Construit l'URL d'une page admin à partir du nom de fichier.
 * Compatible avec les deux niveaux d'imbrication (admin/includes → admin/).
 */
function admin_url(string $page = 'index.php'): string
{
    // Détermine le chemin de base admin en fonction du script courant
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if (str_contains($script, DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR)) {
        return '../' . $page;
    }
    return $page;
}
