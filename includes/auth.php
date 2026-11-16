<?php
// ============================================================
// Gestion des sessions et des rôles
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', 2592000);
    ini_set('session.gc_maxlifetime', 2592000);
    if (!headers_sent()) {
        session_set_cookie_params([
            'lifetime' => 2592000,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

// Auto-reconnexion transparente via Cookie Persistant Alma Hotel (90 jours) - UNIQUEMENT SUR MOBILE
$is_mobile_client = (bool) preg_match('/(android|iphone|ipad|ipod|mobile|phone|blackberry|opera mini|windows phone)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
if ($is_mobile_client && empty($_SESSION['user_id']) && !empty($_COOKIE['hotel_remember']) && isset($pdo)) {
    $cookie_parts = explode(':', $_COOKIE['hotel_remember'], 2);
    if (count($cookie_parts) === 2) {
        $c_uid   = intval($cookie_parts[0]);
        $c_token = $cookie_parts[1];
        if ($c_uid > 0) {
            try {
                $stmt_auto = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ? AND actif = 1 LIMIT 1");
                $stmt_auto->execute([$c_uid]);
                $auto_user = $stmt_auto->fetch();
                if ($auto_user) {
                    $expected_token = hash_hmac('sha256', $auto_user['id'] . $auto_user['mot_de_passe'], 'ALMA_HOTEL_SECRET_2026');
                    if (hash_equals($expected_token, $c_token)) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $auto_user['id'];
                        $_SESSION['nom']     = $auto_user['nom'];
                        $_SESSION['prenom']  = $auto_user['prenom'];
                        $_SESSION['role']    = $auto_user['role'];
                    }
                }
            } catch (Throwable $e) {}
        }
    }
}

/** Redirige vers login si non connecté */
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/** Redirige si pas admin */
function require_admin(): void {
    require_login();
    if ($_SESSION['role'] !== 'admin') {
        set_flash('error', 'Accès refusé. Droits administrateur requis.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function is_admin(): bool {
    return ($_SESSION['role'] ?? '') === 'admin';
}

function is_chef_receptionniste(): bool {
    return ($_SESSION['role'] ?? '') === 'chef_receptionniste';
}

function is_receptionniste(): bool {
    return ($_SESSION['role'] ?? '') === 'receptionniste';
}

function is_supervisor(): bool {
    return in_array($_SESSION['role'] ?? '', ['admin', 'chef_receptionniste']);
}

function role_label(string $role): string {
    $labels = [
        'admin'               => 'Direction (Boss)',
        'chef_receptionniste' => 'Chef Réceptionniste',
        'receptionniste'      => 'Réceptionniste',
    ];
    return $labels[$role] ?? ucfirst($role);
}

/** Retourne les infos de l'utilisateur connecté */
function current_user(): array {
    return [
        'id'     => $_SESSION['user_id'] ?? null,
        'nom'    => $_SESSION['nom']     ?? '',
        'prenom' => $_SESSION['prenom']  ?? '',
        'role'   => $_SESSION['role']    ?? '',
    ];
}
