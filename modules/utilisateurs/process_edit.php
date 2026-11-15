<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id                  = post_int('id');
    $identifiant         = post('identifiant');
    $nom                 = post('nom');
    $prenom              = post('prenom');
    $email               = post('email');
    $role                = post('role', 'receptionniste');
    $actif               = post_int('actif', 1);
    $nouveau_mot_de_passe = post('nouveau_mot_de_passe');

    if ($id > 0 && $identifiant && $nom && $prenom) {
        // Vérifier unicité identifiant
        $stmt_c = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE identifiant = ? AND id != ?");
        $stmt_c->execute([$identifiant, $id]);
        if ($stmt_c->fetchColumn() > 0) {
            redirect("edit.php?id=$id", 'danger', 'Cet identifiant est déjà utilisé par un autre compte.');
        }

        if ($nouveau_mot_de_passe && strlen($nouveau_mot_de_passe) >= 4) {
            $hash = password_hash($nouveau_mot_de_passe, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE utilisateurs SET identifiant = ?, nom = ?, prenom = ?, email = ?, role = ?, actif = ?, mot_de_passe = ? WHERE id = ?");
            $stmt->execute([$identifiant, $nom, $prenom, $email, $role, $actif, $hash, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE utilisateurs SET identifiant = ?, nom = ?, prenom = ?, email = ?, role = ?, actif = ? WHERE id = ?");
            $stmt->execute([$identifiant, $nom, $prenom, $email, $role, $actif, $id]);
        }

        set_flash('success', "Le compte utilisateur '$identifiant' a été mis à jour.");
        redirect('list.php');
    }
}

redirect('list.php');
