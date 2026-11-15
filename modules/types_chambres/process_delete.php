<?php
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect(BASE_URL . '/modules/types_chambres/list.php');
}

// Check if any chambers use this type
$stmt = $pdo->prepare("SELECT COUNT(*) FROM chambres WHERE id_type_chambre = ?");
$stmt->execute([$id]);
$count = $stmt->fetchColumn();

if ($count > 0) {
    redirect(BASE_URL . '/modules/types_chambres/list.php', 'error', 'Impossible de supprimer ce type car il est utilisé par des chambres.');
}

try {
    $stmt = $pdo->prepare("DELETE FROM types_chambres WHERE id = ?");
    $stmt->execute([$id]);
    redirect(BASE_URL . '/modules/types_chambres/list.php', 'success', 'Type de chambre supprimé avec succès.');
} catch (PDOException $e) {
    redirect(BASE_URL . '/modules/types_chambres/list.php', 'error', 'Erreur lors de la suppression.');
}
