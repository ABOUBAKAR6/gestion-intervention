-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mar. 13 oct. 2026 à 04:07
-- Version du serveur : 8.4.7
-- Version de PHP : 8.5.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `gestion_interventions`
--

-- --------------------------------------------------------

--
-- Structure de la table `besoins_materiel`
--

DROP TABLE IF EXISTS `besoins_materiel`;
CREATE TABLE IF NOT EXISTS `besoins_materiel` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `intervention_id` int UNSIGNED NOT NULL,
  `description_besoin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantite` int UNSIGNED NOT NULL DEFAULT '1',
  `statut` enum('signale','commande','recu') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'signale',
  `signale_par_id` int UNSIGNED NOT NULL,
  `date_signalement` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie_par_id` int UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_bes_intervention` (`intervention_id`),
  KEY `fk_bes_signaleur` (`signale_par_id`),
  KEY `fk_bes_modifieur` (`modifie_par_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `besoins_materiel`
--

INSERT INTO `besoins_materiel` (`id`, `intervention_id`, `description_besoin`, `quantite`, `statut`, `signale_par_id`, `date_signalement`, `modifie_par_id`, `updated_at`) VALUES
(1, 1, 'Kit de joints toriques', 2, 'signale', 1, '2026-10-13 03:08:06', NULL, '2026-10-13 03:08:06');

-- --------------------------------------------------------

--
-- Structure de la table `groupes`
--

DROP TABLE IF EXISTS `groupes`;
CREATE TABLE IF NOT EXISTS `groupes` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `createur_id` int UNSIGNED NOT NULL,
  `archive_le` datetime DEFAULT NULL,
  `archive_motif` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_grg_createur` (`createur_id`)
) ;

--
-- Déchargement des données de la table `groupes`
--

INSERT INTO `groupes` (`id`, `nom`, `createur_id`, `archive_le`, `archive_motif`, `created_at`, `updated_at`) VALUES
(1, 'Technique Jour', 1, '2026-10-13 03:08:38', 'sans_chef', '2026-10-13 03:07:30', '2026-10-13 03:07:30');

-- --------------------------------------------------------

--
-- Structure de la table `historique_statuts`
--

DROP TABLE IF EXISTS `historique_statuts`;
CREATE TABLE IF NOT EXISTS `historique_statuts` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `intervention_id` int UNSIGNED NOT NULL,
  `ancien_statut` enum('signalee','en_cours','resolue','non_resolue','en_attente_materiel') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nouveau_statut` enum('signalee','en_cours','resolue','non_resolue','en_attente_materiel') COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_par_id` int UNSIGNED NOT NULL,
  `change_le` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `commentaire` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_hist_intervention` (`intervention_id`),
  KEY `fk_hist_auteur` (`change_par_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `historique_statuts`
--

INSERT INTO `historique_statuts` (`id`, `intervention_id`, `ancien_statut`, `nouveau_statut`, `change_par_id`, `change_le`, `commentaire`) VALUES
(1, 1, NULL, 'signalee', 1, '2026-10-13 03:07:55', NULL),
(2, 1, 'signalee', 'en_cours', 1, '2026-10-13 03:08:06', 'Prise en charge par la plomberie');

-- --------------------------------------------------------

--
-- Structure de la table `interventions`
--

