<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifiant  = post('identifiant');
    $nom          = post('nom');
    $prenom       = post('prenom');
    $email        = post('email');
    $role         = post('role', 'receptionniste');
    $mot_de_passe = post('mot_de_passe');

    if ($identifiant && $nom && $prenom && $mot_de_passe) {
        // Vérifier identifiant unique
        $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE identifiant = ?");
        $stmt_c->execute([$identifiant]);
        if ($stmt_c->fetchColumn() > 0) {
            redirect('add.php', 'danger', 'Cet identifiant de connexion est déjà utilisé par un autre compte.');
        }

        $hash = password_hash($mot_de_passe, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO utilisateurs (identifiant, nom, prenom, email, mot_de_passe, role, actif, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$identifiant, $nom, $prenom, $email, $hash, $role]);

        set_flash('success', "Le compte utilisateur '$identifiant' a été créé avec succès.");
        redirect('list.php');
    } else {
        redirect('add.php', 'danger', 'Veuillez remplir tous les champs obligatoires.');
    }
}

redirect('list.php');
