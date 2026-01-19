CREATE DATABASE IF NOT EXISTS exams_planning;
USE exams_planning;

-- Table departements
CREATE TABLE IF NOT EXISTS departements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table formations
CREATE TABLE IF NOT EXISTS formations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  departement_id INT NOT NULL,
  nbr_module INT NOT NULL,
  FOREIGN KEY (departement_id) REFERENCES departements(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table professeurs
CREATE TABLE IF NOT EXISTS professeurs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  departement_id INT NOT NULL,
  specialite VARCHAR(100),
  FOREIGN KEY (departement_id) REFERENCES departements(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table modules
CREATE TABLE IF NOT EXISTS modules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(150) NOT NULL,
  credits INT DEFAULT 6,
  formation_id INT NOT NULL,
  responsable_id INT NOT NULL,
  idPrerequis INT,
  FOREIGN KEY (formation_id) REFERENCES formations(id),
  FOREIGN KEY (responsable_id) REFERENCES professeurs(id),
  FOREIGN KEY (idPrerequis) REFERENCES modules(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table etudiants
CREATE TABLE IF NOT EXISTS etudiants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  prenom VARCHAR(100) NOT NULL,
  formation_id INT NOT NULL,
  promo VARCHAR(100) NOT NULL,
  FOREIGN KEY (formation_id) REFERENCES formations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table inscriptions
CREATE TABLE IF NOT EXISTS inscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  etudiant_id INT NOT NULL,
  module_id INT NOT NULL,
  annee_academique VARCHAR(9) NOT NULL,
  note DECIMAL(4,2),
  UNIQUE (etudiant_id, module_id, annee_academique),
  FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
  FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table lieu_exam
CREATE TABLE IF NOT EXISTS lieu_exam (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  capacite INT NOT NULL,
  type ENUM('SALLE_TD','AMPHI') DEFAULT 'SALLE_TD',
  batiment VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table sessions
CREATE TABLE IF NOT EXISTS sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL,
  date_debut DATE NOT NULL,
  date_fin DATE NOT NULL,
  type_session ENUM('PRINCIPALE','RATTRAPAGE','CONTROLE') DEFAULT 'PRINCIPALE',
  statut ENUM('PLANIFICATION','EN_COURS','TERMINEE') DEFAULT 'PLANIFICATION',
  UNIQUE (nom)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table jours_session
CREATE TABLE IF NOT EXISTS jours_session (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id INT NOT NULL,
  date DATE NOT NULL,
  ordre INT NOT NULL,
  jour_semaine VARCHAR(10),
  est_ferie TINYINT DEFAULT 0,
  capacite_max_salles INT,
  UNIQUE (session_id, date),
  UNIQUE (session_id, ordre),
  FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table examens
CREATE TABLE IF NOT EXISTS examens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  module_id INT NOT NULL,
  salle_id INT NOT NULL,
  jour_id INT NOT NULL,
  surveillant_principal_id INT NOT NULL,
  date_heure DATETIME NOT NULL,
  duree_minute INT NOT NULL,
  statu ENUM('VALIDATION_FINAL','VALIDER','EN_ATTENTE') DEFAULT 'EN_ATTENTE',
  UNIQUE (module_id, jour_id),
  UNIQUE (salle_id, date_heure),
  FOREIGN KEY (module_id) REFERENCES modules(id),
  FOREIGN KEY (salle_id) REFERENCES lieu_exam(id),
  FOREIGN KEY (jour_id) REFERENCES jours_session(id),
  FOREIGN KEY (surveillant_principal_id) REFERENCES professeurs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table surveillances
CREATE TABLE IF NOT EXISTS surveillances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  examen_id INT NOT NULL,
  professeur_id INT NOT NULL,
  role VARCHAR(30) DEFAULT 'SURVEILLANT',
  UNIQUE (examen_id, professeur_id),
  FOREIGN KEY (examen_id) REFERENCES examens(id) ON DELETE CASCADE,
  FOREIGN KEY (professeur_id) REFERENCES professeurs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table conflits_edt
CREATE TABLE IF NOT EXISTS conflits_edt (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type_conflit VARCHAR(50) NOT NULL,
  examen_id_1 INT DEFAULT NULL,
  examen_id_2 INT DEFAULT NULL,
  description TEXT NOT NULL,
  date_detection DATETIME DEFAULT CURRENT_TIMESTAMP,
  statut VARCHAR(20) DEFAULT 'NON_RESOLU',
  FOREIGN KEY (examen_id_1) REFERENCES examens(id) ON DELETE SET NULL,
  FOREIGN KEY (examen_id_2) REFERENCES examens(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELIMITER $$

CREATE TRIGGER trg_etudiant_un_examen_par_jour
AFTER INSERT ON examens
FOR EACH ROW
BEGIN
    DECLARE mod_nom VARCHAR(150);
    DECLARE jour_date DATE;
    DECLARE ancien_examen_id INT;

    
    SELECT nom INTO mod_nom FROM modules WHERE id = NEW.module_id;

    
    SELECT date INTO jour_date FROM jours_session WHERE id = NEW.jour_id;

    
    SELECT e.id INTO ancien_examen_id
    FROM examens e
    JOIN inscriptions i ON e.module_id = i.module_id
    WHERE i.etudiant_id IN (
        SELECT etudiant_id
        FROM inscriptions
        WHERE module_id = NEW.module_id
    )
    AND e.jour_id = NEW.jour_id
    AND e.id != NEW.id
    LIMIT 1;

    IF ancien_examen_id IS NOT NULL THEN
        INSERT INTO conflits_edt(
            type_conflit,
            examen_id_1,
            examen_id_2,
            description
        )
        VALUES(
            'Etudiant',
            ancien_examen_id,  
            NEW.id,           
            CONCAT('Conflit détecté (plus de un examen par jour) pour le module ', mod_nom, ' le jour ', jour_date)
        );
    END IF;

END$$

DELIMITER ;

DELIMITER $$

CREATE TRIGGER trg_salle_capacity
AFTER INSERT ON examens
FOR EACH ROW
BEGIN
    DECLARE capacite_max INT;
    DECLARE nb_etudiants INT;
    DECLARE salle_nom VARCHAR(100);

   
    SELECT capacite, nom INTO capacite_max, salle_nom
    FROM lieu_exam
    WHERE id = NEW.salle_id;

  
    SELECT COUNT(*) INTO nb_etudiants
    FROM inscriptions
    WHERE module_id = NEW.module_id;

    IF nb_etudiants > capacite_max THEN
        INSERT INTO conflits_edt(type_conflit, examen_id_1, examen_id_2, description)
        VALUES (
            'Salle',
            NEW.id,  
            NULL,
            CONCAT('La salle ', salle_nom, ' ne peut accueillir ', nb_etudiants, ' étudiants (capacité: ', capacite_max, ')')
        );
    END IF;
END$$

DELIMITER ;







DELIMITER $$

DELIMITER $$

CREATE TRIGGER trg_prof_max_3_exams_after
AFTER INSERT ON examens
FOR EACH ROW
BEGIN
    DECLARE nb_exams INT;
    DECLARE prof_nom VARCHAR(150);
    DECLARE jour_date DATE;
    DECLARE ancien_examen_id INT;

    SELECT CONCAT(nom, ' ', prenom) INTO prof_nom
    FROM professeurs
    WHERE id = NEW.surveillant_principal_id;

    SELECT date INTO jour_date
    FROM jours_session
    WHERE id = NEW.jour_id;

   
    SELECT id INTO ancien_examen_id
    FROM examens
    WHERE surveillant_principal_id = NEW.surveillant_principal_id
      AND jour_id = NEW.jour_id
      AND id != NEW.id
    LIMIT 1;

    IF ancien_examen_id IS NOT NULL THEN
        INSERT INTO conflits_edt(
            type_conflit,
            examen_id_1,
            examen_id_2,
            description
        )
        VALUES(
            'Professeur',
            ancien_examen_id,
            NEW.id,
            CONCAT('Conflit pour le prof ', prof_nom , ' le jour ', jour_date)
        );
    END IF;
END$$

DELIMITER ;

ALTER TABLE examens
DROP INDEX module_id,  
ADD UNIQUE KEY unique_exam (module_id, salle_id, date_heure);


/*INDEX POUR OPTIMISATION DES PERFORMANCES*/

CREATE INDEX idx_exam_date_heure
ON examens(date_heure);

CREATE INDEX idx_exam_salle_date
ON examens(salle_id, date_heure);

CREATE INDEX idx_exam_prof_date
ON examens(surveillant_principal_id, date_heure);

CREATE INDEX idx_exam_module
ON examens(module_id);
 
 CREATE INDEX idx_inscriptions_module
ON inscriptions(module_id);

CREATE INDEX idx_inscriptions_etudiant_module
ON inscriptions(etudiant_id, module_id);

CREATE INDEX idx_modules_responsable
ON modules(responsable_id);

CREATE INDEX idx_modules_formation
ON modules(formation_id);

CREATE INDEX idx_professeurs_dept
ON professeurs(departement_id);

CREATE INDEX idx_conflits_statut
ON conflits_edt(statut);

   
   
   INSERT INTO departements (nom) VALUES
('Informatique'),
('Mathématiques'),
('Biologie'),
('Physique'),
('Chimie'),
('Médecine'),
('Agronomie');

INSERT INTO formations (nom, departement_id, nbr_module) VALUES
('Licence Informatique', 1, 6),
('Master Informatique', 1, 8),
('Licence Génie Logiciel', 1, 7),
('Master Génie Logiciel', 1, 9),
('Licence Réseaux et Sécurité', 1, 6),
('Master Réseaux et Sécurité', 1, 8),
('Licence Intelligence Artificielle', 1, 6),
('Master Intelligence Artificielle', 1, 8),
('Licence Data Science', 1, 6),
('Master Data Science', 1, 8),
('Licence Cybersécurité', 1, 6),
('Master Cybersécurité', 1, 8),
('Licence Informatique Industrielle', 1, 6),
('Master Informatique Industrielle', 1, 8),
('Licence Développement Web', 1, 6),
('Master Développement Web', 1, 8),
('Licence Mobile et Applications', 1, 6),
('Master Mobile et Applications', 1, 8),
('Licence Cloud Computing', 1, 6),
('Master Cloud Computing', 1, 8),
('Licence Systèmes Embarqués', 1, 6),
('Master Systèmes Embarqués', 1, 8),
('Licence Algorithmique et Data Structures', 1, 6),
('Master Algorithmique et Data Structures', 1, 8),
('Licence Informatique Graphique', 1, 6),
('Master Informatique Graphique', 1, 8),
('Licence Informatique Quantique', 1, 6),
('Master Informatique Quantique', 1, 8),
('Licence Robotique', 1, 6),
('Master Robotique', 1, 8),
('Licence Informatique Mobile', 1, 6),
('Master Informatique Mobile', 1, 8),
('Licence Informatique Financière', 1, 6),
('Master Informatique Financière', 1, 8),
('Licence Systèmes et Réseaux', 1, 6),
('Master Systèmes et Réseaux', 1, 8);

INSERT INTO formations (nom, departement_id, nbr_module) VALUES
('Licence Mathématiques', 2, 6),
('Master Mathématiques Appliquées', 2, 8),
('Licence Statistiques', 2, 6),
('Master Statistiques et Data', 2, 8),
('Licence Mathématiques Fondamentales', 2, 6),
('Master Mathématiques Fondamentales', 2, 8),
('Licence Analyse et Algèbre', 2, 6),
('Master Analyse et Algèbre', 2, 8),
('Licence Probabilités et Statistiques', 2, 6),
('Master Probabilités et Statistiques', 2, 8),
('Licence Mathématiques pour la Finance', 2, 6),
('Master Mathématiques pour la Finance', 2, 8),
('Licence Informatique Mathématique', 2, 6),
('Master Informatique Mathématique', 2, 8),
('Licence Data Analytics', 2, 6),
('Master Data Analytics', 2, 8),
('Licence Modélisation Mathématique', 2, 6),
('Master Modélisation Mathématique', 2, 8),
('Licence Optimisation', 2, 6),
('Master Optimisation', 2, 8),
('Licence Mathématiques et Physique', 2, 6),
('Master Mathématiques et Physique', 2, 8),
('Licence Mathématiques et Informatique', 2, 6),
('Master Mathématiques et Informatique', 2, 8),
('Licence Mathématiques Appliquées à l’Économie', 2, 6),
('Master Mathématiques Appliquées à l’Économie', 2, 8),
('Licence Mathématiques et Biostatistique', 2, 6),
('Master Mathématiques et Biostatistique', 2, 8),
('Licence Mathématiques pour Data Science', 2, 6),
('Master Mathématiques pour Data Science', 2, 8),
('Licence Statistiques Appliquées', 2, 6),
('Master Statistiques Appliquées', 2, 8),
('Licence Mathématiques Computationnelles', 2, 6),
('Master Mathématiques Computationnelles', 2, 8),
('Licence Mathématiques et Intelligence Artificielle', 2, 6),
('Master Mathématiques et Intelligence Artificielle', 2, 8);

INSERT INTO formations (nom, departement_id, nbr_module) VALUES
('Licence Biologie', 3, 6),
('Master Biologie Moléculaire', 3, 8),
('Licence Biochimie', 3, 6),
('Master Biochimie', 3, 8),
('Licence Biotechnologie', 3, 6),
('Master Biotechnologie', 3, 8),
('Licence Génétique', 3, 6),
('Master Génétique', 3, 8),
('Licence Microbiologie', 3, 6),
('Master Microbiologie', 3, 8),
('Licence Physiologie', 3, 6),
('Master Physiologie', 3, 8),
('Licence Écologie', 3, 6),
('Master Écologie', 3, 8),
('Licence Biologie Marine', 3, 6),
('Master Biologie Marine', 3, 8),
('Licence Immunologie', 3, 6),
('Master Immunologie', 3, 8),
('Licence Biologie Cellulaire', 3, 6),
('Master Biologie Cellulaire', 3, 8),
('Licence Biologie Structurale', 3, 6),
('Master Biologie Structurale', 3, 8),
('Licence Biologie Environnementale', 3, 6),
('Master Biologie Environnementale', 3, 8),
('Licence Biologie Théorique', 3, 6),
('Master Biologie Théorique', 3, 8),
('Licence Biologie Appliquée', 3, 6),
('Master Biologie Appliquée', 3, 8);

INSERT INTO formations (nom, departement_id, nbr_module) VALUES
('Licence Physique', 4, 6),
('Master Physique Fondamentale', 4, 8),
('Licence Physique Appliquée', 4, 6),
('Master Physique Appliquée', 4, 8),
('Licence Optique', 4, 6),
('Master Optique', 4, 8),
('Licence Mécanique', 4, 6),
('Master Mécanique', 4, 8),
('Licence Électromagnétisme', 4, 6),
('Master Électromagnétisme', 4, 8),
('Licence Physique Théorique', 4, 6),
('Master Physique Théorique', 4, 8),
('Licence Physique des Particules', 4, 6),
('Master Physique des Particules', 4, 8),
('Licence Physique Nucléaire', 4, 6),
('Master Physique Nucléaire', 4, 8),
('Licence Astrophysique', 4, 6),
('Master Astrophysique', 4, 8),
('Licence Physique Informatique', 4, 6),
('Master Physique Informatique', 4, 8),
('Licence Physique Appliquée à l’Industrie', 4, 6),
('Master Physique Appliquée à l’Industrie', 4, 8),
('Licence Physique et Électronique', 4, 6),
('Master Physique et Électronique', 4, 8),
('Licence Physique et Informatique', 4, 6),
('Master Physique et Informatique', 4, 8),
('Licence Physique Nucléaire Avancée', 4, 6),
('Master Physique Nucléaire Avancée', 4, 8),
('Licence Astrophysique Appliquée', 4, 6),
('Master Astrophysique Appliquée', 4, 8);


INSERT INTO formations (nom, departement_id, nbr_module) VALUES
('Licence Chimie', 5, 6),
('Master Chimie Organique', 5, 8),
('Master Chimie Industrielle', 5, 8),
('Licence Chimie Analytique', 5, 6),
('Master Chimie Analytique', 5, 8),
('Licence Chimie Physique', 5, 6),
('Master Chimie Physique', 5, 8),
('Licence Chimie Biologique', 5, 6),
('Master Chimie Biologique', 5, 8),
('Licence Chimie Théorique', 5, 6),
('Master Chimie Théorique', 5, 8),
('Licence Chimie des Matériaux', 5, 6),
('Master Chimie des Matériaux', 5, 8),
('Licence Chimie Industrielle Avancée', 5, 6),
('Master Chimie Industrielle Avancée', 5, 8),
('Licence Chimie et Environnement', 5, 6),
('Master Chimie et Environnement', 5, 8),
('Licence Chimie pour la Santé', 5, 6),
('Master Chimie pour la Santé', 5, 8),
('Licence Chimie Alimentaire', 5, 6),
('Master Chimie Alimentaire', 5, 8),
('Licence Chimie et Biotechnologie', 5, 6),
('Master Chimie et Biotechnologie', 5, 8),
('Licence Chimie Théorique Avancée', 5, 6),
('Master Chimie Théorique Avancée', 5, 8),
('Licence Chimie Analytique Avancée', 5, 6),
('Master Chimie Analytique Avancée', 5, 8),
('Licence Chimie Physique Avancée', 5, 6),
('Master Chimie Physique Avancée', 5, 8);

INSERT INTO formations (nom, departement_id, nbr_module) VALUES
('Licence Médecine', 6, 6),
('Master Médecine Générale', 6, 8),
('Licence Chirurgie', 6, 6),
('Master Chirurgie', 6, 8),
('Licence Pharmacie', 6, 6),
('Master Pharmacie', 6, 8),
('Licence Médecine Dentaire', 6, 6),
('Master Médecine Dentaire', 6, 8),
('Licence Biologie Médicale', 6, 6),
('Master Biologie Médicale', 6, 8),
('Licence Médecine Vétérinaire', 6, 6),
('Master Médecine Vétérinaire', 6, 8),
('Licence Santé Publique', 6, 6),
('Master Santé Publique', 6, 8),
('Licence Nutrition', 6, 6),
('Master Nutrition', 6, 8),
('Licence Kinésithérapie', 6, 6),
('Master Kinésithérapie', 6, 8),
('Licence Psychologie Médicale', 6, 6),
('Licence Psychologie Médicale humains', 6, 7),
('Master Psychologie Médicale', 6, 8);

INSERT INTO formations (nom, departement_id, nbr_module) VALUES
('Licence Agronomie', 7, 6),
('Master Agronomie', 7, 8),
('Licence Agriculture', 7, 6),
('Master Agriculture', 7, 8),
('Licence Génie Rural', 7, 6),
('Master Génie Rural', 7, 8),
('Licence Horticulture', 7, 6),
('Master Horticulture', 7, 8),
('Licence Agriculture Durable', 7, 6),
('Master Agriculture Durable', 7, 8),
('Licence Agroalimentaire', 7, 6),
('Master Agroalimentaire', 7, 8),
('Licence Agroécologie', 7, 6),
('Master Agroécologie', 7, 8),
('Licence Science du Sol', 7, 6),
('Master Science du Sol', 7, 8),
('Licence Foresterie', 7, 6),
('Master Foresterie', 7, 8),
('Licence Gestion des Ressources Naturelles', 7, 6),
('Master Gestion des Ressources Naturelles', 7, 8);

SELECT COUNT(*) FROM formations;


INSERT INTO etudiants (nom, prenom, formation_id, promo)
SELECT
  ELT(1 + FLOOR(RAND()*186), 'Benali','Boudjemaa','Kaci','Hamdi','Toumi','Saidi','Mansouri',
    'Bouaziz','Cherif','Amrani','Meziane','Rahmani','AitAhmed','Zerrouki',
    'Larbi','Belkacem','Bensaid','Yahiaoui','Haddad','Slimani','Ferhat',
    'Messaoud','Djaffar','Nacer','Tahar','Hocine','Samir','Rachid','Fouad',
    'Kamel','Nadir','Lotfi','Youssef','Reda','Hicham','Mounir','Adel','Amara',
    'Aziz','Said','Farouk','Mokhtar','Khaled','Imad','Rami','Mehdi','Mustapha',
    'Salah','Amine','Mohsen','Tarik','Yassine','Anouar','Fares','Marouane',
    'Hakim','Samy','Idir','Rayan','Omar','Sami','Karim','Ali','Bilal','Othman',
    'Ilyes','Malik','Redouane','Abdel','Aymen','Ismail','Hamza','Adil','Nabil',
    'Tayeb','Younes','Sofiane','Yahia','Yassin','Ahmed','Mohamed','Yacine',
    'Rabah','Lotfi','Hassan','Nacer','Said','Fouzi','Amar','Khalil','Riad',
    'Amin','Farid','Hichem','Kamel','Abdelkader','Samir','Mounir','Abdelhamid',
    'Azzedine','Salah','Karim','MohamedAli','MohamedAmine','OmarFarouk','AliRami',
    'MehdiSamir','YassineHakim','RedaMounir','NadirLotfi','AzizKhaled','ImadTarik',
    'AnisFarouk','MarouaneIdir','TaharRayan','SofianeKarim','AmineYounes','HassanMalik',
    'Ferrah','Medour','Touati','Abdeli','Simohand','Zerrouk',
     'Derias','Ferhaoui','Fennour','Fennouh','Coumichi','Fazouli',
'Zerrifi','Seffroun','Abbasi','Bousoir','Zemmour','Zemouri',
'Amrouche','Arkoub','Bedjbedj','Bouter','Osmani','Rekkas','Merdes','Saidani','Sellami',
'Seghir','Yahiaoui','Lounic','Chergi','Grib','Kohli','Mekiri','Tafat','Ouhibi','Zbour',
'Zimam','Selikh','Oubakouk','Ghouila','Fahdi',
'Zerrouki','Mahrez','Zidan','Amoura','Boulbina','Mandi','HadjMoussa','Maza','Bensabaini',
'Bounedjah','Blaili','Belghali','Aitnouri','Benacer','Boudaoui','Chaibi','Belaid','Kebbal',
'Mandrea','Benbout','Tougai','Atal','Hadjem','Zorgane','Aouar','Bekrar')
AS nom,
  ELT(1 + FLOOR(RAND()*153), 'Ahmed','Mohamed','Yacine','Sofiane','Islam','Amine','Anis','Walid','Nassim',
    'Karim','Imane','Sarah','Lina','Aya','Nour','Fatima','Khadija','Asma','Ines',
    'Meriem','Samira','Nadia','Amina','Yasmine','Leila','Selma','Sofia','Rania',
    'Salima','Djamila','Siham','Farah','Naima','Malika','Souad','Soraya','Rachida',
    'Lamia','Nouria','Nawal','Hanane','Samia','Zineb','Ikram','Meryem','Safia','Houda',
    'Imen','Yousra','Jihane','Kenza','Amel','Samar','Naoual','Chaima','Sana','Hiba',
    'SarahAmel','LeilaNour','YasmineAya','MeriemInes','FatimaLina','ImaneSofia','NadiaRania',
    'AminaSalima','SamiraDjamila','SihamFarah','NaimaMalika','SouadSoraya','RachidaLamia',
    'NouriaNawal','HananeSamia','IkramMeryem','SafiaHouda','ImenYousra','JihaneKenza',
    'AmelSamar','NaoualChaima','SanaHiba','AyaYasmine','LinaSarah','MeryemInes','KhadijaFatima',
'Ilan','Oumaima','Sara','Afaf','Hamza','Heithem','Ouail','Assil','Akram','Basat','Bara','Aous',
'Kousay','Yousr','Yazan','Ghait','Laith','Saith','Afnan','Taim','Badar','Ayoub','Ala','Khaoulat',
'Mouna','Mouni','Samah','Malik','abla','Fayrouz','Hasna','Berkahoum','Nayla','Samara','Zouliha',
'Mimina','Fadia','Nesayem','Rawia','Rabab','Akila','Batoul','Dalal','Fatine','Hafsa','Haifa','Ibtihal',
'Ibtissem','Lamya','Ridwana','Widad','Aicha','Anissa','Dana','Dina','Layal','Azra','Ilham','Khalida',
'Nassira','Nadira','Sawsan','Zaina','Ibrahim','Salem','Salim','Kamel','Faras','Nadir','Nawal') AS prenom,
  (SELECT id FROM formations ORDER BY RAND() LIMIT 1) AS formation_id,
  ELT(1 + FLOOR(RAND()*5), '2020-2021','2021-2022','2022-2023','2023-2024','2024-2025') AS promo
FROM information_schema.tables t1
CROSS JOIN information_schema.tables t2
LIMIT 13000;


-- PROFESSORS (200 random)

INSERT INTO professeurs (nom, prenom, departement_id, specialite)
SELECT
  CONCAT('ProfNom', LPAD(FLOOR(RAND()*9999),4,'0')),
  CONCAT('ProfPrenom', LPAD(FLOOR(RAND()*9999),4,'0')),
  1 + FLOOR(RAND()*7),  -- department 1-7
  ELT(1 + FLOOR(RAND()*10), 'Informatique','Maths','Biologie','Physique','Chimie','Médecine','Agronomie','AI','Réseaux','Cybersécurité')
FROM information_schema.tables t1
CROSS JOIN information_schema.tables t2
LIMIT 200;


-- EXAM ROOMS (100 random)

INSERT INTO lieu_exam (nom, capacite, type, batiment)
SELECT
  CONCAT('Salle_', LPAD(FLOOR(RAND()*9999),4,'0')),
  20 + FLOOR(RAND()*180), -- capacity between 20 and 200
  ELT(1 + FLOOR(RAND()*2), 'SALLE_TD','AMPHI'),
  ELT(1 + FLOOR(RAND()*10), 'A','B','C','D','E','F','G','H','I','J')
FROM information_schema.tables t1
CROSS JOIN information_schema.tables t2
LIMIT 100;


-- MODULES (One per formation, random responsible)

INSERT INTO modules (nom, credits, formation_id, responsable_id, idPrerequis)
SELECT
  CONCAT('Module_', f.id, '_', LPAD(FLOOR(RAND()*9999),4,'0')),
  6 + FLOOR(RAND()*3), -- credits 6-8
  f.id,
  FLOOR(1 + RAND()*200), -- random professor
  NULL
FROM formations f;


-- SESSIONS (Monthly)

INSERT INTO sessions (nom, date_debut, date_fin, type_session, statut)
VALUES
('Session Principale 2026','2026-06-01','2026-06-30','PRINCIPALE','PLANIFICATION');







-- INSCRIPTIONS (all students to all modules in their formation)

INSERT INTO inscriptions (etudiant_id, module_id, annee_academique, note)
SELECT e.id, m.id, '2025-2026', NULL
FROM etudiants e
JOIN modules m ON e.formation_id = m.formation_id;


-- DAYS OF SESSION (1 month)

DELIMITER $$

CREATE PROCEDURE inserer_jours_session()
BEGIN
    DECLARE d DATE;
    SET d = @date_start;

    WHILE d <= @date_end DO
        INSERT INTO jours_session
        (session_id, date, ordre, jour_semaine, est_ferie, capacite_max_salles)
        VALUES
        (1, d, DAY(d), DAYNAME(d), 0, 5);

        SET d = DATE_ADD(d, INTERVAL 1 DAY);
    END WHILE;
END$$

DELIMITER ;

CALL inserer_jours_session();








