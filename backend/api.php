<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/AdzunaJobOfferDto.php';
require_once __DIR__ . '/AdzunaJobOfferRepository.php';

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function loadConfig(string $path): array
{
    if (!file_exists($path)) {
        throw new RuntimeException('Le fichier config.json est introuvable.');
    }

    $jsonContent = file_get_contents($path);
    if ($jsonContent === false) {
        throw new RuntimeException('Impossible de lire le fichier config.json.');
    }

    $config = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
        throw new RuntimeException('Erreur dans le JSON : ' . json_last_error_msg());
    }

    return $config;
}

function loadEnvValue(string $key): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        throw new RuntimeException('La variable d environnement ' . $key . ' est introuvable.');
    }

    return (string) $value;
}

function criteriaDefaults(array $preset): array
{
    $defaults = [
        'url' => 'https://api.adzuna.com/v1/api/jobs/{country}/search/{page}',
        'country' => 'fr',
        'page' => 1,
        'results_per_page' => 20,
        'what' => '',
        'where' => '',
        'what_and' => '',
        'what_phrase' => '',
        'what_or' => '',
        'what_exclude' => '',
        'title_only' => '',
        'distance' => '',
        'location0' => '',
        'location1' => '',
        'location2' => '',
        'location3' => '',
        'location4' => '',
        'location5' => '',
        'location6' => '',
        'location7' => '',
        'category' => '',
        'sort_dir' => '',
        'sort_by' => '',
        'salary_min' => '',
        'salary_max' => '',
        'salary_include_unknown' => '',
        'full_time' => '',
        'part_time' => '',
        'contract' => '',
        'permanent' => '',
        'company' => '',
    ];

    foreach ($defaults as $key => $value) {
        if (array_key_exists($key, $preset) && $preset[$key] !== '' && $preset[$key] !== null) {
            $defaults[$key] = (string) $preset[$key];
        }
    }

    $defaults['page'] = max(1, (int) $defaults['page']);
    $defaults['results_per_page'] = max(1, min(50, (int) $defaults['results_per_page']));

    return $defaults;
}

function requestCriteria(array $defaults): array
{
    $criteria = $defaults;

    foreach ($criteria as $key => $value) {
        if (array_key_exists($key, $_REQUEST)) {
            $incoming = $_REQUEST[$key];
            $criteria[$key] = is_string($incoming) ? trim($incoming) : $incoming;
        }
    }

    $criteria['page'] = max(1, (int) ($criteria['page'] ?? 1));
    $criteria['results_per_page'] = max(1, min(50, (int) ($criteria['results_per_page'] ?? 20)));

    return $criteria;
}

function buildAdzunaUrl(array $criteria, string $appId, string $appKey): string
{
    if (empty($criteria['country'])) {
        throw new RuntimeException('Le champ country est manquant.');
    }

    $url = str_replace('{country}', (string) $criteria['country'], (string) $criteria['url']);
    $url = str_replace('{page}', (string) $criteria['page'], $url);

    $params = [
        'app_id' => $appId,
        'app_key' => $appKey,
        'results_per_page' => (int) $criteria['results_per_page'],
    ];

    foreach ($criteria as $key => $value) {
        if (in_array($key, ['url', 'country', 'page', 'results_per_page'], true)) {
            continue;
        }

        if ($value === '' || $value === null) {
            continue;
        }

        $params[$key] = $value;
    }

    return $url . '?' . http_build_query($params);
}

function callApi(string $url): array
{
    $curl = curl_init();

    if ($curl === false) {
        throw new RuntimeException('Impossible d initialiser cURL.');
    }

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $response = curl_exec($curl);

    if ($response === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new RuntimeException('Erreur cURL : ' . $error);
    }

    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('Erreur HTTP ' . $httpCode . ' : ' . $response);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        throw new RuntimeException('La réponse de l API n est pas un JSON valide : ' . json_last_error_msg());
    }

    return $data;
}

