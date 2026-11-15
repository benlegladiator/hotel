<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

$id = $_GET['id'] ?? null;
if (!$id) redirect('list.php');

$stmt = $pdo->prepare("SELECT * FROM personnel WHERE id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) redirect('list.php');

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Modifier le Personnel</h1>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary btn-sm">Retour</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_edit.php" method="post">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label req">Nom</label>
                    <input type="text" name="nom" class="form-control" value="<?= h($p['nom']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label req">Prénom</label>
                    <input type="text" name="prenom" class="form-control" value="<?= h($p['prenom']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" class="form-control" value="<?= h($p['telephone']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label req">Rôle</label>
                    <select name="role" class="form-select" required>
                        <option value="cuisinier" <?= $p['role'] === 'cuisinier' ? 'selected' : '' ?>>Cuisinier</option>
                        <option value="menage" <?= $p['role'] === 'menage' ? 'selected' : '' ?>>Ménage</option>
                        <option value="autre" <?= $p['role'] === 'autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">
                        <input type="checkbox" name="actif" value="1" <?= $p['actif'] ? 'checked' : '' ?>> Actif
                    </label>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea form-control" rows="4"><?= h($p['notes']) ?></textarea>
                </div>
            </div>
            <div class="form-actions mt-3">
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
