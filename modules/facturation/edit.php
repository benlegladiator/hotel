<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$id = $_GET['id'] ?? null;
if (!$id) redirect('list.php');

$stmt = $pdo->prepare("SELECT * FROM factures WHERE id = ?");
$stmt->execute([$id]);
$f = $stmt->fetch();

if (!$f) redirect('list.php');

$clients = $pdo->query("SELECT id, nom, prenom FROM clients ORDER BY nom")->fetchAll();

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Modifier la Facture #F-<?= str_pad($f['id'], 4, '0', STR_PAD_LEFT) ?></h1>
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
                    <label class="form-label req">Client (Fixe)</label>
                    <select name="id_client" class="form-select" disabled>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $f['id_client'] ? 'selected' : '' ?>>
                                <?= h($c['nom'] . ' ' . $c['prenom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label req">Date d'émission</label>
                    <input type="date" name="date_emission" class="form-control" value="<?= h($f['date_emission']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Montant Total (Fixe)</label>
                    <input type="number" class="form-control" value="<?= h($f['montant_total']) ?>" disabled>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Réduction (%)</label>
                    <input type="number" step="0.01" name="reduction_pct" class="form-control" value="<?= h($f['reduction_pct']) ?>" min="0" max="100">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Motif de réduction</label>
                    <input type="text" name="reduction_motif" class="form-control" value="<?= h($f['reduction_motif']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label req">Mode de Paiement</label>
                    <select name="mode_paiement" class="form-select" required>
                        <option value="especes" <?= $f['mode_paiement'] == 'especes' ? 'selected' : '' ?>>Espèces</option>
                        <option value="carte" <?= $f['mode_paiement'] == 'carte' ? 'selected' : '' ?>>Carte</option>
                        <option value="mobile" <?= $f['mode_paiement'] == 'mobile' ? 'selected' : '' ?>>Mobile Money</option>
                        <option value="autre" <?= $f['mode_paiement'] == 'autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Référence Paiement</label>
                    <input type="text" name="reference_paiement" class="form-control" value="<?= h($f['reference_paiement']) ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label req">Statut Paiement</label>
                    <select name="statut_paiement" class="form-select" required>
                        <option value="impayee" <?= $f['statut_paiement'] == 'impayee' ? 'selected' : '' ?>>Impayée</option>
                        <option value="partielle" <?= $f['statut_paiement'] == 'partielle' ? 'selected' : '' ?>>Partielle</option>
                        <option value="payee" <?= $f['statut_paiement'] == 'payee' ? 'selected' : '' ?>>Payée</option>
                    </select>
                </div>
            </div>
            <div class="form-actions mt-3">
                <button type="submit" class="btn btn-primary">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
