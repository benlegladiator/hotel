<?php
// ============================================================
// dashboard.php — Tableau de Bord Épuré & Rôles Dédiés (Alma Hotel v2.0)
// ============================================================

require_once 'config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

$current_u = current_user();
$user_id   = $current_u['id'];
$is_boss   = is_admin();
$today     = date('Y-m-d');

// ── 1. Statistiques Financières du Jour ──────────────────────
// Entrées aujourd'hui (Recettes séjours)
$stmt_in = $pdo->prepare("
    SELECT COALESCE(SUM(montant_final), 0) FROM factures 
    WHERE date_emission = ? AND statut_paiement IN ('payee', 'partielle')
");
$stmt_in->execute([$today]);
$entrees_today = (float)$stmt_in->fetchColumn();

// Sorties aujourd'hui (Dépenses courantes + versements)
$stmt_out = $pdo->prepare("
    SELECT COALESCE(SUM(montant), 0) FROM sorties_caisse 
    WHERE date_sortie = ?
");
$stmt_out->execute([$today]);
$sorties_today = (float)$stmt_out->fetchColumn();

$solde_net_today = $entrees_today - $sorties_today;

// ── 2. Statistiques Trésorerie Globale (Spécifique Boss) ─────
if ($is_boss) {
    // Total récupéré en Caisse du Boss (tous les versements patron)
    $caisse_boss_total = (float)$pdo->query("
        SELECT COALESCE(SUM(montant), 0) FROM sorties_caisse 
        WHERE categorie = 'versement_patron'
    ")->fetchColumn();

    // Total toutes recettes séjours historiques
    $total_recettes_all = (float)$pdo->query("
        SELECT COALESCE(SUM(montant_final), 0) FROM factures 
        WHERE statut_paiement IN ('payee', 'partielle')
    ")->fetchColumn();

    // Total toutes sorties de caisse historiques
    $total_sorties_all = (float)$pdo->query("
        SELECT COALESCE(SUM(montant), 0) FROM sorties_caisse
    ")->fetchColumn();

    // Espèces actuellement dans les tiroirs-caisses des employées
    $especes_caissieres_total = max(0, $total_recettes_all - $total_sorties_all);
}

// ── 3. Statut des Chambres & Taux d'Occupation ───────────────
$stat_chambres = $pdo->query("SELECT statut, COUNT(*) as n FROM chambres GROUP BY statut")->fetchAll();
$chambres_map  = array_column($stat_chambres, 'n', 'statut');
$total_chambres= array_sum($chambres_map);
$ch_libres     = $chambres_map['libre'] ?? 0;
$ch_occupees   = $chambres_map['occupee'] ?? 0;
$ch_nettoyage  = $chambres_map['nettoyage'] ?? 0;
$ch_reservees  = $chambres_map['reservee'] ?? 0;

$taux_occupation = $total_chambres > 0 ? round(($ch_occupees / $total_chambres) * 100, 0) : 0;

// Chambres en attente de nettoyage
$stmt_clean = $pdo->query("
    SELECT c.id, c.numero, tc.nom as type_nom 
    FROM chambres c 
    JOIN types_chambres tc ON c.id_type_chambre = tc.id 
    WHERE c.statut = 'nettoyage'
    ORDER BY c.numero ASC
");
$chambres_a_nettoyer = $stmt_clean->fetchAll();

// Liste de toutes les chambres
$chambres_all = $pdo->query("
    SELECT c.id, c.numero, c.statut, tc.nom as type_nom, tc.prix_nuit
    FROM chambres c
    JOIN types_chambres tc ON tc.id = c.id_type_chambre
    ORDER BY c.numero ASC
")->fetchAll();

// ── 3b. Siestes actives en cours (Prolongations possibles) ────────
$stmt_siestes = $pdo->query("
    SELECT r.id as res_id, r.date_arrivee, ch.numero as chambre_numero, tc.nom as type_nom,
           c.nom as client_nom, c.prenom as client_prenom
    FROM reservations r
    JOIN chambres ch ON r.id_chambre = ch.id
    JOIN types_chambres tc ON ch.id_type_chambre = tc.id
    JOIN clients c ON r.id_client = c.id
    WHERE r.statut = 'en_cours' AND (r.remarques LIKE '%sieste%' OR r.date_arrivee = r.date_depart)
    ORDER BY r.id DESC
");
$siestes_actives = $stmt_siestes->fetchAll();

// ── 4. Dernières Opérations de Caisse ─────────────────────────
$stmt_recent_e = $pdo->prepare("
    SELECT 'entree' as type, '' as categorie, f.id as id_op, f.created_at, f.montant_final as montant, 
           CONCAT('Client: ', c.nom, ' ', c.prenom, IF(ch.numero IS NOT NULL, CONCAT(' (Ch. ', ch.numero, ')'), '')) as libelle,
           u.prenom as user_prenom
    FROM factures f
    JOIN clients c ON f.id_client = c.id
    LEFT JOIN reservations r ON f.id_reservation = r.id
    LEFT JOIN chambres ch ON r.id_chambre = ch.id
    LEFT JOIN utilisateurs u ON f.id_receptionniste = u.id
    WHERE f.date_emission = ? AND f.statut_paiement IN ('payee', 'partielle')
    ORDER BY f.created_at DESC LIMIT 5
");
$stmt_recent_e->execute([$today]);
$recent_entrees = $stmt_recent_e->fetchAll();

$stmt_recent_s = $pdo->prepare("
    SELECT 'sortie' as type, s.categorie, s.id as id_op, s.created_at, s.montant, s.libelle, u.prenom as user_prenom
    FROM sorties_caisse s
    LEFT JOIN utilisateurs u ON s.id_utilisateur = u.id
    WHERE s.date_sortie = ?
    ORDER BY s.created_at DESC LIMIT 5
");
$stmt_recent_s->execute([$today]);
$recent_sorties = $stmt_recent_s->fetchAll();

$mouvements_today = array_merge($recent_entrees, $recent_sorties);
usort($mouvements_today, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$mouvements_today = array_slice($mouvements_today, 0, 6);

$page_title = $is_boss ? 'Tableau de Bord Direction — Alma Hotel' : 'Tableau de Bord Caisse — Alma Hotel';
require_once INCLUDES_PATH . '/header.php';
?>

<div style="max-width: 1240px; margin: 0 auto;">

    <!-- ── En-tête Profil Style Orange Money ── -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 20px;">
        <div style="display: flex; align-items: center; gap: 14px;">
            <div style="width: 48px; height: 48px; border-radius: 50%; background: #1e1b4b; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 18px; font-weight: 800; border: 2.5px solid #ff7900; box-shadow: 0 4px 12px rgba(30,27,75,0.25); flex-shrink: 0;">
                <?= strtoupper(mb_substr($current_u['prenom'] ?? 'A', 0, 1) . mb_substr($current_u['nom'] ?? 'M', 0, 1)) ?>
            </div>
            <div>
                <h1 style="font-size: 22px; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.02em;">
                    Bonjour, <?= h($current_u['prenom']) ?> 👋
                </h1>
                <p style="color: var(--text-secondary); margin-top: 2px; font-size: 13px;">
                    <?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?> &bull; <span style="color: #ff7900; font-weight: 700;"><?= role_label($current_u['role'] ?? 'receptionniste') ?></span>
                </p>
            </div>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if (is_supervisor()): ?>
                <a href="<?= BASE_URL ?>/modules/rapports/bilan_employes.php" class="btn btn-sm btn-primary" style="box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25); border-radius: 10px;">
                    👥 Bilan Caisses
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="btn btn-sm btn-outline" style="border-radius: 10px;">
                📊 Journal Caisse
            </a>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- CAS 1 : TABLEAU DE BORD DU BOSS (STYLE ORANGE MONEY)      -->
    <!-- ========================================================= -->
    <?php if ($is_boss): 
        $solde_total_tresorerie = ($caisse_boss_total ?? 0) + ($especes_caissieres_total ?? 0);
    ?>

        <!-- ── 1. CARTE HÉRO TRÉSORERIE STYLE "COMPTE PRINCIPAL" ── -->
        <div class="om-hero-card">
            <div class="om-hero-top">
                <div>
                    <div class="om-hero-tag">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ff7900" stroke-width="2.5"><polyline points="7 17 17 7 17 17"/><polyline points="7 7 17 7"/></svg>
                        <span>Compte Principal &bull; Trésorerie</span>
                    </div>
                    <div class="om-hero-amount" id="omHeroAmount">
                        <?= format_prix($solde_total_tresorerie) ?>
                    </div>
                </div>
                <div class="om-hero-qr" title="Alma Hotel Pro">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1e1b4b" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
                        <circle cx="6.5" cy="6.5" r="1.5" fill="#ff7900"/><circle cx="17.5" cy="6.5" r="1.5" fill="#ff7900"/><circle cx="6.5" cy="17.5" r="1.5" fill="#ff7900"/>
                    </svg>
                </div>
            </div>

            <button type="button" onclick="toggleOmBalance()" id="omToggleBtn" class="om-btn-toggle-balance">
                <span id="omToggleText">Masquer le solde</span> <span id="omToggleIcon">👁️</span>
            </button>

            <div class="om-hero-subaccounts">
                <div class="om-subaccount-box">
                    <div class="om-subaccount-title">
                        <span>👑</span> <span>Caisse Boss (Coffre)</span>
                    </div>
                    <div class="om-subaccount-value" id="omSubBoss" style="color: #fde68a;">
                        <?= format_prix($caisse_boss_total ?? 0) ?>
                    </div>
                </div>
                <div class="om-subaccount-box">
                    <div class="om-subaccount-title">
                        <span>💵</span> <span>Tiroir Guichet</span>
                    </div>
                    <div class="om-subaccount-value" id="omSubGuichet">
                        <?= format_prix($especes_caissieres_total ?? 0) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── 2. BANNIÈRE HORIZONTALE OCCUPATION (STYLE "MES CRÉDITS") ── -->
        <a href="<?= BASE_URL ?>/modules/chambres/tracabilite.php" class="om-credit-banner">
            <div class="om-credit-icon">
                📈
            </div>
            <div class="om-credit-info">
                <div class="om-credit-title">Taux d'Occupation Hôtel : <strong><?= $taux_occupation ?>%</strong></div>
                <div class="om-credit-subtitle"><?= $ch_occupees ?>/<?= $total_chambres ?> occupées &bull; <?= $ch_libres ?> libres &bull; <?= $ch_nettoyage ?> en ménage</div>
            </div>
            <div class="om-credit-arrow">
                &rsaquo;
            </div>
        </a>

        <!-- ── 3. GRILLE DES SERVICES 4 COLONNES (TRICHE ORANGE MONEY) ── -->
        <div class="om-services-section">
            <div class="om-section-title">
                <span>⚡</span> <span>Services & Décisions Direction</span>
            </div>
            <div class="om-services-grid">
                <!-- 1. Arrivée Express -->
                <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5); border: 1.5px solid #a7f3d0;">
                        <span>⚡</span>
                    </div>
                    <span class="om-tile-label">Arrivée Express</span>
                </a>

                <!-- 2. Départ Client -->
                <a href="<?= BASE_URL ?>/modules/checkin_checkout/checkout.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); border: 1.5px solid #fde68a;">
                        <span>📋</span>
                    </div>
                    <span class="om-tile-label">Départ Client</span>
                </a>

                <!-- 3. Caisse Boss -->
                <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #fff7ed, #ffedd5); border: 1.5px solid #fed7aa;">
                        <span>👑</span>
                    </div>
                    <span class="om-tile-label">Caisse Boss</span>
                </a>

                <!-- 4. + Dépense Direction -->
                <a href="<?= BASE_URL ?>/modules/comptabilite/add_depense.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #fff1f2, #ffe4e6); border: 1.5px solid #fecdd3;">
                        <span>💸</span>
                    </div>
                    <span class="om-tile-label">+ Dépense</span>
                </a>

                <!-- 5. Journal Occupations -->
                <a href="<?= BASE_URL ?>/modules/chambres/tracabilite.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1.5px solid #bbf7d0;">
                        <span>🏨</span>
                    </div>
                    <span class="om-tile-label">Occupations</span>
                </a>

                <!-- 6. Audit Ménage -->
                <a href="<?= BASE_URL ?>/modules/menage/tracabilite.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #ecfeff, #cffafe); border: 1.5px solid #a5f3fc;">
                        <span>🧹</span>
                    </div>
                    <span class="om-tile-label">Audit Ménage</span>
                </a>

                <!-- 7. Bilan Employés -->
                <a href="<?= BASE_URL ?>/modules/rapports/bilan_employes.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #eef2ff, #e0e7ff); border: 1.5px solid #c7d2fe;">
                        <span>👥</span>
                    </div>
                    <span class="om-tile-label">Employés</span>
                </a>

                <!-- 8. Paramètres & CRUD -->
                <a href="<?= BASE_URL ?>/modules/parametres/gestion.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1.5px solid #cbd5e1;">
                        <span>⚙️</span>
                    </div>
                    <span class="om-tile-label">Paramètres</span>
                </a>
            </div>
        </div>

        <!-- ── 4. FLUX DU JOUR COMPACT (RECETTES VS DÉPENSES) ── -->
        <div class="om-flux-grid">
            <div class="om-flux-card" style="border-left: 4px solid #059669;">
                <div class="om-flux-header">
                    <span style="font-size: 11px; font-weight: 700; color: #059669;">🟢 ENTRÉES DU JOUR</span>
                    <span class="om-flux-tag" style="background: #d1fae5; color: #065f46;">Séjours</span>
                </div>
                <div class="om-flux-value" style="color: #059669;" id="omFluxEntrees">
                    <?= format_prix($entrees_today) ?>
                </div>
                <div class="om-flux-desc">Paiements perçus aujourd'hui</div>
            </div>

            <div class="om-flux-card" style="border-left: 4px solid #e11d48;">
                <div class="om-flux-header">
                    <span style="font-size: 11px; font-weight: 700; color: #e11d48;">🔴 SORTIES DU JOUR</span>
                    <span class="om-flux-tag" style="background: #ffe4e6; color: #9f1239;">Charges</span>
                </div>
                <div class="om-flux-value" style="color: #e11d48;" id="omFluxSorties">
                    <?= format_prix($sorties_today) ?>
                </div>
                <div class="om-flux-desc">Dépenses & achats enregistrés</div>
            </div>
        </div>

    <!-- ========================================================= -->
    <!-- CAS 2 : TABLEAU DE BORD DE LA CAISSIÈRE (STYLE ORANGE MONEY)-->
    <!-- ========================================================= -->
    <?php else: ?>

        <!-- ── 1. CARTE HÉRO CAISSE GUICHET ── -->
        <div class="om-hero-card">
            <div class="om-hero-top">
                <div>
                    <div class="om-hero-tag">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ff7900" stroke-width="2.5"><polyline points="7 17 17 7 17 17"/><polyline points="7 7 17 7"/></svg>
                        <span>Caisse Guichet &bull; Mon Tiroir</span>
                    </div>
                    <div class="om-hero-amount" id="omHeroAmount">
                        <?= format_prix($solde_net_today) ?>
                    </div>
                </div>
                <div class="om-hero-qr" title="Alma Hotel Caisse">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1e1b4b" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
                        <circle cx="6.5" cy="6.5" r="1.5" fill="#ff7900"/><circle cx="17.5" cy="6.5" r="1.5" fill="#ff7900"/><circle cx="6.5" cy="17.5" r="1.5" fill="#ff7900"/>
                    </svg>
                </div>
            </div>

            <button type="button" onclick="toggleOmBalance()" id="omToggleBtn" class="om-btn-toggle-balance">
                <span id="omToggleText">Masquer le solde</span> <span id="omToggleIcon">👁️</span>
            </button>

            <div class="om-hero-subaccounts">
                <div class="om-subaccount-box">
                    <div class="om-subaccount-title">
                        <span>🟢</span> <span>Entrées Séjours</span>
                    </div>
                    <div class="om-subaccount-value" id="omSubBoss" style="color: #86efac;">
                        <?= format_prix($entrees_today) ?>
                    </div>
                </div>
                <div class="om-subaccount-box">
                    <div class="om-subaccount-title">
                        <span>🔴</span> <span>Sorties Justifiées</span>
                    </div>
                    <div class="om-subaccount-value" id="omSubGuichet" style="color: #fca5a5;">
                        <?= format_prix($sorties_today) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── 2. BANNIÈRE HORIZONTALE OCCUPATION ── -->
        <a href="<?= BASE_URL ?>/modules/chambres/list.php" class="om-credit-banner">
            <div class="om-credit-icon">
                🛏️
            </div>
            <div class="om-credit-info">
                <div class="om-credit-title">Occupation Chambres : <strong><?= $taux_occupation ?>%</strong></div>
                <div class="om-credit-subtitle"><?= $ch_occupees ?>/<?= $total_chambres ?> occupées &bull; <?= $ch_libres ?> chambres prêtes</div>
            </div>
            <div class="om-credit-arrow">
                &rsaquo;
            </div>
        </a>

        <!-- ── 3. GRILLE DES SERVICES CAISSIÈRE (TRICHE ORANGE MONEY) ── -->
        <div class="om-services-section">
            <div class="om-section-title">
                <span>⚡</span> <span>Opérations Caissière</span>
            </div>
            <div class="om-services-grid">
                <!-- 1. Enregistrer Occupation -->
                <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #ecfdf5, #d1fae5); border: 1.5px solid #a7f3d0;">
                        <span>⚡</span>
                    </div>
                    <span class="om-tile-label">Arrivée Directe</span>
                </a>

                <!-- 2. Départ Client -->
                <a href="<?= BASE_URL ?>/modules/checkin_checkout/checkout.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); border: 1.5px solid #fde68a;">
                        <span>📋</span>
                    </div>
                    <span class="om-tile-label">Départ Client</span>
                </a>

                <!-- 3. Sortie Caisse -->
                <a href="<?= BASE_URL ?>/modules/comptabilite/add_depense.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #fff1f2, #ffe4e6); border: 1.5px solid #fecdd3;">
                        <span>💸</span>
                    </div>
                    <span class="om-tile-label">Sortie Caisse</span>
                </a>

                <!-- 4. Verser au Boss ou Chambres -->
                <?php if (is_supervisor()): ?>
                <a href="<?= BASE_URL ?>/modules/comptabilite/transfert_boss.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #fff7ed, #ffedd5); border: 1.5px solid #fed7aa;">
                        <span>👑</span>
                    </div>
                    <span class="om-tile-label">Verser Boss</span>
                </a>
                <?php else: ?>
                <a href="<?= BASE_URL ?>/modules/chambres/list.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1.5px solid #bbf7d0;">
                        <span>🛏️</span>
                    </div>
                    <span class="om-tile-label">Chambres</span>
                </a>
                <?php endif; ?>

                <!-- 5. Nettoyage -->
                <a href="<?= BASE_URL ?>/modules/menage/tracabilite.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #ecfeff, #cffafe); border: 1.5px solid #a5f3fc;">
                        <span>🧹</span>
                    </div>
                    <span class="om-tile-label">Nettoyage</span>
                </a>

                <!-- 6. Fichier Clients -->
                <a href="<?= BASE_URL ?>/modules/clients/list.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #eef2ff, #e0e7ff); border: 1.5px solid #c7d2fe;">
                        <span>👤</span>
                    </div>
                    <span class="om-tile-label">Clients</span>
                </a>

                <!-- 7. Facturation -->
                <a href="<?= BASE_URL ?>/modules/facturation/list.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #fdf4ff, #fae8ff); border: 1.5px solid #f0abfc;">
                        <span>🧾</span>
                    </div>
                    <span class="om-tile-label">Factures</span>
                </a>

                <!-- 8. Journal Caisse -->
                <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="om-service-tile">
                    <div class="om-tile-icon" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9); border: 1.5px solid #cbd5e1;">
                        <span>📊</span>
                    </div>
                    <span class="om-tile-label">Journal</span>
                </a>
            </div>
        </div>

        <!-- ── 4. FLUX DU JOUR CAISSIÈRE ── -->
        <div class="om-flux-grid">
            <div class="om-flux-card" style="border-left: 4px solid #059669;">
                <div class="om-flux-header">
                    <span style="font-size: 11px; font-weight: 700; color: #059669;">🟢 ENTRÉES DU JOUR</span>
                    <span class="om-flux-tag" style="background: #d1fae5; color: #065f46;">Séjours</span>
                </div>
                <div class="om-flux-value" style="color: #059669;">
                    <?= format_prix($entrees_today) ?>
                </div>
                <div class="om-flux-desc">Paiements perçus aujourd'hui</div>
            </div>

            <div class="om-flux-card" style="border-left: 4px solid #e11d48;">
                <div class="om-flux-header">
                    <span style="font-size: 11px; font-weight: 700; color: #e11d48;">🔴 DÉPENSES DU JOUR</span>
                    <span class="om-flux-tag" style="background: #ffe4e6; color: #9f1239;">Charges</span>
                </div>
                <div class="om-flux-value" style="color: #e11d48;">
                    <?= format_prix($sorties_today) ?>
                </div>
                <div class="om-flux-desc">Sorties justifiées aujourd'hui</div>
            </div>
        </div>

        <!-- ── ALERTE MÉNAGE CAISSIÈRE (VALIDATION 1-CLIC) ── -->
        <?php if (!empty($chambres_a_nettoyer)): ?>
            <div class="card" style="border: 2px solid #c7d2fe; background: #faf5ff; margin-bottom: 32px;">
                <div class="card-header" style="background: transparent; border-bottom: 1px solid #e9d5ff;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 22px;">🧹</span>
                        <h2 class="card-title" style="color: #4338ca;">
                            Chambres à Nettoyer (<?= count($chambres_a_nettoyer) ?> en attente)
                        </h2>
                    </div>
                    <span style="font-size: 13px; color: #4338ca; font-weight: 600;">
                        Validez en 1 clic après avoir nettoyé :
                    </span>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
                        <?php foreach ($chambres_a_nettoyer as $can): ?>
                            <div style="background: #ffffff; border-radius: 12px; border: 1.5px solid #c7d2fe; padding: 16px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-xs);">
                                <div>
                                    <div style="font-size: 17px; font-weight: 800; color: var(--text-primary);">Chambre <?= h($can['numero']) ?></div>
                                    <div style="font-size: 12.5px; color: var(--text-secondary);"><?= h($can['type_nom']) ?></div>
                                </div>
                                <a href="<?= BASE_URL ?>/modules/menage/quick_clean.php?id_chambre=<?= $can['id'] ?>" class="btn btn-success" style="font-weight: 700; border-radius: 10px; padding: 10px 16px;" onclick="return confirm('Confirmez-vous que vous avez nettoyé la Chambre <?= $can['numero'] ?> ?');">
                                    ✔ Nettoyée & Prête
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- ── ALERTE SIESTES EN COURS (PROLONGATION RAPIDE) ── -->
        <?php if (!empty($siestes_actives)): ?>
            <div class="card" style="border: 2px solid #fde68a; background: #fffdf5; margin-bottom: 32px;">
                <div class="card-header" style="background: transparent; border-bottom: 1px solid #fef3c7; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 22px;">⚡</span>
                        <h2 class="card-title" style="color: #b45309; margin: 0; font-size: 16px;">
                            Siestes en cours (<?= count($siestes_actives) ?>) — Prolongations en Nuitée
                        </h2>
                    </div>
                    <span style="font-size: 13px; color: #b45309; font-weight: 600;">
                        Le client souhaite rester la nuit ? Ne paye que le reliquat :
                    </span>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
                        <?php foreach ($siestes_actives as $sa): ?>
                            <div style="background: #ffffff; border-radius: 12px; border: 1.5px solid #fde68a; padding: 14px 16px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-xs);">
                                <div>
                                    <div style="font-size: 15px; font-weight: 800; color: #d97706;">Chambre <?= h($sa['chambre_numero']) ?> (<?= h($sa['type_nom']) ?>)</div>
                                    <div style="font-size: 13px; color: var(--text-primary); font-weight: 600; margin-top: 2px;">
                                        👤 <?= h($sa['client_nom'] . ' ' . $sa['client_prenom']) ?>
                                    </div>
                                </div>
                                <a href="<?= BASE_URL ?>/modules/checkin_checkout/bascule_sieste.php?id=<?= $sa['res_id'] ?>" class="btn" style="background: #fffbeb; color: #b45309; border: 1.5px solid #fde68a; font-weight: 800; border-radius: 10px; padding: 8px 12px; font-size: 13px; display: inline-flex; align-items: center; gap: 5px;">
                                    <span>🌙</span> En Nuitée
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- ── 3. DERNIÈRES OPÉRATIONS DE CAISSE ── -->
    <div class="card" style="margin-bottom: 32px;">
        <div class="card-header">
            <h2 class="card-title">
                📋 Dernières Opérations de Caisse d'Aujourd'hui
            </h2>
            <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="btn btn-sm btn-outline">
                Voir tout le journal →
            </a>
        </div>

        <?php if (empty($mouvements_today)): ?>
            <div class="card-body" style="text-align: center; color: var(--text-light); padding: 36px 20px; font-style: italic;">
                Aucune opération de caisse enregistrée aujourd'hui pour l'instant.
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Heure</th>
                            <th>Type</th>
                            <th>Motif / Libellé</th>
                            <th>Employé(e)</th>
                            <th style="text-align: right;">Montant</th>
                            <th style="text-align: center;">Reçu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mouvements_today as $mt): 
                            $recu_url = ($mt['type'] === 'entree')
                                ? BASE_URL . '/modules/facturation/print.php?id=' . $mt['id_op']
                                : BASE_URL . '/modules/comptabilite/print_depense.php?id=' . $mt['id_op'];
                        ?>
                            <tr>
                                <td style="color: var(--text-secondary); font-weight: 500;"><?= date('H:i', strtotime($mt['created_at'])) ?></td>
                                <td>
                                    <?php if ($mt['type'] === 'entree'): ?>
                                        <span class="badge badge-libre">🟢 ENTRÉE</span>
                                    <?php elseif (($mt['categorie'] ?? '') === 'versement_patron'): ?>
                                        <span class="badge" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; font-weight:800;">👑 VERS. BOSS</span>
                                    <?php else: ?>
                                        <span class="badge badge-occupee">🔴 SORTIE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight: 600; color: var(--text-primary);"><?= h($mt['libelle']) ?></td>
                                <td style="color: var(--text-secondary);">👤 <?= h($mt['user_prenom'] ?? 'N/A') ?></td>
                                <td style="text-align: right; font-weight: 800; font-size: 15px; color: <?= $mt['type'] === 'entree' ? '#059669' : '#e11d48' ?>;">
                                    <?= $mt['type'] === 'entree' ? '+' : '-' ?> <?= format_prix($mt['montant']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="<?= $recu_url ?>" target="_blank" class="btn btn-sm btn-outline">
                                        🖨️ Reçu
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── 4. PLAN VISUEL DES CHAMBRES (CONSULTATION PURE POUR LE BOSS) ── -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                🛏️ Disponibilité des Chambres (<?= $ch_libres ?> Libres / <?= $ch_occupees ?> Occupées / <?= $ch_nettoyage ?> Nettoyage)
            </h2>
            <div style="display: flex; gap: 10px; font-size: 12px; font-weight: 600; flex-wrap: wrap;">
                <span class="badge badge-libre">Libre (<?= $ch_libres ?>)</span>
                <span class="badge badge-occupee">Occupée (<?= $ch_occupees ?>)</span>
                <span class="badge badge-nettoyage">Nettoyage (<?= $ch_nettoyage ?>)</span>
            </div>
        </div>

        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 14px;">
                <?php foreach ($chambres_all as $ch): 
                    $badge_class = 'badge-libre'; $badge_text = 'Libre';
                    if ($ch['statut'] === 'occupee') {
                        $badge_class = 'badge-occupee'; $badge_text = 'Occupée';
                    } elseif ($ch['statut'] === 'nettoyage') {
                        $badge_class = 'badge-nettoyage'; $badge_text = 'À Nettoyer';
                    }
                ?>
                    <div style="background: #ffffff; border: 1.5px solid <?= $ch['statut'] === 'nettoyage' ? '#c7d2fe' : 'var(--border-light)' ?>; border-radius: 12px; padding: 14px 10px; text-align: center; box-shadow: var(--shadow-xs); transition: var(--transition);">
                        <div style="font-size: 18px; font-weight: 800; color: var(--text-primary);">Ch. <?= h($ch['numero']) ?></div>
                        <div style="font-size: 11px; color: var(--text-secondary); margin: 2px 0 6px 0;"><?= h($ch['type_nom']) ?></div>
                        <div>
                            <span class="badge <?= $badge_class ?>"><?= $badge_text ?></span>
                        </div>
                        <?php if (!$is_boss && $ch['statut'] === 'nettoyage'): ?>
                            <div style="margin-top: 8px;">
                                <a href="<?= BASE_URL ?>/modules/menage/quick_clean.php?id_chambre=<?= $ch['id'] ?>" class="btn btn-sm btn-success" style="font-size: 11px; padding: 4px 8px; width: 100%; font-weight: 700;" onclick="return confirm('Confirmez-vous que vous avez nettoyé la Chambre <?= $ch['numero'] ?> ?');">
                                    ✔ Prête
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<script>
// ── Bascule Masquer / Afficher le Solde Style Orange Money ──
let isOmBalanceHidden = false;
const realOmHeroAmount = <?= json_encode($is_boss ? format_prix($solde_total_tresorerie ?? 0) : format_prix($solde_net_today ?? 0)) ?>;
const realOmSubBoss    = <?= json_encode($is_boss ? format_prix($caisse_boss_total ?? 0) : format_prix($entrees_today ?? 0)) ?>;
const realOmSubGuichet = <?= json_encode($is_boss ? format_prix($especes_caissieres_total ?? 0) : format_prix($sorties_today ?? 0)) ?>;

function toggleOmBalance() {
    isOmBalanceHidden = !isOmBalanceHidden;
    const heroEl    = document.getElementById('omHeroAmount');
    const bossEl    = document.getElementById('omSubBoss');
    const guichetEl = document.getElementById('omSubGuichet');
    const btnText   = document.getElementById('omToggleText');
    const btnIcon   = document.getElementById('omToggleIcon');

    if (isOmBalanceHidden) {
        if (heroEl) heroEl.textContent = '***** FCFA';
        if (bossEl) bossEl.textContent = '***** FCFA';
        if (guichetEl) guichetEl.textContent = '***** FCFA';
        if (btnText) btnText.textContent = 'Afficher le solde';
        if (btnIcon) btnIcon.textContent = '👁️';
        localStorage.setItem('alma_om_balance_hidden', 'true');
    } else {
        if (heroEl) heroEl.textContent = realOmHeroAmount;
        if (bossEl) bossEl.textContent = realOmSubBoss;
        if (guichetEl) guichetEl.textContent = realOmSubGuichet;
        if (btnText) btnText.textContent = 'Masquer le solde';
        if (btnIcon) btnIcon.textContent = '👁️';
        localStorage.removeItem('alma_om_balance_hidden');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('alma_om_balance_hidden') === 'true') {
        toggleOmBalance();
    }
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
