<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/offreModel.php';
require_once __DIR__ . '/../src/CandidatureModel.php';
require_once __DIR__ . '/../src/helpers.php';

$offreModel       = new OffreModel();
$candidatureModel = new CandidatureModel();

$id    = (int) ($_GET['id'] ?? 0);
$offre = $offreModel->getOffreById($id);

if (!$offre) {
    header('Location: index.php');
    exit;
}

$erreur     = '';
$succes     = '';
$dejaPostule = false;

if (isLoggedIn() && currentRole() === 'candidat') {
    $dejaPostule = $candidatureModel->aDejaPostule(currentUserId(), $id);
}

// Traitement du formulaire de candidature
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn() && currentRole() === 'candidat') {
    $lettre = trim($_POST['lettre'] ?? '');
    $cvPath = null;

    // Gestion de l'upload du CV
    if (!empty($_FILES['cv']['name'])) {
        $file    = $_FILES['cv'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf', 'doc', 'docx'];

        if (!in_array($ext, $allowed)) {
            $erreur = 'Format de CV non accepte. Utilisez PDF, DOC ou DOCX.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $erreur = 'Le fichier ne doit pas depasser 5 Mo.';
        } else {
            $nomFichier  = 'cv_' . currentUserId() . '_' . time() . '.' . $ext;
            $destination = __DIR__ . '/../assets/uploads/' . $nomFichier;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $cvPath = $nomFichier;
            } else {
                $erreur = 'Erreur lors de l upload du CV.';
            }
        }
    }

    if (!$erreur) {
        if (!$cvPath) {
            $erreur = 'Veuillez televerser votre CV pour postuler.';
        } else {
            try {
                $candidatureModel->postuler(currentUserId(), $id, $lettre, $cvPath);
                $succes      = 'Votre candidature a bien ete envoyee !';
                $dejaPostule = true;
            } catch (RuntimeException $e) {
                $erreur = $e->getMessage();
            }
        }
    }
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($offre['titre']) ?> — SearchForAJob</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/Offre.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg sticky-top px-3">
  <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
    <i class="bi bi-briefcase-fill" style="color:var(--blue-main);font-size:1.2rem;" aria-hidden="true"></i>
    Search<span>ForAJob</span>
  </a>
  <div class="ms-auto d-flex gap-2">
    <?php if (isLoggedIn()): ?>
      <?php if (currentRole() === 'recruteur'): ?>
        <a href="dashboard-recruteur.php" class="btn btn-connexion">Mon espace</a>
      <?php else: ?>
        <a href="dashboard-candidat.php" class="btn btn-connexion">Mes candidatures</a>
      <?php endif; ?>
      <a href="logout.php" class="btn btn-connexion">Deconnexion</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-connexion">Connexion</a>
      <a href="register.php" class="btn btn-publier">S'inscrire</a>
    <?php endif; ?>
  </div>
</nav>

<div style="max-width:1000px; margin:1.25rem auto; padding:0 1rem;">
  <a href="index.php" class="btn-retour">
    <i class="bi bi-arrow-left"></i> Retour aux offres
  </a>
</div>

<main class="offre-layout">

  <!-- CONTENU PRINCIPAL -->
  <article class="offre-main">

    <!-- EN-TETE OFFRE -->
    <header class="offre-header">
      <figure class="offre-logo" aria-hidden="true">
        <?= strtoupper(substr($offre['entreprise_nom'], 0, 3)) ?>
      </figure>
      <hgroup>
        <h1 class="offre-titre"><?= htmlspecialchars($offre['titre']) ?></h1>
        <address class="offre-entreprise">
          <?= htmlspecialchars($offre['entreprise_nom']) ?> &middot;
          <?= htmlspecialchars($offre['localisation']) ?>
        </address>
      </hgroup>
    </header>

    <!-- BADGES -->
    <div>
      <span class="badge badge-blue"><?= htmlspecialchars($offre['type_contrat']) ?></span>
      <span class="badge badge-green"><?= htmlspecialchars($offre['teletravail']) ?></span>
      <span class="badge badge-gray"><?= htmlspecialchars($offre['experience']) ?></span>
      <span class="badge badge-amber"><?= htmlspecialchars($offre['entreprise_secteur'] ?? '') ?></span>
    </div>

    <!-- DESCRIPTION -->
    <p class="section-label">Description du poste</p>
    <p class="offre-description"><?= htmlspecialchars($offre['description']) ?></p>

    <!-- COMPETENCES -->
    <?php if (!empty($offre['competences'])): ?>
    <p class="section-label">Competences requises</p>
    <ul class="competences-list">
      <?php foreach ($offre['competences'] as $comp): ?>
        <li><?= htmlspecialchars($comp['libelle']) ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <!-- DESCRIPTION ENTREPRISE -->
    <?php if (!empty($offre['entreprise_description'])): ?>
    <p class="section-label">A propos de <?= htmlspecialchars($offre['entreprise_nom']) ?></p>
    <p class="offre-description"><?= htmlspecialchars($offre['entreprise_description']) ?></p>
    <?php endif; ?>

  </article>

  <!-- SIDEBAR -->
  <aside class="offre-sidebar">

    <!-- INFOS RAPIDES -->
    <div class="sidebar-card">
      <h2>Informations</h2>

      <div class="info-row">
        <i class="bi bi-geo-alt info-icon"></i>
        <div>
          <div class="info-label">Localisation</div>
          <div class="info-value"><?= htmlspecialchars($offre['localisation']) ?></div>
        </div>
      </div>

      <div class="info-row">
        <i class="bi bi-file-text info-icon"></i>
        <div>
          <div class="info-label">Contrat</div>
          <div class="info-value"><?= htmlspecialchars($offre['type_contrat']) ?></div>
        </div>
      </div>

      <div class="info-row">
        <i class="bi bi-laptop info-icon"></i>
        <div>
          <div class="info-label">Teletravail</div>
          <div class="info-value"><?= htmlspecialchars($offre['teletravail']) ?></div>
        </div>
      </div>

      <div class="info-row">
        <i class="bi bi-bar-chart info-icon"></i>
        <div>
          <div class="info-label">Experience</div>
          <div class="info-value"><?= htmlspecialchars($offre['experience']) ?></div>
        </div>
      </div>

      <div class="info-row">
        <i class="bi bi-cash info-icon"></i>
        <div>
          <div class="info-label">Salaire</div>
          <div class="info-value"><?= formatSalaire((float)$offre['salaire_min'], (float)$offre['salaire_max']) ?></div>
        </div>
      </div>

      <div class="info-row">
        <i class="bi bi-calendar info-icon"></i>
        <div>
          <div class="info-label">Publiee le</div>
          <div class="info-value"><?= date('d/m/Y', strtotime($offre['date_publication'])) ?></div>
        </div>
      </div>
    </div>

    <!-- FORMULAIRE CANDIDATURE -->
    <div class="sidebar-card">
      <h2>Postuler</h2>

      <?php if ($succes): ?>
        <div class="alert alert-success py-2 px-3" style="font-size:0.85rem;">
          <i class="bi bi-check-circle"></i> <?= htmlspecialchars($succes) ?>
        </div>

      <?php elseif (!isLoggedIn()): ?>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">
          Connectez-vous pour postuler a cette offre.
        </p>
        <a href="login.php" class="btn-postuler" style="text-decoration:none;">
          <i class="bi bi-box-arrow-in-right"></i> Se connecter
        </a>

      <?php elseif (currentRole() === 'recruteur'): ?>
        <p style="font-size:0.85rem; color:var(--text-muted);">
          Les recruteurs ne peuvent pas postuler a une offre.
        </p>

      <?php elseif ($dejaPostule): ?>
        <div class="alert alert-info py-2 px-3" style="font-size:0.85rem;">
          <i class="bi bi-info-circle"></i> Vous avez deja postule a cette offre.
        </div>

      <?php else: ?>
        <?php if ($erreur): ?>
          <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.85rem;">
            <?= htmlspecialchars($erreur) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="offre.php?id=<?= $id ?>" class="form-candidature" enctype="multipart/form-data">

          <!-- CV UPLOAD obligatoire -->
          <label style="font-size:0.85rem; font-weight:500; margin-bottom:6px; display:block;">
            CV <span style="color:#B91C1C;">*</span>
          </label>
          <label class="cv-upload-zone" for="cv">
            <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" style="display:none;" required />
            <i class="bi bi-cloud-upload" style="font-size:1.5rem; color:var(--text-muted); display:block; margin-bottom:6px;"></i>
            <span style="font-size:0.85rem; color:var(--text-muted);">Cliquez pour uploader votre CV</span>
            <span style="font-size:0.75rem; color:var(--text-muted); display:block; margin-top:3px;">PDF, DOC, DOCX — max 5 Mo</span>
          </label>
          <p id="cv-filename" style="font-size:0.8rem; color:var(--blue-main); margin-top:5px;"></p>

          <!-- LETTRE optionnelle -->
          <label for="lettre" style="font-size:0.85rem; font-weight:500; margin:10px 0 6px; display:block;">
            Lettre de motivation <span style="color:var(--text-muted); font-weight:400;">(optionnel)</span>
          </label>
          <textarea id="lettre" name="lettre"
                    placeholder="Presentez-vous brievement et expliquez pourquoi ce poste vous interesse..."></textarea>

          <button type="submit" class="btn-postuler mt-3">
            <i class="bi bi-send"></i> Envoyer ma candidature
          </button>
        </form>

      <?php endif; ?>
    </div>

  </aside>
</main>

<?php require_once __DIR__ . '/../src/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('cv')?.addEventListener('change', function () {
    const label = document.getElementById('cv-filename');
    if (this.files[0]) {
      label.textContent = 'Fichier selectionne : ' + this.files[0].name;
    }
  });
</script>
</body>
</html>
