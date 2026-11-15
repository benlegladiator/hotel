<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$search = $_GET['search'] ?? '';
$categorie = $_GET['categorie'] ?? '';

$sql = "SELECT * FROM catalogue_services WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND nom LIKE ?";
    $params[] = "%$search%";
}

if ($categorie) {
    $sql .= " AND categorie = ?";
    $params[] = $categorie;
}

$sql .= " ORDER BY nom ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-header-title">Catalogue des Services</h2>
    <div class="page-header-actions">
        <?php if (is_admin()): ?>
            <a href="add.php" class="btn btn-primary">+ Ajouter un service</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-4" style="display: flex; gap: 10px;">
            <div class="form-group mb-0" style="flex: 1;">
                <input type="text" name="search" class="form-control" placeholder="Rechercher un service..." value="<?= h($search) ?>">
            </div>
            <div class="form-group mb-0" style="flex: 1;">
                <select name="categorie" class="form-select">
                    <option value="">Toutes les catégories</option>
                    <option value="boisson" <?= $categorie == 'boisson' ? 'selected' : '' ?>>Boisson</option>
                    <option value="nourriture" <?= $categorie == 'nourriture' ? 'selected' : '' ?>>Nourriture</option>
                    <option value="spa" <?= $categorie == 'spa' ? 'selected' : '' ?>>Spa</option>
                    <option value="piscine" <?= $categorie == 'piscine' ? 'selected' : '' ?>>Piscine</option>
                    <option value="voiture" <?= $categorie == 'voiture' ? 'selected' : '' ?>>Voiture</option>
                    <option value="autre" <?= $categorie == 'autre' ? 'selected' : '' ?>>Autre</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrer</button>
        </form>

        <?php if (empty($services)): ?>
            <div class="empty-state">
                <p>Aucun service trouvé.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Disponible</th>
                            <?php if (is_admin()): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><?= h($service['nom']) ?></td>
                                <td>
                                    <?php
                                    $badgeClass = 'badge-secondary';
                                    switch ($service['categorie']) {
                                        case 'boisson': $badgeClass = 'badge-blue'; break;
                                        case 'nourriture': $badgeClass = 'badge-green'; break;
                                        case 'spa': $badgeClass = 'badge-purple'; break;
                                        case 'piscine': $badgeClass = 'badge-blue'; break;
                                        case 'voiture': $badgeClass = 'badge-amber'; break;
                                        case 'autre': $badgeClass = 'badge-gray'; break;
                                    }
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= h(ucfirst($service['categorie'])) ?></span>
                                </td>
                                <td><?= format_money($service['prix']) ?></td>
                                <td>
                                    <?php if ($service['disponible']): ?>
                                        <span class="badge badge-success">Oui</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Non</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (is_admin()): ?>
                                    <td>
                                        <a href="edit.php?id=<?= $service['id'] ?>" class="btn btn-sm btn-secondary">Modifier</a>
                                        <a href="process_delete.php?id=<?= $service['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce service ?');">Supprimer</a>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
