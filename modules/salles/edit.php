<?php
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

$id = $_GET['id'] ?? null;
if (!$id) redirect(BASE_URL . '/modules/salles/list.php');

$stmt = $pdo->prepare("SELECT * FROM salles WHERE id = ?");
$stmt->execute([$id]);
$salle = $stmt->fetch();

if (!$salle) {
    redirect(BASE_URL . '/modules/salles/list.php', 'error', 'Salle introuvable.');
}

$page_title = 'Modifier une Salle';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Modifier : <?= h($salle['nom']) ?></h1>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">← Retour</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_edit.php" method="POST">
            <input type="hidden" name="id" value="<?= h($salle['id']) ?>">
            
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="nom">Nom de la salle *</label>
                    <input type="text" id="nom" name="nom" class="form-control" value="<?= h($salle['nom']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="capacite">Capacité *</label>
                    <input type="number" id="capacite" name="capacite" class="form-control" value="<?= h($salle['capacite']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="prix_location">Prix de location *</label>
                    <input type="number" step="0.01" id="prix_location" name="prix_location" class="form-control" value="<?= h($salle['prix_location']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="statut">Statut *</label>
                    <select id="statut" name="statut" class="form-select" required>
                        <option value="libre" <?= $salle['statut'] === 'libre' ? 'selected' : '' ?>>Libre</option>
                        <option value="reservee" <?= $salle['statut'] === 'reservee' ? 'selected' : '' ?>>Réservée</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-textarea" rows="3"><?= h($salle['description']) ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
