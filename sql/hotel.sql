-- ============================================================
-- Minabou Hotel — Schéma Base de Données MySQL (Enrichi)
-- Encodage : utf8mb4 | Moteur : InnoDB
-- ============================================================

CREATE DATABASE IF NOT EXISTS `minabou_hotel`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `minabou_hotel`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `parametres_systeme`;
DROP TABLE IF EXISTS `historique_occupation`;
DROP TABLE IF EXISTS `taches_menage`;
DROP TABLE IF EXISTS `factures`;
DROP TABLE IF EXISTS `commande_lignes`;
DROP TABLE IF EXISTS `commandes`;
DROP TABLE IF EXISTS `reservations`;
DROP TABLE IF EXISTS `menu_items`;
DROP TABLE IF EXISTS `menus_cuisine`;
DROP TABLE IF EXISTS `catalogue_services`;
DROP TABLE IF EXISTS `personnel`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `salles`;
DROP TABLE IF EXISTS `chambres`;
DROP TABLE IF EXISTS `types_chambres`;
DROP TABLE IF EXISTS `utilisateurs`;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------
-- 1. Utilisateurs (acteurs actifs)
-- ----------------------------
CREATE TABLE `utilisateurs` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `identifiant`  VARCHAR(50)  NOT NULL UNIQUE,
  `nom`          VARCHAR(100) NOT NULL,
  `prenom`       VARCHAR(100) NOT NULL,
  `email`        VARCHAR(150) DEFAULT NULL,
  `mot_de_passe` VARCHAR(255) NOT NULL,
  `role`         ENUM('admin','receptionniste') NOT NULL,
  `langue`       VARCHAR(10) NOT NULL DEFAULT 'fr',
  `theme`        VARCHAR(20) NOT NULL DEFAULT 'dark',
  `actif`        TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------
-- 2. Types de chambres
-- ----------------------------
CREATE TABLE `types_chambres` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `nom`         VARCHAR(100) NOT NULL,
  `description` TEXT,
  `prix_nuit`   DECIMAL(12,2) NOT NULL,
  `prix_sieste` DECIMAL(12,2) NOT NULL DEFAULT 10000.00,
  `capacite`    INT NOT NULL DEFAULT 2,
  `avantages`   TEXT COMMENT 'Avantages séparés par virgule',
  `photo`       VARCHAR(255) DEFAULT 'default_type.jpg',
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------
-- 3. Chambres
-- ----------------------------
CREATE TABLE `chambres` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `numero`           VARCHAR(20) NOT NULL UNIQUE,
  `id_type_chambre`  INT NOT NULL,
  `etage`            INT NOT NULL DEFAULT 1,
  `statut`           ENUM('libre','occupee','reservee','nettoyage','maintenance') NOT NULL DEFAULT 'libre',
  `description`      TEXT,
  `photo`            VARCHAR(255) DEFAULT 'chambre_default.jpg',
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_type_chambre`) REFERENCES `types_chambres`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------
-- 4. Salles de fête (Module Optionnel Premium)
-- ----------------------------
CREATE TABLE `salles` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `nom`            VARCHAR(100) NOT NULL,
  `capacite`       INT NOT NULL,
  `prix_location`  DECIMAL(12,2) NOT NULL,
  `description`    TEXT,
  `statut`         ENUM('libre','reservee') NOT NULL DEFAULT 'libre',
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------
-- 5. Clients
-- ----------------------------
CREATE TABLE `clients` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `nom`             VARCHAR(100) NOT NULL,
  `prenom`          VARCHAR(100) NOT NULL,
  `date_naissance`  DATE DEFAULT NULL,
  `lieu_naissance`  VARCHAR(150) DEFAULT NULL,
  `telephone`       VARCHAR(30),
  `email`           VARCHAR(150),
  `adresse`         TEXT,
  `nationalite`     VARCHAR(80),
  `piece_identite`  VARCHAR(100),
  `preferences`     TEXT,
  `nb_sejours`      INT NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------
-- 6. Personnel (cuisiniers, ménage, maintenance)
-- ----------------------------
CREATE TABLE `personnel` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `nom`         VARCHAR(100) NOT NULL,
  `prenom`      VARCHAR(100) NOT NULL,
  `telephone`   VARCHAR(30),
  `role`        ENUM('cuisinier','menage','autre') NOT NULL,
  `actif`       TINYINT(1) NOT NULL DEFAULT 1,
  `notes`       TEXT,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------
-- 7. Catalogue services
-- ----------------------------
CREATE TABLE `catalogue_services` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `nom`         VARCHAR(150) NOT NULL,
  `categorie`   ENUM('boisson','nourriture','spa','piscine','voiture','autre') NOT NULL,
  `description` TEXT,
  `prix`        DECIMAL(12,2) NOT NULL,
  `disponible`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------
-- 8. Menus cuisine (Module Optionnel Premium)
-- ----------------------------
CREATE TABLE `menus_cuisine` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `id_chef`          INT NOT NULL,
  `titre`            VARCHAR(200) NOT NULL,
  `description`      TEXT,
  `date_publication` DATE NOT NULL,
  `disponible`       TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_chef`) REFERENCES `personnel`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------
