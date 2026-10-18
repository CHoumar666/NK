/* ============================================================
   PROJET BERNABE - Entreprise de depannage
   Script complet : structure + jeu de donnees
   Import : phpMyAdmin > Importer, ou
            mysql -u root -p < database/bernabe.sql
   ============================================================ */
/* ============================================================
   BASE DE DONNEES : ENTREPRISE DE DEPANNAGE
   SGBD : MySQL 8.x
   ============================================================ */
DROP DATABASE IF EXISTS entreprise_depannage;
CREATE DATABASE entreprise_depannage
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE entreprise_depannage;
/* ============================================================
   1. TABLE SERVICE
   ============================================================ */
CREATE TABLE service (
    id_service INT PRIMARY KEY AUTO_INCREMENT,
    nom_service VARCHAR(100) NOT NULL,
    description VARCHAR(255)
);
/* ============================================================
   2. TABLE FONCTION
   ============================================================ */
CREATE TABLE fonction (
    id_fonction INT PRIMARY KEY AUTO_INCREMENT,
    libelle VARCHAR(100) NOT NULL UNIQUE
);
/* ============================================================
   3. TABLE GRILLE_SALAIRE
   Le salaire dépend de la fonction et de l'ancienneté
   ============================================================ */
CREATE TABLE grille_salaire (
    id_grille INT PRIMARY KEY AUTO_INCREMENT,
    id_fonction INT NOT NULL,
    anciennete_min INT NOT NULL,
    anciennete_max INT NOT NULL,
    salaire DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_grille_fonction
        FOREIGN KEY (id_fonction)
        REFERENCES fonction(id_fonction),
    CONSTRAINT ck_anciennete
        CHECK (anciennete_min >= 0
               AND anciennete_max >= anciennete_min),
    CONSTRAINT ck_salaire
        CHECK (salaire > 0),
    UNIQUE (id_fonction, anciennete_min, anciennete_max)
);
/* ============================================================
   4. TABLE EMPLOYE
   supérieur_id est une clé étrangère vers EMPLOYE
   ============================================================ */
CREATE TABLE employe (
    id_employe INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(80) NOT NULL,
    prenom VARCHAR(80) NOT NULL,
    date_embauche DATE NOT NULL,
    id_service INT NOT NULL,
    id_fonction INT NOT NULL,
    id_superieur INT NULL,
    CONSTRAINT fk_employe_service
        FOREIGN KEY (id_service)
        REFERENCES service(id_service),
    CONSTRAINT fk_employe_fonction
        FOREIGN KEY (id_fonction)
        REFERENCES fonction(id_fonction),
    CONSTRAINT fk_employe_superieur
        FOREIGN KEY (id_superieur)
        REFERENCES employe(id_employe)
    /* La regle "un employe ne peut pas etre son propre superieur"
       n'est pas ecrite en CHECK : MySQL 8 refuse un CHECK portant
       sur une colonne AUTO_INCREMENT (erreur 3818). Elle est
       controlee par l'application (employes.php), qui interdit
       aussi les cycles hierarchiques. */
);
/* ============================================================
   5. TABLE MATERIEL
   ============================================================ */
CREATE TABLE materiel (
    id_materiel INT PRIMARY KEY AUTO_INCREMENT,
    designation VARCHAR(150) NOT NULL,
    description VARCHAR(255),
    date_acquisition DATE,
    etat VARCHAR(30) DEFAULT 'Disponible',
    CONSTRAINT ck_etat_materiel
        CHECK (etat IN ('Disponible',
                        'En maintenance',
                        'Hors service'))
);
/* ============================================================
   6. COMPOSITION DES MATERIELS
   Un matériel peut être composé d'autres matériels.
   ============================================================ */
CREATE TABLE composer (
    id_materiel_parent INT NOT NULL,
    id_materiel_composant INT NOT NULL,
    quantite INT DEFAULT 1,
    PRIMARY KEY (
        id_materiel_parent,
        id_materiel_composant
    ),
    CONSTRAINT fk_composer_parent
        FOREIGN KEY (id_materiel_parent)
        REFERENCES materiel(id_materiel),
    CONSTRAINT fk_composer_composant
        FOREIGN KEY (id_materiel_composant)
        REFERENCES materiel(id_materiel),
    CONSTRAINT ck_quantite
        CHECK (quantite > 0),
    CONSTRAINT ck_composition
        CHECK (
            id_materiel_parent <> id_materiel_composant
        )
);
/* ============================================================
   7. QUALIFICATION DES EMPLOYES
   ============================================================ */
