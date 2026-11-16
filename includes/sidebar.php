<?php
// ============================================================
// sidebar.php — Navigation Latérale Dynamique (Boss vs Caissière)
// ============================================================

$_uri = $_SERVER['REQUEST_URI'] ?? '';

function nav_active(string ...$segments): string {
    global $_uri;
    foreach ($segments as $seg) {
        if (strpos($_uri, $seg) !== false) return 'active';
    }
    return '';
}

function icon(string $name): string {
    $icons = [
        'dashboard'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'bed'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M6 8v-4"/></svg>',
        'users'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>',
        'calendar'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'log-in'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>',
        'broom'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21l9-9"/><path d="M12.22 6.22L19 5l-5 10-4-4 2.22-4.78z"/></svg>',
        'wallet'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
        'file-text'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'bar-chart'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>',
        'settings'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    ];
    return $icons[$name] ?? '';
}

$hotel_name = get_system_setting('nom_hotel', 'Alma Hotel');
$is_admin   = is_admin();
?>
<!-- Fond sombre au clic pour fermer le menu sur mobile -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div class="sidebar-hotel-name"><?= h($hotel_name) ?></div>
            <div class="sidebar-hotel-subtitle"><?= $is_admin ? 'Supervision Patron' : 'Gestion Hôtelière & Caisse' ?></div>
        </div>
        <button type="button" id="sidebarCloseBtn" class="sidebar-close-btn" aria-label="Fermer le menu" style="display:none; background:none; border:none; color:var(--text-secondary); font-size:20px; cursor:pointer; padding:4px;">✕</button>
    </div>

    <nav class="sidebar-nav">

        <!-- 1. Accueil & Dashboard -->
        <a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-link <?= nav_active('/dashboard.php') ?>">
            <?= icon('dashboard') ?> <?= __('Tableau de bord') ?>
        </a>

        <?php if ($is_admin): ?>
            <!-- ================= Navigation Direction (Patron) ================= -->
            <div class="sidebar-section-label">Supervision & Finances</div>
            <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="sidebar-link <?= nav_active('journal.php', '/comptabilite/') ?>">
                <?= icon('wallet') ?> 👑 Trésorerie & Caisse Boss
            </a>
            <a href="<?= BASE_URL ?>/modules/chambres/tracabilite.php" class="sidebar-link <?= nav_active('/chambres/tracabilite.php') ?>">
                <?= icon('bed') ?> 🏨 Journal des Occupations
            </a>
            <a href="<?= BASE_URL ?>/modules/rapports/bilan_employes.php" class="sidebar-link <?= nav_active('bilan_employes.php') ?>">
                <?= icon('users') ?> 👥 Bilan & Jours Employés
            </a>
            <a href="<?= BASE_URL ?>/modules/menage/tracabilite.php" class="sidebar-link <?= nav_active('/menage/') ?>">
                <?= icon('broom') ?> 🧹 Audit Ménage & Propreté
            </a>
            <a href="<?= BASE_URL ?>/modules/comptabilite/add_depense.php" class="sidebar-link <?= nav_active('add_depense.php') ?>">
                <?= icon('wallet') ?> 💸 Dépense / Achat Direction
            </a>

            <div class="sidebar-section-label">Gestion & Configuration</div>
            <a href="<?= BASE_URL ?>/modules/parametres/gestion.php" class="sidebar-link <?= nav_active('/parametres/gestion.php') ?>">
                <?= icon('settings') ?> ⚙️ Paramètres & CRUD
            </a>
            <a href="<?= BASE_URL ?>/modules/clients/list.php" class="sidebar-link <?= nav_active('/clients/') ?>">
                <?= icon('users') ?> Fichier Clients
            </a>

        <?php elseif (is_chef_receptionniste()): ?>
            <!-- ================= Navigation Chef de Réception ================= -->
            <div class="sidebar-section-label">Supervision & Trésorerie</div>
            <a href="<?= BASE_URL ?>/modules/comptabilite/transfert_boss.php" class="sidebar-link <?= nav_active('transfert_boss.php') ?>" style="font-weight:700; color:#d97706; background:#fffdfa; border: 1px solid #fde68a;">
                <?= icon('wallet') ?> 👑 Verser au Boss (Quinzaine)
            </a>
            <a href="<?= BASE_URL ?>/modules/rapports/bilan_employes.php" class="sidebar-link <?= nav_active('bilan_employes.php') ?>" style="font-weight:600; color:#4f46e5;">
                <?= icon('users') ?> 👥 Bilan Caisses Réceptionnistes
            </a>
            <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="sidebar-link <?= nav_active('journal.php') ?>">
                <?= icon('wallet') ?> 📊 Journal Global de Caisse
            </a>

            <div class="sidebar-section-label">Opérations Caisse & Clients</div>
            <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="sidebar-link <?= nav_active('express_checkin.php') ?>" style="font-weight:600; color:#10b981;">
                <?= icon('log-in') ?> 🏨 Arrivée Express
            </a>
            <a href="<?= BASE_URL ?>/modules/checkin_checkout/checkout.php" class="sidebar-link <?= nav_active('checkout.php') ?>">
                <?= icon('file-text') ?> Départs & Factures
            </a>
            <a href="<?= BASE_URL ?>/modules/comptabilite/add_depense.php" class="sidebar-link <?= nav_active('add_depense.php') ?>" style="font-weight:600; color:#ef4444;">
                <?= icon('wallet') ?> 💸 Sortie Caisse (Dépense)
            </a>

            <div class="sidebar-section-label">Chambres & Entretien</div>
            <a href="<?= BASE_URL ?>/modules/chambres/list.php" class="sidebar-link <?= nav_active('/chambres/list.php') ?>">
                <?= icon('bed') ?> État des Chambres
            </a>
            <a href="<?= BASE_URL ?>/modules/menage/tracabilite.php" class="sidebar-link <?= nav_active('/menage/') ?>">
                <?= icon('broom') ?> Suivi des Ménages
            </a>
            <a href="<?= BASE_URL ?>/modules/clients/list.php" class="sidebar-link <?= nav_active('/clients/') ?>">
                <?= icon('users') ?> Fichier Clients
            </a>

        <?php else: ?>
            <!-- ================= Navigation Réceptionniste Opérationnel ================= -->
            <div class="sidebar-section-label">Opérations Caisse & Clients</div>
            <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="sidebar-link <?= nav_active('express_checkin.php') ?>" style="font-weight:600; color:#10b981;">
                <?= icon('log-in') ?> 🏨 Arrivée Express
            </a>
            <a href="<?= BASE_URL ?>/modules/checkin_checkout/checkout.php" class="sidebar-link <?= nav_active('checkout.php') ?>">
                <?= icon('file-text') ?> 📋 Départs & Factures
            </a>
            <a href="<?= BASE_URL ?>/modules/comptabilite/add_depense.php" class="sidebar-link <?= nav_active('add_depense.php') ?>" style="font-weight:600; color:#ef4444;">
                <?= icon('wallet') ?> 💸 Sortie Caisse (Dépense)
            </a>

            <div class="sidebar-section-label">Chambres & Entretien</div>
            <a href="<?= BASE_URL ?>/modules/chambres/list.php" class="sidebar-link <?= nav_active('/chambres/list.php') ?>">
                <?= icon('bed') ?> État des Chambres
            </a>
            <a href="<?= BASE_URL ?>/modules/menage/tracabilite.php" class="sidebar-link <?= nav_active('/menage/') ?>">
                <?= icon('broom') ?> Suivi des Ménages
            </a>
            <a href="<?= BASE_URL ?>/modules/clients/list.php" class="sidebar-link <?= nav_active('/clients/') ?>">
                <?= icon('users') ?> Fichier Clients
            </a>
            <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="sidebar-link <?= nav_active('journal.php') ?>">
                <?= icon('wallet') ?> 📊 Mon Journal de Caisse
            </a>
        <?php endif; ?>

    </nav>

    <!-- ── Bouton Déconnexion Fixe en Bas du Menu Latéral (Rouge Fixe) ── -->
    <div class="sidebar-footer" style="position: sticky; bottom: 0; left: 0; right: 0; padding: 14px 16px; background: #ffffff; border-top: 1.5px solid var(--border-light); z-index: 999; box-shadow: 0 -4px 14px rgba(0,0,0,0.06); flex-shrink: 0;">
        <a href="<?= BASE_URL ?>/logout.php" class="btn-sidebar-logout" style="display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; height: 46px; background: #dc2626 !important; color: #ffffff !important; border-radius: 10px; font-weight: 800; font-size: 14.5px; text-decoration: none; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.35); transition: all 0.2s ease;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            <span style="color: #ffffff !important; font-weight: 800; letter-spacing: 0.3px;">Déconnexion</span>
        </a>
    </div>
</aside>
