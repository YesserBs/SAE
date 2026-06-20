<?php

declare(strict_types=1);

require_once __DIR__ . '/AdzunaClient.php';

/*
Page de test / debug pour vérifier que l'intégration Adzuna fonctionne.
La page utilisée réellement par le site est public/offres-partenaires.php ;
celle-ci reste utile pour déboguer rapidement les paramètres de recherche.
*/

function afficherErreur(string $message): void
{
    echo "<h2>Erreur</h2>";
    echo "<pre>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</pre>";
}

function afficherResultat(mixed $data): void
{
    echo "<h2>Résultat de l'API</h2>";
    echo "<pre>";
    echo htmlspecialchars(
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        ENT_QUOTES,
        'UTF-8'
    );
    echo "</pre>";
}

echo "<!DOCTYPE html>";
echo "<html lang='fr'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Test API Adzuna</title>";
echo "</head>";
echo "<body>";

try {
    // 1. Charger les paramètres de recherche depuis config.json
    $jsonPath = __DIR__ . '/config.json';

    if (!file_exists($jsonPath)) {
        throw new Exception("Le fichier config.json est introuvable.");
    }

    $jsonContent = file_get_contents($jsonPath);
    if ($jsonContent === false) {
        throw new Exception("Impossible de lire le fichier config.json.");
    }

    $config = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erreur dans le JSON : " . json_last_error_msg());
    }

    $champs = $config[0]['champs'][0] ?? null;
    if ($champs === null) {
        throw new Exception("Aucune configuration API trouvée dans config.json.");
    }

    // 2. Appeler l'API via le client partagé (clés chargées en dehors du code source)
    $client = new AdzunaClient();
    $data   = $client->rechercherOffres(
        $champs['what']  ?? '',
        $champs['where'] ?? '',
        (int) ($champs['page'] ?? 1),
        (int) ($champs['results_per_page'] ?? 20)
    );

    // 3. Afficher le résultat
    afficherResultat($data);

} catch (Exception $e) {
    afficherErreur($e->getMessage());
}

echo "</body>";
echo "</html>";
