<?php
// ============================================================
// modules/chambres/tracabilite.php — Journal des Occupations & Traçabilité Chambres
// "Comment tourne l'hôtel" : Qui, Quand, Montant, Caissière, Filtres
// ============================================================

require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
$is_boss = is_admin();

$page_title = "Journal des Occupations & Traçabilité";

// Période dynamique (Jour, Semaine, Mois, 2 Mois, 3 Mois, Année, Custom)
$period_preset = $_GET['period'] ?? 'month';
$custom_debut  = $_GET['date_debut'] ?? '';
$custom_fin    = $_GET['date_fin'] ?? '';

$period_info   = get_period_dates($period_preset, $custom_debut, $custom_fin);
$date_debut    = $period_info['debut'];
$date_fin      = $period_info['fin'];

// Filtres supplémentaires
$filter_numero = trim($_GET['numero'] ?? '');
$filter_statut = trim($_GET['statut'] ?? '');
$filter_client = trim($_GET['client'] ?? '');

// Action rapide de changement de statut depuis le modal (réceptionnistes / gestionnaire)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_statut_chambre'])) {
    $id_ch   = post_int('id_chambre');
    $n_statut = post('nouveau_statut');
    if ($id_ch > 0 && in_array($n_statut, ['libre','nettoyage','maintenance','occupee'])) {
        $stmt_s = $pdo->prepare("UPDATE chambres SET statut = ? WHERE id = ?");
        $stmt_s->execute([$n_statut, $id_ch]);
        set_flash('success', "Le statut de la chambre a été mis à jour vers '$n_statut'.");
    }
    redirect("tracabilite.php?period=$period_preset");
}

// 1. Chambres actuelles avec leur statut en temps réel
$sql_chambres = "SELECT c.*, tc.nom as type_nom, tc.prix_nuit, tc.avantages,
                 r.id as id_res, r.date_arrivee, r.date_depart, r.statut as res_statut, r.remarques as res_remarques,
                 cl.id as client_id, cl.nom as client_nom, cl.prenom as client_prenom, cl.telephone as client_tel, cl.piece_identite as client_piece
                 FROM chambres c
                 JOIN types_chambres tc ON c.id_type_chambre = tc.id
                 LEFT JOIN reservations r ON c.id = r.id_chambre AND r.statut = 'en_cours'
                 LEFT JOIN clients cl ON r.id_client = cl.id
                 WHERE 1=1";

$params_chambres = [];
if ($filter_numero) {
    $sql_chambres .= " AND c.numero LIKE ?";
    $params_chambres[] = "%$filter_numero%";
}
if ($filter_statut) {
    $sql_chambres .= " AND c.statut = ?";
    $params_chambres[] = $filter_statut;
}
$sql_chambres .= " ORDER BY c.numero ASC";
$stmt = $pdo->prepare($sql_chambres);
$stmt->execute($params_chambres);
$chambres = $stmt->fetchAll();

// Stats occupation temps réel
$total_chambres  = count($chambres);
$occupees_count  = 0;
$libres_count    = 0;
$nettoyage_count = 0;
$reservees_count = 0;

foreach ($chambres as $ch) {
    if ($ch['statut'] === 'occupee') $occupees_count++;
    elseif ($ch['statut'] === 'libre') $libres_count++;
    elseif ($ch['statut'] === 'nettoyage') $nettoyage_count++;
    elseif ($ch['statut'] === 'reservee') $reservees_count++;
}

$taux_occupation = $total_chambres > 0 ? round(($occupees_count / $total_chambres) * 100, 1) : 0;

