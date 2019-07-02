-- phpMyAdmin SQL Dump
-- version 4.8.4
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le :  jeu. 21 mars 2019 à 16:11
-- Version du serveur :  5.7.24
-- Version de PHP :  7.2.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données :  `db_stock`
--

-- --------------------------------------------------------

--
-- Structure de la table `alerte`
--

DROP TABLE IF EXISTS `alerte`;
CREATE TABLE IF NOT EXISTS `alerte` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alerte_utilisateur_id` int(11) DEFAULT NULL,
  `alerte_ref_article_id` int(11) DEFAULT NULL,
  `alerte_nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alerte_numero` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alerte_seuil` int(11) DEFAULT NULL,
  `seuil_atteint` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_3AE753A7CF90D8D` (`alerte_utilisateur_id`),
  KEY `IDX_3AE753AF3208540` (`alerte_ref_article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `article`
--

DROP TABLE IF EXISTS `article`;
CREATE TABLE IF NOT EXISTS `article` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ref_article_id` int(11) DEFAULT NULL,
  `reception_id` int(11) DEFAULT NULL,
  `statut_id` int(11) DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantite` int(11) DEFAULT NULL,
  `commentaire` longtext COLLATE utf8mb4_unicode_ci,
  `quantite_arecevoir` int(11) DEFAULT NULL,
  `quantite_collectee` int(11) DEFAULT NULL,
  `conform` tinyint(1) NOT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_23A0E6674516982` (`ref_article_id`),
  KEY `IDX_23A0E667C14DF52` (`reception_id`),
  KEY `IDX_23A0E66F6203804` (`statut_id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `article`
--

INSERT INTO `article` (`id`, `ref_article_id`, `reception_id`, `statut_id`, `label`, `quantite`, `commentaire`, `quantite_arecevoir`, `quantite_collectee`, `conform`, `reference`) VALUES
(57, 826, 24, 172, 'bxvb', NULL, 'dd', NULL, NULL, 1, '20190320194038-0');

-- --------------------------------------------------------

--
-- Structure de la table `categorie_statut`
--

DROP TABLE IF EXISTS `categorie_statut`;
CREATE TABLE IF NOT EXISTS `categorie_statut` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=193 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `categorie_statut`
--

INSERT INTO `categorie_statut` (`id`, `nom`) VALUES
(187, 'article'),
(188, 'collecte'),
(189, 'demande'),
(190, 'livraison'),
(191, 'preparation'),
(192, 'reception');

-- --------------------------------------------------------

--
-- Structure de la table `category_type`
--

DROP TABLE IF EXISTS `category_type`;
CREATE TABLE IF NOT EXISTS `category_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `category_type`
--

INSERT INTO `category_type` (`id`, `label`) VALUES
(29, 'référence article');

-- --------------------------------------------------------

--
-- Structure de la table `champs_libre`
--

DROP TABLE IF EXISTS `champs_libre`;
CREATE TABLE IF NOT EXISTS `champs_libre` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id` int(11) DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `typage` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `default_value` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_A061547BC54C8C93` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `collecte`
--

DROP TABLE IF EXISTS `collecte`;
CREATE TABLE IF NOT EXISTS `collecte` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `point_collecte_id` int(11) DEFAULT NULL,
  `demandeur_id` int(11) DEFAULT NULL,
  `statut_id` int(11) DEFAULT NULL,
  `numero` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `objet` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `commentaire` longtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `IDX_55AE4A3D47D5A513` (`point_collecte_id`),
  KEY `IDX_55AE4A3D95A6EE59` (`demandeur_id`),
  KEY `IDX_55AE4A3DF6203804` (`statut_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `collecte_article`
--

