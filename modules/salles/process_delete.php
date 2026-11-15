<?php
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect(BASE_URL . '/modules/salles/list.php');
}

try {
    $stmt = $pdo->prepare("DELETE FROM salles WHERE id = ?");
    $stmt->execute([$id]);
    redirect(BASE_URL . '/modules/salles/list.php', 'success', 'Salle supprimée avec succès.');
} catch (PDOException $e) {
    redirect(BASE_URL . '/modules/salles/list.php', 'error', 'Erreur lors de la suppression.');
}
