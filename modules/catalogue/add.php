<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_admin();

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h2 class="page-header-title">Ajouter un service</h2>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">Retour au catalogue</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_add.php" method="POST">
            <div class="form-group">
                <label class="form-label" for="nom">Nom *</label>
                <input type="text" id="nom" name="nom" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="categorie">Catégorie *</label>
                <select id="categorie" name="categorie" class="form-select" required>
                    <option value="">Sélectionner...</option>
                    <option value="boisson">Boisson</option>
                    <option value="nourriture">Nourriture</option>
                    <option value="spa">Spa</option>
                    <option value="piscine">Piscine</option>
                    <option value="voiture">Voiture</option>
                    <option value="autre">Autre</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="prix">Prix *</label>
                <input type="number" id="prix" name="prix" class="form-control" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-textarea" rows="4"></textarea>
            </div>
            
            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" id="disponible" name="disponible" value="1" checked>
                <label class="form-label mb-0" for="disponible">Disponible immédiatement</label>
            </div>
            
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary">Enregistrer le service</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
