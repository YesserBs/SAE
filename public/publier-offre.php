<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/offreModel.php';
require_once __DIR__ . '/../config/database.php';

requireRole('recruteur');

$offreModel = new OffreModel();

// Récupérer l'entreprise du recruteur
$pdo  = getPDO();
$stmt = $pdo->prepare("SELECT id, nom FROM entreprise WHERE recruteur_id = :rid LIMIT 1");
$stmt->execute([':rid' => currentUserId()]);
$entreprise = $stmt->fetch();

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
    $competLibelles = $_POST['competences_texte'] ?? [];

    if (!$titre || !$description || !$localisation || !$contrat || !$teletravail || !$experience) {
        $erreur = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!$entreprise) {
        $erreur = 'Vous devez d\'abord créer une entreprise.';
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
            ]);
            // Attacher les compétences par libellés texte libres
            $offreModel->attacherCompetencesParLibelle($offreId, $competLibelles);

            header('Location: offre.php?id=' . $offreId);
            exit;
        } catch (Exception $e) {
            $erreur = 'Une erreur est survenue : ' . $e->getMessage();
        }
    }
}

// En cas d'erreur POST, on restaure les compétences saisies (texte)
$competLibellesInit = array_filter(array_map('trim', $_POST['competences_texte'] ?? []));
$competLibellesJson = json_encode(array_values($competLibellesInit), JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Publier une offre — SearchForAJob</title>
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

    .form-card { max-width:720px; margin:2rem auto; background:#fff; border:1px solid var(--border); border-radius:12px; padding:2rem; }
    .form-card h1 { font-size:1.3rem; font-weight:700; margin-bottom:0.25rem; }
    .form-card .subtitle { font-size:0.875rem; color:var(--text-muted); margin-bottom:1.75rem; }

    .form-group { margin-bottom:1.25rem; }
    label.form-label { font-size:0.875rem; font-weight:500; margin-bottom:5px; display:block; }
    .form-control, .form-select { font-size:0.875rem; border:1px solid rgba(0,0,0,0.15); border-radius:8px; padding:10px 12px; width:100%; outline:none; font-family:inherit; background:#fff; color:#1a1a2e; }
    .form-control:focus, .form-select:focus { border-color:var(--blue-main); box-shadow:0 0 0 3px rgba(24,95,165,.10); }
    textarea.form-control { resize:vertical; min-height:140px; }

    .section-sep { font-size:0.75rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em; margin:1.75rem 0 1rem; padding-bottom:0.5rem; border-bottom:1px solid var(--border); }

    /* ── Compétences dynamiques ── */
    #competences-list { display:flex; flex-direction:column; gap:8px; }

    .competence-row {
      display:flex; align-items:center; gap:8px;
      animation: slideIn 0.2s ease;
    }
    @keyframes slideIn {
      from { opacity:0; transform:translateY(-6px); }
      to   { opacity:1; transform:translateY(0); }
    }

    .competence-row input {
      flex:1;
      font-size:0.875rem;
      border:1px solid rgba(0,0,0,0.15);
      border-radius:8px;
      padding:9px 12px;
      outline:none;
      font-family:inherit;
      background:#fff;
      color:#1a1a2e;
      transition: border-color .15s, box-shadow .15s;
    }
    .competence-row input:focus {
      border-color:var(--blue-main);
      box-shadow:0 0 0 3px rgba(24,95,165,.10);
    }
    .competence-row input::placeholder { color:#9CA3AF; }

    .btn-remove-comp {
      flex-shrink:0;
      width:34px; height:34px;
      border:1px solid #FCA5A5;
      border-radius:8px;
      background:#FEF2F2;
      color:#B91C1C;
      cursor:pointer;
      display:flex; align-items:center; justify-content:center;
      transition: background .15s;
      font-size:0.9rem;
    }
    .btn-remove-comp:hover { background:#FEE2E2; }
    .btn-remove-comp:disabled { opacity:.3; cursor:default; border-color:var(--border); background:#fff; color:var(--text-muted); }

    .btn-add-comp {
      margin-top:10px;
      display:inline-flex; align-items:center; gap:7px;
      font-size:0.85rem; font-weight:500;
      color:var(--blue-main);
      background:var(--blue-light);
      border:1px dashed var(--blue-main);
      border-radius:8px;
      padding:8px 14px;
      cursor:pointer;
      transition: background .15s;
      font-family:inherit;
    }
    .btn-add-comp:hover { background:#D1E8F8; }

    .comp-hint { font-size:0.75rem; color:var(--text-muted); margin-top:8px; }

    .btn-submit { background:var(--blue-main); color:#fff; border:none; border-radius:8px; padding:12px 28px; font-size:0.95rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:8px; }
    .btn-submit:hover { background:var(--blue-dark); }

    @media(max-width:600px) { .row.g-3 > div { flex:0 0 100%; max-width:100%; } }
  </style>
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

    <h1>Publier une offre</h1>
    <p class="subtitle">
      <?php if ($entreprise): ?>
        Pour : <strong><?= htmlspecialchars($entreprise['nom']) ?></strong>
      <?php else: ?>
        Vous n'avez pas encore d'entreprise associée à votre compte.
      <?php endif; ?>
    </p>

    <?php if ($erreur): ?>
      <div class="alert alert-danger py-2 px-3 mb-3" style="font-size:0.85rem;"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if (!$entreprise): ?>
      <p style="text-align:center; padding:2rem 0;">
        <a href="entreprise.php" class="btn-submit" style="text-decoration:none;">
          <i class="bi bi-building"></i> Créer mon entreprise
        </a>
      </p>
    <?php else: ?>

    <form method="POST" action="publier-offre.php">

      <div class="section-sep">Informations principales</div>

      <div class="form-group">
        <label class="form-label" for="titre">Titre du poste *</label>
        <input type="text" id="titre" name="titre" class="form-control"
               placeholder="Ex : Développeur Full Stack PHP / React"
               value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" required />
      </div>

      <div class="form-group">
        <label class="form-label" for="localisation">Localisation *</label>
        <input type="text" id="localisation" name="localisation" class="form-control"
               placeholder="Ex : Paris 9e, Lyon, Remote"
               value="<?= htmlspecialchars($_POST['localisation'] ?? '') ?>" required />
      </div>

      <div class="form-group">
        <label class="form-label" for="description">Description du poste *</label>
        <textarea id="description" name="description" class="form-control"
                  placeholder="Décrivez les missions, le contexte, l'équipe..."
                  required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
      </div>

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
            <label class="form-label" for="teletravail">Télétravail *</label>
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
            <label class="form-label" for="experience">Expérience *</label>
            <select id="experience" name="experience" class="form-select" required>
              <option value="">Choisir...</option>
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
            <input type="number" id="salaire_min" name="salaire_min" class="form-control"
                   placeholder="Ex : 40000" min="0"
                   value="<?= htmlspecialchars($_POST['salaire_min'] ?? '') ?>" />
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-group">
            <label class="form-label" for="salaire_max">Salaire maximum (€/an)</label>
            <input type="number" id="salaire_max" name="salaire_max" class="form-control"
                   placeholder="Ex : 55000" min="0"
                   value="<?= htmlspecialchars($_POST['salaire_max'] ?? '') ?>" />
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
        <a href="dashboard-recruteur.php"
           style="font-size:0.875rem; color:var(--text-muted); text-decoration:none; padding:12px 16px;">
          Annuler
        </a>
        <button type="submit" class="btn-submit">
          <i class="bi bi-send"></i> Publier l'offre
        </button>
      </div>

    </form>

    <?php endif; ?>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Compétences à restaurer en cas d'erreur de formulaire (vide au premier chargement)
const INIT_COMPETENCES = <?= $competLibellesJson ?>;

const list   = document.getElementById('competences-list');
const btnAdd = document.getElementById('btn-add-comp');

if (!list) throw new Error('Element #competences-list introuvable');

/* ── Crée une ligne compétence ── */
function creerLigne(valeur = '') {
  const row = document.createElement('div');
  row.className = 'competence-row';

  const input = document.createElement('input');
  input.type         = 'text';
  input.name         = 'competences_texte[]';
  input.placeholder  = 'Ex : PHP 8, React, Docker…';
  input.value        = valeur;
  input.maxLength    = 100;
  input.autocomplete = 'off';

  const btnDel = document.createElement('button');
  btnDel.type      = 'button';
  btnDel.className = 'btn-remove-comp';
  btnDel.title     = 'Supprimer cette compétence';
  btnDel.innerHTML = '<i class="bi bi-trash3"></i>';
  btnDel.addEventListener('click', () => {
    if (list.children.length > 1) {
      row.remove();
      mettreAJourBoutonsSuppression();
    } else {
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
  boutons.forEach(b => { b.disabled = boutons.length === 1; });
}

/* ── Bouton "Ajouter une compétence" ── */
btnAdd.addEventListener('click', () => creerLigne('').focus());

/* ── Initialisation ── */
if (INIT_COMPETENCES.length > 0) {
  INIT_COMPETENCES.forEach(lib => creerLigne(lib));
} else {
  creerLigne(''); // toujours au moins une ligne vide
}

/* ── Entrée = passer à la ligne suivante ── */
list.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
    e.preventDefault();
    creerLigne('').focus();
  }
});
</script>
</body>
</html>
