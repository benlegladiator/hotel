<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

$id = $_GET['id'] ?? null;
if (!$id) redirect(BASE_URL . '/modules/chambres/list.php');

$stmt = $pdo->prepare("SELECT * FROM chambres WHERE id = ?");
$stmt->execute([$id]);
$chambre = $stmt->fetch();

if (!$chambre) {
    redirect(BASE_URL . '/modules/chambres/list.php', 'error', 'Chambre introuvable.');
}

$types = $pdo->query("SELECT id, nom, prix_nuit FROM types_chambres ORDER BY nom ASC")->fetchAll();

$page_title = 'Modifier une Chambre';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-header-title">Modifier la Chambre N° <?= h($chambre['numero']) ?></h1>
        <p class="text-secondary" style="margin-top:4px;">Gestion du numéro, type, photo et statut</p>
    </div>
    <div class="page-header-actions">
        <a href="tracabilite.php?numero=<?= h($chambre['numero']) ?>" class="btn btn-outline" style="margin-right:8px;">📊 Voir Traçabilité</a>
        <a href="list.php" class="btn btn-secondary">← Retour</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_edit.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= h($chambre['id']) ?>">
            
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="numero">Numéro de Chambre *</label>
                    <input type="text" id="numero" name="numero" class="form-control" value="<?= h($chambre['numero']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="id_type_chambre">Type de Chambre & Tarif *</label>
                    <select id="id_type_chambre" name="id_type_chambre" class="form-select" required>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= $type['id'] ?>" <?= $chambre['id_type_chambre'] == $type['id'] ? 'selected' : '' ?>>
                                <?= h($type['nom']) ?> (<?= format_money($type['prix_nuit']) ?> / nuit)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="etage">Étage *</label>
                    <input type="number" id="etage" name="etage" class="form-control" value="<?= h($chambre['etage']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="statut">Statut Actuel *</label>
                    <select id="statut" name="statut" class="form-select" required>
                        <option value="libre" <?= $chambre['statut'] === 'libre' ? 'selected' : '' ?>>Libre</option>
                        <option value="occupee" <?= $chambre['statut'] === 'occupee' ? 'selected' : '' ?>>Occupée</option>
                        <option value="nettoyage" <?= $chambre['statut'] === 'nettoyage' ? 'selected' : '' ?>>En Nettoyage</option>
                        <option value="maintenance" <?= $chambre['statut'] === 'maintenance' ? 'selected' : '' ?>>En Maintenance</option>
                    </select>
                </div>
            </div>

            <!-- Champ Photo de la Chambre -->
            <div class="form-group mb-4" style="background:var(--bg-app); padding:16px; border-radius:var(--r-md); border:1px dashed var(--border-mid);">
                <label class="form-label" style="font-weight:700; color:var(--gold-500);">📸 Photo de la Chambre</label>
                <p class="text-secondary" style="font-size:13px; margin-bottom:12px;">Modifier la photo de la chambre (remplacer par un fichier ou indiquer un nom de fichier).</p>
                <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                    <input type="file" name="photo_file" accept="image/*" class="form-control" style="flex:1; min-width:220px;" onchange="previewImage(this)">
                    <span style="font-size:13px; color:var(--text-secondary);">ou Fichier actuel :</span>
                    <input type="text" name="photo_name" id="photo_name" class="form-control" style="width:200px;" value="<?= h($chambre['photo'] ?? 'default.svg') ?>">
                </div>
                <div style="margin-top:12px; display:flex; align-items:center; gap:12px;">
                    <img id="img_preview" src="<?= BASE_URL ?>/assets/img/chambres/default.svg" alt="Photo Chambre" style="width:160px; height:100px; object-fit:cover; border-radius:var(--r-sm); border:1px solid var(--border-light);" onerror="this.src='<?= BASE_URL ?>/assets/img/chambres/default.svg'">
                    <span class="text-secondary" style="font-size:12px;">Photo actuelle enregistrée : <strong><?= h($chambre['photo'] ?? 'default.svg') ?></strong></span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description & Équipements de la Chambre</label>
                <textarea id="description" name="description" class="form-textarea" rows="3"><?= h($chambre['description']) ?></textarea>
            </div>

            <div class="form-actions" style="margin-top:20px;">
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;">Mettre à jour la Chambre</button>
            </div>
        </form>
    </div>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('img_preview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
