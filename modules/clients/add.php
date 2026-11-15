<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();
require_once '../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Nouveau client</h1>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">Retour</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="process_add.php">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label req">Nom de Famille</label>
                    <input type="text" name="nom" class="form-control" required placeholder="Ex: TRAORÉ">
                </div>
                <div class="form-group">
                    <label class="form-label req">Prénom(s)</label>
                    <input type="text" name="prenom" class="form-control" required placeholder="Ex: Moussa">
                </div>
                <div class="form-group">
                    <label class="form-label">Date de Naissance</label>
                    <input type="date" name="date_naissance" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Lieu de Naissance</label>
                    <input type="text" name="lieu_naissance" class="form-control" placeholder="Ex: Douala, Yaoundé...">
                </div>
                <div class="form-group">
                    <label class="form-label req">Numéro de Téléphone</label>
                    <input type="tel" name="telephone" class="form-control" required placeholder="Ex: +237 600 00 00 00">
                </div>
                <div class="form-group">
                    <label class="form-label">Nationalité</label>
                    <input type="text" name="nationalite" class="form-control" placeholder="Ex: Camerounaise" value="Camerounaise">
                </div>
                <div class="form-group">
                    <label class="form-label">Type de Pièce d'Identité</label>
                    <select name="type_piece" class="form-select">
                        <option value="CNI" selected>🪪 CNI (Carte Nationale d'Identité)</option>
                        <option value="Passeport">🛂 Passeport</option>
                        <option value="Permis">🚗 Permis de Conduire</option>
                        <option value="Carte de Séjour">🌍 Carte de Séjour / Consulaire</option>
                        <option value="Autre">📄 Autre pièce</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label req">Numéro CNI / Pièce d'Identité</label>
                    <input type="text" name="numero_piece" class="form-control" required placeholder="Ex: 1029384756" style="font-weight:700;">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="Ex: client@email.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse / Ville de Résidence</label>
                    <input type="text" name="adresse" class="form-control" placeholder="Ex: Douala, Akwa">
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 15px;">
                <label class="form-label">Préférences / Remarques Particulières</label>
                <textarea name="preferences" class="form-textarea" rows="2" placeholder="Ex: Étage calme, non fumeur..."></textarea>
            </div>

            <div class="form-actions" style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
