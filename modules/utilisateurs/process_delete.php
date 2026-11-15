<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

$id = post_int('id', $_GET['id'] ?? 0);

if ($id > 0 && $id != current_user()['id']) {
    $stmt = $pdo->prepare("SELECT actif, identifiant FROM utilisateurs WHERE id = ?");
    $stmt->execute([$id]);
    $u = $stmt->fetch();

    if ($u) {
        $n_actif = $u['actif'] ? 0 : 1;
        $stmt_u = $pdo->prepare("UPDATE utilisateurs SET actif = ? WHERE id = ?");
        $stmt_u->execute([$n_actif, $id]);

        $msg = $n_actif ? "Le compte '{$u['identifiant']}' a été réactivé." : "Le compte '{$u['identifiant']}' a été désactivé.";
        set_flash('success', $msg);
    }
}

redirect('list.php');
