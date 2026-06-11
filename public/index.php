<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/offreModel.php';
$model = new OffreModel();

// Récupération des filtres depuis l'URL
$filtres = [
    'q'           => trim($_GET['q']    ?? ''),
    'ville'       => trim($_GET['ville'] ?? ''),
    'contrat'     => $_GET['contrat']     ?? [],
    'teletravail' => $_GET['teletravail'] ?? [],
    'experience'  => $_GET['xp']          ?? [],
    'secteur'     => trim($_GET['secteur'] ?? ''),
];

// Pagination
$parPage = 10;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$total   = $model->compterOffres($filtres);
$nbPages = (int) ceil($total / $parPage);
$offres  = $model->listerOffres($filtres, $page, $parPage);

// Helper pour afficher le salaire
function formatSalaire(?float $min, ?float $max): string
{
    if (!$min && !$max) return 'Salaire non précisé';
    if ($min && $max)   return number_format($min, 0, ',', ' ') . ' – ' . number_format($max, 0, ',', ' ') . ' €';
    if ($min)           return 'À partir de ' . number_format($min, 0, ',', ' ') . ' €';
    return "Jusqu'à " . number_format($max, 0, ',', ' ') . ' €';
}

// Badge couleur selon le type de contrat
function badgeContrat(string $type): string
{
    return match($type) {
        'CDI'        => 'badge-blue',
        'CDD'        => 'badge-blue',
        'Stage'      => 'badge-amber',
        'Alternance' => 'badge-amber',
        'Freelance'  => 'badge-purple',
        default      => 'badge-gray',
    };
}

// Badge couleur selon le télétravail
function badgeTeletravail(string $t): string
{
    return match($t) {
        'Full remote' => 'badge-green',
        'Hybride'     => 'badge-green',
        default       => 'badge-amber',
    };
}

// Formate la date relative
function dateRelative(string $date): string
{
    $diff = time() - strtotime($date);
    if ($diff < 86400)    return "Aujourd'hui";
    if ($diff < 172800)   return "Hier";
    if ($diff < 604800)   return "Il y a " . round($diff / 86400)  . " jours";
    if ($diff < 2592000)  return "Il y a " . round($diff / 604800) . " semaines";
    return "Il y a " . round($diff / 2592000) . " mois";
}

