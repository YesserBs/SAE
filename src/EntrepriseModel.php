<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class EntrepriseModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPDO();
    }

    // --------------------------------------------------------
    //  Récupère l'entreprise associée à un recruteur (1 recruteur
    //  = 1 entreprise dans ce projet). Retourne null si le
    //  recruteur n'a pas encore créé son entreprise.
    // --------------------------------------------------------
    public function getByRecruteur(int $recruteurId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM entreprise WHERE recruteur_id = :rid LIMIT 1");
        $stmt->execute([':rid' => $recruteurId]);
        return $stmt->fetch() ?: null;
    }

    // --------------------------------------------------------
    //  Récupère une entreprise par son id (utile pour les
    //  pages publiques affichant une fiche entreprise).
    // --------------------------------------------------------
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM entreprise WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    // --------------------------------------------------------
    //  Crée l'entreprise d'un recruteur.
    // --------------------------------------------------------
    public function creer(int $recruteurId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO entreprise (recruteur_id, nom, secteur, ville, description)
            VALUES (:rid, :nom, :secteur, :ville, :description)
        ");
        $stmt->execute([
            ':rid'         => $recruteurId,
            ':nom'         => $data['nom'],
            ':secteur'     => $data['secteur']     ?? null,
            ':ville'       => $data['ville']       ?? null,
            ':description' => $data['description'] ?? null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    // --------------------------------------------------------
    //  Met à jour l'entreprise d'un recruteur. La clause
    //  recruteur_id dans le WHERE garantit qu'un recruteur ne
    //  peut modifier que sa propre entreprise.
    // --------------------------------------------------------
    public function mettreAJour(int $entrepriseId, int $recruteurId, array $data): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE entreprise
            SET nom = :nom, secteur = :secteur, ville = :ville, description = :description
            WHERE id = :id AND recruteur_id = :rid
        ");
        $stmt->execute([
            ':nom'         => $data['nom'],
            ':secteur'     => $data['secteur']     ?? null,
            ':ville'       => $data['ville']       ?? null,
            ':description' => $data['description'] ?? null,
            ':id'          => $entrepriseId,
            ':rid'         => $recruteurId,
        ]);
        return $stmt->rowCount() >= 0;
    }

    // --------------------------------------------------------
    //  Met à jour le logo de l'entreprise.
    // --------------------------------------------------------
    public function mettreAJourLogo(int $entrepriseId, int $recruteurId, string $logoPath): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE entreprise SET logo_path = :logo
            WHERE id = :id AND recruteur_id = :rid
        ");
        $stmt->execute([
            ':logo' => $logoPath,
            ':id'   => $entrepriseId,
            ':rid'  => $recruteurId,
        ]);
        return $stmt->rowCount() > 0;
    }
}
