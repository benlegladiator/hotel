<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = post_int('id');
    $date_emission = post('date_emission');
    $reduction_pct = post_float('reduction_pct', 0);
    $reduction_motif = post('reduction_motif');
    $mode_paiement = post('mode_paiement');
    $reference_paiement = post('reference_paiement');
    $statut_paiement = post('statut_paiement');

    if ($id) {
        $stmt = $pdo->prepare("SELECT montant_total FROM factures WHERE id = ?");
        $stmt->execute([$id]);
        $f = $stmt->fetch();
        
        if ($f) {
            $montant_total = $f['montant_total'];
            $montant_final = $montant_total - ($montant_total * ($reduction_pct / 100));
            
            $update = $pdo->prepare("UPDATE factures SET date_emission = ?, reduction_pct = ?, reduction_motif = ?, montant_final = ?, statut_paiement = ?, mode_paiement = ?, reference_paiement = ? WHERE id = ?");
            $update->execute([$date_emission, $reduction_pct, $reduction_motif, $montant_final, $statut_paiement, $mode_paiement, $reference_paiement, $id]);
        }
    }
}
redirect('list.php');
