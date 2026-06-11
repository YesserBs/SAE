<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/UserModel.php';

// Si déjà connecté, rediriger
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$erreur  = '';
$succes  = '';
$role    = $_GET['role'] ?? 'candidat';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom']       ?? '');
    $prenom    = trim($_POST['prenom']    ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = trim($_POST['password']  ?? '');
    $password2 = trim($_POST['password2'] ?? '');
    $role      = $_POST['role'] ?? 'candidat';

    // Validation simple
    if (!$nom || !$prenom || !$email || !$password) {
        $erreur = 'Tous les champs obligatoires doivent être remplis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Adresse email invalide.';
    } elseif (strlen($password) < 6) {
        $erreur = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $password2) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } elseif (!in_array($role, ['candidat', 'recruteur'], true)) {
        $erreur = 'Rôle invalide.';
    } else {
        try {
            $model  = new UserModel();
            $userId = $model->inscrire([
                'nom'       => $nom,
                'prenom'    => $prenom,
                'email'     => $email,
                'password'  => $password,
                'role'      => $role,
                'telephone' => trim($_POST['telephone'] ?? ''),
            ]);
            $succes = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
        } catch (RuntimeException $e) {
            $erreur = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inscription — SearchForAJob</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    :root { --blue-main: #185FA5; --blue-dark: #0C447C; --blue-light: #E6F1FB; }
    body  { background: #F4F6F9; font-family: 'Segoe UI', system-ui, sans-serif; }
    .card-auth { max-width: 480px; margin: 3rem auto; background: #fff; border-radius: 12px; border: 1px solid rgba(0,0,0,0.08); padding: 2rem; }
    .brand     { font-size: 1.2rem; font-weight: 700; color: #1a1a2e; }
    .brand span { color: var(--blue-main); }
    .btn-primary-custom { background: var(--blue-main); color: #fff; border: none; border-radius: 7px; padding: 10px; width: 100%; font-size: 0.95rem; }
    .btn-primary-custom:hover { background: var(--blue-dark); color: #fff; }
    .role-btn  { border: 1px solid rgba(0,0,0,0.15); border-radius: 8px; padding: 10px 16px; cursor: pointer; background: #fff; flex: 1; text-align: center; font-size: 0.875rem; transition: all 0.15s; }
    .role-btn.active { border-color: var(--blue-main); background: var(--blue-light); color: var(--blue-main); font-weight: 600; }
    label { font-size: 0.875rem; font-weight: 500; margin-bottom: 4px; }
    .form-control { font-size: 0.875rem; border-radius: 7px; border: 1px solid rgba(0,0,0,0.15); }
    .form-control:focus { border-color: var(--blue-main); box-shadow: 0 0 0 3px rgba(24,95,165,0.12); }
  </style>
</head>
<body>

<main>
  <section class="card-auth">

    <!-- Logo -->
    <div class="text-center mb-4">
      <a href="index.php" class="brand text-decoration-none">
        <i class="bi bi-briefcase-fill me-1" style="color:var(--blue-main)"></i>
        Search<span>ForAJob</span>
      </a>
      <p class="text-muted mt-1" style="font-size:0.85rem;">Créez votre compte gratuitement</p>
    </div>

    <?php if ($erreur): ?>
      <div class="alert alert-danger py-2 px-3" style="font-size:0.85rem;"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <?php if ($succes): ?>
      <div class="alert alert-success py-2 px-3" style="font-size:0.85rem;">
        <?= htmlspecialchars($succes) ?>
        <a href="login.php" class="fw-semibold">Se connecter</a>
      </div>
    <?php else: ?>

    <form method="POST" action="register.php" novalidate>

      <!-- Choix du rôle -->
      <div class="mb-4">
        <label class="d-block mb-2">Je suis :</label>
        <div class="d-flex gap-2">
          <label class="role-btn <?= $role === 'candidat' ? 'active' : '' ?>" id="label-candidat">
            <input type="radio" name="role" value="candidat" class="d-none"
                   <?= $role === 'candidat' ? 'checked' : '' ?> />
            <i class="bi bi-person me-1"></i> Candidat
          </label>
          <label class="role-btn <?= $role === 'recruteur' ? 'active' : '' ?>" id="label-recruteur">
            <input type="radio" name="role" value="recruteur" class="d-none"
                   <?= $role === 'recruteur' ? 'checked' : '' ?> />
            <i class="bi bi-building me-1"></i> Recruteur
          </label>
        </div>
      </div>

      <!-- Nom / Prénom -->
      <div class="row g-2 mb-3">
        <div class="col">
          <label for="prenom">Prénom *</label>
          <input type="text" id="prenom" name="prenom" class="form-control"
                 value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" required />
        </div>
        <div class="col">
          <label for="nom">Nom *</label>
          <input type="text" id="nom" name="nom" class="form-control"
                 value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" required />
        </div>
      </div>

      <!-- Email -->
      <div class="mb-3">
        <label for="email">Email *</label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required />
      </div>

      <!-- Téléphone -->
      <div class="mb-3">
        <label for="telephone">Téléphone</label>
        <input type="tel" id="telephone" name="telephone" class="form-control"
               value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>" placeholder="Optionnel" />
      </div>

      <!-- Mot de passe -->
      <div class="mb-3">
        <label for="password">Mot de passe *</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="6 caractères minimum" required />
      </div>

      <div class="mb-4">
        <label for="password2">Confirmer le mot de passe *</label>
        <input type="password" id="password2" name="password2" class="form-control" required />
      </div>

      <button type="submit" class="btn-primary-custom">Créer mon compte</button>

    </form>

    <?php endif; ?>

    <p class="text-center mt-3" style="font-size:0.85rem; color:#6B7280;">
      Déjà un compte ? <a href="login.php" style="color:var(--blue-main);">Se connecter</a>
    </p>

  </section>
</main>

<script>
  // Gestion visuelle des boutons de rôle
  document.querySelectorAll('input[name="role"]').forEach(radio => {
    radio.addEventListener('change', function () {
      document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
      this.closest('.role-btn').classList.add('active');
    });
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>