-- 9. Articles d'un menu (Module Optionnel Premium)
-- ----------------------------
CREATE TABLE `menu_items` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `id_menu`    INT NOT NULL,
  `id_service` INT NOT NULL,
  FOREIGN KEY (`id_menu`)    REFERENCES `menus_cuisine`(`id`)       ON DELETE CASCADE,
  FOREIGN KEY (`id_service`) REFERENCES `catalogue_services`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------
-- 10. Réservations (chambre ou salle)
-- ----------------------------
CREATE TABLE `reservations` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `id_client`        INT NOT NULL,
  `id_chambre`       INT,
  `id_salle`         INT,
  `type_reservation` ENUM('chambre','salle') NOT NULL DEFAULT 'chambre',
  `date_arrivee`     DATE NOT NULL,
  `date_depart`      DATE NOT NULL,
  `statut`           ENUM('confirmee','annulee','en_cours','terminee') NOT NULL DEFAULT 'confirmee',
  `nb_personnes`     INT NOT NULL DEFAULT 1,
  `remarques`        TEXT,
  `id_receptionniste` INT NOT NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_client`)         REFERENCES `clients`(`id`)       ON DELETE RESTRICT,
  FOREIGN KEY (`id_chambre`)        REFERENCES `chambres`(`id`)      ON DELETE SET NULL,
  FOREIGN KEY (`id_salle`)          REFERENCES `salles`(`id`)        ON DELETE SET NULL,
  FOREIGN KEY (`id_receptionniste`) REFERENCES `utilisateurs`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------
-- 11. Commandes clients
-- ----------------------------
CREATE TABLE `commandes` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `id_client`        INT NOT NULL,
  `id_reservation`   INT,
  `id_receptionniste` INT NOT NULL,
  `statut`           ENUM('en_cours','servie','annulee') NOT NULL DEFAULT 'en_cours',
  `notes`            TEXT,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_client`)         REFERENCES `clients`(`id`)       ON DELETE RESTRICT,
  FOREIGN KEY (`id_reservation`)    REFERENCES `reservations`(`id`)  ON DELETE SET NULL,
  FOREIGN KEY (`id_receptionniste`) REFERENCES `utilisateurs`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------
-- 12. Lignes de commande
-- ----------------------------
CREATE TABLE `commande_lignes` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `id_commande`  INT NOT NULL,
  `id_service`   INT NOT NULL,
  `quantite`     INT NOT NULL DEFAULT 1,
  `prix_unitaire` DECIMAL(12,2) NOT NULL,
  `sous_total`   DECIMAL(12,2) NOT NULL,
  FOREIGN KEY (`id_commande`) REFERENCES `commandes`(`id`)            ON DELETE CASCADE,
  FOREIGN KEY (`id_service`)  REFERENCES `catalogue_services`(`id`)   ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------
