<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$sql = "
    SELECT m.*, p.nom as chef_nom, p.prenom as chef_prenom,
    (SELECT COUNT(*) FROM menu_items WHERE id_menu = m.id) as nb_plats
    FROM menus_cuisine m
    LEFT JOIN personnel p ON m.id_chef = p.id
    ORDER BY m.date_publication DESC, m.created_at DESC
";
$stmt = $pdo->query($sql);
$menus = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-header-title">Menus de Cuisine</h2>
    <div class="page-header-actions">
        <a href="add.php" class="btn btn-primary">+ Publier un menu</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($menus)): ?>
            <div class="empty-state">
                <p>Aucun menu publié.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Chef</th>
                            <th>Date de publication</th>
                            <th>Plats</th>
                            <th>Disponible</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menus as $menu): ?>
                            <tr>
                                <td><strong><?= h($menu['titre']) ?></strong></td>
                                <td><?= h($menu['chef_prenom'] . ' ' . $menu['chef_nom']) ?></td>
                                <td><?= format_date($menu['date_publication']) ?></td>
                                <td><?= (int)$menu['nb_plats'] ?></td>
                                <td>
                                    <?php if ($menu['disponible']): ?>
                                        <span class="badge badge-success">Oui</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Non</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?= $menu['id'] ?>" class="btn btn-sm btn-secondary">Modifier</a>
                                    <?php if ($menu['disponible']): ?>
                                        <a href="process_delete.php?id=<?= $menu['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Retirer ce menu ?');">Retirer</a>
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
