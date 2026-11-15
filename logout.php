<?php
// ============================================================
// logout.php — Déconnexion
// ============================================================

require_once 'config/db.php';
require_once INCLUDES_PATH . '/auth.php';

session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit;
