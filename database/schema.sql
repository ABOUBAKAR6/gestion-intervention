-- =============================================================
-- Base de données : gestion_interventions
-- Cahier des charges : gestion des interventions du service technique (CHU)
-- Moteur InnoDB, utf8mb4
-- =============================================================
-- Exécution : mysql.exe -u root gestion_interventions < schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS gestion_interventions
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestion_interventions;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS tentatives_connexion, journal_activites, besoins_materiel,
  historique_statuts, interventions, types_intervention, services_demandeurs,
  membres_groupe, groupes, utilisateurs, roles;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------- TABLE roles --------------------------
CREATE TABLE roles (
  id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(30)  NOT NULL UNIQUE,
  nom  VARCHAR(60)  NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (code, nom) VALUES ('admin', 'Administrateur'), ('utilisateur', 'Utilisateur');

-- ------------------ TABLE utilisateurs ------------------------
CREATE TABLE utilisateurs (
  id                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role_id                   INT UNSIGNED NOT NULL,
  nom                       VARCHAR(100)  NULL,
  prenom                    VARCHAR(100)  NULL,
  email                     VARCHAR(190)  NOT NULL UNIQUE,
  telephone                 VARCHAR(30)   NULL,
  password_hash             VARCHAR(255)  NOT NULL,
  actif                     TINYINT(1)    NOT NULL DEFAULT 1,
  doit_changer_mot_de_passe TINYINT(1)    NOT NULL DEFAULT 1,
  auth_version              INT UNSIGNED  NOT NULL DEFAULT 1,
  created_at                DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at             DATETIME      NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_util_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------- TABLE groupes -------------------------
CREATE TABLE groupes (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom           VARCHAR(120) NOT NULL,
  createur_id   INT UNSIGNED NOT NULL,
  archive_le    DATETIME     NULL,
  archive_motif VARCHAR(60)  NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_grg_createur FOREIGN KEY (createur_id) REFERENCES utilisateurs (id),
  CONSTRAINT chk_archive_motif CHECK (
    (archive_le IS NULL AND archive_motif IS NULL) OR
    (archive_le IS NOT NULL AND archive_motif IN ('sans_chef','suppression_createur','suppression_admin'))
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ TABLE membres_groupe ----------------------
-- Unicité d'une appartenance active : contrôlée par l'application
-- (MySQL ne sait pas créer un index unique partiel sur date_retrait).
CREATE TABLE membres_groupe (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  groupe_id       INT UNSIGNED NOT NULL,
  utilisateur_id  INT UNSIGNED NOT NULL,
  est_chef        TINYINT(1)   NOT NULL DEFAULT 0,
  ajoute_par_id   INT UNSIGNED NOT NULL,
  date_ajout      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  retire_par_id   INT UNSIGNED NULL,
  date_retrait    DATETIME     NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_mem_groupe FOREIGN KEY (groupe_id) REFERENCES groupes (id),
  CONSTRAINT fk_mem_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id),
  CONSTRAINT fk_mem_ajout FOREIGN KEY (ajoute_par_id) REFERENCES utilisateurs (id),
  CONSTRAINT fk_mem_retrait FOREIGN KEY (retire_par_id) REFERENCES utilisateurs (id),
  INDEX idx_mem_actifs (groupe_id, utilisateur_id, date_retrait)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------- services_demandeurs / types_intervention -------
CREATE TABLE services_demandeurs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom        VARCHAR(100) NOT NULL UNIQUE,
  actif      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE types_intervention (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom        VARCHAR(100) NOT NULL UNIQUE,
  actif      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO services_demandeurs (nom) VALUES
  ('Urgences'), ('Bloc opératoire'), ('Radiologie'), ('Administration'), ('Laboratoire');
INSERT INTO types_intervention (nom) VALUES
  ('Plomberie'), ('Électricité'), ('Informatique'), ('Biomédical'), ('Serrurerie');

-- ------------------ TABLE interventions -----------------------
CREATE TABLE interventions (
  id                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  reference              VARCHAR(20)   NOT NULL UNIQUE,
  groupe_id              INT UNSIGNED  NULL,
  service_demandeur_id   INT UNSIGNED  NULL,
  service_autre          VARCHAR(100)  NULL,
  type_intervention_id   INT UNSIGNED  NULL,
  type_autre             VARCHAR(100)  NULL,
  nom_demandeur          VARCHAR(150)  NOT NULL,
  telephone_demandeur    VARCHAR(30)   NOT NULL,
  description_probleme   TEXT          NOT NULL,
  priorite               ENUM('basse','normale','haute','urgente') NOT NULL DEFAULT 'normale',
  statut                 ENUM('signalee','en_cours','resolue','non_resolue','en_attente_materiel')
                                       NOT NULL DEFAULT 'signalee',
  technicien_id          INT UNSIGNED  NULL,
  cree_par_id            INT UNSIGNED  NOT NULL,
  modifie_par_id         INT UNSIGNED  NULL,
  supprime_par_id        INT UNSIGNED  NULL,
  supprime_le            DATETIME      NULL,
  date_signalement       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_prise_en_charge   DATETIME      NULL,
  date_resolution        DATETIME      NULL,
  commentaire_resolution TEXT          NULL,
  created_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_int_groupe FOREIGN KEY (groupe_id) REFERENCES groupes (id),
  CONSTRAINT fk_int_service FOREIGN KEY (service_demandeur_id) REFERENCES services_demandeurs (id),
  CONSTRAINT fk_int_type FOREIGN KEY (type_intervention_id) REFERENCES types_intervention (id),
  CONSTRAINT fk_int_technicien FOREIGN KEY (technicien_id) REFERENCES utilisateurs (id),
  CONSTRAINT fk_int_creeur FOREIGN KEY (cree_par_id) REFERENCES utilisateurs (id),
  CONSTRAINT fk_int_modif FOREIGN KEY (modifie_par_id) REFERENCES utilisateurs (id),
  CONSTRAINT fk_int_supprimeur FOREIGN KEY (supprime_par_id) REFERENCES utilisateurs (id),
  -- Service : liste OU texte libre, jamais les deux, jamais aucun (RG14)
  CONSTRAINT chk_service CHECK (
    (service_demandeur_id IS NOT NULL AND service_autre IS NULL) OR
    (service_demandeur_id IS NULL AND service_autre IS NOT NULL AND service_autre <> '')
  ),
  -- Type : liste OU texte libre, jamais les deux, jamais aucun (RG14)
  CONSTRAINT chk_type CHECK (
    (type_intervention_id IS NOT NULL AND type_autre IS NULL) OR
    (type_intervention_id IS NULL AND type_autre IS NOT NULL AND type_autre <> '')
  ),
  INDEX idx_int_liste (groupe_id, statut, supprime_le),
  INDEX idx_int_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- TABLE historique_statuts --------------------
CREATE TABLE historique_statuts (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  intervention_id  INT UNSIGNED NOT NULL,
  ancien_statut    ENUM('signalee','en_cours','resolue','non_resolue','en_attente_materiel') NULL,
  nouveau_statut   ENUM('signalee','en_cours','resolue','non_resolue','en_attente_materiel') NOT NULL,
  change_par_id    INT UNSIGNED NOT NULL,
  change_le        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  commentaire      VARCHAR(255) NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_hist_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id),
  CONSTRAINT fk_hist_auteur FOREIGN KEY (change_par_id) REFERENCES utilisateurs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ TABLE besoins_materiel --------------------
CREATE TABLE besoins_materiel (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  intervention_id    INT UNSIGNED NOT NULL,
  description_besoin VARCHAR(255) NOT NULL,
  quantite           INT UNSIGNED NOT NULL DEFAULT 1,
  statut             ENUM('signale','commande','recu') NOT NULL DEFAULT 'signale',
  signale_par_id     INT UNSIGNED NOT NULL,
  date_signalement   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modifie_par_id     INT UNSIGNED NULL,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_bes_intervention FOREIGN KEY (intervention_id) REFERENCES interventions (id),
  CONSTRAINT fk_bes_signaleur FOREIGN KEY (signale_par_id) REFERENCES utilisateurs (id),
  CONSTRAINT fk_bes_modifieur FOREIGN KEY (modifie_par_id) REFERENCES utilisateurs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------ TABLE journal_activites -------------------
-- Table en ajout seulement : jamais modifiée ni vidée par l'application.
CREATE TABLE journal_activites (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id INT UNSIGNED NULL,
  action         VARCHAR(60)  NOT NULL,
  objet_type     VARCHAR(40)  NULL,
  objet_id       INT UNSIGNED NULL,
  groupe_id      INT UNSIGNED NULL,
  details        TEXT         NULL,
  adresse_ip     VARCHAR(45)  NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_jrn_user FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id),
  CONSTRAINT fk_jrn_groupe FOREIGN KEY (groupe_id) REFERENCES groupes (id),
  INDEX idx_jrn_recherche (action, created_at),
  INDEX idx_jrn_groupe (groupe_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------- TABLE tentatives_connexion -------------------
CREATE TABLE tentatives_connexion (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email_normalise VARCHAR(190) NOT NULL,
  adresse_ip      VARCHAR(45)  NOT NULL,
  reussie         TINYINT(1)   NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_tent_email (email_normalise, created_at),
  INDEX idx_tent_ip (adresse_ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vue pratique : chefs actifs par groupe
CREATE OR REPLACE VIEW v_chefs_actifs AS
  SELECT groupe_id, COUNT(DISTINCT utilisateur_id) AS nb_chefs_actifs
  FROM membres_groupe m
  JOIN utilisateurs u ON u.id = m.utilisateur_id
  WHERE m.est_chef = 1 AND m.retire_par_id IS NULL AND m.date_retrait IS NULL AND u.actif = 1
  GROUP BY groupe_id;
