<?php
// ============================================================
// modules/rapports/bilan_employes.php (v2.0)
// Bilan par Employé, Caisses Individuelles & Efficacité Ménage
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin(); // Réservé à l'administrateur / Patron

// Filtres
$filtre_periode = $_GET['periode'] ?? 'aujourdhui';
$date_debut     = $_GET['date_debut'] ?? '';
$date_fin       = $_GET['date_fin'] ?? '';
$selected_user  = (int)($_GET['id_user'] ?? 0);

$today = date('Y-m-d');
if ($filtre_periode === 'aujourdhui') {
    $date_debut = $today;
    $date_fin   = $today;
} elseif ($filtre_periode === 'hier') {
    $date_debut = date('Y-m-d', strtotime('-1 day'));
    $date_fin   = $date_debut;
} elseif ($filtre_periode === 'semaine') {
    $date_debut = date('Y-m-d', strtotime('monday this week'));
    $date_fin   = date('Y-m-d', strtotime('sunday this week'));
} elseif ($filtre_periode === 'mois' || $filtre_periode === '1mois') {
    $date_debut = date('Y-m-01');
    $date_fin   = date('Y-m-t');
} elseif ($filtre_periode === '2mois') {
    $date_debut = date('Y-m-01', strtotime('-1 month'));
    $date_fin   = $today;
} elseif ($filtre_periode === '3mois') {
    $date_debut = date('Y-m-01', strtotime('-2 month'));
    $date_fin   = $today;
} else {
    if (empty($date_debut)) $date_debut = $today;
    if (empty($date_fin))   $date_fin   = $today;
}

// 1. Liste de tous les utilisateurs / employés actifs
$stmt_users = $pdo->query("SELECT id, nom, prenom, identifiant, role FROM utilisateurs WHERE actif = 1 ORDER BY nom ASC, prenom ASC");
$utilisateurs = $stmt_users->fetchAll();

// 2. Calcul du bilan global pour chaque employé + Caisse du Boss
$bilan_employes = [];
$total_global_encaisse = 0;
$total_global_depenses = 0;
$total_global_verse_boss = 0;
$total_global_especes_main = 0;