CREATE TABLE qualification (
    id_employe INT NOT NULL,
    id_materiel INT NOT NULL,
    date_qualification DATE NOT NULL,
    niveau VARCHAR(30) NOT NULL,
    PRIMARY KEY (id_employe, id_materiel),
    CONSTRAINT fk_qualification_employe
        FOREIGN KEY (id_employe)
        REFERENCES employe(id_employe),
    CONSTRAINT fk_qualification_materiel
        FOREIGN KEY (id_materiel)
        REFERENCES materiel(id_materiel),
    CONSTRAINT ck_niveau
        CHECK (niveau IN ('Debutant',
                          'Intermediaire',
                          'Avance',
                          'Expert'))
);
/* ============================================================
   8. TABLE CLIENT
   ============================================================ */
CREATE TABLE client (
    id_client INT PRIMARY KEY AUTO_INCREMENT,
    nom VARCHAR(120) NOT NULL,
    adresse VARCHAR(255),
    telephone VARCHAR(30),
    email VARCHAR(120)
);
/* ============================================================
   9. TABLE INTERVENTION
   ============================================================ */
CREATE TABLE intervention (
    id_intervention INT PRIMARY KEY AUTO_INCREMENT,
    date_intervention DATE NOT NULL,
    description VARCHAR(255),
    id_client INT NOT NULL,
    id_responsable INT NOT NULL,
    statut VARCHAR(30) DEFAULT 'Planifiee',
    CONSTRAINT fk_intervention_client
        FOREIGN KEY (id_client)
        REFERENCES client(id_client),
    CONSTRAINT fk_intervention_responsable
        FOREIGN KEY (id_responsable)
        REFERENCES employe(id_employe),
    CONSTRAINT ck_statut_intervention
        CHECK (statut IN ('Planifiee', 'En cours', 'Terminee', 'Annulee'))
);
/* ============================================================
   10. TABLE ACTE DE DEPANNAGE
   ============================================================ */
CREATE TABLE acte_depannage (
    id_acte INT PRIMARY KEY AUTO_INCREMENT,
    id_intervention INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    cout DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_acte_intervention
        FOREIGN KEY (id_intervention)
        REFERENCES intervention(id_intervention),
    CONSTRAINT ck_cout CHECK (cout >= 0)
);
/* ============================================================
   11. PARTICIPATION DES EMPLOYES AUX ACTES
   ============================================================ */
CREATE TABLE participation (
    id_employe INT NOT NULL,
    id_acte INT NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NULL,
    PRIMARY KEY (id_employe, id_acte),
    CONSTRAINT fk_participation_employe
        FOREIGN KEY (id_employe)
        REFERENCES employe(id_employe),
    CONSTRAINT fk_participation_acte
        FOREIGN KEY (id_acte)
        REFERENCES acte_depannage(id_acte),
    CONSTRAINT ck_dates_participation
        CHECK (
            date_fin IS NULL
            OR date_fin >= date_debut
        )
);

-- 2. Insertion des services
INSERT INTO service (nom_service, description) VALUES
('Electricite', 'Depannage des installations electriques'),
('Plomberie', 'Depannage des installations de plomberie'),
('Climatisation', 'Installation et maintenance des climatiseurs'),
('Informatique', 'Maintenance des equipements informatiques'),
('Telecommunication', 'Maintenance des equipements de communication'),
('Mecanique', 'Maintenance des equipements mecaniques'),
('Automatisme', 'Maintenance des systemes automatises'),
('Securite', 'Installation et maintenance des systemes de securite'),
('Reseaux', 'Maintenance des reseaux informatiques'),
('Maintenance generale', 'Maintenance generale des installations');

-- 3. Insertion des fonctions
INSERT INTO fonction (libelle) VALUES
('Directeur technique'),
('Chef de service'),
('Responsable technique'),
('Ingenieur'),
('Technicien senior'),
('Technicien'),
('Technicien junior'),
('Electricien'),
('Plombier'),
('Informaticien'),
('Mecanicien'),
('Automaticien'),
('Agent de maintenance'),
('Assistant technique'),
('Stagiaire');

