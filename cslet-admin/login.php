<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Si déjà connecté, rediriger vers le dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $db   = getDB();
            $stmt = $db->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Authentification réussie
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['last_regen'] = time();
                // Générer le token CSRF
                csrf_token();

                header('Location: index.php');
                exit;
            } else {
                // Délai constant pour éviter le timing attack
                usleep(300000);
                $error = 'Identifiant ou mot de passe incorrect.';
            }
        } catch (PDOException $e) {
            error_log('[CS-LET admin] Login error: ' . $e->getMessage());
            $error = 'Erreur de connexion à la base de données.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Admin CS-LET</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .login-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 16px rgba(44,82,130,0.10);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 380px;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-brand .site-label {
            display: inline-block;
            background: #2c5282;
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            padding: 0.25rem 0.75rem;
            border-radius: 3px;
            margin-bottom: 0.75rem;
        }

        .login-brand h1 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a2d4a;
            line-height: 1.3;
        }

        .login-brand p {
            font-size: 0.82rem;
            color: #718096;
            margin-top: 0.35rem;
        }

        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 1.5rem 0;
        }

        .form-group {
            margin-bottom: 1.1rem;
        }

        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 0.4rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.65rem 0.9rem;
            border: 1px solid #cbd5e0;
            border-radius: 5px;
            font-size: 0.95rem;
            color: #2d3748;
            background: #f7fafc;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #2c5282;
            box-shadow: 0 0 0 3px rgba(44,82,130,0.12);
            background: #fff;
        }

        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: #2c5282;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
            letter-spacing: 0.02em;
            transition: background 0.2s;
        }

        .btn-login:hover { background: #1a365d; }
        .btn-login:active { background: #153060; }

        .alert {
            border-radius: 5px;
            padding: 0.7rem 1rem;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }
        .alert-error {
            background: #fff5f5;
            border: 1px solid #fc8181;
            color: #c53030;
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.75rem;
            color: #a0aec0;
        }

        .login-footer a {
            color: #718096;
            text-decoration: none;
        }
        .login-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-brand">
            <span class="site-label">FMEL</span>
            <h1>CS-LET — Admin</h1>
            <p>Chantier Campus Santé Dorigny</p>
        </div>

        <hr class="divider">

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="login.php" autocomplete="on" novalidate>
            <div class="form-group">
                <label for="username">Identifiant</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="username"
                    autofocus
                    required
                >
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>
            <button type="submit" class="btn-login">Se connecter</button>
        </form>
    </div>

    <div class="login-footer">
        <a href="../index.php">&larr; Retour au site public</a><br>
        &copy; <?= date('Y') ?> FMEL &mdash; Administration réservée
    </div>
</body>
</html>
