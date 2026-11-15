<?php
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/types_chambres/list.php');
}

$nom = trim($_POST['nom'] ?? '');
$prix_nuit = trim($_POST['prix_nuit'] ?? '');
$capacite = trim($_POST['capacite'] ?? '');
$description = trim($_POST['description'] ?? '');
$avantages = trim($_POST['avantages'] ?? '');

if (empty($nom) || $prix_nuit === '' || $capacite === '') {
    redirect(BASE_URL . '/modules/types_chambres/add.php', 'error', 'Veuillez remplir les champs obligatoires.');
}

try {
    $stmt = $pdo->prepare("INSERT INTO types_chambres (nom, description, prix_nuit, capacite, avantages) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$nom, $description, $prix_nuit, $capacite, $avantages]);
    redirect(BASE_URL . '/modules/types_chambres/list.php', 'success', 'Type de chambre ajouté avec succès.');
} catch (PDOException $e) {
    redirect(BASE_URL . '/modules/types_chambres/add.php', 'error', 'Erreur lors de l\'ajout : ' . $e->getMessage());
}
