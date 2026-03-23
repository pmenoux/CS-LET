<?php
/**
 * setup_db.php — CS-LET database initialisation
 * Run once after deployment: https://your-domain.tld/setup_db.php
 * Delete or protect this file after first run.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=UTF-8');

$db = getDB();
$ok = 0;
$errors = [];

function step(string $msg): void {
    echo $msg . PHP_EOL;
    flush();
}

function tableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :t"
    );
    $stmt->execute([':t' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

step('=== CS-LET — Setup base de données ===');
step('');

// ─── Table: nuisances ────────────────────────────────────────────────────────
if (tableExists($db, 'nuisances')) {
    step('[OK] Table `nuisances` existe déjà — skipped.');
} else {
    try {
        $db->exec("
            CREATE TABLE nuisances (
                id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
                date       DATE             NOT NULL,
                picto      VARCHAR(100)     NOT NULL,
                created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                   ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_date (date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        step('[OK] Table `nuisances` créée.');
        $ok++;
    } catch (PDOException $e) {
        $msg = '[ERREUR] nuisances: ' . $e->getMessage();
        step($msg);
        $errors[] = $msg;
    }
}

// ─── Table: users ────────────────────────────────────────────────────────────
if (tableExists($db, 'users')) {
    step('[OK] Table `users` existe déjà — skipped.');
} else {
    try {
        $db->exec("
            CREATE TABLE users (
                id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                username      VARCHAR(80)  NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role          ENUM('admin','travaux') NOT NULL DEFAULT 'travaux',
                created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        step('[OK] Table `users` créée.');
        $ok++;
    } catch (PDOException $e) {
        $msg = '[ERREUR] users: ' . $e->getMessage();
        step($msg);
        $errors[] = $msg;
    }
}

// ─── Table: planning_files ───────────────────────────────────────────────────
if (tableExists($db, 'planning_files')) {
    step('[OK] Table `planning_files` existe déjà — skipped.');
} else {
    try {
        $db->exec("
            CREATE TABLE planning_files (
                id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                filename      VARCHAR(255)  NOT NULL,
                original_name VARCHAR(255)  NOT NULL,
                uploaded_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                uploaded_by   INT UNSIGNED  NULL,
                PRIMARY KEY (id),
                KEY idx_uploaded_at (uploaded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        step('[OK] Table `planning_files` créée.');
        $ok++;
    } catch (PDOException $e) {
        $msg = '[ERREUR] planning_files: ' . $e->getMessage();
        step($msg);
        $errors[] = $msg;
    }
}

// ─── Default users ───────────────────────────────────────────────────────────
step('');
step('--- Création des utilisateurs par défaut ---');

$defaultUsers = [
    ['username' => 'admin',   'password' => 'FMEL2026!', 'role' => 'admin'],
    ['username' => 'travaux', 'password' => 'LET2026!',  'role' => 'travaux'],
];

foreach ($defaultUsers as $u) {
    // Check if user already exists
    $check = $db->prepare("SELECT id FROM users WHERE username = :username");
    $check->execute([':username' => $u['username']]);
    if ($check->fetchColumn()) {
        step('[OK] Utilisateur "' . $u['username'] . '" existe déjà — skipped.');
        continue;
    }

    try {
        $hash = password_hash($u['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $ins  = $db->prepare(
            "INSERT INTO users (username, password_hash, role) VALUES (:username, :hash, :role)"
        );
        $ins->execute([
            ':username' => $u['username'],
            ':hash'     => $hash,
            ':role'     => $u['role'],
        ]);
        step('[OK] Utilisateur "' . $u['username'] . '" créé (rôle: ' . $u['role'] . ').');
        $ok++;
    } catch (PDOException $e) {
        $msg = '[ERREUR] Utilisateur "' . $u['username'] . '": ' . $e->getMessage();
        step($msg);
        $errors[] = $msg;
    }
}

// ─── Summary ─────────────────────────────────────────────────────────────────
step('');
step('=== Résumé ===');
if (empty($errors)) {
    step('Tout s\'est déroulé sans erreur. (' . $ok . ' opération(s) effectuée(s))');
    step('');
    step('IMPORTANT : supprimez ou protégez ce fichier setup_db.php après la première exécution.');
} else {
    step($ok . ' opération(s) réussie(s), ' . count($errors) . ' erreur(s):');
    foreach ($errors as $err) {
        step('  ' . $err);
    }
}
