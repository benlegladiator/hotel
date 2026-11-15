-- ============================================================
-- Minabou Hotel — Données de démonstration enrichies
-- ============================================================

USE `minabou_hotel`;

-- Clean tables before demo re-insert (order matters due to foreign keys)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `historique_occupation`;
TRUNCATE TABLE `taches_menage`;
TRUNCATE TABLE `factures`;
TRUNCATE TABLE `commande_lignes`;
TRUNCATE TABLE `commandes`;
TRUNCATE TABLE `reservations`;
TRUNCATE TABLE `menu_items`;
TRUNCATE TABLE `menus_cuisine`;
TRUNCATE TABLE `catalogue_services`;
TRUNCATE TABLE `personnel`;
TRUNCATE TABLE `clients`;
TRUNCATE TABLE `salles`;
TRUNCATE TABLE `chambres`;
TRUNCATE TABLE `types_chambres`;
TRUNCATE TABLE `utilisateurs`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Utilisateurs (admin / admin123  & recep / admin123)
INSERT INTO `utilisateurs` (`id`, `identifiant`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `langue`, `theme`) VALUES
(1, 'admin', 'Minabou', 'Admin', 'admin@minabouhotel.com', '$2y$10$g5/ity84il9QQ6eQaNVvoufOXZ1gy9bhKGXdMr5iqOnFHhT/fYe8.', 'admin', 'fr', 'dark'),
(2, 'recep', 'Traoré', 'Fatima', 'fatima@minabouhotel.com', '$2y$10$g5/ity84il9QQ6eQaNVvoufOXZ1gy9bhKGXdMr5iqOnFHhT/fYe8.', 'receptionniste', 'fr', 'dark');

-- 2. Types de chambres
INSERT INTO `types_chambres` (`id`, `nom`, `description`, `prix_nuit`, `prix_sieste`, `capacite`, `avantages`, `photo`) VALUES
(1, 'Standard', 'Chambre standard confortable', 12000, 10000, 2, 'Ventilateur / Climatisation, Télévision, Douche', 'type_standard.jpg'),
(2, 'Confort', 'Chambre supérieure tout confort', 15000, 10000, 2, 'Climatisation, TV HD, Salle de bain moderne', 'type_deluxe.jpg'),
(3, 'Boucaro', 'Chambre Boucaro ventilée/climatisée', 10000, 5000, 2, 'Espace calme et intimiste', 'type_suite.jpg');

-- 3. Chambres
INSERT INTO `chambres` (`id`, `numero`, `id_type_chambre`, `etage`, `statut`, `description`, `photo`) VALUES
(1, '101', 1, 1, 'libre', 'Bâtiment 1 - Chambre standard', 'chambre_101.jpg'),
(2, '102', 2, 1, 'libre', 'Bâtiment 1 - Chambre confort', 'chambre_102.jpg'),
(3, '103', 1, 1, 'libre', 'Bâtiment 1 - Chambre standard', 'chambre_103.jpg'),
(4, '104', 2, 1, 'libre', 'Bâtiment 1 - Chambre confort', 'chambre_201.jpg'),
(5, '105', 1, 1, 'libre', 'Bâtiment 2 - Chambre standard', 'chambre_202.jpg'),
(6, '106', 2, 1, 'libre', 'Bâtiment 2 - Chambre confort', 'chambre_301.jpg'),
(7, '107', 1, 1, 'libre', 'Bâtiment 2 - Chambre standard', 'chambre_default.jpg'),
(8, '108', 2, 1, 'libre', 'Bâtiment 2 - Chambre confort', 'chambre_default.jpg'),
(9, '109', 3, 0, 'libre', 'Boucaro 109', 'chambre_default.jpg'),
(10, '110', 3, 0, 'libre', 'Boucaro 110', 'chambre_default.jpg'),
(11, '111', 3, 0, 'libre', 'Boucaro 111', 'chambre_default.jpg'),
(12, '112', 3, 0, 'libre', 'Boucaro 112', 'chambre_default.jpg');

