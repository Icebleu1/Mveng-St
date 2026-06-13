-- ============================================================
--  MVENGINEERING — Base de données complète v2
--  Compatible MySQL 5.6 (XAMPP 3.3.0)
-- ============================================================

CREATE DATABASE IF NOT EXISTS gestion_stagiaires
  CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE gestion_stagiaires;

-- ─── 1. UTILISATEURS ─────────────────────────────────────────
-- Contient admins ET stagiaires (rôle différencie)
CREATE TABLE IF NOT EXISTS utilisateurs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nom           VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL,
    mot_de_passe  VARCHAR(255) NOT NULL,
    role          VARCHAR(20)  NOT NULL DEFAULT 'stagiaire',
                  -- valeurs: 'stagiaire' | 'admin' | 'super_admin'
    actif         TINYINT(1)   NOT NULL DEFAULT 1,
    cree_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dernier_login DATETIME     NULL,
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Admin par défaut : admin@mvengineering.cm / Admin1234
INSERT INTO utilisateurs (nom, email, mot_de_passe, role) VALUES
('Administrateur', 'admin@mvengineering.cm',
 '$2y$12$4lOb0VG5/TtK3.jN5s3fPOLAcRdXlr6nS7xjEcmkM9m0TpTz8BXBO',
 'super_admin');

