<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$page_title = "Recherche Multi-Critères";

$q = post('q', $_GET['q'] ?? '');

$results_clients = [];
$results_chambres = [];
$results_menage = [];
$results_factures = [];

if (strlen($q) >= 1) {
    $search_term = "%$q%";

    // 1. Recherche Clients
    $stmt_c = $pdo->prepare("SELECT * FROM clients 
                             WHERE nom LIKE ? OR prenom LIKE ? OR telephone LIKE ? OR piece_identite LIKE ? 
                             ORDER BY nom LIMIT 10");
    $stmt_c->execute([$search_term, $search_term, $search_term, $search_term]);
    $results_clients = $stmt_c->fetchAll();

    // 2. Recherche Chambres
    $stmt_ch = $pdo->prepare("SELECT c.*, tc.nom as type_nom, tc.prix_nuit FROM chambres c 
                              JOIN types_chambres tc ON c.id_type_chambre = tc.id 
                              WHERE c.numero LIKE ? OR c.description LIKE ? OR tc.nom LIKE ?
                              ORDER BY c.numero LIMIT 10");
    $stmt_ch->execute([$search_term, $search_term, $search_term]);
    $results_chambres = $stmt_ch->fetchAll();

    // 3. Recherche Ménage
    $stmt_m = $pdo->prepare("SELECT tm.*, c.numero as chambre_num, p.nom as agent_nom, p.prenom as agent_prenom
                             FROM taches_menage tm
                             JOIN chambres c ON tm.id_chambre = c.id
                             LEFT JOIN personnel p ON tm.id_agent = p.id
                             WHERE c.numero LIKE ? OR p.nom LIKE ? OR p.prenom LIKE ? OR tm.notes LIKE ?
                             ORDER BY tm.id DESC LIMIT 10");
    $stmt_m->execute([$search_term, $search_term, $search_term, $search_term]);
    $results_menage = $stmt_m->fetchAll();

    // 4. Recherche Factures
    $stmt_f = $pdo->prepare("SELECT f.*, cl.nom as client_nom, cl.prenom as client_prenom 
                             FROM factures f
                             JOIN clients cl ON f.id_client = cl.id
                             WHERE f.id LIKE ? OR cl.nom LIKE ? OR cl.prenom LIKE ? OR f.reference_paiement LIKE ?
                             ORDER BY f.id DESC LIMIT 10");
    $stmt_f->execute([$search_term, $search_term, $search_term, $search_term]);
    $results_factures = $stmt_f->fetchAll();
}

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-header-title">Recherche Multi-Critères</h1>
        <p class="text-secondary" style="margin-top:4px;">Recherche globale unifiée dans tout l'établissement</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-4">
        <form method="GET" style="display:flex; gap:12px;">
            <input type="text" name="q" class="form-control" style="font-size:16px; padding:12px 18px;" placeholder="Tapez un nom de client, numéro de chambre, nom d'agent, numéro de facture..." value="<?= h($q) ?>" autofocus>
            <button type="submit" class="btn btn-primary" style="padding:12px 24px; font-weight:600;">🔍 Rechercher</button>
        </form>
    </div>
</div>

<?php if ($q): ?>
<div style="display:grid; grid-template-columns:1fr; gap:20px;">

    <!-- Résultat Clients -->
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between;">
            <h3 class="card-title">👥 Clients trouvés (<?= count($results_clients) ?>)</h3>
        </div>
        <div class="card-body p-0">
            <?php if ($results_clients): ?>
            <table class="table">
                <thead>
                    <tr><th>Client</th><th>Téléphone</th><th>Email</th><th>Pièce d'Identité</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($results_clients as $cl): ?>
                    <tr>
                        <td><strong><?= h($cl['nom'].' '.$cl['prenom']) ?></strong></td>
                        <td><?= h($cl['telephone'] ?? '—') ?></td>
                        <td><?= h($cl['email'] ?? '—') ?></td>
                        <td><?= h($cl['piece_identite'] ?? '—') ?></td>
                        <td><a href="<?= BASE_URL ?>/modules/clients/edit.php?id=<?= $cl['id'] ?>" class="btn btn-sm btn-secondary">Voir Profil</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="p-3 text-secondary" style="font-style:italic;">Aucun client correspondant.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Résultat Chambres -->
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between;">
            <h3 class="card-title">🏨 Chambres trouvées (<?= count($results_chambres) ?>)</h3>
        </div>
        <div class="card-body p-0">
            <?php if ($results_chambres): ?>
            <table class="table">
                <thead>
                    <tr><th>Chambre</th><th>Type</th><th>Tarif / Nuit</th><th>Statut</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($results_chambres as $ch): ?>
                    <tr>
                        <td><strong>Chambre N° <?= h($ch['numero']) ?></strong></td>
                        <td><?= h($ch['type_nom']) ?></td>
                        <td><?= format_money($ch['prix_nuit']) ?></td>
                        <td><?= status_badge($ch['statut']) ?></td>
                        <td><a href="<?= BASE_URL ?>/modules/chambres/tracabilite.php?numero=<?= h($ch['numero']) ?>" class="btn btn-sm btn-outline">Voir Traçabilité</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="p-3 text-secondary" style="font-style:italic;">Aucune chambre correspondante.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Résultat Ménage -->
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between;">
            <h3 class="card-title">🧹 Entretien & Ménage (<?= count($results_menage) ?>)</h3>
        </div>
        <div class="card-body p-0">
            <?php if ($results_menage): ?>
            <table class="table">
                <thead>
                    <tr><th>Chambre</th><th>Agent d'entretien</th><th>Date prévue</th><th>Statut</th><th>Durée</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($results_menage as $m): ?>
                    <tr>
                        <td><strong>N° <?= h($m['chambre_num']) ?></strong></td>
                        <td><?= h(($m['agent_prenom'] ?? '').' '.($m['agent_nom'] ?? 'Non assigné')) ?></td>
                        <td><?= format_date($m['date_prevue']) ?></td>
                        <td><?= status_badge($m['statut']) ?></td>
                        <td><?= $m['duree_minutes'] ? $m['duree_minutes'].' min' : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="p-3 text-secondary" style="font-style:italic;">Aucun enregistrement ménage correspondant.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Résultat Factures -->
    <div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between;">
            <h3 class="card-title">💳 Factures & Comptabilité (<?= count($results_factures) ?>)</h3>
        </div>
        <div class="card-body p-0">
            <?php if ($results_factures): ?>
            <table class="table">
                <thead>
                    <tr><th>N° Facture</th><th>Client</th><th>Date</th><th>Montant Final</th><th>Paiement</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($results_factures as $f): ?>
                    <tr>
                        <td><strong>#F-<?= str_pad($f['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                        <td><?= h($f['client_nom'].' '.$f['client_prenom']) ?></td>
                        <td><?= format_date($f['date_emission']) ?></td>
                        <td><strong><?= format_money($f['montant_final']) ?></strong></td>
                        <td><?= status_badge($f['statut_paiement']) ?></td>
                        <td><a href="<?= BASE_URL ?>/modules/facturation/print.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-secondary" target="_blank">Imprimer Facture</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="p-3 text-secondary" style="font-style:italic;">Aucune facture correspondante.</div>
            <?php endif; ?>
        </div>
    </div>

</div>
<?php endif; ?>

<?php require_once '../../includes/footer.php'; ?>
