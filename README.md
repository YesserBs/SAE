# SearchForAJob

Application web de recherche d'emploi développée dans le cadre d'une SAE (Situation d'Apprentissage et d'Évaluation) à Sup Galilée — Université Sorbonne Paris Nord. Le site met en relation des candidats et des recruteurs : recherche d'offres avec filtres, candidature en ligne avec CV, gestion des offres côté recruteur, et un module d'agrégation d'offres externes via l'API Adzuna.

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Stack technique](#stack-technique)
- [Structure du projet](#structure-du-projet)
- [Modèle de données](#modèle-de-données)
- [Installation](#installation)
- [Comptes de démonstration](#comptes-de-démonstration)
- [Équipe](#équipe)
- [Limites et ouverture NoSQL](#limites-et-ouverture-nosql)

## Fonctionnalités

**Côté candidat**
- Inscription avec vérification de l'adresse email (lien envoyé automatiquement) et réinitialisation de mot de passe en cas d'oubli
- Recherche d'offres par mot-clé, avec filtres combinables : type de contrat, télétravail, niveau d'expérience, secteur d'activité, adresse + rayon
- Candidature en ligne avec upload de CV (PDF/DOC/DOCX) et lettre de motivation
- Tableau de bord avec historique des candidatures et édition du profil
- Consultation d'offres partenaires externes (API Adzuna), en complément des offres publiées sur le site

**Côté recruteur**
- Création et modification de la fiche entreprise
- Publication d'une offre avec liste de compétences saisies dynamiquement (ajout/suppression de champs sans rechargement de page)
- Modification et suppression des offres publiées
- Tableau de bord avec consultation des candidatures reçues (CV, lettre de motivation, changement de statut)

## Stack technique

| Couche | Technologie |
|---|---|
| Langage serveur | PHP 8.x |
| Accès base de données | PDO (requêtes préparées, aucune concaténation de variables) |
| Base de données | MySQL / MariaDB |
| Front-end | HTML, CSS, Bootstrap 5, JavaScript natif |
| Serveur local | XAMPP |
| API externe | Adzuna (offres d'emploi partenaires) |
| Envoi d'email | Client SMTP maison (sans dépendance externe), compte Gmail |

## Structure du projet

```
├── public/             Pages accessibles depuis le navigateur (1 fichier = 1 page)
│   ├── index.php               Accueil, recherche et filtres
│   ├── offre.php                Détail d'une offre + candidature
│   ├── login.php / register.php Authentification
│   ├── mot-de-passe-oublie.php / reinitialiser-mot-de-passe.php
│   ├── verifier-email.php / renvoyer-verification.php
│   ├── dashboard-candidat.php / dashboard-recruteur.php
│   ├── publier-offre.php / modifier-offre.php
│   ├── entreprise.php           Fiche entreprise du recruteur
│   ├── profil.php               Édition du profil utilisateur
│   └── offres-partenaires.php   Offres externes (API Adzuna)
├── src/                Classes métier (un modèle par entité)
│   ├── offreModel.php / EntrepriseModel.php / CandidatureModel.php / UserModel.php
│   ├── Auth.php                 Sessions et contrôle d'accès par rôle
│   ├── Mailer.php / SmtpMailer.php  Envoi d'emails (vérification, mot de passe)
│   ├── helpers.php              Fonctions d'affichage partagées
│   └── footer.php               Pied de page partagé
├── backend/            Intégration API externe
│   ├── AdzunaClient.php         Client HTTP pour l'API Adzuna
│   ├── api.php / config.json    Script de test de l'intégration
│   └── secrets.example.php      Modèle pour les clés d'API (à copier en secrets.local.php)
├── config/
│   ├── database.php             Connexion PDO
│   └── mail.example.php         Modèle pour les identifiants SMTP (à copier en mail.local.php)
├── assets/             CSS, JavaScript, fichiers uploadés (CV)
└── sql/
    ├── schema.sql                Script de création de la base + données de test
    └── migration_email_verification.sql  Migration pour une base déjà existante
```

## Modèle de données

Le schéma s'appuie sur sept tables et contient les trois types de relations attendus :

- **1–1** : `utilisateur` ↔ `profil`. Chaque utilisateur (candidat ou recruteur) possède exactement un profil ; la relation est imposée par une contrainte `UNIQUE` sur `profil.utilisateur_id`, pas seulement par convention.
- **1–N** : `entreprise` ↔ `offre`. Une entreprise publie plusieurs offres.
- **N–N** :
  - `offre` ↔ `competence`, via la table pivot `offre_competence`.
  - `utilisateur` (candidat) ↔ `offre`, via la table `candidature`, qui porte en plus ses propres attributs (statut, lettre de motivation, date) — c'est une véritable entité-association, pas une simple table pivot.

Toutes les clés étrangères sont déclarées avec `ON DELETE CASCADE`. Le script complet se trouve dans [`sql/schema.sql`](sql/schema.sql).

## Installation

1. **Cloner le dépôt** dans le dossier `htdocs` de XAMPP (ou équivalent WAMP/MAMP).

2. **Créer la base de données** : démarrer Apache et MySQL depuis XAMPP, puis importer le script dans phpMyAdmin (ou en ligne de commande) :
   ```
   sql/schema.sql
   ```
   Si une base existe déjà à partir d'une ancienne version du projet, exécuter plutôt `sql/migration_email_verification.sql`.

3. **Configurer la connexion à la base** dans `config/database.php` (identifiants par défaut XAMPP : `root` / mot de passe vide ou `root` selon l'installation).

4. **Configurer l'envoi d'email** (vérification de compte, mot de passe oublié) :
   - copier `config/mail.example.php` en `config/mail.local.php`
   - renseigner un compte Gmail avec un **mot de passe d'application** (la validation en deux étapes doit être activée sur le compte ; le mot de passe habituel ne fonctionne pas pour l'envoi SMTP)
   - tester la configuration via `backend/test-email.php?to=votre@email.com`

5. **Configurer l'API Adzuna** (offres partenaires) :
   - copier `backend/secrets.example.php` en `backend/secrets.local.php`
   - renseigner un `app_id` et une `app_key` obtenus sur [developer.adzuna.com](https://developer.adzuna.com/)

6. Accéder au site via `http://localhost/<dossier-du-projet>/public/index.php`

`config/mail.local.php` et `backend/secrets.local.php` sont exclus du dépôt via `.gitignore` : ils ne doivent jamais être commités.

## Comptes de démonstration

Le script `schema.sql` insère quatre comptes de test (mot de passe : `secret123`), déjà marqués comme vérifiés :

| Email | Rôle |
|---|---|
| alice@example.com | candidat |
| bob@example.com | candidat |
| recruteur@bnp.com | recruteur |
| recruteur@ovh.com | recruteur |

## Équipe

Projet réalisé en binôme :

- **Ablaye Sow** — conception de la base de données, ensemble des pages PHP (authentification, recherche, gestion des offres, tableaux de bord) et intégration front-end (Bootstrap / JavaScript)
- **Yesser Benselma** — intégration de l'API externe Adzuna (client HTTP, page des offres partenaires)

## Limites et ouverture NoSQL

Le modèle relationnel mis en place convient à l'échelle d'un projet académique, mais montrerait ses limites avec un volume de données réaliste pour une plateforme nationale : les filtres combinés de la page d'accueil reposent sur des jointures entre `offre` et `entreprise` dont le coût augmenterait fortement, et la table `candidature` grandirait indéfiniment, transformant des requêtes simples en jointures à trois tables exécutées en continu. Une approche NoSQL orientée documents (MongoDB) permettrait de limiter ces jointures en embarquant directement, par exemple, l'historique des candidatures d'un candidat dans son propre document plutôt que dans une table séparée — au prix d'une duplication de données à gérer lors des mises à jour. Cette piste reste conceptuelle et n'a pas été implémentée dans le cadre de ce projet.
