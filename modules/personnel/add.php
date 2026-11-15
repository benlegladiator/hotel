<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Ajouter un Personnel</h1>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary btn-sm">Retour</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_add.php" method="post">
            <div class="form-grid-2" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label req">Nom</label>
                    <input type="text" name="nom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label req">Prénom</label>
                    <input type="text" name="prenom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label req">Rôle</label>
                    <select name="role" class="form-select" required>
                        <option value="cuisinier">Cuisinier</option>
                        <option value="menage">Ménage</option>
                        <option value="autre">Autre</option>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">
                        <input type="checkbox" name="actif" value="1" checked> Actif
                    </label>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea form-control" rows="4"></textarea>
                </div>
            </div>
            <div class="form-actions mt-3">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
