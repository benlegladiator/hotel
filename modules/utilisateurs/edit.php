<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

$id = post_int('id', $_GET['id'] ?? 0);
if (!$id) redirect('list.php');

$stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE id = ?");
$stmt->execute([$id]);
$u = $stmt->fetch();

if (!$u) redirect('list.php', 'danger', 'Utilisateur introuvable.');

$page_title = "Modifier l'Utilisateur " . $u['identifiant'];
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-header-title">Modifier l'Utilisateur</h1>
        <p class="text-secondary" style="margin-top:4px;">Modification des informations et réinitialisation du mot de passe pour <?= h($u['identifiant']) ?></p>
    </div>
    <div class="page-header-actions">
        <a href="list.php" class="btn btn-secondary">← Retour</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form action="process_edit.php" method="POST">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="identifiant">Identifiant *</label>
                    <input type="text" id="identifiant" name="identifiant" class="form-control" value="<?= h($u['identifiant']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="role">Rôle *</label>
                    <select id="role" name="role" class="form-select" required>
                        <option value="receptionniste" <?= $u['role']==='receptionniste'?'selected':'' ?>>Réceptionniste</option>
                        <option value="chef_receptionniste" <?= $u['role']==='chef_receptionniste'?'selected':'' ?>>Chef Réceptionniste</option>
                        <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Administrateur / Boss</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="nom">Nom *</label>
                    <input type="text" id="nom" name="nom" class="form-control" value="<?= h($u['nom']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="prenom">Prénom *</label>
                    <input type="text" id="prenom" name="prenom" class="form-control" value="<?= h($u['prenom']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= h($u['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="actif">Statut du Compte *</label>
                    <select id="actif" name="actif" class="form-select" required>
                        <option value="1" <?= $u['actif']?'selected':'' ?>>Actif</option>
                        <option value="0" <?= !$u['actif']?'selected':'' ?>>Désactivé</option>
                    </select>
                </div>
            </div>

            <div class="form-group mb-4" style="background:var(--bg-app); padding:16px; border-radius:var(--r-md); margin-top:16px;">
                <label class="form-label" style="font-weight:700;">Nouveau Mot de Passe (Optionnel)</label>
                <p class="text-secondary" style="font-size:12px; margin-bottom:8px;">Laissez vide pour conserver le mot de passe actuel.</p>
                <input type="password" name="nouveau_mot_de_passe" class="form-control" placeholder="Saisir uniquement si réinitialisation">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Mettre à jour le Compte</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
