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

$stmt = $pdo->prepare("SELECT * FROM menus_cuisine WHERE id = ?");
$stmt->execute([$id]);
$menu = $stmt->fetch();

if (!$menu) {
    redirect('list.php');
}

// Récupérer les chefs
$stmt = $pdo->query("SELECT id, nom, prenom FROM personnel WHERE role = 'cuisinier' AND actif = 1 ORDER BY nom");
$chefs = $stmt->fetchAll();

// Récupérer les plats (nourriture)
$stmt = $pdo->query("SELECT id, nom, prix FROM catalogue_services WHERE categorie = 'nourriture' AND disponible = 1 ORDER BY nom");
$plats = $stmt->fetchAll();

// Récupérer les plats sélectionnés pour ce menu
$stmt = $pdo->prepare("SELECT id_service FROM menu_items WHERE id_menu = ?");
$stmt->execute([$id]);
$selected_plats_rows = $stmt->fetchAll();
$selected_plats = array_column($selected_plats_rows, 'id_service');

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-header-title">Modifier le menu</h2>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">Retour aux menus</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_edit.php" method="POST">
            <input type="hidden" name="id" value="<?= $menu['id'] ?>">
            
            <div class="form-group">
                <label class="form-label" for="titre">Titre du menu *</label>
                <input type="text" id="titre" name="titre" class="form-control" value="<?= h($menu['titre']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="id_chef">Chef responsable *</label>
                <select id="id_chef" name="id_chef" class="form-select" required>
                    <option value="">Sélectionner un chef...</option>
                    <?php foreach ($chefs as $chef): ?>
                        <option value="<?= $chef['id'] ?>" <?= $menu['id_chef'] == $chef['id'] ? 'selected' : '' ?>><?= h($chef['prenom'] . ' ' . $chef['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="date_publication">Date de publication</label>
                <input type="date" id="date_publication" name="date_publication" class="form-control" value="<?= $menu['date_publication'] ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="description">Description (Optionnel)</label>
                <textarea id="description" name="description" class="form-textarea" rows="3"><?= h($menu['description']) ?></textarea>
            </div>
            
            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="disponible" name="disponible" value="1" <?= $menu['disponible'] ? 'checked' : '' ?>>
                <label class="form-label mb-0" for="disponible">Disponible</label>
            </div>
            
            <h4 class="mt-4 mb-3">Sélection des plats (Catalogue)</h4>
            <div class="form-group" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; border-radius: 5px;">
                <?php foreach ($plats as $plat): ?>
                    <div style="margin-bottom: 8px;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="service_id[]" value="<?= $plat['id'] ?>" <?= in_array($plat['id'], $selected_plats) ? 'checked' : '' ?>>
                            <span><?= h($plat['nom']) ?> (<?= format_money($plat['prix']) ?>)</span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary">Mettre à jour le menu</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
