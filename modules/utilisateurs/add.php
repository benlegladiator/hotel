<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

$page_title = "Créer un Utilisateur";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-header-title">Créer un Compte Utilisateur</h1>
        <p class="text-secondary" style="margin-top:4px;">Ajout d'un nouvel accès administrateur ou réceptionniste</p>
    </div>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">← Retour aux comptes</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_add.php" method="POST">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="identifiant">Identifiant de connexion *</label>
                    <input type="text" id="identifiant" name="identifiant" class="form-control" placeholder="Ex: fatima_t" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="role">Rôle / Privilèges *</label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="receptionniste" selected>Réceptionniste (Opérations courantes)</option>
                        <option value="chef_receptionniste">Chef Réceptionniste (Versement au Boss & Supervision)</option>
                        <option value="admin">Administrateur / Boss (Accès complet & Coffre)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="nom">Nom *</label>
                    <input type="text" id="nom" name="nom" class="form-control" placeholder="Ex: Traoré" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="prenom">Prénom *</label>
                    <input type="text" id="prenom" name="prenom" class="form-control" placeholder="Ex: Fatima" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Adresse Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Ex: fatima@minabouhotel.com">
                </div>

                <div class="form-group">
                    <label class="form-label" for="mot_de_passe">Mot de Passe Initial *</label>
                    <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control" required placeholder="Minimum 4 caractères">
                </div>
            </div>

            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary" style="padding:10px 24px;">Créer l'Utilisateur</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
