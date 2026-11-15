<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('list.php');
}

$stmt = $pdo->prepare("
    SELECT c.*, cl.nom as client_nom, cl.prenom as client_prenom, ch.numero as chambre_numero
    FROM commandes c
    LEFT JOIN clients cl ON c.id_client = cl.id
    LEFT JOIN reservations r ON c.id_reservation = r.id
    LEFT JOIN chambres ch ON r.id_chambre = ch.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$commande = $stmt->fetch();

if (!$commande) {
    redirect('list.php');
}

$stmt = $pdo->prepare("
    SELECT cl.*, cs.nom as service_nom
    FROM commande_lignes cl
    JOIN catalogue_services cs ON cl.id_service = cs.id
    WHERE cl.id_commande = ?
");
$stmt->execute([$id]);
$lignes = $stmt->fetchAll();

$total = 0;
foreach ($lignes as $ligne) {
    $total += $ligne['sous_total'];
}

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-header-title">Détails de la Commande #<?= str_pad($commande['id'], 4, '0', STR_PAD_LEFT) ?></h2>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">Retour aux commandes</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h3 class="card-title">Informations</h3>
        <p><strong>Client :</strong> <?= h($commande['client_prenom'] . ' ' . $commande['client_nom']) ?></p>
        <p><strong>Réservation :</strong> <?= $commande['chambre_numero'] ? 'Chambre ' . h($commande['chambre_numero']) : 'Aucune' ?></p>
        <p><strong>Date :</strong> <?= format_date($commande['created_at']) ?></p>
        <p><strong>Total :</strong> <?= format_money($total) ?></p>
        
        <form action="process_edit.php" method="POST" class="mt-4">
            <input type="hidden" name="id" value="<?= $commande['id'] ?>">
            
            <div class="form-group">
                <label class="form-label" for="statut">Statut</label>
                <select id="statut" name="statut" class="form-select">
                    <option value="en_cours" <?= $commande['statut'] == 'en_cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="servie" <?= $commande['statut'] == 'servie' ? 'selected' : '' ?>>Servie</option>
                    <option value="annulee" <?= $commande['statut'] == 'annulee' ? 'selected' : '' ?>>Annulée</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-textarea" rows="2"><?= h($commande['notes']) ?></textarea>
            </div>
            
            <div class="form-actions mt-3">
                <button type="submit" class="btn btn-primary">Mettre à jour la commande</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h3 class="card-title">Articles</h3>
        <?php if (empty($lignes)): ?>
            <p>Aucun article.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Prix U.</th>
                        <th>Qté</th>
                        <th>Sous-total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lignes as $ligne): ?>
                        <tr>
                            <td><?= h($ligne['service_nom']) ?></td>
                            <td><?= format_money($ligne['prix_unitaire']) ?></td>
                            <td><?= $ligne['quantite'] ?></td>
                            <td><strong><?= format_money($ligne['sous_total']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" style="text-align: right;">Total :</th>
                        <th><?= format_money($total) ?></th>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
