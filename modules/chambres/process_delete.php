<?php
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect(BASE_URL . '/modules/chambres/list.php');
}

// Optionnel: vérifier qu'il n'y a pas de réservation en cours pour cette chambre
// TODO: Si une table reservations existe, ajouter une vérification ici

try {
    $stmt = $pdo->prepare("DELETE FROM chambres WHERE id = ?");
    $stmt->execute([$id]);
    redirect(BASE_URL . '/modules/chambres/list.php', 'success', 'Chambre supprimée avec succès.');
} catch (PDOException $e) {
    redirect(BASE_URL . '/modules/chambres/list.php', 'error', 'Erreur lors de la suppression.');
}
