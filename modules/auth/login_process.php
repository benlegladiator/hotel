<?php
// ============================================================
// login_process.php — Traitement du formulaire de connexion
// ============================================================

require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/login.php');
}

$identifiant = trim($_POST['identifiant'] ?? '');
$password    = $_POST['mot_de_passe'] ?? '';

// Validation basique
if (!$identifiant || !$password) {
    redirect(BASE_URL . '/login.php?error=invalid');
}

// Recherche utilisateur par identifiant
$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE identifiant = ? LIMIT 1");
$stmt->execute([$identifiant]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['mot_de_passe'])) {
    redirect(BASE_URL . '/login.php?error=invalid');
}

if (!$user['actif']) {
    redirect(BASE_URL . '/login.php?error=inactive');
}

// Création session
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['nom']     = $user['nom'];
$_SESSION['prenom']  = $user['prenom'];
$_SESSION['role']    = $user['role'];

redirect(BASE_URL . '/dashboard.php');