-- 4. Insertion de la grille des salaires
INSERT INTO grille_salaire
(id_fonction, anciennete_min, anciennete_max, salaire)
VALUES
(1, 0, 5, 1500000),
(1, 6, 15, 1800000),
(2, 0, 5, 900000),
(2, 6, 15, 1100000),
(3, 0, 5, 800000),
(3, 6, 15, 1000000),
(4, 0, 5, 750000),
(4, 6, 15, 950000),
(5, 0, 5, 600000),
(5, 6, 15, 750000),
(6, 0, 5, 450000),
(6, 6, 15, 600000),
(7, 0, 5, 350000),
(7, 6, 15, 450000),
(8, 0, 5, 400000),
(8, 6, 15, 520000),
(9, 0, 5, 400000),
(9, 6, 15, 520000),
(10, 0, 5, 450000),
(10, 6, 15, 600000),
(11, 0, 5, 450000),
(11, 6, 15, 600000),
(12, 0, 5, 500000),
(12, 6, 15, 650000),
(13, 0, 5, 380000),
(13, 6, 15, 500000),
(14, 0, 5, 300000),
(14, 6, 15, 400000),
(15, 0, 5, 200000);

-- 5. Insertion des employés
-- Il faut faire attention ici à la clé étrangère réflexive id_superieur.
-- On insère d'abord les responsables, puis les autres employés.
INSERT INTO employe
(nom, prenom, date_embauche, id_service, id_fonction, id_superieur)
VALUES
('Kouassi', 'Jean', '2010-01-15', 10, 1, NULL),
('Yao', 'Bernard', '2012-03-10', 1, 2, 1),
('Koffi', 'Michel', '2014-06-20', 2, 2, 1),
('N''Guessan', 'Paul', '2015-02-12', 3, 2, 1),
('Kouame', 'Alain', '2016-08-01', 4, 2, 1),
('Bamba', 'Ibrahim', '2017-04-15', 5, 2, 1),
('Traore', 'Moussa', '2018-01-20', 6, 2, 1),
('Ouattara', 'Adama', '2019-05-10', 7, 2, 1),
('Konan', 'Serge', '2020-09-15', 8, 2, 1),
('Diomande', 'Ibrahima', '2021-02-05', 9, 2, 1);
-- Puis les techniciens :
INSERT INTO employe
(nom, prenom, date_embauche, id_service, id_fonction, id_superieur)
VALUES
('Yao', 'Patrick', '2018-01-10', 1, 6, 2),
('Koffi', 'Armand', '2019-02-15', 1, 8, 2),
('Kouadio', 'Eric', '2020-03-20', 1, 8, 2),
('Amani', 'Joseph', '2021-05-10', 1, 7, 2),
('Kone', 'Franck', '2017-06-01', 2, 6, 3),
('N''Dri', 'Roger', '2019-07-12', 2, 9, 3),
('Yapi', 'Jean-Luc', '2020-09-20', 2, 9, 3),
('Aka', 'Richard', '2022-01-15', 2, 7, 3),
('Kouassi', 'Didier', '2016-04-05', 3, 5, 4),
('Adjoua', 'Marthe', '2018-10-15', 3, 6, 4),
('Koffi', 'Daniel', '2020-02-20', 3, 7, 4),
('N''Guessan', 'Alphonse', '2022-03-01', 3, 6, 4),
('Brou', 'Alain', '2017-05-10', 4, 10, 5),
('Kouame', 'Cedric', '2019-06-20', 4, 10, 5),
('Yoboue', 'Franck', '2021-08-15', 4, 7, 5),
('Bamba', 'Issa', '2016-02-10', 5, 5, 6),
('Traore', 'Mamadou', '2018-05-15', 5, 6, 6),
('Fofana', 'Abdoulaye', '2020-10-01', 5, 7, 6),
('Kone', 'Karim', '2015-03-12', 6, 11, 7),
('Koffi', 'Yves', '2018-04-20', 6, 6, 7),
('Kouadio', 'Marc', '2021-06-10', 6, 7, 7),
('Yao', 'Emmanuel', '2019-01-05', 7, 12, 8),
('Aka', 'Bernard', '2020-05-20', 7, 6, 8),
('Kouame', 'Serge', '2022-07-10', 7, 7, 8),
('N''Guessan', 'Arnaud', '2017-08-15', 8, 5, 9),
('Koffi', 'Brice', '2020-09-10', 8, 6, 9),
('Amani', 'Didier', '2022-02-01', 8, 7, 9),
('Ouattara', 'Moussa', '2018-01-10', 9, 5, 10),
('Bamba', 'Seydou', '2020-03-15', 9, 6, 10),
('Fofana', 'Moussa', '2022-04-20', 9, 7, 10);
-- Nous avons ainsi 40 employés.

