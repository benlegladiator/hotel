<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $pdo->prepare("UPDATE menus_cuisine SET disponible = 0 WHERE id = ?");
    $stmt->execute([$id]);
}

redirect('list.php');
