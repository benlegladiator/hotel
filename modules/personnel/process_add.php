<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = post('nom');
    $prenom = post('prenom');
    $telephone = post('telephone');
    $role = post('role');
    $actif = post_int('actif', 0);
    $notes = post('notes');

    if ($nom && $prenom && $role) {
        $stmt = $pdo->prepare("INSERT INTO personnel (nom, prenom, telephone, role, actif, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nom, $prenom, $telephone, $role, $actif, $notes]);
    }
}

redirect('list.php');
