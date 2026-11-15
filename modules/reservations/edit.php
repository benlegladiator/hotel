<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

// Module Réservations désactivé temporairement selon la consigne
set_flash('info', "Le module Réservations est temporairement désactivé.");
redirect(BASE_URL . '/dashboard.php');
if (!$id) {
    redirect(BASE_URL . '/modules/reservations/list.php');
}

$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$id]);
$res = $stmt->fetch();

if (!$res) {
    set_flash('danger', 'Réservation introuvable');
    redirect(BASE_URL . '/modules/reservations/list.php');
}

$stmt = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom, prenom");
$clients = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Modifier réservation #<?= $res['id'] ?></h1>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">Retour</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="process_edit.php">
            <input type="hidden" name="id" value="<?= $res['id'] ?>">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Client</label>
                    <select name="id_client" class="form-select" disabled>
                        <?php foreach($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $res['id_client'] == $c['id'] ? 'selected' : '' ?>><?= h($c['nom'] . ' ' . $c['prenom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Type de réservation</label>
                    <input type="text" class="form-control" value="<?= h($res['type_reservation']) ?>" disabled>
                </div>

                <div class="form-group">
                    <label class="form-label req">Date d'arrivée</label>
                    <input type="date" name="date_arrivee" class="form-control" value="<?= $res['date_arrivee'] ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label req">Date de départ</label>
                    <input type="date" name="date_depart" class="form-control" value="<?= $res['date_depart'] ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nombre de personnes</label>
                    <input type="number" name="nb_personnes" class="form-control" min="1" value="<?= h($res['nb_personnes']) ?>">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label class="form-label">Remarques</label>
                <textarea name="remarques" class="form-textarea"><?= h($res['remarques']) ?></textarea>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
