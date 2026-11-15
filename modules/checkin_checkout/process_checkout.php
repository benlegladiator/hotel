<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_res    = (int)($_POST['id_reservation'] ?? 0);
    $id_client = (int)($_POST['id_client']      ?? 0);

    if ($id_res && $id_client) {
        // Récupère réservation + prix nuit
        $stmt = $pdo->prepare(
            "SELECT r.id_chambre, r.type_reservation, r.date_arrivee, r.date_depart,
                    t.prix_nuit
             FROM reservations r
             LEFT JOIN chambres ch ON ch.id = r.id_chambre
             LEFT JOIN types_chambres t ON t.id = ch.id_type_chambre
             WHERE r.id = ?"
        );
        $stmt->execute([$id_res]);
        $res = $stmt->fetch();

        if ($res) {
            // Calcul montant séjour
            $nuits         = nb_nuits($res['date_arrivee'], $res['date_depart']);
            $prix_nuit     = (float)($res['prix_nuit'] ?? 0);
            $mont_sejour   = $nuits * $prix_nuit;

            // Montant services (commandes liées)
            $stmt_s = $pdo->prepare(
                "SELECT COALESCE(SUM(l.sous_total),0)
                 FROM commande_lignes l
                 JOIN commandes c ON c.id = l.id_commande
                 WHERE c.id_reservation = ?"
            );
            $stmt_s->execute([$id_res]);
            $mont_services = (float)$stmt_s->fetchColumn();

            $mont_total  = $mont_sejour + $mont_services;
            $mont_final  = $mont_total;

            // Vérifie si une facture existe déjà pour cette réservation
            $stmt_fac_exist = $pdo->prepare("SELECT id FROM factures WHERE id_reservation = ? ORDER BY id DESC LIMIT 1");
            $stmt_fac_exist->execute([$id_res]);
            $id_facture = (int)$stmt_fac_exist->fetchColumn();

            if ($id_facture > 0) {
                // Mise à jour de la facture existante (règlement complet lors du départ)
                $pdo->prepare("
                    UPDATE factures 
                    SET montant_sejour = ?, montant_services = ?, montant_total = ?, montant_final = ?, statut_paiement = 'payee'
                    WHERE id = ?
                ")->execute([$mont_sejour, $mont_services, $mont_total, $mont_final, $id_facture]);
            } else {
                // Créer la facture si elle n'existait pas encore
                $pdo->prepare("
                    INSERT INTO factures
                    (id_client, id_reservation, date_emission, montant_sejour, montant_services, montant_total, montant_final, statut_paiement, id_receptionniste)
                    VALUES (?, ?, CURDATE(), ?, ?, ?, ?, 'payee', ?)
                ")->execute([$id_client, $id_res, $mont_sejour, $mont_services, $mont_total, $mont_final, $_SESSION['user_id']]);
                $id_facture = (int)$pdo->lastInsertId();
            }

            // Réservation → terminée
            $pdo->prepare("UPDATE reservations SET statut='terminee' WHERE id=?")->execute([$id_res]);

            // Chambre → statut nettoyage + création tâche de ménage
            if ($res['type_reservation'] === 'chambre' && $res['id_chambre']) {
                $pdo->prepare("UPDATE chambres SET statut='nettoyage' WHERE id=?")->execute([$res['id_chambre']]);
                $pdo->prepare("
                    INSERT INTO taches_menage (id_chambre, type_tache, date_prevue, statut) 
                    VALUES (?, 'sortie', CURDATE(), 'assignee')
                ")->execute([$res['id_chambre']]);
            }

            set_flash('success', "🚪 Check-out validé avec succès ! La chambre est libérée et passée en nettoyage.");
            redirect(BASE_URL . '/modules/facturation/print.php?id=' . $id_facture);
        }
    }
}

redirect(BASE_URL . '/modules/checkin_checkout/checkout.php', 'error', 'Données invalides.');
