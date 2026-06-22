<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/EntrepriseModel.php';

requireRole('recruteur');

$model      = new EntrepriseModel();
$entreprise = $model->getByRecruteur(currentUserId());
$nouvelle   = !$entreprise;

$erreur = '';
$succes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom         = trim($_POST['nom']         ?? '');
    $secteur     = trim($_POST['secteur']     ?? '');
    $ville       = trim($_POST['ville']       ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$nom) {
        $erreur = 'Le nom de l\'entreprise est obligatoire.';
    } else {
        $data = [
            'nom'         => $nom,
            'secteur'     => $secteur ?: null,
            'ville'       => $ville ?: null,
            'description' => $description ?: null,
        ];

        try {
            if ($nouvelle) {
                $model->creer(currentUserId(), $data);
            } else {
                $model->mettreAJour((int) $entreprise['id'], currentUserId(), $data);
            }
            // On recharge l'entreprise à jour pour réafficher le formulaire pré-rempli
            $entreprise = $model->getByRecruteur(currentUserId());
            $nouvelle   = false;
            $succes     = 'Informations enregistrées avec succès !';
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
  <title>Mon entreprise — SearchForAJob</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/entreprise.css">
</head>
<body>

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

    <h1><?= $nouvelle ? 'Créer mon entreprise' : 'Mon entreprise' ?></h1>
    <p class="subtitle">
      <?php if ($nouvelle): ?>
        Avant de publier une offre, vous devez d'abord créer la fiche de votre entreprise.
      <?php else: ?>
        Modifiez les informations affichées aux candidats sur vos offres.
      <?php endif; ?>
    </p>

    <?php if ($erreur): ?>
      <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.85rem;"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>
    <?php if ($succes): ?>
      <div class="alert alert-success py-2 px-3 mb-3" style="font-size:0.85rem;"><?= htmlspecialchars($succes) ?></div>
    <?php endif; ?>

    <form method="POST" action="entreprise.php">

      <div class="form-group">
        <label class="form-label" for="nom">Nom de l'entreprise *</label>
        <input type="text" id="nom" name="nom" class="form-control"
               placeholder="Ex: BNP Paribas"
               value="<?= htmlspecialchars($entreprise['nom'] ?? $_POST['nom'] ?? '') ?>" required />
      </div>

      <div class="form-group">
        <label class="form-label" for="secteur">Secteur d'activité</label>
        <input type="text" id="secteur" name="secteur" class="form-control"
               placeholder="Ex: Informatique, Finance, Santé..."
               value="<?= htmlspecialchars($entreprise['secteur'] ?? $_POST['secteur'] ?? '') ?>" />
      </div>

      <div class="form-group">
        <label class="form-label" for="ville">Ville</label>
        <input type="text" id="ville" name="ville" class="form-control"
               placeholder="Ex: Paris"
               value="<?= htmlspecialchars($entreprise['ville'] ?? $_POST['ville'] ?? '') ?>" />
      </div>

      <div class="form-group">
        <label class="form-label" for="description">Description de l'entreprise</label>
        <textarea id="description" name="description" class="form-control"
                  placeholder="Présentez votre entreprise aux candidats..."><?= htmlspecialchars($entreprise['description'] ?? $_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <?php if (!$nouvelle): ?>
          <a href="publier-offre.php" style="font-size:0.875rem; color:var(--text-muted); text-decoration:none; padding:12px 16px;">
            Annuler
          </a>
        <?php endif; ?>
        <button type="submit" class="btn-submit">
          <i class="bi bi-check-lg"></i> <?= $nouvelle ? 'Créer mon entreprise' : 'Enregistrer' ?>
        </button>
      </div>

    </form>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
