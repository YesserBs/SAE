<?php

declare(strict_types=1);

/*
L'objectif est de charger les données de l'API et les afficher simplement
dans une page HTML.

NOTE :
Pour aller vite dans ce projet, on met APP_ID et APP_KEY directement en dur
dans le code. On ignore volontairement l'aspect sécurité / confidentialité
des clés API pour cette version rapide.
Dans un vrai projet public ou professionnel, il faudrait utiliser un fichier .env
non versionné sur GitHub.
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
    // Clés API mises en dur pour aller vite dans ce projet.
    $appId = '9b8adf41';
    $appKey = 'd2cf6c9c63a7b533a4d0198e00f72274';

    // 1. Charger le fichier JSON
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

    // 2. Récupérer la première configuration
    $champs = $config[0]['champs'][0] ?? null;

    if ($champs === null) {
        throw new Exception("Aucune configuration API trouvée dans config.json.");
    }

    // 3. Construire l'URL de base
    $url = $champs['url'];

    $url = str_replace('{country}', $champs['country'], $url);
    $url = str_replace('{page}', (string) $champs['page'], $url);

    // 4. Préparer les paramètres GET
    $params = [
        'app_id' => $appId,
        'app_key' => $appKey,
        'results_per_page' => $champs['results_per_page'] ?? 20,
    ];

    // Ajouter uniquement les champs non vides du fichier JSON
    foreach ($champs as $key => $value) {
        if (
            !in_array($key, ['url', 'country', 'page', 'results_per_page'], true)
            && $value !== ""
            && $value !== null
        ) {
            $params[$key] = $value;
        }
    }

    $finalUrl = $url . '?' . http_build_query($params);

    // Affichage de l'URL appelée, pratique pour vérifier
    echo "<h2>URL appelée</h2>";
    echo "<pre>" . htmlspecialchars($finalUrl, ENT_QUOTES, 'UTF-8') . "</pre>";

    // 5. Appeler l'API avec cURL
    $curl = curl_init();

    if ($curl === false) {
        throw new Exception("Impossible d'initialiser cURL.");
    }

    curl_setopt_array($curl, [
        CURLOPT_URL => $finalUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,

        // Pour aller vite en local, on désactive la vérification SSL.
        // On ignore volontairement l'aspect sécurité SSL pour cette version rapide.
        // Dans un vrai projet, il faudrait configurer correctement le certificat CA.
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

    // 6. Décoder la réponse JSON
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("La réponse de l'API n'est pas un JSON valide : " . json_last_error_msg());
    }

    // 7. Afficher le résultat
    afficherResultat($data);

} catch (Exception $e) {
    afficherErreur($e->getMessage());
}

echo "</body>";
echo "</html>";