-- 6. Insertion des matériels
INSERT INTO materiel
(designation, description, date_acquisition, etat)
VALUES
('Groupe electrogene 100 KVA',
 'Groupe electrogene industriel',
 '2020-01-15', 'Disponible'),
('Groupe electrogene 250 KVA',
 'Groupe electrogene grande puissance',
 '2021-03-20', 'Disponible'),
('Transformateur 100 KVA',
 'Transformateur electrique',
 '2019-05-10', 'Disponible'),
('Transformateur 250 KVA',
 'Transformateur haute puissance',
 '2020-07-15', 'Disponible'),
('Disjoncteur industriel',
 'Disjoncteur haute tension',
 '2021-01-20', 'Disponible'),
('Multimetre professionnel',
 'Appareil de mesure electrique',
 '2022-02-10', 'Disponible'),
('Oscilloscope',
 'Oscilloscope numerique',
 '2022-04-15', 'Disponible'),
('Compresseur industriel',
 'Compresseur haute pression',
 '2019-08-20', 'En maintenance'),
('Pompe hydraulique',
 'Pompe hydraulique industrielle',
 '2020-09-10', 'Disponible'),
('Climatiseur industriel',
 'Climatiseur grande capacite',
 '2021-06-15', 'Disponible'),
('Manometre',
 'Appareil de mesure de pression',
 '2022-01-05', 'Disponible'),
('Poste a souder',
 'Poste a souder professionnel',
 '2020-11-10', 'Disponible'),
('Perceuse industrielle',
 'Perceuse electrique',
 '2021-02-15', 'Disponible'),
('Groupe de refroidissement',
 'Systeme de refroidissement',
 '2019-10-20', 'Disponible'),
('Serveur informatique',
 'Serveur professionnel',
 '2021-05-15', 'Disponible'),
('Onduleur 10 KVA',
 'Onduleur informatique',
 '2022-06-20', 'Disponible'),
('Routeur professionnel',
 'Routeur reseau',
 '2022-03-10', 'Disponible'),
('Analyseur reseau',
 'Analyseur de trafic reseau',
 '2021-09-15', 'Disponible'),
('Automate programmable',
 'Automate industriel',
 '2020-04-10', 'Disponible'),
('Capteur industriel',
 'Capteur de temperature',
 '2022-05-20', 'Disponible'),
('Robot industriel',
 'Robot de maintenance',
 '2020-08-15', 'Disponible'),
('Bras robotique',
 'Bras robotique industriel',
 '2021-07-10', 'Disponible'),
('Camera thermique',
 'Camera infrarouge',
 '2022-01-25', 'Disponible'),
('Drone inspection',
 'Drone pour inspection technique',
 '2022-09-15', 'Disponible'),
('Detecteur de tension',
 'Detecteur haute tension',
 '2021-03-05', 'Disponible'),
('Analyseur de puissance',
 'Analyseur electrique',
 '2020-12-15', 'Disponible'),
('Kit plomberie',
 'Kit professionnel de plomberie',
 '2022-02-20', 'Disponible'),
('Pompe a vide',
 'Pompe a vide professionnelle',
 '2021-10-15', 'Disponible'),
('Serveur de sauvegarde',
 'Serveur de sauvegarde informatique',
 '2022-07-20', 'Disponible'),
('Baie informatique',
 'Baie reseau professionnelle',
 '2021-11-10', 'Disponible');