foreach ($utilisateurs as $u) {
    $uid = $u['id'];

    // Décompte des jours réels de travail (dates uniques où l'employé a eu une activité enregistrée)
    $stmt_days = $pdo->prepare("
        SELECT DISTINCT action_date FROM (
            SELECT date_emission as action_date FROM factures WHERE id_receptionniste = ? AND date_emission BETWEEN ? AND ?
            UNION
            SELECT date_sortie as action_date FROM sorties_caisse WHERE id_utilisateur = ? AND date_sortie BETWEEN ? AND ?
            UNION
            SELECT date_prevue as action_date FROM taches_menage WHERE id_utilisateur = ? AND statut = 'terminee' AND (date_prevue BETWEEN ? AND ? OR DATE(created_at) BETWEEN ? AND ?)
            UNION
            SELECT date_arrivee as action_date FROM reservations WHERE id_receptionniste = ? AND date_arrivee BETWEEN ? AND ?
        ) as user_active_days
        ORDER BY action_date DESC
    ");
    $stmt_days->execute([$uid, $date_debut, $date_fin, $uid, $date_debut, $date_fin, $uid, $date_debut, $date_fin, $date_debut, $date_fin, $uid, $date_debut, $date_fin]);
    $dates_travaillees = $stmt_days->fetchAll(PDO::FETCH_COLUMN);
    $nb_jours_travail  = count($dates_travaillees);

    // Total Encaissé
    $stmt_enc = $pdo->prepare("
        SELECT COALESCE(SUM(montant_final), 0) FROM factures 
        WHERE id_receptionniste = ? AND date_emission BETWEEN ? AND ? AND statut_paiement IN ('payee', 'partielle')
    ");
    $stmt_enc->execute([$uid, $date_debut, $date_fin]);
    $encaisse = (float)$stmt_enc->fetchColumn();

    // Dépenses de fonctionnement (hors versement patron)
    $stmt_dep = $pdo->prepare("
        SELECT COALESCE(SUM(montant), 0) FROM sorties_caisse 
        WHERE id_utilisateur = ? AND date_sortie BETWEEN ? AND ? AND categorie != 'versement_patron'
    ");
    $stmt_dep->execute([$uid, $date_debut, $date_fin]);
    $depense_fonctionnement = (float)$stmt_dep->fetchColumn();

    // Versements remis au Boss
    $stmt_vb = $pdo->prepare("
        SELECT COALESCE(SUM(montant), 0) FROM sorties_caisse 
        WHERE id_utilisateur = ? AND date_sortie BETWEEN ? AND ? AND categorie = 'versement_patron'
    ");
    $stmt_vb->execute([$uid, $date_debut, $date_fin]);
    $verse_au_boss = (float)$stmt_vb->fetchColumn();

    // Total sorties
    $total_sorties = $depense_fonctionnement + $verse_au_boss;

    // Espèces physiques restantes dans le tiroir-caisse de la caissière
    $especes_en_main = $encaisse - $total_sorties;

    // Nombre de Ménages Validés
    $stmt_men = $pdo->prepare("
        SELECT COUNT(*) FROM taches_menage 
        WHERE id_utilisateur = ? AND statut = 'terminee' 
          AND (date_prevue BETWEEN ? AND ? OR DATE(created_at) BETWEEN ? AND ?)
    ");
    $stmt_men->execute([$uid, $date_debut, $date_fin, $date_debut, $date_fin]);
    $nb_menages = (int)$stmt_men->fetchColumn();

    // Nombre d'Arrivées / Check-ins gérés
    $stmt_chk = $pdo->prepare("
        SELECT COUNT(*) FROM reservations 
        WHERE id_receptionniste = ? AND date_arrivee BETWEEN ? AND ?
    ");
    $stmt_chk->execute([$uid, $date_debut, $date_fin]);
    $nb_checkins = (int)$stmt_chk->fetchColumn();

    $bilan_employes[$uid] = [
        'user'                   => $u,
        'encaisse'               => $encaisse,
        'depense_fonctionnement' => $depense_fonctionnement,
        'verse_au_boss'          => $verse_au_boss,
        'total_sorties'          => $total_sorties,
        'especes_en_main'        => $especes_en_main,
        'nb_menages'             => $nb_menages,
        'nb_checkins'            => $nb_checkins,
        'nb_jours_travail'       => $nb_jours_travail,
        'dates_travaillees'      => $dates_travaillees,
    ];

    $total_global_encaisse     += $encaisse;
    $total_global_depenses     += $depense_fonctionnement;
    $total_global_verse_boss   += $verse_au_boss;
    $total_global_especes_main += $especes_en_main;
}

// Récupération de tous les versements effectués au Boss sur la période (Caisse du Boss)
$stmt_all_vb = $pdo->prepare("
    SELECT s.*, u.nom as agent_nom, u.prenom as agent_prenom, u.identifiant as agent_user
    FROM sorties_caisse s
    JOIN utilisateurs u ON s.id_utilisateur = u.id
    WHERE s.categorie = 'versement_patron' AND s.date_sortie BETWEEN ? AND ?
    ORDER BY s.created_at DESC
");
$stmt_all_vb->execute([$date_debut, $date_fin]);
$versements_boss_list = $stmt_all_vb->fetchAll();

// 3. Si un employé précis est sélectionné, charger ses détails
$details_user = null;
$user_encaissements = [];
$user_sorties = [];
$user_menages = [];

if ($selected_user > 0 && isset($bilan_employes[$selected_user])) {
    $details_user = $bilan_employes[$selected_user];

    // Encaissements détaillés
    $stmt_de = $pdo->prepare("
        SELECT f.*, c.nom as client_nom, c.prenom as client_prenom, ch.numero as chambre_num
        FROM factures f
        JOIN clients c ON f.id_client = c.id
        LEFT JOIN reservations r ON f.id_reservation = r.id
        LEFT JOIN chambres ch ON r.id_chambre = ch.id
        WHERE f.id_receptionniste = ? AND f.date_emission BETWEEN ? AND ? AND f.statut_paiement IN ('payee', 'partielle')
        ORDER BY f.created_at DESC
    ");
    $stmt_de->execute([$selected_user, $date_debut, $date_fin]);
    $user_encaissements = $stmt_de->fetchAll();

    // Sorties détaillées
    $stmt_ds = $pdo->prepare("
        SELECT * FROM sorties_caisse 
        WHERE id_utilisateur = ? AND date_sortie BETWEEN ? AND ?
        ORDER BY created_at DESC
    ");
    $stmt_ds->execute([$selected_user, $date_debut, $date_fin]);
    $user_sorties = $stmt_ds->fetchAll();

    // Ménages détaillés
    $stmt_dm = $pdo->prepare("
        SELECT tm.*, ch.numero as chambre_num, tc.nom as type_nom
        FROM taches_menage tm
        JOIN chambres ch ON tm.id_chambre = ch.id
        JOIN types_chambres tc ON ch.id_type_chambre = tc.id
        WHERE tm.id_utilisateur = ? AND tm.statut = 'terminee'
          AND (tm.date_prevue BETWEEN ? AND ? OR DATE(tm.created_at) BETWEEN ? AND ?)
        ORDER BY tm.created_at DESC
    ");
    $stmt_dm->execute([$selected_user, $date_debut, $date_fin, $date_debut, $date_fin]);
    $user_menages = $stmt_dm->fetchAll();
}

// 4. Statistiques de nettoyage par Chambre (pour évaluer le matériel / savon)
$stmt_ch_clean = $pdo->prepare("
    SELECT ch.id, ch.numero, tc.nom as type_nom, COUNT(tm.id) as total_nettoyages
    FROM chambres ch
    JOIN types_chambres tc ON ch.id_type_chambre = tc.id
    LEFT JOIN taches_menage tm ON tm.id_chambre = ch.id AND tm.statut = 'terminee' AND (tm.date_prevue BETWEEN ? AND ? OR DATE(tm.created_at) BETWEEN ? AND ?)
    GROUP BY ch.id, ch.numero, tc.nom
    ORDER BY total_nettoyages DESC, ch.numero ASC
");
$stmt_ch_clean->execute([$date_debut, $date_fin, $date_debut, $date_fin]);
$stat_chambres_nettoyage = $stmt_ch_clean->fetchAll();

$page_title = "Bilan des Caisses & Traçabilité des Employés";
require_once '../../includes/header.php';
?>

<div style="max-width: 1240px; margin: 0 auto;">
    
    <!-- ── En-tête ── -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 800; color: var(--text-primary); margin: 0;">👥 Caisse du Boss & Caisses des Caissières</h1>
            <p style="color: var(--text-secondary); margin-top: 4px; font-size: 14px;">
                Traçabilité des fonds remis au Patron, soldes en tiroir-caisse et rotation du ménage du 
                <strong><?= format_date($date_debut) ?></strong> au <strong><?= format_date($date_fin) ?></strong>.
            </p>
        </div>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <?php if ($selected_user > 0): ?>
                <a href="print_shift_employe.php?id_user=<?= $selected_user ?>&periode=<?= urlencode($filtre_periode) ?>&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>" target="_blank" class="btn btn-primary" style="box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);">
                    <span>🖨️</span> Imprimer Clôture Shift (A4)
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-outline">
                ← Retour Dashboard
            </a>
        </div>
    </div>

    <!-- ── Cartes de Synthèse Caisse Boss & Caisses Caissières ── -->
    <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 28px;">
        
        <div class="metric-card" style="border-left: 4px solid #d97706; background: #fffdfa;">
            <div class="metric-card-header">
                <span class="metric-label" style="color: #d97706;">👑 Caisse du Boss</span>
                <span class="badge" style="background: #fef3c7; color: #b45309; border: 1px solid #fde68a; font-weight: 700;">Direction</span>
            </div>
            <div class="metric-value" style="color: #d97706;"><?= format_prix($total_global_verse_boss) ?></div>
            <div class="metric-sub"><?= count($versements_boss_list) ?> versement(s) reçu(s) en main propre</div>
        </div>

        <div class="metric-card" style="border-left: 4px solid #4f46e5;">
            <div class="metric-card-header">
                <span class="metric-label" style="color: var(--primary);">💵 Tiroir-Caisse Caissières</span>
                <span class="badge badge-nettoyage">En Service</span>
            </div>
            <div class="metric-value" style="color: var(--text-primary);"><?= format_prix($total_global_especes_main) ?></div>
            <div class="metric-sub">Espèces physiques en main du personnel</div>
        </div>

        <div class="metric-card" style="border-left: 4px solid #059669;">
            <div class="metric-card-header">
                <span class="metric-label" style="color: #059669;">🟢 Total Recettes Séjours</span>
                <span class="badge badge-libre">Entrées</span>
            </div>
            <div class="metric-value" style="color: #059669;"><?= format_prix($total_global_encaisse) ?></div>
            <div class="metric-sub">Paiements encaissés par le personnel</div>
        </div>

        <div class="metric-card" style="border-left: 4px solid #e11d48;">
            <div class="metric-card-header">
                <span class="metric-label" style="color: #e11d48;">🔴 Achats Savon & Dépenses</span>
                <span class="badge badge-occupee">Charges</span>
            </div>
            <div class="metric-value" style="color: #e11d48;"><?= format_prix($total_global_depenses) ?></div>
            <div class="metric-sub">Fournitures, eau, entretien réglés</div>
        </div>

    </div>

    <!-- ── Filtres Période & Employé ── -->
    <div class="card" style="margin-bottom: 28px;">
        <div class="card-body" style="padding: 20px 24px;">
            <form method="GET" action="bilan_employes.php" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end;">
                
                <div style="min-width: 180px;">
                    <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Période d'Analyse :</label>
                    <select name="periode" class="form-select" onchange="toggleCustomDates(this.value)" style="font-weight: 700;">
                        <option value="aujourdhui" <?= $filtre_periode === 'aujourdhui' ? 'selected' : '' ?>>Aujourd'hui</option>
                        <option value="hier" <?= $filtre_periode === 'hier' ? 'selected' : '' ?>>Hier</option>
                        <option value="semaine" <?= $filtre_periode === 'semaine' ? 'selected' : '' ?>>Cette semaine</option>
                        <option value="mois" <?= ($filtre_periode === 'mois' || $filtre_periode === '1mois') ? 'selected' : '' ?>>Ce mois-ci (1 mois)</option>
                        <option value="2mois" <?= $filtre_periode === '2mois' ? 'selected' : '' ?>>Les 2 derniers mois</option>
                        <option value="3mois" <?= $filtre_periode === '3mois' ? 'selected' : '' ?>>Les 3 derniers mois</option>
                        <option value="personnalisee" <?= $filtre_periode === 'personnalisee' ? 'selected' : '' ?>>Plage personnalisée...</option>
                    </select>
                </div>

                <div id="customDates" style="display: <?= $filtre_periode === 'personnalisee' ? 'flex' : 'none' ?>; gap: 10px;">
                    <div>
                        <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Du :</label>
                        <input type="date" name="date_debut" value="<?= $date_debut ?>" class="form-input">
                    </div>
                    <div>
                        <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Au :</label>
                        <input type="date" name="date_fin" value="<?= $date_fin ?>" class="form-input">
                    </div>
                </div>

                <div style="min-width: 240px;">
                    <label class="form-label" style="font-size: 12px; margin-bottom: 4px;">Voir le Détail d'un Employé :</label>
                    <select name="id_user" class="form-select" style="font-weight: 600;">
                        <option value="0">-- Vue Globale (Tous les Employés) --</option>
                        <?php foreach ($utilisateurs as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $selected_user == $u['id'] ? 'selected' : '' ?>>
                                👤 <?= h($u['nom'] . ' ' . $u['prenom']) ?> (<?= h(ucfirst($u['role'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="height: 46px;">
                    🔍 Actualiser
                </button>
            </form>
        </div>
    </div>

    <!-- ── CAS 1 : VUE DÉTAILLÉE D'UN EMPLOYÉ SÉLECTIONNÉ ── -->
    <?php if ($details_user): 
        $u_info = $details_user['user'];
    ?>
        <div class="card" style="margin-bottom: 32px; border: 2px solid #c7d2fe;">
            <div class="card-header" style="background: #f8fafc;">
                <div>
                    <h2 class="card-title">
                        👤 Fiche Individuelle de Caisse & Activités : <?= h($u_info['prenom'] . ' ' . $u_info['nom']) ?>
                    </h2>
                    <p style="color: var(--text-secondary); margin-top: 2px; font-size: 13px;">
                        Rôle : <span style="font-weight: 600; text-transform: capitalize;"><?= h($u_info['role']) ?></span> | Identifiant : <code><?= h($u_info['identifiant']) ?></code> | Présence : <strong><?= $details_user['nb_jours_travail'] ?> jour(s) actif(s)</strong>
                    </p>
                </div>
                <a href="bilan_employes.php?periode=<?= urlencode($filtre_periode) ?>&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>" class="btn btn-sm btn-outline">
                    ← Revenir au tableau général
                </a>
            </div>

            <div class="card-body">
                <!-- Cartes chiffres de cet employé -->
                <div class="metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 28px;">
                    <div class="metric-card" style="border-left: 4px solid #4f46e5;">
                        <div class="metric-label" style="color: var(--primary);">Jours Travaillés</div>
                        <div class="metric-value" style="color: var(--text-primary);"><?= $details_user['nb_jours_travail'] ?> j</div>
                        <div class="metric-sub">Dates de shift actives</div>
                    </div>

                    <div class="metric-card" style="border-left: 4px solid #059669;">
                        <div class="metric-label" style="color: #059669;">1. Encaissements Séjours</div>
                        <div class="metric-value" style="color: #059669;"><?= format_prix($details_user['encaisse']) ?></div>
                        <div class="metric-sub"><?= count($user_encaissements) ?> paiement(s) perçu(s)</div>
                    </div>

                    <div class="metric-card" style="border-left: 4px solid #e11d48;">
                        <div class="metric-label" style="color: #e11d48;">2. Dépenses Fonctionnement</div>
                        <div class="metric-value" style="color: #e11d48;"><?= format_prix($details_user['depense_fonctionnement']) ?></div>
                        <div class="metric-sub">Achats savon, eau, maintenance</div>
                    </div>

                    <div class="metric-card" style="border-left: 4px solid #d97706;">
                        <div class="metric-label" style="color: #d97706;">3. Versé au Boss (Direction)</div>
                        <div class="metric-value" style="color: #d97706;"><?= format_prix($details_user['verse_au_boss']) ?></div>
                        <div class="metric-sub">Fonds remis en main propre</div>
                    </div>

                    <div class="metric-card" style="border-left: 4px solid #0891b2;">
                        <div class="metric-label" style="color: #0891b2;">4. Solde Restant en Main</div>
                        <div class="metric-value" style="color: <?= $details_user['especes_en_main'] >= 0 ? '#0891b2' : '#e11d48' ?>;"><?= format_prix($details_user['especes_en_main']) ?></div>
                        <div class="metric-sub">Espèces dans son tiroir-caisse</div>
                    </div>
                </div>

                <!-- Détail 0 : Dates de présence / jours travaillés -->
                <?php if (!empty($details_user['dates_travaillees'])): ?>
                    <div style="margin-bottom: 28px; background: #f8fafc; border: 1px solid var(--border-light); border-radius: 12px; padding: 18px 20px;">
                        <div style="font-size: 14px; font-weight: 700; color: var(--text-primary); margin-bottom: 10px;">
                            📅 Calendrier des Jours d'Activité Enregistrés (<?= $details_user['nb_jours_travail'] ?> jours) :
                        </div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php foreach ($details_user['dates_travaillees'] as $dt): ?>
                                <span class="badge" style="background: #ffffff; border: 1px solid #cbd5e1; color: var(--text-primary); padding: 6px 10px; font-size: 12px; font-weight: 600;">
                                    🗓️ <?= format_date($dt) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Détail 1 : Ménages validés par cette caissière -->
                <div style="margin-bottom: 32px;">
                    <h3 style="font-size: 16px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px;">
                        🧹 Historique des Ménages Réalisés par <?= h($u_info['prenom']) ?> (<?= count($user_menages) ?>)
                    </h3>
                    <?php if (empty($user_menages)): ?>
                        <p style="color: var(--text-light); font-style: italic; font-size: 13px;">Aucun ménage enregistré sur cette période.</p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date & Heure</th>
                                        <th>Chambre</th>
                                        <th>Type Chambre</th>
                                        <th>Notes / Traçabilité</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_menages as $um): ?>
                                        <tr>
                                            <td style="color: var(--text-secondary);"><?= date('d/m/Y H:i', strtotime($um['created_at'])) ?></td>
                                            <td style="font-weight: 800; color: var(--text-primary);">Chambre <?= h($um['chambre_num']) ?></td>
                                            <td style="color: var(--text-secondary);"><?= h($um['type_nom']) ?></td>
                                            <td><span class="badge badge-libre">✔ <?= h($um['notes'] ?: 'Nettoyé et validé') ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Détail 2 : Sorties de caisse et Versements Boss -->
                <div>
                    <h3 style="font-size: 16px; font-weight: 700; color: #e11d48; margin-bottom: 12px;">
                        💸 Sorties de Caisse & Versements Patron Justifiés par <?= h($u_info['prenom']) ?>
                    </h3>
                    <?php if (empty($user_sorties)): ?>
                        <p style="color: var(--text-light); font-style: italic; font-size: 13px;">Aucune sortie de caisse enregistrée sur cette période.</p>
                    <?php else: ?>
                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date & Heure</th>
                                        <th>Type</th>
                                        <th>Motif / Libellé</th>
                                        <th style="text-align: right;">Montant</th>
                                        <th style="text-align: center;">Reçu / Décharge</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_sorties as $us): ?>
                                        <tr>
                                            <td style="color: var(--text-secondary);"><?= date('d/m/Y H:i', strtotime($us['created_at'])) ?></td>
                                            <td>
                                                <?php if ($us['categorie'] === 'versement_patron'): ?>
                                                    <span class="badge" style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; font-weight:800;">👑 VERS. BOSS</span>
                                                <?php else: ?>
                                                    <span class="badge badge-occupee"><?= h($us['categorie']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-weight: 600; color: var(--text-primary);"><?= h($us['libelle']) ?></td>
                                            <td style="text-align: right; font-weight: 800; color: <?= $us['categorie'] === 'versement_patron' ? '#d97706' : '#e11d48' ?>;">
                                                - <?= format_prix($us['montant']) ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <a href="<?= BASE_URL ?>/modules/comptabilite/print_depense.php?id=<?= $us['id'] ?>" target="_blank" class="btn btn-sm btn-outline">
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

            </div>
        </div>
    <?php endif; ?>

    <!-- ── CAS 2 : TABLEAU DES CAISSES INDIVIDUELLES PAR CAISSIÈRE ── -->
    <div class="card" style="margin-bottom: 28px;">
        <div class="card-header">
            <h2 class="card-title">
                👥 Contrôle des Employés : Jours Travaillés, Tiroirs-Caisses & Ménages
            </h2>
        </div>

        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employé(e)</th>
                        <th>Rôle</th>
                        <th style="text-align: center;">📅 Jours Travaillés</th>
                        <th style="text-align: right;">1. Encaissements (+)</th>
                        <th style="text-align: right;">2. Dépenses Savon (-)</th>
                        <th style="text-align: right;">3. Versé au Boss (-)</th>
                        <th style="text-align: right; background: #f8fafc;">💵 En Main (Tiroir)</th>
                        <th style="text-align: center;">Ménages Validés</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bilan_employes as $uid => $b): 
                        $u = $b['user'];
                    ?>
                        <tr>
                            <td style="font-weight: 700; color: var(--text-primary);">
                                👤 <?= h($u['nom'] . ' ' . $u['prenom']) ?>
                            </td>
                            <td style="text-transform: capitalize; color: var(--text-secondary);">
                                <?= h($u['role']) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge" style="background: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; font-weight: 800; font-size: 13px;">
                                    📅 <?= $b['nb_jours_travail'] ?> jour(s)
                                </span>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #059669;">
                                <?= format_prix($b['encaisse']) ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #e11d48;">
                                <?= format_prix($b['depense_fonctionnement']) ?>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #d97706;">
                                <?= format_prix($b['verse_au_boss']) ?>
                            </td>
                            <td style="text-align: right; font-weight: 800; font-size: 15px; background: #f8fafc; color: <?= $b['especes_en_main'] >= 0 ? '#0891b2' : '#e11d48' ?>;">
                                <?= format_prix($b['especes_en_main']) ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-reservee">
                                    🧹 <?= $b['nb_menages'] ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <a href="bilan_employes.php?id_user=<?= $uid ?>&periode=<?= urlencode($filtre_periode) ?>&date_debut=<?= urlencode($date_debut) ?>&date_fin=<?= urlencode($date_fin) ?>" class="btn btn-sm btn-outline">
                                    🔍 Voir Détail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── CAS 3 : TRAÇABILITÉ DE LA CAISSE DU BOSS (VERSEMENTS REÇUS) ── -->
    <div class="card" style="margin-bottom: 28px; border-left: 4px solid #d97706;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 class="card-title" style="color: #92400e;">
                    👑 Caisse du Boss — Traçabilité des Versements Reçus en Main Propre
                </h2>
                <p style="color: var(--text-secondary); margin-top: 2px; font-size: 13px;">
                    Historique de tous les fonds remis par les caissières avec reçus de décharge signés.
                </p>
            </div>
            <div style="font-size: 18px; font-weight: 800; color: #d97706;">
                Total Reçu : <?= format_prix($total_global_verse_boss) ?>
            </div>
        </div>

        <?php if (empty($versements_boss_list)): ?>
            <div class="card-body" style="text-align: center; color: var(--text-light); padding: 30px; font-style: italic;">
                Aucun versement enregistré au Boss pour cette période.
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date & Heure</th>
                            <th>Caissière / Agent Remettant</th>
                            <th>Motif / Référence</th>
                            <th style="text-align: right;">Montant Reçu par le Boss</th>
                            <th style="text-align: center;">Décharge A4</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($versements_boss_list as $vb): ?>
                            <tr>
                                <td style="color: var(--text-secondary);"><?= date('d/m/Y H:i', strtotime($vb['created_at'])) ?></td>
                                <td style="font-weight: 700; color: var(--text-primary);">
                                    👤 <?= h($vb['agent_prenom'] . ' ' . $vb['agent_nom']) ?>
                                </td>
                                <td style="color: var(--text-secondary);">
                                    <?= h($vb['libelle']) ?> <?= $vb['notes'] ? ' (' . h($vb['notes']) . ')' : '' ?>
                                </td>
                                <td style="text-align: right; font-weight: 800; font-size: 15px; color: #d97706;">
                                    + <?= format_prix($vb['montant']) ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="<?= BASE_URL ?>/modules/comptabilite/print_depense.php?id=<?= $vb['id'] ?>" target="_blank" class="btn btn-sm btn-outline">
                                        🖨️ Reçu Décharge
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- ── SECTION 4 : TRAÇABILITÉ DES MÉNAGES PAR CHAMBRE (ÉVALUATION MATÉRIEL / SAVON) ── -->
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">
                    🛏️ Traçabilité des Nettoyages par Chambre (Contrôle Conso Savon & Matériel)
                </h2>
                <p style="color: var(--text-secondary); margin-top: 4px; font-size: 13px;">
                    Permet au patron de corréler le nombre réel de nettoyages par chambre avec les achats de savons, détergents et draps.
                </p>
            </div>
        </div>

        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
                <?php foreach ($stat_chambres_nettoyage as $sc): ?>
                    <div style="background: #ffffff; border: 1.5px solid var(--border-light); border-radius: 12px; padding: 18px; text-align: center; box-shadow: var(--shadow-xs);">
                        <div style="font-size: 18px; font-weight: 800; color: var(--text-primary);">Chambre <?= h($sc['numero']) ?></div>
                        <div style="font-size: 12.5px; color: var(--text-secondary); margin-top: 2px;"><?= h($sc['type_nom']) ?></div>
                        <div style="margin-top: 10px; font-size: 22px; font-weight: 800; color: var(--primary);">
                            <?= $sc['total_nettoyages'] ?> <span style="font-size: 12px; font-weight: 500; color: var(--text-light);">nettoyage(s)</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<script>
function toggleCustomDates(val) {
    document.getElementById('customDates').style.display = (val === 'personnalisee') ? 'flex' : 'none';
}
</script>

<?php require_once '../../includes/footer.php'; ?>
