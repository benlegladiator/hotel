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
    $notes = post('notes');
    $id_receptionniste = $_SESSION['user_id'] ?? 1; // Fallback to 1 if no session user id
    
    $services = $_POST['service_id'] ?? [];
    $quantites = $_POST['quantite'] ?? [];
    
    if ($id_client) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO commandes (id_client, id_reservation, id_receptionniste, statut, notes, created_at) VALUES (?, ?, ?, 'en_cours', ?, NOW())");
            $stmt->execute([$id_client, $id_reservation, $id_receptionniste, $notes]);
            $id_commande = $pdo->lastInsertId();
            
            $stmt_service = $pdo->prepare("SELECT prix FROM catalogue_services WHERE id = ?");
            $stmt_ligne = $pdo->prepare("INSERT INTO commande_lignes (id_commande, id_service, quantite, prix_unitaire, sous_total) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($services as $index => $id_service) {
                $quantite = intval($quantites[$index] ?? 0);
                if ($id_service && $quantite > 0) {
                    $stmt_service->execute([$id_service]);
                    $service = $stmt_service->fetch();
                    if ($service) {
                        $prix = $service['prix'];
                        $sous_total = $prix * $quantite;
                        $stmt_ligne->execute([$id_commande, $id_service, $quantite, $prix, $sous_total]);
                    }
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            // Handle error minimally
        }
    }
}

redirect('list.php');
