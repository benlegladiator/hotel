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
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.json" crossorigin="use-credentials">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/img/pwa/favicon-32x32.png">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/img/pwa/favicon.png">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/pwa/apple-touch-icon.png">
    <meta name="theme-color" content="#1e1b4b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css?v=<?= time() ?>">
    <script>window.HOTEL_BASE_URL = '<?= BASE_URL ?>';</script>
    <script src="<?= BASE_URL ?>/assets/js/hotel-offline-sync.js?v=<?= time() ?>"></script>
</head>
<body data-theme="<?= h($theme) ?>" data-accent="<?= h($accent) ?>">
<div class="app-layout">

    <?php require_once INCLUDES_PATH . '/sidebar.php'; ?>

    <div class="app-main" id="appMain">

        <!-- ── Barre supérieure ── -->
        <header class="topbar">
            <div class="topbar-left">
                <?php 
                $curr_script = basename($_SERVER['PHP_SELF'] ?? '');
                $is_dashboard = ($curr_script === 'dashboard.php' || $curr_script === 'index.php');
                if (!$is_dashboard): 
                ?>
                    <button type="button" onclick="if(window.history.length > 1) { window.history.back(); } else { window.location.href='<?= BASE_URL ?>/dashboard.php'; }" class="btn-topbar-back" aria-label="Retour" title="Page précédente">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12"></line>
                            <polyline points="12 19 5 12 12 5"></polyline>
                        </svg>
                        <span class="back-text">Retour</span>
                    </button>
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir/fermer menu" style="padding:6px 8px;">
                        <span></span><span></span><span></span>
                    </button>
                <?php else: ?>
                    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Ouvrir/fermer menu">
                        <span></span><span></span><span></span>
                    </button>
                <?php endif; ?>
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

                <?php if (is_supervisor()): ?>
                <!-- Cloche Sonore des Notifications du Boss -->
                <div style="position: relative;" class="no-print">
                    <button type="button" id="bossBellBtn" onclick="toggleBossBellDropdown()" class="btn btn-sm btn-outline" style="border-radius: 50%; width: 34px; height: 34px; padding: 0; display: flex; align-items: center; justify-content: center; position: relative;" title="Alertes Entrées & Caisse Boss">
                        🔔
                        <span id="bossBellBadge" style="display:none; position: absolute; top: -4px; right: -4px; background: #ef4444; color: #fff; font-size: 10px; font-weight: 800; border-radius: 10px; padding: 1px 5px; min-width: 16px; text-align: center; border: 1.5px solid #fff;">0</span>
                    </button>
                    <div id="bossBellDropdown" style="display: none; position: absolute; right: 0; top: 40px; width: 320px; max-height: 400px; overflow-y: auto; background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 14px; box-shadow: 0 10px 30px rgba(0,0,0,0.18); z-index: 10001; padding: 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; margin-bottom: 10px;">
                            <strong style="font-size: 13px; color: #0f172a;">🔔 Entrées & Caisses Récentes</strong>
                            <button type="button" onclick="markAllBossBellRead()" style="background: none; border: none; font-size: 11px; color: #4f46e5; font-weight: 700; cursor: pointer;">Tout marquer lu</button>
                        </div>
                        <div id="bossBellList" style="font-size: 12px; color: #334155; display: flex; flex-direction: column; gap: 8px;">
                            <div style="text-align: center; padding: 15px; color: #94a3b8;">Chargement des alertes...</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