-- 4. Salles (Optionnel Premium)
INSERT INTO `salles` (`id`, `nom`, `capacite`, `prix_location`, `description`, `statut`) VALUES
(1, 'Salle 1', 150, 350000, 'Grande salle de fête pour séminaires et cérémonies', 'libre'),
(2, 'Salle 2', 80, 250000, 'Salle de fête 2 équipée et climatisée', 'libre');

-- 5. Clients
INSERT INTO `clients` (`id`, `nom`, `prenom`, `date_naissance`, `lieu_naissance`, `telephone`, `email`, `adresse`, `nationalite`, `piece_identite`, `nb_sejours`) VALUES
(1, 'Diarra', 'Mamadou', '1985-04-12', 'Bamako', '+223 76 12 34 56', 'm.diarra@email.com', 'Bamako, Hamdallaye ACI', 'Malienne', 'CNI 123456789', 3),
(2, 'Kouassi', 'Jean-Philippe', '1990-08-23', 'Abidjan', '+225 07 88 99 00', 'jp.kouassi@email.com', 'Abidjan, Cocody', 'Ivoirienne', 'Passeport B987654', 1),
(3, 'Sow', 'Aïssatou', '1993-11-05', 'Dakar', '+221 77 450 11 22', 'aissatou.sow@email.com', 'Dakar, Mermoz', 'Sénégalaise', 'CNI 98761234', 2),
(4, 'Smith', 'Robert', '1980-02-18', 'Lyon', '+33 6 12 34 56 78', 'r.smith@email.com', 'Paris, France', 'Française', 'Passeport FR998877', 4);

-- 6. Personnel
INSERT INTO `personnel` (`id`, `nom`, `prenom`, `telephone`, `role`, `actif`, `notes`) VALUES
(1, 'Koné', 'Aminata', '+223 66 11 22 33', 'menage', 1, 'Agent senior - Ménage et entretien étage 1 & 2'),
(2, 'Camara', 'Sékou', '+223 75 44 55 66', 'menage', 1, 'Agent de nettoyage - Spécialiste désinfection et sorties'),
(3, 'Diallo', 'Mariam', '+223 90 88 77 66', 'menage', 1, 'Agent de ménage et blanchisserie'),
(4, 'Sidibé', 'Ousmane', '+223 65 33 22 11', 'cuisinier', 1, 'Chef cuisinier (Prestations restauration)');

-- 7. Catalogue de services (Prestations simplifiées + extras)
INSERT INTO `catalogue_services` (`id`, `nom`, `categorie`, `description`, `prix`, `disponible`) VALUES
(1, 'Eau minérale 1.5L', 'boisson', 'Bouteille d\'eau fraîche 1.5L', 1000, 1),
(2, 'Café / Thé complet', 'boisson', 'Service café expresso ou thé avec viennoiserie', 1500, 1),
(3, 'Jus de fruits frais', 'boisson', 'Jus naturel (Mangue, Bissap, Gingembre)', 1500, 1),
(4, 'Petit-déjeuner Continental', 'nourriture', 'Pain chaud, beurre, confiture, œufs, jus, boisson chaude', 4000, 1),
(5, 'Omelette garnie & frites', 'nourriture', 'Omelette jambon/fromage et portion de frites', 3500, 1),
(6, 'Service Blanchisserie (chemise/pantalon)', 'autre', 'Lavage, séchage et repassage', 2000, 1),
(7, 'Lavage complet costume/robe', 'autre', 'Nettoyage à sec et repassage', 5000, 1);

-- 8. Réservations
INSERT INTO `reservations` (`id`, `id_client`, `id_chambre`, `type_reservation`, `date_arrivee`, `date_depart`, `statut`, `nb_personnes`, `id_receptionniste`) VALUES
(1, 1, 1, 'chambre', DATE_SUB(CURRENT_DATE(), INTERVAL 2 DAY), DATE_ADD(CURRENT_DATE(), INTERVAL 2 DAY), 'en_cours', 2, 2),
(2, 2, 4, 'chambre', DATE_SUB(CURRENT_DATE(), INTERVAL 1 DAY), DATE_ADD(CURRENT_DATE(), INTERVAL 3 DAY), 'en_cours', 1, 2),
(3, 3, 6, 'chambre', DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY), DATE_ADD(CURRENT_DATE(), INTERVAL 5 DAY), 'confirmee', 2, 1),
(4, 4, 2, 'chambre', DATE_SUB(CURRENT_DATE(), INTERVAL 8 DAY), DATE_SUB(CURRENT_DATE(), INTERVAL 3 DAY), 'terminee', 1, 2);

