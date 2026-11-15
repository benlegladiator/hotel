<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = post_int('id');
    $nom = post('nom');
    $categorie = post('categorie');
    $prix = post_float('prix');
    $description = post('description');
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    
    if ($id && $nom && $categorie && $prix !== null) {
        $stmt = $pdo->prepare("UPDATE catalogue_services SET nom = ?, categorie = ?, description = ?, prix = ?, disponible = ? WHERE id = ?");
        $stmt->execute([$nom, $categorie, $description, $prix, $disponible, $id]);
    }
}

redirect('list.php');