DROP TABLE IF EXISTS `collecte_article`;
CREATE TABLE IF NOT EXISTS `collecte_article` (
  `collecte_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  PRIMARY KEY (`collecte_id`,`article_id`),
  KEY `IDX_5B24B3A5710A9AC6` (`collecte_id`),
  KEY `IDX_5B24B3A57294869C` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `demande`
--

DROP TABLE IF EXISTS `demande`;
CREATE TABLE IF NOT EXISTS `demande` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `destination_id` int(11) DEFAULT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `preparation_id` int(11) DEFAULT NULL,
  `livraison_id` int(11) DEFAULT NULL,
  `statut_id` int(11) DEFAULT NULL,
  `numero` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_2694D7A5816C6140` (`destination_id`),
  KEY `IDX_2694D7A5FB88E14F` (`utilisateur_id`),
  KEY `IDX_2694D7A53DD9B8BA` (`preparation_id`),
  KEY `IDX_2694D7A58E54FB25` (`livraison_id`),
  KEY `IDX_2694D7A5F6203804` (`statut_id`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `demande`
--

INSERT INTO `demande` (`id`, `destination_id`, `utilisateur_id`, `preparation_id`, `livraison_id`, `statut_id`, `numero`, `date`) VALUES
(40, 1421, 38, NULL, 3, 180, 'D-20190320172153', '2019-03-20 17:21:53'),
(41, 1418, 5, NULL, 4, 180, 'D-20190320172524', '2019-03-20 17:25:24'),
(42, 1418, 5, NULL, NULL, 178, 'D-20190320172613', '2019-03-20 17:26:13'),
(43, 1418, 4, NULL, NULL, 178, 'D-20190320173413', '2019-03-20 17:34:13'),
(44, 1420, 4, NULL, NULL, 178, 'D-20190320173647', '2019-03-20 17:36:47'),
(45, 1431, 38, NULL, NULL, 178, 'D-20190320164339', '2019-03-20 16:43:39'),
(46, 1432, 38, NULL, NULL, 178, 'D-20190320164615', '2019-03-20 16:46:15'),
(47, 1418, 38, 6, NULL, 178, 'D-20190320173347', '2019-03-20 17:33:47'),
(48, 1430, 38, 7, NULL, 178, 'D-20190320180743', '2019-03-20 18:07:43'),
(49, 1418, 38, NULL, NULL, 177, 'D-20190320182602', '2019-03-20 18:26:02'),
(50, 1436, 38, NULL, NULL, 177, 'D-20190320191144', '2019-03-20 19:11:44'),
(51, 1418, 38, NULL, NULL, 177, 'D-20190320192916', '2019-03-20 19:29:16');

-- --------------------------------------------------------

--
-- Structure de la table `emplacement`
--

DROP TABLE IF EXISTS `emplacement`;
CREATE TABLE IF NOT EXISTS `emplacement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1470 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `emplacement`
--

INSERT INTO `emplacement` (`id`, `label`, `description`) VALUES
(1418, 'SAS 4101 BASEMENT E16', 'Gestion KANBAN - Flux CSP'),
(1419, 'SAS 4102 BASEMENT B21', 'Gestion KANBAN - Flux CSP'),
(1420, 'SAS BHT N1', 'Gestion KANBAN - Flux CSP'),
(1421, 'SAS BHT N1 B5', 'Gestion KANBAN - Flux CSP'),
(1422, 'SAS BHT N3', 'Gestion KANBAN - Flux CSP'),
(1423, 'SAS BHT BASEMENT 14L', 'Gestion KANBAN - Flux CSP'),
(1424, 'SAS BOC / BCA C148', 'Gestion KANBAN - Flux CSP'),
(1425, 'SAS BAT41.07 N0 SAS 2', 'Gestion KANBAN - Flux CSP'),
(1426, 'SAS PFP RDC C1425', 'Gestion KANBAN - Flux CSP'),
(1427, 'Stock CSP', 'Gestion KANBAN - Flux CSP'),
(1428, 'Stock Cible / slug', 'Gestion KANBAN - Flux CSP'),
(1429, 'Ensacheuses', 'Gestion KANBAN - Flux CSP'),
(1430, 'Zone sous-traitance', 'Retrait et Collecte - Flux Scilicium'),
(1431, 'Casiers', 'Retrait et Collecte - Flux Scilicium'),
(1432, 'Mise au stockage', 'Retrait et Collecte - Flux Scilicium'),
(1433, 'Stock interne', 'Retrait et Collecte - Flux Scilicium'),
(1434, 'stock externe', 'Retrait et Collecte - Flux Scilicium'),
(1435, 'stock silicium', 'Retrait et Collecte - Flux Scilicium'),
(1436, 'Recyclage', 'Retrait et Collecte - Flux Scilicium'),
(1437, 'Départ sous-traitance', 'Retrait et Collecte - Flux Scilicium'),
(1438, 'Rives', 'Retrait et Collecte - Flux Scilicium'),
(1439, 'SAS 41', 'Retrait et Collecte - Flux Scilicium'),
(1440, 'Etagère 200 BH', 'Retrait et Collecte - Flux Scilicium'),
(1441, 'SAS BHT', 'Retrait et Collecte - Flux Scilicium'),
(1442, 'Labo 40.06', 'Retrait et Collecte - Flux Scilicium'),
(1443, 'Temoins 200', 'Retrait et Collecte - Flux Scilicium'),
(1444, 'Etagère IL1000A', 'Retrait et Collecte - Flux Scilicium'),
(1445, 'Etagère lots 200', 'Retrait et Collecte - Flux Scilicium'),
(1446, 'Entrées 300', 'Retrait et Collecte - Flux Scilicium'),
(1447, 'SAS Silicum', 'Retrait et Collecte - Flux Scilicium'),
(1448, 'Gare LBB BHT', 'Retrait et Collecte - Flux Scilicium'),
(1449, 'Hauvent', 'Retrait et Collecte - Flux Scilicium'),
(1450, 'Rives', 'Retrait et Collecte - Flux Scilicium'),
(1451, 'Stock silicium', 'Retrait et Collecte - Flux Scilicium'),
(1452, '41.23 Etagère Litho (entrée & sortie)', 'Retrait et Collecte - Flux PDT'),
(1453, '41.23 Etagère Gravure (entrée & sortie)', 'Retrait et Collecte - Flux PDT'),
(1454, '41.23 Etagère Metro (entrée & sortie)', 'Retrait et Collecte - Flux PDT'),
(1455, '41.26 Etagère collecte (entrée & sortie)', 'Retrait et Collecte - Flux PDT'),
(1456, 'Stock PDT Etagère retrait', 'Retrait et Collecte - Flux PDT'),
(1457, 'Stock PDT Etagère sortie', 'Retrait et Collecte - Flux PDT'),
(1458, 'BHT N1 Etagère entrée & sortie', 'Retrait et Collecte - Flux PDT'),
(1459, 'Stock pompe', 'Retrait et Collecte - Flux PDT'),
(1460, 'Kit gravure Plot F8', 'Retrait et Collecte - Flux PDT'),
(1461, 'Kit BHT', 'Retrait et Collecte - Flux PDT'),
(1462, 'BHT plot 22J1', 'Retrait et Collecte - Flux PDT'),
(1463, 'BHT N1 Sas kit', 'Retrait et Collecte - Flux PDT'),
(1464, 'Stock grillagé', 'Retrait et Collecte - Flux PDT'),
(1465, '40.17 stock PDT hors SB', 'Retrait et Collecte - Flux PDT'),
(1466, 'Haut vent', 'Retrait et Collecte - Flux PDT'),
(1467, 'Stock M26', 'Retrait et Collecte - Flux PDT'),
(1468, 'Stock 33.03', 'Retrait et Collecte - Flux Mobilier'),
(1469, 'Stock Rives', 'Retrait et Collecte - Flux Mobilier');

-- --------------------------------------------------------

--
-- Structure de la table `fournisseur`
--

DROP TABLE IF EXISTS `fournisseur`;
CREATE TABLE IF NOT EXISTS `fournisseur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code_reference` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_369ECA322E6312B` (`code_reference`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `fournisseur`
--

INSERT INTO `fournisseur` (`id`, `code_reference`, `nom`) VALUES
(11, 'LL', 'SFR'),
(12, 'AMA', 'Amazon');

-- --------------------------------------------------------

--
-- Structure de la table `ligne_article`
--

DROP TABLE IF EXISTS `ligne_article`;
CREATE TABLE IF NOT EXISTS `ligne_article` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reference_id` int(11) DEFAULT NULL,
  `demande_id` int(11) DEFAULT NULL,
  `quantite` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_9DA3305F1645DEA9` (`reference_id`),
  KEY `IDX_9DA3305F80E95E18` (`demande_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `ligne_article`
--

INSERT INTO `ligne_article` (`id`, `reference_id`, `demande_id`, `quantite`) VALUES
(22, 831, 47, 1),
(23, 825, 48, 1),
(24, 825, 49, 2),
(25, 827, 49, 7),
(26, 831, 49, 2),
(27, 839, 49, 22);

-- --------------------------------------------------------

--
-- Structure de la table `livraison`
--

DROP TABLE IF EXISTS `livraison`;
CREATE TABLE IF NOT EXISTS `livraison` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `destination_id` int(11) DEFAULT NULL,
  `statut_id` int(11) DEFAULT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `numero` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `preparation_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_A60C9F1F816C6140` (`destination_id`),
  KEY `IDX_A60C9F1FF6203804` (`statut_id`),
  KEY `IDX_A60C9F1FFB88E14F` (`utilisateur_id`),
  KEY `IDX_A60C9F1F3DD9B8BA` (`preparation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `livraison`
--

INSERT INTO `livraison` (`id`, `destination_id`, `statut_id`, `utilisateur_id`, `numero`, `date`, `preparation_id`) VALUES
(3, NULL, 182, 38, 'L-20190320205551', '2019-03-20 20:55:51', 6),
(4, NULL, 182, 38, 'L-20190320222524', '2019-03-20 22:25:24', 7);

-- --------------------------------------------------------

--
-- Structure de la table `migration_versions`
--

DROP TABLE IF EXISTS `migration_versions`;
CREATE TABLE IF NOT EXISTS `migration_versions` (
  `version` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL,
  `executed_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `mouvement`
--

DROP TABLE IF EXISTS `mouvement`;
CREATE TABLE IF NOT EXISTS `mouvement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emplacement_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `link_id` int(11) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_5B51FC3EADA40271` (`link_id`),
  KEY `IDX_5B51FC3EC4598A51` (`emplacement_id`),
  KEY `IDX_5B51FC3EA76ED395` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `mouvement_article`
--

DROP TABLE IF EXISTS `mouvement_article`;
CREATE TABLE IF NOT EXISTS `mouvement_article` (
  `mouvement_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  PRIMARY KEY (`mouvement_id`,`article_id`),
  KEY `IDX_CDFADB71ECD1C222` (`mouvement_id`),
  KEY `IDX_CDFADB717294869C` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `preparation`
--

DROP TABLE IF EXISTS `preparation`;
CREATE TABLE IF NOT EXISTS `preparation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `statut_id` int(11) DEFAULT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `numero` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_F9F0AAF4F6203804` (`statut_id`),
  KEY `IDX_F9F0AAF4FB88E14F` (`utilisateur_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `preparation`
--

INSERT INTO `preparation` (`id`, `statut_id`, `utilisateur_id`, `date`, `numero`) VALUES
(6, 184, 28, '2019-03-20 17:55:29', 'P-20190320175529'),
(7, 184, 38, '2019-03-20 21:43:44', 'P-20190320214344');

-- --------------------------------------------------------

--
-- Structure de la table `preparation_article`
--

DROP TABLE IF EXISTS `preparation_article`;
CREATE TABLE IF NOT EXISTS `preparation_article` (
  `preparation_id` int(11) NOT NULL,
  `article_id` int(11) NOT NULL,
  PRIMARY KEY (`preparation_id`,`article_id`),
  KEY `IDX_BE3E52BE3DD9B8BA` (`preparation_id`),
  KEY `IDX_BE3E52BE7294869C` (`article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `reception`
--

DROP TABLE IF EXISTS `reception`;
CREATE TABLE IF NOT EXISTS `reception` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fournisseur_id` int(11) DEFAULT NULL,
  `utilisateur_id` int(11) DEFAULT NULL,
  `statut_id` int(11) DEFAULT NULL,
  `commentaire` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `numero_reception` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_attendu` datetime DEFAULT NULL,
  `date_reception` datetime DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_50D6852F670C757F` (`fournisseur_id`),
  KEY `IDX_50D6852FFB88E14F` (`utilisateur_id`),
  KEY `IDX_50D6852FF6203804` (`statut_id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `reception`
--

INSERT INTO `reception` (`id`, `fournisseur_id`, `utilisateur_id`, `statut_id`, `commentaire`, `date`, `numero_reception`, `date_attendu`, `date_reception`, `reference`) VALUES
(24, 11, 38, 186, 'dsgfvs', '2019-03-20 00:00:00', 'R190320-185018', '2019-03-21 00:00:00', NULL, 'dfvg'),
(25, 11, 38, 185, '', '2019-03-20 20:17:24', 'R190320-201724', '2019-03-28 00:00:00', NULL, ''),
(26, 11, 38, 185, '', '2019-03-21 00:00:00', 'R190320-202322', '2019-03-21 00:00:00', NULL, ''),
(27, 11, 38, 185, '', '2019-03-20 20:24:23', 'R190320-202423', '2019-03-20 20:24:23', NULL, ''),
(28, 11, 38, 185, '', '2019-03-20 20:25:26', 'R190320-202526', '2019-03-20 20:25:26', NULL, ''),
(29, 11, 38, 185, '', '2019-03-20 20:27:15', 'R190320-202715', '2019-03-20 20:27:15', NULL, ''),
(30, 11, 38, 185, '', '2019-03-20 20:28:31', 'R190320-202831', '2019-03-20 20:28:31', NULL, ''),
(31, 11, 38, 185, '', '2019-03-20 20:30:57', 'R190320-203057', '2019-03-20 20:30:57', NULL, ''),
(32, 11, 38, 185, '', '2019-03-20 20:32:03', 'R190320-203203', '2019-03-20 20:32:03', NULL, ''),
(33, 12, 38, 185, '', '2019-03-21 14:34:07', 'R190321-143407', '2019-03-21 14:34:07', NULL, '');

-- --------------------------------------------------------

--
-- Structure de la table `reference_article`
--

DROP TABLE IF EXISTS `reference_article`;
CREATE TABLE IF NOT EXISTS `reference_article` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `libelle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_article` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `custom` json DEFAULT NULL,
  `quantite_disponible` int(11) DEFAULT NULL,
  `quantite_reservee` int(11) DEFAULT NULL,
  `quantite_stock` int(11) DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_54AABCEAEA34913` (`reference`),
  KEY `IDX_54AABCEC54C8C93` (`type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=981 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `reference_article`
--

INSERT INTO `reference_article` (`id`, `libelle`, `photo_article`, `reference`, `custom`, `quantite_disponible`, `quantite_reservee`, `quantite_stock`, `type_id`) VALUES
(825, ' SILICIUM_P_MONITOR_CZ_<100>_525UM_14-20OHM', NULL, 'SIL_100_001', NULL, 106, 3, 106, NULL),
(826, '	SIL_N+_MONITOR_<111>_525UM_1-4MILLIOHM_TTV<12UM', NULL, 'SIL_100_002', NULL, 33, NULL, 70, NULL),
(827, '	SILCIUM_P+_MONITOR_CZ_<100>_525UM_0,01-0,02Ohms', NULL, 'SIL_100_004 ', NULL, 33, 7, 124, NULL),
(828, '	SIL N+<100>525µm 0.001-0.004Ohms', NULL, 'SIL_100_005A', NULL, 33, NULL, 130, NULL),
(829, '	SI P/B<100> 515 µm 10-20 Ohms DSP', NULL, 'SIL_100_007', NULL, 33, NULL, 25, NULL),
(830, '	SI_100_SOI_340_400NM', NULL, 'SIL_100_008', NULL, 33, NULL, 17, NULL),
(831, '	SILICIUM_P_MONITOR_CZ_<110>_725UM_6-12OHM_TTV<5UM', NULL, 'SIL_200_001', NULL, 33, 3, 46, NULL),
(832, '	SIL_P_MONITOR_CZ_<100>_550UM_5-20OHM_DSP_TTV<2.5UM', NULL, 'SIL_200_002', NULL, 33, NULL, 126, NULL),
(833, '	SILIC_P_MONITOR_CZ_<100>_550UM_5-20OHM_DSP_TTV<1UM', NULL, 'SIL_200_003', NULL, 33, NULL, 807, NULL),
(834, '	SILICIUM_P_MONITOR_CZ_<100>_725UM_1-50OHM', NULL, 'SIL_200_005', NULL, 33, NULL, 2052, NULL),
(835, '	SILICIUM_P_PRIME_CZ_<100>_725UM_5-10OHM', NULL, 'SIL_200_006', NULL, 33, NULL, 1138, NULL),
(836, '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE1_MINI-660UM', NULL, 'SIL_200_008', NULL, 33, NULL, 92, NULL),
(837, '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE2_MINI-660UM', NULL, 'SIL_200_009', NULL, 33, NULL, 183, NULL),
(838, '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE3_MINI-630UM', NULL, 'SIL_200_010', NULL, 33, NULL, 1642, NULL),
(839, ' STOCK RIVES	SILICIUM_RECYCLE-CONTAMINE-CU_GRADE1_MINI-660UM', NULL, 'SIL_200_012', NULL, 33, 22, 700, NULL),
(840, '	SILICIUM_RECYCLE-CONTAMINE-CU_GRADE2_MINI-660UM', NULL, 'SIL_200_013', NULL, 33, NULL, 0, NULL),
(841, '	SILICIUM_RECYCLE-CONTAMINE-CU_GRADE3_MINI-630UM', NULL, 'SIL_200_014', NULL, 33, NULL, 0, NULL),
(842, '	SILIC_P_MONITOR_CZ_<100>_725UM_5-20OHM_DSP_TTV<1UM', NULL, 'SIL_200_015', NULL, 33, NULL, 668, NULL),
(843, '	SIL_P+_FR_MONITOR_CZ_<100>_725UM_0.01-0.02OHM_DSP', NULL, 'SIL_200_017', NULL, 33, NULL, 1029, NULL),
(844, '	SILICIUM_P-_HR_PRIME_CZ_<100>_725UM_1000-99999OHM', NULL, 'SIL_200_018', NULL, 33, NULL, 581, NULL),
(845, '	SIL_P_MONITOR_ULTRAFLAT_CZ_<100>_725UM_10000-50.000 ohm_DSP', NULL, 'SIL_200_019', NULL, 33, NULL, 120, NULL),
(846, '	SILICIUM_N_PRIME_CZ_<100>_3-6OHM_725UM', NULL, 'SIL_200_024', NULL, 33, NULL, 352, NULL),
(847, '	SILICIUM_P_MONITOR_CZ_<111>_725UM_6-12OHM', NULL, 'SIL_200_026', NULL, 33, NULL, 396, NULL),
(848, '	SILIC_N+_FR_MONITOR_CZ_<100>_725UM_<3MILLIOHM_DSP', NULL, 'SIL_200_029A', NULL, 33, NULL, 251, NULL),
(849, '	SILICIUM_N_BSOI_CZ_<100>_25000NM-4000NM', NULL, 'SIL_200_030A', NULL, 33, NULL, 20, NULL),
(850, '	SILICIUM_P_BSOI_CZ_<100>_20000NM-2000NM', NULL, 'SIL_200_031', NULL, 33, NULL, 5, NULL),
(851, '	SOI_MONITOR_70NM-145NM_P_<100>_725UM_8.5-11.5OHM', NULL, 'SIL_200_032', NULL, 33, NULL, 82, NULL),
(852, '	SOI_PRIME_70NM-145NM_P_CZ_<100>_725UM_8.5-11.5OHM', NULL, 'SIL_200_033', NULL, 33, NULL, 23, NULL),
(853, '	SOI_PRIME_340NM-2000NM_P_<100>_725UM_8.5-11.5OHM', NULL, 'SIL_200_034', NULL, 33, NULL, 549, NULL),
(854, '	SOI_PRIME_160NM-400NM_P-_HR_<100>_725UM_>1000OHM', NULL, 'SIL_200_035A', NULL, 33, NULL, 130, NULL),
(855, '	SOI_PRIME_205NM-400NM_P_CZ_<100>_725UM_8.5-11.5OHM', NULL, 'SIL_200_037', NULL, 33, NULL, 103, NULL),
(856, '	SOI_PRIME_100NM-200NM_P_CZ_<100>_725UM_8.5-11.5OHM', NULL, 'SIL_200_039', NULL, 33, NULL, 59, NULL),
(857, '	SOI_PRIM_400NM-1000NM_P_CZ_<100>_725UM_8.5-11.5OHM', NULL, 'SIL_200_040', NULL, 33, NULL, 147, NULL),
(858, '	SOI_PRIM_400NM-2000NM_P_CZ_<100>_725UM_8.5-11.5OHM', NULL, 'SIL_200_041', NULL, 33, NULL, 73, NULL),
(859, ' STOCK RIVES	SOI_PRIME_P_20%_145A-1450A_CZ_<100>_725UM', NULL, 'SIL_200_042', NULL, 33, NULL, 23, NULL),
(860, ' STOCK RIVES	SOI_PRIME_P_30%_120A-1450A_CZ_<100>_725UM', NULL, 'SIL_200_043', NULL, 33, NULL, 3, NULL),
(861, ' STOCK RIVES	SOI_PRIME_P_40%_90A-1450A_CZ_<100>_725UM', NULL, 'SIL_200_044', NULL, 33, NULL, 9, NULL),
(862, '	BSOI_PRIME_P_12000NM-500NM_CZ_<100>_725UM', NULL, 'SIL_200_046A', NULL, 33, NULL, 32, NULL),
(863, '	BSOI_PRIME_P_27000NM-500NM_CZ_<100>_725UM', NULL, 'SIL_200_047A', NULL, 33, NULL, 51, NULL),
(864, '	VERRE_BOROFLOAT33_500UM_DSP_TTV<5UM_MARQUE', NULL, 'SIL_200_050', NULL, 33, NULL, 151, NULL),
(865, '	SILICIUM_P_MONITOR_CZ_<111>_1000UM_3-20OHM', NULL, 'SIL_200_060A', NULL, 33, NULL, 901, NULL),
(866, ' UNIQUEMENT POUR DUPRE	SOI_PRIME_220NM-2000NM_P_CZ_<100>_725UM_14-18.9OHM', NULL, 'SIL_200_062', NULL, 33, NULL, 39, NULL),
(867, '	VERRE_Eagle XG_700UM_DSP_MARQUE', NULL, 'SIL_200_078', NULL, 33, NULL, 358, NULL),
(868, '	VERRE_Eagle XG_700UM_DSP_MARQUE', NULL, 'SIL_200_078A', NULL, 33, NULL, 49, NULL),
(869, '	SILICIUM_P_MONITOR-2-SUP-QUAL_<100>_725UM_1-50OHM', NULL, 'SIL_200_079A', NULL, 33, NULL, 925, NULL),
(870, '	SILICIUM_N+_ANTIMONY_CZ_<100>_725UM_0,01-0,02OHM', NULL, 'SIL_200_085', NULL, 33, NULL, 20, NULL),
(871, '	SILICIUM_P_MONITOR_CZ_<111>_1000UM_1-1500OHM', NULL, 'SIL_200_086A', NULL, 33, NULL, 5, NULL),
(872, '	SILIC_P+_FR_MONITOR_CZ_<100>_725UM - 0.01-0.02OHM', NULL, 'SIL_200_087', NULL, 33, NULL, 324, NULL),
(873, '	SILICIUM_P_MONITOR_CZ_<100>_1000UM_1-20OHM', NULL, 'SIL_200_088', NULL, 33, NULL, 7, NULL),
(874, '	SILICIUM_P_MONITOR_CZ_<100>_725UM_20-30OHM_TTV<3UM', NULL, 'SIL_200_090A', NULL, 33, NULL, 15, NULL),
(875, '	SIL_N++_RED-PH_MONITOR_<100>_725UM_10-20MILLIOHM', NULL, 'SIL_200_093A', NULL, 33, NULL, 22, NULL),
(876, '	BSOI_PRIME_18UM-1,5UM', NULL, 'SIL_200_097A', NULL, 33, NULL, 5, NULL),
(877, '	SIL_N+_RED_PH_MONITOR_<100>_1000UM_1.2-1.5MILLIOHM', NULL, 'SIL_200_099', NULL, 33, NULL, 136, NULL),
(878, '	SOI_PRIME_600NM', NULL, 'SIL_200_101A', NULL, 33, NULL, 30, NULL),
(879, '	SOI_PRIME_310NM-800NM_P_CZ_<100>_725UM_>750OHM', NULL, 'SIL_200_102A', NULL, 33, NULL, 177, NULL),
(880, '	BSOI_N_CZ_<100>_1.5UM-2UM_725UM_5-15OHM', NULL, 'SIL_200_104B', NULL, 33, NULL, 28, NULL),
(881, '	BSOI_N_CZ_<100>_0.5UM-100UM_725UM_5-15OHM', NULL, 'SIL_200_105A', NULL, 33, NULL, 81, NULL),
(882, '	BSOI_P+_<100>_1.5UM-10UM_<100>_725UM_0.01-0.025OHM', NULL, 'SIL_200_109A', NULL, 33, NULL, 25, NULL),
(883, '	SOI_PRIME_HR_220NM-2000NM+-100A_>750OHM', NULL, 'SIL_200_111A', NULL, 33, NULL, 169, NULL),
(884, '	SIL_N+_RED_PH_MONITOR_CZ_<100>_725UM_<1,6MILLIOHM', NULL, 'SIL_200_113', NULL, 33, NULL, 81, NULL),
(885, '	SIL_RECYCLE-NON-CONTAMINE_SUPPLY_GRADE1_MINI-660UM', NULL, 'SIL_200_115', NULL, 33, NULL, 35, NULL),
(886, '	SIL_RECYCLE-NON-CONTAMINE_SUPPLY_GRADE1_MINI-660UM', NULL, 'SIL_200_115SAS', NULL, 33, NULL, 50, NULL),
(887, '	SOI_PRIME_500-1000NM+/-100A _P-_HR_725UM 	', NULL, 'SIL_200_116', NULL, 33, NULL, 84, NULL),
(888, '	SILICIUM_P_MONITOR_CZ_<100>_725UM_1-5000 OHM ', NULL, 'SIL_200_117', NULL, 33, NULL, 1, NULL),
(889, '	SILICIUM_P_MONITOR_CZ_<100>_1000µm_5-20mOhm ', NULL, 'SIL_200_118', NULL, 33, NULL, 72, NULL),
(890, '	SIL_P_HR_PRIME_CZ_<100>_725UM_>1000/99999OHM/DSP ', NULL, 'SIL_200_120A', NULL, 33, NULL, 19, NULL),
(891, '	SIL_P_HR_PRIME_CZ_<100>_725UM_>1000/99999OHM/DSP ', NULL, 'SIL_200_120c', NULL, 33, NULL, 11, NULL),
(892, '	SILICIUM_P_EPI_7µm 	', NULL, 'SIL_200_121A', NULL, 33, NULL, 69, NULL),
(893, '	200MM-SILICIUM-N-5-25OHMS-DSP ET MESURE µPCF', NULL, 'SIL_200_124A', NULL, 33, NULL, 1, NULL),
(894, '	SILICIUM_P+_CZ_<100>_725UM_8-15MILLIOHM_POLY/LTO ', NULL, 'SIL_200_126A', NULL, 33, NULL, 53, NULL),
(895, '	SIL_N+_RED_PHOS_725µm_1.0-2.0 Mohm', NULL, 'SIL_200_127', NULL, 33, NULL, 556, NULL),
(896, '	SIL_N+_RED_PH_1000µm ', NULL, 'SIL_200_128', NULL, 33, NULL, 300, NULL),
(897, '	VERRE_EAGLE XG_500UM	', NULL, 'SIL_200_129', NULL, 33, NULL, 50, NULL),
(898, '	SIL_N+_725UM_As_3-7MILLIOHM	', NULL, 'SIL_200_130', NULL, 33, NULL, 49, NULL),
(899, '	SILICIUM P+<111> 1500µm 0.1-8 mohm	', NULL, 'SIL_200_133A', NULL, 33, NULL, 35, NULL),
(900, '	SIL 200MM TYPE <111> 1000µm <5mOhm Edge 32R20	', NULL, 'SIL_200_134A', NULL, 33, NULL, 20, NULL),
(901, '	SIL P<111> 1150µMCM 3000-8000OHMCM	', NULL, 'SIL_200_137A', NULL, 33, NULL, 33, NULL),
(902, '	SIL P <001>OFF Orient 0.4 miscut towards <111>	', NULL, 'SIL_200_138', NULL, 33, NULL, 100, NULL),
(903, '	WAF.RAW_200MM_BSOI_PP+_CZ_<100>_5UM_1UM_725UM_>100	', NULL, 'SIL_200_139', NULL, 33, NULL, 44, NULL),
(904, '	VERRE_BOROFLOAT33_500UM_DSP_TTV<3UM	', NULL, 'SIL_200_140A', NULL, 33, NULL, 82, NULL),
(905, '	SIL_N+_ARSENIC_<111>_1.5-5MOHM	', NULL, 'SIL_200_141A', NULL, 33, NULL, 395, NULL),
(906, '	BSOI_P_CZ_(100)_725UM_BOX2000NMLAYER-THICKNE-130UM', NULL, 'SIL_200_142A', NULL, 33, NULL, 25, NULL),
(907, '	BSOI_P_CZ_(100)_725UM_BOX1500NMLAYER-THICKNES-17UM	', NULL, 'SIL_200_143A', NULL, 33, NULL, 75, NULL),
(908, '	VERRE_200_BOROFLOAT_700µm	', NULL, 'SIL_200_146', NULL, 33, NULL, 27, NULL),
(909, '	SI 200 SOI N 1.5-2µm CZ<100> 725µm 5-10OHMS	', NULL, 'SIL_200_147A', NULL, 33, NULL, 75, NULL),
(910, '	SI 200 SOI P 1.5µm-17µm<100> 725µm	', NULL, 'SIL_200_149A', NULL, 33, NULL, 74, NULL),
(911, '	SI 200 SOI P 1.5µm-3µm <100> 725µm 5-10 OHMS	', NULL, 'SIL_200_150A', NULL, 33, NULL, 22, NULL),
(912, '	200mm_SILICIUM_P_MONITOR_CZ<100>550µm_5-20_OHM SSP	', NULL, 'SIL_200_151A', NULL, 33, NULL, 13, NULL),
(913, '	200mm VERRE BOROFLOAT33 725µm DSP TTV < 5µm MARQUE	', NULL, 'SIL_200_152', NULL, 33, NULL, 319, NULL),
(914, '	200mm VERRE BOROFLOAT33 725µm DSP TTV < 5µm MARQUE	', NULL, 'SIL_200_152A', NULL, 33, NULL, 150, NULL),
(915, '	SI_200_SOI_60-4µm_CZ_724µm_0.01-0.02_OHM.CM	', NULL, 'SIL_200_153', NULL, 33, NULL, 42, NULL),
(916, '	SIL_200_PHOSPHORE<100>725µm_10.61-11.62-OHMS*CM	', NULL, 'SIL_200_156A', NULL, 33, NULL, 25, NULL),
(917, '	VERRE BOROFLOAT33-1000µm TTV<3µm', NULL, 'SIL_200_157A', NULL, 33, NULL, 23, NULL),
(918, '	200MM_(111)_1000µm>6§KOHM.CM	', NULL, 'SIL_200_158A', NULL, 33, NULL, 47, NULL),
(919, '	200MM_(111)_725µm>5KOHM.CM', NULL, 'SIL_200_159A', NULL, 33, NULL, 14, NULL),
(920, '	200MMSOI1500NM-17µm 568µm 5-15 OHMCM', NULL, 'SIL_200_160A', NULL, 33, NULL, 64, NULL),
(921, '	SOI_P/B(100)625µm_0.5-50µm_N/P(100)10-20_OHMCM', NULL, 'SIL_200_161', NULL, 33, NULL, 25, NULL),
(922, '	SOI_P/B(100)625µm_0.5-100µm_N/P(100)10-20_OHMCM	', NULL, 'SIL_200_162', NULL, 33, NULL, 50, NULL),
(923, '	SIL_N+_ARSENIC_<111>_1.5-5OHM_DEPOT_ALN	', NULL, 'SIL_200_163', NULL, 33, NULL, 200, NULL),
(924, '	SIL_(100)725µm 1-2OHM*CM TTV3.5µm	', NULL, 'SIL_200_164', NULL, 33, NULL, 3, NULL),
(925, '	SOI 90µm-1µm_725µm_725-375_OHMS	', NULL, 'SIL_200_165', NULL, 33, NULL, 21, NULL),
(926, '	SOI 1µm BOX 1µm	', NULL, 'SIL_200_166', NULL, 33, NULL, 11, NULL),
(927, '	SOI 2µm-1µm_725_1-30_OHMS	', NULL, 'SIL_200_167', NULL, 33, NULL, 9, NULL),
(928, '	SOI 145NM-1000NM_725µm	', NULL, 'SIL_200_168', NULL, 33, NULL, 14, NULL),
(929, '	SOI 1000NM-1000NM_725µm	', NULL, 'SIL_200_169', NULL, 33, NULL, 4, NULL),
(930, '	SOI 60µm-1µm_CZ_(100)725µm_750-375OHM	', NULL, 'SIL_200_170', NULL, 33, NULL, 15, NULL),
(931, '	SOI 160NM-400NM_725µm_HR	', NULL, 'SIL_200_171', NULL, 33, NULL, 2, NULL),
(932, '	SOI 135µm-1µm_725µm_725-375_OHMS	', NULL, 'SIL_200_172', NULL, 33, NULL, 22, NULL),
(933, '	VERRE_BOROFLOAT33_700UM_DSP_TTV<5UM	', NULL, 'SIL_300_101', NULL, 33, NULL, 93, NULL),
(934, '	SILICIUM_P_MONITOR_CZ_<100>_775UM_1-100OHM_DSP	', NULL, 'SIL_300_102', NULL, 33, NULL, 1289, NULL),
(935, '	SILICIUM_P_MONITOR_CZ_<100>_775UM_1-100OHM_DSP	', NULL, 'SIL_300_102Sas', NULL, 33, NULL, 100, NULL),
(936, '	SILICIUM_P_MONITOR_CZ_<100>_775UM_1-100OHM_DSP	', NULL, 'SIL_300_102 STOCK RIVES', NULL, 33, NULL, 2700, NULL),
(937, '	SILICIUM_P_EXTRA-PRIME_CZ_<100>_775UM_10-20OHM_DSP	', NULL, 'SIL_300_104', NULL, 33, NULL, 100, NULL),
(938, '	SILICIUM_P_PRIME_CZ_<100>_775UM_10-20OHM_DSP	', NULL, 'SIL_300_105 STOCK RIVES', NULL, 33, NULL, 450, NULL),
(939, '	SILICIUM_P_PRIME_CZ_<100>_775UM_10-20OHM_DSP	', NULL, 'SIL_300_105', NULL, 33, NULL, 126, NULL),
(940, '	SILICIUM_N_PRIME_CZ_<100>_775UM_20-60OHM_DSP	', NULL, 'SIL_300_106', NULL, 33, NULL, 253, NULL),
(941, '	SILICIUM_N_PRIME_CZ_<100>_775UM_20-60OHM_DSP	', NULL, 'SIL_300_106 STOCK RIVES', NULL, 33, NULL, 175, NULL),
(942, '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE_1_MINI-700UM	', NULL, 'SIL_300_109', NULL, 33, NULL, 293, NULL),
(943, '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE_1_MINI-700UM	', NULL, 'SIL_300_109Sas', NULL, 33, NULL, 50, NULL),
(944, '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE_1_MINI-700UM	', NULL, 'SIL_300_109 STOCK RIVES', NULL, 33, NULL, 1525, NULL),
(945, '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE_2_MINI-700UM	', NULL, 'SIL_300_110 STOCK RIVES', NULL, 33, NULL, 500, NULL),
(946, '	SILICIUM_RECYCLE-NON-CONTAMINE_GRADE_2_MINI-700UM	', NULL, 'SIL_300_110', NULL, 33, NULL, 242, NULL),
(947, '	SOI_PRIME_12NM-25NM_P_CZ_<100>_775UM_10-15OHM_0°OF	', NULL, 'SIL_300_117A', NULL, 33, NULL, 26, NULL),
(948, '	SI_SOI_PRIME_88NM-145NM_P_CZ_(100)_775µm_9-15OHM	', NULL, 'SIL_300_121', NULL, 33, NULL, 35, NULL),
(949, '	VERRE_BOROFLOAT33_500UM_DSP_TTV<5UM	', NULL, 'SIL_300_122 STOCK RIVES', NULL, 33, NULL, 124, NULL),
(950, '	VERRE_BOROFLOAT33_500UM_DSP_TTV<5UM	', NULL, 'SIL_300_122', NULL, 33, NULL, 25, NULL),
(951, '	SOI_PRIME_16NM-145NM_P_CZ_<100>_775UM_10-15OHM_0°', NULL, 'SIL_300_128', NULL, 33, NULL, 468, NULL),
(952, '	300_143 RIVES	', NULL, 'SIL_300_143 STOCK RIVES', NULL, 33, NULL, 50, NULL),
(953, '	300_143	', NULL, 'SIL_300_143', NULL, 33, NULL, 0, NULL),
(954, '	SOI_PRIME_12NM-145NM_P_CZ_<100>_775UM_10-15OHM	', NULL, 'SIL_300_144', NULL, 33, NULL, 3, NULL),
(955, '	SILICIUM_P+_PRIME_FR_<100>_775UM_1,08-1,8OHM_DES4°	', NULL, 'SIL_300_147A', NULL, 33, NULL, 136, NULL),
(956, '	SOI_PRIM_14-25NM_P_<100>_775UM_0°OF_10-15OHM_Ra0.2	', NULL, 'SIL_300_148A', NULL, 33, NULL, 52, NULL),
(957, '	SOI_PRIME_310NM-720NM	', NULL, 'SIL_300_149A', NULL, 33, NULL, 88, NULL),
(958, '	SOI_MON_14-25NM_P_<100>_775UM_0°OF_10-15OHM_Ra0.08	', NULL, 'SIL_300_150', NULL, 33, NULL, 1, NULL),
(959, '	CARRIER-ZONE-BOND_3MM	', NULL, 'SIL_300_151', NULL, 33, NULL, 70, NULL),
(960, '	UTBOX15_PRO_PRIME_12-15NM_LOW-Ra', NULL, 'SIL_300_152A', NULL, 33, NULL, 0, NULL),
(961, '	UTBOX15_PRO_PRIME_12-15NM_LOW-Ra', NULL, 'SIL_300_152A STOCK RIVES', NULL, 33, NULL, 100, NULL),
(962, '	SOI_MONITOR_14NM-20NM_10-15OHM	', NULL, 'SIL_300_153', NULL, 33, NULL, 3, NULL),
(963, '	300_155 RIVES	', NULL, 'SIL_300_155 STOCK RIVES', NULL, 33, NULL, 100, NULL),
(964, '	300_155	', NULL, 'SIL_300_155', NULL, 33, NULL, 75, NULL),
(965, '	SOI_P_<100>_10-15OHM 	', NULL, 'SIL_300_157', NULL, 33, NULL, 28, NULL),
(966, '	SOI_PRIME_220NM-2000NM_P_CZ_<100>_775UM 	', NULL, 'SIL_300_158A', NULL, 33, NULL, 59, NULL),
(967, '	VERRE_EAGLE-XG_700UM_DSP_TTV<5UM	', NULL, 'SIL_300_159', NULL, 33, NULL, 178, NULL),
(968, '	SILICIUM P <100> 0.5° orientation ', NULL, 'SIL_300_160A', NULL, 33, NULL, 61, NULL),
(969, '	SOI_PRIME_15-20NM_P_<100>_775µm_9-15OHMS	', NULL, 'SIL_300_162', NULL, 33, NULL, 53, NULL),
(970, '	300MM-SOI-310-720NM	', NULL, 'SIL_300_167A', NULL, 33, NULL, 25, NULL),
(971, '	SIL MONITOR CZ(100)775µm 1-100OHM*CMSFQR 26*8MM ', NULL, 'SIL_300_168A', NULL, 33, NULL, 100, NULL),
(972, '	100_P_BORON_FZ(100)525um_1-5_ohm', NULL, 'SIL_100_009', NULL, 33, NULL, 57, NULL),
(973, '	100_P_BORON_FZ(100)525um_1-5_ohm_off-0,15	', NULL, 'SIL_100_010', NULL, 33, NULL, 25, NULL),
(974, '	100_N_PH_FZ(100)525um_1-5_ohm', NULL, 'SIL_100_011', NULL, 33, NULL, 75, NULL),
(975, 'na', NULL, 'SIL_300_163A', NULL, 33, NULL, 0, NULL),
(976, 'na', NULL, 'SIL_300_165A', NULL, 33, NULL, 0, NULL),
(977, 'na', NULL, 'SIL_300_166', NULL, 33, NULL, 0, NULL),
(978, '	WAF.RAW 200 M M RESERV_SILICIUM_P_MONITOR_B_111_725UM_1-100_OHM_TTV<4UM	', NULL, 'SIL_200_173', NULL, 33, NULL, 175, NULL),
(979, '	300mm soi 70-145nm	', NULL, 'SIL_300_174', NULL, 33, NULL, 50, NULL),
(980, '	300MM_SOI_PRIME_DECLASSE_88NM-145NM_P_CZ (100)_775UM_9-15 OHM	', NULL, 'SIL_300_121A', NULL, 33, NULL, 100, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `statut`
--

DROP TABLE IF EXISTS `statut`;
CREATE TABLE IF NOT EXISTS `statut` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categorie_id` int(11) DEFAULT NULL,
  `nom` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_E564F0BFBCF5E72D` (`categorie_id`)
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `statut`
--

INSERT INTO `statut` (`id`, `categorie_id`, `nom`) VALUES
(172, 187, 'actif'),
(173, 187, 'inactif'),
(174, 188, 'brouillon'),
(175, 188, 'à traiter'),
(176, 188, 'collecté'),
(177, 189, 'brouillon'),
(178, 189, 'à traiter'),
(179, 189, 'préparé'),
(180, 189, 'livré'),
(181, 190, 'à traiter'),
(182, 190, 'livré'),
(183, 191, 'à traiter'),
(184, 191, 'préparé'),
(185, 192, 'en attente de réception'),
(186, 192, 'réception partielle'),
(187, 192, 'réception totale'),
(188, 192, 'anomalie');

-- --------------------------------------------------------

--
-- Structure de la table `type`
--

DROP TABLE IF EXISTS `type`;
CREATE TABLE IF NOT EXISTS `type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_8CDE572912469DE2` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

DROP TABLE IF EXISTS `utilisateur`;
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `roles` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '(DC2Type:array)',
  `last_login` datetime DEFAULT NULL,
  `api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_1D1C63B3E7927C74` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`id`, `username`, `email`, `password`, `roles`, `last_login`, `api_key`) VALUES
(3, 'Jean Vivien', 'sicot.jv@live.com', '$2y$13$XrVLOGH7tDIWwdhdskKiO.Q9UTW2h6meERkFbKqQKdiqkw7Vbimay', 'a:1:{i:0;s:16:\"ROLE_SUPER_ADMIN\";}', '2019-03-20 17:23:30', '1e26f43d7f2275688673d6e37ea4982a'),
(4, 'benoit.coste@wiilog.fr', 'benoit.coste@wiilog.fr', '$2y$13$cTABvY.2U9CRjtTJzvjno./1S20HC8CQ5PSz6v6o3N1E3WGIjLzk2', 'a:1:{i:0;s:16:\"ROLE_SUPER_ADMIN\";}', '2019-03-20 17:37:17', NULL),
(5, 'Mehdi Boumahrou', 'mehdi.boumahrou@wiilog.fr', '$2y$13$ZPm3s8bbOFzXTL1zOUmJweIYi/nyH66llj8aX/8ZtlqE4zO48E3XW', 'a:1:{i:0;s:16:\"ROLE_SUPER_ADMIN\";}', '2019-03-20 17:37:38', NULL),
(25, 'Cindy', 'c.egloff@gt-logistics.fr', '$2y$13$9c3JSlO6qKi8hyu5HjdvpeopL/oHVNbO8PCLc.KuyqFG8WSfDXo8S', 'a:1:{i:0;s:16:\"ROLE_SUPER_ADMIN\";}', NULL, NULL),
(26, 'Jérémie', 'j.suzet@gt-logistics.fr', '$2y$13$i06vFuQEWoCO6RSylAiRw.RAtEUiyjmFw7a.qWPOSPW/TMiQ1Xvn.', 'a:1:{i:0;s:16:\"ROLE_SUPER_ADMIN\";}', NULL, NULL),
(27, 'Patrice', 'Patrice.NAL@cea.fr', '$2y$13$b7HKmF1TUz8TOPf7e95h4.LOP26S/UBoDsMkZYHteu6RiU4rbu4he', 'a:1:{i:0;s:16:\"ROLE_SUPER_ADMIN\";}', NULL, NULL),
(28, 'Christelle', 'christelle.verne-tournon@cea.fr', '$2y$13$7zx.JGsNZOyB6cauEdXaeeP5/5aLQDtWHKww2V1G42Qng7h5fC3Sy', 'a:1:{i:0;s:16:\"ROLE_SUPER_ADMIN\";}', NULL, NULL),
(38, 'cegaz', 'cecile.gazaniol@wiilog.fr', '$2y$13$/1hgdU.FKbzqeoD5/yVTbere10blGYW3y1apBoQw.7QfclPNPlh4.', 'a:1:{i:0;s:16:\"ROLE_SUPER_ADMIN\";}', '2019-03-21 15:26:44', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `valeur_champs_libre`
--

DROP TABLE IF EXISTS `valeur_champs_libre`;
CREATE TABLE IF NOT EXISTS `valeur_champs_libre` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `valeur` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `champ_libre_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_59F211AACC5E904E` (`champ_libre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `valeur_champs_libre_reference_article`
--

DROP TABLE IF EXISTS `valeur_champs_libre_reference_article`;
CREATE TABLE IF NOT EXISTS `valeur_champs_libre_reference_article` (
  `valeur_champs_libre_id` int(11) NOT NULL,
  `reference_article_id` int(11) NOT NULL,
  PRIMARY KEY (`valeur_champs_libre_id`,`reference_article_id`),
  KEY `IDX_44605CFDB2C4ADFF` (`valeur_champs_libre_id`),
  KEY `IDX_44605CFD268AB3D3` (`reference_article_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `alerte`
--
ALTER TABLE `alerte`
  ADD CONSTRAINT `FK_3AE753A7CF90D8D` FOREIGN KEY (`alerte_utilisateur_id`) REFERENCES `utilisateur` (`id`),
  ADD CONSTRAINT `FK_3AE753AF3208540` FOREIGN KEY (`alerte_ref_article_id`) REFERENCES `reference_article` (`id`);

--
-- Contraintes pour la table `article`
--
ALTER TABLE `article`
  ADD CONSTRAINT `FK_23A0E6674516982` FOREIGN KEY (`ref_article_id`) REFERENCES `reference_article` (`id`),
  ADD CONSTRAINT `FK_23A0E667C14DF52` FOREIGN KEY (`reception_id`) REFERENCES `reception` (`id`),
  ADD CONSTRAINT `FK_23A0E66F6203804` FOREIGN KEY (`statut_id`) REFERENCES `statut` (`id`);

--
-- Contraintes pour la table `champs_libre`
--
ALTER TABLE `champs_libre`
  ADD CONSTRAINT `FK_A061547BC54C8C93` FOREIGN KEY (`type_id`) REFERENCES `type` (`id`);

--
-- Contraintes pour la table `collecte`
--
ALTER TABLE `collecte`
  ADD CONSTRAINT `FK_55AE4A3D47D5A513` FOREIGN KEY (`point_collecte_id`) REFERENCES `emplacement` (`id`),
  ADD CONSTRAINT `FK_55AE4A3D95A6EE59` FOREIGN KEY (`demandeur_id`) REFERENCES `utilisateur` (`id`),
  ADD CONSTRAINT `FK_55AE4A3DF6203804` FOREIGN KEY (`statut_id`) REFERENCES `statut` (`id`);

--
-- Contraintes pour la table `collecte_article`
--
ALTER TABLE `collecte_article`
  ADD CONSTRAINT `FK_5B24B3A5710A9AC6` FOREIGN KEY (`collecte_id`) REFERENCES `collecte` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_5B24B3A57294869C` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `demande`
--
ALTER TABLE `demande`
  ADD CONSTRAINT `FK_2694D7A53DD9B8BA` FOREIGN KEY (`preparation_id`) REFERENCES `preparation` (`id`),
  ADD CONSTRAINT `FK_2694D7A5816C6140` FOREIGN KEY (`destination_id`) REFERENCES `emplacement` (`id`),
  ADD CONSTRAINT `FK_2694D7A58E54FB25` FOREIGN KEY (`livraison_id`) REFERENCES `livraison` (`id`),
  ADD CONSTRAINT `FK_2694D7A5F6203804` FOREIGN KEY (`statut_id`) REFERENCES `statut` (`id`),
  ADD CONSTRAINT `FK_2694D7A5FB88E14F` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`);

--
-- Contraintes pour la table `ligne_article`
--
ALTER TABLE `ligne_article`
  ADD CONSTRAINT `FK_9DA3305F1645DEA9` FOREIGN KEY (`reference_id`) REFERENCES `reference_article` (`id`),
  ADD CONSTRAINT `FK_9DA3305F80E95E18` FOREIGN KEY (`demande_id`) REFERENCES `demande` (`id`);

--
-- Contraintes pour la table `preparation`
--
ALTER TABLE `preparation`
  ADD CONSTRAINT `FK_F9F0AAF4F6203804` FOREIGN KEY (`statut_id`) REFERENCES `statut` (`id`),
  ADD CONSTRAINT `FK_F9F0AAF4FB88E14F` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur` (`id`);

--
-- Contraintes pour la table `preparation_article`
--
ALTER TABLE `preparation_article`
  ADD CONSTRAINT `FK_BE3E52BE3DD9B8BA` FOREIGN KEY (`preparation_id`) REFERENCES `preparation` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `FK_BE3E52BE7294869C` FOREIGN KEY (`article_id`) REFERENCES `article` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
