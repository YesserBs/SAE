<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/UserModel.php';

requireAuth();

$userModel = new UserModel();
$profil    = $userModel->getProfil((int) currentUserId());

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom']       ?? '');
    $prenom    = trim($_POST['prenom']    ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $bio       = trim($_POST['bio']       ?? '');

    if (!$nom || !$prenom) {
        $erreur = 'Le nom et le prénom sont obligatoires.';
    } else {
        $userModel->mettreAJourProfil((int) currentUserId(), [
            'nom'       => $nom,
            'prenom'    => $prenom,
            'telephone' => $telephone ?: null,
            'bio'       => $bio ?: null,
        ]);

        // Upload du CV (uniquement pour les candidats), même logique de validation que sur offre.php
        if (currentRole() === 'candidat' && !empty($_FILES['cv']['name'])) {
            $file    = $_FILES['cv'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx'];

            if (!in_array($ext, $allowed)) {
                $erreur = 'Format de CV non accepté. Utilisez PDF, DOC ou DOCX.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $erreur = 'Le fichier ne doit pas dépasser 5 Mo.';
            } else {
                $nomFichier  = 'cv_' . currentUserId() . '_' . time() . '.' . $ext;
                $destination = __DIR__ . '/../assets/uploads/' . $nomFichier;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $userModel->mettreAJourCV((int) currentUserId(), $nomFichier);
                } else {
                    $erreur = 'Erreur lors de l\'upload du CV.';
                }
            }
        }

        if (!$erreur) {
            $succes = 'Profil mis à jour avec succès !';
            $profil = $userModel->getProfil((int) currentUserId());
        }
    }
}