-- 7. Composition des matériels
-- Nous pouvons maintenant représenter des matériels complexes composés de plusieurs composants.
INSERT INTO composer
(id_materiel_parent, id_materiel_composant, quantite)
VALUES
(1, 5, 1),
(1, 6, 1),
(2, 5, 2),
(2, 6, 1),
(2, 26, 1),
(3, 5, 1),
(3, 25, 1),
(4, 5, 2),
(4, 25, 2),
(4, 26, 1),
(8, 11, 1),
(8, 28, 1),
(10, 11, 2),
(10, 14, 1),
(15, 16, 1),
(15, 29, 1),
(17, 18, 1),
(17, 30, 1),
(19, 20, 4),
(19, 21, 1),
(21, 22, 1),
(21, 23, 2),
(24, 23, 2),
(24, 25, 1);
-- Ici, par exemple :
-- Le Groupe électrogène 250 KVA est composé de :
-- Disjoncteur industriel
-- Multimètre professionnel
-- Analyseur de puissance

-- 8. Qualification des employés
INSERT INTO qualification
(id_employe, id_materiel, date_qualification, niveau)
VALUES
(12, 1, '2021-03-10', 'Expert'),
(12, 2, '2022-01-15', 'Avance'),
(13, 3, '2021-06-20', 'Expert'),
(13, 5, '2021-06-20', 'Avance'),
(14, 1, '2022-03-10', 'Intermediaire'),
(14, 6, '2022-03-10', 'Avance'),
(15, 8, '2020-08-15', 'Expert'),
(16, 9, '2021-09-20', 'Avance'),
(17, 10, '2022-02-15', 'Intermediaire'),
(18, 10, '2022-03-10', 'Avance'),
(19, 15, '2020-05-10', 'Expert'),
(20, 15, '2021-07-15', 'Avance'),
(21, 16, '2022-01-10', 'Intermediaire'),
(22, 17, '2021-06-15', 'Expert'),
(23, 18, '2021-08-20', 'Avance'),
(24, 17, '2022-03-10', 'Intermediaire'),
(25, 19, '2020-09-15', 'Expert'),
(26, 19, '2021-10-10', 'Avance'),
(27, 20, '2022-02-15', 'Intermediaire'),
(28, 21, '2021-04-10', 'Expert'),
(29, 21, '2022-05-20', 'Avance'),
(30, 22, '2022-06-15', 'Intermediaire'),
(31, 23, '2020-10-15', 'Expert'),
(32, 24, '2022-01-20', 'Avance'),
(33, 25, '2022-03-15', 'Intermediaire'),
(34, 26, '2021-05-10', 'Expert'),
(35, 27, '2022-02-10', 'Avance'),
(36, 28, '2021-12-15', 'Expert'),
(37, 29, '2022-01-20', 'Avance'),
(38, 30, '2022-03-20', 'Intermediaire');

-- 9. Insertion des clients
INSERT INTO client
(nom, adresse, telephone, email)
VALUES
('CIE', 'Abidjan Cocody', '0707000001', 'contact@cie.ci'),
('SODECI', 'Abidjan Plateau', '0707000002', 'contact@sodeci.ci'),
('Orange CI', 'Abidjan Marcory', '0707000003', 'contact@orange.ci'),
('MTN CI', 'Abidjan Plateau', '0707000004', 'contact@mtn.ci'),
('Moov Africa CI', 'Abidjan Treichville', '0707000005', 'contact@moov.ci'),
('Hotel Ivoire', 'Abidjan Cocody', '0707000006', 'contact@hotelivoire.ci'),
('Universite Felix Houphouet Boigny', 'Abidjan Cocody', '0707000007', 'contact@ufhb.ci'),
('ESATIC', 'Abidjan Treichville', '0707000008', 'contact@esatic.ci'),
('Port Autonome d''Abidjan', 'Abidjan Treichville', '0707000009', 'contact@portabidjan.ci'),
('Aeroport FHB', 'Abidjan Port-Bouet', '0707000010', 'contact@aeroport.ci'),
('SICOGI', 'Abidjan Cocody', '0707000011', 'contact@sicogi.ci'),
('BNI', 'Abidjan Plateau', '0707000012', 'contact@bni.ci'),
('SGCI', 'Abidjan Plateau', '0707000013', 'contact@sgci.ci'),
('NSIA Banque', 'Abidjan Plateau', '0707000014', 'contact@nsiabanque.ci'),
('Orange Money', 'Abidjan Marcory', '0707000015', 'contact@orangemoney.ci'),
('CNPS', 'Abidjan Plateau', '0707000016', 'contact@cnps.ci'),
('RTI', 'Abidjan Cocody', '0707000017', 'contact@rti.ci'),
('Fraternite Matin', 'Abidjan Adjamé', '0707000018', 'contact@fratmat.ci'),
('Air Cote d''Ivoire', 'Abidjan Port-Bouet', '0707000019', 'contact@aircotedivoire.ci'),
('Sotra', 'Abidjan Koumassi', '0707000020', 'contact@sotra.ci');

