<?php
/**
 * import_historique.php — Importe les 573 entrées de nuisances depuis l'export Squarespace
 * Exécuter UNE SEULE FOIS après setup_db.php
 * URL : https://cslet.ldsnet.ch/import_historique.php
 * Supprimer après exécution.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=UTF-8');

$jsonFile = __DIR__ . '/export/nuisances_historique.json';

if (!file_exists($jsonFile)) {
    die("ERREUR : fichier nuisances_historique.json introuvable.\nChemin attendu : $jsonFile\n");
}

$data = json_decode(file_get_contents($jsonFile), true);
if (!$data || !is_array($data)) {
    die("ERREUR : impossible de parser le JSON.\n");
}

echo "=== Import historique des nuisances CS-LET ===\n";
echo "Fichier : nuisances_historique.json\n";
echo "Entrées à importer : " . count($data) . "\n\n";

// Mapping des noms de pictos de l'export vers nos fichiers locaux
$pictoMap = [
    'PICTO_Camion Faible.png'      => 'PICTO_Camion_Faible.png',
    'PICTO_Camion Faible pous.png' => 'PICTO_Camion_Faible_pous.png',
    'PICTO_Pelleteuse Faible.png'  => 'PICTO_Pelleteuse_Faible.png',
    'PICTO_Pieux_Moyen.png'        => 'PICTO_Pieux_Moyen.png',
    'PICTO_Pieux_Fort.png'         => 'PICTO_Pieux_Fort.png',
];

$db = getDB();

$stmt = $db->prepare(
    "INSERT INTO nuisances (date, picto, created_at)
     VALUES (:date, :picto, NOW())
     ON DUPLICATE KEY UPDATE picto = VALUES(picto), updated_at = NOW()"
);

$imported = 0;
$updated  = 0;
$errors   = 0;
$skipped  = 0;

foreach ($data as $entry) {
    $date  = $entry['date'] ?? null;
    $picto = $entry['picto'] ?? null;

    if (!$date || !$picto) {
        echo "SKIP : entrée invalide (date=$date, picto=$picto)\n";
        $skipped++;
        continue;
    }

    // Mapper le nom de picto
    $mappedPicto = $pictoMap[$picto] ?? $picto;

    try {
        $stmt->execute([
            ':date'  => $date,
            ':picto' => $mappedPicto,
        ]);

        if ($stmt->rowCount() === 1) {
            $imported++;
        } else {
            $updated++;
        }
    } catch (PDOException $e) {
        echo "ERREUR date=$date : " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=== Résultat ===\n";
echo "Importées  : $imported\n";
echo "Mises à jour : $updated\n";
echo "Ignorées   : $skipped\n";
echo "Erreurs    : $errors\n";
echo "Total traité : " . ($imported + $updated + $skipped + $errors) . " / " . count($data) . "\n";

if ($errors === 0) {
    echo "\nIMPORTANT : Supprimez ce fichier import_historique.php après vérification.\n";
} else {
    echo "\nATTENTION : Des erreurs se sont produites. Vérifiez avant de supprimer.\n";
}
