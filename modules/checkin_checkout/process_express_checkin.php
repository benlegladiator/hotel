<?php
// ============================================================
// modules/checkin_checkout/process_express_checkin.php
// Traitement de l'arrivée express
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/dashboard.php');
}

$current_u  = current_user();
$user_id    = (int)($current_u['id'] ?? 1);

// Protection anti-doublon (validation et invalidation immédiate du token à usage unique)
$posted_token  = $_POST['checkin_token'] ?? '';
$session_token = $_SESSION['checkin_token'] ?? '';

if (empty($posted_token) || empty($session_token) || !hash_equals($session_token, $posted_token)) {
    set_flash('error', "Cette arrivée a déjà été enregistrée ou le formulaire a expiré. Veuillez vérifier le tableau de bord pour éviter les doublons.");
    redirect(BASE_URL . '/dashboard.php');
}
unset($_SESSION['checkin_token']);

$id_client_existant = (int)($_POST['id_client_existant'] ?? 0);
$nom            = trim($_POST['nom'] ?? '');
$prenom         = trim($_POST['prenom'] ?? '');
$date_naissance = !empty($_POST['date_naissance']) ? trim($_POST['date_naissance']) : null;
$lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
$telephone      = trim($_POST['telephone'] ?? '');
$type_piece     = trim($_POST['type_piece'] ?? 'CNI');
$numero_piece   = trim($_POST['numero_piece'] ?? '');
$nationalite    = trim($_POST['nationalite'] ?? 'Camerounaise');
$adresse        = trim($_POST['adresse'] ?? '');
$email          = trim($_POST['email'] ?? '');
$type_sejour    = in_array($_POST['type_sejour'] ?? '', ['nuitee', 'sieste']) ? $_POST['type_sejour'] : 'nuitee';

$est_pour_tiers    = !empty($_POST['est_pour_tiers']);
$nom_occupant_reel = trim($_POST['nom_occupant_reel'] ?? '');

$id_chambre   = (int)($_POST['id_chambre'] ?? 0);
$nb_nuits     = max(1, (int)($_POST['nb_nuits'] ?? 1));
$date_arrivee = !empty($_POST['date_arrivee']) ? $_POST['date_arrivee'] : date('Y-m-d');
$montant_paye = (float)($_POST['montant_paye'] ?? 0);
$mode_paiement= in_array($_POST['mode_paiement'] ?? '', ['especes', 'carte', 'mobile', 'autre']) ? $_POST['mode_paiement'] : 'especes';

// Formatage propre de la pièce d'identité
$piece_identite = $numero_piece;
if ($numero_piece && $type_piece && stripos($numero_piece, $type_piece) === false) {
    $piece_identite = "$type_piece $numero_piece";
}

if (empty($nom) || empty($prenom) || empty($numero_piece) || $id_chambre <= 0) {
    set_flash('error', "Veuillez renseigner le nom, le prénom, le numéro de CNI / pièce d'identité et choisir une chambre.");
    redirect('express_checkin.php');
}

