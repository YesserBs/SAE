<?php
declare(strict_types=1);

// ============================================================
//  Client pour l'API Adzuna (offres d'emploi externes/partenaires).
//
//  Les clés d'API ne sont JAMAIS écrites en dur ici. Elles sont
//  chargées, dans l'ordre de priorité :
//    1. Variables d'environnement ADZUNA_APP_ID / ADZUNA_APP_KEY
//    2. Fichier backend/secrets.local.php (non versionné, voir .gitignore)
//
//  Voir backend/secrets.example.php pour le format attendu.
// ============================================================

class AdzunaClient
{
    private string $appId;
    private string $appKey;

    public function __construct()
    {
        [$appId, $appKey] = self::chargerClefs();
        $this->appId  = $appId;
        $this->appKey = $appKey;
    }

    public static function chargerClefs(): array
    {
        $appId  = getenv('ADZUNA_APP_ID')  ?: null;
        $appKey = getenv('ADZUNA_APP_KEY') ?: null;

        $secretsFile = __DIR__ . '/secrets.local.php';
        if ((!$appId || !$appKey) && file_exists($secretsFile)) {
            $secrets = require $secretsFile;
            $appId   = $appId  ?: ($secrets['ADZUNA_APP_ID']  ?? null);
            $appKey  = $appKey ?: ($secrets['ADZUNA_APP_KEY'] ?? null);
        }

        if (!$appId || !$appKey) {
            throw new RuntimeException(
                "Clés API Adzuna manquantes. Définissez ADZUNA_APP_ID / ADZUNA_APP_KEY " .
                "en variables d'environnement, ou copiez backend/secrets.example.php " .
                "vers backend/secrets.local.php et renseignez vos clés."
            );
        }

        return [$appId, $appKey];
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->appId && $this->appKey);
    }

    /**
     * Recherche des offres sur Adzuna.
     *
     * @param string $what  Mot-clé recherché (ex: "developer")
     * @param string $where Localisation (ex: "Paris")
     * @param int    $page  Numéro de page (commence à 1)
     * @return array Tableau décodé de la réponse JSON Adzuna
     */
    public function rechercherOffres(string $what = '', string $where = '', int $page = 1, int $resultsParPage = 20): array
    {
        $url = "https://api.adzuna.com/v1/api/jobs/fr/search/" . max(1, $page);

        $params = [
            'app_id'            => $this->appId,
            'app_key'           => $this->appKey,
            'results_per_page'  => $resultsParPage,
            'content-type'      => 'application/json',
        ];
        if ($what)  $params['what']  = $what;
        if ($where) $params['where'] = $where;

        $finalUrl = $url . '?' . http_build_query($params);

        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException("Impossible d'initialiser cURL.");
        }

        curl_setopt_array($curl, [
            CURLOPT_URL            => $finalUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException("Erreur cURL : " . $error);
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Erreur HTTP $httpCode lors de l'appel à l'API Adzuna.");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Réponse Adzuna invalide : " . json_last_error_msg());
        }

        return $data;
    }
}
