<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();
require_admin();

$role_filter = $_GET['role'] ?? '';

$sql = "SELECT * FROM personnel";
$params = [];
if ($role_filter) {
    $sql .= " WHERE role = ?";
    $params[] = $role_filter;
}
$sql .= " ORDER BY role, nom, prenom";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personnels = $stmt->fetchAll();

$page_title = "Liste du Personnel";
require_once '../../includes/header.php';
?>

<!-- En-tête d'impression A4 -->
<div class="print-header">
    <h1><?= h(get_system_setting('nom_hotel', 'Minabou Hotel')) ?></h1>
    <p><?= h(get_system_setting('adresse_hotel', '')) ?> — Tél: <?= h(get_system_setting('telephone_hotel', '')) ?></p>
    <h2>Liste Officielle du Personnel d'Établissement</h2>
    <p>Éditée le <?= date('d/m/Y à H:i') ?></p>
</div>

<div class="page-header no-print" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; margin-bottom:20px;">
    <div>
        <h1 class="page-header-title">Liste du Personnel</h1>
        <p class="text-secondary" style="margin-top:4px;">Gestion des agents, rôles, états actifs et impression</p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn-print" onclick="window.print()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Imprimer la Liste du Personnel
        </button>
        <a href="add.php" class="btn btn-primary">+ Ajouter un Agent</a>
    </div>
</div>

<div class="card">
    <div class="card-header no-print" style="display:flex; justify-content:space-between; align-items:center;">
        <h2 class="card-title">Filtre des rôles</h2>
        <form method="get" class="form-inline" style="display:flex; gap:10px;">
            <select name="role" class="form-select" onchange="this.form.submit()">
                <option value="">Tous les rôles</option>
                <option value="menage" <?= $role_filter === 'menage' ? 'selected' : '' ?>>Ménage / Entretien</option>
                <option value="cuisinier" <?= $role_filter === 'cuisinier' ? 'selected' : '' ?>>Cuisinier / Restauration</option>
                <option value="autre" <?= $role_filter === 'autre' ? 'selected' : '' ?>>Autre service</option>
            </select>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>N°</th>
                        <th>Nom & Prénom</th>
                        <th>Téléphone</th>
                        <th>Rôle / Service</th>
                        <th>Statut</th>
                        <th>Notes & Observations</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($personnels)): ?>
                        <tr>
                            <td colspan="7" class="text-center empty-state p-4">Aucun agent enregistré dans cette catégorie.</td>
                        </tr>
                    <?php else: ?>
                        <?php $i=1; foreach ($personnels as $p): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><strong><?= h($p['nom'] . ' ' . $p['prenom']) ?></strong></td>
                                <td><?= h($p['telephone'] ?? '—') ?></td>
                                <td>
                                    <span style="font-weight:600; text-transform:capitalize; color:var(--gold-500);">
                                        <?= h($p['role'] === 'menage' ? 'Agent de Ménage' : ucfirst($p['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($p['actif']): ?>
                                        <span class="badge badge-libre">Actif</span>
                                    <?php else: ?>
                                        <span class="badge badge-occupee">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($p['notes'] ?? '—') ?></td>
                                <td class="no-print">
                                    <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-secondary">Modifier</a>
                                    <?php if ($p['actif']): ?>
                                        <a href="process_delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Désactiver cet agent ?')">Désactiver</a>
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

<div class="print-footer">
    <p>Minabou Hotel — Document RH interne. Imprimé le <?= date('d/m/Y à H:i') ?></p>
</div>

<?php require_once '../../includes/footer.php'; ?>
