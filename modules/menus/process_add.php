<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = post('titre');
    $id_chef = post_int('id_chef');
    $description = post('description');
    $date_publication = post('date_publication');
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    $services = $_POST['service_id'] ?? [];
    
    if ($titre && $id_chef) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO menus_cuisine (id_chef, titre, description, date_publication, disponible, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$id_chef, $titre, $description, $date_publication, $disponible]);
            $id_menu = $pdo->lastInsertId();
            
            $stmt_item = $pdo->prepare("INSERT INTO menu_items (id_menu, id_service) VALUES (?, ?)");
            foreach ($services as $id_service) {
                if ($id_service) {
                    $stmt_item->execute([$id_menu, $id_service]);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

redirect('list.php');
