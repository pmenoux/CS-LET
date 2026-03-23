<?php
require_once __DIR__ . '/config.php';

/**
 * Returns a PDO singleton connection.
 * Throws PDOException on failure (caught by the caller or the global error handler).
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Ne pas exposer les credentials en production
            error_log('[CS-LET] DB connection failed: ' . $e->getMessage());
            die('<p style="font-family:sans-serif;color:#c00;padding:2rem;">Erreur de connexion à la base de données. Veuillez contacter l\'administrateur.</p>');
        }
    }

    return $pdo;
}
