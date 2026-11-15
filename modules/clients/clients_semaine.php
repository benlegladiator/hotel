<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$page_title = "Liste des Clients de la Semaine";

// Période de la semaine en cours (Lundi au Dimanche)
$lundi = date('Y-m-d', strtotime('monday this week'));
$dimanche = date('Y-m-d', strtotime('sunday this week'));

$date_debut = post('date_debut', $_GET['date_debut'] ?? $lundi);
$date_fin   = post('date_fin', $_GET['date_fin'] ?? $dimanche);

$sql = "SELECT DISTINCT cl.*, r.id as res_id, r.date_arrivee, r.date_depart, r.statut as res_statut,
        c.numero as chambre_num, tc.nom as type_chambre
        FROM clients cl
        JOIN reservations r ON cl.id = r.id_client
        LEFT JOIN chambres c ON r.id_chambre = c.id
        LEFT JOIN types_chambres tc ON c.id_type_chambre = tc.id
        WHERE (r.date_arrivee <= ? AND r.date_depart >= ?)
        ORDER BY r.date_arrivee DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$date_fin, $date_debut]);
$clients_semaine = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<!-- En-tête d'impression A4 -->
<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Minabou Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Registre Hebdomadaire des Clients & Occupants</h2>
    <p>Période du <?= format_date($date_debut) ?> au <?= format_date($date_fin) ?> — Éditée le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title">Clients de la Semaine</h1>
        <p class="text-secondary" style="margin-top:4px;">Suivi et registre hebdomadaire de la clientèle de l'hôtel</p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn-print" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimer la Liste de la Semaine
        </button>
        <a href="list.php" class="btn btn-outline">Tous les clients</a>
    </div>
</div>

<!-- Filtre Période -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" style="display:flex; gap:15px; flex-wrap:wrap; align-items:flex-end;">
            <div style="flex:1; min-width:200px;">
                <label class="form-label">Début de semaine</label>
                <input type="date" name="date_debut" class="form-control" value="<?= h($date_debut) ?>">
            </div>
            <div style="flex:1; min-width:200px;">
                <label class="form-label">Fin de semaine</label>
                <input type="date" name="date_fin" class="form-control" value="<?= h($date_fin) ?>">
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Afficher la Période</button>
                <a href="clients_semaine.php" class="btn btn-outline" style="margin-left:5px;">Cette Semaine</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title">Registre des Clients Hébergés (<?= count($clients_semaine) ?> occupants)</h3>
        <span class="badge badge-libre">Période du <?= format_date($date_debut) ?> au <?= format_date($date_fin) ?></span>
    </div>
    <div class="card-body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Nom & Prénom</th>
                    <th>Téléphone</th>
                    <th>Nationalité / Pièce</th>
                    <th>Chambre Occupée</th>
                    <th>Période du Séjour</th>
                    <th>Statut</th>
                    <th class="no-print">Fiche Client</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients_semaine)): ?>
                    <tr>
                        <td colspan="8" class="text-center p-4 text-secondary">Aucun client enregistré pour cette période de la semaine.</td>
                    </tr>
                <?php else: ?>
                    <?php $i=1; foreach ($clients_semaine as $c): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td>
                                <strong><?= h($c['nom'] . ' ' . $c['prenom']) ?></strong>
                                <?php if (!empty($c['date_naissance']) || !empty($c['lieu_naissance'])): ?>
                                    <br><small class="text-secondary">🎂 <?= !empty($c['date_naissance']) ? format_date($c['date_naissance']) : '' ?><?= !empty($c['lieu_naissance']) ? ' à ' . h($c['lieu_naissance']) : '' ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= h($c['telephone'] ?? '—') ?>
                                <?php if (!empty($c['adresse'])): ?>
                                    <br><small class="text-secondary">📍 <?= h($c['adresse']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= h($c['nationalite'] ?? 'Non renseignée') ?><br>
                                <small class="text-secondary"><?= h($c['piece_identite'] ?? '') ?></small>
                            </td>
                            <td>
                                <strong>Chambre <?= h($c['chambre_num'] ?? 'N/A') ?></strong><br>
                                <small class="text-secondary"><?= h($c['type_chambre'] ?? '') ?></small>
                            </td>
                            <td>
                                Du <?= format_date($c['date_arrivee']) ?><br>
                                au <?= format_date($c['date_depart']) ?> (<?= nb_nuits($c['date_arrivee'], $c['date_depart']) ?> nuits)
                            </td>
                            <td><?= status_badge($c['res_statut']) ?></td>
                            <td class="no-print">
                                <a href="edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-secondary">Voir Fiche</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="print-footer">
    <p>Minabou Hotel — Registre de police et suivi d'occupation hebdomadaire. Imprimé le <?= date('d/m/Y à H:i') ?></p>
</div>

<?php require_once '../../includes/footer.php'; ?>
