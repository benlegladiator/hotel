<?php
// ============================================================
// Gestion des sessions et des rôles
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
