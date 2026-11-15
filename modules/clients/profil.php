<?php
require_once '../../config/app.php';
require_once '../../config/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

require_login();

$id = $_GET['id'] ?? null;
if (!$id) {
    redirect(BASE_URL . '/modules/clients/list.php');
}

$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    set_flash('danger', 'Client introuvable');
    redirect(BASE_URL . '/modules/clients/list.php');
}

// Stats
$stmt_stats = $pdo->prepare("SELECT SUM(montant_final) as total FROM factures WHERE id_client = ? AND statut_paiement = 'payee'");
$stmt_stats->execute([$id]);
$stats = $stmt_stats->fetch();
$total_depense = $stats['total'] ?? 0;

// Factures
$stmt_fac = $pdo->prepare("SELECT * FROM factures WHERE id_client = ? ORDER BY id DESC LIMIT 10");
$stmt_fac->execute([$id]);
$factures = $stmt_fac->fetchAll();

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-header-title">Profil: <?= h($client['nom'] . ' ' . $client['prenom']) ?></h1>
    <div class="page-header-actions">
        <a href="edit.php?id=<?= $client['id'] ?>" class="btn btn-secondary">Modifier</a>
        <a href="<?= BASE_URL ?>/modules/checkin_checkout/express_checkin.php" class="btn btn-primary">🏨 Arrivée Express</a>
        <a href="list.php" class="btn btn-secondary">Retour</a>
    </div>
</div>

<div class="stats-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div class="stat-card card">
        <div class="card-body">
            <h3>Séjours</h3>
            <p style="font-size: 24px; font-weight: bold;"><?= $client['nb_sejours'] ?></p>
        </div>
    </div>
    <div class="stat-card card">
        <div class="card-body">
            <h3>Total dépensé</h3>
            <p style="font-size: 24px; font-weight: bold;"><?= format_money($total_depense) ?></p>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <div class="card-header"><h3 class="card-title">Informations Personnelles & Pièce d'Identité</h3></div>
    <div class="card-body">
        <p><strong>🪪 Numéro CNI / Pièce :</strong> <strong style="font-size:15px; color:#1e293b; background:#e2e8f0; padding:3px 10px; border-radius:6px;"><?= h($client['piece_identite'] ?: 'Non renseigné') ?></strong></p>
        <p><strong>🎂 Date & Lieu de Naissance :</strong> <?= !empty($client['date_naissance']) ? format_date($client['date_naissance']) : '—' ?><?= !empty($client['lieu_naissance']) ? ' à ' . h($client['lieu_naissance']) : '' ?></p>
        <p><strong>📞 Téléphone :</strong> <?= h($client['telephone'] ?: '—') ?></p>
        <p><strong>🌍 Nationalité :</strong> <?= h($client['nationalite'] ?: '—') ?></p>
        <p><strong>🏠 Adresse / Ville :</strong> <?= nl2br(h($client['adresse'] ?: '—')) ?></p>
        <p><strong>✉️ Email :</strong> <?= h($client['email'] ?: '—') ?></p>
        <p><strong>📝 Préférences / Notes :</strong> <?= nl2br(h($client['preferences'] ?: '—')) ?></p>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