DROP TABLE IF EXISTS `interventions`;
CREATE TABLE IF NOT EXISTS `interventions` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `groupe_id` int UNSIGNED DEFAULT NULL,
  `service_demandeur_id` int UNSIGNED DEFAULT NULL,
  `service_autre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_intervention_id` int UNSIGNED DEFAULT NULL,
  `type_autre` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nom_demandeur` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone_demandeur` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description_probleme` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `priorite` enum('basse','normale','haute','urgente') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normale',
  `statut` enum('signalee','en_cours','resolue','non_resolue','en_attente_materiel') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'signalee',
  `technicien_id` int UNSIGNED DEFAULT NULL,
  `cree_par_id` int UNSIGNED NOT NULL,
  `modifie_par_id` int UNSIGNED DEFAULT NULL,
  `supprime_par_id` int UNSIGNED DEFAULT NULL,
  `supprime_le` datetime DEFAULT NULL,
  `date_signalement` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_prise_en_charge` datetime DEFAULT NULL,
  `date_resolution` datetime DEFAULT NULL,
  `commentaire_resolution` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `fk_int_service` (`service_demandeur_id`),
  KEY `fk_int_type` (`type_intervention_id`),
  KEY `fk_int_technicien` (`technicien_id`),
  KEY `fk_int_creeur` (`cree_par_id`),
  KEY `fk_int_modif` (`modifie_par_id`),
  KEY `fk_int_supprimeur` (`supprime_par_id`),
  KEY `idx_int_liste` (`groupe_id`,`statut`,`supprime_le`),
  KEY `idx_int_reference` (`reference`)
) ;

--
-- Déchargement des données de la table `interventions`
--

INSERT INTO `interventions` (`id`, `reference`, `groupe_id`, `service_demandeur_id`, `service_autre`, `type_intervention_id`, `type_autre`, `nom_demandeur`, `telephone_demandeur`, `description_probleme`, `priorite`, `statut`, `technicien_id`, `cree_par_id`, `modifie_par_id`, `supprime_par_id`, `supprime_le`, `date_signalement`, `date_prise_en_charge`, `date_resolution`, `commentaire_resolution`, `created_at`, `updated_at`) VALUES
(1, 'INT-2026-0001', 1, 1, NULL, 3, NULL, 'Djeneba K.', '0701020304', 'Fuite d\'eau au robinet du couloir des urgences.', 'haute', 'en_cours', NULL, 1, 1, NULL, NULL, '2026-10-13 03:07:55', '2026-10-13 03:08:06', NULL, NULL, '2026-10-13 03:07:55', '2026-10-13 03:07:55');

-- --------------------------------------------------------

--
-- Structure de la table `journal_activites`
--

