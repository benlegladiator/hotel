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
    // Total versements remis au Boss par les réceptionnistes
    $total_versements_boss = (float)$pdo->query("
        SELECT COALESCE(SUM(montant), 0) FROM sorties_caisse 
        WHERE categorie = 'versement_patron'
    ")->fetchColumn();

    // Total retraits personnels effectués par le Boss (sorties privées / prélèvements patron)
    $total_retraits_boss = (float)$pdo->query("
        SELECT COALESCE(SUM(montant), 0) FROM sorties_caisse 
        WHERE categorie = 'prelevement_patron' 
           OR (id_utilisateur = 1 AND (categorie = 'autre' OR libelle LIKE '%prélèvement%' OR libelle LIKE '%retrait patron%'))
    ")->fetchColumn();

    // Solde net physique du Coffre du Boss
    $caisse_boss_total = max(0, $total_versements_boss - $total_retraits_boss);

    // Décomposition précise par réceptionniste (Qui détient combien actuellement ?)
    $stmt_rep = $pdo->query("
        SELECT 
            u.id, 
            u.nom, 
            u.prenom, 
            u.role,
            u.identifiant,
            COALESCE(rec.total_encaisse, 0) as total_encaisse,
            COALESCE(rec_today.today_encaisse, 0) as today_encaisse,
            COALESCE(dep.total_depenses, 0) as total_depenses,
            COALESCE(vers.total_verse_boss, 0) as total_verse_boss
        FROM utilisateurs u
        LEFT JOIN (
            SELECT id_receptionniste, SUM(montant_final) as total_encaisse 
            FROM factures 
            WHERE statut_paiement IN ('payee', 'partielle') 
            GROUP BY id_receptionniste
        ) rec ON rec.id_receptionniste = u.id
        LEFT JOIN (
            SELECT id_receptionniste, SUM(montant_final) as today_encaisse 
            FROM factures 
            WHERE statut_paiement IN ('payee', 'partielle') AND date_emission = CURDATE()
            GROUP BY id_receptionniste
        ) rec_today ON rec_today.id_receptionniste = u.id
        LEFT JOIN (
            SELECT id_utilisateur, SUM(montant) as total_depenses 
            FROM sorties_caisse 
            WHERE categorie NOT IN ('versement_patron', 'prelevement_patron')
            GROUP BY id_utilisateur
        ) dep ON dep.id_utilisateur = u.id
        LEFT JOIN (
            SELECT id_utilisateur, SUM(montant) as total_verse_boss 
            FROM sorties_caisse 
            WHERE categorie = 'versement_patron'
            GROUP BY id_utilisateur
        ) vers ON vers.id_utilisateur = u.id
        WHERE u.actif = 1 AND (u.role IN ('receptionniste', 'chef_receptionniste') OR rec.total_encaisse > 0)
        ORDER BY u.nom ASC
    ");

    $receptionnistes_treso = [];
    $especes_caissieres_total = 0;
    while ($r = $stmt_rep->fetch()) {
        $r_solde_main = max(0, (float)$r['total_encaisse'] - (float)$r['total_depenses'] - (float)$r['total_verse_boss']);
        $r['solde_en_main'] = $r_solde_main;
        $especes_caissieres_total += $r_solde_main;
        $receptionnistes_treso[] = $r;
    }

    $solde_total_tresorerie = $caisse_boss_total + $especes_caissieres_total;
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
    <!-- CAS 1 : TABLEAU DE BORD DU BOSS (LÉGER, ÉPURÉ, 2 PAR LIGNE) -->
    <!-- ========================================================= -->
    <?php if ($is_boss): 
        $solde_total_tresorerie = ($caisse_boss_total ?? 0) + ($especes_caissieres_total ?? 0);
    ?>

        <!-- ── 1. CARTE TRÉSORERIE ÉPURÉE ALMA HOTEL (CLAIR & COMPACT) ── -->
        <div class="alma-tresorerie-card">
            <div class="alma-tresorerie-header">
                <div class="alma-tresorerie-title">
                    <span>👑</span> <span>Trésorerie Globale &bull; Alma Hotel</span>
                </div>
                <button type="button" onclick="toggleOmBalance()" id="omToggleBtn" class="alma-btn-eye">
                    <span id="omToggleIcon">👁️</span> <span id="omToggleText">Masquer</span>
                </button>
            </div>

            <div class="alma-tresorerie-amount" id="omHeroAmount" onclick="toggleTresoDetails()" style="cursor: pointer; display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap;" title="Cliquez pour afficher la répartition par réceptionniste">
                <span><?= format_prix($solde_total_tresorerie) ?></span>
                <span style="font-size: 11.5px; font-weight: 700; color: #4f46e5; text-decoration: underline;">(Voir répartition ▾)</span>
            </div>

            <div class="alma-tresorerie-pills">
                <div class="alma-pill-badge" style="border-left: 3px solid #f59e0b;">
                    <span>👑</span> Coffre Boss : <strong id="omSubBoss" style="color: #b45309;"><?= format_prix($caisse_boss_total ?? 0) ?></strong>
                </div>
                <div class="alma-pill-badge" style="border-left: 3px solid #10b981;">
                    <span>💵</span> Espèces Guichet : <strong id="omSubGuichet" style="color: #065f46;"><?= format_prix($especes_caissieres_total ?? 0) ?></strong>
                </div>
            </div>

            <!-- ── Bouton / Accordéon de Répartition par Réceptionniste ── -->
            <div onclick="toggleTresoDetails()" id="btnToggleTreso" style="margin-top: 10px; padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; transition: all 0.2s ease;">
                <div style="font-size: 12px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 6px;">
                    <span>👥</span> <span>Qui détient combien ? Répartition Réceptionnistes</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <span style="font-size: 11px; color: #64748b; font-weight: 600;"><?= count($receptionnistes_treso) ?> employés</span>
                    <span id="tresoChevron" style="font-size: 11px; color: #4f46e5; transition: transform 0.25s ease;">▼</span>
                </div>
            </div>

            <!-- ── Volet Dépliable : Détail par Réceptionniste ── -->
            <div id="tresoDetailsContent" style="display: none; margin-top: 12px; padding-top: 12px; border-top: 1.5px dashed #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <div style="font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 800; color: #64748b;">
                        💵 Espèces physiques actuellement en caisse :
                    </div>
                    <a href="<?= BASE_URL ?>/modules/rapports/bilan_employes.php" style="font-size: 11px; color: #4f46e5; font-weight: 700;">
                        Bilan complet &rsaquo;
                    </a>
                </div>

                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <?php if (empty($receptionnistes_treso)): ?>
                        <div style="text-align: center; padding: 15px; color: #94a3b8; font-size: 12px;">Aucun employé enregistré.</div>
                    <?php else: ?>
                        <?php foreach ($receptionnistes_treso as $rep): 
                            $has_cash = ($rep['solde_en_main'] > 0);
                            $initials = strtoupper(mb_substr($rep['prenom'] ?? '', 0, 1) . mb_substr($rep['nom'] ?? '', 0, 1));
                        ?>
                            <div style="background: <?= $has_cash ? '#ffffff' : '#f8fafc' ?>; border: 1.5px solid <?= $has_cash ? '#bbf7d0' : '#e2e8f0' ?>; border-radius: 12px; padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; box-shadow: <?= $has_cash ? '0 2px 6px rgba(16,185,129,0.06)' : 'none' ?>;">
                                
                                <div style="display: flex; align-items: center; gap: 10px; min-width: 130px;">
                                    <div style="width: 34px; height: 34px; border-radius: 50%; background: <?= $has_cash ? '#ecfdf5' : '#f1f5f9' ?>; color: <?= $has_cash ? '#059669' : '#64748b' ?>; font-weight: 800; font-size: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid <?= $has_cash ? '#a7f3d0' : '#cbd5e1' ?>; flex-shrink: 0;">
                                        <?= $initials ?>
                                    </div>
                                    <div>
                                        <div style="font-size: 13.5px; font-weight: 800; color: #0f172a;">
                                            <?= h($rep['prenom'] . ' ' . $rep['nom']) ?>
                                        </div>
                                        <div style="font-size: 11px; color: #64748b;">
                                            <?= $rep['role'] === 'chef_receptionniste' ? 'Chef Réception' : 'Réceptionniste' ?>
                                            &bull; Total Prod: <?= format_prix($rep['total_encaisse']) ?>
                                        </div>
                                    </div>
                                </div>

                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div style="text-align: right;">
                                        <div class="rep-cash-amount" style="font-size: 14.5px; font-weight: 800; color: <?= $has_cash ? '#059669' : '#94a3b8' ?>;" data-real="<?= format_prix($rep['solde_en_main']) ?>">
                                            <?= format_prix($rep['solde_en_main']) ?>
                                        </div>
                                        <div style="font-size: 9.5px; font-weight: 700; color: <?= $has_cash ? '#059669' : '#94a3b8' ?>; text-transform: uppercase;">
                                            <?= $has_cash ? '● En tiroir' : 'À jour (0 F)' ?>
                                        </div>
                                    </div>

                                    <?php if ($has_cash): ?>
                                        <a href="<?= BASE_URL ?>/modules/comptabilite/transfert_boss.php?id_user=<?= $rep['id'] ?>&montant=<?= (int)$rep['solde_en_main'] ?>" class="btn btn-sm" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; border-radius: 8px; font-weight: 800; font-size: 11px; padding: 4px 8px; white-space: nowrap;" title="Encaisser les espèces détenues par <?= h($rep['prenom']) ?>">
                                            👑 Encaisser
                                        </a>
                                    <?php endif; ?>
                                </div>

                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Prélèvement Personnel Patron / Sortie du Coffre -->
                <div style="margin-top: 10px; padding: 8px 12px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="font-size: 11px; color: #92400e; line-height: 1.3;">
                        <strong>👑 Retrait Patron :</strong> Enregistrez vos prélèvements personnels pour sortir l'argent de la plateforme vers vos dépenses privées.
                    </div>
                    <a href="<?= BASE_URL ?>/modules/comptabilite/add_depense.php" class="btn btn-sm" style="background: #d97706; color: #ffffff; font-weight: 800; border-radius: 8px; font-size: 11px; padding: 4px 10px; white-space: nowrap;">
                        💸 Retirer / Sortir Somme
                    </a>
                </div>
            </div>
        </div>

        <!-- ── 2. BANNIÈRE HORIZONTALE OCCUPATION COMPACTE ── -->
        <a href="<?= BASE_URL ?>/modules/chambres/tracabilite.php" class="hotel-occupation-bar">
            <span style="font-size: 20px;">📈</span>
            <div style="flex: 1; min-width: 0;">
                <div style="font-size: 13px; font-weight: 800; color: #0f172a;">
                    Occupation Hôtel : <span style="color: #4f46e5;"><?= $taux_occupation ?>%</span>
                </div>
                <div style="font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?= $ch_occupees ?>/<?= $total_chambres ?> occupées &bull; <?= $ch_libres ?> libres &bull; <?= $ch_nettoyage ?> en ménage
                </div>
            </div>
            <span style="font-size: 16px; color: #94a3b8; font-weight: bold;">&rsaquo;</span>
        </a>

        <!-- ── 3. ACTIONS RAPIDES : 2 BOUTONS PAR LIGNE (DEMANDÉ PAR L'UTILISATEUR) ── -->
        <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
            ⚡ Opérations Clés (2 par ligne)
        </div>

        <div class="hotel-action-grid-2">
            <!-- Ligne 1 : Arrivée & Départ -->
            <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="hotel-action-card-2" style="border-left: 4px solid #10b981;">
                <div class="hac-icon" style="background: #ecfdf5; color: #059669;">⚡</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #065f46;">Arrivée</div>
                    <div class="hac-sub">Check-in direct</div>
                </div>
            </a>

            <a href="<?= BASE_URL ?>/modules/checkin_checkout/checkout.php" class="hotel-action-card-2" style="border-left: 4px solid #f59e0b;">
                <div class="hac-icon" style="background: #fffbeb; color: #d97706;">📋</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #92400e;">Départ</div>
                    <div class="hac-sub">Check-out client</div>
                </div>
            </a>

            <!-- Ligne 2 : Caisse & Nettoyage -->
            <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="hotel-action-card-2" style="border-left: 4px solid #6366f1;">
                <div class="hac-icon" style="background: #eef2ff; color: #4f46e5;">💰</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #3730a3;">Caisse</div>
                    <div class="hac-sub">Journal & Trésorerie</div>
                </div>
            </a>

            <a href="<?= BASE_URL ?>/modules/menage/tracabilite.php" class="hotel-action-card-2" style="border-left: 4px solid #06b6d4;">
                <div class="hac-icon" style="background: #ecfeff; color: #0891b2;">🧹</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #155e75;">Nettoyage</div>
                    <div class="hac-sub">Audit ménage</div>
                </div>
            </a>

            <!-- Ligne 3 : Occupations & + Dépense -->
            <a href="<?= BASE_URL ?>/modules/chambres/tracabilite.php" class="hotel-action-card-2" style="border-left: 4px solid #3b82f6;">
                <div class="hac-icon" style="background: #eff6ff; color: #2563eb;">🏨</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #1e40af;">Occupations</div>
                    <div class="hac-sub">Suivi chambres</div>
                </div>
            </a>

            <a href="<?= BASE_URL ?>/modules/comptabilite/add_depense.php" class="hotel-action-card-2" style="border-left: 4px solid #ef4444;">
                <div class="hac-icon" style="background: #fef2f2; color: #dc2626;">💸</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #991b1b;">+ Dépense</div>
                    <div class="hac-sub">Sortie / Achat</div>
                </div>
            </a>

            <!-- Ligne 4 : Employés & Paramètres -->
            <a href="<?= BASE_URL ?>/modules/rapports/bilan_employes.php" class="hotel-action-card-2" style="border-left: 4px solid #8b5cf6;">
                <div class="hac-icon" style="background: #f5f3ff; color: #7c3aed;">👥</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #5b21b6;">Employés</div>
                    <div class="hac-sub">Bilan & Caisses</div>
                </div>
            </a>

            <a href="<?= BASE_URL ?>/modules/parametres/gestion.php" class="hotel-action-card-2" style="border-left: 4px solid #64748b;">
                <div class="hac-icon" style="background: #f8fafc; color: #475569;">⚙️</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #1e293b;">Paramètres</div>
                    <div class="hac-sub">Gestion & CRUD</div>
                </div>
            </a>
        </div>

        <!-- ── 4. FLUX DU JOUR CLAIR ET COMPACT (2 COLONNES) ── -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px;">
            <div style="background: #ffffff; border-radius: 14px; padding: 12px 14px; border: 1px solid #e2e8f0; border-left: 4px solid #10b981; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                <div style="font-size: 10.5px; font-weight: 700; color: #059669; text-transform: uppercase;">🟢 Recettes Jour</div>
                <div style="font-size: 18px; font-weight: 800; color: #065f46; margin: 3px 0 2px;" id="omFluxEntrees"><?= format_prix($entrees_today) ?></div>
                <div style="font-size: 11px; color: #64748b;">Séjours encaissés</div>
            </div>
            <div style="background: #ffffff; border-radius: 14px; padding: 12px 14px; border: 1px solid #e2e8f0; border-left: 4px solid #ef4444; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                <div style="font-size: 10.5px; font-weight: 700; color: #dc2626; text-transform: uppercase;">🔴 Dépenses Jour</div>
                <div style="font-size: 18px; font-weight: 800; color: #991b1b; margin: 3px 0 2px;" id="omFluxSorties"><?= format_prix($sorties_today) ?></div>
                <div style="font-size: 11px; color: #64748b;">Charges & sorties</div>
            </div>
        </div>

    <!-- ========================================================= -->
    <!-- CAS 2 : TABLEAU DE BORD DE LA CAISSIÈRE (CLAIR, 2 PAR LIGNE)-->
    <!-- ========================================================= -->
    <?php else: ?>

        <!-- ── 1. CARTE CAISSE GUICHET ÉPURÉE ALMA HOTEL (CLAIR & COMPACT) ── -->
        <div class="alma-tresorerie-card">
            <div class="alma-tresorerie-header">
                <div class="alma-tresorerie-title">
                    <span>💵</span> <span>Caisse Guichet &bull; Mon Tiroir</span>
                </div>
                <button type="button" onclick="toggleOmBalance()" id="omToggleBtn" class="alma-btn-eye">
                    <span id="omToggleIcon">👁️</span> <span id="omToggleText">Masquer</span>
                </button>
            </div>

            <div class="alma-tresorerie-amount" id="omHeroAmount">
                <?= format_prix($solde_net_today) ?>
            </div>

            <div class="alma-tresorerie-pills">
                <div class="alma-pill-badge" style="border-left: 3px solid #10b981;">
                    <span>🟢</span> Entrées : <strong id="omSubBoss" style="color: #065f46;"><?= format_prix($entrees_today) ?></strong>
                </div>
                <div class="alma-pill-badge" style="border-left: 3px solid #ef4444;">
                    <span>🔴</span> Sorties : <strong id="omSubGuichet" style="color: #991b1b;"><?= format_prix($sorties_today) ?></strong>
                </div>
            </div>
        </div>

        <!-- ── 2. BANNIÈRE HORIZONTALE OCCUPATION COMPACTE ── -->
        <a href="<?= BASE_URL ?>/modules/chambres/list.php" class="hotel-occupation-bar">
            <span style="font-size: 20px;">🛏️</span>
            <div style="flex: 1; min-width: 0;">
                <div style="font-size: 13px; font-weight: 800; color: #0f172a;">
                    Occupation Chambres : <span style="color: #4f46e5;"><?= $taux_occupation ?>%</span>
                </div>
                <div style="font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    <?= $ch_occupees ?>/<?= $total_chambres ?> occupées &bull; <?= $ch_libres ?> chambres prêtes
                </div>
            </div>
            <span style="font-size: 16px; color: #94a3b8; font-weight: bold;">&rsaquo;</span>
        </a>

        <!-- ── 3. ACTIONS RAPIDES : 2 BOUTONS PAR LIGNE (CAISSIÈRE) ── -->
        <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">
            ⚡ Opérations Clés (2 par ligne)
        </div>

        <div class="hotel-action-grid-2">
            <!-- Ligne 1 : Arrivée & Départ -->
            <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="hotel-action-card-2" style="border-left: 4px solid #10b981;">
                <div class="hac-icon" style="background: #ecfdf5; color: #059669;">⚡</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #065f46;">Arrivée</div>
                    <div class="hac-sub">Check-in direct</div>
                </div>
            </a>

            <a href="<?= BASE_URL ?>/modules/checkin_checkout/checkout.php" class="hotel-action-card-2" style="border-left: 4px solid #f59e0b;">
                <div class="hac-icon" style="background: #fffbeb; color: #d97706;">📋</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #92400e;">Départ</div>
                    <div class="hac-sub">Check-out client</div>
                </div>
            </a>

            <!-- Ligne 2 : Caisse & Nettoyage -->
            <a href="<?= BASE_URL ?>/modules/comptabilite/journal.php" class="hotel-action-card-2" style="border-left: 4px solid #6366f1;">
                <div class="hac-icon" style="background: #eef2ff; color: #4f46e5;">💰</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #3730a3;">Caisse</div>
                    <div class="hac-sub">Journal & Soldes</div>
                </div>
            </a>

            <a href="<?= BASE_URL ?>/modules/menage/tracabilite.php" class="hotel-action-card-2" style="border-left: 4px solid #06b6d4;">
                <div class="hac-icon" style="background: #ecfeff; color: #0891b2;">🧹</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #155e75;">Nettoyage</div>
                    <div class="hac-sub">Suivi propreté</div>
                </div>
            </a>

            <!-- Ligne 3 : Sortie Dépense & Verser Boss / Chambres -->
            <a href="<?= BASE_URL ?>/modules/comptabilite/add_depense.php" class="hotel-action-card-2" style="border-left: 4px solid #ef4444;">
                <div class="hac-icon" style="background: #fef2f2; color: #dc2626;">💸</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #991b1b;">+ Dépense</div>
                    <div class="hac-sub">Sortie caisse</div>
                </div>
            </a>

            <?php if (is_supervisor()): ?>
            <a href="<?= BASE_URL ?>/modules/comptabilite/transfert_boss.php" class="hotel-action-card-2" style="border-left: 4px solid #eab308;">
                <div class="hac-icon" style="background: #fefce8; color: #ca8a04;">👑</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #854d0e;">Verser Boss</div>
                    <div class="hac-sub">Remise patron</div>
                </div>
            </a>
            <?php else: ?>
            <a href="<?= BASE_URL ?>/modules/chambres/list.php" class="hotel-action-card-2" style="border-left: 4px solid #3b82f6;">
                <div class="hac-icon" style="background: #eff6ff; color: #2563eb;">🛏️</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #1e40af;">Chambres</div>
                    <div class="hac-sub">État & tarifs</div>
                </div>
            </a>
            <?php endif; ?>

            <!-- Ligne 4 : Clients & Factures -->
            <a href="<?= BASE_URL ?>/modules/clients/list.php" class="hotel-action-card-2" style="border-left: 4px solid #8b5cf6;">
                <div class="hac-icon" style="background: #f5f3ff; color: #7c3aed;">👤</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #5b21b6;">Clients</div>
                    <div class="hac-sub">Fichier clients</div>
                </div>
            </a>

            <a href="<?= BASE_URL ?>/modules/facturation/list.php" class="hotel-action-card-2" style="border-left: 4px solid #ec4899;">
                <div class="hac-icon" style="background: #fdf2f8; color: #db2777;">🧾</div>
                <div class="hac-text">
                    <div class="hac-title" style="color: #9d174d;">Factures</div>
                    <div class="hac-sub">Reçus & clôtures</div>
                </div>
            </a>
        </div>

        <!-- ── 4. FLUX DU JOUR CLAIR ET COMPACT (2 COLONNES) ── -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px;">
            <div style="background: #ffffff; border-radius: 14px; padding: 12px 14px; border: 1px solid #e2e8f0; border-left: 4px solid #10b981; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                <div style="font-size: 10.5px; font-weight: 700; color: #059669; text-transform: uppercase;">🟢 Recettes Jour</div>
                <div style="font-size: 18px; font-weight: 800; color: #065f46; margin: 3px 0 2px;"><?= format_prix($entrees_today) ?></div>
                <div style="font-size: 11px; color: #64748b;">Séjours perçus</div>
            </div>
            <div style="background: #ffffff; border-radius: 14px; padding: 12px 14px; border: 1px solid #e2e8f0; border-left: 4px solid #ef4444; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                <div style="font-size: 10.5px; font-weight: 700; color: #dc2626; text-transform: uppercase;">🔴 Dépenses Jour</div>
                <div style="font-size: 18px; font-weight: 800; color: #991b1b; margin: 3px 0 2px;"><?= format_prix($sorties_today) ?></div>
                <div style="font-size: 11px; color: #64748b;">Sorties justifiées</div>
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
    const repAmounts = document.querySelectorAll('.rep-cash-amount');

    if (isOmBalanceHidden) {
        if (heroEl) {
            heroEl.innerHTML = '***** FCFA <span style="font-size: 11.5px; font-weight: 700; color: #4f46e5; text-decoration: underline;">(Voir répartition ▾)</span>';
        }
        if (bossEl) bossEl.textContent = '***** FCFA';
        if (guichetEl) guichetEl.textContent = '***** FCFA';
        repAmounts.forEach(el => el.textContent = '***** FCFA');
        if (btnText) btnText.textContent = 'Afficher';
        if (btnIcon) btnIcon.textContent = '👁️';
        localStorage.setItem('alma_om_balance_hidden', 'true');
    } else {
        if (heroEl) {
            heroEl.innerHTML = realOmHeroAmount + ' <span style="font-size: 11.5px; font-weight: 700; color: #4f46e5; text-decoration: underline;">(Voir répartition ▾)</span>';
        }
        if (bossEl) bossEl.textContent = realOmSubBoss;
        if (guichetEl) guichetEl.textContent = realOmSubGuichet;
        repAmounts.forEach(el => el.textContent = el.getAttribute('data-real') || '0 FCFA');
        if (btnText) btnText.textContent = 'Masquer';
        if (btnIcon) btnIcon.textContent = '👁️';
        localStorage.removeItem('alma_om_balance_hidden');
    }
}

// ── Bascule Volet Répartition Trésorerie par Réceptionniste ──
function toggleTresoDetails() {
    const content = document.getElementById('tresoDetailsContent');
    const chevron = document.getElementById('tresoChevron');
    if (!content) return;
    const isCurrentlyOpen = (content.style.display === 'block');
    if (isCurrentlyOpen) {
        content.style.display = 'none';
        if (chevron) chevron.style.transform = 'rotate(0deg)';
        localStorage.setItem('alma_treso_breakdown_open', '0');
    } else {
        content.style.display = 'block';
        if (chevron) chevron.style.transform = 'rotate(180deg)';
        localStorage.setItem('alma_treso_breakdown_open', '1');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('alma_om_balance_hidden') === 'true') {
        toggleOmBalance();
    }
    if (localStorage.getItem('alma_treso_breakdown_open') === '1') {
        const content = document.getElementById('tresoDetailsContent');
        const chevron = document.getElementById('tresoChevron');
        if (content) content.style.display = 'block';
        if (chevron) chevron.style.transform = 'rotate(180deg)';
    }
});
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
