-- Gestion locative — schéma MySQL 8 (utf8mb4)
--
-- ⚠️ RÉSERVÉ À UNE BASE VIDE / RECRÉATION COMPLÈTE (supprime toutes les données).
-- Si tu as déjà une base `gestion_locative` avec des données, NE PAS exécuter ce fichier.
--
-- Importer : mysql -u root gestion_locative < database/schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS quittances;
DROP TABLE IF EXISTS paiements;
DROP TABLE IF EXISTS locataires;
DROP TABLE IF EXISTS chambres;
DROP TABLE IF EXISTS maisons;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE maisons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(255) NOT NULL,
    adresse TEXT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chambres (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    maison_id INT UNSIGNED NOT NULL,
    numero VARCHAR(50) NOT NULL COMMENT 'Unique par maison (ex. C1, C2)',
    statut ENUM('libre', 'occupée') NOT NULL DEFAULT 'libre',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_chambre_maison_numero (maison_id, numero),
    CONSTRAINT fk_chambres_maison FOREIGN KEY (maison_id) REFERENCES maisons (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE locataires (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    chambre_id INT UNSIGNED NOT NULL,
    nom_complet VARCHAR(255) NOT NULL,
    telephone VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    loyer_mensuel DECIMAL(12,2) NOT NULL DEFAULT 0,
    date_entree DATE NOT NULL,
    date_sortie DATE NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_locataires_chambre FOREIGN KEY (chambre_id) REFERENCES chambres (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paiements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    locataire_id INT UNSIGNED NOT NULL,
    montant DECIMAL(12,2) NOT NULL,
    mois_concerne DATE NOT NULL COMMENT 'Premier jour du mois concerné',
    date_paiement DATE NOT NULL,
    mode_paiement VARCHAR(50) NOT NULL,
    note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_paiement_locataire_mois (locataire_id, mois_concerne),
    CONSTRAINT fk_paiements_locataire FOREIGN KEY (locataire_id) REFERENCES locataires (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quittances (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    paiement_id INT UNSIGNED NOT NULL,
    numero_quittance VARCHAR(32) NOT NULL,
    fichier VARCHAR(512) NULL COMMENT 'Chemin relatif sous quittances/',
    envoye_mail TINYINT(1) NOT NULL DEFAULT 0,
    envoye_whatsapp TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_quittance_numero (numero_quittance),
    UNIQUE KEY uq_quittance_paiement (paiement_id),
    CONSTRAINT fk_quittances_paiement FOREIGN KEY (paiement_id) REFERENCES paiements (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
