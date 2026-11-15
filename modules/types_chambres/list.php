<?php
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

$search = $_GET['q'] ?? '';
$query = "SELECT tc.*, (SELECT COUNT(*) FROM chambres c WHERE c.id_type_chambre = tc.id) as nb_chambres 
          FROM types_chambres tc";
$params = [];

if ($search) {
    $query .= " WHERE tc.nom LIKE :search OR tc.description LIKE :search";
    $params['search'] = "%$search%";
}

$query .= " ORDER BY tc.nom ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$types = $stmt->fetchAll();

$page_title = 'Types de Chambres';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
    <div>
        <h1 class="page-header-title">Catégories & Tarifs des Chambres</h1>
        <p class="text-secondary" style="margin-top:4px;">Configuration des types et prix par nuitée</p>
    </div>
    <div class="page-header-actions" style="display:flex; gap:10px;">
        <a href="add.php" class="btn btn-primary">+ Ajouter un Type</a>
        <a href="<?= BASE_URL ?>/modules/chambres/list.php" class="btn btn-secondary">← Retour Chambres</a>
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
                    <th>Prix / Nuit</th>
                    <th>Capacité</th>
                    <th>Avantages</th>
                    <th>Chambres</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($types)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">Aucun type de chambre trouvé.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($types as $type): ?>
                        <tr>
                            <td><?= h($type['nom']) ?></td>
                            <td><?= format_money($type['prix_nuit']) ?></td>
                            <td><?= h($type['capacite']) ?> pers.</td>
                            <td><?= h(mb_strimwidth($type['avantages'], 0, 50, '...')) ?></td>
                            <td><span class="badge"><?= $type['nb_chambres'] ?></span></td>
                            <td>
                                <a href="edit.php?id=<?= $type['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
                                <a href="process_delete.php?id=<?= $type['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Confirmer la suppression ?">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
