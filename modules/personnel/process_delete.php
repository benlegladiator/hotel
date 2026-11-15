<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE personnel SET actif = 0 WHERE id = ?");
    $stmt->execute([$id]);
}

redirect('list.php');
