<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE id_client = ? AND statut IN ('en_cours', 'confirmee')");
    $stmt->execute([$id]);
    $has_active = $stmt->fetchColumn();
    
    if ($has_active > 0) {
        set_flash('danger', 'Impossible de supprimer ce client : il a des réservations actives.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        set_flash('success', 'Client supprimé avec succès.');
    }
}

redirect(BASE_URL . '/modules/clients/list.php');
