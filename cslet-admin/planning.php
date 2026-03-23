<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

check_auth();
$user    = get_user();
$isAdmin = is_admin();

$error   = '';
$success = '';

// ── Suppression (admin seulement) ─────────────────────────────────────────
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verify_csrf();
    $fileId = (int) ($_POST['file_id'] ?? 0);
    if ($fileId > 0) {
        try {
            $db   = getDB();
            $stmt = $db->prepare('SELECT filename FROM planning_files WHERE id = ?');
            $stmt->execute([$fileId]);
            $row  = $stmt->fetch();
            if ($row) {
                $filepath = UPLOADS_PATH . '/' . $row['filename'];
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                $db->prepare('DELETE FROM planning_files WHERE id = ?')->execute([$fileId]);
                $success = 'Fichier supprime avec succes.';
            }
        } catch (PDOException $e) {
            error_log('[CS-LET admin] Delete planning error: ' . $e->getMessage());
            $error = 'Erreur lors de la suppression.';
        }
    }
}

// ── Upload ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['planning_file'])) {
    verify_csrf();

    $file         = $_FILES['planning_file'];
    $maxSize      = 10 * 1024 * 1024; // 10 MB
    $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png'];
    $allowedExts  = ['pdf', 'jpg', 'jpeg', 'png'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'Le fichier depasse la taille maximale autorisee par le serveur.',
            UPLOAD_ERR_FORM_SIZE  => 'Le fichier depasse la taille maximale du formulaire.',
            UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a ete que partiellement uploade.',
            UPLOAD_ERR_NO_FILE    => 'Aucun fichier selectionne.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'ecrire sur le disque.',
        ];
        $error = $uploadErrors[$file['error']] ?? 'Erreur d\'upload inconnue.';
    } elseif ($file['size'] > $maxSize) {
        $error = 'Le fichier depasse la taille maximale autorisee (10 Mo).';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            $error = 'Format non autorise. Seuls PDF, JPG et PNG sont acceptes.';
        } else {
            // Vérification du type MIME réel
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, $allowedMimes, true)) {
                $error = 'Type de fichier invalide.';
            } else {
                // Créer le dossier uploads si nécessaire
                if (!is_dir(UPLOADS_PATH)) {
                    mkdir(UPLOADS_PATH, 0755, true);
                }

                // Nom de fichier sécurisé et unique
                $originalName = basename($file['name']);
                $safeName     = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                $uniqueName   = date('Ymd_His') . '_' . $safeName;
                $destination  = UPLOADS_PATH . '/' . $uniqueName;

                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    $error = 'Erreur lors de l\'enregistrement du fichier.';
                } else {
                    try {
                        $db   = getDB();
                        $stmt = $db->prepare(
                            'INSERT INTO planning_files (filename, original_name, uploaded_at, uploaded_by)
                             VALUES (?, ?, NOW(), ?)'
                        );
                        $stmt->execute([$uniqueName, $originalName, $user['id']]);
                        $success = 'Fichier &laquo;&nbsp;' . htmlspecialchars($originalName, ENT_QUOTES) . '&nbsp;&raquo; uploade avec succes.';
                    } catch (PDOException $e) {
                        error_log('[CS-LET admin] Insert planning_file error: ' . $e->getMessage());
                        $error = 'Fichier sauvegarde mais erreur d\'enregistrement en base de donnees.';
                    }
                }
            }
        }
    }
}

// ── Liste des fichiers ────────────────────────────────────────────────────
$planningFiles = [];
try {
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT pf.id, pf.filename, pf.original_name, pf.uploaded_at, u.username
         FROM planning_files pf
         LEFT JOIN users u ON u.id = pf.uploaded_by
         ORDER BY pf.uploaded_at DESC'
    );
    $stmt->execute();
    $planningFiles = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[CS-LET admin] List planning_files error: ' . $e->getMessage());
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plannings — Admin CS-LET</title>
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body class="admin-body">

<!-- ── Navigation ─────────────────────────────────────────────────────────── -->
<nav class="admin-nav">
    <div class="admin-nav-brand">
        <span class="badge-fmel">FMEL</span>
        <span class="nav-title">CS-LET Admin</span>
    </div>
    <ul class="admin-nav-links">
        <li><a href="index.php">Dashboard</a></li>
        <li><a href="calendrier.php">Calendrier</a></li>
        <li><a href="planning.php" class="active">Plannings</a></li>
        <li><a href="rapport.php">Rapport</a></li>
    </ul>
    <div class="admin-nav-user">
        <span class="nav-username"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></span>
        <a href="logout.php" class="btn-logout">Deconnexion</a>
    </div>
</nav>

