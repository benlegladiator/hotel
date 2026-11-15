<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $date_naissance = !empty($_POST['date_naissance']) ? trim($_POST['date_naissance']) : null;
    $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
    $piece_identite = trim($_POST['piece_identite'] ?? '');
    
    if (!$id || empty($nom) || empty($prenom) || empty($piece_identite)) {
        set_flash('danger', "Le nom, prénom et le numéro de CNI / pièce d'identité sont obligatoires.");
        redirect(BASE_URL . '/modules/clients/edit.php?id=' . $id);
    }
    
    $stmt = $pdo->prepare("UPDATE clients SET nom=?, prenom=?, date_naissance=?, lieu_naissance=?, telephone=?, email=?, adresse=?, nationalite=?, piece_identite=?, preferences=? WHERE id=?");
    $stmt->execute([
        $nom, 
        $prenom, 
        $date_naissance,
        $lieu_naissance ?: null,
        trim($_POST['telephone'] ?? ''), 
        trim($_POST['email'] ?? ''), 
        trim($_POST['adresse'] ?? ''), 
        trim($_POST['nationalite'] ?? 'Camerounaise'), 
        $piece_identite, 
        trim($_POST['preferences'] ?? ''),
        $id
    ]);
    
    set_flash('success', 'Client modifié avec succès.');
    redirect(BASE_URL . '/modules/clients/list.php');
}
