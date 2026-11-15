<?php
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/salles/list.php');
}

$nom = trim($_POST['nom'] ?? '');
$capacite = trim($_POST['capacite'] ?? '');
$prix_location = trim($_POST['prix_location'] ?? '');
$statut = $_POST['statut'] ?? 'libre';
$description = trim($_POST['description'] ?? '');

if (empty($nom) || $capacite === '' || $prix_location === '') {
    redirect(BASE_URL . '/modules/salles/add.php', 'error', 'Veuillez remplir les champs obligatoires.');
}

try {
    $stmt = $pdo->prepare("INSERT INTO salles (nom, capacite, prix_location, statut, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$nom, $capacite, $prix_location, $statut, $description]);
    redirect(BASE_URL . '/modules/salles/list.php', 'success', 'Salle ajoutée avec succès.');
} catch (PDOException $e) {
    redirect(BASE_URL . '/modules/salles/add.php', 'error', 'Erreur lors de l\'ajout : ' . $e->getMessage());
}
