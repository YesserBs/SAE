<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/offreModel.php';

requireRole('recruteur');

$offreModel = new OffreModel();

$id    = (int) ($_GET['id'] ?? 0);
$offre = $offreModel->getOffreForRecruteur($id, currentUserId());

if (!$offre) {
    header('Location: dashboard-recruteur.php');
    exit;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre        = trim($_POST['titre']        ?? '');
    $description  = trim($_POST['description']  ?? '');
    $localisation = trim($_POST['localisation'] ?? '');
    $contrat      = $_POST['type_contrat']      ?? '';
    $teletravail  = $_POST['teletravail']       ?? '';
    $experience   = $_POST['experience']        ?? '';
    $salaire_min  = $_POST['salaire_min']       ?? null;
    $salaire_max  = $_POST['salaire_max']       ?? null;
    // Compétences : tableau de libellés texte libres
    $competLibelles = $_POST['competences_texte'] ?? [];

    if (!$titre || !$description || !$localisation || !$contrat || !$teletravail || !$experience) {
        $erreur = 'Veuillez remplir tous les champs obligatoires.';
    } else {
        try {
            $offreModel->modifierOffre($id, currentUserId(), [
                'titre'        => $titre,
                'description'  => $description,
                'localisation' => $localisation,
                'type_contrat' => $contrat,
                'teletravail'  => $teletravail,
                'experience'   => $experience,
                'salaire_min'  => $salaire_min ? (float) $salaire_min : null,
                'salaire_max'  => $salaire_max ? (float) $salaire_max : null,
            ]);
            // On gère les compétences séparément avec la méthode par libellés
            $offreModel->attacherCompetencesParLibelle($id, $competLibelles);

            header('Location: offre.php?id=' . $id);
            exit;
        } catch (Exception $e) {
            $erreur = 'Une erreur est survenue : ' . $e->getMessage();
            $offre  = $offreModel->getOffreForRecruteur($id, currentUserId());
        }
    }
} else {
    // Pré-remplissage initial depuis la base
    $_POST = [
        'titre'        => $offre['titre'],
        'description'  => $offre['description'],
        'localisation' => $offre['localisation'],
        'type_contrat' => $offre['type_contrat'],
        'teletravail'  => $offre['teletravail'],
        'experience'   => $offre['experience'],
        'salaire_min'  => $offre['salaire_min'],
        'salaire_max'  => $offre['salaire_max'],
    ];
}

// Libellés à pré-remplir dans les inputs JS
// — En GET : depuis la base ; en POST avec erreur : depuis $_POST
$competLibellesInit = ($_SERVER['REQUEST_METHOD'] === 'POST')
    ? array_filter(array_map('trim', $_POST['competences_texte'] ?? []))
    : $offre['competences_libelles'];
