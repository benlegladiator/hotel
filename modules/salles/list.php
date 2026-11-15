<?php
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

$search = $_GET['q'] ?? '';
$query = "SELECT * FROM salles";
$params = [];

if ($search) {
    $query .= " WHERE nom LIKE :search OR description LIKE :search";
    $params['search'] = "%$search%";
}

$query .= " ORDER BY nom ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$salles = $stmt->fetchAll();

$page_title = 'Salles';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Salles de Conférence / Réunion</h1>
    <div class="page-header-actions">
        <a href="add.php" class="btn btn-primary">+ Ajouter</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="table-toolbar">
            <form action="" method="GET" class="search-box">
                <input type="text" name="q" class="form-control" placeholder="Rechercher..." value="<?= h($search) ?>">
                <button type="submit" class="btn btn-secondary">Filtrer</button>
            </form>
        </div>
    </div>
    
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Capacité</th>
                    <th>Prix / Location</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($salles)): ?>
                    <tr>
                        <td colspan="5" class="empty-state">Aucune salle trouvée.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($salles as $salle): ?>
                        <tr>
                            <td><strong><?= h($salle['nom']) ?></strong></td>
                            <td><?= h($salle['capacite']) ?> pers.</td>
                            <td><?= format_money($salle['prix_location']) ?></td>
                            <td><?= status_badge($salle['statut']) ?></td>
                            <td>
                                <a href="edit.php?id=<?= $salle['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
                                <a href="process_delete.php?id=<?= $salle['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Confirmer la suppression ?">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
