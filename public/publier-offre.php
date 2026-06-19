<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/offreModel.php';
require_once __DIR__ . '/../config/database.php';

requireRole('recruteur');

$offreModel  = new OffreModel();
$competences = $offreModel->getCompetences();

// Récupérer l'entreprise du recruteur
$pdo  = getPDO();
$stmt = $pdo->prepare("SELECT id, nom FROM entreprise WHERE recruteur_id = :rid LIMIT 1");
$stmt->execute([':rid' => currentUserId()]);
$entreprise = $stmt->fetch();

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre       = trim($_POST['titre']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $localisation= trim($_POST['localisation']?? '');
    $contrat     = $_POST['type_contrat']     ?? '';
    $teletravail = $_POST['teletravail']      ?? '';
    $experience  = $_POST['experience']       ?? '';
    $salaire_min = $_POST['salaire_min']      ?? null;
    $salaire_max = $_POST['salaire_max']      ?? null;
    $competIds   = $_POST['competences']      ?? [];

    // Validation
    if (!$titre || !$description || !$localisation || !$contrat || !$teletravail || !$experience) {
        $erreur = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!$entreprise) {
        $erreur = 'Vous devez d abord creer une entreprise.';
    } else {
        try {
            $offreId = $offreModel->creerOffre([
                'entreprise_id' => $entreprise['id'],
                'titre'         => $titre,
                'description'   => $description,
                'localisation'  => $localisation,
                'type_contrat'  => $contrat,
                'teletravail'   => $teletravail,
                'experience'    => $experience,
                'salaire_min'   => $salaire_min ? (float) $salaire_min : null,
                'salaire_max'   => $salaire_max ? (float) $salaire_max : null,
                'competences'   => array_map('intval', $competIds),
            ]);
            header('Location: offre.php?id=' . $offreId);
            exit;
        } catch (Exception $e) {
            $erreur = 'Une erreur est survenue : ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Publier une offre — SearchForAJob</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="/../assets/css/publier-offre.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar sticky-top px-3">
  <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
    <i class="bi bi-briefcase-fill" style="color:var(--blue-main);font-size:1.2rem;" aria-hidden="true"></i>
    Search<span>ForAJob</span>
  </a>
  <a href="dashboard-recruteur.php" class="btn-retour ms-auto">
    <i class="bi bi-arrow-left"></i> Tableau de bord
  </a>
</nav>

<main style="padding:0 1rem;">
  <div class="form-card">

    <h1>Publier une offre</h1>
    <p class="subtitle">
      <?php if ($entreprise): ?>
        Pour : <strong><?= htmlspecialchars($entreprise['nom']) ?></strong>
      <?php else: ?>
        Vous n avez pas encore d entreprise associee a votre compte.
      <?php endif; ?>
    </p>

    <?php if ($erreur): ?>
      <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.85rem;"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if (!$entreprise): ?>
      <p style="text-align:center; padding:2rem 0;">
        <a href="dashboard-recruteur.php" class="btn-submit" style="text-decoration:none;">
          Retour au tableau de bord
        </a>
      </p>
    <?php else: ?>

    <form method="POST" action="publier-offre.php">

      <!-- INFORMATIONS PRINCIPALES -->
      <div class="section-sep">Informations principales</div>

      <div class="form-group">
        <label class="form-label" for="titre">Titre du poste *</label>
        <input type="text" id="titre" name="titre" class="form-control"
               placeholder="Ex: Developpeur Full Stack PHP / React"
               value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" required />
      </div>

      <div class="form-group">
        <label class="form-label" for="localisation">Localisation *</label>
        <input type="text" id="localisation" name="localisation" class="form-control"
               placeholder="Ex: Paris 9e, Lyon, Remote"
               value="<?= htmlspecialchars($_POST['localisation'] ?? '') ?>" required />
      </div>

      <div class="form-group">
        <label class="form-label" for="description">Description du poste *</label>
        <textarea id="description" name="description" class="form-control"
                  placeholder="Decrivez les missions, le contexte, l equipe..."
                  required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <!-- CONDITIONS -->
      <div class="section-sep">Conditions</div>

      <div class="row g-3">
        <div class="col-md-4">
          <div class="form-group">
            <label class="form-label" for="type_contrat">Type de contrat *</label>
            <select id="type_contrat" name="type_contrat" class="form-select" required>
              <option value="">Choisir...</option>
              <?php foreach (['CDI','CDD','Stage','Alternance','Freelance'] as $c): ?>
                <option value="<?= $c ?>" <?= ($_POST['type_contrat'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label class="form-label" for="teletravail">Teletravail *</label>
            <select id="teletravail" name="teletravail" class="form-select" required>
              <option value="">Choisir...</option>
              <?php foreach (['Presentiel','Hybride','Full remote'] as $t): ?>
                <option value="<?= $t ?>" <?= ($_POST['teletravail'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label class="form-label" for="experience">Experience *</label>
            <select id="experience" name="experience" class="form-select" required>
              <option value="">Choisir...</option>
              <?php foreach (['Junior','Confirme','Senior'] as $x): ?>
                <option value="<?= $x ?>" <?= ($_POST['experience'] ?? '') === $x ? 'selected' : '' ?>><?= $x ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- SALAIRE -->
      <div class="section-sep">Salaire (optionnel)</div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="form-group">
            <label class="form-label" for="salaire_min">Salaire minimum (EUR/an)</label>
            <input type="number" id="salaire_min" name="salaire_min" class="form-control"
                   placeholder="Ex: 40000" min="0"
                   value="<?= htmlspecialchars($_POST['salaire_min'] ?? '') ?>" />
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="form-label" for="salaire_max">Salaire maximum (EUR/an)</label>
            <input type="number" id="salaire_max" name="salaire_max" class="form-control"
                   placeholder="Ex: 55000" min="0"
                   value="<?= htmlspecialchars($_POST['salaire_max'] ?? '') ?>" />
          </div>
        </div>
      </div>

      <!-- COMPETENCES -->
      <div class="section-sep">Competences requises (optionnel)</div>

      <div class="competences-grid">
        <?php foreach ($competences as $comp):
          $checked = in_array($comp['id'], $_POST['competences'] ?? []) ? 'checked' : '';
          echo "
          <label class='comp-check'>
            <input type='checkbox' name='competences[]' value='{$comp['id']}' $checked />
            {$comp['libelle']}
          </label>";
        endforeach; ?>
      </div>

      <!-- SOUMISSION -->
      <div class="mt-4 d-flex justify-content-end gap-2">
        <a href="dashboard-recruteur.php" style="font-size:0.875rem; color:var(--text-muted); text-decoration:none; padding:12px 16px;">
          Annuler
        </a>
        <button type="submit" class="btn-submit">
          <i class="bi bi-send"></i> Publier l offre
        </button>
      </div>

    </form>

    <?php endif; ?>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
