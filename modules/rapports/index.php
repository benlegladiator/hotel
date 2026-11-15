<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

$page_title = "Centre de Rapports & Bilan Global";

// Période dynamique (Jour, Semaine, Mois, Année, Custom)
$period_preset = $_GET['period'] ?? 'month';
$custom_debut  = $_GET['date_debut'] ?? '';
$custom_fin    = $_GET['date_fin'] ?? '';

$period_info   = get_period_dates($period_preset, $custom_debut, $custom_fin);
$date_debut    = $period_info['debut'];
$date_fin      = $period_info['fin'];

// 1. Recettes et Chiffre d'Affaires sur la période
$stmt_rev = $pdo->prepare("SELECT SUM(montant_final) as total_net,
                           SUM(montant_sejour) as total_sejour,
                           SUM(montant_services) as total_extras,
                           SUM(CASE WHEN statut_paiement = 'impayee' THEN montant_final ELSE 0 END) as total_impaye
                           FROM factures
                           WHERE date_emission BETWEEN ? AND ?");
$stmt_rev->execute([$date_debut, $date_fin]);
$bilan_fin = $stmt_rev->fetch();

$rev_total_histo = $pdo->query("SELECT SUM(montant_final) as t FROM factures WHERE statut_paiement IN ('payee','partielle')")->fetch()['t'] ?? 0;

// 2. Taux d'Occupation et Séjours sur la période
$stmt_res = $pdo->prepare("SELECT COUNT(*) as nb_reservations,
                           SUM(DATEDIFF(date_depart, date_arrivee)) as nuitees_totales
                           FROM reservations
                           WHERE (date_arrivee <= ? AND date_depart >= ?) AND statut IN ('en_cours','terminee','confirmee')");
$stmt_res->execute([$date_fin, $date_debut]);
$res_stats = $stmt_res->fetch();

$chambres_totales = (int)$pdo->query("SELECT COUNT(*) FROM chambres")->fetchColumn();
$nb_jours_periode  = max(1, (int)(new DateTime($date_debut))->diff(new DateTime($date_fin))->days + 1);
$nuitées_possibles = $chambres_totales * $nb_jours_periode;
$nuitées_vendues   = (int)($res_stats['nuitees_totales'] ?? 0);
$taux_occ_periode  = $nuitées_possibles > 0 ? round(($nuitées_vendues / $nuitées_possibles) * 100, 1) : 0;

// 3. Performance Ménage sur la période
$stmt_menage = $pdo->prepare("SELECT p.nom, p.prenom, COUNT(tm.id) as total_taches,
                              SUM(CASE WHEN tm.statut IN ('terminee','inspectee') THEN 1 ELSE 0 END) as terminees,
                              ROUND(AVG(tm.duree_minutes), 0) as duree_moy
                              FROM personnel p
                              LEFT JOIN taches_menage tm ON p.id = tm.id_agent AND (tm.date_prevue BETWEEN ? AND ?)
                              WHERE p.role = 'menage' AND p.actif = 1
                              GROUP BY p.id");
$stmt_menage->execute([$date_debut, $date_fin]);
$perf_menage = $stmt_menage->fetchAll();

// 4. Top Chambres les plus occupées sur la période
$stmt_ch_top = $pdo->prepare("SELECT c.numero, tc.nom as type_nom, COUNT(r.id) as nb_sejours, SUM(DATEDIFF(r.date_depart, r.date_arrivee)) as total_nuits
                              FROM chambres c
                              JOIN types_chambres tc ON c.id_type_chambre = tc.id
                              LEFT JOIN reservations r ON c.id = r.id_chambre AND (r.date_arrivee <= ? AND r.date_depart >= ?)
                              GROUP BY c.id
                              ORDER BY total_nuits DESC");
$stmt_ch_top->execute([$date_fin, $date_debut]);
$chambres_stats = $stmt_ch_top->fetchAll();

require_once '../../includes/header.php';
?>

<!-- En-tête d'impression A4 -->
<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Minabou Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Rapport d'Activité, Occupation & Bilan Financier</h2>
    <p>Période : <?= h($period_info['label']) ?> — Édité le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title">Centre de Rapports & Bilan Global</h1>
        <p class="text-secondary" style="margin-top:4px;">Génération et impression de rapports dynamiques par période (Journée, Semaine, Mois, Année, Sur mesure)</p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="performance.php" class="btn btn-primary">📊 Analyse de Rentabilité & Performance</a>
        <button class="btn-print" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimer le Rapport (<?= h($period_info['label']) ?>)
        </button>
    </div>
</div>

<!-- Barre de Filtrage Période Dynamique -->
<div class="card mb-4 no-print" style="border:1px solid var(--border-mid);">
    <div class="card-body p-3">
        <form method="GET" action="" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
            <!-- Raccourcis de sélection rapide -->
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                <span style="font-weight:600; font-size:13px; margin-right:6px;">🕒 Période du Rapport :</span>
                <a href="?period=today" class="btn btn-sm <?= $period_preset==='today'?'btn-primary':'btn-outline' ?>">Aujourd'hui</a>
                <a href="?period=week" class="btn btn-sm <?= $period_preset==='week'?'btn-primary':'btn-outline' ?>">Cette Semaine</a>
                <a href="?period=month" class="btn btn-sm <?= $period_preset==='month'?'btn-primary':'btn-outline' ?>">Ce Mois</a>
                <a href="?period=year" class="btn btn-sm <?= $period_preset==='year'?'btn-primary':'btn-outline' ?>">Cette Année</a>
            </div>

            <!-- Dates sur mesure -->
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="period" value="custom">
                <span style="font-size:12px; color:var(--text-secondary);">Du :</span>
                <input type="date" name="date_debut" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($date_debut) ?>">
                <span style="font-size:12px; color:var(--text-secondary);">Au :</span>
                <input type="date" name="date_fin" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($date_fin) ?>">
                <button type="submit" class="btn btn-sm btn-secondary">Générer Rapport</button>
            </div>
        </form>
    </div>
</div>

<!-- Synthèse KPI de la période -->
<div class="stats-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:16px; margin-bottom:24px;">
    <div class="card p-3 text-center" style="border-left:4px solid var(--gold-500);">
        <span class="text-secondary" style="font-size:13px;">Recettes sur la Période</span>
        <h2 style="margin:8px 0; font-size:24px; color:var(--gold-500);"><?= format_money($bilan_fin['total_net'] ?? 0) ?></h2>
        <span class="text-secondary" style="font-size:12px;"><?= h($period_info['label']) ?></span>
    </div>
    <div class="card p-3 text-center" style="border-left:4px solid var(--s-libre);">
        <span class="text-secondary" style="font-size:13px;">Taux d'Occupation</span>
        <h2 style="margin:8px 0; font-size:24px; color:var(--s-libre);"><?= $taux_occ_periode ?>%</h2>
        <span class="text-secondary" style="font-size:12px;"><?= $nuitées_vendues ?> nuits vendues</span>
    </div>
    <div class="card p-3 text-center" style="border-left:4px solid var(--s-nettoyage);">
        <span class="text-secondary" style="font-size:13px;">Séjours Réalisés</span>
        <h2 style="margin:8px 0; font-size:24px; color:var(--s-nettoyage);"><?= (int)($res_stats['nb_reservations'] ?? 0) ?></h2>
        <span class="text-secondary" style="font-size:12px;">Occupations sur la période</span>
    </div>
    <div class="card p-3 text-center" style="border-left:4px solid var(--s-reservee);">
        <span class="text-secondary" style="font-size:13px;">Total Recettes Historique</span>
        <h2 style="margin:8px 0; font-size:24px; color:var(--s-reservee);"><?= format_money($rev_total_histo) ?></h2>
        <span class="text-secondary" style="font-size:12px;">Depuis le démarrage</span>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">

    <!-- Performance Occupation Chambres -->
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3 class="card-title">Occupation par Chambre</h3>
            <span class="badge badge-libre"><?= h($period_info['label']) ?></span>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr><th>Chambre</th><th>Type</th><th>Séjours</th><th>Nuits Occupées</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($chambres_stats as $cs): ?>
                    <tr>
                        <td><strong>N° <?= h($cs['numero']) ?></strong></td>
                        <td><?= h($cs['type_nom']) ?></td>
                        <td><?= $cs['nb_sejours'] ?></td>
                        <td><strong style="color:var(--gold-500);"><?= $cs['total_nuits'] ?? 0 ?> nuit(s)</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bilan Activité du Personnel de Nettoyage -->
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h3 class="card-title">Bilan Activité du Ménage</h3>
            <span class="badge badge-libre"><?= h($period_info['label']) ?></span>
        </div>
        <div class="card-body p-0">
            <table class="table">
                <thead>
                    <tr><th>Agent de Ménage</th><th>Tâches Effectuées</th><th>Durée Moyenne</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($perf_menage as $pm): ?>
                    <tr>
                        <td><strong><?= h($pm['prenom'].' '.$pm['nom']) ?></strong></td>
                        <td><?= $pm['terminees'] ?> / <?= $pm['total_taches'] ?> réalisées</td>
                        <td><?= $pm['duree_moy'] ? $pm['duree_moy'].' min / chambre' : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($perf_menage)): ?>
                    <tr><td colspan="3" class="text-center p-3 text-secondary">Aucune donnée ménage sur cette période.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<div class="print-footer">
    <p>Minabou Hotel — Rapport officiel de gestion et d'activité. Document imprimé le <?= date('d/m/Y à H:i') ?></p>
</div>

<?php require_once '../../includes/footer.php'; ?>
