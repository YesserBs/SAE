<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/UserModel.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $erreur = 'Veuillez remplir tous les champs.';
    } else {
        $model = new UserModel();
        $user  = $model->connecter($email, $password);

        if ($user) {
            // On stocke les infos essentielles en session
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['user_nom']   = $user['prenom'] . ' ' . $user['nom'];

            // Redirection selon le rôle
            if ($user['role'] === 'recruteur') {
                header('Location: dashboard-recruteur.php');
            } else {
                header('Location: dashboard-candidat.php');
            }
            exit;
        } else {
            $erreur = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Connexion — SearchForAJob</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>

<main>
  <section class="card-auth">

    <div class="text-center mb-4">
      <a href="index.php" class="brand text-decoration-none">
        <i class="bi bi-briefcase-fill me-1" style="color:var(--blue-main)"></i>
        Search<span>ForAJob</span>
      </a>
      <p class="text-muted mt-1" style="font-size:0.85rem;">Connectez-vous à votre espace</p>
    </div>

    <?php if ($erreur): ?>
      <div class="alert alert-danger py-2 px-3" style="font-size:0.85rem;"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>

      <div class="mb-3">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               placeholder="votre@email.com" required autofocus />
      </div>

      <div class="mb-4">
        <label for="password">Mot de passe</label>
        <div class="input-group">
          <input type="password" id="password" name="password" class="form-control"
                 placeholder="••••••••" required />
          <button type="button" class="btn btn-outline-secondary border"
                  onclick="togglePwd()" aria-label="Afficher le mot de passe">
            <i class="bi bi-eye" id="eye-icon"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-primary-custom">Se connecter</button>

    </form>

    <p class="text-center mt-3" style="font-size:0.85rem; color:#6B7280;">
      Pas encore de compte ?
      <a href="register.php" style="color:var(--blue-main);">Créer un compte</a>
    </p>

  </section>
</main>

<script>
  function togglePwd() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eye-icon');
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
  }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>