-- ─── 2. DEMANDES ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS demandes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT          NULL,  -- lié si le stagiaire a un compte
    nom_complet     VARCHAR(150) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    telephone       VARCHAR(20)  NOT NULL,
    ecole           VARCHAR(200) NOT NULL,
    filiere         VARCHAR(150) NOT NULL,
    niveau          VARCHAR(50)  NOT NULL,
    date_souhaitee  DATE         NOT NULL,
    duree           VARCHAR(50)  NOT NULL,
    message         TEXT         NULL,
    cv_fichier      VARCHAR(255) NULL,
    statut          VARCHAR(20)  NOT NULL DEFAULT 'en_attente',
    motif_refus     TEXT         NULL,
    email_envoye    TINYINT(1)   NOT NULL DEFAULT 0,
    soumis_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    traite_le       DATETIME     NULL,
    traite_par      INT          NULL,
    CONSTRAINT fk_demande_user    FOREIGN KEY (traite_par)     REFERENCES utilisateurs(id) ON DELETE SET NULL,
    CONSTRAINT fk_demande_compte  FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ─── 3. STAGIAIRES ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS stagiaires (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    demande_id      INT          NOT NULL,
    utilisateur_id  INT          NULL,  -- compte stagiaire créé à l'acceptation
    nom_complet     VARCHAR(150) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    telephone       VARCHAR(20)  NOT NULL,
    ecole           VARCHAR(200) NOT NULL,
    filiere         VARCHAR(150) NOT NULL,
    date_debut      DATE         NOT NULL,
    duree           VARCHAR(50)  NOT NULL,
    date_fin        DATE         NULL,
    statut          VARCHAR(20)  NOT NULL DEFAULT 'actif',
    maitre_stage    VARCHAR(150) NULL,
    note_finale     DECIMAL(4,2) NULL,   -- /20
    appreciation    TEXT         NULL,
    notes_internes  TEXT         NULL,
    ajoute_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_demande (demande_id),
    CONSTRAINT fk_stag_demande FOREIGN KEY (demande_id)     REFERENCES demandes(id)     ON DELETE CASCADE,
    CONSTRAINT fk_stag_user    FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ─── 4. ÉVALUATIONS ──────────────────────────────────────────
-- Grille d'évaluation du stagiaire par l'admin/RH
CREATE TABLE IF NOT EXISTS evaluations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    stagiaire_id    INT          NOT NULL,
    evaluateur_id   INT          NULL,
    semaine         INT          NOT NULL DEFAULT 1,
    note_travail    TINYINT      NULL,  -- /5
    note_ponctualite TINYINT     NULL,
    note_initiative TINYINT      NULL,
    note_communication TINYINT   NULL,
    note_technique  TINYINT      NULL,
    commentaire     TEXT         NULL,
    cree_le         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_eval_stag FOREIGN KEY (stagiaire_id)  REFERENCES stagiaires(id)    ON DELETE CASCADE,
    CONSTRAINT fk_eval_user FOREIGN KEY (evaluateur_id) REFERENCES utilisateurs(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ─── 5. RAPPORTS HEBDOMADAIRES ────────────────────────────────
-- Rapports soumis par le stagiaire chaque semaine
CREATE TABLE IF NOT EXISTS rapports (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    stagiaire_id    INT          NOT NULL,
    semaine         INT          NOT NULL DEFAULT 1,
    titre           VARCHAR(255) NOT NULL,
    contenu         TEXT         NOT NULL,
    fichier         VARCHAR(255) NULL,
    statut          VARCHAR(20)  NOT NULL DEFAULT 'soumis',
                    -- 'soumis' | 'lu' | 'valide' | 'a_revoir'
    commentaire_rh  TEXT         NULL,
    soumis_le       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    lu_le           DATETIME     NULL,
    CONSTRAINT fk_rapport_stag FOREIGN KEY (stagiaire_id) REFERENCES stagiaires(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ─── 6. NOTIFICATIONS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    demande_id   INT          NULL,
    type         VARCHAR(30)  NOT NULL,
    destinataire VARCHAR(150) NOT NULL,
    sujet        VARCHAR(255) NOT NULL,
    corps        TEXT         NOT NULL,
    envoye       TINYINT(1)   NOT NULL DEFAULT 0,
    envoye_le    DATETIME     NULL,
    erreur       TEXT         NULL,
    cree_le      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_demande FOREIGN KEY (demande_id) REFERENCES demandes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ─── 7. LOGS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS logs_admin (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT          NULL,
    action  VARCHAR(100) NOT NULL,
    details TEXT         NULL,
    ip      VARCHAR(45)  NULL,
    fait_le DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ─── DONNÉES DE DÉMONSTRATION ────────────────────────────────

-- Stagiaire démo avec compte
INSERT INTO utilisateurs (nom, email, mot_de_passe, role) VALUES
('Jean-Pierre Mballa', 'jp.mballa@email.com',
 '$2y$12$4lOb0VG5/TtK3.jN5s3fPOLAcRdXlr6nS7xjEcmkM9m0TpTz8BXBO',
 'stagiaire'),
('Samuel Tchamba', 's.tchamba@email.com',
 '$2y$12$4lOb0VG5/TtK3.jN5s3fPOLAcRdXlr6nS7xjEcmkM9m0TpTz8BXBO',
 'stagiaire');

INSERT INTO demandes (utilisateur_id, nom_complet, email, telephone, ecole, filiere, niveau, date_souhaitee, duree, message, statut, traite_par) VALUES
(2, 'Jean-Pierre Mballa',  'jp.mballa@email.com',   '+237 612 345 678', 'Universite de Yaounde I', 'Informatique',           'Master 1',  '2025-02-01', '3 mois', 'Tres motive.', 'accepte', 1),
(NULL,'Aminata Diallo',    'aminata.d@email.com',    '+237 678 901 234', 'ISTDI Douala',            'Gestion des entreprises','Licence 3', '2025-02-15', '2 mois', 'Stage licence.', 'en_attente', NULL),
(NULL,'Christian Fopa',    'c.fopa@email.com',       '+237 655 678 901', 'ENSP Yaounde',            'Genie civil',            'Master 2',  '2025-03-01', '6 mois', 'Fin d etudes.', 'refuse', 1),
(NULL,'Marie-Claire Ngo',  'mc.ngo@email.com',       '+237 699 012 345', 'Universite de Douala',    'Finance et comptabilite','Licence 2', '2025-01-20', '1 mois', '', 'en_attente', NULL),
(3,  'Samuel Tchamba',     's.tchamba@email.com',    '+237 670 234 567', 'IUT Ngaoundere',          'Reseaux et telecom',     'DUT',       '2025-02-10', '4 mois', 'Cybersecurite.', 'accepte', 1);

INSERT INTO stagiaires (demande_id, utilisateur_id, nom_complet, email, telephone, ecole, filiere, date_debut, duree, date_fin, statut, maitre_stage) VALUES
(1, 2, 'Jean-Pierre Mballa', 'jp.mballa@email.com', '+237 612 345 678', 'Universite de Yaounde I', 'Informatique', '2025-02-01', '3 mois', '2025-05-01', 'actif', 'M. Dupont'),
(5, 3, 'Samuel Tchamba',     's.tchamba@email.com', '+237 670 234 567', 'IUT Ngaoundere', 'Reseaux et telecom', '2025-02-10', '4 mois', '2025-06-10', 'actif', 'Mme. Martin');

INSERT INTO rapports (stagiaire_id, semaine, titre, contenu, statut) VALUES
(1, 1, 'Rapport semaine 1', 'Prise en main de l environnement de travail et presentation a l equipe. Installation des outils de developpement.', 'valide'),
(1, 2, 'Rapport semaine 2', 'Debut du projet principal. Analyse des besoins et conception de la base de donnees.', 'lu'),
(2, 1, 'Rapport semaine 1', 'Decouverte de l infrastructure reseau de l entreprise.', 'valide');

INSERT INTO evaluations (stagiaire_id, evaluateur_id, semaine, note_travail, note_ponctualite, note_initiative, note_communication, note_technique, commentaire) VALUES
(1, 1, 1, 4, 5, 3, 4, 4, 'Tres bonne integration dans l equipe. A ameliorer l initiative.'),
(2, 1, 1, 5, 5, 4, 3, 5, 'Excellentes competences techniques. Communication a developper.');
