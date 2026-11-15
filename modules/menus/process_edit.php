<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = post_int('id');
    $titre = post('titre');
    $id_chef = post_int('id_chef');
    $description = post('description');
    $date_publication = post('date_publication');
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    $services = $_POST['service_id'] ?? [];
    
    if ($id && $titre && $id_chef) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE menus_cuisine SET id_chef = ?, titre = ?, description = ?, date_publication = ?, disponible = ? WHERE id = ?");
            $stmt->execute([$id_chef, $titre, $description, $date_publication, $disponible, $id]);
            
            $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id_menu = ?");
            $stmt->execute([$id]);
            
            $stmt_item = $pdo->prepare("INSERT INTO menu_items (id_menu, id_service) VALUES (?, ?)");
            foreach ($services as $id_service) {
                if ($id_service) {
                    $stmt_item->execute([$id, $id_service]);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

redirect('list.php');
