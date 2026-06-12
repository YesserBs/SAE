<?php

declare(strict_types=1);

/*
L'objectif est de charger les données de l'API Adzuna,
de les transformer en DTO, puis de les enregistrer dans la table adzuna_job_offer.

NOTE :
Pour aller vite dans ce projet, on met APP_ID et APP_KEY directement en dur
dans le code. On ignore volontairement l'aspect sécurité / confidentialité
des clés API pour cette version rapide.

On met aussi la connexion PDO directement dans ce fichier pour éviter les problèmes
de chemins avec require_once. Plus tard, on pourra remettre ça proprement dans
un fichier database.php ou un repository.
*/

// ============================================================
//  Connexion PDO — WAMP / XAMPP / MySQL / MariaDB
// ============================================================

define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'searchforajob');
define('DB_USER',    'root');
define('DB_PASS',    'admin');
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}

// ============================================================
//  DTO
// ============================================================

class AdzunaJobOfferDto
{
    public function __construct(
        public string $externalId,
        public string $source,

        public string $title,
        public string $description,
        public string $redirectUrl,

        public ?string $companyName,

        public ?string $locationName,
        public ?string $country,
        public ?string $region,
        public ?string $city,
        public ?float $latitude,
        public ?float $longitude,

        public ?string $categoryLabel,
        public ?string $categoryTag,

        public ?string $contractType,
        public ?string $contractTime,

        public ?float $salaryMin,
        public ?float $salaryMax,
        public bool $salaryIsPredicted,

        public ?string $sourceCreatedAt,
        public bool $isActive = true
    ) {}
}

// ============================================================
//  Fonctions HTML
// ============================================================

function afficherErreur(string $message): void
{
    echo "<h2>Erreur</h2>";
    echo "<pre>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</pre>";
}

function afficherMessage(string $message): void
{
    echo "<p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
}

// ============================================================
//  Mapping API Adzuna -> DTO
// ============================================================

function mapAdzunaJobToDto(array $job): AdzunaJobOfferDto
{
    $area = $job['location']['area'] ?? [];

    return new AdzunaJobOfferDto(
        externalId: (string) ($job['id'] ?? ''),
        source: 'adzuna',

        title: trim((string) ($job['title'] ?? 'Offre sans titre')),
        description: trim((string) ($job['description'] ?? 'Description non disponible')),
        redirectUrl: (string) ($job['redirect_url'] ?? ''),

        companyName: $job['company']['display_name'] ?? null,

        locationName: $job['location']['display_name'] ?? null,
        country: $area[0] ?? null,
        region: $area[1] ?? null,
        city: $area[2] ?? null,

        latitude: isset($job['latitude']) ? (float) $job['latitude'] : null,
        longitude: isset($job['longitude']) ? (float) $job['longitude'] : null,

        categoryLabel: $job['category']['label'] ?? null,
        categoryTag: $job['category']['tag'] ?? null,

        contractType: $job['contract_type'] ?? null,
        contractTime: $job['contract_time'] ?? null,

        salaryMin: isset($job['salary_min']) ? (float) $job['salary_min'] : null,
        salaryMax: isset($job['salary_max']) ? (float) $job['salary_max'] : null,

        salaryIsPredicted: isset($job['salary_is_predicted'])
            && (string) $job['salary_is_predicted'] === '1',

        sourceCreatedAt: isset($job['created'])
            ? date('Y-m-d H:i:s', strtotime($job['created']))
            : null,

        isActive: true
    );
}

// ============================================================
//  Sauvegarde DTO -> MySQL
// ============================================================

function saveAdzunaJobOffer(PDO $pdo, AdzunaJobOfferDto $dto): void
{
    if ($dto->externalId === '') {
        throw new Exception("Impossible d'enregistrer une offre sans external_id.");
    }

    if ($dto->redirectUrl === '') {
        throw new Exception("Impossible d'enregistrer une offre sans redirect_url.");
    }

    $sql = "
        INSERT INTO adzuna_job_offer (
            external_id,
            source,
            title,
            description,
            redirect_url,
            company_name,
            location_name,
            country,
            region,
            city,
            latitude,
            longitude,
            category_label,
            category_tag,
            contract_type,
            contract_time,
            salary_min,
            salary_max,
            salary_is_predicted,
            source_created_at,
            is_active
        ) VALUES (
            :external_id,
            :source,
            :title,
            :description,
            :redirect_url,
            :company_name,
            :location_name,
            :country,
            :region,
            :city,
            :latitude,
            :longitude,
            :category_label,
            :category_tag,
            :contract_type,
            :contract_time,
            :salary_min,
            :salary_max,
            :salary_is_predicted,
            :source_created_at,
            :is_active
        )
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            description = VALUES(description),
            redirect_url = VALUES(redirect_url),
            company_name = VALUES(company_name),
            location_name = VALUES(location_name),
            country = VALUES(country),
            region = VALUES(region),
            city = VALUES(city),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            category_label = VALUES(category_label),
            category_tag = VALUES(category_tag),
            contract_type = VALUES(contract_type),
            contract_time = VALUES(contract_time),
            salary_min = VALUES(salary_min),
            salary_max = VALUES(salary_max),
            salary_is_predicted = VALUES(salary_is_predicted),
            source_created_at = VALUES(source_created_at),
            is_active = VALUES(is_active)
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'external_id' => $dto->externalId,
        'source' => $dto->source,

        'title' => $dto->title,
        'description' => $dto->description,
        'redirect_url' => $dto->redirectUrl,

        'company_name' => $dto->companyName,

        'location_name' => $dto->locationName,
        'country' => $dto->country,
        'region' => $dto->region,
        'city' => $dto->city,
        'latitude' => $dto->latitude,
        'longitude' => $dto->longitude,

        'category_label' => $dto->categoryLabel,
        'category_tag' => $dto->categoryTag,

        'contract_type' => $dto->contractType,
        'contract_time' => $dto->contractTime,

        'salary_min' => $dto->salaryMin,
        'salary_max' => $dto->salaryMax,
        'salary_is_predicted' => $dto->salaryIsPredicted ? 1 : 0,

        'source_created_at' => $dto->sourceCreatedAt,
        'is_active' => $dto->isActive ? 1 : 0,
    ]);
}

