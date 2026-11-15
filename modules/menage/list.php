<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

require_login();

$statut = $_GET['statut'] ?? '';

$sql = "
    SELECT t.*, ch.numero as chambre_numero, p.nom as agent_nom, p.prenom as agent_prenom
    FROM taches_menage t
    JOIN chambres ch ON t.id_chambre = ch.id
    LEFT JOIN personnel p ON t.id_agent = p.id
    WHERE 1=1
";
$params = [];

if ($statut) {
    $sql .= " AND t.statut = ?";
    $params[] = $statut;
}

$sql .= " ORDER BY t.date_prevue ASC, t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$taches = $stmt->fetchAll();

require_once '../../includes/header.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
    <div>
        <h1 class="page-header-title">Tâches de Ménage</h1>
        <p class="text-secondary" style="margin-top:4px;">Gestion et attribution des tâches de nettoyage</p>
    </div>
    <div class="page-header-actions" style="display:flex; gap:10px;">
        <a href="assign.php" class="btn btn-primary">+ Assigner une tâche</a>
        <a href="tracabilite.php" class="btn btn-outline">📊 Traçabilité</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">← Retour Dashboard</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-4" style="display: flex; gap: 10px;">
            <div class="form-group mb-0" style="flex: 1;">
                <select name="statut" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="assignee" <?= $statut == 'assignee' ? 'selected' : '' ?>>Assignée</option>
                    <option value="en_cours" <?= $statut == 'en_cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="terminee" <?= $statut == 'terminee' ? 'selected' : '' ?>>Terminée</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Filtrer</button>
        </form>

        <?php if (empty($taches)): ?>
            <div class="empty-state">
                <p>Aucune tâche de ménage trouvée.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Chambre</th>
                            <th>Agent</th>
                            <th>Type</th>
                            <th>Date prévue</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($taches as $tache): ?>
                            <tr>
                                <td><strong>Chambre <?= h($tache['chambre_numero']) ?></strong></td>
                                <td><?= $tache['id_agent'] ? h($tache['agent_prenom'] . ' ' . $tache['agent_nom']) : '<em class="text-muted">Non assigné</em>' ?></td>
                                <td>
                                    <?php
                                    $typeBadge = 'badge-secondary';
                                    if ($tache['type_tache'] == 'entree') $typeBadge = 'badge-blue';
                                    if ($tache['type_tache'] == 'sortie') $typeBadge = 'badge-amber';
                                    if ($tache['type_tache'] == 'periodique') $typeBadge = 'badge-gray';
                                    ?>
                                    <span class="badge <?= $typeBadge ?>"><?= ucfirst(h($tache['type_tache'])) ?></span>
                                </td>
                                <td><?= format_date($tache['date_prevue']) ?></td>
                                <td><?= status_badge($tache['statut']) ?></td>
                                <td>
                                    <?php if ($tache['statut'] == 'assignee'): ?>
                                        <a href="process_update.php?id=<?= $tache['id'] ?>&statut=en_cours" class="btn btn-sm btn-secondary">Mettre "En cours"</a>
                                    <?php elseif ($tache['statut'] == 'en_cours'): ?>
                                        <a href="process_update.php?id=<?= $tache['id'] ?>&statut=terminee" class="btn btn-sm btn-success">Mettre "Terminée"</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
