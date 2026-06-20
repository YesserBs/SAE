<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../backend/AdzunaClient.php';

$what  = trim($_GET['what']  ?? 'developer');
$where = trim($_GET['where'] ?? 'Paris');
$page  = max(1, (int) ($_GET['page'] ?? 1));

$resultats = [];
$total     = 0;
$erreur    = '';

try {
    $client    = new AdzunaClient();
    $reponse   = $client->rechercherOffres($what, $where, $page, 20);
    $resultats = $reponse['results'] ?? [];
    $total     = (int) ($reponse['count'] ?? 0);
} catch (Throwable $e) {
    $erreur = $e->getMessage();
}

// Traduction simple des champs Adzuna pour l'affichage
function labelContrat(array $offre): string
{
    $type = $offre['contract_type'] ?? '';
    return match($type) {
        'permanent' => 'CDI',
        'contract'  => 'CDD / Mission',
        default     => 'Non précisé',
    };
}

function labelTemps(array $offre): string
{
    $temps = $offre['contract_time'] ?? '';
    return match($temps) {
        'full_time' => 'Temps plein',
        'part_time' => 'Temps partiel',
        default     => '',
    };
}

function formatSalaireAdzuna(?float $min, ?float $max): string
{
    if (!$min && !$max) return 'Salaire non précisé';
    if ($min && $max)   return number_format($min, 0, ',', ' ') . ' – ' . number_format($max, 0, ',', ' ') . ' €';
    if ($min)           return 'À partir de ' . number_format($min, 0, ',', ' ') . ' €';
    return "Jusqu'à " . number_format($max, 0, ',', ' ') . ' €';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Offres partenaires — SearchForAJob</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/index.css">
  <style>
    .partner-badge { display:inline-flex; align-items:center; gap:6px; background:var(--blue-light); color:var(--blue-main); font-size:0.75rem; font-weight:600; padding:4px 10px; border-radius:20px; margin-bottom:1rem; }
    .search-box-inline { display:flex; gap:10px; max-width:680px; margin:0 auto 2rem; flex-wrap:wrap; }
    .search-box-inline input { flex:1; min-width:160px; border:1px solid rgba(255,255,255,0.4); border-radius:8px; padding:10px 14px; font-size:0.9rem; }
    .search-box-inline button { background:#fff; color:var(--blue-dark); border:none; border-radius:8px; padding:10px 20px; font-weight:600; cursor:pointer; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top px-3">
  <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
    <i class="bi bi-briefcase-fill" style="color:var(--blue-main);font-size:1.2rem;" aria-hidden="true"></i>
    Search<span>ForAJob</span>
  </a>
  <div class="ms-auto d-flex gap-2">
    <a href="index.php" style="font-size:0.85rem; color:var(--text-muted); text-decoration:none; align-self:center;">
      <i class="bi bi-arrow-left"></i> Offres SearchForAJob
    </a>
  </div>
</nav>

<header class="hero">
  <span class="partner-badge"><i class="bi bi-broadcast"></i> Propulsé par Adzuna</span>
  <h1>Offres partenaires</h1>
  <p>Élargissez votre recherche à des offres publiées ailleurs sur le web</p>

  <form class="search-box-inline" action="offres-partenaires.php" method="GET">
    <input type="text" name="what" placeholder="Poste, mot-clé..." value="<?= htmlspecialchars($what) ?>" />
    <input type="text" name="where" placeholder="Ville" value="<?= htmlspecialchars($where) ?>" />
    <button type="submit"><i class="bi bi-search"></i> Rechercher</button>
  </form>
</header>

<main class="main-layout" style="grid-template-columns:1fr;">
  <section class="offers" aria-label="Offres partenaires">

    <?php if ($erreur): ?>
      <div class="empty-state">
        <i class="bi bi-exclamation-triangle" style="font-size:2rem;"></i>
        <p class="mt-2">Impossible de récupérer les offres partenaires pour le moment.</p>
        <p style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($erreur) ?></p>
      </div>
    <?php elseif (empty($resultats)): ?>
      <div class="empty-state">
        <i class="bi bi-search" style="font-size:2rem;"></i>
        <p class="mt-2">Aucune offre partenaire ne correspond à votre recherche.</p>
      </div>
    <?php else: ?>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><?= $total ?> offre<?= $total > 1 ? 's' : '' ?> partenaire<?= $total > 1 ? 's' : '' ?> trouvée<?= $total > 1 ? 's' : '' ?></h2>
      </div>

      <ul class="offers-list">
        <?php foreach ($resultats as $offre): ?>
          <li>
            <article class="offer-card">
              <a href="<?= htmlspecialchars($offre['redirect_url'] ?? '#') ?>" class="offer-link" target="_blank" rel="noopener noreferrer">
                <div class="d-flex align-items-start gap-3">
                  <figure class="offer-logo m-0" aria-hidden="true">
                    <?= strtoupper(substr($offre['company']['display_name'] ?? '??', 0, 3)) ?>
                  </figure>
                  <hgroup class="flex-grow-1">
                    <h3 class="offer-title"><?= htmlspecialchars($offre['title'] ?? 'Offre sans titre') ?></h3>
                    <address class="offer-company">
                      <?= htmlspecialchars($offre['company']['display_name'] ?? 'Entreprise non précisée') ?>
                      &middot; <?= htmlspecialchars($offre['location']['display_name'] ?? '') ?>
                    </address>
                  </hgroup>
                  <span class="badge badge-gray" style="white-space:nowrap;"><i class="bi bi-box-arrow-up-right"></i> Adzuna</span>
                </div>

                <ul class="offer-tags" aria-label="Caractéristiques">
                  <li><span class="badge badge-blue"><?= labelContrat($offre) ?></span></li>
                  <?php if (labelTemps($offre)): ?>
                    <li><span class="badge badge-amber"><?= labelTemps($offre) ?></span></li>
                  <?php endif; ?>
                </ul>

                <footer class="offer-footer">
                  <span class="offer-location">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i> <?= htmlspecialchars($offre['location']['display_name'] ?? 'Non précisé') ?>
                  </span>
                  <strong class="offer-salary">
                    <?= formatSalaireAdzuna(
                          isset($offre['salary_min']) ? (float) $offre['salary_min'] : null,
                          isset($offre['salary_max']) ? (float) $offre['salary_max'] : null
                        ) ?>
                  </strong>
                  <time class="offer-date">
                    <?= !empty($offre['created']) ? date('d/m/Y', strtotime($offre['created'])) : '' ?>
                  </time>
                </footer>
              </a>
            </article>
          </li>
        <?php endforeach; ?>
      </ul>

      <!-- PAGINATION -->
      <nav class="pagination-nav mt-4" aria-label="Pagination">
        <ul class="pagination justify-content-center">
          <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"><i class="bi bi-chevron-left"></i></a>
          </li>
          <li class="page-item active"><span class="page-link"><?= $page ?></span></li>
          <li class="page-item">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"><i class="bi bi-chevron-right"></i></a>
          </li>
        </ul>
      </nav>

    <?php endif; ?>
  </section>
</main>

<?php require_once __DIR__ . '/../src/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
