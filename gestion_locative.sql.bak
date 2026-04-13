-- ============================================================
--  SYSTÈME DE GESTION LOCATIVE
--  Schéma MySQL complet
--  Paiements enregistrés manuellement par l'admin
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. MAISONS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS maisons (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(100)  NOT NULL,               -- ex: "Villa Kokou"
    adresse     VARCHAR(255)  NOT NULL,
    description TEXT          NULL,
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. CHAMBRES
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chambres (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    maison_id   INT UNSIGNED  NOT NULL,
    numero      VARCHAR(20)   NOT NULL,               -- ex: "C1", "C2"
    description TEXT          NULL,
    statut      ENUM('libre','occupée') DEFAULT 'libre',
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_chambre_maison
        FOREIGN KEY (maison_id) REFERENCES maisons(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    UNIQUE KEY uq_chambre (maison_id, numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. LOCATAIRES
--    Un seul locataire actif par chambre à la fois
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS locataires (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chambre_id      INT UNSIGNED  NOT NULL,
    nom_complet     VARCHAR(150)  NOT NULL,
    telephone       VARCHAR(25)   NULL,
    email           VARCHAR(150)  NULL,
    loyer_mensuel   DECIMAL(10,2) NOT NULL,            -- montant variable par locataire
    date_entree     DATE          NOT NULL,
    date_sortie     DATE          NULL,                -- NULL = encore en place
    actif           TINYINT(1)    DEFAULT 1,           -- 1 = en cours, 0 = parti
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_locataire_chambre
        FOREIGN KEY (chambre_id) REFERENCES chambres(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. PAIEMENTS
--    Enregistrés manuellement par l'admin (hors système)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS paiements (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    locataire_id    INT UNSIGNED  NOT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    mois_concerne   DATE          NOT NULL,            -- stocker le 1er du mois ex: 2025-04-01
    date_paiement   DATE          NOT NULL,            -- date réelle où l'argent a été remis
    mode_paiement   ENUM(
                        'espèces',
                        'mobile money',
                        'virement',
                        'chèque',
                        'autre'
                    ) DEFAULT 'espèces',
    note            VARCHAR(255)  NULL,                -- remarque libre de l'admin
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_paiement_locataire
        FOREIGN KEY (locataire_id) REFERENCES locataires(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    -- évite de comptabiliser deux fois le même mois pour le même locataire
    UNIQUE KEY uq_paiement_mois (locataire_id, mois_concerne)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. QUITTANCES
--    Générées automatiquement après chaque paiement
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS quittances (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paiement_id         INT UNSIGNED  NOT NULL UNIQUE, -- 1 quittance par paiement
    numero_quittance    VARCHAR(30)   NOT NULL UNIQUE, -- ex: "QUIT-2025-0042"
    date_emission       DATE          NOT NULL,
    pdf_path            VARCHAR(255)  NULL,            -- chemin du fichier PDF généré
    envoye_mail         TINYINT(1)    DEFAULT 0,
    envoye_whatsapp     TINYINT(1)    DEFAULT 0,
    created_at          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_quittance_paiement
        FOREIGN KEY (paiement_id) REFERENCES paiements(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  VUES UTILES
-- ============================================================

-- Vue : état de toutes les chambres avec leur locataire actif
CREATE OR REPLACE VIEW vue_chambres_etat AS
SELECT
    m.id          AS maison_id,
    m.nom         AS maison,
    c.id          AS chambre_id,
    c.numero      AS chambre,
    c.statut,
    l.id          AS locataire_id,
    l.nom_complet AS locataire,
    l.telephone,
    l.loyer_mensuel,
    l.date_entree
FROM chambres c
JOIN maisons m ON m.id = c.maison_id
LEFT JOIN locataires l ON l.chambre_id = c.id AND l.actif = 1
ORDER BY m.nom, c.numero;

-- Vue : historique complet des paiements avec quittances
CREATE OR REPLACE VIEW vue_paiements_complets AS
SELECT
    p.id              AS paiement_id,
    m.nom             AS maison,
    c.numero          AS chambre,
    l.nom_complet     AS locataire,
    l.loyer_mensuel,
    p.montant,
    DATE_FORMAT(p.mois_concerne, '%M %Y') AS mois,
    p.date_paiement,
    p.mode_paiement,
    q.numero_quittance,
    q.envoye_mail,
    q.envoye_whatsapp
FROM paiements p
JOIN locataires l  ON l.id = p.locataire_id
JOIN chambres c    ON c.id = l.chambre_id
JOIN maisons m     ON m.id = c.maison_id
LEFT JOIN quittances q ON q.paiement_id = p.id
ORDER BY p.date_paiement DESC;

-- Vue : locataires en retard (pas de paiement le mois courant)
CREATE OR REPLACE VIEW vue_retards AS
SELECT
    m.nom         AS maison,
    c.numero      AS chambre,
    l.id          AS locataire_id,
    l.nom_complet AS locataire,
    l.telephone,
    l.loyer_mensuel
FROM locataires l
JOIN chambres c ON c.id = l.chambre_id
JOIN maisons m  ON m.id = c.maison_id
WHERE l.actif = 1
  AND l.id NOT IN (
      SELECT locataire_id FROM paiements
      WHERE mois_concerne = DATE_FORMAT(CURDATE(), '%Y-%m-01')
  )
ORDER BY m.nom, c.numero;

-- ============================================================
--  DONNÉES DE TEST
-- ============================================================

INSERT INTO maisons (nom, adresse) VALUES
    ('Villa Kokou',   'Rue des Cocotiers, Cotonou'),
    ('Résidence Akpé', 'Quartier Zongo, Porto-Novo');

INSERT INTO chambres (maison_id, numero, statut) VALUES
    (1, 'C1', 'occupée'),
    (1, 'C2', 'occupée'),
    (1, 'C3', 'libre'),
    (2, 'C1', 'occupée'),
    (2, 'C2', 'libre');

INSERT INTO locataires (chambre_id, nom_complet, telephone, email, loyer_mensuel, date_entree) VALUES
    (1, 'Kofi Mensah',    '+22960112233', 'kofi@mail.com',   35000.00, '2024-01-01'),
    (2, 'Ama Dossou',     '+22961445566', 'ama@mail.com',    40000.00, '2024-03-15'),
    (4, 'Yao Gbaguidi',   '+22962778899', NULL,              30000.00, '2024-06-01');

SET FOREIGN_KEY_CHECKS = 1;