function mapAdzunaJobToDto(array $job): AdzunaJobOfferDto
{
    $area = $job['location']['area'] ?? [];

    return new AdzunaJobOfferDto(
      (string) ($job['id'] ?? ''),
      'adzuna',
      trim((string) ($job['title'] ?? 'Offre sans titre')),
      trim((string) ($job['description'] ?? 'Description non disponible')),
      (string) ($job['redirect_url'] ?? ''),
      $job['company']['display_name'] ?? null,
      $job['location']['display_name'] ?? null,
      $area[0] ?? null,
      $area[1] ?? null,
      $area[2] ?? null,
      isset($job['latitude']) ? (float) $job['latitude'] : null,
      isset($job['longitude']) ? (float) $job['longitude'] : null,
      $job['category']['label'] ?? null,
      $job['category']['tag'] ?? null,
      $job['contract_type'] ?? null,
      $job['contract_time'] ?? null,
      isset($job['salary_min']) ? (float) $job['salary_min'] : null,
      isset($job['salary_max']) ? (float) $job['salary_max'] : null,
      isset($job['salary_is_predicted']) && (string) $job['salary_is_predicted'] === '1',
      isset($job['created']) ? date('Y-m-d H:i:s', strtotime((string) $job['created'])) : null,
      true
    );
}

function importJobs(array $jobs, AdzunaJobOfferRepository $repository): array
{
    $saved = 0;
    $errors = [];

    foreach ($jobs as $index => $job) {
        if (!is_array($job)) {
            continue;
        }

        try {
            $repository->save(mapAdzunaJobToDto($job));
            $saved++;
        } catch (Throwable $throwable) {
            $errors[] = 'Offre #' . ($index + 1) . ' : ' . $throwable->getMessage();
        }
    }

    return ['saved' => $saved, 'errors' => $errors];
}

function salaryLabel(?float $min, ?float $max): string
{
    if ($min === null && $max === null) {
        return 'Salaire non renseigné';
    }

    if ($min !== null && $max !== null) {
        return number_format($min, 0, ',', ' ') . ' - ' . number_format($max, 0, ',', ' ') . ' €';
    }

    if ($min !== null) {
        return 'À partir de ' . number_format($min, 0, ',', ' ') . ' €';
    }

    return 'Jusqu à ' . number_format((float) $max, 0, ',', ' ') . ' €';
}

function excerpt(string $text, int $length = 220): string
{
    $plain = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

    if (function_exists('mb_strlen') && mb_strlen($plain) > $length) {
        return mb_substr($plain, 0, $length - 1) . '…';
    }

    if (strlen($plain) > $length) {
        return substr($plain, 0, $length - 1) . '…';
    }

    return $plain;
}

