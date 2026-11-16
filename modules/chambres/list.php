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

<!-- ── Bouton Retour Dédié ── -->
<div class="no-print">
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn-page-back">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        <span>← Retour au Tableau de bord</span>
    </a>
</div>

<div class="page-header">
    <div>
        <h1 class="page-header-title">Chambres & Visuels</h1>
        <p class="text-secondary" style="margin-top:4px;">Gestion des chambres, tarifs et photos</p>
    </div>
    <div class="page-header-actions">
        <a href="tracabilite.php" class="btn btn-outline">📊 Grille de Traçabilité</a>
        <a href="add.php" class="btn btn-primary">+ Ajouter une Chambre</a>
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
    
    <!-- ── VUE MOBILE CARTE RESPONSIVE (POUR SMARTPHONE) ── -->
    <div class="mobile-only room-cards-container" style="padding: 12px; gap: 12px;">
        <?php if (empty($chambres)): ?>
            <div style="text-align: center; padding: 30px; color: #94a3b8;">Aucune chambre trouvée.</div>
        <?php else: ?>
            <?php foreach ($chambres as $chambre): ?>
                <div class="card" style="margin-bottom: 12px; border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px; background: #ffffff; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 44px; height: 44px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; border: 1px solid #e2e8f0;">
                                🛏️
                            </div>
                            <div>
                                <strong style="font-size: 16px; color: #0f172a;">Chambre <?= h($chambre['numero']) ?></strong>
                                <div style="font-size: 12px; color: #4f46e5; font-weight: 700;"><?= h($chambre['type_nom']) ?></div>
                            </div>
                        </div>
                        <div>
                            <?= status_badge($chambre['statut']) ?>
                        </div>
                    </div>
                    <div style="background: #f8fafc; border-radius: 8px; padding: 8px 12px; margin-bottom: 10px; font-size: 12px; color: #475569; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 4px;">
                        <span>🌙 Nuit : <strong><?= format_money($chambre['prix_nuit']) ?></strong></span>
                        <span>⚡ Sieste : <strong><?= format_money($chambre['prix_sieste'] ?? 10000) ?></strong></span>
                        <span>🏢 <?= $chambre['etage'] > 0 ? 'Étage ' . h($chambre['etage']) : 'RDC / Boucaro' ?></span>
                    </div>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;" class="no-print">
                        <?php if ($chambre['statut'] === 'nettoyage'): ?>
                            <a href="<?= BASE_URL ?>/modules/menage/quick_clean.php?id_chambre=<?= $chambre['id'] ?>" class="btn btn-sm" style="background:#10b981; color:#fff; font-weight:700; border-radius:8px; padding:6px 12px; flex: 1 1 auto; text-align: center;" onclick="return confirm('Confirmez-vous que vous avez nettoyé la Chambre <?= $chambre['numero'] ?> ?');">
                                ✔ Marquer Propre
                            </a>
                        <?php endif; ?>
                        <a href="history.php?id=<?= $chambre['id'] ?>" class="btn btn-sm btn-outline" style="border-radius: 8px; flex: 1 1 auto; text-align: center;">📜 Historique</a>
                        <a href="edit.php?id=<?= $chambre['id'] ?>" class="btn btn-sm btn-secondary" style="border-radius: 8px; flex: 1 1 auto; text-align: center;">Modifier</a>
                        <a href="process_delete.php?id=<?= $chambre['id'] ?>" class="btn btn-sm btn-danger" style="border-radius: 8px;" onclick="return confirm('Confirmer la suppression de la chambre ?')">🗑</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ── VUE TABLEAU DESKTOP (POUR ÉCRANS LARGES) ── -->
    <div class="table-wrapper desktop-only">
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
