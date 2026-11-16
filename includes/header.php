<?php
// ============================================================
// En-tête HTML commun — à inclure en premier dans chaque page
// Requiert : $page_title (string) défini avant l'inclusion
// ============================================================
$page_title = $page_title ?? 'Dashboard';
$user   = current_user();
$flash  = get_flash();
$theme  = get_user_theme();
$accent = get_user_accent();
?>
<!DOCTYPE html>
<html lang="<?= get_user_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= h($page_title) ?> — <?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?></title>
    <meta name="description" content="Alma Hotel — Système de gestion hôtelière & caisse">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.php">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/img/pwa/favicon-32x32.png">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/pwa/favicon.png">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/pwa/apple-touch-icon.png">
    <meta name="theme-color" content="#1e1b4b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css?v=<?= time() ?>">
    <script>window.HOTEL_BASE_URL = '<?= BASE_URL ?>';</script>
    <script src="<?= BASE_URL ?>/assets/js/hotel-offline-sync.js"></script>
</head>
<body data-theme="<?= h($theme) ?>" data-accent="<?= h($accent) ?>">
<div class="app-layout">

    <?php require_once INCLUDES_PATH . '/sidebar.php'; ?>

    <div class="app-main" id="appMain">

        <!-- ── Barre supérieure ── -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir/fermer menu">
                    <span></span><span></span><span></span>
                </button>
                <span class="page-title"><?= h($page_title) ?></span>
            </div>
            <div class="topbar-right">
                <!-- Indicateur Réseau & Centre Hors-Ligne Alma Hotel -->
                <button type="button" id="hotelNetStatusBtn" onclick="openHotelOfflineModal()" class="btn btn-sm no-print" style="display:flex; align-items:center; gap:8px; border:1px solid #10b981; background:#ecfdf5; color:#047857; font-weight:700; border-radius:20px; padding:4px 12px; cursor:pointer; font-size:12px;" title="Centre Hors-Ligne & Synchronisation">
                    <span id="hotelNetStatusDot" style="width:8px; height:8px; border-radius:50%; background:#10b981; display:inline-block;"></span>
                    <span id="hotelNetStatusText">En ligne</span>
                    <span id="hotelNetPendingBadge" style="display:none; background:#d97706; color:#ffffff; font-size:10px; padding:1px 6px; border-radius:10px;">0</span>
                </button>

                <a href="<?= BASE_URL ?>/modules/parametres/index.php?tab=apparence" class="btn btn-sm btn-outline no-print" title="Changer le Thème & la Couleur" style="display:flex; align-items:center; gap:6px;">
                    🎨 <span style="font-size:12px; text-transform:capitalize;"><?= h($theme) ?> / <?= h($accent) ?></span>
                </a>
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(mb_substr($user['prenom'] ?? 'A', 0, 1) . mb_substr($user['nom'] ?? 'M', 0, 1)) ?>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= h(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? '')) ?></span>
                        <span class="user-role"><?= h(role_label($user['role'] ?? '')) ?></span>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/logout.php" class="btn-logout" title="Déconnexion">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </a>
            </div>
        </header>

        <!-- ── Message flash ── -->
        <?php if ($flash): ?>
        <div class="flash flash-<?= h($flash['type']) ?>" id="flashMsg" role="alert">
            <?= h($flash['message']) ?>
            <button class="flash-close" onclick="this.parentElement.remove()" aria-label="Fermer">×</button>
        </div>
        <?php endif; ?>

        <!-- ── Contenu principal ── -->
        <main class="main-content">