DROP TABLE IF EXISTS `journal_activites`;
CREATE TABLE IF NOT EXISTS `journal_activites` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `objet_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objet_id` int UNSIGNED DEFAULT NULL,
  `groupe_id` int UNSIGNED DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `adresse_ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_jrn_user` (`utilisateur_id`),
  KEY `idx_jrn_recherche` (`action`,`created_at`),
  KEY `idx_jrn_groupe` (`groupe_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `journal_activites`
--

INSERT INTO `journal_activites` (`id`, `utilisateur_id`, `action`, `objet_type`, `objet_id`, `groupe_id`, `details`, `adresse_ip`, `created_at`) VALUES
(1, 1, 'compte_cree', 'utilisateur', 1, NULL, 'Premier administrateur initialisé en CLI', NULL, '2026-10-13 03:06:08'),
(2, 1, 'connexion_reussie', NULL, NULL, NULL, NULL, '::1', '2026-10-13 03:06:21'),
(3, 1, 'connexion_reussie', NULL, NULL, NULL, NULL, '::1', '2026-10-13 03:06:45'),
(4, 1, 'groupe_cree', 'groupe', 1, 1, 'Nom : Technique Jour', '::1', '2026-10-13 03:07:30'),
(5, 1, 'compte_cree', 'utilisateur', 2, NULL, 'Rôle : utilisateur', '::1', '2026-10-13 03:07:43'),
(6, 1, 'membre_ajoute', 'groupe', 1, 1, 'Membre #2 ajouté', '::1', '2026-10-13 03:07:54'),
(7, 1, 'intervention_creee', 'intervention', 1, 1, 'Réf. INT-2026-0001', '::1', '2026-10-13 03:07:55'),
(8, 1, 'intervention_statut_change', 'intervention', 1, 1, 'Signalée → En cours', '::1', '2026-10-13 03:08:06'),
(9, 1, 'besoin_ajoute', 'intervention', 1, 1, 'Kit de joints toriques x2', '::1', '2026-10-13 03:08:06'),
(10, 1, 'pdf_genere', 'intervention', 1, 1, 'Réf. INT-2026-0001', '::1', '2026-10-13 03:08:24'),
(11, 1, 'membre_retire', 'groupe', 1, 1, 'Membre #1 retiré', '::1', '2026-10-13 03:08:38'),
(12, 1, 'groupe_archive', 'groupe', 1, 1, 'Aucun chef actif (RG12)', '::1', '2026-10-13 03:08:38'),
(13, 1, 'connexion_reussie', NULL, NULL, NULL, NULL, '::1', '2026-10-13 03:09:12'),
(14, 1, 'compte_mdp_reset', 'utilisateur', 2, NULL, NULL, '::1', '2026-10-13 03:09:12'),
(16, 1, 'connexion_reussie', NULL, NULL, NULL, NULL, '::1', '2026-10-13 03:10:55'),
(17, 1, 'compte_mdp_reset', 'utilisateur', 2, NULL, NULL, '::1', '2026-10-13 03:10:55'),
(18, 1, 'compte_mdp_reset', 'utilisateur', 2, NULL, NULL, '::1', '2026-10-13 03:11:38'),
(19, 1, 'compte_mdp_reset', 'utilisateur', 2, NULL, NULL, '::1', '2026-10-13 03:12:27'),
(23, 1, 'connexion_reussie', NULL, NULL, NULL, NULL, '::1', '2026-10-13 03:13:56'),
(24, 1, 'connexion_reussie', NULL, NULL, NULL, NULL, '::1', '2026-10-13 03:15:34'),
(25, 1, 'connexion_reussie', NULL, NULL, NULL, NULL, '::1', '2026-10-13 03:17:03'),
(26, 1, 'connexion_reussie', NULL, NULL, NULL, NULL, '::1', '2026-10-13 03:28:58'),
(27, 1, 'impression_genere', 'intervention', 1, 1, 'Réf. INT-2026-0001', '::1', '2026-10-13 03:28:59'),
(28, 1, 'connexion_reussie', NULL, NULL, NULL, NULL, '::1', '2026-10-13 03:30:27'),
(29, 1, 'compte_cree', 'utilisateur', 3, NULL, 'Rôle : utilisateur', '::1', '2026-10-13 03:30:28'),
(34, 1, 'connexion_reussie', NULL, NULL, NULL, NULL, '::1', '2026-10-13 03:31:15'),
(35, 1, 'intervention_modifiee', 'intervention', 1, 1, 'Réf. INT-2026-0001', '::1', '2026-10-13 03:35:55'),
(36, 1, 'pdf_genere', 'intervention', 1, 1, 'Réf. INT-2026-0001', '::1', '2026-10-13 03:36:04'),
(37, 1, 'pdf_genere', 'intervention', 1, 1, 'Réf. INT-2026-0001', '::1', '2026-10-13 03:36:04'),
(38, 1, 'connexion_reussie', NULL, NULL, NULL, NULL, '::1', '2026-10-13 03:44:25');

-- --------------------------------------------------------

--
-- Structure de la table `membres_groupe`
--

DROP TABLE IF EXISTS `membres_groupe`;
CREATE TABLE IF NOT EXISTS `membres_groupe` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `groupe_id` int UNSIGNED NOT NULL,
  `utilisateur_id` int UNSIGNED NOT NULL,
  `est_chef` tinyint(1) NOT NULL DEFAULT '0',
  `ajoute_par_id` int UNSIGNED NOT NULL,
  `date_ajout` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `retire_par_id` int UNSIGNED DEFAULT NULL,
  `date_retrait` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_mem_user` (`utilisateur_id`),
  KEY `fk_mem_ajout` (`ajoute_par_id`),
  KEY `fk_mem_retrait` (`retire_par_id`),
  KEY `idx_mem_actifs` (`groupe_id`,`utilisateur_id`,`date_retrait`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `membres_groupe`
--

INSERT INTO `membres_groupe` (`id`, `groupe_id`, `utilisateur_id`, `est_chef`, `ajoute_par_id`, `date_ajout`, `retire_par_id`, `date_retrait`) VALUES
(1, 1, 1, 1, 1, '2026-10-13 03:07:30', 1, '2026-10-13 03:08:38');

-- --------------------------------------------------------

--
-- Structure de la table `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `roles`
--

INSERT INTO `roles` (`id`, `code`, `nom`) VALUES
(1, 'admin', 'Administrateur'),
(2, 'utilisateur', 'Utilisateur');

-- --------------------------------------------------------

--
-- Structure de la table `services_demandeurs`
--

DROP TABLE IF EXISTS `services_demandeurs`;
CREATE TABLE IF NOT EXISTS `services_demandeurs` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `services_demandeurs`
--

INSERT INTO `services_demandeurs` (`id`, `nom`, `actif`, `created_at`) VALUES
(1, 'Urgences', 1, '2026-10-13 02:56:43'),
(2, 'Bloc op├®ratoire', 1, '2026-10-13 02:56:43'),
(3, 'Radiologie', 1, '2026-10-13 02:56:43'),
(4, 'Administration', 1, '2026-10-13 02:56:43'),
(5, 'Laboratoire', 1, '2026-10-13 02:56:43');

-- --------------------------------------------------------

--
-- Structure de la table `tentatives_connexion`
--

DROP TABLE IF EXISTS `tentatives_connexion`;
CREATE TABLE IF NOT EXISTS `tentatives_connexion` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `email_normalise` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adresse_ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reussie` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tent_email` (`email_normalise`,`created_at`),
  KEY `idx_tent_ip` (`adresse_ip`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `tentatives_connexion`
--

INSERT INTO `tentatives_connexion` (`id`, `email_normalise`, `adresse_ip`, `reussie`, `created_at`) VALUES
(1, 'siluelagnon08@gmail.com', '::1', 1, '2026-10-13 03:13:56'),
(2, 'siluelagnon08@gmail.com', '::1', 1, '2026-10-13 03:15:34'),
(3, 'siluelagnon08@gmail.com', '::1', 0, '2026-10-13 03:16:45'),
(4, 'siluelagnon08@gmail.com', '::1', 1, '2026-10-13 03:17:03'),
(5, 'siluelagnon08@gmail.com', '::1', 1, '2026-10-13 03:28:58'),
(6, 'siluelagnon08@gmail.com', '::1', 1, '2026-10-13 03:30:27'),
(8, 'siluelagnon08@gmail.com', '::1', 1, '2026-10-13 03:31:15'),
(9, 'siluelagnon08@gmail.com', '::1', 1, '2026-10-13 03:44:25');

-- --------------------------------------------------------

--
-- Structure de la table `types_intervention`
--

DROP TABLE IF EXISTS `types_intervention`;
CREATE TABLE IF NOT EXISTS `types_intervention` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nom` (`nom`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `types_intervention`
--

INSERT INTO `types_intervention` (`id`, `nom`, `actif`, `created_at`) VALUES
(1, 'Plomberie', 1, '2026-10-13 02:56:43'),
(2, '├ëlectricit├®', 1, '2026-10-13 02:56:43'),
(3, 'Informatique', 1, '2026-10-13 02:56:43'),
(4, 'Biom├®dical', 1, '2026-10-13 02:56:43'),
(5, 'Serrurerie', 1, '2026-10-13 02:56:43');

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` int UNSIGNED NOT NULL,
  `nom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prenom` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `doit_changer_mot_de_passe` tinyint(1) NOT NULL DEFAULT '1',
  `auth_version` int UNSIGNED NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_util_role` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `role_id`, `nom`, `prenom`, `email`, `telephone`, `password_hash`, `actif`, `doit_changer_mot_de_passe`, `auth_version`, `created_at`, `updated_at`, `last_login_at`) VALUES
(1, 1, 'Admin', 'Service technique', 'siluelagnon08@gmail.com', NULL, '$2y$12$Xhy92t0gqh3aopAJRtDsLub.MLmuK0TAUBKxYAqGZJPlEtyc28AOW', 1, 0, 1, '2026-10-13 03:06:08', '2026-10-13 03:06:08', '2026-10-13 03:44:25');

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `v_chefs_actifs`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `v_chefs_actifs`;
CREATE TABLE IF NOT EXISTS `v_chefs_actifs` (
`groupe_id` int unsigned
,`nb_chefs_actifs` bigint
);

-- --------------------------------------------------------

--
-- Structure de la vue `v_chefs_actifs`
--
DROP TABLE IF EXISTS `v_chefs_actifs`;

DROP VIEW IF EXISTS `v_chefs_actifs`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_chefs_actifs`  AS SELECT `m`.`groupe_id` AS `groupe_id`, count(distinct `m`.`utilisateur_id`) AS `nb_chefs_actifs` FROM (`membres_groupe` `m` join `utilisateurs` `u` on((`u`.`id` = `m`.`utilisateur_id`))) WHERE ((`m`.`est_chef` = 1) AND (`m`.`retire_par_id` is null) AND (`m`.`date_retrait` is null) AND (`u`.`actif` = 1)) GROUP BY `m`.`groupe_id` ;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `besoins_materiel`
--
ALTER TABLE `besoins_materiel`
  ADD CONSTRAINT `fk_bes_intervention` FOREIGN KEY (`intervention_id`) REFERENCES `interventions` (`id`),
  ADD CONSTRAINT `fk_bes_modifieur` FOREIGN KEY (`modifie_par_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `fk_bes_signaleur` FOREIGN KEY (`signale_par_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `groupes`
--
ALTER TABLE `groupes`
  ADD CONSTRAINT `fk_grg_createur` FOREIGN KEY (`createur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `historique_statuts`
--
ALTER TABLE `historique_statuts`
  ADD CONSTRAINT `fk_hist_auteur` FOREIGN KEY (`change_par_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `fk_hist_intervention` FOREIGN KEY (`intervention_id`) REFERENCES `interventions` (`id`);

--
-- Contraintes pour la table `interventions`
--
ALTER TABLE `interventions`
  ADD CONSTRAINT `fk_int_creeur` FOREIGN KEY (`cree_par_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `fk_int_groupe` FOREIGN KEY (`groupe_id`) REFERENCES `groupes` (`id`),
  ADD CONSTRAINT `fk_int_modif` FOREIGN KEY (`modifie_par_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `fk_int_service` FOREIGN KEY (`service_demandeur_id`) REFERENCES `services_demandeurs` (`id`),
  ADD CONSTRAINT `fk_int_supprimeur` FOREIGN KEY (`supprime_par_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `fk_int_technicien` FOREIGN KEY (`technicien_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `fk_int_type` FOREIGN KEY (`type_intervention_id`) REFERENCES `types_intervention` (`id`);

--
-- Contraintes pour la table `journal_activites`
--
ALTER TABLE `journal_activites`
  ADD CONSTRAINT `fk_jrn_groupe` FOREIGN KEY (`groupe_id`) REFERENCES `groupes` (`id`),
  ADD CONSTRAINT `fk_jrn_user` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `membres_groupe`
--
ALTER TABLE `membres_groupe`
  ADD CONSTRAINT `fk_mem_ajout` FOREIGN KEY (`ajoute_par_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `fk_mem_groupe` FOREIGN KEY (`groupe_id`) REFERENCES `groupes` (`id`),
  ADD CONSTRAINT `fk_mem_retrait` FOREIGN KEY (`retire_par_id`) REFERENCES `utilisateurs` (`id`),
  ADD CONSTRAINT `fk_mem_user` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateurs` (`id`);

--
-- Contraintes pour la table `utilisateurs`
--
ALTER TABLE `utilisateurs`
  ADD CONSTRAINT `fk_util_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
