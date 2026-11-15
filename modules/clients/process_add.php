<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom            = trim($_POST['nom'] ?? '');
    $prenom         = trim($_POST['prenom'] ?? '');
    $date_naissance = !empty($_POST['date_naissance']) ? trim($_POST['date_naissance']) : null;
    $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
    $telephone      = trim($_POST['telephone'] ?? '');
    $type_piece     = trim($_POST['type_piece'] ?? 'CNI');
    $numero_piece   = trim($_POST['numero_piece'] ?? ($_POST['piece_identite'] ?? ''));
    $nationalite    = trim($_POST['nationalite'] ?? 'Camerounaise');
    $adresse        = trim($_POST['adresse'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $preferences    = trim($_POST['preferences'] ?? '');
    
    // Formatage propre de la pièce d'identité
    $piece_identite = $numero_piece;
    if ($numero_piece && $type_piece && stripos($numero_piece, $type_piece) === false) {
        $piece_identite = "$type_piece $numero_piece";
    }

    if (empty($nom) || empty($prenom) || empty($numero_piece)) {
        set_flash('danger', "Le nom, prénom et le numéro de CNI / pièce d'identité sont obligatoires.");
        redirect(BASE_URL . '/modules/clients/add.php');
    }
    
    $stmt = $pdo->prepare("INSERT INTO clients (nom, prenom, date_naissance, lieu_naissance, telephone, email, adresse, nationalite, piece_identite, preferences, nb_sejours) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([
        $nom, 
        $prenom, 
        $date_naissance,
        $lieu_naissance ?: null,
        $telephone, 
        $email, 
        $adresse, 
        $nationalite, 
        $piece_identite, 
        $preferences
    ]);
    
    set_flash('success', "Client $nom $prenom (CNI: $piece_identite) enregistré avec succès.");
    redirect(BASE_URL . '/modules/clients/list.php');
}
