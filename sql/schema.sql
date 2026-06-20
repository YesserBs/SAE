-- ============================================================
--  SearchForAJob — schema.sql
--  Compatible MySQL / MariaDB (XAMPP)
--  Encodage : UTF-8 | Moteur : InnoDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS searchforajob
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE searchforajob;

-- ============================================================
--  1. UTILISATEUR
--     Représente un candidat ou un recruteur.
-- ============================================================
CREATE TABLE utilisateur (
  id         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  email      VARCHAR(180)    NOT NULL UNIQUE,
  password   VARCHAR(255)    NOT NULL,          -- hash bcrypt
  role       ENUM('candidat','recruteur') NOT NULL DEFAULT 'candidat',
  created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id)
) ENGINE=InnoDB;


-- ============================================================
--  2. PROFIL  (relation 1-1 avec utilisateur)
--     Un utilisateur possède exactement un profil.
--     La contrainte UNIQUE sur utilisateur_id enforce le 1-1.
-- ============================================================
CREATE TABLE profil (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  utilisateur_id  INT UNSIGNED  NOT NULL UNIQUE,   -- clé du 1-1
  nom             VARCHAR(100)  NOT NULL,
  prenom          VARCHAR(100)  NOT NULL,
  telephone       VARCHAR(20)   DEFAULT NULL,
  cv_path         VARCHAR(255)  DEFAULT NULL,       -- chemin vers le fichier CV
  bio             TEXT          DEFAULT NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  CONSTRAINT fk_profil_utilisateur
    FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
--  3. ENTREPRISE  (relation 1-N avec offre)
--     Un recruteur (utilisateur) peut gérer une entreprise.
--     Une entreprise publie plusieurs offres.
-- ============================================================
CREATE TABLE entreprise (
  id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  recruteur_id INT UNSIGNED  NOT NULL,
  nom          VARCHAR(150)  NOT NULL,
  secteur      VARCHAR(100)  DEFAULT NULL,
  ville        VARCHAR(100)  DEFAULT NULL,
  description  TEXT          DEFAULT NULL,
  logo_path    VARCHAR(255)  DEFAULT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  CONSTRAINT fk_entreprise_recruteur
    FOREIGN KEY (recruteur_id) REFERENCES utilisateur(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
--  4. OFFRE  (relation 1-N : une entreprise -> plusieurs offres)
-- ============================================================
CREATE TABLE offre (
  id               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  entreprise_id    INT UNSIGNED   NOT NULL,
  titre            VARCHAR(200)   NOT NULL,
  description      TEXT           NOT NULL,
  localisation     VARCHAR(150)   NOT NULL,
  type_contrat     ENUM('CDI','CDD','Stage','Alternance','Freelance') NOT NULL,
  teletravail      ENUM('Presentiel','Hybride','Full remote')         NOT NULL DEFAULT 'Presentiel',
  experience       ENUM('Junior','Confirme','Senior')                 NOT NULL DEFAULT 'Junior',
  salaire_min      DECIMAL(10,2)  DEFAULT NULL,
  salaire_max      DECIMAL(10,2)  DEFAULT NULL,
  is_active        TINYINT(1)     NOT NULL DEFAULT 1,
  date_publication DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  CONSTRAINT fk_offre_entreprise
    FOREIGN KEY (entreprise_id) REFERENCES entreprise(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
--  5. COMPETENCE
--     Référentiel de compétences (PHP, React, MySQL, etc.)
-- ============================================================
CREATE TABLE competence (
  id      INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  libelle VARCHAR(100)  NOT NULL UNIQUE,

  PRIMARY KEY (id)
) ENGINE=InnoDB;


-- ============================================================
--  6. OFFRE_COMPETENCE  (relation N-N : offre <-> competence)
--     Table pivot : une offre requiert plusieurs compétences,
--     une compétence apparaît dans plusieurs offres.
-- ============================================================
CREATE TABLE offre_competence (
  offre_id      INT UNSIGNED NOT NULL,
  competence_id INT UNSIGNED NOT NULL,

  PRIMARY KEY (offre_id, competence_id),
  CONSTRAINT fk_oc_offre
    FOREIGN KEY (offre_id)      REFERENCES offre(id)      ON DELETE CASCADE,
  CONSTRAINT fk_oc_competence
    FOREIGN KEY (competence_id) REFERENCES competence(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
--  7. CANDIDATURE  (relation N-N : candidat <-> offre)
--     Table pivot enrichie : un candidat postule à plusieurs
--     offres, une offre reçoit plusieurs candidatures.
-- ============================================================
CREATE TABLE candidature (
  candidat_id      INT UNSIGNED NOT NULL,
  offre_id         INT UNSIGNED NOT NULL,
  cv_path          VARCHAR(255) DEFAULT NULL,
  lettre_motivation TEXT        DEFAULT NULL,
  statut           ENUM('En attente','Vue','Acceptee','Refusee') NOT NULL DEFAULT 'En attente',
  date_candidature DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (candidat_id, offre_id),
  CONSTRAINT fk_cand_candidat
    FOREIGN KEY (candidat_id) REFERENCES utilisateur(id) ON DELETE CASCADE,
  CONSTRAINT fk_cand_offre
    FOREIGN KEY (offre_id)    REFERENCES offre(id)       ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
--  DONNEES DE TEST
-- ============================================================

-- Utilisateurs (passwords = 'secret123' hashé avec bcrypt)
INSERT INTO utilisateur (email, password, role) VALUES
  ('alice@example.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'candidat'),
  ('bob@example.com',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'candidat'),
  ('recruteur@bnp.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'recruteur'),
  ('recruteur@ovh.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'recruteur');

-- Profils (1-1 avec utilisateur)
INSERT INTO profil (utilisateur_id, nom, prenom, telephone) VALUES
  (1, 'Dupont',  'Alice', '0601020304'),
  (2, 'Martin',  'Bob',   '0605060708'),
  (3, 'Leroy',   'Julie', '0611121314'),
  (4, 'Moreau',  'Paul',  '0615161718');

-- Entreprises
INSERT INTO entreprise (recruteur_id, nom, secteur, ville) VALUES
  (3, 'BNP Paribas', 'Finance',      'Paris'),
  (4, 'OVHcloud',    'Informatique', 'Roubaix');

-- Offres
INSERT INTO offre (entreprise_id, titre, description, localisation, type_contrat, teletravail, experience, salaire_min, salaire_max) VALUES
  (1, 'Developpeur Full Stack PHP / React',
      'Vous rejoignez l equipe web pour developper des applications internes.',
      'Paris 9e', 'CDI', 'Hybride', 'Confirme', 45000, 55000),
  (2, 'Developpeur PHP / MySQL equipe data',
      'Integration de pipelines de donnees et maintenance de l API REST.',
      'Remote',   'CDI', 'Full remote', 'Senior', 42000, 50000);

-- Competences
INSERT INTO competence (libelle) VALUES
  ('PHP 8'), ('React'), ('MySQL'), ('MariaDB'), ('Docker'), ('Symfony');

-- Liaison offre <-> competence (N-N)
INSERT INTO offre_competence (offre_id, competence_id) VALUES
  (1, 1), (1, 2), (1, 3),   -- offre 1 : PHP8, React, MySQL
  (2, 1), (2, 4), (2, 5);   -- offre 2 : PHP8, MariaDB, Docker

-- Candidatures (N-N)
INSERT INTO candidature (candidat_id, offre_id, statut) VALUES
  (1, 1, 'En attente'),
  (1, 2, 'Vue'),
  (2, 1, 'Acceptee');