-- 9. Historique d'Occupation (Traçabilité des séjours)
INSERT INTO `historique_occupation` (`id`, `id_chambre`, `id_reservation`, `id_client`, `date_arrivee`, `date_depart_prevue`, `date_depart_effective`, `statut_sejour`, `id_receptionniste`, `remarques`) VALUES
(1, 1, 1, 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_ADD(NOW(), INTERVAL 2 DAY), NULL, 'en_cours', 2, 'Client régulier - Arrivée 14h30'),
(2, 4, 2, 2, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_ADD(NOW(), INTERVAL 3 DAY), NULL, 'en_cours', 2, 'Arrivée tardive 21h00'),
(3, 2, 4, 4, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), 'termine', 2, 'Séjour de 5 nuits sans problème - Réduction accordée');

-- 10. Tâches de ménage (Traçabilité enrichie)
INSERT INTO `taches_menage` (`id`, `id_chambre`, `id_agent`, `type_tache`, `priorite`, `date_prevue`, `heure_debut`, `heure_fin`, `duree_minutes`, `statut`, `qualite_inspection`, `notes`) VALUES
(1, 3, 1, 'sortie', 'haute', CURRENT_DATE(), DATE_SUB(NOW(), INTERVAL 45 MINUTE), DATE_SUB(NOW(), INTERVAL 10 MINUTE), 35, 'terminee', 'impeccable', 'Nettoyage complet effectué, drap changés, minibar réapprovisionné'),
(2, 2, 2, 'periodique', 'normale', CURRENT_DATE(), DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR), 60, 'terminee', 'satisfaisant', 'Aération et dépoussiérage OK'),
(3, 1, 1, 'periodique', 'normale', CURRENT_DATE(), NULL, NULL, NULL, 'assignee', NULL, 'Ménage quotidien chambre occupée (changement serviettes)'),
(4, 5, 3, 'periodique', 'normale', CURRENT_DATE(), NOW(), NULL, NULL, 'en_cours', NULL, 'En cours de nettoyage');

-- 11. Commandes & Prestations
INSERT INTO `commandes` (`id`, `id_client`, `id_reservation`, `id_receptionniste`, `statut`, `notes`) VALUES
(1, 1, 1, 2, 'servie', 'Petit déjeuner en chambre'),
(2, 2, 2, 2, 'servie', 'Blanchisserie express');

INSERT INTO `commande_lignes` (`id`, `id_commande`, `id_service`, `quantite`, `prix_unitaire`, `sous_total`) VALUES
(1, 1, 4, 2, 4000, 8000),
(2, 1, 1, 2, 1000, 2000),
(3, 2, 6, 3, 2000, 6000);

-- 12. Factures (Paiements et comptabilité)
INSERT INTO `factures` (`id`, `id_client`, `id_reservation`, `date_emission`, `montant_sejour`, `montant_services`, `montant_total`, `reduction_pct`, `montant_final`, `statut_paiement`, `mode_paiement`, `reference_paiement`, `id_receptionniste`) VALUES
(1, 4, 4, DATE_SUB(CURRENT_DATE(), INTERVAL 3 DAY), 125000, 5000, 130000, 5.00, 123500, 'payee', 'especes', 'CASH-882', 2),
(2, 1, 1, CURRENT_DATE(), 100000, 10000, 110000, 0.00, 110000, 'partielle', 'mobile', 'WAVE-9921', 2),
(3, 2, 2, CURRENT_DATE(), 180000, 6000, 186000, 0.00, 186000, 'impayee', NULL, NULL, 2);
