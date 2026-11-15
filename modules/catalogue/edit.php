<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_admin();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect('list.php');
}

$stmt = $pdo->prepare("SELECT * FROM catalogue_services WHERE id = ?");
$stmt->execute([$id]);
$service = $stmt->fetch();

if (!$service) {
    redirect('list.php');
}

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-header-title">Modifier le service</h2>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">Retour au catalogue</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_edit.php" method="POST">
            <input type="hidden" name="id" value="<?= h($service['id']) ?>">
            
            <div class="form-group">
                <label class="form-label" for="nom">Nom *</label>
                <input type="text" id="nom" name="nom" class="form-control" value="<?= h($service['nom']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="categorie">Catégorie *</label>
                <select id="categorie" name="categorie" class="form-select" required>
                    <option value="boisson" <?= $service['categorie'] == 'boisson' ? 'selected' : '' ?>>Boisson</option>
                    <option value="nourriture" <?= $service['categorie'] == 'nourriture' ? 'selected' : '' ?>>Nourriture</option>
                    <option value="spa" <?= $service['categorie'] == 'spa' ? 'selected' : '' ?>>Spa</option>
                    <option value="piscine" <?= $service['categorie'] == 'piscine' ? 'selected' : '' ?>>Piscine</option>
                    <option value="voiture" <?= $service['categorie'] == 'voiture' ? 'selected' : '' ?>>Voiture</option>
                    <option value="autre" <?= $service['categorie'] == 'autre' ? 'selected' : '' ?>>Autre</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="prix">Prix *</label>
                <input type="number" id="prix" name="prix" class="form-control" step="0.01" min="0" value="<?= h($service['prix']) ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-textarea" rows="4"><?= h($service['description']) ?></textarea>
            </div>
            
            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="disponible" name="disponible" value="1" <?= $service['disponible'] ? 'checked' : '' ?>>
                <label class="form-label mb-0" for="disponible">Disponible immédiatement</label>
            </div>
            
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary">Mettre à jour le service</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