$competLibellesJson = json_encode(array_values($competLibellesInit), JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Modifier l'offre — SearchForAJob</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/modifier-offre.css">
</head>
<body>

<nav class="navbar sticky-top px-3">
  <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
    <i class="bi bi-briefcase-fill" style="color:var(--blue-main);font-size:1.2rem;" aria-hidden="true"></i>
    Search<span>ForAJob</span>
  </a>
  <a href="dashboard-recruteur.php?offre=<?= $id ?>" class="btn-retour ms-auto">
    <i class="bi bi-arrow-left"></i> Tableau de bord
  </a>
</nav>

<main style="padding:0 1rem;">
  <div class="form-card">

    <h1>Modifier l'offre</h1>
    <p class="subtitle"><?= htmlspecialchars($offre['titre']) ?></p>

    <?php if ($erreur): ?>
      <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.85rem;"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" action="modifier-offre.php?id=<?= $id ?>">

      <div class="section-sep">Informations principales</div>

      <div class="form-group">
        <label class="form-label" for="titre">Titre du poste *</label>
        <input type="text" id="titre" name="titre" class="form-control"
               value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" required />
      </div>

      <div class="form-group">
        <label class="form-label" for="localisation">Localisation *</label>
        <input type="text" id="localisation" name="localisation" class="form-control"
               value="<?= htmlspecialchars($_POST['localisation'] ?? '') ?>" required />
      </div>

      <div class="form-group">
        <label class="form-label" for="description">Description du poste *</label>
        <textarea id="description" name="description" class="form-control"
                  required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="section-sep">Conditions</div>

      <div class="row g-3">
        <div class="col-md-4">
          <div class="form-group">
            <label class="form-label" for="type_contrat">Type de contrat *</label>
            <select id="type_contrat" name="type_contrat" class="form-select" required>
              <?php foreach (['CDI','CDD','Stage','Alternance','Freelance'] as $c): ?>
                <option value="<?= $c ?>" <?= ($_POST['type_contrat'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label class="form-label" for="teletravail">Télétravail *</label>
            <select id="teletravail" name="teletravail" class="form-select" required>
              <?php foreach (['Presentiel','Hybride','Full remote'] as $t): ?>
                <option value="<?= $t ?>" <?= ($_POST['teletravail'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            <label class="form-label" for="experience">Expérience *</label>
            <select id="experience" name="experience" class="form-select" required>
              <?php foreach (['Junior','Confirme','Senior'] as $x): ?>
                <option value="<?= $x ?>" <?= ($_POST['experience'] ?? '') === $x ? 'selected' : '' ?>><?= $x ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="section-sep">Salaire (optionnel)</div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="form-group">
            <label class="form-label" for="salaire_min">Salaire minimum (€/an)</label>
            <input type="number" id="salaire_min" name="salaire_min" class="form-control" min="0"
                   value="<?= htmlspecialchars((string) ($_POST['salaire_min'] ?? '')) ?>" />
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="form-label" for="salaire_max">Salaire maximum (€/an)</label>
            <input type="number" id="salaire_max" name="salaire_max" class="form-control" min="0"
                   value="<?= htmlspecialchars((string) ($_POST['salaire_max'] ?? '')) ?>" />
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════
           SECTION COMPÉTENCES — inputs texte dynamiques
           ══════════════════════════════════════════════ -->
      <div class="section-sep">Compétences requises (optionnel)</div>

      <div id="competences-list">
        <!-- Les lignes sont injectées par JS au chargement -->
      </div>

      <button type="button" class="btn-add-comp" id="btn-add-comp">
        <i class="bi bi-plus-circle"></i> Ajouter une compétence
      </button>

      <p class="comp-hint">
        <i class="bi bi-info-circle"></i>
        Saisissez une compétence par champ (ex : PHP 8, React, Docker…). Laissez vide pour ignorer.
      </p>

      <div class="mt-4 d-flex justify-content-end gap-2">
        <a href="dashboard-recruteur.php?offre=<?= $id ?>"
           style="font-size:0.875rem; color:var(--text-muted); text-decoration:none; padding:12px 16px;">
          Annuler
        </a>
        <button type="submit" class="btn-submit">
          <i class="bi bi-check-lg"></i> Enregistrer les modifications
        </button>
      </div>

    </form>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Libellés initiaux injectés par PHP (tableau JSON des compétences existantes)
const INIT_COMPETENCES = <?= $competLibellesJson ?>;

const list   = document.getElementById('competences-list');
const btnAdd = document.getElementById('btn-add-comp');

/* ── Crée une ligne compétence ── */
function creerLigne(valeur = '') {
  const row = document.createElement('div');
  row.className = 'competence-row';

  const input = document.createElement('input');
  input.type        = 'text';
  input.name        = 'competences_texte[]';
  input.placeholder = 'Ex : PHP 8, React, Docker…';
  input.value       = valeur;
  input.maxLength   = 100;
  input.autocomplete = 'off';

  const btnDel = document.createElement('button');
  btnDel.type      = 'button';
  btnDel.className = 'btn-remove-comp';
  btnDel.title     = 'Supprimer cette compétence';
  btnDel.innerHTML = '<i class="bi bi-trash3"></i>';
  btnDel.addEventListener('click', () => {
    // On ne supprime pas si c'est la dernière ligne
    if (list.children.length > 1) {
      row.remove();
      mettreAJourBoutonsSuppression();
    } else {
      // On vide juste le champ
      input.value = '';
      input.focus();
    }
  });

  row.appendChild(input);
  row.appendChild(btnDel);
  list.appendChild(row);

  mettreAJourBoutonsSuppression();
  return input;
}

/* ── Désactive le bouton supprimer s'il n'y a qu'une seule ligne ── */
function mettreAJourBoutonsSuppression() {
  const boutons = list.querySelectorAll('.btn-remove-comp');
  boutons.forEach(b => {
    b.disabled = boutons.length === 1;
  });
}

/* ── Bouton "Ajouter une compétence" ── */
btnAdd.addEventListener('click', () => {
  const input = creerLigne('');
  input.focus();
});

/* ── Initialisation au chargement ── */
if (INIT_COMPETENCES.length > 0) {
  INIT_COMPETENCES.forEach(lib => creerLigne(lib));
} else {
  // Toujours au moins une ligne vide
  creerLigne('');
}

/* ── Appuyer sur Entrée dans un input = ajouter une nouvelle ligne ── */
list.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
    e.preventDefault();
    const input = creerLigne('');
    input.focus();
  }
});
</script>
</body>
</html>
