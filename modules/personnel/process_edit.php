<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = post_int('id');
    $nom = post('nom');
    $prenom = post('prenom');
    $telephone = post('telephone');
    $role = post('role');
    $actif = post_int('actif', 0);
    $notes = post('notes');

    if ($id && $nom && $prenom && $role) {
        $stmt = $pdo->prepare("UPDATE personnel SET nom = ?, prenom = ?, telephone = ?, role = ?, actif = ?, notes = ? WHERE id = ?");
        $stmt->execute([$nom, $prenom, $telephone, $role, $actif, $notes, $id]);
    }
}

redirect('list.php');
