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

    <!-- ── En-tête de Bienvenue ── -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.02em;">
                Bonjour, <?= h($current_u['prenom']) ?> 👋
            </h1>
            <p style="color: var(--text-secondary); margin-top: 4px; font-size: 14px;">
                <?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?> &bull; <?= date('d/m/Y') ?> &bull; Profil : <strong><?= role_label($current_u['role'] ?? 'receptionniste') ?></strong>
            </p>
        </div>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <?php if (is_supervisor()): ?>
                <a href="<?= BASE_URL ?>/modules/rapports/bilan_employes.php" class="btn btn-primary" style="box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);">
                    👥 Bilan des Caisses
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="btn btn-outline">
                📊 Journal de Caisse
            </a>
        </div>
    </div>

    <!-- ========================================================= -->
    <!-- CAS 1 : TABLEAU DE BORD DU BOSS (SUPERVISION STRATÉGIQUE) -->
    <!-- ========================================================= -->
    <?php if ($is_boss): ?>

        <!-- ── 1. STATS GLOBALES DU BOSS EN HAUT (5 Indicateurs Ultra-Compacts) ── -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 8px; margin-bottom: 20px;">
            
            <div style="background: #fffdfa; border: 1px solid #fde68a; border-left: 4px solid #d97706; border-radius: 8px; padding: 6px 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                    <span style="font-size: 11px; font-weight: 700; color: #d97706; text-transform: uppercase;">👑 Caisse Boss</span>
                    <span style="font-size: 9.5px; background: #fef3c7; color: #b45309; padding: 1px 5px; border-radius: 4px; font-weight: 700;">Direction</span>
                </div>
                <div style="font-size: 16px; font-weight: 800; color: #d97706; line-height: 1.2;"><?= format_prix($caisse_boss_total) ?></div>
                <div style="font-size: 10px; color: #92400e; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Coffre / Main propre</div>
            </div>

            <div style="background: #ffffff; border: 1px solid #e0e7ff; border-left: 4px solid #4f46e5; border-radius: 8px; padding: 6px 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                    <span style="font-size: 11px; font-weight: 700; color: #4f46e5; text-transform: uppercase;">💵 Tiroir Caisses</span>
                    <span style="font-size: 9.5px; background: #e0e7ff; color: #3730a3; padding: 1px 5px; border-radius: 4px; font-weight: 700;">Guichet</span>
                </div>
                <div style="font-size: 16px; font-weight: 800; color: var(--text-primary); line-height: 1.2;"><?= format_prix($especes_caissieres_total) ?></div>
                <div style="font-size: 10px; color: var(--text-light); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Espèces au guichet</div>
            </div>

            <div style="background: #ffffff; border: 1px solid #d1fae5; border-left: 4px solid #059669; border-radius: 8px; padding: 6px 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                    <span style="font-size: 11px; font-weight: 700; color: #059669; text-transform: uppercase;">🟢 Recettes Jour</span>
                    <span style="font-size: 9.5px; background: #d1fae5; color: #065f46; padding: 1px 5px; border-radius: 4px; font-weight: 700;">Séjours</span>
                </div>
                <div style="font-size: 16px; font-weight: 800; color: #059669; line-height: 1.2;"><?= format_prix($entrees_today) ?></div>
                <div style="font-size: 10px; color: var(--text-light); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Paiements perçus</div>
            </div>

            <div style="background: #ffffff; border: 1px solid #ffe4e6; border-left: 4px solid #e11d48; border-radius: 8px; padding: 6px 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                    <span style="font-size: 11px; font-weight: 700; color: #e11d48; text-transform: uppercase;">🔴 Dépenses Jour</span>
                    <span style="font-size: 9.5px; background: #ffe4e6; color: #9f1239; padding: 1px 5px; border-radius: 4px; font-weight: 700;">Charges</span>
                </div>
                <div style="font-size: 16px; font-weight: 800; color: #e11d48; line-height: 1.2;"><?= format_prix($sorties_today) ?></div>
                <div style="font-size: 10px; color: var(--text-light); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Achats savon, sorties</div>
            </div>

            <div style="background: #ffffff; border: 1px solid #cffafe; border-left: 4px solid #0891b2; border-radius: 8px; padding: 6px 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                    <span style="font-size: 11px; font-weight: 700; color: #0891b2; text-transform: uppercase;">📈 Occupation</span>
                    <span style="font-size: 9.5px; background: #cffafe; color: #155e75; padding: 1px 5px; border-radius: 4px; font-weight: 700;">Direct</span>
                </div>
                <div style="font-size: 16px; font-weight: 800; color: #0891b2; line-height: 1.2;"><?= $taux_occupation ?>%</div>
                <div style="font-size: 10px; color: var(--text-light); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= $ch_occupees ?>/<?= $total_chambres ?> occupées</div>
            </div>

        </div>

        <!-- ── 2. LES 6 GRANDS BOUTONS DU BOSS (BORDURES COLORÉES & ÉPURÉS) ── -->
        <div style="margin-bottom: 36px;">
            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-light); margin-bottom: 14px;">
                ⚡ Espaces de Consultation & Décision du Patron :
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 18px;">
                
                <!-- 1. Trésorerie & Caisse du Boss -->
                <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="action-card-bordered" style="background: #ffffff; border: 1.5px solid #fde68a; border-left: 6px solid #d97706; border-radius: var(--r-lg); padding: 14px 18px; text-decoration: none; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm); transition: var(--transition);">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #fffbeb; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;">👑</div>
                    <div style="font-size: 15.5px; font-weight: 800; color: #d97706; line-height: 1.2;">1. Trésorerie & Caisse du Boss</div>
                </a>

                <!-- 2. Comment Tourne l'Hôtel (Journal des Occupations) -->
                <a href="<?= BASE_URL ?>/modules/chambres/tracabilite.php" class="action-card-bordered" style="background: #ffffff; border: 1.5px solid #a7f3d0; border-left: 6px solid #059669; border-radius: var(--r-lg); padding: 14px 18px; text-decoration: none; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm); transition: var(--transition);">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #ecfdf5; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;">🏨</div>
                    <div style="font-size: 15.5px; font-weight: 800; color: #059669; line-height: 1.2;">2. Journal des Occupations</div>
                </a>

                <!-- 3. Contrôle des Employés & Jours Travaillés -->
                <a href="<?= BASE_URL ?>/modules/rapports/bilan_employes.php" class="action-card-bordered" style="background: #ffffff; border: 1.5px solid #c7d2fe; border-left: 6px solid #4f46e5; border-radius: var(--r-lg); padding: 14px 18px; text-decoration: none; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm); transition: var(--transition);">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #eef2ff; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;">👥</div>
                    <div style="font-size: 15.5px; font-weight: 800; color: #4f46e5; line-height: 1.2;">3. Contrôle Employés & Jours</div>
                </a>

                <!-- 4. Audit du Ménage & Consommation Matériel -->
                <a href="<?= BASE_URL ?>/modules/menage/tracabilite.php" class="action-card-bordered" style="background: #ffffff; border: 1.5px solid #a5f3fc; border-left: 6px solid #0891b2; border-radius: var(--r-lg); padding: 14px 18px; text-decoration: none; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm); transition: var(--transition);">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #ecfeff; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;">🧹</div>
                    <div style="font-size: 15.5px; font-weight: 800; color: #0891b2; line-height: 1.2;">4. Audit Ménage & Savon</div>
                </a>

                <!-- 5. Dépense / Achat Direction (Seule action active) -->
                <a href="<?= BASE_URL ?>/modules/comptabilite/add_depense.php" class="action-card-bordered" style="background: #ffffff; border: 1.5px solid #fecdd3; border-left: 6px solid #e11d48; border-radius: var(--r-lg); padding: 14px 18px; text-decoration: none; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm); transition: var(--transition);">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #fff1f2; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;">💸</div>
                    <div style="font-size: 15.5px; font-weight: 800; color: #e11d48; line-height: 1.2;">5. Dépense / Achat Direction</div>
                </a>

                <!-- 6. Paramètres & Gestion Globale (CRUD) -->
                <a href="<?= BASE_URL ?>/modules/parametres/gestion.php" class="action-card-bordered" style="background: #ffffff; border: 1.5px solid #cbd5e1; border-left: 6px solid #475569; border-radius: var(--r-lg); padding: 14px 18px; text-decoration: none; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm); transition: var(--transition);">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #f8fafc; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;">⚙️</div>
                    <div style="font-size: 15.5px; font-weight: 800; color: #475569; line-height: 1.2;">6. Paramètres & Gestion (CRUD)</div>
                </a>

            </div>
        </div>

    <!-- ========================================================= -->
    <!-- CAS 2 : TABLEAU DE BORD DE LA CAISSIÈRE (OPÉRATIONNEL)    -->
    <!-- ========================================================= -->
    <?php else: ?>

        <!-- ── ACTIONS RAPIDES CAISSIÈRE ── -->
        <div style="margin-bottom: 32px;">
            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-light); margin-bottom: 14px;">
                ⚡ Actions Rapides Caissière :
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 16px;">
                
                <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="action-card-bordered" style="background: #ffffff; border: 1.5px solid #a7f3d0; border-left: 6px solid #059669; border-radius: var(--r-lg); padding: 18px 20px; text-decoration: none; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm); transition: var(--transition);">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: #ecfdf5; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;">🏨</div>
                    <div>
                        <div style="font-size: 15.5px; font-weight: 800; color: #059669; line-height: 1.2;">Enregistrer occupation</div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 3px;">Arrivée & encaissement direct</div>
                    </div>
                </a>

                <a href="<?= BASE_URL ?>/modules/checkin_checkout/checkout.php" class="action-card-bordered" style="background: #ffffff; border: 1.5px solid #fde68a; border-left: 6px solid #d97706; border-radius: var(--r-lg); padding: 18px 20px; text-decoration: none; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm); transition: var(--transition);">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: #fffbeb; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;">📋</div>
                    <div>
                        <div style="font-size: 15.5px; font-weight: 800; color: #d97706; line-height: 1.2;">Départ client</div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 3px;">Facture & passage en ménage</div>
                    </div>
                </a>

                <a href="<?= BASE_URL ?>/modules/comptabilite/add_depense.php" class="action-card-bordered" style="background: #ffffff; border: 1.5px solid #fecdd3; border-left: 6px solid #e11d48; border-radius: var(--r-lg); padding: 18px 20px; text-decoration: none; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm); transition: var(--transition);">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: #fff1f2; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;">💸</div>
                    <div>
                        <div style="font-size: 15.5px; font-weight: 800; color: #e11d48; line-height: 1.2;">Sortie de caisse</div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 3px;">Achats savon, eau, maintenance</div>
                    </div>
                </a>

                <?php if (is_supervisor()): ?>
                <a href="<?= BASE_URL ?>/modules/comptabilite/transfert_boss.php" class="action-card-bordered" style="background: #ffffff; border: 1.5px solid #fde68a; border-left: 6px solid #b45309; border-radius: var(--r-lg); padding: 18px 20px; text-decoration: none; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm); transition: var(--transition);">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: #fffbeb; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;">👑</div>
                    <div>
                        <div style="font-size: 15.5px; font-weight: 800; color: #b45309; line-height: 1.2;">Verser au Boss</div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 3px;">Remise d'argent quinzaine & décharge</div>
                    </div>
                </a>
                <?php else: ?>
                <a href="<?= BASE_URL ?>/modules/chambres/list.php?statut=nettoyage" class="action-card-bordered" style="background: #ffffff; border: 1.5px solid #a5f3fc; border-left: 6px solid #0891b2; border-radius: var(--r-lg); padding: 18px 20px; text-decoration: none; display: flex; align-items: center; gap: 14px; box-shadow: var(--shadow-sm); transition: var(--transition);">
                    <div style="width: 48px; height: 48px; border-radius: 14px; background: #ecfeff; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;">🧹</div>
                    <div>
                        <div style="font-size: 15.5px; font-weight: 800; color: #0891b2; line-height: 1.2;">Nettoyage Chambres</div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 3px;">Valider les chambres faites</div>
                    </div>
                </a>
                <?php endif; ?>

            </div>
        </div>

        <!-- ── CHIFFRES DU JOUR CAISSIÈRE ── -->
        <div class="metrics-grid" style="margin-bottom: 18px;">
            <div class="metric-card" style="border-left: 4px solid #059669;">
                <div class="metric-card-header">
                    <span class="metric-label" style="color: #059669;">🟢 Entrées du Jour</span>
                </div>
                <div class="metric-value" style="color: #059669;"><?= format_prix($entrees_today) ?></div>
                <div class="metric-sub">Paiements perçus aujourd'hui</div>
            </div>

            <div class="metric-card" style="border-left: 4px solid #e11d48;">
                <div class="metric-card-header">
                    <span class="metric-label" style="color: #e11d48;">🔴 Dépenses du Jour</span>
                </div>
                <div class="metric-value" style="color: #e11d48;"><?= format_prix($sorties_today) ?></div>
                <div class="metric-sub">Sorties justifiées aujourd'hui</div>
            </div>

            <div class="metric-card" style="border-left: 4px solid <?= $solde_net_today >= 0 ? '#4f46e5' : '#e11d48' ?>;">
                <div class="metric-card-header">
                    <span class="metric-label" style="color: var(--primary);">⚖️ Solde Net Caisse</span>
                </div>
                <div class="metric-value" style="color: <?= $solde_net_today >= 0 ? 'var(--text-primary)' : '#e11d48' ?>;"><?= format_prix($solde_net_today) ?></div>
                <div class="metric-sub">Espèces en caisse</div>
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

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
