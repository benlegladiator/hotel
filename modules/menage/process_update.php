<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$id = $_GET['id'] ?? null;
$statut = $_GET['statut'] ?? null;

if ($id && in_array($statut, ['assignee', 'en_cours', 'terminee'])) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE taches_menage SET statut = ? WHERE id = ?");
        $stmt->execute([$statut, $id]);
        
        if ($statut === 'terminee') {
            // Get chambre id
            $stmt_chambre = $pdo->prepare("SELECT id_chambre FROM taches_menage WHERE id = ?");
            $stmt_chambre->execute([$id]);
            $tache = $stmt_chambre->fetch();
            
            if ($tache) {
                // If the chambre was in nettoyage, maybe set it to libre
                $stmt_update = $pdo->prepare("UPDATE chambres SET statut = 'libre' WHERE id = ? AND statut = 'nettoyage'");
                $stmt_update->execute([$tache['id_chambre']]);
            }
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

redirect('list.php');
