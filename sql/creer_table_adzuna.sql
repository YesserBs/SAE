CREATE TABLE IF NOT EXISTS adzuna_job_offer (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Identifiant de l'offre côté Adzuna
  external_id VARCHAR(100) NOT NULL,

  -- Source de l'offre, utile si plus tard tu ajoutes une autre API
  source VARCHAR(50) NOT NULL DEFAULT 'adzuna',

  -- Informations principales de l'offre
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  redirect_url TEXT NOT NULL,

  -- Entreprise simplifiée
  company_name VARCHAR(255) DEFAULT NULL,

  -- Localisation
  location_name VARCHAR(255) DEFAULT NULL,
  country VARCHAR(100) DEFAULT NULL,
  region VARCHAR(150) DEFAULT NULL,
  city VARCHAR(150) DEFAULT NULL,
  latitude DECIMAL(10, 7) DEFAULT NULL,
  longitude DECIMAL(10, 7) DEFAULT NULL,

  -- Catégorie Adzuna
  category_label VARCHAR(150) DEFAULT NULL,
  category_tag VARCHAR(150) DEFAULT NULL,

  -- Contrat
  contract_type VARCHAR(100) DEFAULT NULL,
  contract_time VARCHAR(100) DEFAULT NULL,

  -- Salaire
  salary_min DECIMAL(10, 2) DEFAULT NULL,
  salary_max DECIMAL(10, 2) DEFAULT NULL,
  salary_is_predicted TINYINT(1) NOT NULL DEFAULT 0,

  -- Dates
  source_created_at DATETIME DEFAULT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP,

  -- Statut local dans ton application
  is_active TINYINT(1) NOT NULL DEFAULT 1,

  PRIMARY KEY (id),

  -- Empêche d'insérer deux fois la même offre Adzuna
  UNIQUE KEY uniq_adzuna_external_id (source, external_id),

  -- Index utiles pour les recherches
  INDEX idx_title (title),
  INDEX idx_company_name (company_name),
  INDEX idx_city (city),
  INDEX idx_contract_type (contract_type),
  INDEX idx_source_created_at (source_created_at)
) ENGINE=InnoDB;