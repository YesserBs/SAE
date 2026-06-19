<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/UserModel.php';
require_once __DIR__ . '/../src/CandidatureModel.php';

requireRole('candidat');

$userModel        = new UserModel();
$candidatureModel = new CandidatureModel();

$profil       = $userModel->getProfil(currentUserId());
$candidatures = $candidatureModel->getCandidaturesCandidat(currentUserId());

// Compteurs par statut
$stats = ['En attente' => 0, 'Vue' => 0, 'Acceptee' => 0, 'Refusee' => 0];
foreach ($candidatures as $c) {
    if (isset($stats[$c['statut']])) $stats[$c['statut']]++;
}

function badgeStatut(string $statut): string
{
    return match($statut) {
        'En attente' => 'badge-amber',
        'Vue'        => 'badge-blue',
        'Acceptee'   => 'badge-green',
        'Refusee'    => 'badge-red',
        default      => 'badge-gray',
    };
}

function iconStatut(string $statut): string
{
    return match($statut) {
        'En attente' => 'bi-clock',
        'Vue'        => 'bi-eye',
        'Acceptee'   => 'bi-check-circle',
        'Refusee'    => 'bi-x-circle',
        default      => 'bi-question-circle',
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mon espace candidat — SearchForAJob</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/dashboard-candidat.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg sticky-top px-3">
  <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
    <i class="bi bi-briefcase-fill" style="color:var(--blue-main);font-size:1.2rem;" aria-hidden="true"></i>
    Search<span>ForAJob</span>
  </a>
  <div class="ms-auto d-flex gap-2">
    <a href="index.php" class="btn btn-edit-profil">
      <i class="bi bi-search"></i> Offres
    </a>
    <a href="logout.php" class="btn btn-publier">Deconnexion</a>
  </div>
</nav>

<main class="dashboard">

  <!-- PROFIL HEADER -->
  <div class="profil-header">
    <div class="avatar">
      <?php
        $initiales = strtoupper(substr($profil['prenom'] ?? 'U', 0, 1) . substr($profil['nom'] ?? '', 0, 1));
        echo $initiales;
      ?>
    </div>
    <div class="profil-info">
      <h1><?= htmlspecialchars(($profil['prenom'] ?? '') . ' ' . ($profil['nom'] ?? '')) ?></h1>
      <p><?= htmlspecialchars($profil['email'] ?? '') ?></p>
      <?php if (!empty($profil['telephone'])): ?>
        <p><?= htmlspecialchars($profil['telephone']) ?></p>
      <?php endif; ?>
    </div>
    <a href="profil.php" class="btn-edit-profil">
      <i class="bi bi-pencil"></i> Modifier le profil
    </a>
  </div>

  <!-- STATISTIQUES -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-number"><?= count($candidatures) ?></div>
      <div class="stat-label">Total candidatures</div>
    </div>
    <div class="stat-card">
      <div class="stat-number" style="color:#633806;"><?= $stats['En attente'] ?></div>
      <div class="stat-label">En attente</div>
    </div>
    <div class="stat-card acceptee">
      <div class="stat-number"><?= $stats['Acceptee'] ?></div>
      <div class="stat-label">Acceptees</div>
    </div>
    <div class="stat-card refusee">
      <div class="stat-number"><?= $stats['Refusee'] ?></div>
      <div class="stat-label">Refusees</div>
    </div>
  </div>

  <!-- LISTE DES CANDIDATURES -->
  <h2 class="section-title">Mes candidatures</h2>

  <?php if (empty($candidatures)): ?>
    <div class="empty-state">
      <i class="bi bi-inbox"></i>
      <p>Vous n avez pas encore postule a une offre.</p>
      <a href="index.php" class="btn btn-publier mt-2">Voir les offres</a>
    </div>
  <?php else: ?>

  <div class="table-candidatures">
    <table>
      <thead>
        <tr>
          <th>Offre</th>
          <th>Contrat</th>
          <th>Date</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($candidatures as $c):
          $badgeClass = badgeStatut($c['statut']);
          $iconClass  = iconStatut($c['statut']);
          $titre      = htmlspecialchars($c['titre']);
          $entreprise = htmlspecialchars($c['entreprise_nom']);
          $contrat    = htmlspecialchars($c['type_contrat']);
          $localisa   = htmlspecialchars($c['localisation']);
          $statut     = htmlspecialchars($c['statut']);
          $date       = date('d/m/Y', strtotime($c['date_candidature']));
          $offreId    = $c['offre_id'];

          echo "
          <tr>
            <td>
              <a href='offre.php?id=$offreId' class='offre-titre'>$titre</a>
              <div class='entreprise-nom'>$entreprise &middot; $localisa</div>
            </td>
            <td><span class='badge badge-gray'>$contrat</span></td>
            <td style='font-size:0.8rem; color:var(--text-muted);'>$date</td>
            <td><span class='badge $badgeClass'><i class='bi $iconClass'></i> $statut</span></td>
          </tr>";
        endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
