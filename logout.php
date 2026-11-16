<?php
// ============================================================
// logout.php — Déconnexion
// ============================================================

require_once 'config/db.php';
require_once INCLUDES_PATH . '/auth.php';

// Supprimer le cookie persistant à la déconnexion volontaire
if (isset($_COOKIE['hotel_remember'])) {
    setcookie('hotel_remember', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