// 2. Historique complet de la traçabilité des séjours selon la période choisie (Journal d'Occupation)
$sql_histo = "SELECT h.*, c.numero as chambre_num, c.etage as chambre_etage, tc.nom as type_nom, tc.prix_nuit,
              cl.nom as client_nom, cl.prenom as client_prenom, cl.telephone as client_tel,
              u.identifiant as recep_identifiant, u.nom as recep_nom, u.prenom as recep_prenom,
              r.id as id_res, r.date_arrivee as res_arrivee, r.date_depart as res_depart, r.statut as res_statut,
              f.id as id_facture, f.montant_total as fac_total, f.montant_final as fac_final, f.statut_paiement as fac_statut, f.mode_paiement as fac_mode
              FROM historique_occupation h
              JOIN chambres c ON h.id_chambre = c.id
              JOIN types_chambres tc ON c.id_type_chambre = tc.id
              JOIN clients cl ON h.id_client = cl.id
              LEFT JOIN utilisateurs u ON h.id_receptionniste = u.id
              LEFT JOIN reservations r ON h.id_reservation = r.id
              LEFT JOIN factures f ON (f.id_reservation = r.id OR (f.id_client = cl.id AND DATE(f.date_emission) = DATE(h.date_arrivee)))
              WHERE (DATE(h.date_arrivee) <= ? AND DATE(h.date_depart_prevue) >= ?)";

$params_histo = [$date_fin, $date_debut];

if ($filter_numero) {
    $sql_histo .= " AND c.numero LIKE ?";
    $params_histo[] = "%$filter_numero%";
}

if ($filter_client) {
    $sql_histo .= " AND (cl.nom LIKE ? OR cl.prenom LIKE ? OR cl.telephone LIKE ?)";
    $params_histo[] = "%$filter_client%";
    $params_histo[] = "%$filter_client%";
    $params_histo[] = "%$filter_client%";
}

$sql_histo .= " GROUP BY h.id ORDER BY h.date_arrivee DESC LIMIT 250";
$stmt_h = $pdo->prepare($sql_histo);
$stmt_h->execute($params_histo);
$historique = $stmt_h->fetchAll();

// Total recettes générées sur la période sélectionnée
$total_recettes_periode = 0;
$total_sejours_periode = count($historique);
foreach ($historique as $item) {
    if (!empty($item['fac_final'])) {
        $total_recettes_periode += (float)$item['fac_final'];
    } elseif (!empty($item['prix_nuit'])) {
        $nuits = nb_nuits($item['date_arrivee'], $item['date_depart_prevue']);
        $total_recettes_periode += ((float)$item['prix_nuit'] * $nuits);
    }
}

require_once '../../includes/header.php';
?>

<!-- En-tête d'impression A4 -->
<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Journal des Occupations & Traçabilité (<?= h($period_info['label']) ?>)</h2>
    <p>Édité le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title" style="display:flex; align-items:center; gap:10px;">
            <span>🏨</span> Journal des Occupations
        </h1>
        <p class="text-secondary" style="margin:5px 0 0 0;">
            Supervision de l'activité hôtelière : <strong>Qui</strong> a séjourné, <strong>Quand</strong>, dans <strong>Quelle chambre</strong>, <strong>Combien</strong> a été payé et <strong>Par qui</strong> ça a été enregistré.
        </p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php if (!$is_boss): ?>
            <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="btn btn-success">📥 Enregistrer une Occupation</a>
        <?php endif; ?>
        <button class="btn-print" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimer le Journal
        </button>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">← Retour Dashboard</a>
    </div>
</div>