-- 13. Factures
-- ----------------------------
CREATE TABLE `factures` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `id_client`           INT NOT NULL,
  `id_reservation`      INT,
  `date_emission`       DATE NOT NULL,
  `montant_sejour`      DECIMAL(12,2) NOT NULL DEFAULT 0,
  `montant_services`    DECIMAL(12,2) NOT NULL DEFAULT 0,
  `montant_total`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `reduction_pct`       DECIMAL(5,2)  NOT NULL DEFAULT 0,
  `reduction_motif`     VARCHAR(255),
  `montant_final`       DECIMAL(12,2) NOT NULL DEFAULT 0,
  `statut_paiement`     ENUM('impayee','partielle','payee') NOT NULL DEFAULT 'impayee',
  `mode_paiement`       ENUM('especes','carte','mobile','autre'),
  `reference_paiement`  VARCHAR(100),
  `id_receptionniste`   INT NOT NULL,
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_client`)         REFERENCES `clients`(`id`)       ON DELETE RESTRICT,
  FOREIGN KEY (`id_reservation`)    REFERENCES `reservations`(`id`)  ON DELETE SET NULL,
  FOREIGN KEY (`id_receptionniste`) REFERENCES `utilisateurs`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------
-- 14. Tâches de ménage (Traçabilité enrichie)
-- ----------------------------
CREATE TABLE `taches_menage` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `id_chambre`         INT NOT NULL,
  `id_agent`           INT,
  `type_tache`         ENUM('entree','sortie','periodique') NOT NULL DEFAULT 'sortie',
  `priorite`           ENUM('normale','haute','urgente') NOT NULL DEFAULT 'normale',
  `date_prevue`        DATE NOT NULL,
  `heure_debut`        DATETIME DEFAULT NULL,
  `heure_fin`          DATETIME DEFAULT NULL,
  `duree_minutes`      INT DEFAULT NULL,
  `statut`             ENUM('assignee','en_cours','terminee','inspectee') NOT NULL DEFAULT 'assignee',
  `qualite_inspection` ENUM('impeccable','satisfaisant','a_revoir') DEFAULT NULL,
  `notes`              TEXT,
  `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_chambre`) REFERENCES `chambres`(`id`)   ON DELETE CASCADE,
  FOREIGN KEY (`id_agent`)   REFERENCES `personnel`(`id`)  ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------
-- 15. Historique d'Occupation (Traçabilité des séjours)
-- ----------------------------
CREATE TABLE `historique_occupation` (
  `id`                    INT AUTO_INCREMENT PRIMARY KEY,
  `id_chambre`            INT NOT NULL,
  `id_reservation`        INT DEFAULT NULL,
  `id_client`             INT NOT NULL,
  `date_arrivee`          DATETIME NOT NULL,
  `date_depart_prevue`    DATETIME NOT NULL,
  `date_depart_effective` DATETIME DEFAULT NULL,
  `statut_sejour`         ENUM('en_cours','termine','annule') NOT NULL DEFAULT 'en_cours',
  `id_receptionniste`     INT NOT NULL,
  `remarques`             TEXT,
  `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_chambre`)        REFERENCES `chambres`(`id`)      ON DELETE CASCADE,
  FOREIGN KEY (`id_client`)         REFERENCES `clients`(`id`)       ON DELETE RESTRICT,
  FOREIGN KEY (`id_reservation`)    REFERENCES `reservations`(`id`)  ON DELETE SET NULL,
  FOREIGN KEY (`id_receptionniste`) REFERENCES `utilisateurs`(`id`)  ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ----------------------------
-- 16. Paramètres système
-- ----------------------------
CREATE TABLE `parametres_systeme` (
  `cle`     VARCHAR(50) PRIMARY KEY,
  `valeur`  TEXT NOT NULL
) ENGINE=InnoDB;

-- Initialisation des paramètres système par défaut
INSERT INTO `parametres_systeme` (`cle`, `valeur`) VALUES
('nom_hotel', 'Alma Hotel'),
('adresse_hotel', 'Quartier Administratif, BP 450'),
('telephone_hotel', '+223 20 22 00 11 / +223 76 00 11 22'),
('email_hotel', 'contact@almahotel.com'),
('devise', 'FCFA'),
('enable_premium_modules', '0'),
('theme_defaut', 'dark'),
('langue_defaut', 'fr')
ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`);

-- ----------------------------
-- 17. Sorties de Caisse & Dépenses (Fournitures & Maintenance)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `sorties_caisse` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `libelle`             VARCHAR(255) NOT NULL,
  `categorie`           ENUM('fourniture','nettoyage','maintenance','materiel','facture','autre') NOT NULL DEFAULT 'fourniture',
  `montant`             DECIMAL(12,2) NOT NULL,
  `date_sortie`         DATE NOT NULL,
  `mode_paiement`       ENUM('especes','carte','mobile','autre') NOT NULL DEFAULT 'especes',
  `reference_recu`      VARCHAR(100) DEFAULT NULL,
  `notes`               TEXT,
  `id_utilisateur`     INT NOT NULL,
  `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateurs`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