-- 10. Insertion des interventions
INSERT INTO intervention
(date_intervention, description, id_client, id_responsable, statut)
VALUES
('2026-01-05', 'Panne groupe electrogene', 1, 2, 'Terminee'),
('2026-01-08', 'Maintenance transformateur', 2, 3, 'Terminee'),
('2026-01-12', 'Panne climatiseur', 3, 4, 'Terminee'),
('2026-01-15', 'Probleme reseau informatique', 4, 5, 'Terminee'),
('2026-01-20', 'Maintenance serveur', 5, 5, 'Terminee'),
('2026-01-25', 'Panne pompe hydraulique', 6, 3, 'Terminee'),
('2026-02-01', 'Maintenance automate', 7, 8, 'Terminee'),
('2026-02-05', 'Installation routeur', 8, 10, 'Terminee'),
('2026-02-10', 'Inspection equipement electrique', 9, 2, 'Terminee'),
('2026-02-15', 'Maintenance groupe electrogene', 10, 2, 'Terminee'),
('2026-02-20', 'Maintenance climatiseur', 11, 4, 'Terminee'),
('2026-02-25', 'Panne serveur', 12, 5, 'Terminee'),
('2026-03-01', 'Maintenance reseau', 13, 10, 'Terminee'),
('2026-03-05', 'Panne transformateur', 14, 2, 'Terminee'),
('2026-03-10', 'Installation onduleur', 15, 5, 'Terminee'),
('2026-03-15', 'Maintenance electrique', 16, 2, 'Terminee'),
('2026-03-20', 'Inspection drone', 17, 9, 'Terminee'),
('2026-03-25', 'Maintenance camera thermique', 18, 9, 'Terminee'),
('2026-04-01', 'Maintenance robot industriel', 19, 7, 'En cours'),
('2026-04-05', 'Maintenance baie informatique', 20, 10, 'Planifiee');

-- 11. Insertion des actes de dépannage
INSERT INTO acte_depannage
(id_intervention, description, cout)
VALUES
(1, 'Diagnostic du groupe', 75000),
(1, 'Remplacement du disjoncteur', 125000),
(1, 'Test de fonctionnement', 50000),
(2, 'Diagnostic transformateur', 90000),
(2, 'Remplacement composant', 175000),
(2, 'Tests electriques', 80000),
(3, 'Diagnostic climatiseur', 50000),
(3, 'Remplacement manometre', 95000),
(4, 'Diagnostic reseau', 70000),
(4, 'Configuration routeur', 120000),
(4, 'Tests reseau', 60000),
(5, 'Diagnostic serveur', 80000),
(5, 'Remplacement onduleur', 150000),
(5, 'Test serveur', 90000),
(6, 'Diagnostic pompe', 65000),
(6, 'Remplacement piece', 180000),
(7, 'Diagnostic automate', 100000),
(7, 'Reprogrammation automate', 250000),
(8, 'Installation routeur', 150000),
(8, 'Configuration reseau', 100000),
(9, 'Inspection electrique', 120000),
(9, 'Analyse puissance', 175000),
(10, 'Diagnostic groupe', 75000),
(10, 'Maintenance moteur', 200000),
(11, 'Diagnostic climatiseur', 60000),
(11, 'Nettoyage systeme', 80000),
(12, 'Diagnostic serveur', 90000),
(12, 'Remplacement disque', 200000),
(13, 'Diagnostic reseau', 60000),
(13, 'Reconfiguration reseau', 150000),
(14, 'Diagnostic transformateur', 100000),
(14, 'Remplacement composant', 250000),
(15, 'Installation onduleur', 180000),
(16, 'Diagnostic electrique', 80000),
(16, 'Remplacement disjoncteur', 130000),
(17, 'Inspection par drone', 300000),
(18, 'Inspection thermique', 200000),
(19, 'Diagnostic robot', 250000),
(19, 'Maintenance bras robotique', 350000),
(20, 'Installation baie informatique', 180000);

