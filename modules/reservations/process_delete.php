<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch();
    
    if ($res && $res['statut'] !== 'annulee') {
        $stmt = $pdo->prepare("UPDATE reservations SET statut = 'annulee' WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($res['type_reservation'] === 'chambre' && $res['id_chambre']) {
            $pdo->prepare("UPDATE chambres SET statut='libre' WHERE id=?")->execute([$res['id_chambre']]);
        } elseif ($res['type_reservation'] === 'salle' && $res['id_salle']) {
            $pdo->prepare("UPDATE salles SET statut='libre' WHERE id=?")->execute([$res['id_salle']]);
        }
        
        set_flash('success', 'Réservation annulée avec succès.');
    }
}

redirect(BASE_URL . '/modules/reservations/list.php');