// ============================================================
//  Config JSON
// ============================================================

function loadConfig(string $path): array
{
    if (!file_exists($path)) {
        throw new Exception("Le fichier config.json est introuvable.");
    }

    $jsonContent = file_get_contents($path);

    if ($jsonContent === false) {
        throw new Exception("Impossible de lire le fichier config.json.");
    }

    $config = json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erreur dans le JSON : " . json_last_error_msg());
    }

    return $config;
}

function buildAdzunaUrl(array $champs, string $appId, string $appKey): string
{
    if (empty($champs['url'])) {
        throw new Exception("Le champ url est manquant dans config.json.");
    }

    if (empty($champs['country'])) {
        throw new Exception("Le champ country est manquant dans config.json.");
    }

    if (empty($champs['page'])) {
        throw new Exception("Le champ page est manquant dans config.json.");
    }

    $url = $champs['url'];

    $url = str_replace('{country}', (string) $champs['country'], $url);
    $url = str_replace('{page}', (string) $champs['page'], $url);

    $params = [
        'app_id' => $appId,
        'app_key' => $appKey,
        'results_per_page' => $champs['results_per_page'] ?? 20,
    ];

    foreach ($champs as $key => $value) {
        if (
            !in_array($key, ['url', 'country', 'page', 'results_per_page'], true)
            && $value !== ''
            && $value !== null
        ) {
            $params[$key] = $value;
        }
    }

    return $url . '?' . http_build_query($params);
}

// ============================================================
//  Appel API
// ============================================================

function callApi(string $url): array
{
    $curl = curl_init();

    if ($curl === false) {
        throw new Exception("Impossible d'initialiser cURL.");
    }

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,

        // Pour aller vite en local, on désactive la vérification SSL.
        // On ignore volontairement l'aspect sécurité SSL pour cette version rapide.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($curl);

    if ($response === false) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new Exception("Erreur cURL : " . $error);
    }

    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Erreur HTTP $httpCode : " . $response);
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("La réponse de l'API n'est pas un JSON valide : " . json_last_error_msg());
    }

    return $data;
}

// ============================================================
//  Exécution principale
// ============================================================

echo "<!DOCTYPE html>";
echo "<html lang='fr'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Import API Adzuna</title>";
echo "</head>";
echo "<body>";

try {
    $pdo = getPDO();

    // Clés API mises en dur pour aller vite dans ce projet.
    $appId = '9b8adf41';
    $appKey = 'd2cf6c9c63a7b533a4d0198e00f72274';

    $config = loadConfig(__DIR__ . '/config.json');

    $champs = $config[0]['champs'][0] ?? null;

    if ($champs === null) {
        throw new Exception("Aucune configuration API trouvée dans config.json.");
    }

    $finalUrl = buildAdzunaUrl($champs, $appId, $appKey);

    echo "<h2>URL appelée</h2>";
    echo "<pre>" . htmlspecialchars($finalUrl, ENT_QUOTES, 'UTF-8') . "</pre>";

    $data = callApi($finalUrl);

    if (!isset($data['results']) || !is_array($data['results'])) {
        throw new Exception("La réponse API ne contient pas de tableau results.");
    }

    $savedCount = 0;
    $errors = [];

    foreach ($data['results'] as $index => $job) {
        try {
            $dto = mapAdzunaJobToDto($job);
            saveAdzunaJobOffer($pdo, $dto);
            $savedCount++;
        } catch (Exception $e) {
            $errors[] = "Offre index $index : " . $e->getMessage();
        }
    }

    echo "<h2>Import terminé</h2>";
    afficherMessage($savedCount . " offre(s) enregistrée(s) ou mise(s) à jour en base.");

    if (!empty($errors)) {
        echo "<h2>Erreurs pendant l'import</h2>";
        echo "<pre>" . htmlspecialchars(implode("\n", $errors), ENT_QUOTES, 'UTF-8') . "</pre>";
    }

    echo "<h2>Résumé API</h2>";
    echo "<pre>";
    echo htmlspecialchars(
        json_encode([
            'count_total_api' => $data['count'] ?? null,
            'mean_salary_api' => $data['mean'] ?? null,
            'results_received' => count($data['results']),
            'saved_or_updated' => $savedCount,
            'errors_count' => count($errors),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ENT_QUOTES,
        'UTF-8'
    );
    echo "</pre>";

} catch (Exception $e) {
    afficherErreur($e->getMessage());
}

echo "</body>";
echo "</html>";