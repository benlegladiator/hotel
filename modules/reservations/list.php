<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

// Module Réservations désactivé temporairement selon la consigne
set_flash('info', "Le module Réservations est temporairement désactivé. Enregistrez directement les occupants via l'Arrivée Express.");
redirect(BASE_URL . '/modules/checkin_checkout/express_checkin.php');
$period_preset = $_GET['period'] ?? 'all';
$custom_debut  = $_GET['date_debut'] ?? '';
$custom_fin    = $_GET['date_fin'] ?? '';

if ($period_preset !== 'all') {
    $period_info = get_period_dates($period_preset, $custom_debut, $custom_fin);
} else {
    $period_info = ['preset' => 'all', 'label' => 'Toutes les réservations historiques', 'debut' => '', 'fin' => ''];
}

$statut_filter = $_GET['statut'] ?? '';

$sql = "SELECT r.*, c.nom as client_nom, c.prenom as client_prenom, c.telephone as client_tel, ch.numero as chambre_numero, s.nom as salle_nom 
        FROM reservations r 
        JOIN clients c ON r.id_client = c.id 
        LEFT JOIN chambres ch ON r.id_chambre = ch.id 
        LEFT JOIN salles s ON r.id_salle = s.id
        WHERE 1=1";

$params = [];

if ($statut_filter) {
    $sql .= " AND r.statut = ?";
    $params[] = $statut_filter;
}

if ($period_preset !== 'all' && $period_info['debut'] && $period_info['fin']) {
    $sql .= " AND (r.date_arrivee <= ? AND r.date_depart >= ?)";
    $params[] = $period_info['fin'];
    $params[] = $period_info['debut'];
}

$sql .= " ORDER BY r.date_arrivee DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll();

$page_title = 'Gestion & Impression des Réservations';
require_once '../../includes/header.php';
?>

<!-- En-tête d'impression A4 -->
<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Alma Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Registre des Réservations de l'Établissement</h2>
    <p>Période : <?= h($period_info['label']) ?> — Éditée le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title">Réservations</h1>
        <p class="text-secondary" style="margin-top:4px;">Gestion, filtrage par période et impression A4 des confirmations de réservation</p>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <button class="btn-print" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimer les Réservations (<?= h($period_info['label']) ?>)
        </button>
        <a href="add.php" class="btn btn-primary">+ Nouvelle Réservation</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">← Retour Dashboard</a>
    </div>
</div>

<!-- Barre de Filtrage Période Dynamique -->
<div class="card mb-4 no-print" style="border:1px solid var(--border-mid);">
    <div class="card-body p-3">
        <form method="GET" action="" style="display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between;">
            <!-- Raccourcis de sélection rapide -->
            <div style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                <span style="font-weight:600; font-size:13px; margin-right:6px;">🕒 Période :</span>
                <a href="?period=all" class="btn btn-sm <?= $period_preset==='all'?'btn-primary':'btn-outline' ?>">Toutes</a>
                <a href="?period=today" class="btn btn-sm <?= $period_preset==='today'?'btn-primary':'btn-outline' ?>">Aujourd'hui</a>
                <a href="?period=week" class="btn btn-sm <?= $period_preset==='week'?'btn-primary':'btn-outline' ?>">Cette Semaine</a>
                <a href="?period=month" class="btn btn-sm <?= $period_preset==='month'?'btn-primary':'btn-outline' ?>">Ce Mois</a>
                <a href="?period=year" class="btn btn-sm <?= $period_preset==='year'?'btn-primary':'btn-outline' ?>">Cette Année</a>
            </div>

            <!-- Statut & Dates sur mesure -->
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <select name="statut" class="form-select" style="width:160px; padding:4px 8px; font-size:13px;">
                    <option value="">Tous les statuts</option>
                    <option value="confirmee" <?= $statut_filter === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                    <option value="en_cours" <?= $statut_filter === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="terminee" <?= $statut_filter === 'terminee' ? 'selected' : '' ?>>Terminée</option>
                    <option value="annulee" <?= $statut_filter === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                </select>
                <input type="hidden" name="period" value="custom">
                <input type="date" name="date_debut" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($custom_debut) ?>">
                <input type="date" name="date_fin" class="form-control" style="width:140px; padding:4px 8px; font-size:13px;" value="<?= h($custom_fin) ?>">
                <button type="submit" class="btn btn-sm btn-secondary">Filtrer</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title">Liste des Réservations (<?= count($reservations) ?> enregistrées)</h3>
        <span class="badge badge-libre"><?= h($period_info['label']) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Client & Téléphone</th>
                        <th>Chambre</th>
                        <th>Date Arrivée</th>
                        <th>Date Départ</th>
                        <th>Durée</th>
                        <th>Statut</th>
                        <th class="no-print">Actions & Impression</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $res): ?>
                    <tr>
                        <td><strong>#RES-<?= str_pad($res['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                        <td>
                            <strong><?= h($res['client_nom'] . ' ' . $res['client_prenom']) ?></strong><br>
                            <small class="text-secondary"><?= h($res['client_tel'] ?? '') ?></small>
                        </td>
                        <td>
                            <strong>Chambre N° <?= h($res['chambre_numero'] ?? 'N/A') ?></strong>
                        </td>
                        <td><?= format_date($res['date_arrivee']) ?></td>
                        <td><?= format_date($res['date_depart']) ?></td>
                        <td><?= nb_nuits($res['date_arrivee'], $res['date_depart']) ?> nuit(s)</td>
                        <td><?= status_badge($res['statut']) ?></td>
                        <td class="no-print" style="display:flex; gap:4px; flex-wrap:wrap;">
                            <a href="print.php?id=<?= $res['id'] ?>" class="btn btn-sm btn-outline" target="_blank" title="Imprimer la Fiche de Réservation">📄 Imprimer</a>
                            <a href="edit.php?id=<?= $res['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
                            <?php if($res['statut'] === 'confirmee'): ?>
                            <a href="<?= BASE_URL ?>/modules/checkin_checkout/checkin.php?id=<?= $res['id'] ?>" class="btn btn-sm btn-success">📥 Check-in</a>
                            <?php endif; ?>
                            <?php if($res['statut'] !== 'annulee'): ?>
                            <a href="process_delete.php?id=<?= $res['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Annuler la réservation ?');">Annuler</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reservations)): ?>
                    <tr>
                        <td colspan="8" class="text-center p-4 text-secondary">Aucune réservation trouvée pour cette sélection.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="print-footer">
    <p>Minabou Hotel — Registre officiel des réservations. Imprimé le <?= date('d/m/Y à H:i') ?></p>
</div>

<?php require_once '../../includes/footer.php'; ?>
