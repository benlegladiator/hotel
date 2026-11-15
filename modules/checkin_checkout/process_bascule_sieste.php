<?php
// ============================================================
// modules/checkin_checkout/process_bascule_sieste.php
// Traitement transactionnel de la conversion Sieste ➔ Nuitée
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/checkin_checkout/checkout.php');
}

$current_u = current_user();
$user_id   = (int)($current_u['id'] ?? 1);

// Protection anti-doublon CSRF
$posted_token  = $_POST['bascule_token'] ?? '';
$session_token = $_SESSION['bascule_token'] ?? '';

if (empty($posted_token) || empty($session_token) || !hash_equals($session_token, $posted_token)) {
    set_flash('error', "Cette action a déjà été traitée ou le formulaire a expiré.");
    redirect(BASE_URL . '/modules/checkin_checkout/checkout.php');
}
unset($_SESSION['bascule_token']);

$id_reservation = (int)($_POST['id_reservation'] ?? 0);
$montant_paye   = (float)($_POST['montant_paye'] ?? 0);
$mode_paiement  = in_array($_POST['mode_paiement'] ?? '', ['especes', 'carte', 'mobile', 'autre']) ? $_POST['mode_paiement'] : 'especes';

if ($id_reservation <= 0) {
    set_flash('error', "Réservation invalide.");
    redirect('checkout.php');
}

try {
    $pdo->beginTransaction();

    // 1. Verrouiller la réservation et obtenir les infos tarifaires de la chambre
    $stmt = $pdo->prepare("
        SELECT r.*, c.nom as client_nom, c.prenom as client_prenom,
               ch.numero as chambre_numero, tc.nom as type_nom, tc.prix_nuit, tc.prix_sieste
        FROM reservations r
        JOIN clients c ON r.id_client = c.id
        JOIN chambres ch ON r.id_chambre = ch.id
        JOIN types_chambres tc ON ch.id_type_chambre = tc.id
        WHERE r.id = ? AND r.statut = 'en_cours'
        FOR UPDATE
    ");
    $stmt->execute([$id_reservation]);
    $res = $stmt->fetch();

    if (!$res) {
        throw new Exception("Séjour introuvable ou déjà clôturé.");
    }

    // 2. Verrouiller la facture existante liée à ce séjour
    $stmt_f = $pdo->prepare("
        SELECT * FROM factures 
        WHERE id_reservation = ? 
        ORDER BY id DESC LIMIT 1 
        FOR UPDATE
    ");
    $stmt_f->execute([$id_reservation]);
    $facture = $stmt_f->fetch();

    if (!$facture) {
        throw new Exception("Facture introuvable pour ce séjour.");
    }

    // 3. Calculs des montants et nouvelle date
    $prix_nuitee = (float)$res['prix_nuit'];
    $montant_deja_paye = (float)$facture['montant_sejour'];
    $reliquat_theorique = max(0, $prix_nuitee - $montant_deja_paye);

    $nouvelle_date_depart = date('Y-m-d', strtotime($res['date_arrivee'] . ' +1 day'));
    if ($nouvelle_date_depart <= date('Y-m-d')) {
        $nouvelle_date_depart = date('Y-m-d', strtotime('+1 day'));
    }

    $nouveau_remarque_res = 'Formule Nuitée (Prolongation Sieste ➔ Nuitée — Reliquat réglé: ' . format_prix($montant_paye) . ')';

    // 4. Mettre à jour la réservation
    $stmt_up_res = $pdo->prepare("
        UPDATE reservations 
        SET date_depart = ?, remarques = ? 
        WHERE id = ?
    ");
    $stmt_up_res->execute([$nouvelle_date_depart, $nouveau_remarque_res, $id_reservation]);

    // 5. Mettre à jour la facture existante (Ajustement du montant séjour au tarif nuitée)
    $nouveau_montant_sejour = $prix_nuitee;
    $nouveau_montant_total  = $nouveau_montant_sejour + (float)$facture['montant_services'];
    $nouveau_montant_final  = $nouveau_montant_total;
    $statut_paiement = ($montant_paye >= $reliquat_theorique) ? 'payee' : 'partielle';

    $stmt_up_fac = $pdo->prepare("
        UPDATE factures 
        SET montant_sejour = ?, montant_total = ?, montant_final = ?, statut_paiement = ?, mode_paiement = ? 
        WHERE id = ?
    ");
    $stmt_up_fac->execute([
        $nouveau_montant_sejour,
        $nouveau_montant_total,
        $nouveau_montant_final,
        $statut_paiement,
        $mode_paiement,
        $facture['id']
    ]);

    // 6. Mettre à jour l'historique d'occupation
    $stmt_up_hist = $pdo->prepare("
        UPDATE historique_occupation 
        SET date_depart_prevue = ?, remarques = ? 
        WHERE id_reservation = ? AND statut_sejour = 'en_cours'
    ");
    $stmt_up_hist->execute([
        $nouvelle_date_depart . ' 12:00:00',
        'Prolongation Sieste ➔ Nuitée (Reliquat: ' . format_prix($montant_paye) . ')',
        $id_reservation
    ]);

    $pdo->commit();

    set_flash('success', "🎉 Prolongation réussie ! Chambre {$res['chambre_numero']} ({$res['client_nom']} {$res['client_prenom']}) basculée en Formule Nuitée jusqu'au " . format_date($nouvelle_date_depart) . " 12h00. Reliquat de " . format_prix($montant_paye) . " encaissé.");
    redirect(BASE_URL . "/modules/facturation/print.php?id=" . $facture['id']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', "Erreur lors de la prolongation : " . $e->getMessage());
    redirect(BASE_URL . "/modules/checkin_checkout/checkout.php");
}
