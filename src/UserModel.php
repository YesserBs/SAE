<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class UserModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPDO();
    }

    // --------------------------------------------------------
    //  INSCRIPTION
    // --------------------------------------------------------

    /**
     * Crée un utilisateur + son profil en une transaction.
     * Retourne l'id créé ou lève une exception.
     */
    public function inscrire(array $data): int
    {
        // Vérifie que l'email n'existe pas déjà
        if ($this->emailExiste($data['email'])) {
            throw new RuntimeException('Cet email est déjà utilisé.');
        }

        $this->pdo->beginTransaction();
        try {
            // Insertion utilisateur
            $stmt = $this->pdo->prepare("
                INSERT INTO utilisateur (email, password, role)
                VALUES (:email, :password, :role)
            ");
            $stmt->execute([
                ':email'    => $data['email'],
                ':password' => password_hash($data['password'], PASSWORD_BCRYPT),
                ':role'     => $data['role'],
            ]);
            $userId = (int) $this->pdo->lastInsertId();

            // Insertion profil (1-1)
            $stmt2 = $this->pdo->prepare("
                INSERT INTO profil (utilisateur_id, nom, prenom, telephone)
                VALUES (:uid, :nom, :prenom, :telephone)
            ");
            $stmt2->execute([
                ':uid'       => $userId,
                ':nom'       => $data['nom'],
                ':prenom'    => $data['prenom'],
                ':telephone' => $data['telephone'] ?? null,
            ]);

            $this->pdo->commit();
            return $userId;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // --------------------------------------------------------
    //  CONNEXION
    // --------------------------------------------------------

    /**
     * Vérifie email + password.
     * Retourne le tableau utilisateur ou null si échec.
     */
    public function connecter(string $email, string $password): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.email, u.password, u.role,
                   p.nom, p.prenom
            FROM utilisateur u
            LEFT JOIN profil p ON p.utilisateur_id = u.id
            WHERE u.email = :email
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        // On ne retourne jamais le hash du mot de passe
        unset($user['password']);
        return $user;
    }

    // --------------------------------------------------------
    //  PROFIL
    // --------------------------------------------------------

    /**
     * Retourne le profil complet d'un utilisateur.
     */
    public function getProfil(int $userId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.email, u.role,
                   p.nom, p.prenom, p.telephone, p.cv_path, p.bio
            FROM utilisateur u
            LEFT JOIN profil p ON p.utilisateur_id = u.id
            WHERE u.id = :id
        ");
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Met à jour le profil d'un utilisateur.
     */
    public function mettreAJourProfil(int $userId, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE profil
            SET nom = :nom, prenom = :prenom,
                telephone = :telephone, bio = :bio
            WHERE utilisateur_id = :uid
        ");
        $stmt->execute([
            ':nom'       => $data['nom'],
            ':prenom'    => $data['prenom'],
            ':telephone' => $data['telephone'] ?? null,
            ':bio'       => $data['bio'] ?? null,
            ':uid'       => $userId,
        ]);
    }

    /**
     * Met à jour le chemin du CV.
     */
    public function mettreAJourCV(int $userId, string $cvPath): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE profil SET cv_path = :cv WHERE utilisateur_id = :uid
        ");
        $stmt->execute([':cv' => $cvPath, ':uid' => $userId]);
    }

    // --------------------------------------------------------
    //  HELPERS
    // --------------------------------------------------------

    private function emailExiste(string $email): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM utilisateur WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return (bool) $stmt->fetch();
    }
}