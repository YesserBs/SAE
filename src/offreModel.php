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
        [$where, $params] = $this->construireFiltres($filtres);

        $sql = "
            SELECT o.id, o.titre, o.localisation, o.type_contrat,
                   o.teletravail, o.experience, o.salaire_min, o.salaire_max,
                   o.date_publication,
                   e.nom AS entreprise_nom, e.ville AS entreprise_ville, e.logo_path
            FROM offre o
            INNER JOIN entreprise e ON e.id = o.entreprise_id
            WHERE o.is_active = 1 $where
        ";

        $sql .= " ORDER BY o.date_publication DESC";

        // LIMIT et OFFSET castés en int directement dans la chaîne SQL :
        // pas de risque d'injection (valeurs contrôlées), et évite le bug
        // de PDO avec ATTR_EMULATE_PREPARES=false sur MariaDB/XAMPP.
        $limit  = max(1, (int) $parPage);
        $offset = max(0, (int) (($page - 1) * $parPage));
        $sql .= " LIMIT $limit OFFSET $offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // --------------------------------------------------------
    //  COMPTER (pour la pagination)
    // --------------------------------------------------------
    public function compterOffres(array $filtres = []): int
    {
        [$where, $params] = $this->construireFiltres($filtres);

        $sql = "SELECT COUNT(*) FROM offre o INNER JOIN entreprise e ON e.id = o.entreprise_id WHERE o.is_active = 1 $where";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    // --------------------------------------------------------
    //  Construit la clause WHERE + les paramètres à partir des
    //  filtres reçus. Utilisée à la fois par listerOffres() et
    //  compterOffres() pour garantir que le total affiché et la
    //  liste réellement renvoyée correspondent toujours.
    // --------------------------------------------------------
    private function construireFiltres(array $filtres): array
    {
        $sql    = '';
        $params = [];

        if (!empty($filtres['q'])) {
            $sql .= " AND o.titre LIKE :q";
            $params[':q'] = '%' . $filtres['q'] . '%';
        }
        if (!empty($filtres['adresse'])) {
            $sql .= " AND o.localisation LIKE :adresse";
            $params[':adresse'] = '%' . $filtres['adresse'] . '%';
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

        return [$sql, $params];
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
    //  Récupère une offre appartenant bien au recruteur connecté
    //  (avec ses compétences), pour pré-remplir le formulaire
    //  de modification. Retourne null si l'offre n'existe pas
    //  ou n'appartient pas à ce recruteur.
    // --------------------------------------------------------
    public function getOffreForRecruteur(int $id, int $recruteurId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT o.*
            FROM offre o
            INNER JOIN entreprise e ON e.id = o.entreprise_id
            WHERE o.id = :id AND e.recruteur_id = :rid
        ");
        $stmt->execute([':id' => $id, ':rid' => $recruteurId]);
        $offre = $stmt->fetch();
        if (!$offre) return null;

        // On récupère les libellés (texte) en plus des ids,
        // pour pouvoir pré-remplir les inputs texte dans le formulaire.
        $stmtC = $this->pdo->prepare("
            SELECT c.id, c.libelle
            FROM competence c
            INNER JOIN offre_competence oc ON oc.competence_id = c.id
            WHERE oc.offre_id = :id
            ORDER BY c.libelle
        ");
        $stmtC->execute([':id' => $id]);
        $rows = $stmtC->fetchAll();
        $offre['competences']         = array_column($rows, 'id');
        $offre['competences_libelles'] = array_column($rows, 'libelle');

        return $offre;
    }

    // --------------------------------------------------------
    //  MODIFIER une offre existante (vérifie l'appartenance
    //  au recruteur connecté via la jointure entreprise).
    // --------------------------------------------------------
    public function modifierOffre(int $id, int $recruteurId, array $data): bool
    {
        // UPDATE ... INNER JOIN n'est pas supporté de façon fiable sur toutes
        // les versions de MariaDB/XAMPP — on utilise une sous-requête à la place.
        $stmt = $this->pdo->prepare("
            UPDATE offre
            SET titre = :titre, description = :description, localisation = :localisation,
                type_contrat = :type_contrat, teletravail = :teletravail, experience = :experience,
                salaire_min = :salaire_min, salaire_max = :salaire_max
            WHERE id = :id
              AND entreprise_id IN (
                  SELECT id FROM entreprise WHERE recruteur_id = :rid
              )
        ");
        $stmt->execute([
            ':titre'        => $data['titre'],
            ':description'  => $data['description'],
            ':localisation' => $data['localisation'],
            ':type_contrat' => $data['type_contrat'],
            ':teletravail'  => $data['teletravail'],
            ':experience'   => $data['experience'],
            ':salaire_min'  => $data['salaire_min'] ?? null,
            ':salaire_max'  => $data['salaire_max'] ?? null,
            ':id'           => $id,
            ':rid'          => $recruteurId,
        ]);

        if (isset($data['competences'])) {
            $this->attacherCompetences($id, $data['competences']);
        }

        return true;
    }

    // --------------------------------------------------------
    //  SUPPRIMER une offre (suppression physique).
    //  Les FK ON DELETE CASCADE dans le schéma suppriment
    //  automatiquement les candidatures et offre_competence liées.
    //  La sous-requête garantit qu'un recruteur ne peut supprimer
    //  que ses propres offres.
    // --------------------------------------------------------
    public function supprimerOffre(int $id, int $recruteurId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM offre
            WHERE id = :id
              AND entreprise_id IN (
                  SELECT id FROM entreprise WHERE recruteur_id = :rid
              )
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

    // --------------------------------------------------------
    //  Attache des compétences à partir de libellés texte libres.
    //  Si la compétence n'existe pas encore en base, elle est créée
    //  automatiquement (INSERT IGNORE), puis liée à l'offre.
    //  Les libellés vides ou en double (insensible à la casse)
    //  sont ignorés.
    // --------------------------------------------------------
    public function attacherCompetencesParLibelle(int $offreId, array $libelles): void
    {
        // Nettoyage : on déduplique en insensible à la casse, on retire les vides
        $vus     = [];
        $propres = [];
        foreach ($libelles as $l) {
            $l = trim($l);
            if ($l === '') continue;
            $key = mb_strtolower($l);
            if (isset($vus[$key])) continue;
            $vus[$key]  = true;
            $propres[]  = $l;
        }

        // Vide les compétences actuelles de cette offre
        $del = $this->pdo->prepare("DELETE FROM offre_competence WHERE offre_id = :id");
        $del->execute([':id' => $offreId]);

        if (empty($propres)) return;

        $upsert = $this->pdo->prepare("INSERT IGNORE INTO competence (libelle) VALUES (:lib)");
        $getId  = $this->pdo->prepare("SELECT id FROM competence WHERE libelle = :lib");
        $ins    = $this->pdo->prepare("INSERT IGNORE INTO offre_competence (offre_id, competence_id) VALUES (:oid, :cid)");

        foreach ($propres as $lib) {
            $upsert->execute([':lib' => $lib]);
            $getId->execute([':lib' => $lib]);
            $cid = (int) $getId->fetchColumn();
            if ($cid) {
                $ins->execute([':oid' => $offreId, ':cid' => $cid]);
            }
        }
    }
}
