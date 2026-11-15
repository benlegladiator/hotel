<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_client = post_int('id_client');
    $id_reservation = post_int('id_reservation');
    if (!$id_reservation) $id_reservation = null;
    
    $date_emission = post('date_emission');
    $montant_sejour = post_float('montant_sejour', 0);
    $montant_services = post_float('montant_services', 0);
    $reduction_pct = post_float('reduction_pct', 0);
    $reduction_motif = post('reduction_motif');
    $mode_paiement = post('mode_paiement');
    $reference_paiement = post('reference_paiement');
    $statut_paiement = post('statut_paiement');
    
    $montant_total = $montant_sejour + $montant_services;
    $montant_final = $montant_total - ($montant_total * ($reduction_pct / 100));
    $id_receptionniste = $_SESSION['user_id'] ?? null;

    if ($id_client) {
        $stmt = $pdo->prepare("INSERT INTO factures 
            (id_client, id_reservation, date_emission, montant_sejour, montant_services, montant_total, reduction_pct, reduction_motif, montant_final, statut_paiement, mode_paiement, reference_paiement, id_receptionniste) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $id_client, $id_reservation, $date_emission, $montant_sejour, $montant_services, 
            $montant_total, $reduction_pct, $reduction_motif, $montant_final, 
            $statut_paiement, $mode_paiement, $reference_paiement, $id_receptionniste
        ]);
        
        $new_id = $pdo->lastInsertId();
        redirect("print.php?id=$new_id");
    }
}
redirect('list.php');
