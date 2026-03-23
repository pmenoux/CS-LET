<?php
$pageTitle = 'Accueil';
require_once __DIR__ . '/includes/header.php';
?>

<section class="hero" style="background-image: url('assets/img/hero_chantier.jpg');">
    <div class="hero-overlay">
        <div class="hero-box">
            <p class="hero-text">
                Pour la rentrée 2026, la FMEL construit <strong>776 lits supplémentaires</strong>
                pour les étudiants.
            </p>
        </div>
    </div>
</section>

<section class="section section--info">
    <div class="container">
        <h1 class="section-title">Informations chantier FMEL</h1>
        <p class="section-lead">
            La Fondation Maisons pour Étudiants Lausanne (FMEL) réalise sur le site du Campus Santé
            à Dorigny un nouveau complexe de logements étudiants. Ce site vous informe sur l'avancement
            des travaux et les nuisances prévisibles.
        </p>

        <div class="card-grid">
            <article class="card">
                <h2 class="card-title">Calendrier des nuisances</h2>
                <p class="card-body">
                    Consultez le planning mensuel des nuisances sonores et de circulation liées au chantier,
                    jour par jour.
                </p>
                <a href="calendrier.php" class="btn btn--primary">Voir le calendrier</a>
            </article>

            <article class="card">
                <h2 class="card-title">Planning des travaux</h2>
                <p class="card-body">
                    Téléchargez le planning général des principales étapes des travaux et consultez
                    le schéma des palplanches.
                </p>
                <a href="informations.php" class="btn btn--secondary">Voir le planning</a>
            </article>

            <article class="card">
                <h2 class="card-title">Le projet</h2>
                <p class="card-body">
                    Découvrez le concept architectural du Campus Santé et du bâtiment LET — 332 logements
                    étudiants, label Minergie P Eco.
                </p>
                <a href="a-propos.php" class="btn btn--secondary">En savoir plus</a>
            </article>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
