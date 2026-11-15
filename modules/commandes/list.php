<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$statut = $_GET['statut'] ?? '';

$sql = "SELECT c.*, cl.nom as client_nom, cl.prenom as client_prenom, 
        r.id_chambre, ch.numero as chambre_numero,
        (SELECT COUNT(*) FROM commande_lignes WHERE id_commande = c.id) as nb_articles,
        (SELECT SUM(sous_total) FROM commande_lignes WHERE id_commande = c.id) as montant_total
        FROM commandes c
        LEFT JOIN clients cl ON c.id_client = cl.id
        LEFT JOIN reservations r ON c.id_reservation = r.id
        LEFT JOIN chambres ch ON r.id_chambre = ch.id
        WHERE 1=1";
$params = [];

if ($statut) {
    $sql .= " AND c.statut = ?";
    $params[] = $statut;
}

$sql .= " ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$commandes = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-header-title">Commandes & Room Service</h2>
    <div class="page-header-actions">
        <a href="add.php" class="btn btn-primary">+ Nouvelle commande</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-4" style="display: flex; gap: 10px;">
            <div class="form-group mb-0" style="flex: 1;">
                <select name="statut" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="en_cours" <?= $statut == 'en_cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="servie" <?= $statut == 'servie' ? 'selected' : '' ?>>Servie</option>
                    <option value="annulee" <?= $statut == 'annulee' ? 'selected' : '' ?>>Annulée</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrer</button>
        </form>

        <?php if (empty($commandes)): ?>
            <div class="empty-state">
                <p>Aucune commande trouvée.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Réservation</th>
                            <th>Date</th>
                            <th>Articles</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $commande): ?>
                            <tr>
                                <td>#<?= str_pad($commande['id'], 4, '0', STR_PAD_LEFT) ?></td>
                                <td><?= h($commande['client_prenom'] . ' ' . $commande['client_nom']) ?></td>
                                <td><?= $commande['chambre_numero'] ? 'Chambre ' . h($commande['chambre_numero']) : '—' ?></td>
                                <td><?= format_date($commande['created_at']) ?></td>
                                <td><?= (int)$commande['nb_articles'] ?></td>
                                <td><?= format_money((float)$commande['montant_total']) ?></td>
                                <td><?= status_badge($commande['statut']) ?></td>
                                <td>
                                    <a href="edit.php?id=<?= $commande['id'] ?>" class="btn btn-sm btn-secondary">Voir / Modifier</a>
                                    <?php if ($commande['statut'] != 'annulee'): ?>
                                        <a href="process_delete.php?id=<?= $commande['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Annuler cette commande ?');">Annuler</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
