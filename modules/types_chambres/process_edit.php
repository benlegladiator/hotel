<?php
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/types_chambres/list.php');
}

$id = $_POST['id'] ?? null;
$nom = trim($_POST['nom'] ?? '');
$prix_nuit = trim($_POST['prix_nuit'] ?? '');
$capacite = trim($_POST['capacite'] ?? '');
$description = trim($_POST['description'] ?? '');
$avantages = trim($_POST['avantages'] ?? '');

if (!$id || empty($nom) || $prix_nuit === '' || $capacite === '') {
    redirect(BASE_URL . "/modules/types_chambres/edit.php?id=$id", 'error', 'Veuillez remplir les champs obligatoires.');
}

try {
    $stmt = $pdo->prepare("UPDATE types_chambres SET nom = ?, description = ?, prix_nuit = ?, capacite = ?, avantages = ? WHERE id = ?");
    $stmt->execute([$nom, $description, $prix_nuit, $capacite, $avantages, $id]);
    redirect(BASE_URL . '/modules/types_chambres/list.php', 'success', 'Type de chambre modifié avec succès.');
} catch (PDOException $e) {
    redirect(BASE_URL . "/modules/types_chambres/edit.php?id=$id", 'error', 'Erreur lors de la modification : ' . $e->getMessage());
}
