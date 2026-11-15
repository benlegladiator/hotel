<?php
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

$id = $_GET['id'] ?? null;
if (!$id) redirect(BASE_URL . '/modules/types_chambres/list.php');

$stmt = $pdo->prepare("SELECT * FROM types_chambres WHERE id = ?");
$stmt->execute([$id]);
$type = $stmt->fetch();

if (!$type) {
    redirect(BASE_URL . '/modules/types_chambres/list.php', 'error', 'Type de chambre introuvable.');
}

$page_title = 'Modifier un Type de Chambre';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Modifier : <?= h($type['nom']) ?></h1>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">← Retour</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_edit.php" method="POST">
            <input type="hidden" name="id" value="<?= h($type['id']) ?>">
            
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="nom">Nom *</label>
                    <input type="text" id="nom" name="nom" class="form-control" value="<?= h($type['nom']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="prix_nuit">Prix / Nuit *</label>
                    <input type="number" step="0.01" id="prix_nuit" name="prix_nuit" class="form-control" value="<?= h($type['prix_nuit']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="capacite">Capacité *</label>
                    <input type="number" id="capacite" name="capacite" class="form-control" value="<?= h($type['capacite']) ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-textarea" rows="3"><?= h($type['description']) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="avantages">Avantages (séparés par virgule)</label>
                <textarea id="avantages" name="avantages" class="form-textarea" rows="2"><?= h($type['avantages']) ?></textarea>
                <small>Ex: Wifi, TV, Balcon, Climatisation</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