<!-- Barre de Filtrage Période Dynamique (1, 2, 3 Mois, etc.) -->
<div class="card mb-4 no-print" style="border:1px solid var(--border-mid); box-shadow:var(--shadow-sm);">
    <div class="card-body p-3">
        <form method="GET" action="" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                <span style="font-weight:700; font-size:13px; color:var(--text-primary); margin-right:4px;">🕒 Période :</span>
                <a href="?period=today" class="btn btn-sm <?= $period_preset==='today'?'btn-primary':'btn-outline' ?>">Aujourd'hui</a>
                <a href="?period=yesterday" class="btn btn-sm <?= $period_preset==='yesterday'?'btn-primary':'btn-outline' ?>">Hier</a>
                <a href="?period=week" class="btn btn-sm <?= $period_preset==='week'?'btn-primary':'btn-outline' ?>">Cette Semaine</a>
                <a href="?period=month" class="btn btn-sm <?= ($period_preset==='month'||$period_preset==='1mois')?'btn-primary':'btn-outline' ?>">Ce Mois (1 mois)</a>
                <a href="?period=2months" class="btn btn-sm <?= ($period_preset==='2months'||$period_preset==='2mois')?'btn-primary':'btn-outline' ?>">2 Mois</a>
                <a href="?period=3months" class="btn btn-sm <?= ($period_preset==='3months'||$period_preset==='3mois')?'btn-primary':'btn-outline' ?>">3 Mois</a>
            </div>

            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="text" name="numero" class="form-control" style="width:110px; padding:5px 8px; font-size:13px;" placeholder="N° ch..." value="<?= h($filter_numero) ?>" title="Filtrer par numéro de chambre">
                <input type="text" name="client" class="form-control" style="width:140px; padding:5px 8px; font-size:13px;" placeholder="Nom / Tél client..." value="<?= h($filter_client) ?>" title="Filtrer par nom ou téléphone du client">
                <input type="hidden" name="period" value="custom">
                <span style="font-size:12px; color:var(--text-secondary);">Du :</span>
                <input type="date" name="date_debut" class="form-control" style="width:135px; padding:4px 8px; font-size:13px;" value="<?= h($date_debut) ?>">
                <span style="font-size:12px; color:var(--text-secondary);">Au :</span>
                <input type="date" name="date_fin" class="form-control" style="width:135px; padding:4px 8px; font-size:13px;" value="<?= h($date_fin) ?>">
                <button type="submit" class="btn btn-sm btn-secondary">🔍 Filtrer</button>
                <?php if ($filter_numero || $filter_client || $period_preset === 'custom'): ?>
                    <a href="tracabilite.php" class="btn btn-sm btn-outline" title="Réinitialiser les filtres">✕</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Synthèse KPI rapide pour la période -->
<div class="stats-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px;">
    <div class="card p-3 text-center" style="border-left:4px solid #059669;">
        <span class="text-secondary" style="font-size:13px; font-weight:600;">Séjours sur la Période</span>
        <h2 style="margin:8px 0; font-size:26px; color:#059669; font-weight:800;"><?= $total_sejours_periode ?></h2>
        <span class="text-secondary" style="font-size:12px;"><?= h($period_info['label']) ?></span>
    </div>
    <div class="card p-3 text-center" style="border-left:4px solid #d97706;">
        <span class="text-secondary" style="font-size:13px; font-weight:600;">Recettes Estimées Séjours</span>
        <h2 style="margin:8px 0; font-size:24px; color:#d97706; font-weight:800;"><?= format_money($total_recettes_periode) ?></h2>
        <span class="text-secondary" style="font-size:12px;">Hébergement facturé</span>
    </div>
    <div class="card p-3 text-center" style="border-left:4px solid #e11d48;">
        <span class="text-secondary" style="font-size:13px; font-weight:600;">Actuellement Occupées</span>
        <h2 style="margin:8px 0; font-size:26px; color:#e11d48; font-weight:800;"><?= $occupees_count ?></h2>
        <span class="text-secondary" style="font-size:12px;">sur <?= $total_chambres ?> chambres (<?= $taux_occupation ?>%)</span>
    </div>
    <div class="card p-3 text-center" style="border-left:4px solid #0891b2;">
        <span class="text-secondary" style="font-size:13px; font-weight:600;">En Nettoyage / Libres</span>
        <h2 style="margin:8px 0; font-size:26px; color:#0891b2; font-weight:800;"><?= $nettoyage_count ?> <span style="font-size:16px; font-weight:normal; color:var(--text-secondary);">/ <?= $libres_count ?></span></h2>
        <span class="text-secondary" style="font-size:12px;"><?= $nettoyage_count ?> à nettoyer, <?= $libres_count ?> prêtes</span>
    </div>
