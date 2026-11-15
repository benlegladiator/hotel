<?php
// Point d'entrée — redirige selon l'état de session
require_once 'config/db.php';
require_once INCLUDES_PATH . '/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
} else {
    header('Location: ' . BASE_URL . '/login.php');
}
exit;
