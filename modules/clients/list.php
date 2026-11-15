<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$page_title = "Registre & Liste des Clients";

// Traitement de la période dynamique
$period_preset = $_GET['period'] ?? 'all';
$custom_debut  = $_GET['date_debut'] ?? '';
$custom_fin    = $_GET['date_fin'] ?? '';

$search = $_GET['q'] ?? '';

if ($period_preset !== 'all') {
    $period_info = get_period_dates($period_preset, $custom_debut, $custom_fin);
} else {
    $period_info = ['preset' => 'all', 'label' => 'Tous les séjours historiques', 'debut' => '', 'fin' => ''];
}

$sql = "SELECT DISTINCT cl.*, r.id as res_id, r.date_arrivee, r.date_depart, r.statut as res_statut,
        c.numero as chambre_num, tc.nom as type_chambre
        FROM clients cl
        LEFT JOIN reservations r ON cl.id = r.id_client
        LEFT JOIN chambres c ON r.id_chambre = c.id
        LEFT JOIN types_chambres tc ON c.id_type_chambre = tc.id
        WHERE 1=1";

$params = [];

if ($search) {
    $sql .= " AND (cl.nom LIKE ? OR cl.prenom LIKE ? OR cl.telephone LIKE ? OR cl.piece_identite LIKE ? OR cl.lieu_naissance LIKE ? OR cl.adresse LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($period_preset !== 'all' && $period_info['debut'] && $period_info['fin']) {
    $sql .= " AND (r.date_arrivee <= ? AND r.date_depart >= ?)";
    $params[] = $period_info['fin'];
    $params[] = $period_info['debut'];
}

$sql .= " ORDER BY cl.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<!-- En-tête d'impression A4 -->
<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Registre des Clients & Occupants de l'Établissement</h2>
    <p>Période : <?= h($period_info['label']) ?> — Éditée le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title">Registre des Clients</h1>
        <p class="text-secondary" style="margin-top:4px;">Affichage dynamique par période (Journée, Semaine, Mois, Année, Sur mesure)</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn-print" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimer le Registre (<?= h($period_info['label']) ?>)
        </button>
        <a href="add.php" class="btn btn-primary">+ Nouveau Client</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">← Retour Dashboard</a>
    </div>
</div>

<!-- Barre de Filtrage Période Dynamique -->
<div class="card mb-4 no-print" style="border:1px solid var(--border-mid);">
    <div class="card-body p-3">
        <form method="GET" action="" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
            <!-- Raccourcis de sélection rapide -->
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                <span style="font-weight:600; font-size:13px; margin-right:6px;">🕒 Filtrer par Période :</span>
                <a href="?period=all" class="btn btn-sm <?= $period_preset==='all'?'btn-primary':'btn-outline' ?>">Tous</a>
                <a href="?period=today" class="btn btn-sm <?= $period_preset==='today'?'btn-primary':'btn-outline' ?>">Aujourd'hui</a>
                <a href="?period=week" class="btn btn-sm <?= $period_preset==='week'?'btn-primary':'btn-outline' ?>">Cette Semaine</a>
                <a href="?period=month" class="btn btn-sm <?= $period_preset==='month'?'btn-primary':'btn-outline' ?>">Ce Mois</a>
                <a href="?period=year" class="btn btn-sm <?= $period_preset==='year'?'btn-primary':'btn-outline' ?>">Cette Année</a>
            </div>

            <!-- Dates sur mesure & Recherche textuelle -->
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="text" name="q" class="form-control" style="width:180px; padding:4px 8px; font-size:13px;" placeholder="Recherche nom..." value="<?= h($search) ?>">
                <input type="hidden" name="period" value="custom">
                <input type="date" name="date_debut" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($custom_debut) ?>">
                <input type="date" name="date_fin" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($custom_fin) ?>">
                <button type="submit" class="btn btn-sm btn-secondary">Appliquer</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title">Liste des Clients (<?= count($clients) ?> résultats)</h3>
        <span class="badge badge-libre"><?= h($period_info['label']) ?></span>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Nom & Prénom</th>
                    <th>Date & Lieu Naiss.</th>
                    <th>Téléphone / Contact</th>
                    <th>Adresse / Ville</th>
                    <th>Nationalité / Pièce</th>
                    <th>Dernière Chambre</th>
                    <th>Période du Séjour</th>
                    <th>Statut</th>
                    <th class="no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="10" class="text-center p-4 text-secondary">Aucun client trouvé pour cette période ou ce critère de recherche.</td>
                    </tr>
                <?php else: ?>
                    <?php $i=1; foreach ($clients as $c): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= h($c['nom'] . ' ' . $c['prenom']) ?></strong></td>
                            <td>
                                <?php if (!empty($c['date_naissance']) || !empty($c['lieu_naissance'])): ?>
                                    <?= !empty($c['date_naissance']) ? format_date($c['date_naissance']) : '—' ?><br>
                                    <small class="text-secondary"><?= !empty($c['lieu_naissance']) ? 'à ' . h($c['lieu_naissance']) : '' ?></small>
                                <?php else: ?>
                                    <span class="text-secondary">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= h($c['telephone'] ?? '—') ?><br>
                                <small class="text-secondary"><?= h($c['email'] ?? '') ?></small>
                            </td>
                            <td>
                                <?= !empty($c['adresse']) ? h($c['adresse']) : '<span class="text-secondary">—</span>' ?>
                            </td>
                            <td>
                                <?= h($c['nationalite'] ?? '—') ?><br>
                                <small class="text-secondary"><?= h($c['piece_identite'] ?? '') ?></small>
                            </td>
                            <td>
                                <?php if ($c['chambre_num']): ?>
                                    <strong>Chambre <?= h($c['chambre_num']) ?></strong> (<?= h($c['type_chambre']) ?>)
                                <?php else: ?>
                                    <span class="text-secondary">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['date_arrivee']): ?>
                                    Du <?= format_date($c['date_arrivee']) ?> au <?= format_date($c['date_depart']) ?>
                                <?php else: ?>
                                    <span class="text-secondary">Aucun séjour</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $c['res_statut'] ? status_badge($c['res_statut']) : '<span class="badge badge-maintenance">Inactif</span>' ?></td>
                            <td class="no-print" style="display:flex; gap:4px; flex-wrap:wrap;">
                                <a href="history.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline" title="Voir l'historique complet des séjours">📜 Historique</a>
                                <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">Fiche</a>
                                <a href="process_delete.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce client ?')">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="print-footer">
    <p>Minabou Hotel — Registre de police des occupants. Imprimé le <?= date('d/m/Y à H:i') ?></p>
</div>

<?php require_once '../../includes/footer.php'; ?>
