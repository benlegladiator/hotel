<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type_checkin = post('type_checkin', 'reservation');
    $user_id      = current_user()['id'];

    if ($type_checkin === 'direct') {
        $id_client          = post_int('id_client');
        $id_chambre         = post_int('id_chambre');
        $date_arrivee       = post('date_arrivee', date('Y-m-d H:i:s'));
        $date_depart_prevue = post('date_depart_prevue', date('Y-m-d H:i:s', strtotime('+1 day')));
        $remarques          = post('remarques');

        if ($id_client > 0 && $id_chambre > 0) {
            // 1. Mettre la chambre en statut 'occupee'
            $stmt_ch = $pdo->prepare("UPDATE chambres SET statut = 'occupee' WHERE id = ?");
            $stmt_ch->execute([$id_chambre]);

            // 2. Insérer dans l'historique d'occupation
            $stmt_h = $pdo->prepare("INSERT INTO historique_occupation (id_chambre, id_client, date_arrivee, date_depart_prevue, statut_sejour, id_receptionniste, remarques, created_at) VALUES (?, ?, ?, ?, 'en_cours', ?, ?, NOW())");
            $stmt_h->execute([$id_chambre, $id_client, $date_arrivee, $date_depart_prevue, $user_id, $remarques]);

            // 3. Créer automatiquement une réservation enregistrée 'en_cours'
            $stmt_r = $pdo->prepare("INSERT INTO reservations (id_client, type_reservation, id_chambre, date_arrivee, date_depart, nb_personnes, statut, id_receptionniste, remarques, created_at) VALUES (?, 'chambre', ?, ?, ?, 1, 'en_cours', ?, ?, NOW())");
            $stmt_r->execute([$id_client, $id_chambre, date('Y-m-d', strtotime($date_arrivee)), date('Y-m-d', strtotime($date_depart_prevue)), $user_id, $remarques]);

            // 4. Incrémenter le nombre de séjours du client
            $stmt_u = $pdo->prepare("UPDATE clients SET nb_sejours = nb_sejours + 1 WHERE id = ?");
            $stmt_u->execute([$id_client]);

            set_flash('success', 'Check-in direct effectué avec succès pour le client existant. La chambre est désormais occupée.');
            redirect(BASE_URL . '/modules/chambres/tracabilite.php');
        } else {
            set_flash('danger', 'Veuillez sélectionner un client et une chambre.');
            redirect('checkin.php');
        }
    } else {
        // Mode réservation confirmée classique
        $id_res = post_int('id_reservation');
        
        if ($id_res) {
            $stmt = $pdo->prepare("SELECT id_client, id_chambre, type_reservation, date_arrivee, date_depart FROM reservations WHERE id = ? AND statut = 'confirmee'");
            $stmt->execute([$id_res]);
            $res = $stmt->fetch();
            
            if ($res) {
                // Passer réservation en_cours
                $pdo->prepare("UPDATE reservations SET statut = 'en_cours' WHERE id = ?")->execute([$id_res]);
                
                // Passer chambre occupée
                if ($res['type_reservation'] === 'chambre' && $res['id_chambre']) {
                    $pdo->prepare("UPDATE chambres SET statut = 'occupee' WHERE id = ?")->execute([$res['id_chambre']]);

                    // Créer traçabilité d'historique d'occupation
                    $stmt_h = $pdo->prepare("INSERT INTO historique_occupation (id_chambre, id_reservation, id_client, date_arrivee, date_depart_prevue, statut_sejour, id_receptionniste, created_at) VALUES (?, ?, ?, NOW(), ?, 'en_cours', ?, NOW())");
                    $stmt_h->execute([$res['id_chambre'], $id_res, $res['id_client'], $res['date_depart'] . ' 12:00:00', $user_id]);
                }
                
                // Incrémenter nb_sejours
                $pdo->prepare("UPDATE clients SET nb_sejours = nb_sejours + 1 WHERE id = ?")->execute([$res['id_client']]);
                
                set_flash('success', 'Check-in de la réservation effectué avec succès.');
                redirect(BASE_URL . '/modules/chambres/tracabilite.php');
            } else {
                set_flash('danger', 'Réservation invalide ou déjà traitée.');
                redirect('checkin.php');
            }
        }
    }
}

redirect('checkin.php');