</div>

<!-- 1. Journal Historique des Occupations (La table maîtresse demandée par le Boss) -->
<div class="card mb-4" style="box-shadow:var(--shadow-sm); border:1px solid var(--border-light);">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card); padding:16px 20px;">
        <div>
            <h3 class="card-title" style="margin:0; font-size:17px; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:8px;">
                <span>📋</span> Registre Détaillé des Occupations
            </h3>
            <div style="font-size:12px; color:var(--text-secondary); margin-top:3px;">
                Traçabilité complète : Dates d'arrivée/départ, Nom du client, Numéro de chambre, Montant payé et Caissière responsable.
            </div>
        </div>
        <span class="badge badge-libre" style="font-size:13px; padding:6px 12px;"><?= count($historique) ?> enregistrement(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table" style="margin-bottom:0;">
                <thead style="background:var(--bg-app); font-size:12px; text-transform:uppercase; letter-spacing:0.5px;">
                    <tr>
                        <th style="padding:14px 16px;">Quand (Arrivée / Départ)</th>
                        <th style="padding:14px 16px;">Chambre</th>
                        <th style="padding:14px 16px;">Qui (Client)</th>
                        <th style="padding:14px 16px;">Montant du Séjour</th>
                        <th style="padding:14px 16px;">Enregistré Par (Caissière)</th>
                        <th style="padding:14px 16px; text-align:center;">Statut Séjour</th>
                        <th style="padding:14px 16px;" class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody style="font-size:14px;">
                    <?php foreach ($historique as $h_item): 
                        $nuits_calc = nb_nuits($h_item['date_arrivee'], $h_item['date_depart_prevue']);
                        $montant_affiche = !empty($h_item['fac_final']) ? (float)$h_item['fac_final'] : ((float)$h_item['prix_nuit'] * $nuits_calc);
                        $caissiere_nom = (!empty($h_item['recep_prenom']) || !empty($h_item['recep_nom'])) 
                                         ? trim($h_item['recep_prenom'] . ' ' . $h_item['recep_nom']) 
                                         : ($h_item['recep_identifiant'] ?? 'Système');
                    ?>
                    <tr style="border-bottom:1px solid var(--border-light); vertical-align:middle;">
                        <td style="padding:14px 16px;">
                            <div style="font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:6px;">
                                <span style="color:#059669;">📥</span> <?= format_datetime($h_item['date_arrivee']) ?>
                            </div>
                            <div style="font-size:12px; color:var(--text-secondary); margin-top:3px; display:flex; align-items:center; gap:6px;">
                                <span style="color:#e11d48;">📤</span> 
                                <?php if ($h_item['date_depart_effective']): ?>
                                    Parti : <?= format_datetime($h_item['date_depart_effective']) ?>
                                <?php else: ?>
                                    Prévu : <?= format_datetime($h_item['date_depart_prevue']) ?>
                                <?php endif; ?>
                            </div>
                            <span class="badge badge-secondary" style="font-size:11px; margin-top:4px;"><?= $nuits_calc ?> nuit(s)</span>
                        </td>
                        <td style="padding:14px 16px;">
                            <strong style="font-size:15px; color:#d97706;">N° <?= h($h_item['chambre_num']) ?></strong>
                            <div style="font-size:12px; color:var(--text-secondary);"><?= h($h_item['type_nom']) ?> (Ét. <?= $h_item['chambre_etage'] ?>)</div>
                        </td>
                        <td style="padding:14px 16px;">
                            <strong style="color:var(--text-primary); font-size:14px;"><?= h($h_item['client_nom'] . ' ' . $h_item['client_prenom']) ?></strong>
                            <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">
                                📞 <?= h($h_item['client_tel'] ?: 'Non renseigné') ?>
                            </div>
                        </td>
                        <td style="padding:14px 16px;">
                            <div style="font-size:15px; font-weight:800; color:#059669;">
                                <?= format_money($montant_affiche) ?>
                            </div>
                            <?php if (!empty($h_item['fac_statut'])): ?>
                                <div style="margin-top:3px;">
                                    <?= status_badge($h_item['fac_statut']) ?>
                                    <?php if (!empty($h_item['fac_mode'])): ?>
                                        <span style="font-size:11px; color:var(--text-secondary); margin-left:4px;">(<?= h(ucfirst($h_item['fac_mode'])) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <small class="text-secondary"><?= format_money($h_item['prix_nuit']) ?> / nuit</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding:14px 16px;">
                            <div style="font-weight:600; color:var(--text-primary); display:flex; align-items:center; gap:6px;">
                                <span>👤</span> <?= h($caissiere_nom) ?>
                            </div>
                            <small class="text-secondary">Réf: @<?= h($h_item['recep_identifiant'] ?? 'sys') ?></small>
                        </td>
                        <td style="padding:14px 16px; text-align:center;">
                            <?php if ($h_item['statut_sejour'] === 'en_cours'): ?>
                                <span class="badge" style="background:#fef3c7; color:#92400e; border:1px solid #f59e0b; padding:5px 10px; font-weight:700;">🟢 En cours</span>
                            <?php elseif ($h_item['statut_sejour'] === 'termine'): ?>
                                <span class="badge" style="background:#d1fae5; color:#065f46; border:1px solid #10b981; padding:5px 10px; font-weight:700;">✓ Terminé</span>
                            <?php else: ?>
                                <span class="badge" style="background:#fee2e2; color:#991b1b; padding:5px 10px; font-weight:700;"><?= h($h_item['statut_sejour']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:14px 16px;" class="no-print">
                            <?php if (!empty($h_item['id_facture'])): ?>
                                <a href="<?= BASE_URL ?>/modules/facturation/print.php?id=<?= (int)$h_item['id_facture'] ?>" class="btn btn-sm btn-outline" title="Voir Facture / Reçu" target="_blank">
                                    🧾 Facture
                                </a>
                            <?php endif; ?>
                            <?php if (!$is_boss && $h_item['statut_sejour'] === 'en_cours' && !empty($h_item['id_res'])): ?>
                                <a href="<?= BASE_URL ?>/modules/checkin_checkout/checkout.php?id=<?= (int)$h_item['id_res'] ?>" class="btn btn-sm btn-danger" style="margin-left:4px;">
                                    📤 Départ
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($historique)): ?>
                    <tr>
                        <td colspan="7" class="text-center p-4 text-secondary" style="padding:40px 20px;">
                            <div style="font-size:28px; margin-bottom:10px;">🏨</div>
                            <strong>Aucun séjour ou occupation trouvé pour cette période.</strong><br>
                            <span style="font-size:13px;">Essayez d'élargir la période de recherche ou vérifiez vos filtres.</span>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 2. Grille Visuelle des Chambres en Temps Réel -->
<div class="card mb-4" style="box-shadow:var(--shadow-sm); border:1px solid var(--border-light);">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; background:var(--bg-card); padding:16px 20px;">
        <div>
            <h3 class="card-title" style="margin:0; font-size:17px; font-weight:800; color:var(--text-primary); display:flex; align-items:center; gap:8px;">
                <span>🛏️</span> État Visuel des Chambres en Direct
            </h3>
            <div style="font-size:12px; color:var(--text-secondary); margin-top:3px;">
                Cliquez sur une chambre pour consulter sa fiche détaillée.
            </div>
        </div>
        <span class="badge badge-libre"><?= count($chambres) ?> chambres au total</span>
    </div>
    <div class="card-body">
        <div class="room-grid">
            <?php foreach ($chambres as $ch): ?>
            <div class="room-card" style="cursor:pointer;" onclick="openRoomModal(<?= htmlspecialchars(json_encode($ch), ENT_QUOTES, 'UTF-8') ?>)">
                <div class="room-card-header">
                    <img src="<?= BASE_URL ?>/assets/img/chambres/default.svg" alt="Photo Chambre <?= h($ch['numero']) ?>" class="room-card-img" onerror="this.src='<?= BASE_URL ?>/assets/img/chambres/default.svg'">
                    <span class="room-card-badge"><?= status_badge($ch['statut']) ?></span>
                    <span class="room-card-number">Chambre <?= h($ch['numero']) ?></span>
                </div>
                <div class="room-card-body">
                    <div>
                        <div class="room-card-title"><?= h($ch['type_nom']) ?> (Étage <?= $ch['etage'] ?>)</div>
                        <div class="room-card-meta">
                            <?php if ($ch['statut'] === 'occupee' && $ch['client_nom']): ?>
                                <strong style="color:#d97706;">Occupant :</strong> <?= h($ch['client_prenom'].' '.$ch['client_nom']) ?><br>
                                <span style="font-size:12px;">Du <?= format_date($ch['date_arrivee']) ?> au <?= format_date($ch['date_depart']) ?></span>
                            <?php elseif ($ch['statut'] === 'nettoyage'): ?>
                                <span class="text-info" style="font-size:13px; color:#0891b2; font-weight:600;">🧹 Nettoyage requis</span>
                            <?php else: ?>
                                <span class="text-success" style="font-size:13px; color:#059669; font-weight:600;">✓ Chambre disponible</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="room-card-footer">
                        <span class="room-card-price"><?= format_money($ch['prix_nuit']) ?> / nuit</span>
                        <span class="btn btn-sm btn-outline no-print" style="font-size:12px;">🔍 Fiche</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal d'Aperçu & Gestion de Chambre -->
<div id="roomModal" class="no-print" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.75); z-index:9999; justify-content:center; align-items:center;">
    <div style="background:var(--bg-card); border-radius:var(--r-lg); width:90%; max-width:650px; padding:24px; box-shadow:var(--shadow-xl); position:relative; border:2px solid var(--gold-500);">
        <button onclick="closeRoomModal()" style="position:absolute; top:16px; right:16px; background:none; border:none; color:var(--text-primary); font-size:24px; cursor:pointer;">&times;</button>
        
        <div style="display:flex; gap:20px; flex-wrap:wrap; margin-bottom:20px;">
            <img id="m_img" src="" alt="Chambre" style="width:180px; height:120px; object-fit:cover; border-radius:var(--r-md); border:1px solid var(--border-light);">
            <div style="flex:1;">
                <h2 id="m_num" style="margin:0 0 6px 0; color:#d97706; font-weight:800;">Chambre N°</h2>
                <div id="m_type" style="font-size:15px; font-weight:700; margin-bottom:6px; color:var(--text-primary);"></div>
                <div id="m_tarif" style="font-size:16px; font-weight:800; color:#059669; margin-bottom:8px;"></div>
                <div id="m_statut"></div>
            </div>
        </div>

        <div style="background:var(--bg-app); padding:16px; border-radius:var(--r-md); margin-bottom:20px; border:1px solid var(--border-light);">
            <h4 style="margin:0 0 8px 0; font-size:14px; color:var(--text-primary);">Informations de l'Occupant & Séjour</h4>
            <div id="m_occupant_info" style="font-size:14px; color:var(--text-secondary); line-height:1.5;">Aucun occupant actuellement. Chambre disponible.</div>
        </div>

        <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
            <?php if (!$is_boss): ?>
                <form method="POST" id="form_statut" style="display:inline-flex; gap:6px;">
                    <input type="hidden" name="change_statut_chambre" value="1">
                    <input type="hidden" name="id_chambre" id="m_id_chambre">
                    <select name="nouveau_statut" class="form-select" style="font-size:13px; height:36px;">
                        <option value="libre">Statut -> Libre / Prête</option>
                        <option value="nettoyage">Statut -> En Nettoyage</option>
                        <option value="maintenance">Statut -> En Maintenance</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline">Valider Statut</button>
                </form>
                <a id="m_bascule_link" href="#" class="btn" style="display:none; background:#fffbeb; color:#b45309; border:1.5px solid #fde68a; font-weight:700;">🌙 Basculer en Nuitée</a>
                <a id="m_checkin_link" href="#" class="btn btn-success">📥 Enregistrer Check-in</a>
            <?php endif; ?>
            <button onclick="closeRoomModal()" class="btn btn-secondary">Fermer</button>
        </div>
    </div>
</div>

<script>
function openRoomModal(ch) {
    document.getElementById('m_id_chambre').value = ch.id;
    document.getElementById('m_num').innerText = 'Chambre N° ' + ch.numero + ' (Étage ' + ch.etage + ')';
    document.getElementById('m_type').innerText = ch.type_nom;
    document.getElementById('m_tarif').innerText = new Intl.NumberFormat().format(ch.prix_nuit) + ' FCFA / nuit';
    document.getElementById('m_statut').innerHTML = 'Statut actuel : <strong>' + ch.statut.toUpperCase() + '</strong>';
    document.getElementById('m_img').src = '<?= BASE_URL ?>/assets/img/chambres/' + (ch.photo || 'default.svg');

    var occInfo = document.getElementById('m_occupant_info');
    var checkinLink = document.getElementById('m_checkin_link');
    var basculeLink = document.getElementById('m_bascule_link');

    if (basculeLink) basculeLink.style.display = 'none';

    if (ch.statut === 'occupee' && ch.client_nom) {
        var isSieste = (ch.res_remarques && ch.res_remarques.toLowerCase().indexOf('sieste') !== -1) || (ch.date_arrivee === ch.date_depart);
        var formuleLabel = isSieste ? '⚡ Sieste / Passage' : '🌙 Nuitée standard';

        occInfo.innerHTML = '<strong style="color:var(--text-primary);">Occupant actuel :</strong> ' + ch.client_prenom + ' ' + ch.client_nom + '<br>' +
                             '<strong style="color:var(--text-primary);">Téléphone :</strong> ' + (ch.client_tel || '—') + '<br>' +
                             '<strong style="color:var(--text-primary);">Formule :</strong> <span style="font-weight:700; color:' + (isSieste ? '#d97706' : '#4f46e5') + ';">' + formuleLabel + '</span><br>' +
                             '<strong style="color:var(--text-primary);">Arrivée :</strong> ' + ch.date_arrivee + ' — <strong style="color:var(--text-primary);">Départ prévu :</strong> ' + ch.date_depart;
        if (checkinLink) {
            checkinLink.href = '<?= BASE_URL ?>/modules/checkin_checkout/checkout.php?id=' + (ch.id_res || '');
            checkinLink.innerText = '📤 Enregistrer Check-out / Départ';
            checkinLink.className = 'btn btn-danger';
        }
        if (basculeLink && isSieste && ch.id_res) {
            basculeLink.href = '<?= BASE_URL ?>/modules/checkin_checkout/bascule_sieste.php?id=' + ch.id_res;
            basculeLink.style.display = 'inline-flex';
        }
    } else {
        occInfo.innerHTML = 'Chambre ' + ch.statut.toUpperCase() + '. Disponible pour une nouvelle occupation.';
        if (checkinLink) {
            checkinLink.href = '<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php?id_chambre=' + ch.id;
            checkinLink.innerText = '📥 Enregistrer Occupation (Arrivée Express)';
            checkinLink.className = 'btn btn-success';
        }
    }

    document.getElementById('roomModal').style.display = 'flex';
}

function closeRoomModal() {
    document.getElementById('roomModal').style.display = 'none';
}
</script>

<div class="print-footer">
    <p><?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?> — Registre officiel de traçabilité des séjours et occupations. Imprimé le <?= date('d/m/Y H:i') ?></p>
</div>

<?php require_once '../../includes/footer.php'; ?>
