<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect(BASE_URL . '/modules/clients/list.php');
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    set_flash('danger', 'Client introuvable');
    redirect(BASE_URL . '/modules/clients/list.php');
}

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Modifier client</h1>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">Retour</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="process_edit.php">
            <input type="hidden" name="id" value="<?= $client['id'] ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label req">Nom de Famille</label>
                    <input type="text" name="nom" class="form-control" value="<?= h($client['nom']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label req">Prénom(s)</label>
                    <input type="text" name="prenom" class="form-control" value="<?= h($client['prenom']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Date de Naissance</label>
                    <input type="date" name="date_naissance" class="form-control" value="<?= h($client['date_naissance'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Lieu de Naissance</label>
                    <input type="text" name="lieu_naissance" class="form-control" value="<?= h($client['lieu_naissance'] ?? '') ?>" placeholder="Ex: Douala, Yaoundé...">
                </div>
                <div class="form-group">
                    <label class="form-label req">Numéro de Téléphone</label>
                    <input type="tel" name="telephone" class="form-control" value="<?= h($client['telephone']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nationalité</label>
                    <input type="text" name="nationalite" class="form-control" value="<?= h($client['nationalite'] ?: 'Camerounaise') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label req">Numéro CNI / Pièce d'Identité</label>
                    <input type="text" name="piece_identite" class="form-control" value="<?= h($client['piece_identite']) ?>" required style="font-weight:700;">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= h($client['email']) ?>">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label class="form-label">Adresse / Ville de Résidence</label>
                <textarea name="adresse" class="form-textarea" rows="2" placeholder="Ex: Douala, Akwa"><?= h($client['adresse']) ?></textarea>
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label class="form-label">Préférences</label>
                <textarea name="preferences" class="form-textarea"><?= h($client['preferences']) ?></textarea>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
