<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = post('nom');
    $categorie = post('categorie');
    $prix = post_float('prix');
    $description = post('description');
    $disponible = isset($_POST['disponible']) ? 1 : 0;
    
    if ($nom && $categorie && $prix !== null) {
        $stmt = $pdo->prepare("INSERT INTO catalogue_services (nom, categorie, description, prix, disponible, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$nom, $categorie, $description, $prix, $disponible]);
    }
}

redirect('list.php');
