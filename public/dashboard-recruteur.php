<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/UserModel.php';
require_once __DIR__ . '/../src/offreModel.php';
require_once __DIR__ . '/../src/CandidatureModel.php';

requireRole('recruteur');

$userModel        = new UserModel();
$offreModel       = new OffreModel();
$candidatureModel = new CandidatureModel();

$profil = $userModel->getProfil(currentUserId());
$offres = $offreModel->getOffresRecruteur(currentUserId());

// Traitement changement de statut candidature (AJAX-like via POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'changer_statut') {
        $candidatId = (int) ($_POST['candidat_id'] ?? 0);
        $offreId    = (int) ($_POST['offre_id']    ?? 0);
        $statut     = $_POST['statut'] ?? '';
        try {
            $candidatureModel->changerStatut($candidatId, $offreId, $statut);
        } catch (Exception $e) { /* silencieux */ }
        header('Location: dashboard-recruteur.php?offre=' . $offreId);
        exit;
    }
    if ($_POST['action'] === 'supprimer_offre') {
        $offreId = (int) ($_POST['offre_id'] ?? 0);
        $offreModel->supprimerOffre($offreId, currentUserId());
        header('Location: dashboard-recruteur.php');
        exit;
    }
}

// Candidatures d'une offre sélectionnée
$offreSelectId   = (int) ($_GET['offre'] ?? 0);
$candidatures    = $offreSelectId ? $candidatureModel->getCandidaturesOffre($offreSelectId) : [];
$offreSelectInfo = null;
if ($offreSelectId) {
    foreach ($offres as $o) {
        if ((int)$o['id'] === $offreSelectId) { $offreSelectInfo = $o; break; }
    }
}

// Stats globales
$totalOffres      = count($offres);
$totalCandidatures = array_sum(array_column($offres, 'nb_candidatures'));
$offresActives    = count(array_filter($offres, fn($o) => $o['is_active']));

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Espace recruteur — SearchForAJob</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/dashboard-recruteur.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg sticky-top px-3">
  <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
    <i class="bi bi-briefcase-fill" style="color:var(--blue-main);font-size:1.2rem;" aria-hidden="true"></i>
    Search<span>ForAJob</span>
  </a>
  <div class="ms-auto d-flex gap-2">
    <a href="entreprise.php" class="btn-outline-sm">
      <i class="bi bi-building"></i> Mon entreprise
    </a>
    <a href="publier-offre.php" class="btn-publier">
      <i class="bi bi-plus-lg"></i> Publier une offre
    </a>
    <a href="logout.php" class="btn-outline-sm">Deconnexion</a>
  </div>
</nav>

