<?php
// ============================================================
// modules/checkin_checkout/sync_offline.php
// Synchronisation centralisée des arrivées et paiements hors-ligne
// ============================================================

header('Content-Type: application/json; charset=utf-8');
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

ini_set('display_errors', 0);
error_reporting(E_ALL);

$response = [
    'success' => false,
    'message' => '',
    'synced_count' => 0,
    'synced_ids' => [],
    'details' => []
];

try {
    $raw_input = file_get_contents('php://input');
    if (empty($raw_input)) {
        throw new Exception("Aucune donnée reçue pour la synchronisation.");
    }

    $payload = json_decode($raw_input, true);
    if (!is_array($payload)) {
        throw new Exception("Payload JSON invalide.");
    }

    $checkins = [];
    if (isset($payload['checkins']) && is_array($payload['checkins'])) {
        $checkins = $payload['checkins'];
    } elseif (isset($payload[0]) && is_array($payload[0])) {
        $checkins = $payload;
    } elseif (isset($payload['nom'])) {
        $checkins = [$payload];
    } else {
        throw new Exception("Format de synchronisation non reconnu.");
    }

    if (empty($checkins)) {
        $response['success'] = true;
        $response['message'] = "Aucune arrivée en attente de synchronisation.";
        echo json_encode($response);
        exit;
    }

    $current_u = current_user();
    $default_user_id = (int)($current_u['id'] ?? 1);

    $synced_ids = [];
    $details = [];

    // Requêtes réutilisables
    $stmt_find_res_ref = $pdo->prepare("SELECT id FROM reservations WHERE remarques LIKE ? LIMIT 1");

    foreach ($checkins as $item) {
        $local_id = $item['id'] ?? null;
        $offline_ref = trim($item['reference'] ?? '');

        // 1. Idempotence : si la référence hors-ligne est déjà enregistrée
        if (!empty($offline_ref)) {
            $stmt_find_res_ref->execute(['%[' . $offline_ref . ']%']);
            $existing_res_id = $stmt_find_res_ref->fetchColumn();
            if ($existing_res_id) {
                if ($local_id !== null) {
                    $synced_ids[] = $local_id;
                }
                $details[] = [
                    'local_id' => $local_id,
                    'reservation_id' => $existing_res_id,
                    'reference' => $offline_ref,
                    'status' => 'already_synced'
                ];
                continue;
            }
        }

        try {
            $pdo->beginTransaction();

            $user_id = (int)($item['user_id'] ?? $default_user_id);
            $nom = trim($item['nom'] ?? '');
            $prenom = trim($item['prenom'] ?? '');
            $date_naissance = !empty($item['date_naissance']) ? trim($item['date_naissance']) : null;
            $lieu_naissance = trim($item['lieu_naissance'] ?? '');
            $telephone = trim($item['telephone'] ?? '');
            $type_piece = trim($item['type_piece'] ?? 'CNI');
            $numero_piece = trim($item['numero_piece'] ?? '');
            $nationalite = trim($item['nationalite'] ?? 'Camerounaise');
            $adresse = trim($item['adresse'] ?? '');
            $email = trim($item['email'] ?? '');
            $type_sejour = in_array($item['type_sejour'] ?? '', ['nuitee', 'sieste']) ? $item['type_sejour'] : 'nuitee';
            
            $est_pour_tiers = !empty($item['est_pour_tiers']);
            $nom_occupant_reel = trim($item['nom_occupant_reel'] ?? '');
            
            $id_chambre = (int)($item['id_chambre'] ?? 0);
            $nb_nuits = max(1, (int)($item['nb_nuits'] ?? 1));
            $date_arrivee = !empty($item['date_arrivee']) ? $item['date_arrivee'] : date('Y-m-d');
            $montant_paye = (float)($item['montant_paye'] ?? 0);
            $mode_paiement = in_array($item['mode_paiement'] ?? '', ['especes', 'carte', 'mobile', 'autre']) ? $item['mode_paiement'] : 'especes';

            $piece_identite = $numero_piece;
            if ($numero_piece && $type_piece && stripos($numero_piece, $type_piece) === false) {
                $piece_identite = "$type_piece $numero_piece";
            }

            if (empty($nom) || empty($prenom) || $id_chambre <= 0) {
                throw new Exception("Nom, prénom et chambre obligatoires.");
            }

            // Gestion Client
            $id_client = (int)($item['id_client_existant'] ?? 0);
            if ($id_client <= 0) {
                $stmt_c = $pdo->prepare("SELECT id FROM clients WHERE nom = ? AND prenom = ? LIMIT 1");
                $stmt_c->execute([$nom, $prenom]);
                $c_found = $stmt_c->fetchColumn();
                if ($c_found) {
                    $id_client = (int)$c_found;
                    $pdo->prepare("
                        UPDATE clients 
                        SET telephone = IF(? != '', ?, telephone),
                            piece_identite = IF(? != '', ?, piece_identite),
                            nb_sejours = nb_sejours + 1
                        WHERE id = ?
                    ")->execute([$telephone, $telephone, $piece_identite, $piece_identite, $id_client]);
                } else {
                    $stmt_ins = $pdo->prepare("
                        INSERT INTO clients (nom, prenom, date_naissance, lieu_naissance, telephone, piece_identite, nationalite, adresse, email, nb_sejours)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $stmt_ins->execute([$nom, $prenom, $date_naissance, $lieu_naissance ?: null, $telephone, $piece_identite, $nationalite, $adresse, $email]);
                    $id_client = (int)$pdo->lastInsertId();
                }
            }

            // Infos Chambre
            $stmt_ch = $pdo->prepare("
                SELECT c.id, c.numero, c.statut, tc.prix_nuit, tc.prix_sieste 
                FROM chambres c 
                JOIN types_chambres tc ON c.id_type_chambre = tc.id 
                WHERE c.id = ?
            ");
            $stmt_ch->execute([$id_chambre]);
            $chambre = $stmt_ch->fetch();
            if (!$chambre) {
                throw new Exception("Chambre ID $id_chambre introuvable.");
            }

            if ($type_sejour === 'sieste') {
                $montant_total = (float)($chambre['prix_sieste'] ?? 10000);
                $date_depart = $date_arrivee;
                $remarque_sejour = 'Sieste / Passage';
            } else {
                $montant_total = (float)$chambre['prix_nuit'] * $nb_nuits;
                $date_depart = date('Y-m-d', strtotime("$date_arrivee +$nb_nuits days"));
                $remarque_sejour = "Arrivée Walk-in ($nb_nuits nuit(s))";
            }

            if (!empty($offline_ref)) {
                $remarque_sejour .= " [$offline_ref]";
            }
            if ($est_pour_tiers && !empty($nom_occupant_reel)) {
                $remarque_sejour .= " [Occupant : $nom_occupant_reel]";
            }

            // Créer la réservation
            $stmt_res = $pdo->prepare("
                INSERT INTO reservations (id_client, id_chambre, type_reservation, date_arrivee, date_depart, statut, nb_personnes, remarques, id_receptionniste)
                VALUES (?, ?, 'chambre', ?, ?, 'en_cours', 1, ?, ?)
            ");
            $stmt_res->execute([$id_client, $id_chambre, $date_arrivee, $date_depart, $remarque_sejour, $user_id]);
            $id_reservation = (int)$pdo->lastInsertId();

            // Mettre la chambre en statut 'occupee'
            $pdo->prepare("UPDATE chambres SET statut = 'occupee' WHERE id = ?")->execute([$id_chambre]);

            // Créer la facture
            $statut_paiement = ($montant_paye >= $montant_total) ? 'payee' : ($montant_paye > 0 ? 'partielle' : 'impayee');
            $stmt_fac = $pdo->prepare("
                INSERT INTO factures (id_client, id_reservation, date_emission, montant_sejour, montant_services, montant_total, montant_final, statut_paiement, mode_paiement, reference_paiement, id_receptionniste)
                VALUES (?, ?, CURDATE(), ?, 0, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_fac->execute([$id_client, $id_reservation, $montant_total, $montant_total, $montant_total, $statut_paiement, $mode_paiement, $offline_ref, $user_id]);
            $id_facture = (int)$pdo->lastInsertId();

            // Historique d'occupation
            $stmt_hist = $pdo->prepare("
                INSERT INTO historique_occupation (id_chambre, id_reservation, id_client, date_arrivee, date_depart_prevue, statut_sejour, id_receptionniste, remarques)
                VALUES (?, ?, ?, NOW(), ?, 'en_cours', ?, ?)
            ");
            $stmt_hist->execute([$id_chambre, $id_reservation, $id_client, $date_depart . ' 12:00:00', $user_id, $remarque_sejour]);

            $pdo->commit();

            if ($local_id !== null) {
                $synced_ids[] = $local_id;
            }
            $details[] = [
                'local_id' => $local_id,
                'reservation_id' => $id_reservation,
                'facture_id' => $id_facture,
                'reference' => $offline_ref,
                'status' => 'synced'
            ];
        } catch (Exception $subEx) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $details[] = [
                'local_id' => $local_id,
                'error' => $subEx->getMessage(),
                'status' => 'failed'
            ];
        }
    }

    $response['success'] = count($synced_ids) > 0;
    $response['synced_count'] = count($synced_ids);
    $response['synced_ids'] = $synced_ids;
    $response['details'] = $details;
    $response['message'] = count($synced_ids) . " arrivée(s) synchronisée(s) avec succès.";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
