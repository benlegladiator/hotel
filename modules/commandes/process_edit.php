<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = post_int('id');
    $statut = post('statut');
    $notes = post('notes');
    
    if ($id && in_array($statut, ['en_cours', 'servie', 'annulee'])) {
        $stmt = $pdo->prepare("UPDATE commandes SET statut = ?, notes = ? WHERE id = ?");
        $stmt->execute([$statut, $notes, $id]);
    }
}

redirect('list.php');
