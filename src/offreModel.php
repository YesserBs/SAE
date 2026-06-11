<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class OffreModel
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPDO();
    }

    // --------------------------------------------------------
    //  LISTER avec filtres + pagination
    // --------------------------------------------------------
    public function listerOffres(array $filtres = [], int $page = 1, int $parPage = 10): array
    {
        $sql = "
            SELECT o.id, o.titre, o.localisation, o.type_contrat,
                   o.teletravail, o.experience, o.salaire_min, o.salaire_max,
                   o.date_publication,
                   e.nom AS entreprise_nom, e.ville AS entreprise_ville, e.logo_path
            FROM offre o
            INNER JOIN entreprise e ON e.id = o.entreprise_id
            WHERE o.is_active = 1
        ";
        $params = [];

        if (!empty($filtres['q'])) {
            $sql .= " AND o.titre LIKE :q";
            $params[':q'] = '%' . $filtres['q'] . '%';
        }
        if (!empty($filtres['ville'])) {
            $sql .= " AND o.localisation LIKE :ville";
            $params[':ville'] = '%' . $filtres['ville'] . '%';
        }
        if (!empty($filtres['contrat']) && is_array($filtres['contrat'])) {
            $ph = [];
            foreach ($filtres['contrat'] as $i => $v) {
                $ph[] = ":contrat$i"; $params[":contrat$i"] = $v;
            }
            $sql .= " AND o.type_contrat IN (" . implode(',', $ph) . ")";
        }
        if (!empty($filtres['teletravail']) && is_array($filtres['teletravail'])) {
            $ph = [];
            foreach ($filtres['teletravail'] as $i => $v) {
                $ph[] = ":tel$i"; $params[":tel$i"] = $v;
            }
            $sql .= " AND o.teletravail IN (" . implode(',', $ph) . ")";
        }
        if (!empty($filtres['experience']) && is_array($filtres['experience'])) {
            $ph = [];
            foreach ($filtres['experience'] as $i => $v) {
                $ph[] = ":xp$i"; $params[":xp$i"] = $v;
            }
            $sql .= " AND o.experience IN (" . implode(',', $ph) . ")";
        }
        if (!empty($filtres['secteur'])) {
            $sql .= " AND e.secteur LIKE :secteur";
            $params[':secteur'] = '%' . $filtres['secteur'] . '%';
        }

        $sql .= " ORDER BY o.date_publication DESC";
        $sql .= " LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit',  $parPage,           PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($page - 1) * $parPage, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // --------------------------------------------------------
    //  COMPTER (pour la pagination)
    // --------------------------------------------------------
    public function compterOffres(array $filtres = []): int
    {
        $sql    = "SELECT COUNT(*) FROM offre o INNER JOIN entreprise e ON e.id = o.entreprise_id WHERE o.is_active = 1";
        $params = [];

        if (!empty($filtres['q'])) {
            $sql .= " AND o.titre LIKE :q";
            $params[':q'] = '%' . $filtres['q'] . '%';
        }
        if (!empty($filtres['ville'])) {
            $sql .= " AND o.localisation LIKE :ville";
            $params[':ville'] = '%' . $filtres['ville'] . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    // --------------------------------------------------------
    //  DETAIL d'une offre avec ses competences
    // --------------------------------------------------------
    public function getOffreById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT o.*, e.nom AS entreprise_nom, e.ville AS entreprise_ville,
                   e.secteur AS entreprise_secteur, e.logo_path, e.description AS entreprise_description
            FROM offre o
            INNER JOIN entreprise e ON e.id = o.entreprise_id
            WHERE o.id = :id AND o.is_active = 1
        ");
        $stmt->execute([':id' => $id]);
        $offre = $stmt->fetch();
        if (!$offre) return null;

        $stmtC = $this->pdo->prepare("
            SELECT c.id, c.libelle FROM competence c
            INNER JOIN offre_competence oc ON oc.competence_id = c.id
            WHERE oc.offre_id = :id ORDER BY c.libelle
        ");
        $stmtC->execute([':id' => $id]);
        $offre['competences'] = $stmtC->fetchAll();

        return $offre;
    }

    // --------------------------------------------------------
    //  OFFRES d'un recruteur (dashboard recruteur)
    // --------------------------------------------------------
    public function getOffresRecruteur(int $recruteurId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT o.id, o.titre, o.type_contrat, o.localisation,
                   o.is_active, o.date_publication,
                   COUNT(c.candidat_id) AS nb_candidatures
            FROM offre o
            INNER JOIN entreprise e ON e.id = o.entreprise_id
            LEFT JOIN candidature c ON c.offre_id = o.id
            WHERE e.recruteur_id = :rid
            GROUP BY o.id
            ORDER BY o.date_publication DESC
        ");
        $stmt->execute([':rid' => $recruteurId]);
        return $stmt->fetchAll();
    }

    // --------------------------------------------------------
    //  CREER une offre
    // --------------------------------------------------------
    public function creerOffre(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO offre (entreprise_id, titre, description, localisation,
                               type_contrat, teletravail, experience, salaire_min, salaire_max)
            VALUES (:entreprise_id, :titre, :description, :localisation,
                    :type_contrat, :teletravail, :experience, :salaire_min, :salaire_max)
        ");
        $stmt->execute([
            ':entreprise_id' => $data['entreprise_id'],
            ':titre'         => $data['titre'],
            ':description'   => $data['description'],
            ':localisation'  => $data['localisation'],
            ':type_contrat'  => $data['type_contrat'],
            ':teletravail'   => $data['teletravail'],
            ':experience'    => $data['experience'],
            ':salaire_min'   => $data['salaire_min'] ?? null,
            ':salaire_max'   => $data['salaire_max'] ?? null,
        ]);
        $offreId = (int) $this->pdo->lastInsertId();

        if (!empty($data['competences'])) {
            $this->attacherCompetences($offreId, $data['competences']);
        }
        return $offreId;
    }

    // --------------------------------------------------------
    //  SUPPRIMER (désactivation logique)
    // --------------------------------------------------------
    public function supprimerOffre(int $id, int $recruteurId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE offre o
            INNER JOIN entreprise e ON e.id = o.entreprise_id
            SET o.is_active = 0
            WHERE o.id = :id AND e.recruteur_id = :rid
        ");
        $stmt->execute([':id' => $id, ':rid' => $recruteurId]);
        return $stmt->rowCount() > 0;
    }

    // --------------------------------------------------------
    //  TOUTES LES COMPETENCES (pour le formulaire de publication)
    // --------------------------------------------------------
    public function getCompetences(): array
    {
        return $this->pdo->query("SELECT id, libelle FROM competence ORDER BY libelle")->fetchAll();
    }

    // --------------------------------------------------------
    //  HELPER PRIVE
    // --------------------------------------------------------
    private function attacherCompetences(int $offreId, array $ids): void
    {
        $del = $this->pdo->prepare("DELETE FROM offre_competence WHERE offre_id = :id");
        $del->execute([':id' => $offreId]);

        $ins = $this->pdo->prepare("INSERT INTO offre_competence (offre_id, competence_id) VALUES (:oid, :cid)");
        foreach ($ids as $cid) {
            $ins->execute([':oid' => $offreId, ':cid' => (int) $cid]);
        }
    }
}