$retourDashboard = currentRole() === 'recruteur' ? 'dashboard-recruteur.php' : 'dashboard-candidat.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mon profil — SearchForAJob</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    :root { --blue-dark:#0C447C; --blue-main:#185FA5; --blue-light:#E6F1FB; --text-muted:#6B7280; --border:rgba(0,0,0,0.10); --bg-page:#F4F6F9; }
    body  { font-family:'Segoe UI',system-ui,sans-serif; background:var(--bg-page); color:#1a1a2e; }
    nav.navbar { background:#fff; border-bottom:1px solid var(--border); height:62px; }
    .navbar-brand { font-size:1.1rem; font-weight:600; color:#1a1a2e !important; }
    .navbar-brand span { color:var(--blue-main); }
    .btn-retour { font-size:0.85rem; color:var(--text-muted); text-decoration:none; display:inline-flex; align-items:center; gap:5px; }
    .btn-retour:hover { color:var(--blue-main); }

    .form-card { max-width:640px; margin:2rem auto; background:#fff; border:1px solid var(--border); border-radius:12px; padding:2rem; }
    .form-card h1 { font-size:1.3rem; font-weight:700; margin-bottom:0.25rem; }
    .form-card .subtitle { font-size:0.875rem; color:var(--text-muted); margin-bottom:1.75rem; }

    .form-group { margin-bottom:1.25rem; }
    label.form-label { font-size:0.875rem; font-weight:500; margin-bottom:5px; display:block; }
    .form-control { font-size:0.875rem; border:1px solid rgba(0,0,0,0.15); border-radius:8px; padding:10px 12px; width:100%; outline:none; font-family:inherit; background:#fff; color:#1a1a2e; }
    .form-control:focus { border-color:var(--blue-main); box-shadow:0 0 0 3px rgba(24,95,165,.10); }
    textarea.form-control { resize:vertical; min-height:100px; }
    .form-control[disabled] { background:#F4F6F9; color:var(--text-muted); }

    .section-sep { font-size:0.75rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em; margin:1.75rem 0 1rem; padding-bottom:0.5rem; border-bottom:1px solid var(--border); }

    .cv-upload-zone { display:block; border:1.5px dashed rgba(0,0,0,0.18); border-radius:10px; padding:18px; text-align:center; cursor:pointer; transition:border-color .2s ease, background .2s ease; }
    .cv-upload-zone:hover { border-color:var(--blue-main); background:var(--blue-light); }
    .cv-current { font-size:0.8rem; color:var(--blue-main); display:flex; align-items:center; gap:6px; margin-bottom:10px; }

    .btn-submit { background:var(--blue-main); color:#fff; border:none; border-radius:8px; padding:12px 28px; font-size:0.95rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:8px; }
    .btn-submit:hover { background:var(--blue-dark); }
  </style>
</head>
<body>

<nav class="navbar sticky-top px-3">
  <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
    <i class="bi bi-briefcase-fill" style="color:var(--blue-main);font-size:1.2rem;" aria-hidden="true"></i>
    Search<span>ForAJob</span>
  </a>
  <a href="<?= $retourDashboard ?>" class="btn-retour ms-auto">
    <i class="bi bi-arrow-left"></i> Tableau de bord
  </a>
</nav>

<main style="padding:0 1rem;">
  <div class="form-card">

    <h1>Mon profil</h1>
    <p class="subtitle">Ces informations sont utilisées sur vos candidatures et votre compte.</p>

    <?php if ($erreur): ?>
      <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.85rem;"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>
    <?php if ($succes): ?>
      <div class="alert alert-success py-2 px-3 mb-3" style="font-size:0.85rem;"><?= htmlspecialchars($succes) ?></div>
    <?php endif; ?>

    <form method="POST" action="profil.php" enctype="multipart/form-data">

      <div class="form-group">
        <label class="form-label" for="email">Adresse email</label>
        <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($profil['email'] ?? '') ?>" disabled />
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="form-group">
            <label class="form-label" for="prenom">Prénom *</label>
            <input type="text" id="prenom" name="prenom" class="form-control"
                   value="<?= htmlspecialchars($profil['prenom'] ?? '') ?>" required />
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="form-label" for="nom">Nom *</label>
            <input type="text" id="nom" name="nom" class="form-control"
                   value="<?= htmlspecialchars($profil['nom'] ?? '') ?>" required />
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="telephone">Téléphone</label>
        <input type="tel" id="telephone" name="telephone" class="form-control"
               value="<?= htmlspecialchars($profil['telephone'] ?? '') ?>" />
      </div>

      <div class="form-group">
        <label class="form-label" for="bio">À propos de vous</label>
        <textarea id="bio" name="bio" class="form-control"
                  placeholder="Quelques mots sur votre parcours..."><?= htmlspecialchars($profil['bio'] ?? '') ?></textarea>
      </div>

      <?php if (currentRole() === 'candidat'): ?>
        <div class="section-sep">Mon CV</div>

        <?php if (!empty($profil['cv_path'])): ?>
          <p class="cv-current">
            <i class="bi bi-file-earmark-check"></i>
            CV actuel : <a href="../assets/uploads/<?= htmlspecialchars($profil['cv_path']) ?>" target="_blank" style="color:var(--blue-main);">
              <?= htmlspecialchars($profil['cv_path']) ?>
            </a>
          </p>
        <?php endif; ?>

        <label class="cv-upload-zone" for="cv">
          <i class="bi bi-cloud-upload" style="font-size:1.4rem; color:var(--text-muted); display:block; margin-bottom:6px;"></i>
          <span style="font-size:0.85rem; color:var(--text-muted);">Cliquez pour remplacer votre CV (PDF, DOC, DOCX — 5 Mo max)</span>
          <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" style="display:none;" />
        </label>
        <p id="cv-filename" style="font-size:0.8rem; color:var(--blue-main); margin-top:5px;"></p>
      <?php endif; ?>

      <div class="mt-4 d-flex justify-content-end gap-2">
        <a href="<?= $retourDashboard ?>" style="font-size:0.875rem; color:var(--text-muted); text-decoration:none; padding:12px 16px;">
          Annuler
        </a>
        <button type="submit" class="btn-submit">
          <i class="bi bi-check-lg"></i> Enregistrer
        </button>
      </div>

    </form>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.getElementById('cv')?.addEventListener('change', function () {
    const label = document.getElementById('cv-filename');
    if (this.files.length) label.textContent = 'Fichier sélectionné : ' + this.files[0].name;
  });
</script>
</body>
</html>