try {
    $pdo->beginTransaction();

    // 1. Récupérer ou créer le client
    $id_client = $id_client_existant;
    if ($id_client <= 0) {
        $stmt_cl = $pdo->prepare("SELECT id FROM clients WHERE nom = ? AND prenom = ? LIMIT 1");
        $stmt_cl->execute([$nom, $prenom]);
        $existing = $stmt_cl->fetchColumn();

        if ($existing) {
            $id_client = (int)$existing;
            $pdo->prepare("
                UPDATE clients 
                SET date_naissance = IF(? IS NOT NULL, ?, date_naissance),
                    lieu_naissance = IF(? != '', ?, lieu_naissance),
                    telephone = IF(? != '', ?, telephone),
                    piece_identite = IF(? != '', ?, piece_identite),
                    nationalite = IF(? != '', ?, nationalite),
                    adresse = IF(? != '', ?, adresse),
                    email = IF(? != '', ?, email),
                    nb_sejours = nb_sejours + 1 
                WHERE id = ?
            ")->execute([
                $date_naissance, $date_naissance,
                $lieu_naissance, $lieu_naissance,
                $telephone, $telephone,
                $piece_identite, $piece_identite,
                $nationalite, $nationalite,
                $adresse, $adresse,
                $email, $email,
                $id_client
            ]);
        } else {
            $stmt_ins_c = $pdo->prepare("
                INSERT INTO clients (nom, prenom, date_naissance, lieu_naissance, telephone, piece_identite, nationalite, adresse, email, nb_sejours) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt_ins_c->execute([$nom, $prenom, $date_naissance, $lieu_naissance ?: null, $telephone, $piece_identite, $nationalite, $adresse, $email]);
            $id_client = (int)$pdo->lastInsertId();
        }
    } else {
        $pdo->prepare("
            UPDATE clients 
            SET date_naissance = IF(? IS NOT NULL, ?, date_naissance),
                lieu_naissance = IF(? != '', ?, lieu_naissance),
                telephone = IF(? != '', ?, telephone),
                piece_identite = IF(? != '', ?, piece_identite),
                nationalite = IF(? != '', ?, nationalite),
                adresse = IF(? != '', ?, adresse),
                email = IF(? != '', ?, email),
                nb_sejours = nb_sejours + 1 
            WHERE id = ?
        ")->execute([
            $date_naissance, $date_naissance,
            $lieu_naissance, $lieu_naissance,
            $telephone, $telephone,
            $piece_identite, $piece_identite,
            $nationalite, $nationalite,
            $adresse, $adresse,
            $email, $email,
            $id_client
        ]);
    }

    // 1b. Contrôle d'unicité : vérifier si le client a déjà une réservation en cours
    $stmt_active = $pdo->prepare("
        SELECT r.id, ch.numero 
        FROM reservations r 
        JOIN chambres ch ON r.id_chambre = ch.id 
        WHERE r.id_client = ? AND r.statut = 'en_cours'
        LIMIT 1
    ");
    $stmt_active->execute([$id_client]);
    $deja_loge = $stmt_active->fetch();

    if ($deja_loge && !$est_pour_tiers) {
        throw new Exception("Ce client occupe déjà la Chambre N° {$deja_loge['numero']}. Dans la logique de l'hôtel, une personne ne peut pas occuper deux chambres en même temps pour elle-même. Veuillez clôturer son séjour précédent ou déclarer un accompagnateur / tiers.");
    }

    if ($deja_loge && $est_pour_tiers && empty($nom_occupant_reel)) {
        throw new Exception("Veuillez renseigner le nom complet du véritable occupant pour cette 2ème chambre (conformité fiche de police obligatoire).");
    }

    // 2. Récupérer, verrouiller et vérifier le statut de la chambre
    $stmt_ch = $pdo->prepare("
        SELECT c.id, c.numero, c.statut, tc.nom as type_nom, tc.prix_nuit, tc.prix_sieste 
        FROM chambres c 
        JOIN types_chambres tc ON c.id_type_chambre = tc.id 
        WHERE c.id = ? 
        FOR UPDATE
    ");
    $stmt_ch->execute([$id_chambre]);
    $chambre = $stmt_ch->fetch();

    if (!$chambre) {
        throw new Exception("Chambre introuvable.");
    }

    if ($chambre['statut'] === 'occupee') {
        throw new Exception("La chambre N° {$chambre['numero']} est actuellement OCCUPÉE par un autre occupant. Impossible d'y enregistrer une nouvelle arrivée.");
    }

    if ($chambre['statut'] === 'nettoyage') {
        throw new Exception("La chambre N° {$chambre['numero']} est actuellement EN COURS DE NETTOYAGE. Veuillez d'abord valider son nettoyage (statut propre) avant de pouvoir y installer un client.");
    }

    if ($chambre['statut'] !== 'libre') {
        throw new Exception("La chambre N° {$chambre['numero']} n'est pas disponible pour attribution (Statut actuel : {$chambre['statut']}).");
    }

    if ($type_sejour === 'sieste') {
        $montant_total = (float)($chambre['prix_sieste'] ?? 10000);
        $date_depart = $date_arrivee;
        $remarque_sejour = 'Sieste / Passage';
        $remarque_hist = 'Arrivée Express - Sieste / Passage';
    } else {
        $prix_nuit = (float)$chambre['prix_nuit'];
        $montant_total = $prix_nuit * $nb_nuits;
        $date_depart = date('Y-m-d', strtotime("$date_arrivee +$nb_nuits days"));
        $remarque_sejour = 'Arrivée Express Walk-in (' . $nb_nuits . ' nuit(s))';
        $remarque_hist = 'Arrivée Express (' . $nb_nuits . ' nuit(s))';
    }

    if ($est_pour_tiers && !empty($nom_occupant_reel)) {
        $remarque_sejour .= " [Occupant réel : $nom_occupant_reel (Payeur : $nom $prenom)]";
        $remarque_hist   .= " [Occupant réel : $nom_occupant_reel]";
    }

    // 3. Créer la réservation (statut en_cours)
    $stmt_res = $pdo->prepare("
        INSERT INTO reservations (id_client, id_chambre, type_reservation, date_arrivee, date_depart, statut, nb_personnes, remarques, id_receptionniste)
        VALUES (?, ?, 'chambre', ?, ?, 'en_cours', 1, ?, ?)
    ");
    $stmt_res->execute([$id_client, $id_chambre, $date_arrivee, $date_depart, $remarque_sejour, $user_id]);
    $id_reservation = (int)$pdo->lastInsertId();

    // 4. Mettre la chambre en statut 'occupee'
    $pdo->prepare("UPDATE chambres SET statut = 'occupee' WHERE id = ?")->execute([$id_chambre]);

    // 5. Créer la facture
    $statut_paiement = ($montant_paye >= $montant_total) ? 'payee' : ($montant_paye > 0 ? 'partielle' : 'impayee');
    $stmt_fac = $pdo->prepare("
        INSERT INTO factures (id_client, id_reservation, date_emission, montant_sejour, montant_services, montant_total, montant_final, statut_paiement, mode_paiement, id_receptionniste)
        VALUES (?, ?, CURDATE(), ?, 0, ?, ?, ?, ?, ?)
    ");
    $stmt_fac->execute([$id_client, $id_reservation, $montant_total, $montant_total, $montant_total, $statut_paiement, $mode_paiement, $user_id]);
    $id_facture = (int)$pdo->lastInsertId();

    // 6. Enregistrer l'historique d'occupation
    $stmt_hist = $pdo->prepare("
        INSERT INTO historique_occupation (id_chambre, id_reservation, id_client, date_arrivee, date_depart_prevue, statut_sejour, id_receptionniste, remarques)
        VALUES (?, ?, ?, NOW(), ?, 'en_cours', ?, ?)
    ");
    $stmt_hist->execute([$id_chambre, $id_reservation, $id_client, $date_depart . ' 12:00:00', $user_id, $remarque_hist]);

    $pdo->commit();

    // 7. Notification automatique au Boss (WhatsApp)
    $wa_details = "🛏️ *Chambre {$chambre['numero']}* ({$chambre['type_nom']})\n" .
                  "👤 *Client :* $nom $prenom\n" .
                  "📋 *Formule :* $remarque_sejour\n" .
                  "💳 *Règlement :* " . ucfirst($mode_paiement);
    notify_boss_whatsapp("Nouvelle Arrivée Encaissée", $wa_details, (float)$montant_paye);

    set_flash('success', "🎉 Client $nom $prenom installé en Chambre {$chambre['numero']} ($remarque_sejour) ! Encaissement de " . format_prix($montant_paye) . " enregistré.");
    redirect(BASE_URL . "/modules/facturation/print.php?id=$id_facture");

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', "Erreur lors de l'enregistrement : " . $e->getMessage());
    redirect('express_checkin.php');
}
