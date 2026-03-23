<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

check_auth();
$user = get_user();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guide — Admin CS-LET</title>
    <link rel="stylesheet" href="assets/admin.css">
    <style>
        .guide { max-width: 800px; margin: 0 auto; }
        .guide h2 { font-size: 1.3rem; color: #1a2d4a; margin-top: 2.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0; }
        .guide h3 { font-size: 1.05rem; color: #2c5282; margin-top: 1.5rem; }
        .guide p, .guide li { font-size: 0.9rem; line-height: 1.7; color: #4a5568; }
        .guide ol, .guide ul { margin: 0.5rem 0 1rem 1.5rem; }
        .guide li { margin-bottom: 0.4rem; }
        .guide strong { color: #1a2d4a; }
        .guide .step-box {
            background: #f7fafc; border: 1px solid #e2e8f0; border-left: 4px solid #2c5282;
            border-radius: 6px; padding: 1.25rem 1.5rem; margin: 1rem 0;
        }
        .guide .step-box h4 { font-size: 0.85rem; text-transform: uppercase; color: #2c5282; margin-bottom: 0.5rem; letter-spacing: 0.05em; }
        .guide .tip {
            background: #fffff0; border: 1px solid #ecc94b; border-radius: 6px;
            padding: 1rem 1.25rem; margin: 1rem 0; font-size: 0.85rem;
        }
        .guide .tip strong { color: #975a16; }
        .guide .picto-table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.85rem; }
        .guide .picto-table th {
            background: #edf2f7; padding: 0.6rem 0.75rem; text-align: left;
            font-size: 0.7rem; text-transform: uppercase; color: #4a5568;
            border-bottom: 2px solid #cbd5e0;
        }
        .guide .picto-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .guide .picto-table img { height: 32px; vertical-align: middle; }
        .guide .summary-table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        .guide .summary-table td { padding: 0.6rem 1rem; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; }
        .guide .summary-table td:first-child { font-weight: 700; color: #2c5282; width: 60px; text-align: center; font-size: 1.1rem; }
        .guide hr { border: none; border-top: 1px solid #e2e8f0; margin: 2rem 0; }
        .guide .contact-box {
            background: #ebf8ff; border: 1px solid #90cdf4; border-radius: 6px;
            padding: 1rem 1.25rem; margin: 1.5rem 0; text-align: center;
        }
        .guide .contact-box strong { color: #2c5282; }
        @media print {
            .admin-nav, .admin-footer { display: none !important; }
            .guide h2 { break-before: auto; }
        }
    </style>
</head>
<body class="admin-body">

<nav class="admin-nav">
    <div class="admin-nav-brand">
        <span class="badge-fmel">FMEL</span>
        <span class="nav-title">CS-LET Admin</span>
    </div>
    <ul class="admin-nav-links">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="calendrier.php">Calendrier</a></li>
        <li><a href="planning.php">Plannings</a></li>
        <li><a href="rapport.php">Rapport</a></li>
        <li><a href="guide.php" class="active">Guide</a></li>
        <li><a href="stats.php">Stats</a></li>
    </ul>
    <div class="admin-nav-user">
        <span class="nav-username"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></span>
        <a href="logout.php" class="btn-logout">Deconnexion</a>
    </div>
</nav>

<main class="admin-main">
<div class="guide">

    <h1 style="font-size:1.6rem; color:#1a2d4a; margin-bottom:0.25rem;">Guide d'utilisation</h1>
    <p style="color:#718096; font-size:0.9rem; margin-bottom:2rem;">Calendrier des nuisances — Chantier LET Campus Sante Dorigny</p>

    <!-- ================================================================== -->
    <h2>1. Procedure mensuelle en 5 minutes</h2>

    <table class="summary-table">
        <tr><td>1</td><td>Se connecter a <strong>/cslet-admin/</strong></td></tr>
        <tr><td>2</td><td>Aller dans <strong>Calendrier</strong></td></tr>
        <tr><td>3</td><td>Naviguer vers le mois a remplir (fleches ← →)</td></tr>
        <tr><td>4</td><td><strong>Mode selection multiple</strong> → choisir plage de dates + picto → Appliquer</td></tr>
        <tr><td>5</td><td>Ajuster les jours individuels si necessaire</td></tr>
        <tr><td>6</td><td>Uploader le planning PDF si nouveau document (onglet <strong>Plannings</strong>)</td></tr>
        <tr><td>7</td><td>Se deconnecter</td></tr>
    </table>

    <!-- ================================================================== -->
    <h2>2. Remplir plusieurs jours d'un coup</h2>

    <p>C'est la methode recommandee pour le remplissage mensuel :</p>

    <div class="step-box">
        <h4>Etapes</h4>
        <ol>
            <li>Cliquer sur <strong>Calendrier</strong> dans le menu</li>
            <li>Naviguer vers le bon mois</li>
            <li>Cliquer le bouton <strong>Mode selection multiple</strong></li>
            <li>Remplir <strong>Du</strong> (premier jour ouvrable) et <strong>Au</strong> (dernier jour)</li>
            <li>Cliquer sur le <strong>picto</strong> a appliquer</li>
            <li>Cliquer <strong>Appliquer a la plage</strong></li>
            <li>Confirmer</li>
        </ol>
    </div>

    <div class="tip">
        <strong>Astuce :</strong> Si le mois comporte differents types de nuisances
        (ex : pieux la 1ere semaine, camions le reste), repetez l'operation pour chaque plage
        avec le picto correspondant.
    </div>

    <!-- ================================================================== -->
    <h2>3. Modifier un jour individuel</h2>

    <div class="step-box">
        <h4>Etapes</h4>
        <ol>
            <li><strong>Cliquer sur le jour</strong> a modifier dans le calendrier</li>
            <li>Une fenetre s'ouvre avec tous les pictos disponibles</li>
            <li>Cliquer sur le <strong>picto</strong> souhaite</li>
            <li>Cliquer <strong>Enregistrer</strong></li>
        </ol>
        <p style="margin-top:0.5rem; font-size:0.85rem; color:#718096;">
            Le calendrier se met a jour immediatement — pas besoin de recharger la page.
        </p>
    </div>

    <!-- ================================================================== -->
    <h2>4. Supprimer une entree</h2>

    <ol>
        <li>Cliquer sur le jour concerne</li>
        <li>Cliquer le bouton rouge <strong>Supprimer l'entree</strong></li>
        <li>Confirmer la suppression</li>
    </ol>

    <!-- ================================================================== -->
    <h2>5. Les pictos disponibles</h2>

    <p>Regle simple : <span style="color:#38a169; font-weight:700;">vert = faible</span>,
    <span style="color:#d69e2e; font-weight:700;">orange = moyen</span>,
    <span style="color:#e53e3e; font-weight:700;">rouge = fort</span>.</p>

    <table class="picto-table">
        <thead>
            <tr>
                <th>Picto</th>
                <th>Type</th>
                <th>Niveau</th>
                <th>Signification</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $pictos = [
                ['PICTO_Camion_Faible.webp',       'Camion',     'Faible', 'Trafic de camions, nuisance sonore faible'],
                ['PICTO_Camion_Faible_pous.webp',   'Camion',     'Faible + poussiere', 'Idem + emission de poussiere'],
                ['PICTO_Camion_Moyen.webp',         'Camion',     'Moyen',  'Trafic de camions soutenu'],
                ['PICTO_Camion_Moyen_pous.webp',    'Camion',     'Moyen + poussiere',  'Idem + emission de poussiere'],
                ['PICTO_Camion_Fort.webp',          'Camion',     'Fort',   'Trafic intense, nuisance sonore forte'],
                ['PICTO_Camion_Fort_pous.webp',     'Camion',     'Fort + poussiere',   'Idem + emission de poussiere'],
                ['PICTO_Pelleteuse_Faible.webp',    'Pelleteuse', 'Faible', 'Travaux de terrassement legers'],
                ['PICTO_Pelleteuse_Moyen.webp',     'Pelleteuse', 'Moyen',  'Travaux de terrassement soutenus'],
                ['PICTO_Pelleteuse_Fort.webp',      'Pelleteuse', 'Fort',   'Travaux de terrassement intensifs'],
                ['PICTO_Pieux_Moyen.webp',          'Pieux',      'Moyen',  'Battage / forage de pieux modere'],
                ['PICTO_Pieux_Fort.webp',           'Pieux',      'Fort',   'Battage de pieux intensif, vibrations fortes'],
            ];
            foreach ($pictos as $p):
                $niveauClass = '';
                if (str_contains($p[2], 'Fort')) $niveauClass = 'color:#e53e3e; font-weight:600;';
                elseif (str_contains($p[2], 'Moyen')) $niveauClass = 'color:#d69e2e; font-weight:600;';
                else $niveauClass = 'color:#38a169; font-weight:600;';
            ?>
            <tr>
                <td><img src="../assets/img/<?= htmlspecialchars($p[0]) ?>" alt="<?= htmlspecialchars($p[1]) ?>"></td>
                <td><?= htmlspecialchars($p[1]) ?></td>
                <td style="<?= $niveauClass ?>"><?= htmlspecialchars($p[2]) ?></td>
                <td><?= htmlspecialchars($p[3]) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- ================================================================== -->
    <h2>6. Uploader un planning PDF</h2>

    <p>Quand un nouveau planning des travaux est emis :</p>

    <div class="step-box">
        <h4>Etapes</h4>
        <ol>
            <li>Cliquer sur <strong>Plannings</strong> dans le menu</li>
            <li><strong>Glisser-deposer</strong> le fichier dans la zone prevue, ou cliquer <strong>Parcourir</strong></li>
            <li>Cliquer <strong>Uploader le planning</strong></li>
            <li>Le planning apparait automatiquement sur la page publique « Informations »</li>
        </ol>
        <p style="margin-top:0.5rem; font-size:0.85rem; color:#718096;">
            Formats acceptes : <strong>PDF, JPG, PNG</strong> — taille max : <strong>10 Mo</strong>
        </p>
    </div>

    <!-- ================================================================== -->
    <h2>7. Verifier son travail</h2>

    <ul>
        <li><strong>Calendrier</strong> : vue mensuelle avec tous les pictos assignes</li>
        <li><strong>Dashboard</strong> : nombre total d'entrees et derniere mise a jour</li>
        <li><strong>Site public</strong> : <a href="../calendrier.php" target="_blank" style="color:#2c5282;">ouvrir le calendrier public</a> pour voir le resultat tel que les voisins le voient</li>
    </ul>

    <hr>

    <div class="contact-box">
        <p>En cas de probleme ou de question :</p>
        <p><strong>Pierre Menoux</strong> — Responsable projet immobilier FMEL<br>pierre.menoux@fmel.ch</p>
    </div>

</div>
</main>

<footer class="admin-footer">
    &copy; <?= date('Y') ?> FMEL &mdash; Interface d'administration CS-LET
</footer>

</body>
</html>
