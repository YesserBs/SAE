<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class CandidatureModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPDO();
    }

    // --------------------------------------------------------
    //  POSTULER à une offre
    // --------------------------------------------------------
    public function postuler(int $candidatId, int $offreId, string $lettre = '', ?string $cvPath = null): bool
    {
        // Vérifie qu'il n'a pas déjà postulé
        if ($this->aDejaPostule($candidatId, $offreId)) {
            throw new RuntimeException('Vous avez déjà postulé à cette offre.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO candidature (candidat_id, offre_id, lettre_motivation, cv_path)
            VALUES (:cid, :oid, :lettre, :cv_path)
        ");
        $stmt->execute([
            ':cid'     => $candidatId,
            ':oid'     => $offreId,
            ':lettre'  => $lettre,
            ':cv_path' => $cvPath,
        ]);
        return true;
    }

    // --------------------------------------------------------
    //  CANDIDATURES d'un candidat (dashboard candidat)
    // --------------------------------------------------------
    public function getCandidaturesCandidat(int $candidatId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.offre_id, c.statut, c.date_candidature,
                   o.titre, o.type_contrat, o.localisation,
                   e.nom AS entreprise_nom
            FROM candidature c
            INNER JOIN offre o       ON o.id = c.offre_id
            INNER JOIN entreprise e  ON e.id = o.entreprise_id
            WHERE c.candidat_id = :cid
            ORDER BY c.date_candidature DESC
        ");
        $stmt->execute([':cid' => $candidatId]);
        return $stmt->fetchAll();
    }

    // --------------------------------------------------------
    //  CANDIDATURES reçues sur une offre (dashboard recruteur)
    // --------------------------------------------------------
    public function getCandidaturesOffre(int $offreId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT c.candidat_id, c.statut, c.date_candidature, c.lettre_motivation,
                   c.cv_path,
                   p.nom, p.prenom, p.telephone,
                   u.email
            FROM candidature c
            INNER JOIN utilisateur u ON u.id = c.candidat_id
            LEFT JOIN profil p       ON p.utilisateur_id = u.id
            WHERE c.offre_id = :oid
            ORDER BY c.date_candidature DESC
        ");
        $stmt->execute([':oid' => $offreId]);
        return $stmt->fetchAll();
    }

    // --------------------------------------------------------
    //  CHANGER le statut d'une candidature (recruteur)
    // --------------------------------------------------------
    public function changerStatut(int $candidatId, int $offreId, string $statut): bool
    {
        $allowed = ['En attente', 'Vue', 'Acceptee', 'Refusee'];
        if (!in_array($statut, $allowed, true)) {
            throw new InvalidArgumentException('Statut invalide.');
        }

        $stmt = $this->pdo->prepare("
            UPDATE candidature SET statut = :statut
            WHERE candidat_id = :cid AND offre_id = :oid
        ");
        $stmt->execute([
            ':statut' => $statut,
            ':cid'    => $candidatId,
            ':oid'    => $offreId,
        ]);
        return $stmt->rowCount() > 0;
    }

    // --------------------------------------------------------
    //  HELPER
    // --------------------------------------------------------
    public function aDejaPostule(int $candidatId, int $offreId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM candidature WHERE candidat_id = :cid AND offre_id = :oid
        ");
        $stmt->execute([':cid' => $candidatId, ':oid' => $offreId]);
        return (bool) $stmt->fetch();
    }
}