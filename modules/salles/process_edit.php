<?php
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/modules/salles/list.php');
}

$id = $_POST['id'] ?? null;
$nom = trim($_POST['nom'] ?? '');
$capacite = trim($_POST['capacite'] ?? '');
$prix_location = trim($_POST['prix_location'] ?? '');
$statut = $_POST['statut'] ?? 'libre';
$description = trim($_POST['description'] ?? '');

if (!$id || empty($nom) || $capacite === '' || $prix_location === '') {
    redirect(BASE_URL . "/modules/salles/edit.php?id=$id", 'error', 'Veuillez remplir les champs obligatoires.');
}

try {
    $stmt = $pdo->prepare("UPDATE salles SET nom = ?, capacite = ?, prix_location = ?, statut = ?, description = ? WHERE id = ?");
    $stmt->execute([$nom, $capacite, $prix_location, $statut, $description, $id]);
    redirect(BASE_URL . '/modules/salles/list.php', 'success', 'Salle modifiée avec succès.');
} catch (PDOException $e) {
    redirect(BASE_URL . "/modules/salles/edit.php?id=$id", 'error', 'Erreur lors de la modification : ' . $e->getMessage());
}
