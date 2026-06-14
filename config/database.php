<?php
// ============================================================
//  config/database.php
//  Connexion PDO — XAMPP (MySQL / MariaDB)
// ============================================================

declare(strict_types=1);

// --- Paramètres de connexion --------------------------------
define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'searchforajob');
define('DB_USER',    'root');       // utilisateur par défaut XAMPP
define('DB_PASS',    'root');           // mot de passe vide par défaut XAMPP
define('DB_CHARSET', 'utf8mb4');
// ------------------------------------------------------------

/**
 * Retourne une instance PDO unique (singleton).
 * Le pattern singleton évite d'ouvrir plusieurs connexions
 * à la base sur une même requête HTTP.
 *
 * @throws PDOException si la connexion échoue
 */
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
            // Lève une exception PDOException sur toute erreur SQL
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

            // Retourne les résultats sous forme de tableaux associatifs
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Désactive l'émulation des requêtes préparées :
            // les vraies requêtes préparées côté serveur sont plus sûres
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } 
        catch (PDOException $e) {
        die('Erreur de connexion à la base de données.');
        }
    }

    return $pdo;
}