function humanDate(?string $value): string
{
    if (!$value) {
        return 'Date inconnue';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    $days = (int) floor((time() - $timestamp) / 86400);

    if ($days <= 0) {
        return 'Aujourd hui';
    }

    if ($days === 1) {
        return 'Hier';
    }

    if ($days < 7) {
        return 'Il y a ' . $days . ' jours';
    }

    if ($days < 30) {
        return 'Il y a ' . (int) floor($days / 7) . ' semaines';
    }

    return 'Il y a ' . (int) floor($days / 30) . ' mois';
}

function badgeClass(array $job): string
{
    $contract = strtolower((string) ($job['contract_type'] ?? ''));

    if (strpos($contract, 'permanent') !== false) {
        return 'badge-green';
    }

    if (strpos($contract, 'contract') !== false) {
        return 'badge-blue';
    }

    return 'badge-amber';
}

function hiddenField(string $name, $value): string
{
    return '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
}

function renderJobCard(array $job, int $index, array $criteria): string
{
    $dto = mapAdzunaJobToDto($job);
    $title = e($dto->title);
    $company = e($dto->companyName ?? 'Entreprise non précisée');
    $location = e($dto->locationName ?? 'Localisation inconnue');
    $description = e(excerpt((string) $dto->description));
    $salary = e(salaryLabel($dto->salaryMin, $dto->salaryMax));
    $published = e(humanDate($dto->sourceCreatedAt));
    $redirect = e($dto->redirectUrl);
    $raw = e(json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $tag = e($dto->categoryLabel ?? 'Catégorie inconnue');
    $contract = e($dto->contractType ?? 'Contrat non précisé');
    $badge = e(badgeClass($job));

    $hiddenFields = '';
    foreach (['url', 'country', 'page', 'results_per_page', 'what', 'where', 'what_and', 'what_phrase', 'what_or', 'what_exclude', 'title_only', 'distance', 'location0', 'location1', 'location2', 'location3', 'location4', 'location5', 'location6', 'location7', 'category', 'sort_dir', 'sort_by', 'salary_min', 'salary_max', 'salary_include_unknown', 'full_time', 'part_time', 'contract', 'permanent', 'company'] as $field) {
      $hiddenFields .= hiddenField($field, isset($criteria[$field]) ? $criteria[$field] : '');
    }

    return <<<HTML
<article class="job-card" data-job-card data-job-index="{$index}">
  <div class="job-top">
    <div>
      <p class="job-kicker">{$company}</p>
      <h3>{$title}</h3>
      <p class="job-location"><i class="bi bi-geo-alt"></i> {$location}</p>
    </div>
    <span class="badge {$badge}">{$contract}</span>
  </div>
  <div class="job-tags">
    <span class="chip">{$tag}</span>
    <span class="chip">{$salary}</span>
    <span class="chip">{$published}</span>
  </div>
  <p class="job-description">{$description}</p>
  <div class="job-actions">
    <a class="btn-link-soft" href="{$redirect}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i> Ouvrir l offre</a>
    <form method="post" class="d-inline m-0">
      <input type="hidden" name="action" value="import_one" />
      <input type="hidden" name="job_index" value="{$index}" />
      {$hiddenFields}
      <button type="submit" class="btn-link-strong"><i class="bi bi-cloud-download"></i> Importer</button>
    </form>
  </div>
  <details class="raw-job">
    <summary>Voir le JSON</summary>
    <pre>{$raw}</pre>
  </details>
</article>
HTML;
}

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$config = loadConfig(__DIR__ . '/config.json');
$defaults = criteriaDefaults($config[0]['champs'][0] ?? []);
$criteria = requestCriteria($defaults);

$flashError = '';
$flashSuccess = '';
$apiData = [];
$apiUrl = '';
$jobs = [];
$importReport = null;

try {
    $appId = loadEnvValue('APP_ID');
    $appKey = loadEnvValue('API_KEY');
    $apiUrl = buildAdzunaUrl($criteria, $appId, $appKey);
    $apiData = callApi($apiUrl);
    $jobs = isset($apiData['results']) && is_array($apiData['results']) ? $apiData['results'] : [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $repository = new AdzunaJobOfferRepository();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'import_one') {
            $index = max(0, (int) ($_POST['job_index'] ?? -1));
            if (!isset($jobs[$index]) || !is_array($jobs[$index])) {
                throw new RuntimeException('L offre sélectionnée est introuvable dans la page courante.');
            }

            $repository->save(mapAdzunaJobToDto($jobs[$index]));
            $flashSuccess = 'Offre importée avec succès.';
        }

        if ($action === 'import_all') {
            $importReport = importJobs($jobs, $repository);
            $flashSuccess = $importReport['saved'] . ' offre(s) importée(s) ou mise(s) à jour.';
        }
    }
} catch (Throwable $throwable) {
    $flashError = $throwable->getMessage();
}

$total = (int) ($apiData['count'] ?? count($jobs));
$meanSalary = $apiData['mean'] ?? null;
$pageCount = max(1, (int) ceil(max(1, $total) / max(1, (int) $criteria['results_per_page'])));
$activeChips = [];

foreach (['what' => 'What', 'where' => 'Where', 'category' => 'Catégorie', 'company' => 'Entreprise'] as $key => $label) {
    if (!empty($criteria[$key])) {
        $activeChips[] = $label . ': ' . $criteria[$key];
    }
}

$importFields = '';
foreach (['url', 'country', 'page', 'results_per_page', 'what', 'where', 'what_and', 'what_phrase', 'what_or', 'what_exclude', 'title_only', 'distance', 'location0', 'location1', 'location2', 'location3', 'location4', 'location5', 'location6', 'location7', 'category', 'sort_dir', 'sort_by', 'salary_min', 'salary_max', 'salary_include_unknown', 'full_time', 'part_time', 'contract', 'permanent', 'company'] as $field) {
    $importFields .= hiddenField($field, $criteria[$field] ?? '');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Console API Adzuna — SearchForAJob</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/css/api-dashboard.css" />
</head>
<body class="api-page">
<div class="api-shell">
  <nav class="api-topbar">
    <a class="brand" href="../public/index.php"><i class="bi bi-briefcase-fill"></i> Search<span>ForAJob</span></a>
    <div class="api-topbar-actions">
      <a href="../public/index.php" class="api-btn api-btn-ghost">Retour au site</a>
      <a href="#results" class="api-btn api-btn-primary">Voir les résultats</a>
    </div>
  </nav>

  <header class="api-hero card-surface">
    <div class="api-hero-grid">
      <div>
        <div class="eyebrow">Console Adzuna complète</div>
        <h1>Rechercher, filtrer, prévisualiser et importer des offres depuis l API.</h1>
        <p>Cette page sert de cockpit pour piloter l API Adzuna: recherche avancée, lecture de la réponse brute, import unitaire, import global et préparation des données en base MySQL.</p>
        <div class="chip-row">
          <span class="chip">Recherche avancée</span>
          <span class="chip">Import unitaire</span>
          <span class="chip">Import global</span>
          <span class="chip">JSON brut</span>
        </div>
      </div>
      <div class="stats-grid stats-grid-3">
        <div class="stat-box"><span>Résultats</span><strong><?= e($total) ?></strong></div>
        <div class="stat-box"><span>Page</span><strong><?= e($criteria['page']) ?> / <?= e($pageCount) ?></strong></div>
        <div class="stat-box"><span>Moyenne</span><strong><?= e($meanSalary !== null ? number_format((float) $meanSalary, 0, ',', ' ') . ' €' : 'N/D') ?></strong></div>
      </div>
    </div>
  </header>

  <div class="api-toolbar">
    <button type="button" id="copy-url-btn" class="api-btn api-btn-ghost"><i class="bi bi-clipboard"></i> Copier l URL</button>
    <form method="post" class="m-0">
      <input type="hidden" name="action" value="import_all" />
      <?= $importFields ?>
      <button type="submit" class="api-btn api-btn-primary"><i class="bi bi-lightning-charge"></i> Importer toute la page</button>
    </form>
  </div>

  <?php if ($flashError !== ''): ?>
    <div class="alert alert-danger api-alert"><?= e($flashError) ?></div>
  <?php endif; ?>

  <?php if ($flashSuccess !== ''): ?>
    <div class="alert alert-success api-alert"><?= e($flashSuccess) ?></div>
  <?php endif; ?>

  <main class="api-grid">
    <aside class="card-surface api-form">
      <h2>Recherche avancée</h2>
      <form method="get" action="api.php">
        <input type="hidden" name="page" value="1" />

        <div class="mb-3">
          <label for="what" class="form-label">Mot-clé principal</label>
          <input type="text" class="form-control" id="what" name="what" value="<?= e($criteria['what']) ?>" placeholder="developer, php, react..." />
        </div>

        <div class="mb-3">
          <label for="where" class="form-label">Localisation</label>
          <input type="text" class="form-control" id="where" name="where" value="<?= e($criteria['where']) ?>" placeholder="Paris, Lyon, Remote..." />
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label for="country" class="form-label">Pays</label>
            <input type="text" class="form-control" id="country" name="country" value="<?= e($criteria['country']) ?>" />
          </div>
          <div class="col-6">
            <label for="page" class="form-label">Page</label>
            <input type="number" class="form-control" id="page" name="page" min="1" value="<?= e($criteria['page']) ?>" />
          </div>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label for="results_per_page" class="form-label">Résultats / page</label>
            <input type="number" class="form-control" id="results_per_page" name="results_per_page" min="1" max="50" value="<?= e($criteria['results_per_page']) ?>" />
          </div>
          <div class="col-6">
            <label for="sort_by" class="form-label">Tri</label>
            <select class="form-select" id="sort_by" name="sort_by">
              <?php foreach (['' => 'Auto', 'relevance' => 'Pertinence', 'date' => 'Date', 'salary' => 'Salaire'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $criteria['sort_by'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <fieldset>
          <legend>Recherche sémantique</legend>
          <div class="mb-2"><label class="form-label" for="what_phrase">Expression exacte</label><input type="text" class="form-control" id="what_phrase" name="what_phrase" value="<?= e($criteria['what_phrase']) ?>" /></div>
          <div class="mb-2"><label class="form-label" for="what_and">Tous les mots</label><input type="text" class="form-control" id="what_and" name="what_and" value="<?= e($criteria['what_and']) ?>" /></div>
          <div class="mb-2"><label class="form-label" for="what_or">Au moins un mot</label><input type="text" class="form-control" id="what_or" name="what_or" value="<?= e($criteria['what_or']) ?>" /></div>
          <div><label class="form-label" for="what_exclude">Mots exclus</label><input type="text" class="form-control" id="what_exclude" name="what_exclude" value="<?= e($criteria['what_exclude']) ?>" /></div>
        </fieldset>

        <fieldset>
          <legend>Filtres emploi</legend>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label" for="salary_min">Salaire min</label><input type="number" class="form-control" id="salary_min" name="salary_min" value="<?= e($criteria['salary_min']) ?>" /></div>
            <div class="col-6"><label class="form-label" for="salary_max">Salaire max</label><input type="number" class="form-control" id="salary_max" name="salary_max" value="<?= e($criteria['salary_max']) ?>" /></div>
          </div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="full_time" value="1" id="full_time" <?= !empty($criteria['full_time']) ? 'checked' : '' ?> /><label class="form-check-label" for="full_time">Temps plein</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="part_time" value="1" id="part_time" <?= !empty($criteria['part_time']) ? 'checked' : '' ?> /><label class="form-check-label" for="part_time">Temps partiel</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="contract" value="1" id="contract" <?= !empty($criteria['contract']) ? 'checked' : '' ?> /><label class="form-check-label" for="contract">Contrat</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="permanent" value="1" id="permanent" <?= !empty($criteria['permanent']) ? 'checked' : '' ?> /><label class="form-check-label" for="permanent">Permanent</label></div>
          <div class="form-check"><input class="form-check-input" type="checkbox" name="salary_include_unknown" value="1" id="salary_include_unknown" <?= !empty($criteria['salary_include_unknown']) ? 'checked' : '' ?> /><label class="form-check-label" for="salary_include_unknown">Inclure salaires inconnus</label></div>
        </fieldset>

        <fieldset>
          <legend>Paramètres complémentaires</legend>
          <div class="mb-2"><label class="form-label" for="category">Catégorie</label><input type="text" class="form-control" id="category" name="category" value="<?= e($criteria['category']) ?>" /></div>
          <div class="mb-2"><label class="form-label" for="distance">Distance</label><input type="number" class="form-control" id="distance" name="distance" value="<?= e($criteria['distance']) ?>" /></div>
          <div class="mb-2"><label class="form-label" for="company">Entreprise</label><input type="text" class="form-control" id="company" name="company" value="<?= e($criteria['company']) ?>" /></div>
        </fieldset>

        <button type="submit" class="api-btn api-btn-primary w-100 justify-content-center">Lancer la recherche</button>
      </form>
    </aside>

    <section id="results" class="api-results">
      <div class="card-surface p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
          <div>
            <h2 class="mb-2">Résultats</h2>
            <div class="api-chip-row">
              <span class="chip">URL prête</span>
              <span class="chip"><?= e((string) count($jobs)) ?> résultats chargés</span>
              <span class="chip">API JSON</span>
            </div>
          </div>
          <div class="api-chip-row">
            <?php foreach ($activeChips as $chip): ?>
              <span class="chip"><?= e($chip) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="api-url-box mt-3"><code id="api-url-text"><?= e($apiUrl) ?></code></div>
      </div>

      <div class="card-surface p-3 mb-3">
        <h2>Résumé</h2>
        <div class="stats-grid stats-grid-4 mt-3">
          <div class="stat-box"><span>Total API</span><strong><?= e(number_format((int) ($apiData['count'] ?? $total), 0, ',', ' ')) ?></strong></div>
          <div class="stat-box"><span>Page</span><strong><?= e($criteria['page']) ?></strong></div>
          <div class="stat-box"><span>Par page</span><strong><?= e($criteria['results_per_page']) ?></strong></div>
          <div class="stat-box"><span>Moyenne</span><strong><?= e($meanSalary !== null ? number_format((float) $meanSalary, 0, ',', ' ') . ' €' : 'N/D') ?></strong></div>
        </div>
      </div>

      <?php if (empty($jobs)): ?>
        <div class="card-surface p-4 text-center">
          <i class="bi bi-inbox fs-1 d-block mb-2" style="color:var(--api-accent);"></i>
          <h2>Aucun résultat</h2>
          <p class="mb-0 text-secondary">Aucune offre ne correspond à la requête courante.</p>
        </div>
      <?php else: ?>
        <div class="results-list">
          <?php foreach ($jobs as $index => $job): ?>
            <?= renderJobCard(is_array($job) ? $job : [], (int) $index, $criteria) ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($pageCount > 1): ?>
        <div class="card-surface p-3 mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="text-secondary">Pagination</div>
          <div class="d-flex gap-2 flex-wrap">
            <?php for ($i = max(1, (int) $criteria['page'] - 2); $i <= min($pageCount, (int) $criteria['page'] + 2); $i++): ?>
              <a class="api-btn <?= $i === (int) $criteria['page'] ? 'api-btn-primary' : 'api-btn-ghost' ?>" href="?<?= e(http_build_query(array_merge($criteria, ['page' => $i]))) ?>"><?= e($i) ?></a>
            <?php endfor; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="card-surface p-3 mt-3">
        <h2>Réponse brute</h2>
        <details>
          <summary class="text-primary fw-semibold">Afficher le JSON complet</summary>
          <pre class="api-json-box mt-3"><?= e(json_encode($apiData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        </details>
      </div>
    </section>
  </main>
</div>

<script>
  const copyButton = document.getElementById('copy-url-btn');
  const apiUrlText = document.getElementById('api-url-text');

  copyButton?.addEventListener('click', async () => {
    const url = apiUrlText?.textContent?.trim();
    if (!url) return;

    try {
      await navigator.clipboard.writeText(url);
      copyButton.innerHTML = '<i class="bi bi-check2"></i> URL copiée';
      setTimeout(() => {
        copyButton.innerHTML = '<i class="bi bi-clipboard"></i> Copier l URL';
      }, 1800);
    } catch (error) {
      alert('Impossible de copier l URL.');
    }
  });
</script>
</body>
</html>