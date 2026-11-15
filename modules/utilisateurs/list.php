<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

$sql = "SELECT * FROM utilisateurs ORDER BY role ASC, nom ASC, prenom ASC";
$stmt = $pdo->query($sql);
$utilisateurs = $stmt->fetchAll();

$page_title = "Gestion des Comptes Utilisateurs";
require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-header-title">Comptes Utilisateurs</h1>
        <p class="text-secondary" style="margin-top:4px;">Création et administration des accès (Administrateurs & Réceptionnistes)</p>
    </div>
    <div class="page-header-actions" style="display:flex; gap:10px;">
        <a href="add.php" class="btn btn-primary">+ Nouvel Utilisateur</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">← Retour Dashboard</a>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h3 class="card-title">Utilisateurs du Système (<?= count($utilisateurs) ?> comptes)</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Identifiant</th>
                        <th>Nom & Prénom</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Langue / Thème</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($utilisateurs)): ?>
                        <tr>
                            <td colspan="8" class="text-center p-4 text-secondary">Aucun utilisateur enregistré.</td>
                        </tr>
                    <?php else: ?>
                        <?php $i=1; foreach ($utilisateurs as $u): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong style="color:var(--gold-500);"><?= h($u['identifiant']) ?></strong></td>
                                <td><strong><?= h($u['nom'] . ' ' . $u['prenom']) ?></strong></td>
                                <td><?= h($u['email'] ?? '—') ?></td>
                                <td>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        <span class="badge badge-occupee">Direction (Boss)</span>
                                    <?php elseif ($u['role'] === 'chef_receptionniste'): ?>
                                        <span class="badge badge-reservee" style="font-weight:700;">Chef Réceptionniste</span>
                                    <?php else: ?>
                                        <span class="badge badge-libre">Réceptionniste</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= h(strtoupper($u['langue'] ?? 'FR')) ?> / <?= h(ucfirst($u['theme'] ?? 'Dark')) ?></small></td>
                                <td>
                                    <?php if ($u['actif']): ?>
                                        <span class="badge badge-libre">Actif</span>
                                    <?php else: ?>
                                        <span class="badge badge-maintenance">Désactivé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-secondary">Modifier</a>
                                    <?php if ($u['id'] != current_user()['id']): ?>
                                        <a href="process_delete.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('<?= $u['actif'] ? 'Désactiver' : 'Réactiver' ?> cet utilisateur ?')">
                                            <?= $u['actif'] ? 'Désactiver' : 'Réactiver' ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