-- 12. Insertion des participations
INSERT INTO participation
(id_employe, id_acte, date_debut, date_fin)
VALUES
(12, 1, '2026-01-05 08:00:00', '2026-01-05 10:00:00'),
(13, 2, '2026-01-05 10:00:00', '2026-01-05 14:00:00'),
(14, 3, '2026-01-05 14:00:00', '2026-01-05 16:00:00'),
(15, 4, '2026-01-08 08:00:00', '2026-01-08 11:00:00'),
(16, 5, '2026-01-08 11:00:00', '2026-01-08 15:00:00'),
(17, 6, '2026-01-08 15:00:00', '2026-01-08 17:00:00'),
(17, 7, '2026-01-12 08:00:00', '2026-01-12 10:00:00'),
(18, 8, '2026-01-12 10:00:00', '2026-01-12 13:00:00'),
(22, 9, '2026-01-15 08:00:00', '2026-01-15 10:00:00'),
(23, 10, '2026-01-15 10:00:00', '2026-01-15 13:00:00'),
(24, 11, '2026-01-15 13:00:00', '2026-01-15 15:00:00'),
(22, 12, '2026-01-20 08:00:00', '2026-01-20 10:00:00'),
(23, 13, '2026-01-20 10:00:00', '2026-01-20 13:00:00'),
(24, 14, '2026-01-20 13:00:00', '2026-01-20 15:00:00'),
(16, 15, '2026-01-25 08:00:00', '2026-01-25 11:00:00'),
(17, 16, '2026-01-25 11:00:00', '2026-01-25 14:00:00'),
(25, 17, '2026-02-01 08:00:00', '2026-02-01 11:00:00'),
(26, 18, '2026-02-01 11:00:00', '2026-02-01 15:00:00'),
(28, 19, '2026-02-05 08:00:00', '2026-02-05 11:00:00'),
(29, 20, '2026-02-05 11:00:00', '2026-02-05 14:00:00'),
(12, 21, '2026-02-10 08:00:00', '2026-02-10 12:00:00'),
(13, 22, '2026-02-10 12:00:00', '2026-02-10 15:00:00'),
(12, 23, '2026-02-15 08:00:00', '2026-02-15 10:00:00'),
(13, 24, '2026-02-15 10:00:00', '2026-02-15 14:00:00'),
(17, 25, '2026-02-20 08:00:00', '2026-02-20 10:00:00'),
(18, 26, '2026-02-20 10:00:00', '2026-02-20 12:00:00'),
(22, 27, '2026-02-25 08:00:00', '2026-02-25 11:00:00'),
(23, 28, '2026-02-25 11:00:00', '2026-02-25 14:00:00'),
(28, 29, '2026-03-01 08:00:00', '2026-03-01 10:00:00'),
(29, 30, '2026-03-01 10:00:00', '2026-03-01 13:00:00'),
(12, 31, '2026-03-05 08:00:00', '2026-03-05 11:00:00'),
(13, 32, '2026-03-05 11:00:00', '2026-03-05 15:00:00'),
(22, 33, '2026-03-10 08:00:00', '2026-03-10 11:00:00'),
(25, 34, '2026-03-15 08:00:00', '2026-03-15 10:00:00'),
(26, 35, '2026-03-15 10:00:00', '2026-03-15 13:00:00'),
(31, 36, '2026-03-20 08:00:00', '2026-03-20 14:00:00'),
(31, 37, '2026-03-25 08:00:00', '2026-03-25 13:00:00'),
(28, 38, '2026-04-01 08:00:00', '2026-04-01 12:00:00'),
(29, 39, '2026-04-01 12:00:00', '2026-04-01 17:00:00'),
(37, 40, '2026-04-05 08:00:00', NULL);
