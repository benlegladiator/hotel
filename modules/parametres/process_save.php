<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$user = current_user();
$user_id = $user['id'];
$section = post('section');

if ($section === 'profil') {
    $nom         = post('nom');
    $prenom      = post('prenom');
    $identifiant = post('identifiant');
    $email       = post('email');

    if ($nom && $prenom && $identifiant) {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET nom = ?, prenom = ?, identifiant = ?, email = ? WHERE id = ?");
        $stmt->execute([$nom, $prenom, $identifiant, $email, $user_id]);

        $_SESSION['user']['nom']         = $nom;
        $_SESSION['user']['prenom']      = $prenom;
        $_SESSION['user']['identifiant'] = $identifiant;
        $_SESSION['user']['email']       = $email;

        set_flash('success', 'Votre profil a été mis à jour avec succès.');
    } else {
        set_flash('danger', 'Veuillez remplir tous les champs obligatoires du profil.');
    }
    redirect('index.php?tab=profil');

} elseif ($section === 'mot_de_passe') {
    $anc_mdp     = post('anc_mdp');
    $nouveau_mdp = post('nouveau_mdp');
    $conf_mdp    = post('conf_mdp');

    // Vérifier ancien mot de passe
    $stmt = $pdo->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id = ?");
    $stmt->execute([$user_id]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($anc_mdp, $hash)) {
        set_flash('danger', 'L\'ancien mot de passe saisi est incorrect.');
    } elseif ($nouveau_mdp !== $conf_mdp) {
        set_flash('danger', 'Le nouveau mot de passe et sa confirmation ne correspondent pas.');
    } elseif (strlen($nouveau_mdp) < 4) {
        set_flash('danger', 'Le nouveau mot de passe doit contenir au moins 4 caractères.');
    } else {
        $new_hash = password_hash($nouveau_mdp, PASSWORD_BCRYPT);
        $stmt_u = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
        $stmt_u->execute([$new_hash, $user_id]);

        set_flash('success', 'Votre mot de passe a été modifié avec succès.');
    }
    redirect('index.php?tab=profil');

} elseif ($section === 'apparence') {
    $theme  = post('theme', 'dark');
    $langue = post('langue', 'fr');
    $accent = post('accent', 'gold');

    try {
        $stmt = $pdo->prepare("UPDATE utilisateurs SET theme = ?, langue = ? WHERE id = ?");
        $stmt->execute([$theme, $langue, $user_id]);
    } catch (Exception $e) {
        // silent fallback
    }

    set_system_setting('accent_defaut', $accent);

    $_SESSION['user']['theme']  = $theme;
    $_SESSION['user']['langue'] = $langue;
    $_SESSION['user']['accent'] = $accent;

    set_flash('success', 'Préférences de thème visuel et de couleur d\'accentuation enregistrées.');
    redirect('index.php?tab=apparence');

} elseif ($section === 'hotel' && is_admin()) {
    set_system_setting('nom_hotel', post('nom_hotel', 'Alma Hotel'));
    set_system_setting('telephone_hotel', post('telephone_hotel', ''));
    set_system_setting('adresse_hotel', post('adresse_hotel', ''));
    set_system_setting('email_hotel', post('email_hotel', ''));
    set_system_setting('devise', post('devise', 'FCFA'));

    set_flash('success', 'Les coordonnées de l\'établissement et l\'en-tête d\'impression ont été enregistrés.');
    redirect('index.php?tab=hotel');

} elseif ($section === 'premium' && is_admin()) {
    $enable = post('enable_premium_modules', '0');
    set_system_setting('enable_premium_modules', $enable);

    $msg = ($enable === '1') ? 'Mode Premium activé : Les modules Spa, Restauration et Salles sont désormais visibles.' : 'Mode Standard activé : Interface épurée axée sur l\'occupation et le ménage.';
    set_flash('success', $msg);
    redirect('index.php?tab=premium');

} elseif ($section === 'notifications' && is_admin()) {
    set_system_setting('boss_whatsapp_number', post('boss_whatsapp_number', '237691670626'));
    set_system_setting('boss_whatsapp_api_url', post('boss_whatsapp_api_url', ''));
    set_system_setting('boss_whatsapp_api_token', post('boss_whatsapp_api_token', ''));
    set_system_setting('boss_notify_checkin', post('boss_notify_checkin', '1'));
    set_system_setting('boss_notify_caisse', post('boss_notify_caisse', '1'));

    set_flash('success', 'Configuration des alertes WhatsApp et de la cloche sonore enregistrée.');
    redirect('index.php?tab=notifications');
}

redirect('index.php');
