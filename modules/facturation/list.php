<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$statut_filter = $_GET['statut'] ?? '';
$search = $_GET['q'] ?? '';

$sql = "SELECT f.*, c.nom as client_nom, c.prenom as client_prenom 
        FROM factures f 
        JOIN clients c ON f.id_client = c.id 
        WHERE 1=1";
$params = [];

if ($statut_filter) {
    $sql .= " AND f.statut_paiement = ?";
    $params[] = $statut_filter;
}
if ($search) {
    $sql .= " AND (c.nom LIKE ? OR c.prenom LIKE ? OR f.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY f.date_emission DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$factures = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
    <div>
        <h1 class="page-header-title">Facturation & Règlements</h1>
        <p class="text-secondary" style="margin-top:4px;">Historique et gestion des factures clients</p>
    </div>
    <div class="page-header-actions" style="display:flex; gap:10px;">
        <a href="create.php" class="btn btn-primary">+ Nouvelle facture</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">← Retour Dashboard</a>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2 class="card-title">Liste des factures</h2>
        <form method="get" class="form-inline" style="display:flex; gap:10px;">
            <input type="text" name="q" value="<?= h($search) ?>" class="form-control" placeholder="Rechercher...">
            <select name="statut" class="form-select" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                <option value="payee" <?= $statut_filter === 'payee' ? 'selected' : '' ?>>Payée</option>
                <option value="partielle" <?= $statut_filter === 'partielle' ? 'selected' : '' ?>>Partielle</option>
                <option value="impayee" <?= $statut_filter === 'impayee' ? 'selected' : '' ?>>Impayée</option>
            </select>
            <button type="submit" class="btn btn-secondary">Filtrer</button>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>N° Facture</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th>Séjour</th>
                        <th>Services</th>
                        <th>Total</th>
                        <th>Réduction</th>
                        <th>Montant final</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($factures)): ?>
                        <tr>
                            <td colspan="10" class="text-center empty-state">Aucune facture trouvée.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($factures as $f): ?>
                            <tr>
                                <td>#F-<?= str_pad($f['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td><?= h($f['client_nom'] . ' ' . $f['client_prenom']) ?></td>
                                <td><?= format_date($f['date_emission']) ?></td>
                                <td><?= format_money($f['montant_sejour']) ?></td>
                                <td><?= format_money($f['montant_services']) ?></td>
                                <td><?= format_money($f['montant_total']) ?></td>
                                <td><?= h($f['reduction_pct']) ?>%</td>
                                <td><strong><?= format_money($f['montant_final']) ?></strong></td>
                                <td><?= status_badge($f['statut_paiement']) ?></td>
                                <td>
                                    <a href="print.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-dark" target="_blank">Imprimer</a>
                                    <a href="edit.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-primary">Modifier</a>
                                    <a href="process_delete.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette facture ?')">Supprimer</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