<main class="dashboard">

  <!-- COLONNE GAUCHE -->
  <div class="sidebar-left">

    <!-- PROFIL -->
    <div class="card-block">
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="avatar">
          <?php echo strtoupper(substr($profil['prenom'] ?? 'R', 0, 1) . substr($profil['nom'] ?? '', 0, 1)); ?>
        </div>
        <div>
          <p class="profil-nom"><?= htmlspecialchars(($profil['prenom'] ?? '') . ' ' . ($profil['nom'] ?? '')) ?></p>
          <p class="profil-email"><?= htmlspecialchars($profil['email'] ?? '') ?></p>
        </div>
      </div>
      <a href="publier-offre.php" class="btn-publier w-100 text-center d-block">
        <i class="bi bi-plus-lg"></i> Nouvelle offre
      </a>
    </div>

    <!-- STATS -->
    <div class="card-block">
      <div class="stat-row">
        <span class="label">Offres publiees</span>
        <span class="value"><?= $totalOffres ?></span>
      </div>
      <div class="stat-row">
        <span class="label">Offres actives</span>
        <span class="value"><?= $offresActives ?></span>
      </div>
      <div class="stat-row">
        <span class="label">Total candidatures</span>
        <span class="value"><?= $totalCandidatures ?></span>
      </div>
    </div>

    <!-- LISTE DES OFFRES -->
    <div class="card-block">
      <p style="font-size:0.8rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:.05em; margin-bottom:0.75rem;">Mes offres</p>
      <?php if (empty($offres)): ?>
        <p style="font-size:0.85rem; color:var(--text-muted); text-align:center; padding:1rem 0;">
          Aucune offre publiee.
        </p>
      <?php else: ?>
      <ul class="offres-list">
        <?php foreach ($offres as $o):
          $actif   = $o['is_active'] ? '' : ' (inactive)';
          $active  = ((int)$o['id'] === $offreSelectId) ? 'active' : '';
          $oid     = $o['id'];
          $titre   = htmlspecialchars($o['titre']);
          $contrat = htmlspecialchars($o['type_contrat']);
          $nb      = $o['nb_candidatures'];

          echo "
          <li>
            <a href='dashboard-recruteur.php?offre=$oid' class='offre-item $active'>
              <div>
                <div class='offre-item-title'>$titre$actif</div>
                <div class='offre-item-meta'>$contrat</div>
              </div>
              <span class='nb-candidatures'>$nb</span>
            </a>
          </li>";
        endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>

  </div>

  <!-- COLONNE DROITE : CANDIDATURES -->
  <section class="section-right">

    <?php if ($offreSelectInfo): ?>

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 style="margin:0;">
          Candidatures pour : <?= htmlspecialchars($offreSelectInfo['titre']) ?>
          <span style="font-size:0.8rem; font-weight:400; color:var(--text-muted);">
            (<?= count($candidatures) ?> candidat<?= count($candidatures) > 1 ? 's' : '' ?>)
          </span>
        </h2>
        <!-- Bouton modifier / supprimer l'offre -->
        <div class="d-flex gap-2">
          <a href="modifier-offre.php?id=<?= $offreSelectId ?>" class="btn-outline-sm">
            <i class="bi bi-pencil"></i> Modifier
          </a>
          <form method="POST" action="dashboard-recruteur.php"
                onsubmit="return confirm('Supprimer cette offre ?');">
            <input type="hidden" name="action"   value="supprimer_offre" />
            <input type="hidden" name="offre_id" value="<?= $offreSelectId ?>" />
            <button type="submit" class="btn-danger-sm">
              <i class="bi bi-trash"></i> Supprimer l offre
            </button>
          </form>
        </div>
      </div>

      <?php if (empty($candidatures)): ?>
        <div class="empty-state">
          <i class="bi bi-inbox"></i>
          <p>Aucune candidature pour cette offre.</p>
        </div>
      <?php else: ?>

      <div class="candidatures-list">
        <?php foreach ($candidatures as $c):
          $nom        = htmlspecialchars(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? ''));
          $email      = htmlspecialchars($c['email'] ?? '');
          $tel        = htmlspecialchars($c['telephone'] ?? '');
          $date       = date('d/m/Y', strtotime($c['date_candidature']));
          $statut     = $c['statut'];
          $badgeCl    = badgeStatut($statut);
          $candidatId = $c['candidat_id'];
          $cvPath     = $c['cv_path'] ?? '';
          $lettre     = htmlspecialchars($c['lettre_motivation'] ?? '');
          $initiales  = strtoupper(substr($c['prenom'] ?? 'C', 0, 1) . substr($c['nom'] ?? '', 0, 1));
          $lettreId   = 'lettre-' . $candidatId;

          echo "
          <div class='candidat-card'>

            <!-- EN-TETE -->
            <div class='candidat-header'>
              <div class='candidat-avatar'>$initiales</div>
              <div class='candidat-info'>
                <div class='candidat-nom'>$nom</div>
                <div class='candidat-email'>
                  <i class='bi bi-envelope' aria-hidden='true'></i> $email
                </div>";

          if ($tel) {
              echo "<div class='candidat-email'>
                      <i class='bi bi-telephone' aria-hidden='true'></i> $tel
                    </div>";
          }

          echo "  </div>
              <div class='candidat-meta'>
                <span class='badge $badgeCl'>$statut</span>
                <div style='font-size:0.75rem; color:var(--text-muted); margin-top:4px;'>
                  <i class='bi bi-calendar' aria-hidden='true'></i> $date
                </div>
              </div>
            </div>

            <!-- DOCUMENTS -->
            <div class='candidat-docs'>";

          // CV
          if ($cvPath) {
              echo "<a href='../assets/uploads/$cvPath' target='_blank' class='doc-btn'>
                      <i class='bi bi-file-earmark-pdf'></i>
                      <span>Voir le CV</span>
                    </a>";
          } else {
              echo "<span class='doc-btn doc-btn-empty'>
                      <i class='bi bi-file-earmark'></i>
                      <span>CV non fourni</span>
                    </span>";
          }

          // Lettre de motivation
          if ($lettre) {
              echo "<button class='doc-btn' onclick=\"toggleLettre('$lettreId')\">
                      <i class='bi bi-file-text'></i>
                      <span>Lettre de motivation</span>
                    </button>";
          } else {
              echo "<span class='doc-btn doc-btn-empty'>
                      <i class='bi bi-file-text'></i>
                      <span>Pas de lettre</span>
                    </span>";
          }

          echo "  </div>";

          // Contenu lettre (masque par defaut)
          if ($lettre) {
              echo "
              <div class='lettre-content' id='$lettreId' style='display:none;'>
                <div class='lettre-label'>
                  <i class='bi bi-quote'></i> Lettre de motivation
                </div>
                <p class='lettre-text'>$lettre</p>
              </div>";
          }

          // Changement de statut
          echo "
            <div class='candidat-footer'>
              <span style='font-size:0.8rem; color:var(--text-muted);'>Changer le statut :</span>
              <form method='POST' action='dashboard-recruteur.php' style='display:inline;'>
                <input type='hidden' name='action'      value='changer_statut' />
                <input type='hidden' name='candidat_id' value='$candidatId' />
                <input type='hidden' name='offre_id'    value='$offreSelectId' />
                <select class='statut-select' name='statut' onchange='this.form.submit()'>
                  <option value='En attente' " . ($statut === 'En attente' ? 'selected' : '') . ">En attente</option>
                  <option value='Vue'        " . ($statut === 'Vue'        ? 'selected' : '') . ">Vue</option>
                  <option value='Acceptee'   " . ($statut === 'Acceptee'   ? 'selected' : '') . ">Acceptee</option>
                  <option value='Refusee'    " . ($statut === 'Refusee'    ? 'selected' : '') . ">Refusee</option>
                </select>
              </form>
            </div>
          </div>";

        endforeach; ?>
      </div>

      <?php endif; ?>

    <?php else: ?>

      <!-- AUCUNE OFFRE SELECTIONNEE -->
      <div class="empty-state" style="margin-top:4rem;">
        <i class="bi bi-cursor-text"></i>
        <p>Selectionnez une offre dans la liste pour voir ses candidatures.</p>
        <a href="publier-offre.php" class="btn-publier mt-2 d-inline-block">
          <i class="bi bi-plus-lg"></i> Publier une offre
        </a>
      </div>

    <?php endif; ?>

  </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function toggleLettre(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const visible = el.style.display !== 'none';
    el.style.display = visible ? 'none' : 'block';
    // Changer le texte du bouton
    const btn = el.previousElementSibling?.querySelector('button.doc-btn');
    if (btn) {
      const span = btn.querySelector('span');
      if (span) span.textContent = visible ? 'Lettre de motivation' : 'Masquer la lettre';
    }
  }
</script>
</body>
</html>