// Construction de l'URL de pagination avec les filtres actifs
function urlPage(int $page): string
{
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SearchForAJob — Trouvez votre prochain emploi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/index.css">
</head>
<body>

<!-- NAVIGATION -->
<nav class="navbar navbar-expand-lg sticky-top px-3">
  <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
    <i class="bi bi-briefcase-fill" style="color:var(--blue-main);font-size:1.2rem;" aria-hidden="true"></i>
    Search<span>ForAJob</span>
  </a>
  <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-label="Menu">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navMenu">
    <ul class="navbar-nav mx-auto">
      <li class="nav-item"><a class="nav-link active" href="index.php">Offres</a></li>
    </ul>
    <div class="d-flex gap-2">
      <?php if (isLoggedIn()): ?>
        <?php if (currentRole() === 'recruteur'): ?>
          <a href="dashboard-recruteur.php" class="btn btn-connexion">Mon espace recruteur</a>
        <?php else: ?>
          <a href="dashboard-candidat.php" class="btn btn-connexion">Mes candidatures</a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-publier">Déconnexion</a>
      <?php else: ?>
        <a href="login.php"            class="btn btn-connexion">Connexion</a>
        <a href="register.php?role=recruteur" class="btn btn-publier">Publier une offre</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<!-- HERO -->
<header class="hero">
  <h1>Trouvez votre prochain emploi</h1>
  <p><?= $total ?> offre<?= $total > 1 ? 's' : '' ?> disponible<?= $total > 1 ? 's' : '' ?> en ce moment</p>
  <form class="search-box" action="index.php" method="GET" role="search">
    <label for="search-q" class="visually-hidden">Rechercher</label>
    <input type="search" id="search-q" name="q"
           placeholder="Titre, compétence, mot-clé..."
           value="<?= htmlspecialchars($filtres['q']) ?>" />
    <label for="search-ville" class="visually-hidden">Ville</label>
    <select id="search-ville" name="ville">
      <option value="">Toute la France</option>
      <?php foreach (['Paris','Lyon','Marseille','Bordeaux','Toulouse','Remote'] as $v): ?>
        <option value="<?= $v ?>" <?= $filtres['ville'] === $v ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit"><i class="bi bi-search" aria-hidden="true"></i> Rechercher</button>
  </form>
</header>

<!-- STATS -->
<section class="stats-bar" aria-label="Statistiques">
  <ul>
    <li><i class="bi bi-briefcase" aria-hidden="true"></i> <strong><?= $total ?></strong>&nbsp;offres actives</li>
  </ul>
</section>

<!-- CONTENU PRINCIPAL -->
<main class="main-layout">

  <!-- FILTRES -->
  <aside class="filters" aria-label="Filtres">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>Filtres</h2>
      <a href="index.php" style="font-size:0.8rem; color:var(--text-muted);">Réinitialiser</a>
    </div>

    <form method="GET" action="index.php">
      <?php if ($filtres['q']): ?>
        <input type="hidden" name="q" value="<?= htmlspecialchars($filtres['q']) ?>" />
      <?php endif; ?>

      <fieldset>
        <legend>Type de contrat</legend>
        <?php foreach (['CDI','CDD','Stage','Alternance','Freelance'] as $c): ?>
          <div class="form-check mb-1">
            <input class="form-check-input" type="checkbox" name="contrat[]" value="<?= $c ?>"
                   id="f-<?= strtolower($c) ?>"
                   <?= in_array($c, $filtres['contrat']) ? 'checked' : '' ?> />
            <label class="form-check-label" for="f-<?= strtolower($c) ?>"><?= $c ?></label>
          </div>
        <?php endforeach; ?>
      </fieldset>

      <hr class="filter-divider" />

      <fieldset>
        <legend>Télétravail</legend>
        <?php foreach (['Hybride','Full remote','Presentiel'] as $t): ?>
          <div class="form-check mb-1">
            <input class="form-check-input" type="checkbox" name="teletravail[]" value="<?= $t ?>"
                   id="f-<?= strtolower(str_replace(' ','-',$t)) ?>"
                   <?= in_array($t, $filtres['teletravail']) ? 'checked' : '' ?> />
            <label class="form-check-label" for="f-<?= strtolower(str_replace(' ','-',$t)) ?>"><?= $t ?></label>
          </div>
        <?php endforeach; ?>
      </fieldset>

      <hr class="filter-divider" />

      <fieldset>
        <legend>Expérience</legend>
        <?php foreach (['Junior','Confirme','Senior'] as $x): ?>
          <div class="form-check mb-1">
            <input class="form-check-input" type="checkbox" name="xp[]" value="<?= $x ?>"
                   id="f-<?= strtolower($x) ?>"
                   <?= in_array($x, $filtres['experience']) ? 'checked' : '' ?> />
            <label class="form-check-label" for="f-<?= strtolower($x) ?>"><?= $x ?></label>
          </div>
        <?php endforeach; ?>
      </fieldset>

      <button type="submit" class="btn btn-publier w-100 mt-3">Appliquer</button>
    </form>
  </aside>

  <!-- OFFRES -->
  <section class="offers" aria-label="Liste des offres">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2><?= $total ?> offre<?= $total > 1 ? 's' : '' ?> trouvée<?= $total > 1 ? 's' : '' ?></h2>
    </div>

    <?php if (empty($offres)): ?>
      <div class="empty-state">
        <i class="bi bi-search" style="font-size:2rem;"></i>
        <p class="mt-2">Aucune offre ne correspond à vos critères.</p>
        <a href="index.php" class="btn btn-publier mt-2">Voir toutes les offres</a>
      </div>
    <?php else: ?>

    <ul class="offers-list">
      <?php foreach ($offres as $i => $offre): ?>
      <li>
        <article class="offer-card <?= $i === 0 ? 'featured' : '' ?>">
          <a href="offre.php?id=<?= $offre['id'] ?>" class="offer-link">
            <div class="d-flex align-items-start gap-3">
              <figure class="offer-logo m-0" aria-hidden="true">
                <?= strtoupper(substr($offre['entreprise_nom'], 0, 3)) ?>
              </figure>
              <hgroup class="flex-grow-1">
                <h3 class="offer-title"><?= htmlspecialchars($offre['titre']) ?></h3>
                <address class="offer-company">
                  <?= htmlspecialchars($offre['entreprise_nom']) ?> &middot; <?= htmlspecialchars($offre['localisation']) ?>
                </address>
              </hgroup>
              <button class="btn-bookmark" onclick="event.preventDefault(); toggleBookmark(this);" aria-label="Sauvegarder">
                <i class="bi bi-bookmark" aria-hidden="true"></i>
              </button>
            </div>

            <ul class="offer-tags" aria-label="Caractéristiques">
              <li><span class="badge <?= badgeContrat($offre['type_contrat']) ?>"><?= $offre['type_contrat'] ?></span></li>
              <li><span class="badge <?= badgeTeletravail($offre['teletravail']) ?>"><?= $offre['teletravail'] ?></span></li>
              <li><span class="badge badge-gray"><?= $offre['experience'] ?></span></li>
            </ul>

            <footer class="offer-footer">
              <span class="offer-location"><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= htmlspecialchars($offre['localisation']) ?></span>
              <strong class="offer-salary"><?= formatSalaire($offre['salaire_min'], $offre['salaire_max']) ?></strong>
              <time class="offer-date" datetime="<?= $offre['date_publication'] ?>">
                <?= dateRelative($offre['date_publication']) ?>
              </time>
            </footer>
          </a>
        </article>
      </li>
      <?php endforeach; ?>



    </ul>

    <!-- PAGINATION -->
    <?php if ($nbPages > 1): ?>
    <nav class="pagination-nav mt-4" aria-label="Pagination">
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= urlPage($page - 1) ?>"><i class="bi bi-chevron-left"></i></a>
        </li>
        <?php for ($p = 1; $p <= $nbPages; $p++): ?>
          <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= urlPage($p) ?>"><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $nbPages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= urlPage($page + 1) ?>"><i class="bi bi-chevron-right"></i></a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>

    <?php endif; ?>
  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/index.js"></script>
</body>
</html>