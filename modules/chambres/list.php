<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/functions.php';
require_login();

$search = $_GET['q'] ?? '';
$statut = $_GET['statut'] ?? '';

$query = "SELECT c.*, tc.nom as type_nom, tc.prix_nuit, tc.prix_sieste 
          FROM chambres c 
          LEFT JOIN types_chambres tc ON c.id_type_chambre = tc.id
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (c.numero LIKE :search OR c.description LIKE :search)";
    $params['search'] = "%$search%";
}

if ($statut) {
    $query .= " AND c.statut = :statut";
    $params['statut'] = $statut;
}

$query .= " ORDER BY c.numero ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$chambres = $stmt->fetchAll();

$page_title = 'Liste des Chambres & Visuels';
require_once INCLUDES_PATH . '/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-header-title">Chambres & Visuels</h1>
        <p class="text-secondary" style="margin-top:4px;">Gestion des chambres, tarifs et photos</p>
    </div>
    <div class="page-header-actions">
        <a href="tracabilite.php" class="btn btn-outline" style="margin-right:8px;">📊 Grille de Traçabilité</a>
        <a href="add.php" class="btn btn-primary">+ Ajouter une Chambre</a>
        <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary" style="margin-left:8px;">← Retour Dashboard</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="table-toolbar">
            <form action="" method="GET" class="search-box" style="display:flex; gap:10px; width:100%;">
                <input type="text" name="q" class="form-control" placeholder="Recherche numéro ou description..." value="<?= h($search) ?>">
                <select name="statut" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="libre" <?= $statut === 'libre' ? 'selected' : '' ?>>Libre</option>
                    <option value="occupee" <?= $statut === 'occupee' ? 'selected' : '' ?>>Occupée</option>
                    <option value="nettoyage" <?= $statut === 'nettoyage' ? 'selected' : '' ?>>Nettoyage</option>
                    <option value="maintenance" <?= $statut === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filtrer</button>
            </form>
        </div>
    </div>
    
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Numéro</th>
                    <th>Type & Tarif</th>
                    <th>Étage</th>
                    <th>Statut Actuel</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($chambres)): ?>
                    <tr>
                        <td colspan="6" class="empty-state">Aucune chambre trouvée.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($chambres as $chambre): ?>
                        <tr>
                            <td style="width:70px;">
                                <img src="<?= BASE_URL ?>/assets/img/chambres/default.svg" alt="Chambre <?= h($chambre['numero']) ?>" class="room-thumb-mini" onerror="this.src='<?= BASE_URL ?>/assets/img/chambres/default.svg'">
                            </td>
                            <td><strong>N° <?= h($chambre['numero']) ?></strong></td>
                            <td>
                                <strong><?= h($chambre['type_nom']) ?></strong><br>
                                <span class="text-secondary" style="font-size:12.5px;">🌙 Nuit: <?= format_money($chambre['prix_nuit']) ?> | ⚡ Sieste: <?= format_money($chambre['prix_sieste'] ?? 10000) ?></span>
                            </td>
                            <td><?= $chambre['etage'] > 0 ? 'Étage ' . h($chambre['etage']) : 'RDC / Boucaro' ?></td>
                            <td><?= status_badge($chambre['statut']) ?></td>
                            <td class="no-print" style="display:flex; gap:6px; flex-wrap:wrap; align-items:center;">
                                <?php if ($chambre['statut'] === 'nettoyage'): ?>
                                    <a href="<?= BASE_URL ?>/modules/menage/quick_clean.php?id_chambre=<?= $chambre['id'] ?>" class="btn btn-sm" style="background:#10b981; color:#fff; font-weight:700; border-radius:6px; padding:4px 10px;" onclick="return confirm('Confirmez-vous que vous avez nettoyé la Chambre <?= $chambre['numero'] ?> ?');">
                                        ✔ Marquer Propre
                                    </a>
                                <?php endif; ?>
                                <a href="history.php?id=<?= $chambre['id'] ?>" class="btn btn-outline btn-sm" title="Voir l'historique d'occupation de cette chambre">📜 Historique</a>
                                <a href="edit.php?id=<?= $chambre['id'] ?>" class="btn btn-secondary btn-sm">Modifier</a>
                                <a href="process_delete.php?id=<?= $chambre['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Confirmer la suppression de la chambre ?')">Supprimer</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once INCLUDES_PATH . '/footer.php'; ?>