<!-- ── Contenu ───────────────────────────────────────────────────────────── -->
<main class="admin-main">
    <div class="page-header">
        <h1 class="page-title">Gestion des plannings</h1>
        <p class="page-subtitle">Uploader un PDF ou une image de planning de nuisances</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- Zone d'upload ────────────────────────────────────────────────────── -->
    <section class="upload-section">
        <h2 class="section-title">Uploader un nouveau planning</h2>
        <form
            id="upload-form"
            method="post"
            action="planning.php"
            enctype="multipart/form-data"
            novalidate
        >
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">

            <div
                id="drop-zone"
                class="drop-zone"
                role="button"
                tabindex="0"
                aria-label="Zone de depot de fichier"
            >
                <div class="drop-zone-content">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#718096" stroke-width="1.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <p class="drop-zone-text">Glissez-deposez votre fichier ici</p>
                    <p class="drop-zone-sub">ou</p>
                    <label for="planning_file" class="btn-choose-file">Parcourir…</label>
                    <input
                        type="file"
                        id="planning_file"
                        name="planning_file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="file-input-hidden"
                    >
                </div>
                <div id="file-preview" class="file-preview" style="display:none;">
                    <p id="file-preview-name" class="file-preview-name"></p>
                    <p id="file-preview-size" class="file-preview-size"></p>
                    <img id="file-preview-img" class="file-preview-img" style="display:none;" alt="Apercu">
                </div>
            </div>

            <p class="upload-hint">Formats acceptes : PDF, JPG, PNG &mdash; Taille max : 10 Mo</p>

            <button type="submit" class="btn-primary btn-upload" id="btn-upload" disabled>
                Uploader le planning
            </button>
        </form>
    </section>

    <!-- Liste des plannings ───────────────────────────────────────────────── -->
    <section class="planning-list-section">
        <h2 class="section-title">Plannings precedents</h2>

        <?php if (empty($planningFiles)): ?>
            <p class="empty-state">Aucun planning uploade pour l'instant.</p>
        <?php else: ?>
            <div class="planning-list">
                <?php foreach ($planningFiles as $pf):
                    $ext        = strtolower(pathinfo($pf['filename'], PATHINFO_EXTENSION));
                    $isImage    = in_array($ext, ['jpg', 'jpeg', 'png'], true);
                    $fileUrl    = '../uploads/planning/' . rawurlencode($pf['filename']);
                    $uploadDate = date('d.m.Y a H:i', strtotime($pf['uploaded_at']));
                ?>
                <div class="planning-item">
                    <div class="planning-item-icon">
                        <?php if ($ext === 'pdf'): ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="1.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <text x="6" y="18" font-size="5" fill="#e53e3e" stroke="none">PDF</text>
                            </svg>
                        <?php else: ?>
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#3182ce" stroke-width="1.5">
                                <rect x="3" y="3" width="18" height="18" rx="2"/>
                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                <polyline points="21 15 16 10 5 21"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="planning-item-info">
                        <span class="planning-item-name"><?= htmlspecialchars($pf['original_name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="planning-item-meta">
                            Uploade le <?= $uploadDate ?>
                            <?php if ($pf['username']): ?>
                                par <?= htmlspecialchars($pf['username'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="planning-item-actions">
                        <a href="<?= htmlspecialchars($fileUrl, ENT_QUOTES) ?>" target="_blank" class="btn-sm btn-outline">
                            Voir
                        </a>
                        <?php if ($isAdmin): ?>
                        <form method="post" action="planning.php" class="inline-form"
                              onsubmit="return confirm('Supprimer ce fichier ?');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="file_id" value="<?= $pf['id'] ?>">
                            <button type="submit" class="btn-sm btn-danger-outline">Supprimer</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

<footer class="admin-footer">
    &copy; <?= date('Y') ?> FMEL &mdash; Interface d'administration CS-LET
</footer>

<script>
(function () {
    'use strict';

    const dropZone   = document.getElementById('drop-zone');
    const fileInput  = document.getElementById('planning_file');
    const btnUpload  = document.getElementById('btn-upload');
    const preview    = document.getElementById('file-preview');
    const previewName = document.getElementById('file-preview-name');
    const previewSize = document.getElementById('file-preview-size');
    const previewImg  = document.getElementById('file-preview-img');
    const MAX_SIZE    = 10 * 1024 * 1024;

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' o';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
        return (bytes / (1024 * 1024)).toFixed(2) + ' Mo';
    }

    function handleFile(file) {
        if (!file) return;

        previewName.textContent = file.name;
        previewSize.textContent = formatSize(file.size);

        const ext = file.name.split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png'].includes(ext)) {
            const reader = new FileReader();
            reader.onload = e => {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            previewImg.style.display = 'none';
        }

        preview.style.display = 'block';
        dropZone.querySelector('.drop-zone-content').style.display = 'none';

        if (file.size > MAX_SIZE) {
            btnUpload.disabled = true;
            previewSize.textContent += ' — TROP GRAND (max 10 Mo)';
            previewSize.style.color = '#e53e3e';
        } else {
            btnUpload.disabled = false;
            previewSize.style.color = '';
        }
    }

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) handleFile(fileInput.files[0]);
    });

    // Drag & drop
    ['dragenter', 'dragover'].forEach(evt => {
        dropZone.addEventListener(evt, e => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
    });
    ['dragleave', 'drop'].forEach(evt => {
        dropZone.addEventListener(evt, e => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
        });
    });
    dropZone.addEventListener('drop', e => {
        const file = e.dataTransfer.files[0];
        if (file) {
            // Injecter le fichier dans l'input
            const dt = new DataTransfer();
            dt.items.add(file);
            fileInput.files = dt.files;
            handleFile(file);
        }
    });

    // Accessibilité clavier sur la drop zone
    dropZone.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            fileInput.click();
        }
    });
})();
</script>
</body>
</html>
